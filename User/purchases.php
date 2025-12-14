<?php
// purchases.php – items the user bought

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

$user_id = (int)$_SESSION['user_id'];

// Each order can have multiple items, so we join orders + order_items + items
$query = "
    SELECT 
        o.id AS order_id,
        o.created_at,
        oi.quantity,
        (oi.price * oi.quantity) AS total_price,
        i.title,
        oi.price AS item_price
    FROM orders o
    JOIN order_items oi ON oi.order_id = o.id
    JOIN items i       ON oi.item_id  = i.id
    WHERE o.buyer_id = ?
    ORDER BY o.created_at DESC
";

$stmt = $conn->prepare($query);
if (!$stmt) {
    die('Database error: ' . $conn->error);
}
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result    = $stmt->get_result();
$purchases = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Purchases</title>
    <link rel="stylesheet" href="/Ecommerce/User/css/user-dashboard.css">
</head>
<body>
<header class="top-nav">
    <div class="logo">SECOND HAND PRODUCTS MARKET</div>
    <nav>
        <a href="home.php">Home</a>
        
        <a href="my_listings.php">My Listings</a>
        <a href="purchases.php" class="active">Purchases</a>
        <a href="cart.php">Cart</a>
        <a href="index.php" class="btn-ghost">Logout</a>
    </nav>
</header>

<main class="page">
    <h1>What I bought</h1>

    <?php if (empty($purchases)): ?>
        <p>You haven’t bought anything yet.</p>
    <?php else: ?>
        <div class="table-wrapper">
            <table class="table">
                <thead>
                <tr>
                    <th>Item</th>
                    <th>Item price</th>
                    <th>Quantity</th>
                    <th>Total paid</th>
                    <th>Date</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($purchases as $p): ?>
                    <tr>
                        <td><?= htmlspecialchars($p['title']) ?></td>
                        <td>$<?= number_format($p['item_price'], 2) ?></td>
                        <td><?= (int)$p['quantity'] ?></td>
                        <td>$<?= number_format($p['total_price'], 2) ?></td>
                        <td><?= htmlspecialchars($p['created_at']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</main>
</body>
</html>
