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

// Fetch unread notification count
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

// Fetch user data
$user_query = "SELECT * FROM users WHERE id = ?";
$stmt = $mysqli->prepare($user_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_result = $stmt->get_result();
$user = $user_result->fetch_assoc();

// Check if pfp column exists, if not set default
if (!isset($user['pfp'])) {
    $user['pfp'] = '';
}

// Handle form submissions
$message = "";
$message_type = "";

// Update profile
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'update_profile') {
        $username = trim($_POST['username']);
        $full_name = trim($_POST['full_name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        
        // Handle profile picture upload
        $profile_pic = $user['pfp'];
        if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] == 0) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif'];
            $filename = $_FILES['profile_pic']['name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            
            if (in_array($ext, $allowed)) {
                $new_name = uniqid() . '.' . $ext;
                $upload_path = 'uploads/' . $new_name;
                
                if (!is_dir('uploads')) {
                    mkdir('uploads', 0777, true);
                }
                
                if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $upload_path)) {
                    $profile_pic = $upload_path;
                }
            }
        }
        
        // Update user
        // Check if pfp column exists
        $check_column = "SHOW COLUMNS FROM users LIKE 'pfp'";
        $column_result = $mysqli->query($check_column);
        $has_pfp = $column_result && $column_result->num_rows > 0;

        // Check if full_name and phone columns exist
        $check_full_name = "SHOW COLUMNS FROM users LIKE 'full_name'";
        $full_name_result = $mysqli->query($check_full_name);
        $has_full_name = $full_name_result && $full_name_result->num_rows > 0;
        
        $check_phone = "SHOW COLUMNS FROM users LIKE 'phone'";
        $phone_result = $mysqli->query($check_phone);
        $has_phone = $phone_result && $phone_result->num_rows > 0;

        if ($has_pfp && $has_full_name && $has_phone) {
            $update_query = "UPDATE users SET username = ?, full_name = ?, email = ?, phone = ?, pfp = ? WHERE id = ?";
            $stmt = $mysqli->prepare($update_query);
            $stmt->bind_param("sssssi", $username, $full_name, $email, $phone, $profile_pic, $user_id);
        } elseif ($has_pfp && $has_full_name) {
            $update_query = "UPDATE users SET username = ?, full_name = ?, email = ?, pfp = ? WHERE id = ?";
            $stmt = $mysqli->prepare($update_query);
            $stmt->bind_param("ssssi", $username, $full_name, $email, $profile_pic, $user_id);
        } elseif ($has_pfp) {
            $update_query = "UPDATE users SET username = ?, email = ?, pfp = ? WHERE id = ?";
            $stmt = $mysqli->prepare($update_query);
            $stmt->bind_param("sssi", $username, $email, $profile_pic, $user_id);
        } elseif ($has_full_name && $has_phone) {
            $update_query = "UPDATE users SET username = ?, full_name = ?, email = ?, phone = ? WHERE id = ?";
            $stmt = $mysqli->prepare($update_query);
            $stmt->bind_param("ssssi", $username, $full_name, $email, $phone, $user_id);
        } else {
            $update_query = "UPDATE users SET username = ?, email = ? WHERE id = ?";
            $stmt = $mysqli->prepare($update_query);
            $stmt->bind_param("ssi", $username, $email, $user_id);
        }

        if ($stmt->execute()) {
            $_SESSION['username'] = $username;
            if ($has_pfp) {
                $_SESSION['pfp'] = $profile_pic;
            }
            $message = "Profile updated successfully!";
            $message_type = "success";

            // Refresh user data
            $user['username'] = $username;
            $user['email'] = $email;
            if ($has_full_name) {
                $user['full_name'] = $full_name;
            }
            if ($has_phone) {
                $user['phone'] = $phone;
            }
            if ($has_pfp) {
                $user['pfp'] = $profile_pic;
            }
        } else {
            $message = "Error updating profile. Username or email may already be in use.";
            $message_type = "error";
        }
    }
    
    // Change password
    if ($_POST['action'] == 'change_password') {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];

        if (password_verify($current_password, $user['password'])) {
            if ($new_password === $confirm_password) {
                if (strlen($new_password) >= 8) {
                    if ($current_password !== $new_password) {
                        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                        $password_query = "UPDATE users SET password = ? WHERE id = ?";
                        $stmt = $mysqli->prepare($password_query);
                        $stmt->bind_param("si", $hashed_password, $user_id);

                        if ($stmt->execute()) {
                            $message = "Password changed successfully!";
                            $message_type = "success";
                        } else {
                            $message = "Error changing password.";
                            $message_type = "error";
                        }
                    } else {
                        $message = "New password cannot be the same as your current password.";
                        $message_type = "error";
                    }
                } else {
                    $message = "Password must be at least 8 characters.";
                    $message_type = "error";
                }
            } else {
                $message = "New passwords do not match.";
                $message_type = "error";
            }
        } else {
            $message = "Current password is incorrect.";
            $message_type = "error";
        }
    }
    
    // Request live chat support
    if ($_POST['action'] == 'request_live_chat') {
        $question = trim($_POST['question'] ?? '');
        if ($question !== '') {
            $check_pending = "SELECT id FROM live_chat_requests WHERE user_id = ? AND status IN ('pending', 'accepted')";
            $stmt = $mysqli->prepare($check_pending);
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $existing_request = $stmt->get_result();

            if ($existing_request && $existing_request->num_rows > 0) {
                $message = "You already have an active live chat request.";
                $message_type = "error";
            } else {
                $insert_query = "INSERT INTO live_chat_requests (user_id, question, status) VALUES (?, ?, 'pending')";
                $stmt = $mysqli->prepare($insert_query);
                $stmt->bind_param("is", $user_id, $question);

                if ($stmt->execute()) {
                    $message = "Your live chat request has been sent to our clerks.";
                    $message_type = "success";
                } else {
                    $message = "Failed to send live chat request.";
                    $message_type = "error";
                }
            }
        } else {
            $message = "Please enter your question before sending the request.";
            $message_type = "error";
        }
    }
    
    // Request account deletion
    if ($_POST['action'] == 'request_deletion') {
        // Check if there's already a pending deletion
        $check_deletion = "SELECT * FROM account_deletion_requests WHERE user_id = ? AND status = 'pending'";
        $stmt = $mysqli->prepare($check_deletion);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $existing = $stmt->get_result();
        
        if ($existing->num_rows > 0) {
            $message = "You already have a pending account deletion request.";
            $message_type = "error";
        } else {
            // Create deletion request
            $deletion_query = "INSERT INTO account_deletion_requests (user_id, requested_at, status) VALUES (?, NOW(), 'pending')";
            $stmt = $mysqli->prepare($deletion_query);
            $stmt->bind_param("i", $user_id);
            
            if ($stmt->execute()) {
                // Send confirmation email (placeholder)
                $to = $user['email'];
                $subject = "Account Deletion Request - Fix N Flip";
                $email_body = "Hello " . $user['username'] . ",\n\nYour account deletion request has been received. Your account will be permanently deleted in 14 days.\n\nIf you log in to your account before the 14-day period, this request will be cancelled.\n\nIf you did not request this deletion, please contact us immediately.\n\nThank you,\nFix N Flip Team";
                
                // In a real implementation, you would use mail() or an email library
                // mail($to, $subject, $email_body);
                
                $message = "Account deletion request submitted. A confirmation email has been sent to " . $user['email'] . ". Your account will be deleted in 14 days.";
                $message_type = "success";
            } else {
                $message = "Error submitting deletion request.";
                $message_type = "error";
            }
        }
    }
    
    // Cancel account deletion
    if ($_POST['action'] == 'cancel_deletion') {
        $cancel_query = "UPDATE account_deletion_requests SET status = 'cancelled' WHERE user_id = ? AND status = 'pending'";
        $stmt = $mysqli->prepare($cancel_query);
        $stmt->bind_param("i", $user_id);
        
        if ($stmt->execute()) {
            $message = "Account deletion request cancelled.";
            $message_type = "success";
        } else {
            $message = "Error cancelling deletion request.";
            $message_type = "error";
        }
    }
    
    // Logout
    if ($_POST['action'] == 'logout') {
        session_destroy();
        header("Location: Index.php");
        exit();
    }
}

