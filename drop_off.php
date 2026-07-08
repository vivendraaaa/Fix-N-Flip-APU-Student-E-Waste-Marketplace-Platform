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

// Get all submissions with PIN (show all so users can see their PIN even before approval)
$drop_off_submissions = $mysqli->query("
    SELECT * FROM device_submissions 
    WHERE user_id = $user_id
    ORDER BY id DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Device Drop-Off - Fix N Flip</title>
    <link rel="stylesheet" href="profile.css">
    <style>
        .drop-off-container {
            max-width: 900px;
            margin: 30px auto;
            padding: 20px;
        }
        
        .drop-off-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border: 1px solid #e5e7eb;
        }
        
        .pin-display {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            padding: 25px;
            border-radius: 12px;
            text-align: center;
            font-size: 36px;
            font-weight: bold;
            letter-spacing: 6px;
            margin: 20px 0;
            color: white;
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.4);
        }
        
        .status-badge {
            padding: 8px 20px;
            border-radius: 25px;
            font-size: 13px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-approved {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .status-received {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        
        .device-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin: 20px 0;
        }
        
        .detail-item {
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 4px solid #10b981;
        }
        
        .instructions {
            background: #e7f3ff;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            border-left: 4px solid #007bff;
        }
        
        .instructions ul {
            margin: 10px 0 0 20px;
        }
        
        .instructions li {
            margin-bottom: 8px;
        }
        
        .submission-id {
            text-align: center;
            margin-top: 15px;
            font-size: 18px;
            font-weight: 600;
            color: #333;
        }
        
        .no-devices {
            text-align: center;
            padding: 60px 20px;
            background: #f8f9fa;
            border-radius: 12px;
            color: #6b7280;
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e5e7eb;
        }
        
        .card-header h3 {
            margin: 0;
            color: #333;
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

    <div class="drop-off-container">
        <h2>Device Drop-Off</h2>
        
        <div class="instructions">
            <strong>Instructions:</strong>
            <ul>
                <li>Your PIN is generated instantly when you submit a device</li>
                <li>Bring your device to the drop-off location when approved</li>
                <li>Show the clerk your PIN and Submission ID</li>
                <li>The clerk will search for your submission by PIN</li>
                <li>The clerk will confirm with you and mark your device as received</li>
                <li>Once received, your device will be sent for repair</li>
            </ul>
        </div>
        
        <?php if ($drop_off_submissions && $drop_off_submissions->num_rows > 0): ?>
            <?php while ($sub = $drop_off_submissions->fetch_assoc()): ?>
                <div class="drop-off-card">
                    <div class="card-header">
                        <h3>Submission #<?php echo $sub['id']; ?></h3>
                        <span class="status-badge status-<?php echo $sub['status']; ?>">
                            <?php echo ucfirst(str_replace('_', ' ', $sub['status'])); ?>
                        </span>
                    </div>
                    
                    <div class="device-info">
                        <div><strong>Device:</strong> <?php echo htmlspecialchars($sub['device_type']); ?></div>
                        <div><strong>Brand:</strong> <?php echo htmlspecialchars($sub['brand']); ?></div>
                        <div><strong>Model:</strong> <?php echo htmlspecialchars($sub['model']); ?></div>
                        <div><strong>Est. Price:</strong> RM<?php echo number_format($sub['estimated_price'], 2); ?></div>
                    </div>
                    
                    <div class="pin-display">
                        <?php echo htmlspecialchars($sub['drop_pin'] ?? 'Generating...'); ?>
                    </div>
                    
                    <div class="submission-id">
                        Submission ID: #<?php echo $sub['id']; ?>
                    </div>
                    
                    <?php if ($sub['status'] === 'pending'): ?>
                        <p style="color: #ffc107; font-weight: 500; text-align: center; margin-top: 20px; font-size: 16px;">⏳ Waiting for approval. PIN will be generated when approved.</p>
                    <?php elseif ($sub['status'] === 'approved'): ?>
                        <p style="color: #007bff; font-weight: 500; text-align: center; margin-top: 20px; font-size: 16px;">✓ Approved! Please bring this PIN and Submission ID to the drop-off location.</p>
                    <?php elseif ($sub['status'] === 'received'): ?>
                        <p style="color: #28a745; font-weight: 500; text-align: center; margin-top: 20px; font-size: 16px;">✓ Device has been received and sent for repair.</p>
                    <?php elseif ($sub['status'] === 'repair_requested'): ?>
                        <p style="color: #6c757d; font-weight: 500; text-align: center; margin-top: 20px; font-size: 16px;">🔧 Device is being repaired.</p>
                    <?php elseif ($sub['status'] === 'rejected'): ?>
                        <p style="color: #dc3545; font-weight: 500; text-align: center; margin-top: 20px; font-size: 16px;">✗ This submission was rejected.</p>
                    <?php endif; ?>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="no-devices">
                <h3>No Devices Pending Drop-Off</h3>
                <p>You don't have any approved devices waiting for drop-off.</p>
                <a href="profile.php" style="color: #007bff; text-decoration: none; font-weight: bold; margin-top: 15px; display: inline-block;">Return to Profile &rarr;</a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
