<?php
session_start();
require_once __DIR__ . '/../admin/config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$item_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($item_id <= 0) {
    die("Invalid item.");
}

// 1) Get the image path (so we can delete file later)
$stmt = $conn->prepare("SELECT image FROM items WHERE id = ? AND seller_id = ?");
$stmt->bind_param("ii", $item_id, $user_id);
$stmt->execute();
$stmt->bind_result($image_path);
$stmt->fetch();
$stmt->close();

// 2) Delete the DB row
$stmt = $conn->prepare("DELETE FROM items WHERE id = ? AND seller_id = ?");
$stmt->bind_param("ii", $item_id, $user_id);
$stmt->execute();
$stmt->close();

// 3) Delete the image file from storage
if (!empty($image_path)) {
    // Convert URL → real file path
    $fs_path = $_SERVER['DOCUMENT_ROOT'] . $image_path;

    if (file_exists($fs_path)) {
        unlink($fs_path); // delete image from server
    }
}

// 4) Redirect back
header("Location: my_listings.php?deleted=1");
exit;
