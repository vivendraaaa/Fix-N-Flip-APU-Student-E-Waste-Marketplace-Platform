<?php
$mysqli = new mysqli("localhost", "root", "", "fyp");

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

// Add parts_requested column
$mysqli->query("ALTER TABLE repair_requests ADD COLUMN parts_requested TEXT NULL AFTER pickup_code");

// Add repair_started_at column
$mysqli->query("ALTER TABLE repair_requests ADD COLUMN repair_started_at DATETIME NULL AFTER parts_requested");

// Add repair_ended_at column
$mysqli->query("ALTER TABLE repair_requests ADD COLUMN repair_ended_at DATETIME NULL AFTER repair_started_at");

// Add disposed_at column
$mysqli->query("ALTER TABLE repair_requests ADD COLUMN disposed_at DATETIME NULL AFTER repair_ended_at");

// Update ENUM to include 'disposed' status
$mysqli->query("ALTER TABLE repair_requests MODIFY COLUMN status ENUM('pending', 'accepted', 'rejected', 'picked_up', 'repairing', 'completed', 'unrepairable', 'disposed') NOT NULL");

echo "Migration completed successfully!<br>";
echo "Added columns: parts_requested, repair_started_at, repair_ended_at, disposed_at<br>";
echo "Updated status ENUM to include 'disposed'<br>";
?>
