<?php
session_start();

header('Content-Type: application/json');

// Database connection
$mysqli = new mysqli("localhost", "root", "", "fyp");

if ($mysqli->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    echo json_encode(['success' => false, 'message' => 'Please login to update cart']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Get cart ID and quantity from POST
$cart_id = isset($_POST['cart_id']) ? intval($_POST['cart_id']) : 0;
$quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;

if ($cart_id <= 0 || $quantity < 1) {
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit;
}

// Update cart quantity
$update_cart = "UPDATE cart SET quantity = ? WHERE id = ? AND user_id = ?";
$stmt = $mysqli->prepare($update_cart);
$stmt->bind_param("iii", $quantity, $cart_id, $user_id);
$stmt->execute();

if ($stmt->affected_rows > 0) {
    echo json_encode(['success' => true, 'message' => 'Cart updated']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update cart']);
}
