<?php
// Only start session if one isn't already active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in (support both session formats)
$is_logged_in = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] || isset($_SESSION['user_id']);
if (!$is_logged_in) {
    header('Location: Login.php');
    exit;
}

// Check if user is admin
$user_role = isset($_SESSION['role']) ? strtolower($_SESSION['role']) : '';
if ($user_role !== 'admin') {
    header('Location: Index.php');
    exit;
}

$mysqli = new mysqli("localhost", "root", "", "fyp");

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

// Handle POST requests for marking notifications
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'mark_read' && isset($_POST['notification_id'])) {
            $notification_id = intval($_POST['notification_id']);
            $stmt = $mysqli->prepare("UPDATE notifications SET is_read = 1 WHERE id = ?");
            $stmt->bind_param('i', $notification_id);
            $stmt->execute();
            $stmt->close();
            echo json_encode(['success' => 'Notification marked as read']);
            exit;
        }
        
        if ($_POST['action'] === 'mark_unread' && isset($_POST['notification_id'])) {
            $notification_id = intval($_POST['notification_id']);
            $stmt = $mysqli->prepare("UPDATE notifications SET is_read = 0 WHERE id = ?");
            $stmt->bind_param('i', $notification_id);
            $stmt->execute();
            $stmt->close();
            echo json_encode(['success' => 'Notification marked as unread']);
            exit;
        }
        
        if ($_POST['action'] === 'mark_all_read') {
            $stmt = $mysqli->prepare("UPDATE notifications SET is_read = 1");
            $stmt->execute();
            $stmt->close();
            echo json_encode(['success' => 'All notifications marked as read']);
            exit;
        }
        
        if ($_POST['action'] === 'delete_notification' && isset($_POST['notification_id'])) {
            $notification_id = intval($_POST['notification_id']);
            $stmt = $mysqli->prepare("DELETE FROM notifications WHERE id = ?");
            $stmt->bind_param('i', $notification_id);
            $stmt->execute();
            $stmt->close();
            echo json_encode(['success' => 'Notification deleted']);
            exit;
        }
    }
}

// Get filters
$read_filter = isset($_GET['read']) ? $_GET['read'] : 'unread'; // default to unread
$type_filter = isset($_GET['type']) ? $_GET['type'] : 'all'; // all, or actual types from database

// Get distinct types that exist in the database
$types_result = $mysqli->query("SELECT DISTINCT type FROM notifications WHERE type IS NOT NULL AND type != '' ORDER BY type");
$existing_types = [];
if ($types_result) {
    while ($row = $types_result->fetch_assoc()) {
        $existing_types[] = $row['type'];
    }
}

// Build query - only show admin-relevant notifications (exclude user-specific notifications like submissions)
$query = "SELECT n.*, u.username FROM notifications n LEFT JOIN users u ON n.user_id = u.id WHERE 1=1";

// Filter out user-specific notification types that are not relevant to admin
$admin_relevant_types = ['flag', 'approval', 'rejection', 'price_change', 'system'];
$query .= " AND (n.type IN ('" . implode("','", $admin_relevant_types) . "') OR n.type IS NULL OR n.type = '')";

if ($read_filter === 'read') {
    $query .= " AND n.is_read = 1";
} elseif ($read_filter === 'unread') {
    $query .= " AND n.is_read = 0";
}

if ($type_filter !== 'all' && in_array($type_filter, $existing_types)) {
    $query .= " AND n.type = '$type_filter'";
}

$query .= " ORDER BY n.created_at DESC";

$notifications = $mysqli->query($query);

// Get notification counts
$total_count = $mysqli->query("SELECT COUNT(*) as count FROM notifications")->fetch_assoc()['count'];
$read_count = $mysqli->query("SELECT COUNT(*) as count FROM notifications WHERE is_read = 1")->fetch_assoc()['count'];
$unread_count = $mysqli->query("SELECT COUNT(*) as count FROM notifications WHERE is_read = 0")->fetch_assoc()['count'];

