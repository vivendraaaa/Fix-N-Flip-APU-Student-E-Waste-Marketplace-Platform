<?php
$mysqli = new mysqli("localhost", "root", "", "fyp");

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

echo "<h2>Repair Requests Table Debug</h2>";

// Check if repair_requests table exists
$check_table = $mysqli->query("SHOW TABLES LIKE 'repair_requests'");
if ($check_table && $check_table->num_rows > 0) {
    echo "✓ repair_requests table exists<br><br>";
    
    // Show table structure
    echo "<h3>Table Structure:</h3>";
    $columns = $mysqli->query("SHOW COLUMNS FROM repair_requests");
    echo "<table border='1' cellpadding='10'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    while ($col = $columns->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $col['Field'] . "</td>";
        echo "<td>" . $col['Type'] . "</td>";
        echo "<td>" . $col['Null'] . "</td>";
        echo "<td>" . $col['Key'] . "</td>";
        echo "<td>" . $col['Default'] . "</td>";
        echo "<td>" . $col['Extra'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Show existing data
    echo "<br><h3>Existing Data:</h3>";
    $data = $mysqli->query("SELECT * FROM repair_requests");
    if ($data && $data->num_rows > 0) {
        echo "<table border='1' cellpadding='10'>";
        echo "<tr><th>ID</th><th>Submission ID</th><th>Technician ID</th><th>Status</th><th>Pickup Code</th></tr>";
        while ($row = $data->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $row['id'] . "</td>";
            echo "<td>" . $row['submission_id'] . "</td>";
            echo "<td>" . $row['technician_id'] . "</td>";
            echo "<td>" . $row['status'] . "</td>";
            echo "<td>" . $row['pickup_code'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "No data in repair_requests table";
    }
    
    // Check for technicians
    echo "<br><h3>Available Technicians:</h3>";
    $techs = $mysqli->query("SELECT id, username FROM users WHERE role = 'technician'");
    if ($techs && $techs->num_rows > 0) {
        echo "<ul>";
        while ($tech = $techs->fetch_assoc()) {
            echo "<li>ID: " . $tech['id'] . " - " . htmlspecialchars($tech['username']) . "</li>";
        }
        echo "</ul>";
    } else {
        echo "No technicians found in users table";
    }
} else {
    echo "✗ repair_requests table does not exist<br>";
}

$mysqli->close();
?>
