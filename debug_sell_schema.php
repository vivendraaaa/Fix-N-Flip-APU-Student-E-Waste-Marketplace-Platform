<?php
$mysqli = new mysqli('localhost','root','','fyp');
if ($mysqli->connect_error) {
    echo 'DBERR ' . $mysqli->connect_error;
    exit(1);
}
$res = $mysqli->query('SHOW COLUMNS FROM device_submissions');
while ($row = $res->fetch_assoc()) {
    echo $row['Field'] . "\t" . $row['Type'] . PHP_EOL;
}
?>
