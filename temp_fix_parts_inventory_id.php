<?php
$mysqli = new mysqli('localhost', 'root', '', 'fyp');
if ($mysqli->connect_error) {
    echo 'CONNECT_ERR:' . $mysqli->connect_error;
    exit(1);
}
if (!$mysqli->query("UPDATE parts_inventory SET id = 1 WHERE id = 0")) {
    echo 'UPDATE_ERR:' . $mysqli->error;
    exit(1);
}
if (!$mysqli->query("ALTER TABLE parts_inventory MODIFY COLUMN id INT(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT = 2")) {
    echo 'ALTER_ERR:' . $mysqli->error;
    exit(1);
}
echo 'FIXED';
?>