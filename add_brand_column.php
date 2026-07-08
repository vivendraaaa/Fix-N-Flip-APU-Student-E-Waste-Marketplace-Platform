<?php
$mysqli = new mysqli("localhost", "root", "", "fyp");

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

// Check if brand column exists in technician_listings table
$check_brand = $mysqli->query("SHOW COLUMNS FROM technician_listings LIKE 'brand'");

if ($check_brand && $check_brand->num_rows === 0) {
    // Column doesn't exist, add it
    $add_column = "ALTER TABLE technician_listings ADD COLUMN brand VARCHAR(100) AFTER technician_id";
    
    if ($mysqli->query($add_column)) {
        echo "Successfully added 'brand' column to technician_listings table.<br>";
    } else {
        echo "Error adding 'brand' column: " . $mysqli->error . "<br>";
    }
} else {
    echo "The 'brand' column already exists in technician_listings table.<br>";
}

$mysqli->close();
?>
