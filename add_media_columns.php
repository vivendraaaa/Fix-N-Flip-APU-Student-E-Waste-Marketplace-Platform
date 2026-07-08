<?php
$mysqli = new mysqli("localhost", "root", "", "fyp");

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

// Check and add missing columns in technician_listings table
$columns_to_check = [
    'image1' => 'LONGBLOB',
    'image2' => 'LONGBLOB',
    'image3' => 'LONGBLOB',
    'video' => 'LONGBLOB'
];

foreach ($columns_to_check as $column_name => $column_type) {
    $check_column = $mysqli->query("SHOW COLUMNS FROM technician_listings LIKE '$column_name'");
    
    if ($check_column && $check_column->num_rows === 0) {
        $add_column = "ALTER TABLE technician_listings ADD COLUMN $column_name $column_type";
        
        if ($mysqli->query($add_column)) {
            echo "Successfully added '$column_name' column to technician_listings table.<br>";
        } else {
            echo "Error adding '$column_name' column: " . $mysqli->error . "<br>";
        }
    } else {
        echo "The '$column_name' column already exists in technician_listings table.<br>";
    }
}

$mysqli->close();
?>
