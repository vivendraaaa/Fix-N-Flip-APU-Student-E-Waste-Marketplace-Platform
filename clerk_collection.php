<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database connection
$mysqli = new mysqli("localhost", "root", "", "fyp");

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

// Check if user is logged in and is clerk
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in'] || strtolower($_SESSION['role']) !== 'clerk') {
    header("Location: Login.php");
    exit();
}

$clerk_id = $_SESSION['user_id'];

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'search_pin') {
        $pin = $_POST['pin'] ?? '';
        
        // Search in device_submissions for drop-off
        $drop_off = $mysqli->query("SELECT * FROM device_submissions WHERE drop_pin = '$pin'")->fetch_assoc();
        
        // Check if pickup_pin column exists before searching
        $check_column = $mysqli->query("SHOW COLUMNS FROM technician_listings LIKE 'pickup_pin'");
        $pickup = null;
        if ($check_column && $check_column->num_rows > 0) {
            $pickup = $mysqli->query("SELECT tl.*, u.username as technician_name FROM technician_listings tl JOIN users u ON tl.technician_id = u.id WHERE tl.pickup_pin = '$pin'")->fetch_assoc();
        }
        
        if ($drop_off) {
            header('Location: clerk.php?section=collection&type=drop_off&pin=' . $pin);
            exit;
        } elseif ($pickup) {
            header('Location: clerk.php?section=collection&type=pickup&pin=' . $pin);
            exit;
        } else {
            header('Location: clerk.php?section=collection&msg=not_found');
            exit;
        }
        
    } elseif ($action === 'mark_received') {
        $submission_id = intval($_POST['submission_id']);
        $mysqli->query("UPDATE device_submissions SET status = 'completed' WHERE id = $submission_id");
        header('Location: clerk.php?section=collection&msg=received');
        exit;
        
    } elseif ($action === 'mark_picked_up') {
        $listing_id = intval($_POST['listing_id']);
        $mysqli->query("UPDATE technician_listings SET pickup_status = 'picked_up', status = 'sold' WHERE id = $listing_id");
        header('Location: clerk.php?section=collection&msg=picked_up');
        exit;
    }
}

// Handle GET parameters
$type = $_GET['type'] ?? '';
$pin = $_GET['pin'] ?? '';
$msg = $_GET['msg'] ?? '';

$drop_off_result = null;
$pickup_result = null;

if ($type === 'drop_off' && $pin) {
    $drop_off_result = $mysqli->query("SELECT ds.*, u.username, u.email, u.phone FROM device_submissions ds JOIN users u ON ds.user_id = u.id WHERE ds.drop_pin = '$pin'")->fetch_assoc();
} elseif ($type === 'pickup' && $pin) {
    // Check if pickup_pin column exists before searching
    $check_column = $mysqli->query("SHOW COLUMNS FROM technician_listings LIKE 'pickup_pin'");
    if ($check_column && $check_column->num_rows > 0) {
        $pickup_result = $mysqli->query("SELECT tl.*, u.username as technician_name, u.email as technician_email, u.phone as technician_phone FROM technician_listings tl JOIN users u ON tl.technician_id = u.id WHERE tl.pickup_pin = '$pin'")->fetch_assoc();
    }
}
?>
<style>
.button2 {
    display: inline-block;
    transition: all 0.2s ease-in;
    position: relative;
    overflow: hidden;
    z-index: 1;
    color: #090909;
    padding: 0.7em 1.7em;
    cursor: pointer;
    font-size: 18px;
    border-radius: 0.5em;
    background: #e8e8e8;
    border: 1px solid #e8e8e8;
    box-shadow: 6px 6px 12px #c5c5c5, -6px -6px 12px #ffffff;
    text-decoration: none;
    margin-top: 15px;
}

.button2:active {
    color: #666;
    box-shadow: inset 4px 4px 12px #c5c5c5, inset -4px -4px 12px #ffffff;
}

.button2:before {
    content: "";
    position: absolute;
    left: 50%;
    transform: translateX(-50%) scaleY(1) scaleX(1.25);
    top: 100%;
    width: 140%;
    height: 180%;
    background-color: rgba(0, 0, 0, 0.05);
    border-radius: 50%;
    display: block;
    transition: all 0.5s 0.1s cubic-bezier(0.55, 0, 0.1, 1);
    z-index: -1;
}

