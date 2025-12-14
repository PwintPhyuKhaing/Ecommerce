<?php
session_start();
require __DIR__ . "/../admin/config.php"; // adjust if needed

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = (int)$_SESSION['user_id'];

// ✅ Fetch all items user created (include status + stock)
$sql = "
    SELECT id, title, price, image, created_at, status, stock
    FROM items
    WHERE seller_id = ?
    ORDER BY created_at DESC
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$listings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Listings</title>
    <link rel="stylesheet" href="/Ecommerce/User/css/my_listings.css">
</head>
<body>

<header class="top-nav">
    <div class="logo">SECOND HAND PRODUCTS MARKET</div>
    <nav>
        <a href="home.php">Home</a>
        <a href="my_listings.php" class="active">My Listings</a>
        <a href="purchases.php">Purchases</a>
        <a href="cart.php">Cart</a>
        <a href="index.php" class="btn-ghost">Logout</a>
    </nav>
</header>

<main class="page">
    <h1>My Listings</h1>

    <?php if (empty($listings)): ?>
        <p>You haven't posted anything yet.</p>
    <?php else: ?>
        <div class="posts-grid">
            <?php foreach ($listings as $item): ?>
                <?php
                  // ✅ decide if sold
                  $is_sold = (($item['status'] ?? '') === 'sold' || (int)($item['stock'] ?? 0) <= 0);
                ?>
                <article class="post-card">

                    <div class="post-image">
                        <?php if (!empty($item['image'])): ?>
                            <img src="<?= htmlspecialchars($item['image']) ?>" alt="">
                        <?php else: ?>
                            <div class="placeholder-img">No Image</div>
                        <?php endif; ?>
                    </div>

                    <div class="post-body">
                        <h3><?= htmlspecialchars($item['title']) ?></h3>
                        <p class="price"><?= number_format($item['price']) ?> MMK</p>

                        <!-- ✅ SOLD badge -->
                        <?php if ($is_sold): ?>
                            <p class="sold-badge">SOLD OUT</p>
                        <?php endif; ?>

                        <div class="actions">
                            <?php if ($is_sold): ?>
                                <!-- ✅ disable actions if sold -->
                                <span class="btn ghost disabled">Sold</span>
                            <?php else: ?>
                                <a class="btn primary" href="edit_item.php?id=<?= (int)$item['id'] ?>">Edit</a>
                                <a class="btn ghost"
                                   href="delete_item.php?id=<?= (int)$item['id'] ?>"
                                   onclick="return confirm('Are you sure you want to delete this item?')">
                                   Delete
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>

                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>
</body>
</html>
