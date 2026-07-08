<?php
$mysqli = new mysqli("localhost", "root", "", "fyp");
if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

echo "Columns in device_submissions table:\n";
$result = $mysqli->query("SHOW COLUMNS FROM device_submissions");
while($row = $result->fetch_assoc()) {
    echo $row['Field'] . " - " . $row['Type'] . "\n";
}

echo "\n\nSample data from device_submissions:\n";
$result = $mysqli->query("SELECT * FROM device_submissions LIMIT 1");
if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    print_r($row);
} else {
    echo "No data in device_submissions table\n";
}
?>
