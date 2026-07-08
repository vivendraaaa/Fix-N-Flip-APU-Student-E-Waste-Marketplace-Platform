<?php
$mysqli = new mysqli('localhost', 'root', '', 'fyp');

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

// Update the device_type ENUM to include 'Smartwatch'
$sql = "ALTER TABLE device_submissions MODIFY COLUMN device_type ENUM('Smartphone','Tablet','Laptop','Smartwatch','Other') NOT NULL";

if ($mysqli->query($sql)) {
    echo "Success: device_type ENUM updated to include 'Smartwatch'";
} else {
    echo "Error: " . $mysqli->error;
}

$mysqli->close();
?>
