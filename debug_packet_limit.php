<?php
$mysqli = new mysqli('localhost','root','','fyp');
if ($mysqli->connect_error) {
    echo 'DBERR ' . $mysqli->connect_error . PHP_EOL;
    exit(1);
}
$res = $mysqli->query("SHOW VARIABLES LIKE 'max_allowed_packet'");
$row = $res->fetch_assoc();
echo 'max_allowed_packet=' . ($row['Value'] ?? 'null') . PHP_EOL;
?>
