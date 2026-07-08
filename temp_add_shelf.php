<?php
$mysqli = new mysqli('localhost', 'root', '', 'fyp');
if ($mysqli->connect_error) {
    echo 'CONNECT_ERR:' . $mysqli->connect_error;
    exit(1);
}
$res = $mysqli->query("SHOW COLUMNS FROM parts_inventory LIKE 'shelf'");
if ($res && $res->num_rows > 0) {
    echo 'COLUMN_EXISTS';
} else {
    if ($mysqli->query("ALTER TABLE parts_inventory ADD COLUMN shelf VARCHAR(50) AFTER location")) {
        echo 'COLUMN_ADDED';
    } else {
        echo 'ALTER_ERR:' . $mysqli->error;
    }
}
?>