<?php
// admin_edit_item.php – admin edit ANY item

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/config.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !is_admin()) {
    header("Location: /Ecommerce/User/access-denied.php");
    exit;
}

$item_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($item_id <= 0) die("Invalid item.");

// fetch item (no seller restriction)
$stmt = $conn->prepare("SELECT * FROM items WHERE id=? LIMIT 1");
$stmt->bind_param("i", $item_id);
$stmt->execute();
$item = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$item) die("Item not found.");

$error = "";
$success = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = (float)($_POST['price'] ?? 0);
    $stock = (int)($_POST['stock'] ?? 1);
    $status = $_POST['status'] ?? 'active';

    if ($title === '' || $price <= 0) {
        $error = "Title and valid price required.";
    } else {
        $up = $conn->prepare("
          UPDATE items SET title=?, description=?, price=?, stock=?, status=?
          WHERE id=?
        ");
        $up->bind_param("ssdisi", $title, $description, $price, $stock, $status, $item_id);
        if ($up->execute()) {
            $success = "Updated successfully.";
            // reload
            $stmt = $conn->prepare("SELECT * FROM items WHERE id=? LIMIT 1");
            $stmt->bind_param("i", $item_id);
            $stmt->execute();
            $item = $stmt->get_result()->fetch_assoc();
            $stmt->close();
        } else {
            $error = "Update failed: " . $conn->error;
        }
        $up->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Edit Item</title>
  <link rel="stylesheet" href="/Ecommerce/User/css/home.css">
  <style>
    .edit-box{max-width:600px;margin:30px auto;background:#ffffffcc;padding:20px;border-radius:12px;}
    input,textarea,select{width:100%;padding:10px;margin-top:6px;margin-bottom:12px;border-radius:8px;border:1px solid #ccc;}
    .btn{padding:8px 14px;border-radius:8px;border:none;cursor:pointer;}
  </style>
</head>
<body>
  <div class="edit-box">
    <h2>Edit Post (Admin)</h2>

    <?php if ($error): ?><p style="color:red;"><?= htmlspecialchars($error) ?></p><?php endif; ?>
    <?php if ($success): ?><p style="color:green;"><?= htmlspecialchars($success) ?></p><?php endif; ?>

    <form method="POST">
      <label>Title</label>
      <input type="text" name="title" value="<?= htmlspecialchars($item['title']) ?>">

      <label>Description</label>
      <textarea name="description" rows="4"><?= htmlspecialchars($item['description'] ?? '') ?></textarea>

      <label>Price</label>
      <input type="number" step="0.01" name="price" value="<?= htmlspecialchars($item['price']) ?>">

      <label>Stock</label>
      <input type="number" name="stock" value="<?= htmlspecialchars($item['stock']) ?>">

      <label>Status</label>
      <select name="status">
        <?php foreach (['active','inactive','sold'] as $st): ?>
          <option value="<?= $st ?>" <?= $item['status']===$st?'selected':''; ?>><?= ucfirst($st) ?></option>
        <?php endforeach; ?>
      </select>

      <button class="btn" type="submit">Save</button>
      <a class="btn" href="admin_home.php">Back</a>
    </form>
  </div>
</body>
</html>
