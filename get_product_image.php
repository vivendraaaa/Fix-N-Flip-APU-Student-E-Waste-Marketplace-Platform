<?php
session_start();
$mysqli = new mysqli("localhost", "root", "", "fyp");

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

if (!isset($_GET['id'])) {
    header('HTTP/1.0 404 Not Found');
    exit;
}

$id = intval($_GET['id']);

header('Content-Type: image/jpeg');

// First try to get from product_images table
$query = "SELECT image FROM product_images WHERE id = ?";
$stmt = $mysqli->prepare($query);
$stmt->bind_param('i', $id);
$stmt->execute();
$stmt->bind_result($data);
$stmt->fetch();
$stmt->close();

// If not found in product_images, try old products table
if (!$data) {
    $query = "SELECT image FROM products WHERE id = ?";
    $stmt = $mysqli->prepare($query);
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->bind_result($data);
    $stmt->fetch();
    $stmt->close();
}

if ($data) {
    echo $data;
}
