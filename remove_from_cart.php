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

// Get cart ID from POST
$cart_id = isset($_POST['cart_id']) ? intval($_POST['cart_id']) : 0;

if ($cart_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid cart ID']);
    exit;
}

// Remove item from cart
$remove_cart = "DELETE FROM cart WHERE id = ? AND user_id = ?";
$stmt = $mysqli->prepare($remove_cart);
$stmt->bind_param("ii", $cart_id, $user_id);
$stmt->execute();

if ($stmt->affected_rows > 0) {
    echo json_encode(['success' => true, 'message' => 'Item removed from cart']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to remove item']);
}
