<?php
$mysqli = new mysqli("localhost", "root", "", "fyp");

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

echo "<h2>Fix repair_requests table id column - Step by Step</h2>";

// Step 1: Delete the invalid row with id=0
echo "<h3>Step 1: Delete invalid row with id=0</h3>";
$delete_query = "DELETE FROM repair_requests WHERE id = 0";
$result = $mysqli->query($delete_query);
if ($result) {
    echo "✓ Deleted row with id=0<br>";
} else {
    echo "✗ Failed to delete row with id=0: " . $mysqli->error . "<br>";
}

// Step 2: Temporarily drop the PRIMARY KEY constraint
echo "<h3>Step 2: Drop PRIMARY KEY constraint</h3>";
$drop_pk = "ALTER TABLE repair_requests DROP PRIMARY KEY";
$result = $mysqli->query($drop_pk);
if ($result) {
    echo "✓ Dropped PRIMARY KEY<br>";
} else {
    echo "✗ Failed to drop PRIMARY KEY: " . $mysqli->error . "<br>";
}

// Step 3: Add AUTO_INCREMENT to id column
echo "<h3>Step 3: Add AUTO_INCREMENT to id column</h3>";
$alter_query = "ALTER TABLE repair_requests MODIFY id INT(11) NOT NULL AUTO_INCREMENT";
$result = $mysqli->query($alter_query);
if ($result) {
    echo "✓ Added AUTO_INCREMENT to id column<br>";
} else {
    echo "✗ Failed to add AUTO_INCREMENT: " . $mysqli->error . "<br>";
}

// Step 4: Add PRIMARY KEY back
echo "<h3>Step 4: Add PRIMARY KEY back</h3>";
$add_pk = "ALTER TABLE repair_requests ADD PRIMARY KEY (id)";
$result = $mysqli->query($add_pk);
if ($result) {
    echo "✓ Added PRIMARY KEY back<br>";
} else {
    echo "✗ Failed to add PRIMARY KEY: " . $mysqli->error . "<br>";
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
