<?php
$mysqli = new mysqli("localhost", "root", "", "fyp");

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

// Check if source_id column exists
$check = $mysqli->query("SHOW COLUMNS FROM parts_inventory LIKE 'source_id'");
if ($check && $check->num_rows > 0) {
    echo "Column 'source_id' already exists in parts_inventory table.";
} else {
    // Add the column
    $result = $mysqli->query("ALTER TABLE parts_inventory ADD COLUMN source_id INT AFTER source_type");
    if ($result) {
        echo "Column 'source_id' added successfully to parts_inventory table.";
    } else {
        echo "Error adding column: " . $mysqli->error;
    }
}
?>
