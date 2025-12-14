<?php
// cart.php – items saved to buy later

// Show errors while developing (optional)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// central config: starts session + connects to DB
require_once __DIR__ . '/../admin/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php'); // /Ecommerce/User/login.php
    exit;
}

$user_id  = (int)$_SESSION['user_id'];
$info_msg = '';
$error_msg = '';

// HANDLE CART ACTIONS
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Remove single item
    if (isset($_POST['remove'])) {
        $cart_item_id = (int)$_POST['cart_item_id'];
        $stmt = $conn->prepare("DELETE FROM cart_items WHERE id = ? AND user_id = ?");
        if ($stmt) {
            $stmt->bind_param("ii", $cart_item_id, $user_id);
            $stmt->execute();
            $stmt->close();
            $info_msg = "Item removed from cart.";
        } else {
            $error_msg = "Database error: " . $conn->error;
        }
    }

    // Clear cart
    if (isset($_POST['clear_cart'])) {
        $stmt = $conn->prepare("DELETE FROM cart_items WHERE user_id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $stmt->close();
            $info_msg = "Cart cleared.";
        } else {
            $error_msg = "Database error: " . $conn->error;
        }
    }

    // Fake checkout (placeholder)
    if (isset($_POST['checkout'])) {
    header('Location: checkout.php');
    exit;
}

}

// LOAD CART ITEMS
$query = "
    SELECT 
        c.id AS cart_item_id,
        c.quantity,
        i.id    AS item_id,
        i.title,
        i.price,
        i.image -- column name in our schema
    FROM cart_items c
    JOIN items i ON c.item_id = i.id
    WHERE c.user_id = ?
";
$stmt = $conn->prepare($query);
if (!$stmt) {
    die("Database error: " . $conn->error);
}
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result     = $stmt->get_result();
$cart_items = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// CALCULATE TOTAL
$cart_total = 0;
foreach ($cart_items as $ci) {
    $cart_total += $ci['price'] * $ci['quantity'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Cart</title>
    <link rel="stylesheet" href="/Ecommerce/User/css/user-dashboard.css">
</head>
<body>
<header class="top-nav">
    <div class="logo">SECOND HAND PRODUCTS MARKET</div>
    <nav>
        <a href="/Ecommerce/User/home.php">Home</a>
        
        <a href="my_listings.php">My Listings</a>
        <a href="purchases.php">Purchases</a>
        <a href="cart.php">Cart</a>
        <a href="index.php" class="btn-ghost">Logout</a>
    </nav>
</header>

<main class="page">
    <h1>My cart</h1>

    <?php if ($info_msg): ?>
        <div class="alert success"><?= htmlspecialchars($info_msg) ?></div>
    <?php endif; ?>
    <?php if ($error_msg): ?>
        <div class="alert error"><?= htmlspecialchars($error_msg) ?></div>
    <?php endif; ?>

    <?php if (empty($cart_items)): ?>
        <p>Your cart is empty. Browse items and add the ones you like.</p>
    <?php else: ?>
        <div class="cart-layout">
            <div class="cart-items">
                <?php foreach ($cart_items as $ci): ?>
                    <article class="cart-card">
                        <div class="cart-img">
                            <?php if (!empty($ci['image'])): ?>
                                <img src="<?= htmlspecialchars($ci['image']) ?>" alt="<?= htmlspecialchars($ci['title']) ?>">
                            <?php else: ?>
                                <div class="placeholder-img">No image</div>
                            <?php endif; ?>
                        </div>
                        <div class="cart-body">
                            <h2><?= htmlspecialchars($ci['title']) ?></h2>
                            <p class="price">$<?= number_format($ci['price'], 2) ?></p>
                            <p class="qty">Qty: <?= (int)$ci['quantity'] ?></p>
                            <form method="post" class="inline-form">
                                <input type="hidden" name="cart_item_id" value="<?= $ci['cart_item_id'] ?>">
                                <button type="submit" name="remove" class="btn-ghost small">Remove</button>
                            </form>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>

            <aside class="cart-summary card">
                <h2>Summary</h2>
                <p class="summary-total">Total: $<?= number_format($cart_total, 2) ?></p>
                <form method="post">
                    <button type="submit" name="checkout" class="btn-primary full-width">Checkout (placeholder)</button>
                    <button type="submit" name="clear_cart" class="btn-d full-width"
                            onclick="return confirm('Clear entire cart?');">
                        Clear cart
                    </button>
                </form>
            </aside>
        </div>
    <?php endif; ?>
</main>
</body>
</html>
