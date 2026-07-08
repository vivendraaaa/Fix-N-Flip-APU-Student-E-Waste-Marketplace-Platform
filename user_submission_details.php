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

function ensure_price_change_columns($mysqli) {
    static $checked = false;
    if ($checked) {
        return;
    }

    $result = $mysqli->query("SHOW COLUMNS FROM device_submissions");
    $columns = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $columns[] = $row['Field'];
        }
    }

    $changes = [
        'price_change_requested' => "ALTER TABLE device_submissions ADD COLUMN price_change_requested TINYINT(1) NOT NULL DEFAULT 0",
        'price_change_new_price' => "ALTER TABLE device_submissions ADD COLUMN price_change_new_price DECIMAL(10,2) NULL",
        'price_change_action' => "ALTER TABLE device_submissions ADD COLUMN price_change_action VARCHAR(20) NULL",
        'price_change_appeal_reason' => "ALTER TABLE device_submissions ADD COLUMN price_change_appeal_reason TEXT NULL"
    ];

    foreach ($changes as $column => $sql) {
        if (!in_array($column, $columns, true)) {
            $mysqli->query($sql);
        }
    }

    $checked = true;
}

$user_id = $_SESSION['user_id'];
$submission_id = intval($_GET['id'] ?? 0);
$price_change_message = '';
$price_change_message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['price_change_action'])) {
    ensure_price_change_columns($mysqli);

    $submission_id = intval($_POST['submission_id'] ?? 0);
    $action = $_POST['price_change_action'];
    $appeal_reason = trim($_POST['price_change_appeal_reason'] ?? '');

    if ($submission_id > 0) {
        $check_stmt = $mysqli->prepare("SELECT id FROM device_submissions WHERE id = ? AND user_id = ?");
        $check_stmt->bind_param("ii", $submission_id, $user_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows > 0) {
            $current_submission = $mysqli->query("SELECT price_change_action, price_change_requested FROM device_submissions WHERE id = $submission_id")->fetch_assoc();
            $is_second_offer = ($current_submission['price_change_action'] ?? '') === 'counter_offer';

            if ($action === 'accept_final') {
                $stmt = $mysqli->prepare("UPDATE device_submissions SET status = 'pending', price_change_requested = 0, price_change_action = 'approved', price_change_appeal_reason = NULL, estimated_price = COALESCE(price_change_new_price, estimated_price) WHERE id = ? AND user_id = ?");
                $stmt->bind_param("ii", $submission_id, $user_id);
                $stmt->execute();
                $price_change_message = 'You accepted the final price. The clerk will need to review and confirm it.';
                $price_change_message_type = 'success';
            } elseif ($action === 'decline_final') {
                $stmt = $mysqli->prepare("UPDATE device_submissions SET status = 'closed', price_change_requested = 0, price_change_action = 'declined', price_change_appeal_reason = NULL WHERE id = ? AND user_id = ?");
                $stmt->bind_param("ii", $submission_id, $user_id);
                $stmt->execute();
                $price_change_message = 'You declined the final price and the submission has been closed.';
                $price_change_message_type = 'success';
            } elseif ($is_second_offer && $action === 'approve') {
                $stmt = $mysqli->prepare("UPDATE device_submissions SET status = 'approved', price_change_requested = 0, price_change_action = 'approved', price_change_appeal_reason = NULL, estimated_price = COALESCE(price_change_new_price, estimated_price) WHERE id = ? AND user_id = ?");
                $stmt->bind_param("ii", $submission_id, $user_id);
                $stmt->execute();
                $price_change_message = 'You approved the final price.';
                $price_change_message_type = 'success';
            } elseif ($is_second_offer && $action === 'reject') {
                $stmt = $mysqli->prepare("UPDATE device_submissions SET status = 'closed', price_change_requested = 0, price_change_action = 'declined', price_change_appeal_reason = NULL WHERE id = ? AND user_id = ?");
                $stmt->bind_param("ii", $submission_id, $user_id);
                $stmt->execute();
                $price_change_message = 'You declined the final price and the submission has been closed.';
                $price_change_message_type = 'success';
            } elseif (!$is_second_offer && $action === 'approve') {
                $stmt = $mysqli->prepare("UPDATE device_submissions SET status = 'pending', price_change_requested = 0, price_change_action = 'approved', price_change_appeal_reason = NULL, estimated_price = COALESCE(price_change_new_price, estimated_price) WHERE id = ? AND user_id = ?");
                $stmt->bind_param("ii", $submission_id, $user_id);
                $stmt->execute();
                $price_change_message = 'You approved the revised price. The clerk will need to review and confirm it.';
                $price_change_message_type = 'success';
            } elseif (!$is_second_offer && $action === 'reject') {
                $stmt = $mysqli->prepare("UPDATE device_submissions SET price_change_requested = 0, price_change_action = 'rejected', price_change_appeal_reason = NULL WHERE id = ? AND user_id = ?");
                $stmt->bind_param("ii", $submission_id, $user_id);
                $stmt->execute();
                $price_change_message = 'You rejected the revised price.';
                $price_change_message_type = 'success';
            } elseif (!$is_second_offer && $action === 'appeal') {
                if ($appeal_reason === '') {
                    $price_change_message = 'Please add a reason for your appeal.';
                    $price_change_message_type = 'error';
                } else {
                    $stmt = $mysqli->prepare("UPDATE device_submissions SET price_change_requested = 1, price_change_action = 'appealed', price_change_appeal_reason = ? WHERE id = ? AND user_id = ?");
                    $stmt->bind_param("sii", $appeal_reason, $submission_id, $user_id);
                    $stmt->execute();
                    $price_change_message = 'Your appeal has been submitted.';
                    $price_change_message_type = 'success';
                }
            }
        }
    }
}

