<?php
// checkout.php – review cart OR single buy-now item, enter address, place order,
// show voucher + email buyer + email seller(s)

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/email_helper.php'; 
// must contain: sendVoucherEmail() and sendSellerSaleEmail()

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = (int)$_SESSION['user_id'];

$info_msg         = '';
$error_msg        = '';
$order_done       = false;
$voucher_items    = [];
$voucher_total    = 0;
$shipping_name    = '';
$shipping_phone   = '';
$shipping_address = '';
$voucher_code     = '';

/* ✅ BUY NOW MODE DETECT */
$is_buy_now      = isset($_GET['buy_now']) && $_GET['buy_now'] == '1';
$buy_now_item_id = isset($_GET['item_id']) ? (int)$_GET['item_id'] : 0;


// 1) Load user info for prefill
$stmt = $conn->prepare("
    SELECT username, email, phone, address
    FROM users
    WHERE id = ?
    LIMIT 1
");
if (!$stmt) {
    die("Database error: " . $conn->error);
}
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($u_username, $u_email, $u_phone, $u_address);
$stmt->fetch();
$stmt->close();

// Prefill shipping values
$shipping_name    = $u_username;
$shipping_phone   = $u_phone ?? '';
$shipping_address = $u_address ?? '';


// Helper to load cart items (WITH seller info)
function load_cart_items(mysqli $conn, int $user_id): array {
    $sql = "
        SELECT c.item_id, c.quantity,
               i.title, i.price, i.image,
               i.seller_id,
               u.email AS seller_email,
               u.username AS seller_name
        FROM cart_items c
        JOIN items i ON c.item_id = i.id
        JOIN users u ON i.seller_id = u.id
        WHERE c.user_id = ?
    ";
    $stmt = $conn->prepare($sql);
    if (!$stmt) return [];
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $items = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $items;
}

// Helper to load SINGLE item for buy now (WITH seller info)
function load_single_item(mysqli $conn, int $item_id): array {
    $sql = "
        SELECT i.id AS item_id, 1 AS quantity,
               i.title, i.price, i.image,
               i.seller_id,
               u.email AS seller_email,
               u.username AS seller_name
        FROM items i
        JOIN users u ON i.seller_id = u.id
        WHERE i.id = ? AND i.status='active'
        LIMIT 1
    ";
    $stmt = $conn->prepare($sql);
    if (!$stmt) return [];
    $stmt->bind_param("i", $item_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close();
    return $row ? [$row] : [];
}


// 2) If POST: handle place order
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {

    $shipping_name    = trim($_POST['shipping_name'] ?? '');
    $shipping_phone   = trim($_POST['shipping_phone'] ?? '');
    $shipping_address = trim($_POST['shipping_address'] ?? '');
    $save_profile     = isset($_POST['save_profile']);

    if ($shipping_name === '' || $shipping_phone === '' || $shipping_address === '') {
        $error_msg = "Please fill in your name, phone and address.";
    } else {

        // ✅ Load items depending on mode
        if ($is_buy_now) {
            $cart_items = load_single_item($conn, $buy_now_item_id);
            if (empty($cart_items)) {
                $error_msg = "This item is not available.";
            }
        } else {
            $cart_items = load_cart_items($conn, $user_id);
            if (empty($cart_items)) {
                $error_msg = "Your cart is empty.";
            }
        }

        if ($error_msg === '') {

            // Optional: update user profile with shipping info
            if ($save_profile) {
                $stmt = $conn->prepare("
                    UPDATE users
                    SET phone = ?, address = ?
                    WHERE id = ?
                ");
                if ($stmt) {
                    $stmt->bind_param("ssi", $shipping_phone, $shipping_address, $user_id);
                    $stmt->execute();
                    $stmt->close();
                }
            }

            // Calculate subtotal/total
            $subtotal = 0.0;
            foreach ($cart_items as $ci) {
                $subtotal += (float)$ci['price'] * (int)$ci['quantity'];
            }
            $discount = 0.0;
            $total    = $subtotal - $discount;

            // Generate voucher code
            $voucher_code = 'RA-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid('', true)), 0, 6));
            $status = 'pending';

            // ✅ START TRANSACTION (so stock/orders stay consistent)
            $conn->begin_transaction();

            try {
                // Insert into orders
                $stmt = $conn->prepare("
                    INSERT INTO orders (buyer_id, subtotal_amount, discount_amount, total_amount, voucher_code, status)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                if (!$stmt) {
                    throw new Exception("Database error while creating order: " . $conn->error);
                }

                $stmt->bind_param(
                    "idddss",
                    $user_id,
                    $subtotal,
                    $discount,
                    $total,
                    $voucher_code,
                    $status
                );

                if (!$stmt->execute()) {
                    throw new Exception("Could not save order. Please try again.");
                }

                $order_id = $conn->insert_id;
                $stmt->close();

                // Insert order items
                $stmt_items = $conn->prepare("
                    INSERT INTO order_items (order_id, item_id, quantity, price)
                    VALUES (?, ?, ?, ?)
                ");
                if (!$stmt_items) {
                    throw new Exception("Database error while saving order items: " . $conn->error);
                }

                $voucher_items = [];

                foreach ($cart_items as $ci) {
                    $item_id    = (int)$ci['item_id'];
                    $qty        = (int)$ci['quantity'];
                    $price      = (float)$ci['price'];
                    $line_total = $price * $qty;

                    // 1) save order item
                    $stmt_items->bind_param("iiid", $order_id, $item_id, $qty, $price);
                    if (!$stmt_items->execute()) {
                        throw new Exception("Failed to insert order item.");
                    }

                    // 2) ✅ reduce stock + mark sold if needed
                    $update = $conn->prepare("
                        UPDATE items
                        SET stock = stock - ?,
                            status = CASE 
                                WHEN stock - ? <= 0 THEN 'sold'
                                ELSE status
                            END
                        WHERE id = ? AND status = 'active'
                    ");
                    if (!$update) {
                        throw new Exception("Failed to update item stock/status.");
                    }
                    $update->bind_param("iii", $qty, $qty, $item_id);
                    if (!$update->execute()) {
                        throw new Exception("Stock update failed.");
                    }
                    $update->close();

                    $voucher_items[] = [
                        'title'      => $ci['title'],
                        'price'      => $price,
                        'quantity'   => $qty,
                        'line_total' => $line_total,
                    ];
                }

                $stmt_items->close();

                // ✅ Clear cart only for normal checkout
                if (!$is_buy_now) {
                    $stmt_del = $conn->prepare("DELETE FROM cart_items WHERE user_id = ?");
                    if ($stmt_del) {
                        $stmt_del->bind_param("i", $user_id);
                        $stmt_del->execute();
                        $stmt_del->close();
                    }
                }

                // ✅ COMMIT everything
                $conn->commit();

                $voucher_total = $total;
                $order_done    = true;

                // --------------------
                // EMAIL BUYER VOUCHER
                // --------------------
                if (!empty($u_email)) {
                    $buyer_email_sent = sendVoucherEmail(
                        $u_email,
                        $shipping_name ?: $u_username,
                        $voucher_code,
                        $voucher_items,
                        $voucher_total
                    );

                    if ($buyer_email_sent) {
                        $info_msg = "Voucher has been emailed to " . $u_email . ".";
                    } else {
                        $info_msg = "Order placed, but we couldn't email your voucher. You can print it below.";
                    }
                }

                // --------------------
                // EMAIL SELLER(S)
                // --------------------
                $sellersMap = [];

                foreach ($cart_items as $ci) {
                    $sid = (int)$ci['seller_id'];

                    if (!isset($sellersMap[$sid])) {
                        $sellersMap[$sid] = [
                            'email' => $ci['seller_email'],
                            'name'  => $ci['seller_name'],
                            'items' => [],
                            'total' => 0
                        ];
                    }

                    $line_total = (float)$ci['price'] * (int)$ci['quantity'];

                    $sellersMap[$sid]['items'][] = [
                        'title'      => $ci['title'],
                        'price'      => (float)$ci['price'],
                        'quantity'   => (int)$ci['quantity'],
                        'line_total' => $line_total
                    ];

                    $sellersMap[$sid]['total'] += $line_total;
                }

                foreach ($sellersMap as $seller) {
                    if (!empty($seller['email'])) {
                        sendSellerSaleEmail(
                            $seller['email'],
                            $seller['name'],
                            $shipping_name ?: $u_username,
                            $shipping_phone,
                            $shipping_address,
                            $voucher_code,
                            $seller['items'],
                            $seller['total']
                        );
                    }
                }

            } catch (Exception $e) {
                // ✅ rollback if ANY step fails
                $conn->rollback();
                $error_msg = $e->getMessage();
            }
        }
    }
}


// 3) If not done yet, show items for review
if (!$order_done) {

    if ($is_buy_now) {
        $voucher_items = load_single_item($conn, $buy_now_item_id);
    } else {
        $voucher_items = load_cart_items($conn, $user_id);
    }

    $voucher_total = 0;
    foreach ($voucher_items as $vi) {
        $voucher_total += (float)$vi['price'] * (int)$vi['quantity'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Checkout</title>
    <link rel="stylesheet" href="/Ecommerce/User/css/checkout.css">
</head>
<body>

<main class="page">
    <h1>Checkout</h1>

    <?php if (!empty($error_msg)): ?>
        <div class="alert error"><?= htmlspecialchars($error_msg) ?></div>
    <?php endif; ?>
    <?php if (!empty($info_msg)): ?>
        <div class="alert success"><?= htmlspecialchars($info_msg) ?></div>
    <?php endif; ?>

    <?php if ($order_done): ?>
        <section class="card voucher">
            <header class="voucher-header">
                <div>
                    <h2>Order Voucher</h2>
                    <p>Thank you for your purchase!</p>
                    <p><strong>Voucher #:</strong> <?= htmlspecialchars($voucher_code) ?></p>
                </div>
                <div class="voucher-meta">
                    <p><strong>Date:</strong> <?= date('Y-m-d H:i') ?></p>
                    <p><strong>Buyer:</strong> <?= htmlspecialchars($shipping_name) ?></p>
                </div>
            </header>

            <section class="voucher-body">
                <div class="voucher-block">
                    <h3>Shipping information</h3>
                    <p><strong>Phone:</strong> <?= htmlspecialchars($shipping_phone) ?></p>
                    <p><strong>Address:</strong><br><?= nl2br(htmlspecialchars($shipping_address)) ?></p>
                </div>

                <div class="voucher-block">
                    <h3>Items</h3>
                    <table class="voucher-table">
                        <thead>
                        <tr>
                            <th>Item</th>
                            <th>Price</th>
                            <th>Qty</th>
                            <th>Total</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($voucher_items as $vi): ?>
                            <tr>
                                <td><?= htmlspecialchars($vi['title']) ?></td>
                                <td><?= number_format($vi['price'], 0) ?> MMK</td>
                                <td><?= (int)$vi['quantity'] ?></td>
                                <td><?= number_format($vi['line_total'], 0) ?> MMK</td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                        <tr>
                            <th colspan="3" class="right">Grand total</th>
                            <th><?= number_format($voucher_total, 0) ?> MMK</th>
                        </tr>
                        </tfoot>
                    </table>
                </div>
            </section>

            <footer class="voucher-footer">
                <button onclick="window.print()" class="btn-primary">Print voucher</button>
                <a href="home.php" class="btn-ghost">Back to home</a>
            </footer>
        </section>

    <?php else: ?>

        <?php if (empty($voucher_items)): ?>
            <p>
                <?= $is_buy_now ? "This item is not available." : "Your cart is empty." ?>
                <a href="home.php">Go back to the shop →</a>
            </p>
        <?php else: ?>
            <section class="grid-2">

                <div class="card">
                    <h2>Order summary</h2>
                    <table class="voucher-table">
                        <thead>
                        <tr>
                            <th>Item</th>
                            <th>Price</th>
                            <th>Qty</th>
                            <th>Total</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($voucher_items as $vi): ?>
                            <tr>
                                <td><?= htmlspecialchars($vi['title']) ?></td>
                                <td><?= number_format($vi['price'], 0) ?> MMK</td>
                                <td><?= (int)$vi['quantity'] ?></td>
                                <td><?= number_format($vi['price'] * $vi['quantity'], 0) ?> MMK</td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                        <tr>
                            <th colspan="3" class="right">Total</th>
                            <th><?= number_format($voucher_total, 0) ?> MMK</th>
                        </tr>
                        </tfoot>
                    </table>
                </div>

                <div class="card">
                    <h2>Shipping details</h2>
                    <form method="post" class="form">
                        <div class="form-group">
                            <label for="shipping_name">Name *</label>
                            <input type="text" id="shipping_name" name="shipping_name"
                                   value="<?= htmlspecialchars($shipping_name) ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="shipping_phone">Phone *</label>
                            <input type="text" id="shipping_phone" name="shipping_phone"
                                   value="<?= htmlspecialchars($shipping_phone) ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="shipping_address">Address *</label>
                            <textarea id="shipping_address" name="shipping_address" rows="4" required><?= htmlspecialchars($shipping_address) ?></textarea>
                        </div>

                        <div class="form-group checkbox-group">
                            <label>
                                <input type="checkbox" name="save_profile" value="1">
                                Save these details to my profile
                            </label>
                        </div>

                        <button type="submit" name="place_order" class="btn-primary">
                            Place order &amp; generate voucher
                        </button>

                        <?php if ($is_buy_now): ?>
                            <a href="home.php" class="btn-ghost">Back to home</a>
                        <?php else: ?>
                            <a href="cart.php" class="btn-ghost">Back to cart</a>
                        <?php endif; ?>
                    </form>
                </div>

            </section>
        <?php endif; ?>

    <?php endif; ?>
</main>
</body>
</html>
