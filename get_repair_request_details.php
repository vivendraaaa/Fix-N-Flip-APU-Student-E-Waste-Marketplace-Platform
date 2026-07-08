<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);

function repair_debug_log($message) {
    $logFile = __DIR__ . '/repair_details_debug.log';
    $entry = '[' . date('Y-m-d H:i:s') . '] ' . $message . "\n";
    error_log($entry, 3, $logFile);
}

register_shutdown_function(function () {
    $error = error_get_last();
    if ($error !== null) {
        repair_debug_log('SHUTDOWN ERROR: ' . json_encode($error));
    }
});

set_exception_handler(function ($exception) {
    repair_debug_log('EXCEPTION: ' . $exception->getMessage() . ' at ' . $exception->getFile() . ':' . $exception->getLine());
    echo json_encode(['error' => 'Server exception']);
    exit;
});

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=UTF-8');

repair_debug_log('REQUEST: GET=' . json_encode($_GET) . ' SESSION=' . json_encode([ 'logged_in' => $_SESSION['logged_in'] ?? null, 'role' => $_SESSION['role'] ?? null, 'user_id' => $_SESSION['user_id'] ?? null ]));

$mysqli = new mysqli("localhost", "root", "", "fyp");
if ($mysqli->connect_error) {
    error_log("get_repair_request_details.php: DB connection failed: " . $mysqli->connect_error);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in'] || strtolower($_SESSION['role'] ?? '') !== 'technician') {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$request_id = intval($_GET['id'] ?? 0);
$technician_id = intval($_SESSION['user_id'] ?? 0);

if ($request_id === 0) {
    echo json_encode(['error' => 'Invalid request ID']);
    exit;
}

// Determine whether the repair_requests table has the optional columns.
$repairColumns = [
    "rr.id AS request_id",
    "rr.submission_id",
    "rr.status AS repair_status",
    "rr.pickup_code"
];

$availableColumns = [];
$columnResult = $mysqli->query("SHOW COLUMNS FROM repair_requests");
if ($columnResult) {
    while ($row = $columnResult->fetch_assoc()) {
        $availableColumns[] = $row['Field'];
    }
}

if (in_array('parts_requested', $availableColumns, true)) {
    $repairColumns[] = 'rr.parts_requested';
}
if (in_array('parts_request_pin', $availableColumns, true)) {
    $repairColumns[] = 'rr.parts_request_pin';
}
if (in_array('rejection_reason', $availableColumns, true)) {
    $repairColumns[] = 'rr.rejection_reason';
}

$repairColumns = implode(",\n        ", $repairColumns);
$query = "SELECT
        $repairColumns,
        rr.created_at AS repair_created_at,
        ds.device_type,
        ds.brand,
        ds.model,
        ds.device_condition,
        ds.storage,
        ds.ram,
        ds.battery_health,
        ds.screen_condition,
        ds.estimated_price,
        ds.submitted_at AS submitted_at,
        '' AS issues,
        ds.accessories,
        ds.description,
        ds.location,
        u.username AS seller_name
    FROM repair_requests rr
    JOIN device_submissions ds ON rr.submission_id = ds.id
    JOIN users u ON ds.user_id = u.id
    WHERE rr.id = ? AND rr.technician_id = ?";

error_log("get_repair_request_details.php: SQL query built: " . $query);

$stmt = $mysqli->prepare($query);
if (!$stmt) {
    error_log("get_repair_request_details.php: prepare failed: " . $mysqli->error);
    error_log("get_repair_request_details.php: falling back to minimal query.");
    $query = "SELECT
        rr.id AS request_id,
        rr.submission_id,
        rr.status AS repair_status,
        rr.pickup_code,
        rr.created_at AS repair_created_at,
        ds.device_type,
        ds.brand,
        ds.model,
        ds.device_condition,
        ds.storage,
        ds.ram,
        ds.battery_health,
        '' AS screen_condition,
        ds.estimated_price,
        ds.submitted_at AS submitted_at,
        '' AS issues,
        ds.accessories,
        ds.description,
        ds.location,
        u.username AS seller_name
    FROM repair_requests rr
    JOIN device_submissions ds ON rr.submission_id = ds.id
    JOIN users u ON ds.user_id = u.id
    WHERE rr.id = ? AND rr.technician_id = ?";
    $stmt = $mysqli->prepare($query);
    if (!$stmt) {
        error_log("get_repair_request_details.php: fallback prepare failed: " . $mysqli->error);
        echo json_encode(['error' => 'Database query failed']);
        exit;
    }
}

$stmt->bind_param("ii", $request_id, $technician_id);
if (!$stmt->execute()) {
    error_log("get_repair_request_details.php: execute failed: " . $stmt->error);
    echo json_encode(['error' => 'Database query failed']);
    exit;
}

$result = $stmt->get_result();
if (!$result) {
    error_log("get_repair_request_details.php: get_result failed: " . $stmt->error);
    echo json_encode(['error' => 'Database query failed']);
    exit;
}

if ($result->num_rows === 0) {
    echo json_encode(['error' => 'Repair request not found']);
    exit;
}

$data = $result->fetch_assoc();
$response = [
    'request_id' => $data['request_id'],
    'submission_id' => $data['submission_id'],
    'device_type' => $data['device_type'] ?? '',
    'brand' => $data['brand'] ?? '',
    'model' => $data['model'] ?? '',
    'device_condition' => $data['device_condition'] ?? '',
    'storage' => $data['storage'] ?? '',
    'ram' => $data['ram'] ?? '',
    'battery_health' => $data['battery_health'] ?? '',
    'screen_condition' => $data['screen_condition'] ?? '',
    'estimated_price' => $data['estimated_price'] ?? '',
    'seller_name' => $data['seller_name'] ?? '',
    'submitted_at' => $data['submitted_at'] ? date('M d, Y g:i A', strtotime($data['submitted_at'])) : 'N/A',
    'issues' => $data['issues'] ?? '',
    'accessories' => $data['accessories'] ?? '',
    'description' => $data['description'] ?? '',
    'location' => $data['location'] ?? '',
    'repair_status' => $data['repair_status'] ?? '',
    'pickup_code' => $data['pickup_code'] ?? '',
    'parts_requested' => isset($data['parts_requested']) ? $data['parts_requested'] : '',
    'parts_request_pin' => isset($data['parts_request_pin']) ? $data['parts_request_pin'] : '',
    'rejection_reason' => isset($data['rejection_reason']) ? $data['rejection_reason'] : ''
];

echo json_encode($response);
?>
