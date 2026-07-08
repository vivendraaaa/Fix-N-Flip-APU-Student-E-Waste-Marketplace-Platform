<?php
$mysqli = new mysqli("localhost", "root", "", "fyp");

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

// Check if rejection_reason column exists
$check = $mysqli->query("SHOW COLUMNS FROM device_submissions LIKE 'rejection_reason'");
if ($check && $check->num_rows > 0) {
    echo "Column 'rejection_reason' already exists in device_submissions table.<br>";
} else {
    // Add the column
    $result = $mysqli->query("ALTER TABLE device_submissions ADD COLUMN rejection_reason TEXT AFTER status");
    if ($result) {
        echo "Column 'rejection_reason' added successfully to device_submissions table.<br>";
    } else {
        echo "Error adding rejection_reason column: " . $mysqli->error . "<br>";
    }
}

echo "<br>Column addition complete!";
$mysqli->close();
?>
