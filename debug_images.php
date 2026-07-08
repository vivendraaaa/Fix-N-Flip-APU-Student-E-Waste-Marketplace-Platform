<?php
$mysqli = new mysqli("localhost", "root", "", "fyp");

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

// Check the 3 recently created listings
$listings = $mysqli->query("
    SELECT id, name, brand, image1, image2, image3, video
    FROM technician_listings
    ORDER BY created_at DESC
    LIMIT 3
");

echo "<h2>Recent Listings - Image Data Check</h2>";
echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
echo "<tr><th>ID</th><th>Name</th><th>Brand</th><th>Image1</th><th>Image2</th><th>Image3</th><th>Video</th></tr>";

while ($row = $listings->fetch_assoc()) {
    echo "<tr>";
    echo "<td>" . $row['id'] . "</td>";
    echo "<td>" . $row['name'] . "</td>";
    echo "<td>" . $row['brand'] . "</td>";
    echo "<td>" . (strlen($row['image1'] ?? '') > 0 ? "✓ (" . strlen($row['image1']) . " bytes)" : "✗ Empty") . "</td>";
    echo "<td>" . (strlen($row['image2'] ?? '') > 0 ? "✓ (" . strlen($row['image2']) . " bytes)" : "✗ Empty") . "</td>";
    echo "<td>" . (strlen($row['image3'] ?? '') > 0 ? "✓ (" . strlen($row['image3']) . " bytes)" : "✗ Empty") . "</td>";
    echo "<td>" . (strlen($row['video'] ?? '') > 0 ? "✓ (" . strlen($row['video']) . " bytes)" : "✗ Empty") . "</td>";
    echo "</tr>";
}
echo "</table>";

$mysqli->close();
?>
