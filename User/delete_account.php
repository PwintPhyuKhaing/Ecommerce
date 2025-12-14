<?php
require_once __DIR__ . '/../admin/config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = (int)$_SESSION['user_id'];

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // ✅ confirm password for safety
    $password = $_POST['password'] ?? '';

    $stmt = $conn->prepare("SELECT password_hash, profile_photo FROM users WHERE id=? LIMIT 1");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($hash, $profile_photo);
    $stmt->fetch();
    $stmt->close();

    if (!$hash || !password_verify($password, $hash)) {
        die("Password မမှန်ပါ။ Account delete မလုပ်နိုင်ပါ။");
    }

    // ✅ Use transaction so all-or-nothing
    $conn->begin_transaction();

    try {
        // 1) cart
        $stmt = $conn->prepare("DELETE FROM cart_items WHERE user_id=?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();

        // 2) password resets
        $stmt = $conn->prepare("DELETE FROM password_resets WHERE user_id=?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();

        // 3) voucher redemptions (if your project uses it)
        $stmt = $conn->prepare("DELETE FROM voucher_redemptions WHERE user_id=?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();

        /**
         * 4) Orders + order_items (buyer)
         * Order_items usually references orders.id,
         * so delete order_items first, then orders.
         */
        $stmt = $conn->prepare("
            DELETE oi FROM order_items oi
            INNER JOIN orders o ON oi.order_id = o.id
            WHERE o.buyer_id=?
        ");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare("DELETE FROM orders WHERE buyer_id=?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();

        /**
         * 5) Items + item_images (seller)
         * item_images references items.id,
         * so delete item_images first, then items.
         */
        $stmt = $conn->prepare("
            DELETE ii FROM item_images ii
            INNER JOIN items i ON ii.item_id = i.id
            WHERE i.seller_id=?
        ");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare("DELETE FROM items WHERE seller_id=?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();

        // 6) finally delete user record
        $stmt = $conn->prepare("DELETE FROM users WHERE id=?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();

        // ✅ Commit DB deletes
        $conn->commit();

        /**
         * 7) delete profile photo file from uploads (optional but good)
         * only if it's a local upload
         */
        if (!empty($profile_photo)) {
            // profile_photo example: /ecommerce/uploads/profiles/user_1_123.png
            $path = realpath(__DIR__ . "/..") . str_replace("/ecommerce", "", $profile_photo);
            if ($path && file_exists($path)) {
                @unlink($path);
            }
        }

        // ✅ logout
        session_unset();
        session_destroy();

        header("Location: login.php?deleted=1");
        exit;

    } catch (Exception $e) {
        $conn->rollback();
        die("Account delete failed: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Delete Account</title>
    <link rel="stylesheet" href="/Ecommerce/User/css/login.css">
</head>
<body>

<div class="auth-wrapper">
    <div class="auth-card">
        <h2>Delete Account</h2>
        <p class="subtitle" style="color:red;">
             Doing this will permanatly all your data including( your cart, your listing, ect).
        </p>

        <form method="post">
            <div class="input-group">
                <label>Confirm your password</label>
                <input type="password" name="password" required>
            </div>

            <div class="actions">
                <button type="submit" class="btn primary" style="background:#e74c3c;">
                    Yes, Delete Everything
                </button>
                <a href="profile.php" class="btn secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

</body>
</html>
