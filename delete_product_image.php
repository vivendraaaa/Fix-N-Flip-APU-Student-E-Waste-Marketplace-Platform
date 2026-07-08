<?php
session_start();
$mysqli = new mysqli("localhost", "root", "", "fyp");

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['image_id'])) {
    $image_id = intval($_POST['image_id']);
    $mysqli->query("DELETE FROM product_images WHERE id = $image_id");
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false]);
}
?>
