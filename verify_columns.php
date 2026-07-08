<?php
$mysqli = new mysqli("localhost", "root", "", "fyp");

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

// Check for condition and price columns
$columns_to_verify = [
    'condition' => '`condition` VARCHAR(50)',
    'price' => 'price DECIMAL(10, 2)'
];

foreach ($columns_to_verify as $column_name => $column_type) {
    $check_column = $mysqli->query("SHOW COLUMNS FROM technician_listings LIKE '$column_name'");
    
    if ($check_column && $check_column->num_rows === 0) {
        $add_column = "ALTER TABLE technician_listings ADD COLUMN $column_type";
        
        if ($mysqli->query($add_column)) {
            echo "Successfully added '$column_name' column to technician_listings table.<br>";
        } else {
            echo "Error adding '$column_name' column: " . $mysqli->error . "<br>";
        }
    } else {
        echo "The '$column_name' column already exists in technician_listings table.<br>";
    }
}

// Display all columns in the table
echo "<br>Current columns in technician_listings:<br>";
$result = $mysqli->query("SHOW COLUMNS FROM technician_listings");
while ($row = $result->fetch_assoc()) {
    echo "- " . $row['Field'] . " (" . $row['Type'] . ")<br>";
}

$mysqli->close();
?>
