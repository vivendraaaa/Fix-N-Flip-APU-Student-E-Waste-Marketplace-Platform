<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);

$mysqli = new mysqli("localhost", "root", "", "fyp");

if ($mysqli->connect_error) {
    echo json_encode(['error' => 'Connection failed: ' . $mysqli->connect_error]);
    exit;
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id <= 0) {
    echo json_encode(['error' => 'Invalid order ID']);
    exit;
}

// Debug: Check if order exists
$check_query = "SELECT o.*, o.total_amount as total FROM orders o WHERE o.id = ?";
$check_stmt = $mysqli->prepare($check_query);
if (!$check_stmt) {
    echo json_encode(['error' => 'Orders table prepare failed: ' . $mysqli->error]);
    exit;
}

$check_stmt->bind_param('i', $id);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

if (!$check_result) {
    echo json_encode(['error' => 'Orders query failed: ' . $check_stmt->error]);
    exit;
}

$order = $check_result->fetch_assoc();

if (!$order) {
    echo json_encode(['error' => 'Order not found', 'id' => $id]);
    exit;
}

// Add username
$user_query = "SELECT username FROM users WHERE id = ?";
$user_stmt = $mysqli->prepare($user_query);
$user_stmt->bind_param('i', $order['user_id']);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user = $user_result->fetch_assoc();
$order['username'] = $user['username'] ?? 'Unknown';

// Try to get order items - first check if table exists
$check_items_table = $mysqli->query("SHOW TABLES LIKE 'order_items'");
if ($check_items_table && $check_items_table->num_rows > 0) {
    $items_query = "SELECT oi.*, oi.price_at_purchase as price, p.name as product_name FROM order_items oi LEFT JOIN products p ON oi.product_id = p.id WHERE oi.order_id = ?";
    $items_stmt = $mysqli->prepare($items_query);
    
    if (!$items_stmt) {
        $order['items'] = [];
        $order['items_error'] = 'Items prepare failed: ' . $mysqli->error;
    } else {
        $items_stmt->bind_param('i', $id);
        $items_stmt->execute();
        $items_result = $items_stmt->get_result();
        
        if (!$items_result) {
            $order['items'] = [];
            $order['items_error'] = 'Items query failed: ' . $items_stmt->error;
        } else {
            $items = [];
            while ($item = $items_result->fetch_assoc()) {
                $items[] = $item;
            }
            $order['items'] = $items;
        }
        $items_stmt->close();
    }
} else {
    $order['items'] = [];
    $order['items_error'] = 'order_items table not found';
}

$user_stmt->close();
$check_stmt->close();
$mysqli->close();

echo json_encode($order);
?>