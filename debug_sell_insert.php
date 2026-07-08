<?php
$mysqli = new mysqli('localhost','root','','fyp');
if ($mysqli->connect_error) {
    echo 'DBERR ' . $mysqli->connect_error . PHP_EOL;
    exit(1);
}
$sql = "INSERT INTO device_submissions (user_id, device_type, brand, model, device_condition, storage, ram, battery_health, screen_condition, accessories, description, estimated_price, image1, image2, image3, video, status, submitted_at) VALUES (1, 'Smartphone', 'Apple', 'iPhone 15', 'Good', 256, 8, 90, '', 'Charger', 'Test', 1000, NULL, NULL, NULL, NULL, 'pending', NOW())";
$result = $mysqli->query($sql);
echo 'RESULT=' . ($result ? '1' : '0') . PHP_EOL;
if (!$result) {
    echo 'ERROR=' . $mysqli->error . PHP_EOL;
}
?>
