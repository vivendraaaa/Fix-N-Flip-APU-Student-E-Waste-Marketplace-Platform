<?php
session_start();

// Database connection
$mysqli = new mysqli("localhost", "root", "", "fyp");

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    header("Location: Login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch cart count
$cart_count = 0;
$check_table = "SHOW TABLES LIKE 'cart'";
$table_result = $mysqli->query($check_table);
if ($table_result && $table_result->num_rows > 0) {
    $query = "SELECT SUM(quantity) as total FROM cart WHERE user_id = ?";
    $stmt = $mysqli->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $cart_count = $row['total'] ?: 0;
}

// Handle clear notifications
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'clear_notifications') {
    $delete_query = "DELETE FROM notifications WHERE user_id = ?";
    $stmt = $mysqli->prepare($delete_query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    header("Location: notifications.php");
    exit();
}

// Handle mark as read
if (isset($_GET['mark_read'])) {
    $notification_id = intval($_GET['mark_read']);
    $update_query = "UPDATE notifications SET is_read = TRUE WHERE id = ? AND user_id = ?";
    $stmt = $mysqli->prepare($update_query);
    $stmt->bind_param("ii", $notification_id, $user_id);
    $stmt->execute();
    header("Location: notifications.php");
    exit();
}

// Mark all notifications as read when the page is opened
if ($_SERVER['REQUEST_METHOD'] === 'GET' && !isset($_GET['mark_read'])) {
    $mark_all_read_query = "UPDATE notifications SET is_read = TRUE WHERE user_id = ? AND is_read = FALSE";
    $stmt = $mysqli->prepare($mark_all_read_query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
}

// Fetch notification count
$notification_count = 0;
$check_table = "SHOW TABLES LIKE 'notifications'";
$table_result = $mysqli->query($check_table);
if ($table_result && $table_result->num_rows > 0) {
    $query = "SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND is_read = FALSE";
    $stmt = $mysqli->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $notification_count = $row['total'] ?: 0;
}

// Fetch all notifications
$notifications = [];
$check_table = "SHOW TABLES LIKE 'notifications'";
$table_result = $mysqli->query($check_table);
if ($table_result && $table_result->num_rows > 0) {
    $notifications_query = "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC";
    $stmt = $mysqli->prepare($notifications_query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $notifications_result = $stmt->get_result();
    while ($notification = $notifications_result->fetch_assoc()) {
        $notifications[] = $notification;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fix N Flip - Notifications</title>
    <link rel="stylesheet" href="profile.css">
    <style>
        .notifications-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            text-align: left;
        }
        .notification-card {
            display: block;
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-left: 4px solid #10b981;
            text-align: left;
            cursor: pointer;
            transition: background 0.2s;
            text-decoration: none;
            color: inherit;
        }
        .notification-card:hover {
            background: #f9fafb;
        }
        .notification-card.type-rejection {
            border-left-color: #ef4444;
        }
        .notification-card.type-flag {
            border-left-color: #8b5cf6;
        }
        .notification-card.type-approval {
            border-left-color: #10b981;
        }
        .notification-card.unread {
            background: #fffbeb;
        }
        .notification-card.type-rejection.unread {
            background: #fef2f2;
        }
        .notification-card.type-flag.unread {
            background: #f5f3ff;
        }
        .notification-card .notification-message {
            font-size: 16px;
            color: #333;
            margin-bottom: 10px;
            text-align: left;
            line-height: 1.5;
        }
        .notification-card.unread .notification-message {
            font-weight: bold;
        }
        .notification-card .notification-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 14px;
            color: #666;
            text-align: left;
        }
        .notification-card .notification-type {
            background: #10b981;
            color: white;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            text-transform: capitalize;
        }
        .notification-card.type-rejection .notification-type {
            background: #ef4444;
        }
        .notification-card.type-flag .notification-type {
            background: #8b5cf6;
        }
        .notification-card .notification-date {
            color: #999;
        }
        .no-notifications {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }
        .no-notifications svg {
            width: 80px;
            height: 80px;
            margin-bottom: 20px;
            color: #ccc;
        }
        footer {
            background: #10b981;
            color: white;
            text-align: center;
            padding: 20px;
            margin-top: 40px;
            position: relative;
            bottom: 0;
            width: 100%;
        }
        body {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .notifications-container {
            flex: 1;
        }

        /* Footer Styles */
        footer {
            background-color: #10b981;
            color: white;
            text-align: center;
            padding: 20px;
            margin-top: 40px;
        }

        footer p {
            margin: 0 0 15px 0;
        }

        .footer-links {
            display: flex;
            justify-content: center;
            gap: 20px;
            align-items: center;
        }

        .footer-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(145deg, rgba(255, 255, 255, 0.2), rgba(255, 255, 255, 0.1));
            color: white;
            text-decoration: none;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            position: relative;
            overflow: hidden;
        }

        .footer-icon::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(145deg, rgba(255, 255, 255, 0.3), rgba(255, 255, 255, 0.1));
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .footer-icon:hover::before {
            opacity: 1;
        }

        .footer-icon:hover {
            background: linear-gradient(145deg, rgba(255, 255, 255, 0.4), rgba(255, 255, 255, 0.2));
            transform: scale(1.15) translateY(-3px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
        }

        .footer-icon svg {
            width: 24px;
            height: 24px;
            position: relative;
            z-index: 1;
            transition: transform 0.3s ease;
        }

        .footer-icon:hover svg {
            transform: scale(1.1);
        }
    </style>
</head>
<body>
    <header>
        <h1>Fix N Flip</h1>
        <p>Buy & Sell Used Devices for APU Students</p>
    </header>
    <nav>
        <ul>
            <li><a href="Index.php">Home</a></li>
            <li><a href="Buy.php">Buy</a></li>
            <li><a href="Sell.php">Sell</a></li>
            <li><a href="AboutUs.php">About Us</a></li>
        </ul>
        <ul>
            <li><a href="AddToCart.php">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M3 3H5L5.4 5M7 13H17L21 5H5.4M7 13L5.4 5M7 13L4.707 15.293C4.077 15.923 4.523 17 5.414 17H17M17 17C15.895 17 15 17.895 15 19C15 20.105 15.895 21 17 21C18.105 21 19 20.105 19 19C19 17.895 18.105 17 17 17ZM9 19C9 20.105 8.105 21 7 21C5.895 21 5 20.105 5 19C5 17.895 5.895 17 7 17C8.105 17 9 17.895 9 19Z" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                </svg>
                Cart
                <?php if ($cart_count > 0): ?>
                    <span class="cart-badge"><?php echo $cart_count; ?></span>
                <?php endif; ?>
            </a></li>
            <li><a href="notifications.php">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                    <path d="M13.73 21a2 2 0 0 1-3.46 0" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                </svg>
                Notifications
            </a></li>
            <li>
                <a href="profile.php">
                    <img src="<?php echo $_SESSION['pfp']; ?>" alt="Profile" class="profile-pic">
                    <?php echo $_SESSION['username']; ?>
                </a>
            </li>
        </ul>
    </nav>

    <div class="notifications-container">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h2>Notifications</h2>
            <?php if (!empty($notifications)): ?>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="clear_notifications">
                    <button type="submit" class="btn btn-danger" style="padding: 8px 16px; border-radius: 8px; border: none; background: #ef4444; color: white; cursor: pointer; font-size: 14px;">Clear All</button>
                </form>
            <?php endif; ?>
        </div>
        <?php if (empty($notifications)): ?>
            <div class="no-notifications">
                <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                    <path d="M13.73 21a2 2 0 0 1-3.46 0" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                </svg>
                <p>No notifications found.</p>
            </div>
        <?php else: ?>
            <?php foreach ($notifications as $notification): ?>
                <a href="profile.php#submissions" class="notification-card type-<?php echo $notification['type']; ?> <?php echo !$notification['is_read'] ? 'unread' : ''; ?>">
                    <p class="notification-message"><?php echo nl2br(htmlspecialchars($notification['message'])); ?></p>
                    <div class="notification-meta">
                        <span class="notification-type"><?php echo str_replace('_', ' ', $notification['type']); ?></span>
                        <span class="notification-date"><?php echo date('F j, Y g:i A', strtotime($notification['created_at'])); ?></span>
                    </div>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <footer id="contact">
        <p>&copy; 2026 Fix N Flip. All rights reserved.</p>
        <div class="footer-links">
            <a href="https://mail.google.com/mail/?view=cm&fs=1&to=mohdkaiser1806@gmail.com&su=Subject%20Line&body=Hi Fix N Flip, I would like more info on this.!" target="_blank" class="footer-icon" title="Contact via Gmail">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                    <polyline points="22,6 12,13 2,6"></polyline>
                </svg>
            </a>
            <a href="https://wa.me/+60109629725" target="_blank" class="footer-icon" title="Contact via WhatsApp">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                </svg>
            </a>
        </div>
    </footer>
</body>
</html>
