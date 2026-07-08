<?php
$mysqli = new mysqli('localhost', 'root', '', 'fyp');
if ($mysqli->connect_error) {
    echo 'CONNECT_ERR:' . $mysqli->connect_error;
    exit(1);
}
$res = $mysqli->query('SELECT id, COUNT(*) AS cnt FROM parts_inventory WHERE id = 0');
if (!$res) {
    echo 'ERR:' . $mysqli->error;
    exit(1);
}
$row = $res->fetch_assoc();
if ($row) {
    echo 'id=0 count:' . $row['cnt'];
} else {
    echo 'NO_ROW';
}
?>