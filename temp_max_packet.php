<?php
$mysqli = new mysqli('localhost', 'root', '', 'fyp');
if ($mysqli->connect_error) {
    echo 'CONNECT_ERR:' . $mysqli->connect_error;
    exit(1);
}
$res = $mysqli->query("SHOW VARIABLES LIKE 'max_allowed_packet'");
if (!$res) {
    echo 'ERR:' . $mysqli->error;
    exit(1);
}
$row = $res->fetch_assoc();
echo $row['Value'];
?>