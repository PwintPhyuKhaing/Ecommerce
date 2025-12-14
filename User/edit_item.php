<?php
// edit_item.php – edit a product the user has posted

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../admin/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = (int)$_SESSION['user_id'];

// Get item id from query
$item_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($item_id <= 0) {
    die("Invalid item.");
}

$success_msg = '';
$error_msg   = '';

// 1) Load existing item, making sure it belongs to this user
$stmt = $conn->prepare("
    SELECT title, description, price, category_label, item_condition, location, image
    FROM items
    WHERE id = ? AND seller_id = ?
    LIMIT 1
");
if (!$stmt) {
    die("Database error: " . $conn->error);
}
$stmt->bind_param("ii", $item_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
$item   = $result->fetch_assoc();
$stmt->close();

if (!$item) {
    die("Item not found or you do not have permission to edit it.");
}

// Pre-fill variables
$title       = $item['title'];
$description = $item['description'];
$price       = $item['price'];
$category    = $item['category_label'];
$condition   = $item['item_condition'];
$location    = $item['location'];
$current_img = $item['image'];

// 2) Handle form submit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_item'])) {
    $title       = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price       = trim($_POST['price'] ?? '');
    $category    = trim($_POST['category'] ?? '');
    $condition   = trim($_POST['condition'] ?? '');
    $location    = trim($_POST['location'] ?? '');

    if ($title === '' || $price === '' || $category === '' || $condition === '' || $description === '') {
        $error_msg = "Please fill in all required fields.";
    } elseif (!is_numeric($price) || (float)$price <= 0) {
        $error_msg = "Price must be a positive number.";
    } else {
        $image_path_for_db = $current_img;

        // If user uploads a new image, replace old one
        if (!empty($_FILES['image']['name'])) {
            $upload_dir_fs  = __DIR__ . '/../assets/img/';
            $upload_dir_url = '/Ecommerce/assets/img/';

            if (!is_dir($upload_dir_fs)) {
                mkdir($upload_dir_fs, 0777, true);
            }

            $original_name = basename($_FILES['image']['name']);
            $ext           = pathinfo($original_name, PATHINFO_EXTENSION);
            $safe_name     = preg_replace(
                '/[^a-zA-Z0-9_\-]/',
                '_',
                pathinfo($original_name, PATHINFO_FILENAME)
            );
            $new_filename  = $safe_name . '_' . time() . '.' . $ext;

            $target_fs  = $upload_dir_fs . $new_filename;
            $target_url = $upload_dir_url . $new_filename;

            if (move_uploaded_file($_FILES['image']['tmp_name'], $target_fs)) {
                $image_path_for_db = $target_url;
            } else {
                $error_msg = "Could not upload image. Please try again.";
            }
        }

        if ($error_msg === '') {
            $sql = "
                UPDATE items
                SET title = ?, description = ?, price = ?, category_label = ?, 
                    item_condition = ?, location = ?, image = ?
                WHERE id = ? AND seller_id = ?
            ";
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                $error_msg = "Database error: " . $conn->error;
            } else {
                $price_float = (float)$price;
                $stmt->bind_param(
                    "ssdssssii",
                    $title,
                    $description,
                    $price_float,
                    $category,
                    $condition,
                    $location,
                    $image_path_for_db,
                    $item_id,
                    $user_id
                );
            }
        }
    }

    if ($error_msg === '' && isset($stmt) && $stmt) {
        if ($stmt->execute()) {
            $success_msg = "Item updated successfully.";
        } else {
            $error_msg = "Could not update item. Please try again.";
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Item - Second Hand Market</title>
    <link rel="stylesheet" href="/Ecommerce/User/css/edit_items.css">
</head>
<body>
<header class="top-nav">
    <div class="logo">SECOND HAND PRODUCTS MARKET</div>
    <nav>
        <a href="home.php">Home</a>
        <a href="my_listings.php">My Listings</a>
        <a href="purchases.php">Purchases</a>
        <a href="cart.php">Cart</a>
        <a href="index.php" class="btn-ghost">Logout</a>
    </nav>
</header>

<main class="page">
    <h1>Edit item</h1>

    <?php if (!empty($success_msg)): ?>
        <div class="alert success"><?= htmlspecialchars($success_msg) ?></div>
    <?php endif; ?>
    <?php if (!empty($error_msg)): ?>
        <div class="alert error"><?= htmlspecialchars($error_msg) ?></div>
    <?php endif; ?>

    <section class="card">
        <form method="post" enctype="multipart/form-data" class="form">
            <div class="form-group">
                <label for="title">Title *</label>
                <input type="text" id="title" name="title"
                       value="<?= htmlspecialchars($title) ?>" required>
            </div>

            <div class="form-group">
                <label for="description">Description *</label>
                <textarea id="description" name="description" rows="4" required><?= htmlspecialchars($description) ?></textarea>
            </div>

            <div class="form-group">
                <label for="price">Price (MMK) *</label>
                <input type="number" step="0.01" min="0" id="price" name="price"
                       value="<?= htmlspecialchars($price) ?>" required>
            </div>

            <div class="form-group">
                <label for="category">Category *</label>
                <input type="text" id="category" name="category"
                       value="<?= htmlspecialchars($category) ?>" required>
                <!-- or swap to a <select> like on post.php if you prefer -->
            </div>

            <div class="form-group">
                <label for="condition">Condition *</label>
                <input type="text" id="condition" name="condition"
                       value="<?= htmlspecialchars($condition) ?>" required>
            </div>

            <div class="form-group">
                <label for="location">Location</label>
                <input type="text" id="location" name="location"
                       value="<?= htmlspecialchars($location) ?>">
            </div>

            <div class="form-group">
                <label>Current image</label><br>
                <?php if (!empty($current_img)): ?>
                    <img src="<?= htmlspecialchars($current_img) ?>" alt="Current image"
                         style="max-width:200px;display:block;margin-bottom:8px;">
                <?php else: ?>
                    <p>No image uploaded yet.</p>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="image">Replace image (optional)</label>
                <input type="file" id="image" name="image" accept="image/*">
            </div>

            <button type="submit" name="update_item" class="btn-primary">
                Save changes
            </button>
            <a href="my_listings=.php" class="btn-ghost" style="margin-left:8px;">Back to my listings</a>
        </form>
    </section>
</main>
</body>
</html>
