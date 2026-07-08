<?php
$mysqli = new mysqli("localhost", "root", "", "fyp");

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

echo "<h2>Pending Submissions Debug</h2>";

// Check if status column exists
$check_status = $mysqli->query("SHOW COLUMNS FROM device_submissions LIKE 'status'");
if ($check_status && $check_status->num_rows > 0) {
    echo "✓ Status column exists<br><br>";
} else {
    echo "✗ Status column does not exist<br><br>";
}

// Count all submissions
$total = $mysqli->query("SELECT COUNT(*) as count FROM device_submissions")->fetch_assoc()['count'];
echo "Total submissions: " . $total . "<br>";

// Count pending submissions
$pending = $mysqli->query("SELECT COUNT(*) as count FROM device_submissions WHERE status = 'pending'")->fetch_assoc()['count'];
echo "Pending submissions: " . $pending . "<br>";

// Show all submissions with their status
echo "<br><h3>All Submissions:</h3>";
$result = $mysqli->query("SELECT id, device_type, brand, model, status FROM device_submissions");
if ($result && $result->num_rows > 0) {
    echo "<table border='1' cellpadding='10'>";
    echo "<tr><th>ID</th><th>Device Type</th><th>Brand</th><th>Model</th><th>Status</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . htmlspecialchars($row['device_type']) . "</td>";
        echo "<td>" . htmlspecialchars($row['brand']) . "</td>";
        echo "<td>" . htmlspecialchars($row['model']) . "</td>";
        echo "<td>" . htmlspecialchars($row['status']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "No submissions found";
}

// Show distinct status values
echo "<br><h3>Distinct Status Values:</h3>";
$status_result = $mysqli->query("SELECT DISTINCT status, COUNT(*) as count FROM device_submissions GROUP BY status");
if ($status_result && $status_result->num_rows > 0) {
    echo "<ul>";
    while ($row = $status_result->fetch_assoc()) {
        echo "<li>" . htmlspecialchars($row['status'] ?? 'NULL') . ": " . $row['count'] . "</li>";
    }
    echo "</ul>";
}

// Check user_id for ID 5 specifically
echo "<br><h3>ID 5 Details:</h3>";
$id5_result = $mysqli->query("SELECT id, user_id, device_type, brand, model, status FROM device_submissions WHERE id = 5");
if ($id5_result && $id5_result->num_rows > 0) {
    $row = $id5_result->fetch_assoc();
    echo "ID: " . $row['id'] . "<br>";
    echo "User ID: " . $row['user_id'] . "<br>";
    echo "Device Type: " . htmlspecialchars($row['device_type']) . "<br>";
    echo "Brand: " . htmlspecialchars($row['brand']) . "<br>";
    echo "Model: " . htmlspecialchars($row['model']) . "<br>";
    echo "Status: " . htmlspecialchars($row['status']) . "<br>";
    
    // Check if this user exists in users table
    $user_check = $mysqli->query("SELECT id, username FROM users WHERE id = " . $row['user_id']);
    if ($user_check && $user_check->num_rows > 0) {
        $user = $user_check->fetch_assoc();
        echo "User exists: " . htmlspecialchars($user['username']) . "<br>";
    } else {
        echo "⚠️ User ID " . $row['user_id'] . " does NOT exist in users table!<br>";
    }
} else {
    echo "ID 5 not found";
}

$mysqli->close();
?>