.button2:after {
    content: "";
    position: absolute;
    left: 55%;
    transform: translateX(-50%) scaleY(1) scaleX(1.45);
    top: 180%;
    width: 160%;
    height: 190%;
    background-color: #10b981;
    border-radius: 50%;
    display: block;
    transition: all 0.5s 0.1s cubic-bezier(0.55, 0, 0.1, 1);
    z-index: -1;
}

.button2:hover {
    color: #ffffff;
    border: 1px solid #10b981;
}

.button2:hover:before {
    top: -35%;
    background-color: #10b981;
    transform: translateX(-50%) scaleY(1.3) scaleX(0.8);
}

.button2:hover:after {
    top: -45%;
    background-color: #10b981;
    transform: translateX(-50%) scaleY(1.3) scaleX(0.8);
}
</style>

<div class="content-section">
    <h2>Device Collection/Sale</h2>
    
    <?php if ($msg === 'not_found'): ?>
        <div style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
            PIN not found. Please check and try again.
        </div>
    <?php elseif ($msg === 'received'): ?>
        <div style="background: #d4edda; color: #155724; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
            Device marked as received successfully!
        </div>
    <?php elseif ($msg === 'picked_up'): ?>
        <div style="background: #d4edda; color: #155724; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
            Device marked as picked up by customer successfully!
        </div>
    <?php elseif ($msg === 'added_to_repair'): ?>
        <div style="background: #d4edda; color: #155724; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
            Device added to repair requests successfully!
        </div>
    <?php endif; ?>
    
    <!-- PIN Search -->
    <div style="background: #f8f9fa; padding: 25px; border-radius: 12px; margin-bottom: 30px; border: 1px solid #e5e7eb;">
        <h3 style="margin-top: 0;">Search by PIN</h3>
        <p style="color: #666; margin-bottom: 15px;">Enter a PIN to search for device drop-offs or sale pickups.</p>
        <form method="POST" style="display: flex; gap: 10px; align-items: center;">
            <input type="hidden" name="action" value="search_pin">
            <input type="text" name="pin" placeholder="Enter 6-digit PIN" maxlength="6" required style="padding: 12px; border: 1px solid #ddd; border-radius: 8px; width: 200px; font-size: 16px;">
            <button type="submit" class="btn btn-primary" style="padding: 12px 24px;">Search</button>
        </form>
    </div>
    
    <?php if ($drop_off_result): ?>
        <div style="background: #ffffff; padding: 30px; border-radius: 12px; margin-bottom: 20px; border-left: 4px solid #10b981;">
            <h3 style="margin-top: 0; color: #10b981;">Device Drop-Off Found</h3>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin: 20px 0;">
                <div style="padding: 12px; background: white; border-radius: 8px;">
                    <strong>PIN:</strong> <?php echo htmlspecialchars($drop_off_result['drop_pin']); ?>
                </div>
                <div style="padding: 12px; background: white; border-radius: 8px;">
                    <strong>Submission ID:</strong> #<?php echo $drop_off_result['id']; ?>
                </div>
                <div style="padding: 12px; background: white; border-radius: 8px;">
                    <strong>Device:</strong> <?php echo htmlspecialchars($drop_off_result['device_type']); ?>
                </div>
                <div style="padding: 12px; background: white; border-radius: 8px;">
                    <strong>Brand:</strong> <?php echo htmlspecialchars($drop_off_result['brand']); ?>
                </div>
                <div style="padding: 12px; background: white; border-radius: 8px;">
                    <strong>Model:</strong> <?php echo htmlspecialchars($drop_off_result['model']); ?>
                </div>
                <div style="padding: 12px; background: white; border-radius: 8px;">
                    <strong>Status:</strong> <?php echo $drop_off_result['status'] ? ucfirst(str_replace('_', ' ', $drop_off_result['status'])) : 'N/A'; ?>
                </div>
                <div style="padding: 12px; background: white; border-radius: 8px;">
                    <strong>Est. Price:</strong> RM<?php echo number_format($drop_off_result['estimated_price'], 2); ?>
                </div>
                <div style="padding: 12px; background: white; border-radius: 8px;">
                    <strong>Submitted By:</strong> <?php echo htmlspecialchars($drop_off_result['username']); ?>
                </div>
                <div style="padding: 12px; background: white; border-radius: 8px;">
                    <strong>Email:</strong> <?php echo htmlspecialchars($drop_off_result['email']); ?>
                </div>
                <div style="padding: 12px; background: white; border-radius: 8px;">
                    <strong>Phone:</strong> <?php echo htmlspecialchars($drop_off_result['phone'] ?? 'Not provided'); ?>
                </div>
            </div>
            <?php if ($drop_off_result['status'] === 'approved' || $drop_off_result['status'] === '' || $drop_off_result['status'] === null): ?>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="mark_received">
                    <input type="hidden" name="submission_id" value="<?php echo $drop_off_result['id']; ?>">
                    <button type="submit" class="button2">Mark as Received</button>
                </form>
            <?php elseif ($drop_off_result['status'] === 'completed' || $drop_off_result['status'] === 'received'): ?>
                <p style="color: #28a745; font-weight: bold; font-size: 16px;">✓ Device marked as Completed</p>
                <form method="POST" action="clerk_submissions.php" style="display: inline; margin-left: 15px;">
                    <input type="hidden" name="action" value="mark_for_repair">
                    <input type="hidden" name="submission_id" value="<?php echo $drop_off_result['id']; ?>">
                    <button type="submit" class="button2">Add to Repair</button>
                </form>
            <?php else: ?>
                <p style="color: #666; font-size: 14px;">Status: <?php echo ucfirst(str_replace('_', ' ', $drop_off_result['status'])); ?></p>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    
    <?php if ($pickup_result): ?>
        <div style="background: #fff3cd; padding: 30px; border-radius: 12px; margin-bottom: 20px; border-left: 4px solid #ffc107;">
            <h3 style="margin-top: 0; color: #856404;">Sale Pickup Found</h3>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin: 20px 0;">
                <div style="padding: 12px; background: white; border-radius: 8px;">
                    <strong>Pickup PIN:</strong> <?php echo htmlspecialchars($pickup_result['pickup_pin']); ?>
                </div>
                <div style="padding: 12px; background: white; border-radius: 8px;">
                    <strong>Listing ID:</strong> #<?php echo $pickup_result['id']; ?>
                </div>
                <div style="padding: 12px; background: white; border-radius: 8px;">
                    <strong>Product:</strong> <?php echo htmlspecialchars($pickup_result['name']); ?>
                </div>
                <div style="padding: 12px; background: white; border-radius: 8px;">
                    <strong>Brand:</strong> <?php echo htmlspecialchars($pickup_result['brand']); ?>
                </div>
                <div style="padding: 12px; background: white; border-radius: 8px;">
                    <strong>Category:</strong> <?php echo htmlspecialchars($pickup_result['category']); ?>
                </div>
                <div style="padding: 12px; background: white; border-radius: 8px;">
                    <strong>Condition:</strong> <?php echo htmlspecialchars($pickup_result['condition']); ?>
                </div>
                <div style="padding: 12px; background: white; border-radius: 8px;">
                    <strong>Price:</strong> RM<?php echo number_format($pickup_result['price'], 2); ?>
                </div>
                <div style="padding: 12px; background: white; border-radius: 8px;">
                    <strong>Technician:</strong> <?php echo htmlspecialchars($pickup_result['technician_name']); ?>
                </div>
                <div style="padding: 12px; background: white; border-radius: 8px;">
                    <strong>Pickup Status:</strong> <?php echo ucfirst($pickup_result['pickup_status']); ?>
                </div>
            </div>
            <?php if ($pickup_result['pickup_status'] === 'pending'): ?>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="mark_picked_up">
                    <input type="hidden" name="listing_id" value="<?php echo $pickup_result['id']; ?>">
                    <button type="submit" class="button2">Mark as Picked Up by Customer</button>
                </form>
            <?php elseif ($pickup_result['pickup_status'] === 'picked_up'): ?>
                <p style="color: #28a745; font-weight: bold; font-size: 16px;">✓ Device already picked up by customer</p>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
