<?php
$mysqli = new mysqli("localhost", "root", "", "fyp");

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

$columns_to_add = [
    'pickup_location' => "ALTER TABLE orders ADD COLUMN pickup_location VARCHAR(255) DEFAULT NULL",
    'proof_pictures' => "ALTER TABLE orders ADD COLUMN proof_pictures LONGBLOB DEFAULT NULL",
    'completed_at' => "ALTER TABLE orders ADD COLUMN completed_at TIMESTAMP NULL DEFAULT NULL"
];

foreach ($columns_to_add as $column_name => $query) {
    $check_query = "SHOW COLUMNS FROM orders LIKE '$column_name'";
    $result = $mysqli->query($check_query);
    
    if ($result && $result->num_rows === 0) {
        if ($mysqli->query($query)) {
            echo "✓ Added column: $column_name<br>";
        } else {
            echo "✗ Error adding column $column_name: " . $mysqli->error . "<br>";
        }
    } else {
        echo "✓ Column $column_name already exists<br>";
    }
}

echo "<br><strong>Migration complete!</strong>";
$mysqli->close();
?>
