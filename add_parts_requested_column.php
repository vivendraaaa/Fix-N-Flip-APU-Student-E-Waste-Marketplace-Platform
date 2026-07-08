<?php
$mysqli = new mysqli("localhost", "root", "", "fyp");

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

// Check if column already exists
$check = $mysqli->query("SHOW COLUMNS FROM repair_requests LIKE 'parts_requested'");
if ($check && $check->num_rows > 0) {
    echo "Column 'parts_requested' already exists in repair_requests table.";
} else {
    // Add the column
    $result = $mysqli->query("ALTER TABLE repair_requests ADD COLUMN parts_requested TEXT AFTER unrepairable_reason");
    if ($result) {
        echo "Column 'parts_requested' added successfully to repair_requests table.";
    } else {
        echo "Error adding column: " . $mysqli->error;
    }
}
?>
