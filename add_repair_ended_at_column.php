<?php
$mysqli = new mysqli("localhost", "root", "", "fyp");

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

// Check if column already exists
$check = $mysqli->query("SHOW COLUMNS FROM repair_requests LIKE 'repair_ended_at'");
if ($check && $check->num_rows > 0) {
    echo "Column 'repair_ended_at' already exists in repair_requests table.";
} else {
    // Add the column
    $result = $mysqli->query("ALTER TABLE repair_requests ADD COLUMN repair_ended_at DATETIME AFTER repair_started_at");
    if ($result) {
        echo "Column 'repair_ended_at' added successfully to repair_requests table.";
    } else {
        echo "Error adding column: " . $mysqli->error;
    }
}
?>
