<?php
$mysqli = new mysqli("localhost", "root", "", "fyp");

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

// Modify the status column to include 'completed' in the ENUM
$alter_query = "ALTER TABLE orders MODIFY COLUMN status ENUM('pending','confirmed','packing','processing','ready_for_pickup','shipped','delivered','completed','cancelled') DEFAULT 'pending'";

if ($mysqli->query($alter_query)) {
    echo "✓ Successfully added 'completed' to status ENUM<br>";
} else {
    echo "✗ Error modifying status column: " . $mysqli->error . "<br>";
}

$mysqli->close();
?>
