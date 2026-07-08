<?php
$mysqli = new mysqli("localhost", "root", "", "fyp");

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

// Check if source_type column exists
$check_type = $mysqli->query("SHOW COLUMNS FROM parts_inventory LIKE 'source_type'");
if ($check_type && $check_type->num_rows > 0) {
    echo "Column 'source_type' already exists in parts_inventory table.<br>";
} else {
    // Add the column
    $result = $mysqli->query("ALTER TABLE parts_inventory ADD COLUMN source_type ENUM('disassembly', 'disposal', 'purchase') DEFAULT 'disassembly'");
    if ($result) {
        echo "Column 'source_type' added successfully to parts_inventory table.<br>";
    } else {
        echo "Error adding source_type column: " . $mysqli->error . "<br>";
    }
}

// Check if source_id column exists
$check_id = $mysqli->query("SHOW COLUMNS FROM parts_inventory LIKE 'source_id'");
if ($check_id && $check_id->num_rows > 0) {
    echo "Column 'source_id' already exists in parts_inventory table.<br>";
} else {
    // Add the column
    $result = $mysqli->query("ALTER TABLE parts_inventory ADD COLUMN source_id INT AFTER source_type");
    if ($result) {
        echo "Column 'source_id' added successfully to parts_inventory table.<br>";
    } else {
        echo "Error adding source_id column: " . $mysqli->error . "<br>";
    }
}

echo "<br>Column addition complete!";
?>
