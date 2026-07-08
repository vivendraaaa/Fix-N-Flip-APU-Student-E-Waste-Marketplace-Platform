<?php
$mysqli = new mysqli("localhost", "root", "", "fyp");

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

$order_id = 1;

echo "<h2>Debug Order Status</h2>";

// Check current status
$result = $mysqli->query("SELECT * FROM orders WHERE id = $order_id");
if ($result && $row = $result->fetch_assoc()) {
    echo "<h3>Current Order Data:</h3>";
    echo "<pre>";
    print_r($row);
    echo "</pre>";
}

// Check all orders to see if prices are affected
echo "<h3>All Orders:</h3>";
$result = $mysqli->query("SELECT id, user_id, total_amount, status FROM orders");
echo "<table border='1'>";
echo "<tr><th>ID</th><th>User ID</th><th>Total Amount</th><th>Status</th></tr>";
while ($row = $result->fetch_assoc()) {
    echo "<tr>";
    echo "<td>" . $row['id'] . "</td>";
    echo "<td>" . $row['user_id'] . "</td>";
    echo "<td>" . $row['total_amount'] . "</td>";
    echo "<td>" . $row['status'] . "</td>";
    echo "</tr>";
}
echo "</table>";

// Check table structure
echo "<h3>Orders Table Structure:</h3>";
$result = $mysqli->query("DESCRIBE orders");
echo "<table border='1'>";
echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
while ($row = $result->fetch_assoc()) {
    echo "<tr>";
    echo "<td>" . $row['Field'] . "</td>";
    echo "<td>" . $row['Type'] . "</td>";
    echo "<td>" . $row['Null'] . "</td>";
    echo "<td>" . $row['Key'] . "</td>";
    echo "<td>" . $row['Default'] . "</td>";
    echo "<td>" . $row['Extra'] . "</td>";
    echo "</tr>";
}
echo "</table>";

$mysqli->close();
?>
