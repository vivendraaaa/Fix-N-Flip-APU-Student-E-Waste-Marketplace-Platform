<?php
// get_dashboard_stats.php - API endpoint for dashboard data
header('Content-Type: application/json');

$mysqli = new mysqli("localhost", "root", "", "fyp");

if ($mysqli->connect_error) {
    echo json_encode(['error' => 'Database connection failed: ' . $mysqli->connect_error]);
    exit;
}

$response = [];

// 1. Total submissions
$result = $mysqli->query("SELECT COUNT(*) as total FROM device_submissions");
$response['total_submissions'] = $result->fetch_assoc()['total'] ?? 0;

// 2. Pending submissions
$result = $mysqli->query("SELECT COUNT(*) as pending FROM device_submissions WHERE clerk_review_status = 'pending' OR status = 'pending'");
$response['pending_submissions'] = $result->fetch_assoc()['pending'] ?? 0;

// 3. Total estimated value
$result = $mysqli->query("SELECT SUM(estimated_price) as total FROM device_submissions");
$response['total_value'] = $result->fetch_assoc()['total'] ?? 0;

// 4. Average price
$result = $mysqli->query("SELECT AVG(estimated_price) as avg FROM device_submissions");
$response['avg_price'] = round($result->fetch_assoc()['avg'] ?? 0, 2);

// 5. Status counts
$result = $mysqli->query("
    SELECT 
        SUM(CASE WHEN clerk_review_status = 'approved' OR status = 'approved' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN clerk_review_status = 'pending' OR status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN clerk_review_status = 'rejected' OR status = 'rejected' THEN 1 ELSE 0 END) as rejected,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
    FROM device_submissions
");
$statusRow = $result->fetch_assoc();
$response['status_labels'] = ['Pending', 'Approved', 'Rejected', 'Completed'];
$response['status_counts'] = [
    (int)($statusRow['pending'] ?? 0),
    (int)($statusRow['approved'] ?? 0),
    (int)($statusRow['rejected'] ?? 0),
    (int)($statusRow['completed'] ?? 0)
];

// 6. Device type distribution
$result = $mysqli->query("
    SELECT device_type, COUNT(*) as count 
    FROM device_submissions 
    WHERE device_type IS NOT NULL AND device_type != ''
    GROUP BY device_type 
    ORDER BY count DESC
    LIMIT 10
");
$deviceTypes = [];
$deviceCounts = [];
while ($row = $result->fetch_assoc()) {
    $deviceTypes[] = $row['device_type'];
    $deviceCounts[] = (int)$row['count'];
}
$response['device_labels'] = $deviceTypes;
$response['device_counts'] = $deviceCounts;

// 7. Repair statistics
$result = $mysqli->query("
    SELECT 
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'awaiting_dropoff' THEN 1 ELSE 0 END) as awaiting,
        SUM(CASE WHEN status = 'in_repair' THEN 1 ELSE 0 END) as in_repair,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
    FROM device_repairs
");
$repairRow = $result->fetch_assoc();
$response['repair_labels'] = ['Pending', 'Awaiting Dropoff', 'In Repair', 'Completed'];
$response['repair_counts'] = [
    (int)($repairRow['pending'] ?? 0),
    (int)($repairRow['awaiting'] ?? 0),
    (int)($repairRow['in_repair'] ?? 0),
    (int)($repairRow['completed'] ?? 0)
];

// 8. Price statistics
$result = $mysqli->query("
    SELECT 
        AVG(estimated_price) as avg_user,
        AVG(clerk_approved_price) as avg_clerk,
        MAX(estimated_price) as max_price,
        MIN(estimated_price) as min_price
    FROM device_submissions
");
$priceRow = $result->fetch_assoc();
$response['avg_user_price'] = round($priceRow['avg_user'] ?? 0, 2);
$response['avg_clerk_price'] = round($priceRow['avg_clerk'] ?? 0, 2);
$response['max_price'] = round($priceRow['max_price'] ?? 0, 2);
$response['min_price'] = round($priceRow['min_price'] ?? 0, 2);

// 9. Monthly trend
$result = $mysqli->query("
    SELECT DATE_FORMAT(submitted_at, '%Y-%m') as month, COUNT(*) as count
    FROM device_submissions
    WHERE submitted_at IS NOT NULL
    GROUP BY DATE_FORMAT(submitted_at, '%Y-%m')
    ORDER BY month DESC
    LIMIT 6
");
$months = [];
$monthCounts = [];
while ($row = $result->fetch_assoc()) {
    $months[] = $row['month'];
    $monthCounts[] = (int)$row['count'];
}
$response['month_labels'] = array_reverse($months);
$response['month_counts'] = array_reverse($monthCounts);

// 10. Recent submissions
$result = $mysqli->query("
    SELECT id, device_type, brand, model, estimated_price, status, submitted_at
    FROM device_submissions 
    ORDER BY id DESC 
    LIMIT 10
");
$recent = [];
while ($row = $result->fetch_assoc()) {
    $recent[] = $row;
}
$response['recent_submissions'] = $recent;

$mysqli->close();
echo json_encode($response);
?>