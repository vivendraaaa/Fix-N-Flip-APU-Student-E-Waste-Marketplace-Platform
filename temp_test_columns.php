<?php
$mysqli = new mysqli('localhost', 'root', '', 'fyp');
if ($mysqli->connect_error) {
    echo "CONNECT FAIL: " . $mysqli->connect_error . "\n";
    exit;
}
$optionalColumns = [
    'parts_requested' => 'rr.parts_requested',
    'parts_request_pin' => 'rr.parts_request_pin'
];
foreach ($optionalColumns as $key => $column) {
    $check = $mysqli->query("SHOW COLUMNS FROM repair_requests LIKE '" . $mysqli->real_escape_string($key) . "'");
    echo "QUERY: SHOW COLUMNS FROM repair_requests LIKE '" . $mysqli->real_escape_string($key) . "'\n";
    if ($check) {
        echo "ROWS: " . $check->num_rows . "\n";
        while ($row = $check->fetch_assoc()) {
            echo "ROW: " . json_encode($row) . "\n";
        }
    } else {
        echo "CHECK FAIL: " . $mysqli->error . "\n";
    }
}
?>