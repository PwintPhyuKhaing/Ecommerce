<?php
// add_to_cart_ajax.php – AJAX add to cart (returns JSON)

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/auth.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'ok' => false,
        'message' => 'not_logged_in'
    ]);
    exit;
}

$user_id = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'ok' => false,
        'message' => 'invalid_method'
    ]);
    exit;
}

$item_id = isset($_POST['item_id']) ? (int)$_POST['item_id'] : 0;
$quantity = 1;

if ($item_id <= 0) {
    echo json_encode([
        'ok' => false,
        'message' => 'invalid_item'
    ]);
    exit;
}

// validate item active + stock
$stmt = $conn->prepare("SELECT stock FROM items WHERE id = ? AND status='active' LIMIT 1");
if (!$stmt) {
    echo json_encode(['ok'=>false,'message'=>'db_error']);
    exit;
}

$stmt->bind_param("i", $item_id);
$stmt->execute();
$res = $stmt->get_result();
$item = $res->fetch_assoc();
$stmt->close();

if (!$item) {
    echo json_encode(['ok'=>false,'message'=>'not_available']);
    exit;
}
if ((int)$item['stock'] <= 0) {
    echo json_encode(['ok'=>false,'message'=>'out_of_stock']);
    exit;
}

// check already in cart
$stmt = $conn->prepare("
    SELECT id, quantity 
    FROM cart_items 
    WHERE user_id = ? AND item_id = ?
    LIMIT 1
");
if (!$stmt) {
    echo json_encode(['ok'=>false,'message'=>'db_error']);
    exit;
}

$stmt->bind_param("ii", $user_id, $item_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$stmt->close();

if ($row) {
    $cart_item_id = (int)$row['id'];
    $new_quantity = (int)$row['quantity'] + $quantity;

    $stmt = $conn->prepare("UPDATE cart_items SET quantity = ? WHERE id = ?");
    if (!$stmt) {
        echo json_encode(['ok'=>false,'message'=>'db_error']);
        exit;
    }
    $stmt->bind_param("ii", $new_quantity, $cart_item_id);
    $stmt->execute();
    $stmt->close();

    echo json_encode([
        'ok' => true,
        'message' => 'updated',
        'new_quantity' => $new_quantity
    ]);
    exit;
}

// insert new row
$stmt = $conn->prepare("
    INSERT INTO cart_items (user_id, item_id, quantity)
    VALUES (?, ?, ?)
");
if (!$stmt) {
    echo json_encode(['ok'=>false,'message'=>'db_error']);
    exit;
}
$stmt->bind_param("iii", $user_id, $item_id, $quantity);
$stmt->execute();
$stmt->close();

echo json_encode([
    'ok' => true,
    'message' => 'added',
    'new_quantity' => 1
]);
exit;