// Get counts by type
$type_counts = [];
foreach ($existing_types as $type) {
    if (!empty($type)) {
        $count = $mysqli->query("SELECT COUNT(*) as count FROM notifications WHERE type = '$type'")->fetch_assoc()['count'];
        $type_counts[$type] = $count;
    }
}
?>

<div class="content-section">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h2>Notifications</h2>
        <button onclick="markAllAsRead()" style="padding: 8px 16px; border-radius: 6px; border: none; background-color: #10b981; color: white; cursor: pointer; font-weight: 500;">Mark All as Read</button>
    </div>
    
    <!-- Read/Unread Tabs -->
    <div style="margin-bottom: 20px;">
        <button onclick="filterByRead('all')" class="filter-btn <?php echo $read_filter === 'all' ? 'active' : ''; ?>" data-filter="read-all">All (<?php echo $total_count; ?>)</button>
        <button onclick="filterByRead('unread')" class="filter-btn <?php echo $read_filter === 'unread' ? 'active' : ''; ?>" data-filter="read-unread">Unread (<?php echo $unread_count; ?>)</button>
        <button onclick="filterByRead('read')" class="filter-btn <?php echo $read_filter === 'read' ? 'active' : ''; ?>" data-filter="read-read">Read (<?php echo $read_count; ?>)</button>
    </div>
    
    <!-- Type Tabs -->
    <div style="margin-bottom: 20px;">
        <button onclick="filterByType('all')" class="filter-btn <?php echo $type_filter === 'all' ? 'active' : ''; ?>" data-filter="type-all">All Types</button>
        <?php foreach ($existing_types as $type): ?>
            <?php if (!empty($type)): ?>
                <button onclick="filterByType('<?php echo $type; ?>')" class="filter-btn <?php echo $type_filter === $type ? 'active' : ''; ?>" data-filter="type-<?php echo $type; ?>">
                    <?php echo ucfirst(str_replace('_', ' ', $type)); ?> (<?php echo $type_counts[$type] ?? 0; ?>)
                </button>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
    
    <table>
        <thead>
            <tr>
                <th>Status</th>
                <th>Subject</th>
                <th>User</th>
                <th>Type</th>
                <th>Date</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($notifications && $notifications->num_rows > 0): ?>
                <?php while ($notification = $notifications->fetch_assoc()): ?>
                    <tr class="notification-row <?php echo $notification['is_read'] ? '' : 'unread-row'; ?>" onclick="toggleNotification(<?php echo $notification['id']; ?>)">
                        <td>
                            <?php if ($notification['is_read']): ?>
                                <span style="padding: 4px 8px; border-radius: 4px; background-color: #6c757d; color: white; font-size: 12px;">Read</span>
                            <?php else: ?>
                                <span style="padding: 4px 8px; border-radius: 4px; background-color: #10b981; color: white; font-size: 12px;">Unread</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong><?php echo htmlspecialchars($notification['subject'] ?? 'No Subject'); ?></strong>
                            <div style="font-size: 12px; color: #666; margin-top: 4px;">
                                <?php echo substr(htmlspecialchars($notification['message']), 0, 80) . (strlen($notification['message']) > 80 ? '...' : ''); ?>
                            </div>
                        </td>
                        <td><?php echo htmlspecialchars($notification['username'] ?? 'N/A'); ?></td>
                        <td>
                            <?php
                            $type_colors = [
                                'price_change' => '#ffc107',
                                'flag' => '#dc3545',
                                'approval' => '#10b981',
                                'rejection' => '#6c757d'
                            ];
                            $color = $type_colors[$notification['type']] ?? '#6c757d';
                            ?>
                            <span style="padding: 4px 8px; border-radius: 4px; background-color: <?php echo $color; ?>; color: white; font-size: 12px;"><?php echo ucfirst(str_replace('_', ' ', $notification['type'])); ?></span>
                        </td>
                        <td><?php echo date('M d, Y H:i', strtotime($notification['created_at'])); ?></td>
                        <td onclick="event.stopPropagation()">
                            <?php if ($notification['is_read']): ?>
                                <button onclick="markAsUnread(<?php echo $notification['id']; ?>)" style="padding: 4px 8px; border-radius: 4px; border: 1px solid #ddd; background-color: white; cursor: pointer; margin-right: 4px;">Mark Unread</button>
                            <?php else: ?>
                                <button onclick="markAsRead(<?php echo $notification['id']; ?>)" style="padding: 4px 8px; border-radius: 4px; border: 1px solid #ddd; background-color: white; cursor: pointer; margin-right: 4px;">Mark Read</button>
                            <?php endif; ?>
                            <button onclick="deleteNotification(<?php echo $notification['id']; ?>)" style="padding: 4px 8px; border-radius: 4px; border: 1px solid #dc3545; background-color: white; color: #dc3545; cursor: pointer;">Delete</button>
                        </td>
                    </tr>
                    <tr id="notification-details-<?php echo $notification['id']; ?>" class="notification-details" style="display: none;">
                        <td colspan="6" style="padding: 20px; background-color: #f9f9f9;">
                            <div style="margin-bottom: 10px;"><strong>Full Message:</strong></div>
                            <div style="padding: 15px; background-color: white; border: 1px solid #ddd; border-radius: 4px;">
                                <?php echo nl2br(htmlspecialchars($notification['message'])); ?>
                            </div>
                            <div style="margin-top: 15px;">
                                <strong>Notification ID:</strong> <?php echo $notification['id']; ?><br>
                                <strong>Created:</strong> <?php echo date('F d, Y g:i A', strtotime($notification['created_at'])); ?>
                            </div>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="6" style="text-align: center; padding: 20px;">No notifications found</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<style>
