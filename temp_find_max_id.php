<?php
$mysqli = new mysqli('localhost', 'root', '', 'fyp');
if ($mysqli->connect_error) {
    echo 'CONNECT_ERR:' . $mysqli->connect_error;
    exit(1);
}
$res = $mysqli->query('SELECT MAX(id) AS max_id, COUNT(*) AS total FROM parts_inventory');
if (!$res) { echo 'ERR:' . $mysqli->error; exit(1); }
$row = $res->fetch_assoc();
echo 'max_id:' . ($row['max_id'] ?? 'NULL') . '\n';
echo 'count:' . $row['total'] . '\n';
$res2 = $mysqli->query('SELECT id, part_name FROM parts_inventory WHERE id = 0');
if ($res2 && $res2->num_rows > 0) {
    echo 'has_zero:1\n';
} else {
    echo 'has_zero:0\n';
}
?>