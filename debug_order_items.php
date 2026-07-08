<?php
$mysqli = new mysqli("localhost", "root", "", "fyp");

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

$order_id = 1;

echo "<h2>Debug Order Items</h2>";

// Check if order_items table exists
$check_items_table = $mysqli->query("SHOW TABLES LIKE 'order_items'");
if ($check_items_table && $check_items_table->num_rows > 0) {
    echo "<h3>Order Items Table Structure:</h3>";
    $result = $mysqli->query("DESCRIBE order_items");
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
    
    echo "<h3>Order Items for Order #$order_id:</h3>";
    $result = $mysqli->query("SELECT oi.*, p.name as product_name, p.price as product_price FROM order_items oi LEFT JOIN products p ON oi.product_id = p.id WHERE oi.order_id = $order_id");
    echo "<table border='1'>";
    echo "<tr><th>ID</th><th>Order ID</th><th>Product ID</th><th>Product Name</th><th>Product Price</th><th>Quantity</th><th>Price (from order_items)</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . $row['order_id'] . "</td>";
        echo "<td>" . $row['product_id'] . "</td>";
        echo "<td>" . ($row['product_name'] ?? 'N/A') . "</td>";
        echo "<td>" . ($row['product_price'] ?? 'N/A') . "</td>";
        echo "<td>" . $row['quantity'] . "</td>";
        echo "<td>" . (isset($row['price']) ? $row['price'] : 'N/A') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<h3>order_items table does not exist</h3>";
}

$mysqli->close();
?>
