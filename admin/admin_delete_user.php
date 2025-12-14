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

$admin_id = (int)($_SESSION['user_id'] ?? 0);
$user_id = (int)($_POST['user_id'] ?? 0);

if ($user_id <= 0 || $user_id === $admin_id) {
    header("Location: admin_home.php");
    exit;
}

// deleting user will cascade-delete their items because fk_items_seller ON DELETE CASCADE
$stmt = $conn->prepare("DELETE FROM users WHERE id=?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->close();

header("Location: admin_home.php");
exit;
