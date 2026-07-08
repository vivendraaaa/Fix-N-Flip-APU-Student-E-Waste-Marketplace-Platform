<?php
$mysqli = new mysqli("localhost", "root", "", "fyp");
if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

$result = $mysqli->query("DESCRIBE parts_inventory");
while ($row = $result->fetch_assoc()) {
    print_r($row);
}
?>