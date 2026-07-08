<?php
session_start();
$mysqli = new mysqli("localhost", "root", "", "fyp");

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

if (!isset($_GET['id']) || !isset($_GET['field'])) {
    header('HTTP/1.0 404 Not Found');
    exit;
}

$id = intval($_GET['id']);
$field = $_GET['field'];

$allowed_fields = ['image1', 'image2', 'image3', 'video', 'pfp'];
if (!in_array($field, $allowed_fields)) {
    header('HTTP/1.0 400 Bad Request');
    exit;
}

$table = ($field === 'pfp') ? 'users' : 'device_submissions';

// Check if column exists first
$columns_result = $mysqli->query("SHOW COLUMNS FROM $table");
$columns = [];
while ($col = $columns_result->fetch_assoc()) {
    $columns[] = $col['Field'];
}

if (!in_array($field, $columns)) {
    // Column doesn't exist, return transparent pixel
    header('Content-Type: image/gif');
    echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
    exit;
}

$query = "SELECT $field FROM $table WHERE id = ?";
$stmt = $mysqli->prepare($query);
$stmt->bind_param('i', $id);
$stmt->execute();
$stmt->bind_result($data);
$stmt->fetch();
$stmt->close();

if ($field === 'video') {
    header('Content-Type: video/mp4');
    header('Content-Length: ' . strlen($data));
} else {
    header('Content-Type: image/jpeg');
}

if ($data) {
    // Check if data is hex-encoded (starts with 0x) - new format
    if (is_string($data) && strpos($data, '0x') === 0) {
        // Remove 0x prefix and convert hex to binary
        $data = hex2bin(substr($data, 2));
    }
    // If data is binary (old format), use it as-is
    echo $data;
} else {
    // Return a simple 1x1 transparent pixel if no data
    header('Content-Type: image/gif');
    echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
    exit;
}
