<?php
$mysqli = new mysqli('localhost', 'root', '', 'fyp');
if ($mysqli->connect_error) {
    echo 'CONNECT_ERR:' . $mysqli->connect_error;
    exit(1);
}
$res = $mysqli->query('DESCRIBE parts_inventory');
if (!$res) {
    echo 'ERR:' . $mysqli->error;
    exit(1);
}
while ($row = $res->fetch_assoc()) {
    echo $row['Field'] . '|' . $row['Type'] . '|' . $row['Null'] . '|' . $row['Key'] . '|' . $row['Default'] . '|' . $row['Extra'] . "\n";
}
?>