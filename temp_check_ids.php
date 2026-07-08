<?php
$mysqli = new mysqli('localhost', 'root', '', 'fyp');
if ($mysqli->connect_error) {
    echo 'CONNECT_ERR:' . $mysqli->connect_error;
    exit(1);
}
$res = $mysqli->query('SELECT id, COUNT(*) as cnt FROM parts_inventory GROUP BY id HAVING cnt > 1');
if (!$res) {
    echo 'ERR:' . $mysqli->error;
    exit(1);
}
while ($row = $res->fetch_assoc()) {
    echo $row['id'] . '|' . $row['cnt'] . "\n";
}
?>