<?php
$mysqli = new mysqli("localhost", "root", "", "fyp");

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

// Modify repair_requests status enum to include disassembled
$check = $mysqli->query("SHOW COLUMNS FROM repair_requests WHERE Field = 'status'");
if ($check) {
    $row = $check->fetch_assoc();
    $type = $row['Type'];
    
    if (strpos($type, 'disassembled') === false) {
        $result = $mysqli->query("ALTER TABLE repair_requests MODIFY COLUMN status ENUM('pending', 'accepted', 'rejected', 'picked_up', 'repairing', 'completed', 'unrepairable', 'disassembled') DEFAULT 'pending'");
        if ($result) {
            echo "Added 'disassembled' status to repair_requests table.<br>";
        } else {
            echo "Error modifying status column: " . $mysqli->error . "<br>";
        }
    } else {
        echo "'disassembled' status already exists in repair_requests table.<br>";
    }
}

// Add disassembled status to device_submissions as well
$check_ds = $mysqli->query("SHOW COLUMNS FROM device_submissions WHERE Field = 'status'");
if ($check_ds) {
    $row = $check_ds->fetch_assoc();
    $type = $row['Type'];
    
    if (strpos($type, 'disassembled') === false) {
        $result = $mysqli->query("ALTER TABLE device_submissions MODIFY COLUMN status ENUM('pending', 'approved', 'awaiting_drop_off', 'received', 'repair_requested', 'repairing', 'disassembled', 'completed', 'rejected', 'flagged') DEFAULT 'pending'");
        if ($result) {
            echo "Added 'disassembled' status to device_submissions table.<br>";
        } else {
            echo "Error modifying device_submissions status column: " . $mysqli->error . "<br>";
        }
    } else {
        echo "'disassembled' status already exists in device_submissions table.<br>";
    }
}

echo "<br>Status update complete!";
?>
