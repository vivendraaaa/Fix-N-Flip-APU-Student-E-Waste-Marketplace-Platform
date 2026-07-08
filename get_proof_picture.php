<?php
// Script to serve proof pictures from database
header('Content-Type: image/jpeg');

$mysqli = new mysqli("localhost", "root", "", "fyp");

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;

if ($order_id > 0) {
    $query = "SELECT proof_pictures FROM orders WHERE id = ?";
    $stmt = $mysqli->prepare($query);
    
    if ($stmt) {
        $stmt->bind_param('i', $order_id);
        $stmt->execute();
        $stmt->bind_result($proof_pictures);
        $stmt->fetch();
        $stmt->close();
        
        if ($proof_pictures) {
            echo $proof_pictures;
        } else {
            // Return a placeholder image if no proof picture exists
            header('Content-Type: image/png');
            echo base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
        }
    }
}

$mysqli->close();
?>
