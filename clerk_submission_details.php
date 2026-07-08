<?php
session_start();

// Database connection
$mysqli = new mysqli("localhost", "root", "", "fyp");

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

// Check if user is logged in and is a clerk
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in'] || $_SESSION['role'] !== 'clerk') {
    header("Location: Login.php");
    exit();
}

$clerk_id = $_SESSION['user_id'];
$submission_id = intval($_GET['id'] ?? 0);

// Get submission details
$submission = $mysqli->query("
    SELECT ds.*, u.username, u.email, u.phone
    FROM device_submissions ds
    JOIN users u ON ds.user_id = u.id
    WHERE ds.id = $submission_id
")->fetch_assoc();

if (!$submission) {
    die("Submission not found");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submission Details - Fix N Flip</title>
    <link rel="stylesheet" href="clerk.css">
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
        
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 25px;
            flex-wrap: wrap;
        }
        
        .status-badge {
            display: inline-block;
            padding: 8px 20px;
            border-radius: 25px;
            font-size: 13px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-approved {
            background: #d4edda;
            color: #155724;
        }
        
        .status-awaiting_drop_off {
            background: #d4edda;
            color: #155724;
        }
        
        .status-received {
            background: #e7f3ff;
            color: #0eff56;
        }
        
        .status-repair_requested {
            background: #d1ecf1;
            color: #0c5460;
        }

        .status-repairing {
            background: #ecc207ee;
            color: #383d41;
        }
        
        .status-rejected {
            background: #f8d7da;
            color: #721c24;
        }
        
        .status-flagged {
            background: #f5f5f5;
            color: #333;
        }
    </style>
</head>
<body>
    <div class="details-container">
        <a href="clerk.php?section=submissions" class="back-link">&larr; Back to Submissions</a>
        
        <div class="details-card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
                <h2>Submission #<?php echo $submission['id']; ?></h2>
                <span class="status-badge status-<?php echo $submission['status']; ?>">
                    <?php echo ucfirst(str_replace('_', ' ', $submission['status'])); ?>
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
                    <span>RM<?php echo number_format($submission['estimated_price'], 2); ?></span>
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
                    <strong>Submitted By</strong>
                    <span><?php echo htmlspecialchars($submission['username'] ?? 'N/A'); ?></span>
                </div>
                <div class="detail-item">
                    <strong>Email</strong>
                    <span><?php echo htmlspecialchars($submission['email'] ?? 'N/A'); ?></span>
                </div>
                <div class="detail-item">
                    <strong>Phone</strong>
                    <span><?php echo htmlspecialchars($submission['phone'] ?? 'Not provided'); ?></span>
                </div>
            </div>
            
            <?php if ($submission['drop_pin']): ?>
                <div class="pin-display">
                    <?php echo htmlspecialchars($submission['drop_pin']); ?>
                </div>
                <p style="text-align: center; color: #666;">PIN for drop-off</p>
            <?php endif; ?>
            
            <?php if ($submission['rejection_reason']): ?>
                <div style="background: #f8d7da; padding: 15px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #dc3545;">
                    <strong>Rejection Reason:</strong> <?php echo htmlspecialchars($submission['rejection_reason']); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($submission['flag_reason']): ?>
                <div style="background: #fff3cd; padding: 15px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #ffc107;">
                    <strong>Flag Reason:</strong> <?php echo htmlspecialchars($submission['flag_reason']); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($submission['image1'] || $submission['image2'] || $submission['image3']): ?>
                <h3 style="margin-top: 30px;">Images</h3>
                <div class="images-container">
                    <?php if ($submission['image1']): ?>
                        <img src="data:image/jpeg;base64,<?php echo base64_encode($submission['image1']); ?>" onclick="window.open(this.src)">
                    <?php endif; ?>
                    <?php if ($submission['image2']): ?>
                        <img src="data:image/jpeg;base64,<?php echo base64_encode($submission['image2']); ?>" onclick="window.open(this.src)">
                    <?php endif; ?>
                    <?php if ($submission['image3']): ?>
                        <img src="data:image/jpeg;base64,<?php echo base64_encode($submission['image3']); ?>" onclick="window.open(this.src)">
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($submission['has_video']): ?>
                <h3 style="margin-top: 30px;">Video</h3>
                <div style="margin: 20px 0;">
                    <video controls style="max-width: 100%; max-height: 400px; border-radius: 8px;">
                        <source src="get_image.php?id=<?php echo $submission['id']; ?>&field=video" type="video/mp4">
                        Your browser does not support video.
                    </video>
                </div>
            <?php endif; ?>
            
            <div style="margin-top: 30px; padding-top: 20px; border-top: 2px solid #e5e7eb;">
                <p style="color: #666;">Use the submissions page to perform actions on this submission.</p>
            </div>
        </div>
    </div>
</body>
</html>
