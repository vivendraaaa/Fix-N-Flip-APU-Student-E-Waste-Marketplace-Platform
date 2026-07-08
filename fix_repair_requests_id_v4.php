<?php
$mysqli = new mysqli("localhost", "root", "", "fyp");

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

echo "<h2>Fix repair_requests table id column - Check Current State</h2>";

// Check current table structure
echo "<h3>Current Table Structure:</h3>";
$columns = $mysqli->query("SHOW COLUMNS FROM repair_requests");
echo "<table border='1' cellpadding='10'>";
echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
$has_primary_key = false;
while ($col = $columns->fetch_assoc()) {
    echo "<tr>";
    echo "<td>" . $col['Field'] . "</td>";
    echo "<td>" . $col['Type'] . "</td>";
    echo "<td>" . $col['Null'] . "</td>";
    echo "<td>" . $col['Key'] . "</td>";
    echo "<td>" . $col['Default'] . "</td>";
    echo "<td>" . $col['Extra'] . "</td>";
    echo "</tr>";
    if ($col['Key'] === 'PRI') {
        $has_primary_key = true;
    }
}
echo "</table>";

if ($has_primary_key) {
    echo "<br>✓ PRIMARY KEY exists<br>";
} else {
    echo "<br>✗ PRIMARY KEY does NOT exist<br>";
}

// Step 1: Delete any invalid rows with id=0
echo "<h3>Step 1: Delete invalid row with id=0</h3>";
$delete_query = "DELETE FROM repair_requests WHERE id = 0";
$result = $mysqli->query($delete_query);
if ($result) {
    echo "✓ Deleted row with id=0 (if it existed)<br>";
} else {
    echo "✗ Failed to delete row with id=0: " . $mysqli->error . "<br>";
}

// Step 2: Add AUTO_INCREMENT and PRIMARY KEY together (if PRIMARY KEY doesn't exist)
if (!$has_primary_key) {
    echo "<h3>Step 2: Add AUTO_INCREMENT and PRIMARY KEY together</h3>";
    $alter_query = "ALTER TABLE repair_requests MODIFY id INT(11) NOT NULL AUTO_INCREMENT, ADD PRIMARY KEY (id)";
    $result = $mysqli->query($alter_query);
    if ($result) {
        echo "✓ Added AUTO_INCREMENT and PRIMARY KEY<br>";
    } else {
        echo "✗ Failed to add AUTO_INCREMENT and PRIMARY KEY: " . $mysqli->error . "<br>";
    }
} else {
    echo "<h3>Step 2: PRIMARY KEY already exists, just add AUTO_INCREMENT</h3>";
    $alter_query = "ALTER TABLE repair_requests MODIFY id INT(11) NOT NULL AUTO_INCREMENT";
    $result = $mysqli->query($alter_query);
    if ($result) {
        echo "✓ Added AUTO_INCREMENT to id column<br>";
    } else {
        echo "✗ Failed to add AUTO_INCREMENT: " . $mysqli->error . "<br>";
    }
}

// Verify the change
echo "<br><h3>Updated Table Structure:</h3>";
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

// Show current data
echo "<br><h3>Current Data:</h3>";
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

$mysqli->close();
?>
