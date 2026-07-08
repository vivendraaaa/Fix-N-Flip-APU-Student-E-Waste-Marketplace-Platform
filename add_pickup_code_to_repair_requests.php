<?php
$mysqli = new mysqli("localhost", "root", "", "fyp");
if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

$column_name = "pickup_code";
$table_name = "repair_requests";

$check_column_sql = "SHOW COLUMNS FROM `$table_name` LIKE ";
$column_exists = $mysqli->query($check_column_sql . "'$column_name'")->num_rows > 0;

if (!$column_exists) {
    $sql = "ALTER TABLE `$table_name` ADD COLUMN `$column_name` VARCHAR(255) NULL AFTER `status`";
    if ($mysqli->query($sql) === TRUE) {
        echo "Column ".$column_name." added successfully to table ".$table_name.".\n";
    } else {
        echo "Error adding column ".$column_name." to table ".$table_name.": " . $mysqli->error . "\n";
    }
} else {
    echo "Column ".$column_name." already exists in table ".$table_name.".\n";
}

$mysqli->close();

?>