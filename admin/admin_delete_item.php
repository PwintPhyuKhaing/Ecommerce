<?php
require_once __DIR__ . '/config.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !is_admin()) {
    header("Location: /Ecommerce/User/access-denied.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: admin_home.php");
    exit;
}

$item_id = (int)($_POST['id'] ?? 0);
if ($item_id <= 0) {
    header("Location: admin_home.php");
    exit;
}

// delete item (FK cascade is fine)
$stmt = $conn->prepare("DELETE FROM items WHERE id=?");
$stmt->bind_param("i", $item_id);
$stmt->execute();
$stmt->close();

header("Location: admin_home.php");
exit;
