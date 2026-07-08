<?php
$mysqli = new mysqli("localhost", "root", "", "fyp");

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

echo "<h2>Fix repair_requests table id column</h2>";

// Add AUTO_INCREMENT to id column
$alter_query = "ALTER TABLE repair_requests MODIFY id INT(11) NOT NULL AUTO_INCREMENT";
$result = $mysqli->query($alter_query);

if ($result) {
    echo "✓ Successfully added AUTO_INCREMENT to id column<br>";
} else {
    echo "✗ Failed to add AUTO_INCREMENT: " . $mysqli->error . "<br>";
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

$mysqli->close();
?>