// Get submission details
$submission = $mysqli->query("
    SELECT ds.*, u.username, u.email, u.phone
    FROM device_submissions ds
    JOIN users u ON ds.user_id = u.id
    WHERE ds.id = $submission_id AND ds.user_id = $user_id
")->fetch_assoc();

if (!$submission) {
    die("Submission not found");
}

$show_price_change_controls = (
    !empty($submission['price_change_requested'])
    || !empty($submission['price_change_new_price'])
    || !empty($submission['price_change_action'])
    || in_array($submission['status'] ?? '', ['pending', 'flagged', 'under_review'], true)
) && !in_array($submission['price_change_action'] ?? '', ['approved', 'declined', 'closed', 'rejected', 'appealed'], true)
    && !in_array($submission['status'] ?? '', ['approved', 'cancelled', 'closed', 'completed', 'received', 'repair_requested', 'rejected'], true);

$show_final_price_controls = ($submission['price_change_action'] ?? '') === 'counter_offer';

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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submission Details - Fix N Flip</title>
    <link rel="stylesheet" href="profile.css">
    <style>
        .details-container {
            max-width: 900px;
            margin: 30px auto;
            padding: 20px;
        }
        
        .details-card {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border: 1px solid #e5e7eb;
        }
        
        .details-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin: 25px 0;
        }
        
        .detail-item {
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 4px solid #10b981;
        }
        
        .detail-item strong {
            display: block;
            margin-bottom: 5px;
            color: #333;
            font-size: 14px;
        }
        
        .detail-item span {
            color: #555;
            font-size: 16px;
        }
        
        .pin-display {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            padding: 30px;
            border-radius: 12px;
            text-align: center;
            font-size: 48px;
            font-weight: bold;
            letter-spacing: 8px;
            margin: 25px 0;
            color: white;
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.4);
        }
        
        .images-container {
            display: flex;
            gap: 15px;
            margin: 25px 0;
            flex-wrap: wrap;
        }
        
        .images-container img {
            width: 150px;
            height: 150px;
            object-fit: cover;
            border-radius: 8px;
            cursor: pointer;
            border: 2px solid #e5e7eb;
        }
        
        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #10b981;
            text-decoration: none;
            font-weight: 500;
        }
        
        .back-link:hover {
            text-decoration: underline;
        }
        
        .status-badge {
            display: inline-block;
            padding: 8px 20px;
            border-radius: 25px;
            font-size: 13px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        <?php
            $status_colors = [
                'pending' => 'background: #fff3cd; color: #856404;',
                'approved' => 'background: #d4edda; color: #155724;',
                'awaiting_drop_off' => 'background: #d4edda; color: #155724;',
                'received' => 'background: #e7f3ff; color: #004085;',
                'repair_requested' => 'background: #d1ecf1; color: #0c5460;',
                'completed' => 'background: #d4edda; color: #155724;',
                'rejected' => 'background: #fee2e2; color: #dc2626;',
                'flagged' => 'background: #fce7f3; color: #db2777;'
            ];
        ?>
        
        <?php foreach ($status_colors as $status => $color): ?>
            .status-<?php echo $status; ?> {
                <?php echo $color; ?>
            }
        <?php endforeach; ?>
        
        .no-pin {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            color: #6c757d;
            font-size: 16px;
            margin: 25px 0;
        }

        .price-change-card {
            background: #f8fafc;
            border: 1px solid #dbeafe;
            border-radius: 10px;
            padding: 20px;
            margin: 25px 0;
        }

        .price-action-row {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin: 15px 0;
        }

        .price-action-btn {
            border: none;
            border-radius: 8px;
            padding: 10px 16px;
            font-weight: 600;
            cursor: pointer;
            color: white;
        }

        .price-action-btn.approve {
            background: #10b981;
        }

        .price-action-btn.reject {
            background: #ef4444;
        }

        .price-action-btn.appeal {
            background: #f59e0b;
        }

        .price-change-form textarea {
            width: 100%;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            padding: 10px;
            resize: vertical;
            min-height: 90px;
            box-sizing: border-box;
        }

        .alert {
            padding: 12px 14px;
            border-radius: 8px;
            margin-bottom: 12px;
            font-size: 14px;
        }

        .alert.success {
            background: #dcfce7;
            color: #166534;
        }

        .alert.error {
            background: #fee2e2;
            color: #b91c1c;
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
                <?php if ($notification_count > 0): ?>
                    <span class="cart-badge"><?php echo $notification_count; ?></span>
                <?php endif; ?>
            </a></li>
            <li>
                <a href="profile.php">
                    <img src="<?php echo !empty($user['pfp']) ? htmlspecialchars($user['pfp']) : 'https://placehold.co/40x40?text=User'; ?>" alt="Profile" class="profile-pic">
                    <?php echo htmlspecialchars($user['username']); ?>
                </a>
            </li>
        </ul>
    </nav>

    <div class="details-container">
        <a href="profile.php" class="back-link">&larr; Back to Profile</a>
        
        <div class="details-card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
                <h2>Submission #<?php echo $submission['id']; ?></h2>
                <?php
                // If device has been picked up (received or later), show as completed
                $display_status = $submission['status'];
                if (in_array($submission['status'], ['received', 'repair_requested', 'repairing', 'completed', 'disposed'])) {
                    $display_status = 'completed';
                }
                ?>
                <span class="status-badge status-<?php echo $display_status; ?>">
                    <?php echo ucfirst(str_replace('_', ' ', $display_status)); ?>
                </span>
            </div>
            
            <div class="details-grid">
                <div class="detail-item">
                    <strong>Device Type</strong>
                    <span><?php echo htmlspecialchars($submission['device_type'] ?? 'N/A'); ?></span>
                </div>
                <div class="detail-item">
                    <strong>Brand</strong>
                    <span><?php echo htmlspecialchars($submission['brand'] ?? 'N/A'); ?></span>
                </div>
                <div class="detail-item">
                    <strong>Model</strong>
                    <span><?php echo htmlspecialchars($submission['model'] ?? 'N/A'); ?></span>
                </div>
                <div class="detail-item">
                    <strong>Estimated Price</strong>
                    <span>RM<?php echo number_format($submission['estimated_price'] ?? 0, 2); ?></span>
                </div>
                <div class="detail-item">
                    <strong>Condition</strong>
                    <span><?php echo htmlspecialchars($submission['device_condition'] ?? 'Not specified'); ?></span>
                </div>
                <div class="detail-item">
                    <strong>Specs</strong>
                    <span><?php echo htmlspecialchars($submission['specs'] ?? 'Not specified'); ?></span>
                </div>
                <div class="detail-item">
                    <strong>Accessories</strong>
                    <span><?php echo htmlspecialchars($submission['accessories'] ?? 'None'); ?></span>
                </div>
                <div class="detail-item">
                    <strong>Submitted</strong>
                    <span><?php echo isset($submission['submitted_at']) ? date('M d, Y', strtotime($submission['submitted_at'])) : 'N/A'; ?></span>
                </div>
            </div>
            
            <?php if ($show_price_change_controls): ?>
                <div class="price-change-card">
                    <h3><?php echo $show_final_price_controls ? 'Final Price Decision' : 'Price Change Request'; ?></h3>
                    <?php if (!empty($submission['price_change_new_price'])): ?>
                        <p><?php echo $show_final_price_controls ? 'The clerk has offered a final price of' : 'The clerk requested a revised price of'; ?> <strong>RM<?php echo number_format($submission['price_change_new_price'], 2); ?></strong>.</p>
                    <?php else: ?>
                        <p><?php echo $show_final_price_controls ? 'This is your final chance to respond to the offered price.' : 'Choose how you want to respond to the requested price change.'; ?></p>
                    <?php endif; ?>
                    <?php if ($price_change_message): ?>
                        <div class="alert <?php echo htmlspecialchars($price_change_message_type); ?>"><?php echo htmlspecialchars($price_change_message); ?></div>
                    <?php endif; ?>
                    <form method="post" class="price-change-form">
                        <input type="hidden" name="submission_id" value="<?php echo (int)$submission['id']; ?>">
                        <div class="price-action-row">
                            <?php if ($show_final_price_controls): ?>
                                <button class="price-action-btn approve" type="submit" name="price_change_action" value="accept_final">Accept Final Price</button>
                                <button class="price-action-btn reject" type="submit" name="price_change_action" value="decline_final">Decline & Close</button>
                            <?php else: ?>
                                <button class="price-action-btn approve" type="submit" name="price_change_action" value="approve">Approve</button>
                                <button class="price-action-btn reject" type="submit" name="price_change_action" value="reject">Reject</button>
                                <button class="price-action-btn appeal" type="submit" name="price_change_action" value="appeal">Appeal</button>
                            <?php endif; ?>
                        </div>
                        <?php if (!$show_final_price_controls): ?>
                            <label for="price_change_appeal_reason" style="display:block; margin-bottom:6px; font-weight:600;">Appeal reason</label>
                            <textarea id="price_change_appeal_reason" name="price_change_appeal_reason" placeholder="Tell us why you want to appeal this price change."></textarea>
                        <?php endif; ?>
                    </form>
                </div>
            <?php endif; ?>

            <?php
            // If device has been picked up (received or later), show COMPLETED instead of PIN
            if (in_array($submission['status'], ['received', 'repair_requested', 'repairing', 'completed', 'disposed'])) {
            ?>
                <div class="pin-display" style="background: #d4edda; color: #155724;">
                    COMPLETED
                </div>
                <p style="text-align: center; color: #666;">Your device has been picked up</p>
            <?php
            } elseif (isset($submission['drop_pin']) && $submission['drop_pin']) {
            ?>
                <div class="pin-display">
                    <?php echo htmlspecialchars($submission['drop_pin']); ?>
                </div>
                <p style="text-align: center; color: #666;">Your PIN for drop-off</p>
            <?php
            } else {
            ?>
                <div class="no-pin">
                    PIN will be generated when your submission is approved
                </div>
            <?php
            }
            ?>
            
            <?php if (isset($submission['rejection_reason']) && $submission['rejection_reason']): ?>
                <div style="background: #f8d7da; padding: 15px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #dc3545;">
                    <strong>Rejection Reason:</strong> <?php echo htmlspecialchars($submission['rejection_reason']); ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($submission['flag_reason']) && $submission['flag_reason']): ?>
                <div style="background: #fff3cd; padding: 15px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #ffc107;">
                    <strong>Flag Reason:</strong> <?php echo htmlspecialchars($submission['flag_reason']); ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($submission['image1']) || isset($submission['image2']) || isset($submission['image3'])): ?>
                <h3 style="margin-top: 30px;">Images</h3>
                <div class="images-container">
                    <?php if (isset($submission['image1']) && $submission['image1']): ?>
                        <img src="data:image/jpeg;base64,<?php echo base64_encode($submission['image1']); ?>" onclick="window.open(this.src)">
                    <?php endif; ?>
                    <?php if (isset($submission['image2']) && $submission['image2']): ?>
                        <img src="data:image/jpeg;base64,<?php echo base64_encode($submission['image2']); ?>" onclick="window.open(this.src)">
                    <?php endif; ?>
                    <?php if (isset($submission['image3']) && $submission['image3']): ?>
                        <img src="data:image/jpeg;base64,<?php echo base64_encode($submission['image3']); ?>" onclick="window.open(this.src)">
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($submission['has_video']) && $submission['has_video']): ?>
                <h3 style="margin-top: 30px;">Video</h3>
                <div style="margin: 20px 0;">
                    <video controls style="max-width: 100%; max-height: 400px; border-radius: 8px;">
                        <source src="get_image.php?id=<?php echo $submission['id']; ?>&field=video" type="video/mp4">
                        Your browser does not support video.
                    </video>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