// Fetch account deletion status
$deletion_status = null;
$check_table = "SHOW TABLES LIKE 'account_deletion_requests'";
$table_result = $mysqli->query($check_table);
if ($table_result && $table_result->num_rows > 0) {
    $deletion_query = "SELECT * FROM account_deletion_requests WHERE user_id = ? ORDER BY requested_at DESC LIMIT 1";
    $stmt = $mysqli->prepare($deletion_query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $deletion_result = $stmt->get_result();
    if ($deletion_result->num_rows > 0) {
        $deletion_status = $deletion_result->fetch_assoc();
        
        // Cancel deletion if user logs in (this check is on every page load)
        if ($deletion_status['status'] == 'pending') {
            $cancel_query = "UPDATE account_deletion_requests SET status = 'cancelled' WHERE user_id = ? AND status = 'pending'";
            $stmt = $mysqli->prepare($cancel_query);
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $deletion_status['status'] = 'cancelled';
        }
    }
}

// Fetch purchase history
$purchase_history = [];
$check_table = "SHOW TABLES LIKE 'orders'";
$table_result = $mysqli->query($check_table);
if ($table_result && $table_result->num_rows > 0) {
    // Check if order_date column exists
    $check_column = "SHOW COLUMNS FROM orders LIKE 'order_date'";
    $column_result = $mysqli->query($check_column);
    $has_order_date = $column_result && $column_result->num_rows > 0;
    
    $orders_query = "SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC";
    $stmt = $mysqli->prepare($orders_query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $orders_result = $stmt->get_result();
    while ($order = $orders_result->fetch_assoc()) {
        $purchase_history[] = $order;
    }
}

// Fetch device submissions
$submissions = [];
$check_table = "SHOW TABLES LIKE 'device_submissions'";
$table_result = $mysqli->query($check_table);
if ($table_result && $table_result->num_rows > 0) {
    $submissions_query = "SELECT * FROM device_submissions WHERE user_id = ? ORDER BY submitted_at DESC";
    $stmt = $mysqli->prepare($submissions_query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $submissions_result = $stmt->get_result();
    while ($submission = $submissions_result->fetch_assoc()) {
        $submissions[] = $submission;
    }
}

// Fetch notifications
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

// Fetch latest live chat request status
$live_chat_request = null;
$check_live_chat_table = "SHOW TABLES LIKE 'live_chat_requests'";
$live_chat_table_result = $mysqli->query($check_live_chat_table);
if ($live_chat_table_result && $live_chat_table_result->num_rows > 0) {
    $live_chat_query = "SELECT l.*, u.username AS clerk_name FROM live_chat_requests l LEFT JOIN users u ON l.clerk_id = u.id WHERE l.user_id = ? ORDER BY l.created_at DESC LIMIT 1";
    $stmt = $mysqli->prepare($live_chat_query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $live_chat_result = $stmt->get_result();
    if ($live_chat_result && $live_chat_result->num_rows > 0) {
        $live_chat_request = $live_chat_result->fetch_assoc();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fix N Flip - My Profile</title>
    <link rel="stylesheet" href="profile.css">
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
                <?php if ($notification_count > 0): ?>
                    <span class="cart-badge"><?php echo $notification_count; ?></span>
                <?php endif; ?>
            </a></li>
            <li>
                <a href="profile.php">
                    <img src="<?php echo htmlspecialchars($user['pfp']); ?>" alt="Profile" class="profile-pic">
                    <?php echo htmlspecialchars($user['username']); ?>
                </a>
            </li>
        </ul>
    </nav>

    <div class="profile-container">
        <?php if ($message): ?>
            <div class="message <?php echo $message_type; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <div class="profile-header">
            <div class="profile-picture-container" onclick="document.getElementById('profile_pic').click()">
                <img src="<?php echo !empty($user['pfp']) ? htmlspecialchars($user['pfp']) : 'https://placehold.co/120x120?text=No+Profile'; ?>" alt="Profile" class="profile-picture-large">
                <div class="edit-overlay">Edit pic</div>
            </div>
            <h2><?php echo htmlspecialchars($user['username']); ?></h2>
            <p><?php echo htmlspecialchars($user['email']); ?></p>
        </div>

        <div class="profile-tabs">
            <button class="tab-btn active" onclick="showTab('settings')">Settings</button>
            <button class="tab-btn" onclick="showTab('history')">Purchase History</button>
            <button class="tab-btn" onclick="showTab('submissions')">Device Submissions</button>
            <!-- <button class="tab-btn" onclick="showTab('livechat')">Live Chat</button> -->
            <button class="tab-btn" onclick="showTab('account')">Account</button>
        </div>

        <!-- Settings Tab -->
        <div id="settings" class="tab-content active">
            <h3>Profile Settings</h3>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="update_profile">
                <input type="file" name="profile_pic" id="profile_pic" accept="image/*" style="display: none;" onchange="previewProfilePic(this)">
                
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" name="username" id="username" value="<?php echo htmlspecialchars($user['username']); ?>" required>
                </div>

                <div class="form-group">
                    <label for="full_name">Full Name</label>
                    <input type="text" name="full_name" id="full_name" value="<?php echo isset($user['full_name']) ? htmlspecialchars($user['full_name']) : ''; ?>" required>
                </div>

                <div class="form-group">
                    <label for="phone">Phone Number</label>
                    <input type="tel" name="phone" id="phone" value="<?php echo isset($user['phone']) ? htmlspecialchars($user['phone']) : ''; ?>" required>
                </div>

                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" name="email" id="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                </div>

                <button type="submit" class="btn btn-primary">Update Profile</button>
            </form>

            <h3>Change Password</h3>
            <form method="POST">
                <input type="hidden" name="action" value="change_password">
                
                <div class="form-group">
                    <label for="current_password">Current Password</label>
                    <div class="password-input-container">
                        <input type="password" name="current_password" id="current_password" required>
                        <button type="button" class="toggle-password" onclick="togglePassword('current_password')">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="form-group">
                    <label for="new_password">New Password</label>
                    <div class="password-input-container">
                        <input type="password" name="new_password" id="new_password" required minlength="8">
                        <button type="button" class="toggle-password" onclick="togglePassword('new_password')">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                        </button>
                    </div>
                    <small>Password must be at least 8 characters and different from your current password.</small>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm New Password</label>
                    <div class="password-input-container">
                        <input type="password" name="confirm_password" id="confirm_password" required minlength="8">
                        <button type="button" class="toggle-password" onclick="togglePassword('confirm_password')">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">Change Password</button>
            </form>
        </div>

        <!-- Purchase History Tab -->
        <div id="history" class="tab-content">
            <h3>Purchase History</h3>
            <?php if (empty($purchase_history)): ?>
                <p class="no-data">No purchase history found.</p>
            <?php else: ?>
                <div class="history-list">
                    <?php foreach ($purchase_history as $order): ?>
                        <div class="history-item">
                            <h4>Order #<?php echo $order['id']; ?></h4>
                            <p>Date: <?php echo date('F j, Y', strtotime($order['created_at'])); ?></p>
                            <p>Total: RM <?php echo number_format($order['total_amount'], 2); ?></p>
                            <p>Status: <span class="status <?php echo htmlspecialchars($order['status']); ?>"><?php echo ucfirst(str_replace('_', ' ', $order['status'])); ?></span></p>
                            <div class="order-progress">
                                <?php
                                    $steps = [
                                        'pending' => 'Order placed',
                                        'confirmed' => 'Order confirmed',
                                        'packing' => 'Packing',
                                        'processing' => 'Processing',
                                        'ready_for_pickup' => 'Ready for pickup',
                                        'shipped' => 'Shipped',
                                        'delivered' => 'Delivered'
                                    ];
                                    $current = $order['status'];
                                ?>
                                <?php foreach ($steps as $step_status => $step_label): ?>
                                    <div class="progress-step <?php echo $step_status === $current || array_search($step_status, array_keys($steps)) < array_search($current, array_keys($steps)) ? 'active' : ''; ?>">
                                        <span><?php echo $step_label; ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Device Submissions Tab -->
        <div id="submissions" class="tab-content">
            <h3>Device Submissions</h3>
            <?php 
            // Check if there are approved or received submissions for drop-off
            $has_drop_off = false;
            foreach ($submissions as $submission) {
                if (in_array($submission['status'], ['approved', 'received'])) {
                    $has_drop_off = true;
                    break;
                }
            }
            ?>
            <?php if ($has_drop_off): ?>
                <div style="background: #e7f3ff; padding: 15px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #007bff;">
                    <strong>Drop-off Required:</strong> You have approved devices waiting for drop-off. 
                    <a href="drop_off.php" style="color: #007bff; text-decoration: none; font-weight: bold;">View Drop-off QR Codes &rarr;</a>
                </div>
            <?php endif; ?>
            <?php if (empty($submissions)): ?>
                <p class="no-data">No device submissions found.</p>
            <?php else: ?>
                <table class="submissions-table" style="width: 100%; border-collapse: collapse; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                    <thead>
                        <tr>
                            <th>Device</th>
                            <th>Submitted</th>
                            <th>Est. Price</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($submissions as $submission): ?>
                            <tr onclick="window.location.href='user_submission_details.php?id=<?php echo $submission['id']; ?>'" style="cursor: pointer;">
                                <td style="padding: 15px;"><?php echo htmlspecialchars($submission['device_type']); ?> - <?php echo htmlspecialchars($submission['brand']); ?> <?php echo htmlspecialchars($submission['model']); ?></td>
                                <td style="padding: 15px;"><?php echo date('M d, Y', strtotime($submission['submitted_at'])); ?></td>
                                <td style="padding: 15px;">RM<?php echo number_format($submission['estimated_price'], 2); ?></td>
                                <td style="padding: 15px;">
                                    <span style="padding: 5px 12px; border-radius: 20px; font-size: 12px; font-weight: bold; text-transform: uppercase;
                                        <?php
                                        // If device has been picked up (received or later), show as completed
                                        $display_status = $submission['status'];
                                        if (in_array($submission['status'], ['received', 'repair_requested', 'repairing', 'completed', 'disposed'])) {
                                            $display_status = 'completed';
                                        }

                                        $status_colors = [
                                            'pending' => 'background: #fff3cd; color: #856404;',
                                            'awaiting_drop_off' => 'background: #d4edda; color: #155724;',
                                            'approved' => 'background: #d4edda; color: #155724;',
                                            'received' => 'background: #d4edda; color: #155724;',
                                            'repair_requested' => 'background: #d4edda; color: #155724;',
                                            'repairing' => 'background: #d4edda; color: #155724;',
                                            'completed' => 'background: #d4edda; color: #155724;',
                                            'disposed' => 'background: #d4edda; color: #155724;',
                                            'rejected' => 'background: #fee2e2; color: #dc2626;',
                                            'flagged' => 'background: #fce7f3; color: #db2777;'
                                        ];
                                        echo $status_colors[$display_status] ?? 'background: #f5f5f5; color: #333;';
                                        ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $display_status)); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Live Chat Tab -->
        <div id="livechat" class="tab-content">
            <h3>Live Chat Support</h3>
            <?php if ($live_chat_request && in_array($live_chat_request['status'], ['pending', 'accepted'])): ?>
                <div class="info-box" style="margin-bottom: 15px;">
                    <strong>Status:</strong> <?php echo ucfirst($live_chat_request['status']); ?>
                    <?php if ($live_chat_request['status'] === 'accepted' && !empty($live_chat_request['clerk_name'])): ?>
                        <br><strong>Assigned clerk:</strong> <?php echo htmlspecialchars($live_chat_request['clerk_name']); ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if (!$live_chat_request || !in_array($live_chat_request['status'], ['pending', 'accepted'])): ?>
                <form method="POST">
                    <input type="hidden" name="action" value="request_live_chat">
                    <div class="form-group">
                        <label for="question">What do you need help with?</label>
                        <textarea name="question" id="question" rows="5" placeholder="Tell us what you need help with..." required></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">Send Live Chat Request</button>
                </form>
            <?php else: ?>
                <?php if ($live_chat_request['status'] === 'accepted'): ?>
                    <div class="chat-panel">
                        <div class="chat-header">Chatting with <?php echo htmlspecialchars($live_chat_request['clerk_name'] ?? 'Clerk'); ?></div>
                        <div class="chat-messages-panel" id="userChatMessages"></div>
                        <div class="chat-input-panel">
                            <input type="text" id="userChatInput" placeholder="Type your message..." onkeypress="if(event.key === 'Enter') sendUserChatMessage()">
                            <button type="button" class="btn btn-primary" onclick="sendUserChatMessage()">Send</button>
                        </div>
                    </div>
                <?php else: ?>
                    <p class="no-data">Your chat request is waiting for a clerk to accept it.</p>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- Account Tab -->
        <div id="account" class="tab-content">
            <h3>Account Settings</h3>

            <?php if ($deletion_status && $deletion_status['status'] == 'cancelled'): ?>
                <div class="info-box">
                    <p>Your previous account deletion request was cancelled because you logged in.</p>
                </div>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="action" value="logout">
                <button type="submit" class="btn btn-secondary">Logout</button>
            </form>

            <div class="danger-zone">
                <h4>Account Deletion</h4>
                <p>Once you delete your account, there is no going back. Please be certain.</p>

                <?php if ($deletion_status && $deletion_status['status'] == 'pending'): ?>
                    <div class="deletion-pending">
                        <p><strong>Account Deletion Pending</strong></p>
                        <p>Your account will be deleted on: <?php echo date('F j, Y', strtotime($deletion_status['requested_at'] . ' +14 days')); ?></p>
                        <form method="POST">
                            <input type="hidden" name="action" value="cancel_deletion">
                            <button type="submit" class="btn btn-success">Cancel Deletion Request</button>
                        </form>
                    </div>
                <?php else: ?>
                    <form method="POST" onsubmit="return confirm('Are you sure you want to delete your account? This action cannot be undone.');">
                        <input type="hidden" name="action" value="request_deletion">
                        <button type="submit" class="btn btn-danger">Request Account Deletion</button>
                    </form>
                    <small>Your account will be deleted in 14 days. Logging in before then will cancel the request.</small>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Submission Details Modal -->
    <div id="submissionDetailsModal" class="modal" style="display: none;">
        <div class="modal-content" style="max-width: 700px;">
            <div class="modal-header">
                <h3>Submission Details</h3>
                <button class="close-modal" onclick="closeModal('submissionDetailsModal')">&times;</button>
            </div>
            <div id="submissionDetailsContent"></div>
        </div>
    </div>

    <!-- Image Viewer Modal -->
    <div id="imageViewerModal" class="modal" style="display: none;">
        <div class="modal-content" style="max-width: 800px;">
            <div class="modal-header">
                <h3>Image Preview</h3>
                <button class="close-modal" onclick="closeModal('imageViewerModal')">&times;</button>
            </div>
            <div style="text-align: center;">
                <img id="viewerImage" src="" style="max-width: 100%; max-height: 70vh; object-fit: contain;">
            </div>
        </div>
    </div>

    <footer>
        <p>&copy; 2026 Fix N Flip. All rights reserved.</p>
    </footer>

    <style>
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }
        .modal-content {
            background: white;
            border-radius: 12px;
            padding: 20px;
            max-height: 90vh;
            overflow-y: auto;
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e5e7eb;
        }
        .close-modal {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #666;
        }
        .submission-images {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .submission-images img {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: 8px;
            cursor: pointer;
            border: 2px solid #e5e7eb;
        }
        .submission-images img:hover {
            border-color: #10b981;
        }
        .chat-panel {
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            overflow: hidden;
            background: #fff;
        }
        .chat-header {
            background: #10b981;
            color: white;
            padding: 12px 16px;
            font-weight: 600;
        }
        .chat-messages-panel {
            min-height: 320px;
            max-height: 420px;
            overflow-y: auto;
            padding: 16px;
            background: #f9fafb;
        }
        .chat-message-item {
            margin-bottom: 12px;
        }
        .chat-message-item.sent {
            text-align: right;
        }
        .chat-message-item.received {
            text-align: left;
        }
        .chat-bubble {
            display: inline-block;
            max-width: 75%;
            padding: 10px 12px;
            border-radius: 12px;
            white-space: pre-wrap;
        }
        .chat-message-item.sent .chat-bubble {
            background: #10b981;
            color: white;
        }
        .chat-message-item.received .chat-bubble {
            background: #e5e7eb;
            color: #111827;
        }
        .chat-input-panel {
            display: flex;
            gap: 10px;
            padding: 12px;
            background: white;
            border-top: 1px solid #e5e7eb;
        }
        .chat-input-panel input {
            flex: 1;
            padding: 10px 12px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
        }
    </style>

    <script>
        function showTab(tabId, buttonElement = null) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });

            // Remove active class from all buttons
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });

            // Show selected tab
            document.getElementById(tabId).classList.add('active');

            // Add active class to the button
            if (buttonElement) {
                buttonElement.classList.add('active');
            } else if (event && event.target) {
                event.target.classList.add('active');
            } else {
                // Find and activate the button by onclick attribute
                const buttons = document.querySelectorAll('.tab-btn');
                buttons.forEach(btn => {
                    if (btn.getAttribute('onclick') && btn.getAttribute('onclick').includes(`'${tabId}'`)) {
                        btn.classList.add('active');
                    }
                });
            }
        }

        function previewProfilePic(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.querySelector('.profile-picture-large').src = e.target.result;
                }
                reader.readAsDataURL(input.files[0]);
            }
        }

        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const button = input.nextElementSibling;
            if (input.type === 'password') {
                input.type = 'text';
                button.innerHTML = `
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-5.06 5.94M1 1l22 22"></path>
                    </svg>
                `;
            } else {
                input.type = 'password';
                button.innerHTML = `
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                        <circle cx="12" cy="12" r="3"></circle>
                    </svg>
                `;
            }
        }

        function viewSubmissionDetails(id) {
            fetch('get_submission_details.php?id=' + id)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        document.getElementById('submissionDetailsContent').innerHTML = '<p>' + data.error + '</p>';
                        openModal('submissionDetailsModal');
                        return;
                    }

                    let html = '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">';
                    html += '<div><strong>Device Type:</strong> ' + (data.device_type || 'N/A') + '</div>';
                    html += '<div><strong>Brand:</strong> ' + (data.brand || 'N/A') + '</div>';
                    html += '<div><strong>Model:</strong> ' + (data.model || 'N/A') + '</div>';
                    html += '<div><strong>Storage:</strong> ' + (data.storage || 'N/A') + '</div>';
                    html += '<div><strong>RAM:</strong> ' + (data.ram || 'N/A') + '</div>';
                    html += '<div><strong>Battery Health:</strong> ' + (data.battery_health ? data.battery_health + '%' : 'N/A') + '</div>';
                    html += '<div><strong>Condition:</strong> ' + (data.device_condition || 'N/A') + '</div>';
                    html += '<div><strong>Estimated Price:</strong> RM' + (data.estimated_price ? parseFloat(data.estimated_price).toFixed(2) : '0.00') + '</div>';
                    html += '<div><strong>Status:</strong> ' + (data.status || 'N/A') + '</div>';
                    html += '<div><strong>Submitted:</strong> ' + (data.submitted_at ? new Date(data.submitted_at).toLocaleString() : 'N/A') + '</div>';
                    html += '</div>';

                    if (data.description) {
                        html += '<div style="margin-top: 20px;"><strong>Description:</strong><p>' + data.description + '</p></div>';
                    }

                    if (data.qr_code) {
                        html += '<div style="margin-top: 20px;"><strong>QR Code:</strong><p>' + data.qr_code + '</p></div>';
                    }

                    // Show reject reason if exists
                    if (data.reject_reason) {
                        html += '<div style="margin-top: 20px; padding: 15px; background: #fee2e2; border-left: 4px solid #ef4444; border-radius: 8px;"><strong style="color: #ef4444;">Rejection Reason:</strong><p style="margin: 5px 0 0 0;">' + data.reject_reason + '</p></div>';
                    }

                    // Show flag reason and notes if exists
                    if (data.flag_reason) {
                        html += '<div style="margin-top: 20px; padding: 15px; background: #ede9fe; border-left: 4px solid #8b5cf6; border-radius: 8px;"><strong style="color: #8b5cf6;">Flag Reason:</strong><p style="margin: 5px 0 0 0;">' + data.flag_reason + '</p></div>';
                    }

                    if (data.flag_notes) {
                        html += '<div style="margin-top: 10px; padding: 15px; background: #f3f4f6; border-left: 4px solid #6b7280; border-radius: 8px;"><strong style="color: #6b7280;">Staff Notes:</strong><p style="margin: 5px 0 0 0;">' + data.flag_notes + '</p></div>';
                    }

                    html += '<div style="margin-top: 20px;"><strong>Images:</strong><div class="submission-images">';
                    if (data.image1) {
                        html += '<img src="data:image/jpeg;base64,' + data.image1 + '" onclick="showImageModal(this.src)">';
                    }
                    if (data.image2) {
                        html += '<img src="data:image/jpeg;base64,' + data.image2 + '" onclick="showImageModal(this.src)">';
                    }
                    if (data.image3) {
                        html += '<img src="data:image/jpeg;base64,' + data.image3 + '" onclick="showImageModal(this.src)">';
                    }
                    html += '</div></div>';

                    if (data.has_video) {
                        html += '<div style="margin-top: 20px;"><strong>Video:</strong><br><video controls style="max-width: 100%; max-height: 300px;"><source src="get_image.php?id=' + id + '&field=video" type="video/mp4">Your browser does not support video.</video></div>';
                    }

                    document.getElementById('submissionDetailsContent').innerHTML = html;
                    openModal('submissionDetailsModal');
                });
        }

        function openModal(modalId) {
            document.getElementById(modalId).style.display = 'flex';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        function showImageModal(src) {
            document.getElementById('viewerImage').src = src;
            openModal('imageViewerModal');
        }

        function loadUserChatSession() {
            const messagesContainer = document.getElementById('userChatMessages');
            if (!messagesContainer) return;

            fetch('user_chat.php?action=get_session')
                .then(response => response.json())
                .then(data => {
                    if (!data.success) {
                        messagesContainer.innerHTML = '<p style="text-align:center;color:#666;">No active chat session yet.</p>';
                        return;
                    }

                    messagesContainer.innerHTML = '';
                    data.messages.forEach(msg => {
                        const row = document.createElement('div');
                        row.className = 'chat-message-item ' + (msg.sender_id === data.current_user_id ? 'sent' : 'received');
                        row.innerHTML = '<div class="chat-bubble">' + (msg.message || '') + '</div>';
                        messagesContainer.appendChild(row);
                    });
                    messagesContainer.scrollTop = messagesContainer.scrollHeight;
                });
        }

        function sendUserChatMessage() {
            const input = document.getElementById('userChatInput');
            const messagesContainer = document.getElementById('userChatMessages');
            if (!input || !messagesContainer) return;

            const message = input.value.trim();
            if (!message) return;

            fetch('user_chat.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=send_message&message=' + encodeURIComponent(message)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    input.value = '';
                    loadUserChatSession();
                } else {
                    alert(data.error || 'Unable to send message');
                }
            });
        }

        // Check for hash in URL to open specific tab
        window.addEventListener('load', function() {
            if (window.location.hash === '#submissions') {
                const button = document.querySelector('.tab-btn[onclick*="submissions"]');
                showTab('submissions', button);
            } else if (window.location.hash === '#livechat') {
                const button = document.querySelector('.tab-btn[onclick*="livechat"]');
                showTab('livechat', button);
            }

            loadUserChatSession();
        });
    </script>

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
