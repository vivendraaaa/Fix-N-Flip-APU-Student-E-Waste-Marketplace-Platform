<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

error_log("get_submission_details.php called with id: " . ($_GET['id'] ?? 'none'));

$mysqli = new mysqli("localhost", "root", "", "fyp");

if ($mysqli->connect_error) {
    header('Content-Type: application/json');
    error_log("Connection failed: " . $mysqli->connect_error);
    echo json_encode(['error' => 'Connection failed']);
    exit;
}

header('Content-Type: application/json');

if (!isset($_GET['id'])) {
    error_log("Missing submission ID");
    echo json_encode(['error' => 'Missing submission ID']);
    exit;
}

$id = intval($_GET['id']);
error_log("Processing submission ID: $id");

try {
    // Check which columns exist in device_submissions table
    $columns_result = $mysqli->query("SHOW COLUMNS FROM device_submissions");
    $columns = [];
    while ($col = $columns_result->fetch_assoc()) {
        $columns[] = $col['Field'];
    }

    // Build query with only existing columns
    $select_columns = ['ds.id', 'ds.user_id', 'ds.device_type', 'ds.brand', 'ds.model', 'ds.storage', 'ds.ram', 'ds.battery_health', 'ds.device_condition', 'ds.accessories', 'ds.description', 'ds.estimated_price', 'ds.status', 'ds.submitted_at', 'u.username'];

    if (in_array('image1', $columns)) $select_columns[] = 'ds.image1';
    if (in_array('image2', $columns)) $select_columns[] = 'ds.image2';
    if (in_array('image3', $columns)) $select_columns[] = 'ds.image3';
    if (in_array('video', $columns)) $select_columns[] = 'ds.video';
    if (in_array('reject_reason', $columns)) $select_columns[] = 'ds.reject_reason';
    if (in_array('flag_reason', $columns)) $select_columns[] = 'ds.flag_reason';
    if (in_array('flag_notes', $columns)) $select_columns[] = 'ds.flag_notes';
    if (in_array('qr_code', $columns)) $select_columns[] = 'ds.qr_code';
    if (in_array('drop_pin', $columns)) $select_columns[] = 'ds.drop_pin';

    $query = "SELECT " . implode(', ', $select_columns) . " FROM device_submissions ds JOIN users u ON ds.user_id = u.id WHERE ds.id = ?";
    error_log("Query: $query");
    
    $stmt = $mysqli->prepare($query);
    if (!$stmt) {
        error_log("Prepare failed: " . $mysqli->error);
        echo json_encode(['error' => 'Prepare failed']);
        exit;
    }
    
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        error_log("Found submission: " . $row['id']);
        // Set flags for image/video existence
        $row['has_image1'] = isset($row['image1']) && $row['image1'] !== null;
        $row['has_image2'] = isset($row['image2']) && $row['image2'] !== null;
        $row['has_image3'] = isset($row['image3']) && $row['image3'] !== null;
        $row['has_video'] = isset($row['video']) && $row['video'] !== null;

        // Don't include binary data in JSON - use get_image.php instead
        unset($row['image1']);
        unset($row['image2']);
        unset($row['image3']);
        unset($row['video']);

        $json = json_encode($row);
        error_log("JSON response length: " . strlen($json));
        echo $json;
    } else {
        error_log("Submission not found");
        echo json_encode(['error' => 'Submission not found']);
    }

    $stmt->close();
} catch (Exception $e) {
    error_log("Exception: " . $e->getMessage());
    echo json_encode(['error' => $e->getMessage()]);
}
