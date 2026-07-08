<?php
$mysqli = new mysqli("localhost", "root", "", "fyp");

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

// Delete all rows with id=0
$mysqli->query("DELETE FROM technician_listings WHERE id = 0");
echo "Deleted rows with id=0<br>";

// Get the maximum ID
$result = $mysqli->query("SELECT MAX(id) as max_id FROM technician_listings");
$row = $result->fetch_assoc();
$max_id = $row['max_id'] ?? 0;

// If no rows, set to 1, otherwise max + 1
$next_id = ($max_id == 0) ? 1 : $max_id + 1;

// Reset auto-increment
$mysqli->query("ALTER TABLE technician_listings AUTO_INCREMENT = $next_id");
echo "Auto-increment set to $next_id<br>";

// Verify
$result = $mysqli->query("SHOW TABLE STATUS LIKE 'technician_listings'");
$row = $result->fetch_assoc();
echo "Verified auto-increment: " . $row['Auto_increment'] . "<br>";

// Show current listings
$result = $mysqli->query("SELECT id, name FROM technician_listings ORDER BY id");
echo "<br>Current listings:<br>";
while ($row = $result->fetch_assoc()) {
    echo "ID: " . $row['id'] . " - Name: " . htmlspecialchars($row['name']) . "<br>";
}
?>
