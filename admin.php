<?php
session_start();
$mysqli = new mysqli("localhost", "root", "", "fyp");

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'admin') {
    header('Location: login.php');
    exit;
}

// Get statistics
$total_users = $mysqli->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'];
$total_orders = $mysqli->query("SELECT COUNT(*) as count FROM orders")->fetch_assoc()['count'];
$total_submissions = $mysqli->query("SELECT COUNT(*) as count FROM device_submissions")->fetch_assoc()['count'];

// Check if orders table has total column
$orders_columns = $mysqli->query("SHOW COLUMNS FROM orders LIKE 'total'");
if ($orders_columns && $orders_columns->num_rows > 0) {
    $total_revenue = $mysqli->query("SELECT SUM(total) as total FROM orders WHERE status = 'delivered'")->fetch_assoc()['total'] ?? 0;
} else {
    $total_revenue = 0;
}

// Check if device_submissions table has status column
$ds_columns = $mysqli->query("SHOW COLUMNS FROM device_submissions LIKE 'status'");
if ($ds_columns && $ds_columns->num_rows > 0) {
    $pending_submissions = $mysqli->query("SELECT COUNT(*) as count FROM device_submissions WHERE status = 'pending'")->fetch_assoc()['count'];
} else {
    $pending_submissions = 0;
}

// Calculate notification count (pending submissions + unread notifications)
$unread_notifications = 0;
$notifications_table_exists = $mysqli->query("SHOW TABLES LIKE 'notifications'");
if ($notifications_table_exists && $notifications_table_exists->num_rows > 0) {
    $unread_notifications = $mysqli->query("SELECT COUNT(*) as count FROM notifications WHERE is_read = 0")->fetch_assoc()['count'] ?? 0;
}
$total_notifications = $pending_submissions + $unread_notifications;

// Check if orders table exists and has necessary columns
$orders_table_exists = $mysqli->query("SHOW TABLES LIKE 'orders'");
$has_order_date = $mysqli->query("SHOW COLUMNS FROM orders LIKE 'order_date'");
if ($orders_table_exists && $orders_table_exists->num_rows > 0 && $has_order_date && $has_order_date->num_rows > 0) {
    $recent_orders = $mysqli->query("SELECT o.*, u.username FROM orders o JOIN users u ON o.user_id = u.id ORDER BY o.order_date DESC LIMIT 5");
} else {
    $recent_orders = null;
}

// Check if device_submissions table has submitted_at column
$has_submitted_at = $mysqli->query("SHOW COLUMNS FROM device_submissions LIKE 'submitted_at'");
if ($has_submitted_at && $has_submitted_at->num_rows > 0) {
    $recent_submissions = $mysqli->query("SELECT ds.*, u.username FROM device_submissions ds JOIN users u ON ds.user_id = u.id ORDER BY ds.submitted_at DESC LIMIT 5");
} else {
    $recent_submissions = $mysqli->query("SELECT ds.*, u.username FROM device_submissions ds JOIN users u ON ds.user_id = u.id ORDER BY ds.id DESC LIMIT 5");
}

// Handle section display
$section = isset($_GET['section']) ? $_GET['section'] : 'dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Fix N Flip</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="admin.css">
</head>
<body>
    <div class="admin-container">
        <aside class="sidebar">
            <h2>Fix N Flip</h2>
            <nav>
                <ul>
                    <li><a href="admin.php?section=dashboard" class="<?php echo $section === 'dashboard' ? 'active' : ''; ?>">Dashboard</a></li>
                    <li><a href="admin.php?section=users" class="<?php echo $section === 'users' ? 'active' : ''; ?>">Users</a></li>
                    <li><a href="admin.php?section=submissions" class="<?php echo $section === 'submissions' ? 'active' : ''; ?>" style="position: relative;">
                        Device Submissions
                        <?php if ($pending_submissions > 0): ?>
                            <span style="position: absolute; top: -5px; right: -5px; background-color: #ef4444; color: white; border-radius: 50%; width: 18px; height: 18px; display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: bold;"><?php echo $pending_submissions > 9 ? '9+' : $pending_submissions; ?></span>
                        <?php endif; ?>
                    </a></li>
                    <li><a href="admin.php?section=products" class="<?php echo $section === 'products' ? 'active' : ''; ?>">Products</a></li>
                    <li><a href="admin.php?section=orders" class="<?php echo $section === 'orders' ? 'active' : ''; ?>">Orders</a></li>
                    <li><a href="admin.php?section=notifications" class="<?php echo $section === 'notifications' ? 'active' : ''; ?>" style="position: relative;">
                        Notifications
                        <?php if ($unread_notifications > 0): ?>
                            <span style="position: absolute; top: -5px; right: -5px; background-color: #ef4444; color: white; border-radius: 50%; width: 18px; height: 18px; display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: bold;"><?php echo $unread_notifications > 9 ? '9+' : $unread_notifications; ?></span>
                        <?php endif; ?>
                    </a></li>
                    <li><a href="admin.php?section=feedback" class="<?php echo $section === 'feedback' ? 'active' : ''; ?>">Feedback</a></li>
                    <li><a href="admin.php?section=roles" class="<?php echo $section === 'roles' ? 'active' : ''; ?>">Roles</a></li>
                    <li><a href="admin.php?section=profile" class="<?php echo $section === 'profile' ? 'active' : ''; ?>">Profile</a></li>
                </ul>
            </nav>
            <div class="sidebar-footer">
                <a href="logout.php" class="logout-btn">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                        <polyline points="16 17 21 12 16 7"></polyline>
                        <line x1="21" y1="12" x2="9" y2="12"></line>
                    </svg>
                    Logout
                </a>
            </div>
        </aside>

        <main class="main-content">
            <div class="header">
                <h1><?php echo ucfirst($section); ?></h1>
                <div class="user-info">
                    <span>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></span>
                </div>
            </div>

            <?php if ($section === 'dashboard'): ?>
                <?php include 'admin_dashboard_content.php'; ?>

            <?php elseif ($section === 'users'): ?>
                <?php include 'admin_users.php'; ?>

            <?php elseif ($section === 'submissions'): ?>
                <?php include 'admin_submissions.php'; ?>

            <?php elseif ($section === 'products'): ?>
                <?php include 'admin_products.php'; ?>

            <?php elseif ($section === 'orders'): ?>
                <?php include 'admin_orders.php'; ?>

            <?php elseif ($section === 'notifications'): ?>
                <?php include 'admin_notifications.php'; ?>

            <?php elseif ($section === 'feedback'): ?>
                <div class="content-section">
                    <h2>Feedback Responses</h2>
                    <p>Click the button below to view the feedback responses and analytics in Google Forms.</p>
                    <a href="https://docs.google.com/forms/d/161K2ToShNCwpWGCnjdlUFsMlwavY_WFdmSg30t5Iqgc/edit?pli=1#responses" target="_blank" style="display: inline-block; padding: 12px 24px; background-color: #3b82f6; color: white; text-decoration: none; border-radius: 6px; margin-top: 10px;">Open Feedback Responses</a>
                </div>

            <?php elseif ($section === 'roles'): ?>
                <?php include 'admin_roles.php'; ?>

            <?php elseif ($section === 'profile'): ?>
                <?php include 'admin_profile.php'; ?>

            <?php endif; ?>
        </main>
    </div>

    <script src="admin.js"></script>
</body>
</html>