.filter-btn {
    padding: 8px 16px;
    border-radius: 6px;
    border: 1px solid #ddd;
    background-color: white;
    cursor: pointer;
    margin-right: 8px;
    font-weight: 500;
    transition: all 0.3s ease;
}

.filter-btn:hover {
    background-color: #f5f5f5;
}

.filter-btn.active {
    background-color: #10b981;
    color: white;
    border-color: #10b981;
}

.unread-row {
    background-color: #f0fff4;
}

.notification-row {
    cursor: pointer;
    transition: background-color 0.2s ease;
}

.notification-row:hover {
    background-color: #f5f5f5;
}

.notification-details {
    border-bottom: 2px solid #10b981;
}
</style>

<script>
function toggleNotification(notificationId) {
    const detailsRow = document.getElementById('notification-details-' + notificationId);
    if (detailsRow.style.display === 'none') {
        detailsRow.style.display = 'table-row';
    } else {
        detailsRow.style.display = 'none';
    }
}

function filterByRead(filter) {
    const currentType = '<?php echo $type_filter; ?>';
    window.location.href = 'admin.php?section=notifications&read=' + filter + '&type=' + currentType;
}

function filterByType(filter) {
    const currentRead = '<?php echo $read_filter; ?>';
    window.location.href = 'admin.php?section=notifications&read=' + currentRead + '&type=' + filter;
}

function markAsRead(notificationId) {
    const formData = new FormData();
    formData.append('action', 'mark_read');
    formData.append('notification_id', notificationId);

    fetch('admin_notifications.php', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred. Please try again.');
    });
}

function markAsUnread(notificationId) {
    const formData = new FormData();
    formData.append('action', 'mark_unread');
    formData.append('notification_id', notificationId);

    fetch('admin_notifications.php', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred. Please try again.');
    });
}

function markAllAsRead() {
    if (confirm('Mark all notifications as read?')) {
        const formData = new FormData();
        formData.append('action', 'mark_all_read');

        fetch('admin_notifications.php', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred. Please try again.');
        });
    }
}

function deleteNotification(notificationId) {
    if (confirm('Delete this notification?')) {
        const formData = new FormData();
        formData.append('action', 'delete_notification');
        formData.append('notification_id', notificationId);

        fetch('admin_notifications.php', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred. Please try again.');
        });
    }
}
</script>
