<?php
$mysqli = new mysqli("localhost", "root", "", "fyp");

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

// Update all device_submissions with status 'repair_requested' to 'repairing'
$update = $mysqli->query("UPDATE device_submissions SET status = 'repairing' WHERE status = 'repair_requested'");

if ($update) {
    $affected_rows = $mysqli->affected_rows;
    echo "Updated $affected_rows record(s) from 'repair_requested' to 'repairing'.<br>";
} else {
    echo "Error updating records: " . $mysqli->error . "<br>";
}

// Show current status counts
echo "<br>Current status counts:<br>";
$result = $mysqli->query("SELECT status, COUNT(*) as count FROM device_submissions GROUP BY status");
while ($row = $result->fetch_assoc()) {
    echo $row['status'] . ": " . $row['count'] . "<br>";
}
?>
