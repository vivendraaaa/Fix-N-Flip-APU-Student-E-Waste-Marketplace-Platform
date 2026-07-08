<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
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
        'price_change_appeal_reason' => "ALTER TABLE device_submissions ADD COLUMN price_change_appeal_reason TEXT NULL",
        'return_requested' => "ALTER TABLE device_submissions ADD COLUMN return_requested TINYINT(1) NOT NULL DEFAULT 0"
    ];

    foreach ($changes as $column => $sql) {
        if (!in_array($column, $columns, true)) {
            $mysqli->query($sql);
        }
    }

    $checked = true;
}

// Handle API endpoint first - before any other code
if (isset($_GET['get_details']) && $_GET['get_details'] == '1') {
    error_reporting(0);
    ini_set('display_errors', 0);
    header('Content-Type: application/json');

    $mysqli = new mysqli("localhost", "root", "", "fyp");
    if ($mysqli->connect_error) {
        echo json_encode(['error' => 'Connection failed']);
        exit;
    }

    ensure_price_change_columns($mysqli);

    $view_id = intval($_GET['id'] ?? 0);

    // Check which columns exist
    $columns_result = $mysqli->query("SHOW COLUMNS FROM device_submissions");
    $columns = [];
    while ($col = $columns_result->fetch_assoc()) {
        $columns[] = $col['Field'];
    }

    // Build query with only existing columns
    $select_columns = ['ds.id', 'ds.user_id', 'ds.device_type', 'ds.brand', 'ds.model', 'ds.storage', 'ds.ram', 'ds.battery_health', 'ds.device_condition', 'ds.accessories', 'ds.description', 'ds.estimated_price', 'ds.status', 'ds.submitted_at', 'ds.location', 'ds.drop_pin', 'u.username', 'u.email', 'u.phone'];

    if (in_array('reject_reason', $columns)) $select_columns[] = 'ds.reject_reason';
    if (in_array('flag_reason', $columns)) $select_columns[] = 'ds.flag_reason';
    if (in_array('flag_notes', $columns)) $select_columns[] = 'ds.flag_notes';
    if (in_array('price_change_requested', $columns)) $select_columns[] = 'ds.price_change_requested';
    if (in_array('price_change_new_price', $columns)) $select_columns[] = 'ds.price_change_new_price';
    if (in_array('price_change_action', $columns)) $select_columns[] = 'ds.price_change_action';
    if (in_array('price_change_appeal_reason', $columns)) $select_columns[] = 'ds.price_change_appeal_reason';
    if (in_array('return_requested', $columns)) $select_columns[] = 'ds.return_requested';

    $query = "SELECT " . implode(', ', $select_columns) . " FROM device_submissions ds JOIN users u ON ds.user_id = u.id WHERE ds.id = $view_id";
    $submission = $mysqli->query($query)->fetch_assoc();

    if ($submission) {
        // Check if video exists
        $has_video = false;
        if (in_array('video', $columns)) {
            $video_check = $mysqli->query("SELECT video FROM device_submissions WHERE id = $view_id")->fetch_assoc();
            if ($video_check && !empty($video_check['video'])) {
                $has_video = true;
            }
        }
        
        // Check which images exist
        $has_image1 = false;
        $has_image2 = false;
        $has_image3 = false;
        
        if (in_array('image1', $columns)) {
            $img_check = $mysqli->query("SELECT image1 FROM device_submissions WHERE id = $view_id")->fetch_assoc();
            if ($img_check && !empty($img_check['image1'])) {
                $has_image1 = true;
            }
        }
        if (in_array('image2', $columns)) {
            $img_check = $mysqli->query("SELECT image2 FROM device_submissions WHERE id = $view_id")->fetch_assoc();
            if ($img_check && !empty($img_check['image2'])) {
                $has_image2 = true;
            }
        }
        if (in_array('image3', $columns)) {
            $img_check = $mysqli->query("SELECT image3 FROM device_submissions WHERE id = $view_id")->fetch_assoc();
            if ($img_check && !empty($img_check['image3'])) {
                $has_image3 = true;
            }
        }
        
        // Don't include binary data in JSON - use get_image.php instead
        unset($submission['image1']);
        unset($submission['image2']);
        unset($submission['image3']);
        unset($submission['video']);
        $submission['has_video'] = $has_video;
        $submission['has_image1'] = $has_image1;
        $submission['has_image2'] = $has_image2;
        $submission['has_image3'] = $has_image3;
        echo json_encode($submission);
    } else {
        echo json_encode(['error' => 'Submission not found']);
    }
    exit;
}

$mysqli = new mysqli("localhost", "root", "", "fyp");

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

ensure_price_change_columns($mysqli);

$clerk_id = $_SESSION['user_id'];

// Fetch drop-off points
$drop_off_points = $mysqli->query("SELECT * FROM drop_off_points ORDER BY name");

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'approve') {
        $submission_id = intval($_POST['submission_id']);
        $final_price = isset($_POST['final_price']) && $_POST['final_price'] !== '' ? floatval($_POST['final_price']) : null;
        $drop_off_point = intval($_POST['drop_off_point']);

        if ($final_price === null) {
            $current_price_result = $mysqli->query("SELECT estimated_price FROM device_submissions WHERE id = $submission_id");
            $final_price = floatval($current_price_result->fetch_assoc()['estimated_price']);
        }

        // Generate QR code
        $qr_code = 'FIXNFLIP-' . strtoupper(uniqid());

        // Generate PIN for drop-off
        $drop_pin = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);

        // Update submission with approved status, price, drop-off point, QR code, and PIN
        $mysqli->query("UPDATE device_submissions SET status = 'approved', estimated_price = $final_price, drop_off_point_id = $drop_off_point, qr_code = '$qr_code', drop_pin = '$drop_pin' WHERE id = $submission_id");

        // Get submission details for notification
        $submission = $mysqli->query("SELECT * FROM device_submissions WHERE id = $submission_id")->fetch_assoc();
        $user_id = $submission['user_id'];
        $device_type = $submission['device_type'];
        $brand = $submission['brand'];
        $model = $submission['model'];

        // Get drop-off point name
        $point_result = $mysqli->query("SELECT name FROM drop_off_points WHERE id = $drop_off_point");
        $point_name = $point_result ? $point_result->fetch_assoc()['name'] : 'Unknown';

        // Add notification to user
        $notification_message = "Your device submission #$submission_id ($device_type - $brand $model) has been approved! Final Price: RM$final_price. Please bring your device to: $point_name. Your PIN: $drop_pin";
        $mysqli->query("INSERT INTO notifications (user_id, message, type, created_at) VALUES ($user_id, '$notification_message', 'approval', NOW())");

        header('Location: clerk.php?section=submissions');
        exit;
        
    } elseif ($action === 'mark_received') {
        $submission_id = intval($_POST['submission_id']);
        $mysqli->query("UPDATE device_submissions SET status = 'received' WHERE id = $submission_id");
        header('Location: clerk.php?section=submissions');
        exit;
        
    } elseif ($action === 'search_by_pin') {
        $pin = $mysqli->real_escape_string($_POST['pin'] ?? '');
        $submission = $mysqli->query("SELECT * FROM device_submissions WHERE drop_pin = '$pin'")->fetch_assoc();
        
        if ($submission) {
            header('Location: clerk.php?section=submissions&found_pin=' . urlencode($pin));
            exit;
        } else {
            header('Location: clerk.php?section=submissions&msg=pin_not_found');
            exit;
        }
        
    } elseif ($action === 'mark_for_repair') {
        $submission_id = intval($_POST['submission_id']);

        // Get submission details
        $submission = $mysqli->query("SELECT * FROM device_submissions WHERE id = $submission_id")->fetch_assoc();
        $user_id = $submission['user_id'];

        // Generate pickup code
        $pickup_code = strtoupper(substr(md5(uniqid()), 0, 8));

        // Get a random technician to assign to
        $technician = $mysqli->query("SELECT id FROM users WHERE role = 'technician' ORDER BY RAND() LIMIT 1")->fetch_assoc();
        $technician_id = $technician ? $technician['id'] : 0;

        // Create repair request (assign to a random technician)
        $mysqli->query("INSERT INTO repair_requests (submission_id, technician_id, status, pickup_code) VALUES ($submission_id, $technician_id, 'pending', '$pickup_code')");

        // Update submission status
        $mysqli->query("UPDATE device_submissions SET status = 'repair_requested' WHERE id = $submission_id");

        // Notify technician (not user)
        if ($technician_id) {
            $notification_message = "New repair request assigned. Device #$submission_id. Pickup code: $pickup_code";
            $mysqli->query("INSERT INTO notifications (user_id, message, type, created_at) VALUES ($technician_id, '$notification_message', 'repair', NOW())");
        }

        // Check if coming from collection page
        if (isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], 'section=collection') !== false) {
            header('Location: clerk.php?section=collection&msg=added_to_repair');
        } else {
            header('Location: clerk.php?section=submissions');
        }
        exit;
        
    } elseif ($action === 'reject') {
        $submission_id = intval($_POST['submission_id']);
        $reject_reason = $_POST['reject_reason'] ?? '';

        // Get submission details
        $submission = $mysqli->query("SELECT * FROM device_submissions WHERE id = $submission_id")->fetch_assoc();
        $user_id = $submission['user_id'];
        $device_type = $submission['device_type'];
        $brand = $submission['brand'];
        $model = $submission['model'];
        $current_status = $submission['status'];

        // Update status
        $stmt = $mysqli->prepare("UPDATE device_submissions SET status = 'rejected', reject_reason = ? WHERE id = ?");
        $stmt->bind_param("si", $reject_reason, $submission_id);
        $stmt->execute();

        // Only notify user if device hasn't been picked up yet (not received or repair_requested)
        if ($current_status !== 'received' && $current_status !== 'repair_requested') {
            $notification_message = "Your device submission #$submission_id ($device_type - $brand $model) has been rejected. Reason: $reject_reason";
            $mysqli->query("INSERT INTO notifications (user_id, message, type, created_at) VALUES ($user_id, '$notification_message', 'rejection', NOW())");
        }

        header('Location: clerk.php?section=submissions');
        exit;
        
    } elseif ($action === 'flag') {
        $submission_id = intval($_POST['submission_id']);
        $reason = $_POST['flag_reason'] ?? '';
        $notes = $_POST['flag_notes'] ?? '';

        // Get submission details for notification
        $submission = $mysqli->query("SELECT * FROM device_submissions WHERE id = $submission_id")->fetch_assoc();
        $user_id = $submission['user_id'];
        $device_type = $submission['device_type'];
        $brand = $submission['brand'];
        $model = $submission['model'];
        $current_status = $submission['status'];

        // Update status
        $stmt = $mysqli->prepare("UPDATE device_submissions SET status = 'flagged', flag_reason = ?, flag_notes = ? WHERE id = ?");
        $stmt->bind_param("ssi", $reason, $notes, $submission_id);
        $stmt->execute();

        // Only notify user if device hasn't been picked up yet (not received or repair_requested)
        if ($current_status !== 'received' && $current_status !== 'repair_requested') {
            $notification_message = "Your device submission #$submission_id ($device_type - $brand $model) has been flagged. Reason: $reason. Notes: $notes";
            $mysqli->query("INSERT INTO notifications (user_id, message, type, created_at) VALUES ($user_id, '$notification_message', 'flag', NOW())");
        }

        header('Location: clerk.php?section=submissions');
        exit;
        
    } elseif ($action === 'edit_price') {
        $submission_id = intval($_POST['submission_id']);
        $new_price = floatval($_POST['new_price'] ?? 0);
        $reason = $_POST['price_change_reason'] ?? '';

        // Get submission details for notification
        $submission = $mysqli->query("SELECT * FROM device_submissions WHERE id = $submission_id")->fetch_assoc();
        $user_id = $submission['user_id'];
        $device_type = $submission['device_type'];
        $brand = $submission['brand'];
        $model = $submission['model'];
        $old_price = $submission['estimated_price'];
        $current_status = $submission['status'];

        // Update price
        $stmt = $mysqli->prepare("UPDATE device_submissions SET estimated_price = ?, price_change_requested = 0, price_change_action = 're_evaluated', price_change_appeal_reason = NULL WHERE id = ?");
        $stmt->bind_param("di", $new_price, $submission_id);
        $stmt->execute();

        // Only notify user if device hasn't been picked up yet (not received or repair_requested)
        if ($current_status !== 'received' && $current_status !== 'repair_requested') {
            $notification_message = "The estimated price for your device submission #$submission_id ($device_type - $brand $model) has been updated from RM$old_price to RM$new_price. Reason: $reason";
            $mysqli->query("INSERT INTO notifications (user_id, message, type, created_at) VALUES ($user_id, '$notification_message', 'price_change', NOW())");
        }

        header('Location: clerk.php?section=submissions');
        exit;

    } elseif ($action === 're_evaluate_price') {
        $submission_id = intval($_POST['submission_id']);
        $new_price = floatval($_POST['new_price'] ?? 0);
        $reason = $_POST['price_change_reason'] ?? '';

        $submission = $mysqli->query("SELECT * FROM device_submissions WHERE id = $submission_id")->fetch_assoc();
        $user_id = $submission['user_id'];
        $device_type = $submission['device_type'];
        $brand = $submission['brand'];
        $model = $submission['model'];
        $current_status = $submission['status'];

        $stmt = $mysqli->prepare("UPDATE device_submissions SET estimated_price = ?, status = 'pending', price_change_requested = 1, price_change_new_price = ?, price_change_action = 'counter_offer', price_change_appeal_reason = NULL WHERE id = ?");
        $stmt->bind_param("ddi", $new_price, $new_price, $submission_id);
        $stmt->execute();

        if ($current_status !== 'received' && $current_status !== 'repair_requested') {
            $notification_message = "The clerk has made a final counter-offer of RM$new_price for your device submission #$submission_id ($device_type - $brand $model). Reason: $reason";
            $mysqli->query("INSERT INTO notifications (user_id, message, type, created_at) VALUES ($user_id, '$notification_message', 'price_change', NOW())");
        }

        header('Location: clerk.php?section=submissions');
        exit;

    } elseif ($action === 'close_submission') {
        $submission_id = intval($_POST['submission_id']);
        $reason = $_POST['close_reason'] ?? 'The submission was closed.';

        $submission = $mysqli->query("SELECT * FROM device_submissions WHERE id = $submission_id")->fetch_assoc();
        $user_id = $submission['user_id'];
        $device_type = $submission['device_type'];
        $brand = $submission['brand'];
        $model = $submission['model'];
        $current_status = $submission['status'];

        $stmt = $mysqli->prepare("UPDATE device_submissions SET status = 'closed', reject_reason = ?, price_change_requested = 0, price_change_action = 'closed', return_requested = 0, price_change_appeal_reason = NULL WHERE id = ?");
        $stmt->bind_param("si", $reason, $submission_id);
        $stmt->execute();

        if ($current_status !== 'received' && $current_status !== 'repair_requested') {
            $notification_message = "Your submission #$submission_id ($device_type - $brand $model) has been closed. We understand if you change your mind. Feel free to submit a new request anytime!";
            $mysqli->query("INSERT INTO notifications (user_id, message, type, created_at) VALUES ($user_id, '$notification_message', 'rejection', NOW())");
        }

        header('Location: clerk.php?section=submissions');
        exit;
        
    } elseif ($action === 'update_submission') {
        $submission_id = intval($_POST['submission_id']);
        $device_type = $_POST['device_type'] ?? '';
        $brand = $_POST['brand'] ?? '';
        $model = $_POST['model'] ?? '';
        $estimated_price = floatval($_POST['estimated_price'] ?? 0);
        $location = $_POST['location'] ?? '';
        
        $stmt = $mysqli->prepare("UPDATE device_submissions SET device_type = ?, brand = ?, model = ?, estimated_price = ?, location = ? WHERE id = ?");
        $stmt->bind_param("sssdsi", $device_type, $brand, $model, $estimated_price, $location, $submission_id);
        $stmt->execute();
        
        header('Location: clerk.php?section=submissions&view=' . $submission_id);
        exit;
    }
}

// Get all submissions with search
$search = isset($_GET['search']) ? $mysqli->real_escape_string($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $mysqli->real_escape_string($_GET['status']) : '';

$where = "WHERE 1=1";
if ($search) {
    $where .= " AND (ds.device_type LIKE '%$search%' OR ds.brand LIKE '%$search%' OR ds.model LIKE '%$search%' OR u.username LIKE '%$search%')";
}
if ($status_filter) {
    $where .= " AND ds.status = '$status_filter'";
}

$submissions = $mysqli->query("
    SELECT ds.*, u.username 
    FROM device_submissions ds
    JOIN users u ON ds.user_id = u.id
    $where
    ORDER BY ds.id DESC
");
?>

<div class="content-section">
    <h2>Device Submissions</h2>
    
    <div class="search-bar">
        <input type="text" id="searchInput" placeholder="Search by device, brand, model, or user..." value="<?php echo htmlspecialchars($search); ?>">
        <select id="statusFilter">
            <option value="">All Status</option>
            <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
            <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>Approved</option>
            <option value="received" <?php echo $status_filter === 'received' ? 'selected' : ''; ?>>Received</option>
            <option value="repair_requested" <?php echo $status_filter === 'repair_requested' ? 'selected' : ''; ?>>Repair Requested</option>
            <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
            <option value="flagged" <?php echo $status_filter === 'flagged' ? 'selected' : ''; ?>>Flagged</option>
            <option value="closed" <?php echo $status_filter === 'closed' ? 'selected' : ''; ?>>Closed</option>
        </select>
        <button class="btn btn-primary" onclick="applyFilters()">Search</button>
    </div>
    
    
    <?php if ($submissions && $submissions->num_rows > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Device</th>
                    <th>Brand</th>
                    <th>Model</th>
                    <th>User</th>
                    <th>Est. Price</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($sub = $submissions->fetch_assoc()): ?>
                    <tr>
                        <td>#<?php echo $sub['id']; ?></td>
                        <td><?php echo htmlspecialchars($sub['device_type']); ?></td>
                        <td><?php echo htmlspecialchars($sub['brand']); ?></td>
                        <td><?php echo htmlspecialchars($sub['model']); ?></td>
                        <td><?php echo htmlspecialchars($sub['username']); ?></td>
                        <td>RM<?php echo number_format($sub['estimated_price'], 2); ?></td>
                        <td>
                            <?php
                            $status_value = $sub['status'] ?? '';
                            $price_action = $sub['price_change_action'] ?? '';
                            $workflow_statuses = ['repair_requested', 'repairing', 'received', 'disassembled', 'completed', 'rejected', 'flagged', 'closed', 'awaiting_drop_off'];

                            if (in_array($status_value, $workflow_statuses, true)) {
                                $badge_class = 'status-' . $status_value;
                                $badge_label = ucfirst(str_replace('_', ' ', $status_value));
                            } elseif ($status_value === 'approved') {
                                $badge_class = 'status-approved';
                                $badge_label = 'Approved';
                            } elseif ($status_value === 'pending' && $price_action === 'approved') {
                                $badge_class = 'status-user_approved_price';
                                $badge_label = 'User Approved Price';
                            } elseif ($price_action === 'rejected') {
                                $badge_class = 'status-user_rejected_price';
                                $badge_label = 'User Rejected Price';
                            } elseif ($price_action === 'appealed') {
                                $badge_class = 'status-user_appealed_price';
                                $badge_label = 'User Appealed Price';
                            } else {
                                $badge_class = 'status-' . $status_value;
                                $badge_label = ucfirst(str_replace('_', ' ', $status_value));
                            }
                            ?>
                            <span class="status-badge <?php echo $badge_class; ?>"><?php echo htmlspecialchars($badge_label); ?></span>
                        </td>
                        <td>
                            <button class="btn btn-primary btn-sm" onclick="viewSubmissionClerk(<?php echo $sub['id']; ?>)">View</button>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>No submissions found.</p>
    <?php endif; ?>
</div>

<!-- View Submission Modal -->
<div id="viewSubmissionModal" class="modal">
    <div class="modal-content" style="max-width: 700px;">
        <div class="modal-header">
            <h3>Submission Details</h3>
            <button class="close-modal" onclick="closeModal('viewSubmissionModal')">&times;</button>
        </div>
        <div id="submissionDetails"></div>
    </div>
</div>

<!-- Edit Submission Modal -->
<div id="editSubmissionModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Edit Submission</h3>
            <button class="close-modal" onclick="closeModal('editSubmissionModal')">&times;</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="action" value="update_submission">
            <input type="hidden" name="submission_id" id="editSubmissionId">
            
            <div class="form-group">
                <label for="edit_device_type">Device Type</label>
                <input type="text" id="edit_device_type" name="device_type" required>
            </div>
            
            <div class="form-group">
                <label for="edit_brand">Brand</label>
                <input type="text" id="edit_brand" name="brand" required>
            </div>
            
            <div class="form-group">
                <label for="edit_model">Model</label>
                <input type="text" id="edit_model" name="model" required>
            </div>
            
            <div class="form-group">
                <label for="edit_estimated_price">Estimated Price (RM)</label>
                <input type="number" id="edit_estimated_price" name="estimated_price" step="0.01" min="0" required>
            </div>
            
            <div class="form-group">
                <label for="edit_location">Location</label>
                <input type="text" id="edit_location" name="location">
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editSubmissionModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Price Modal -->
<div id="editPriceModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Edit Price</h3>
            <button class="close-modal" onclick="closeModal('editPriceModal')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="edit_price">
            <input type="hidden" name="submission_id" id="editPriceSubmissionId">
            <div class="form-group">
                <label>New Estimated Price (RM) *</label>
                <input type="number" step="0.01" name="new_price" id="newPrice" required>
            </div>
            <div class="form-group">
                <label>Reason for Price Change (will be sent to user) *</label>
                <textarea name="price_change_reason" rows="3" placeholder="Explain why the price was adjusted..." required></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editPriceModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Price</button>
            </div>
        </form>
    </div>
</div>

<!-- Close Submission Modal -->
<div id="closeSubmissionModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Close Submission</h3>
            <button class="close-modal" onclick="closeModal('closeSubmissionModal')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="close_submission">
            <input type="hidden" name="submission_id" id="closeSubmissionId">
            <div class="form-group">
                <label>Closing Note *</label>
                <textarea name="close_reason" rows="3" required placeholder="Explain the closing message to the user."></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('closeSubmissionModal')">Cancel</button>
                <button type="submit" class="btn btn-danger">Close Submission</button>
            </div>
        </form>
    </div>
</div>

<!-- Flag Modal -->
<div id="flagModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Flag Submission</h3>
            <button class="close-modal" onclick="closeModal('flagModal')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="flag">
            <input type="hidden" name="submission_id" id="flagSubmissionId">
            <div class="form-group">
                <label>Flag Reason *</label>
                <textarea name="flag_reason" rows="3" required placeholder="Explain why this submission is being flagged..."></textarea>
            </div>
            <div class="form-group">
                <label>Additional Notes (will be sent to user)</label>
                <textarea name="flag_notes" rows="3" placeholder="Provide additional details about what needs to be corrected..."></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('flagModal')">Cancel</button>
                <button type="submit" class="btn btn-warning">Flag Submission</button>
            </div>
        </form>
    </div>
</div>

<!-- Reject Modal -->
<div id="rejectModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Reject Submission</h3>
            <button class="close-modal" onclick="closeModal('rejectModal')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="reject">
            <input type="hidden" name="submission_id" id="rejectSubmissionId">
            <div class="form-group">
                <label>Reject Reason *</label>
                <textarea name="reject_reason" rows="3" required placeholder="Explain why this submission is being rejected..."></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('rejectModal')">Cancel</button>
                <button type="submit" class="btn btn-danger">Reject Submission</button>
            </div>
        </form>
    </div>
</div>

<!-- Approve Modal -->
<div id="approveModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Approve Submission</h3>
            <button class="close-modal" onclick="closeModal('approveModal')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="approve">
            <input type="hidden" name="submission_id" id="approveSubmissionId">
            <div class="form-group">
                <label>Final Price (RM) *</label>
                <input type="number" step="0.01" name="final_price" id="finalPrice" required>
            </div>
            <div class="form-group">
                <label>Drop-off Point *</label>
                <select name="drop_off_point" required>
                    <option value="">Select a drop-off point...</option>
                    <?php while ($point = $drop_off_points->fetch_assoc()): ?>
                        <option value="<?php echo $point['id']; ?>"><?php echo htmlspecialchars($point['name']); ?> - <?php echo htmlspecialchars($point['address']); ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('approveModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Approve</button>
            </div>
        </form>
    </div>
</div>

<!-- Mark Received Modal -->
<div id="markReceivedModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Mark as Received</h3>
            <button class="close-modal" onclick="closeModal('markReceivedModal')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="mark_received">
            <input type="hidden" name="submission_id" id="markReceivedSubmissionId">
            <p>Are you sure you want to mark this submission as received?</p>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('markReceivedModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Mark Received</button>
            </div>
        </form>
    </div>
</div>

<!-- Mark for Repair Modal -->
<div id="markForRepairModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Mark for Repair</h3>
            <button class="close-modal" onclick="closeModal('markForRepairModal')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="mark_for_repair">
            <input type="hidden" name="submission_id" id="markForRepairSubmissionId">
            <p>Are you sure you want to mark this device for repair? This will create a repair request.</p>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('markForRepairModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Mark for Repair</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(modalId) {
    document.getElementById(modalId).classList.add('active');
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('active');
}

function applyFilters() {
    const search = document.getElementById('searchInput').value;
    const status = document.getElementById('statusFilter').value;
    window.location.href = 'clerk.php?section=submissions&search=' + encodeURIComponent(search) + '&status=' + encodeURIComponent(status);
}

function viewSubmissionClerk(id) {
    console.log('viewSubmissionClerk called with id:', id);
    fetch('clerk_submissions.php?get_details=1&id=' + id)
        .then(response => response.json())
        .then(data => {
            console.log('Received data:', data);
            if (data.error) {
                document.getElementById('submissionDetails').innerHTML = '<p>' + data.error + '</p>';
                openModal('viewSubmissionModal');
                return;
            }
            
            let html = '<style>';
            html += '.details-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin: 20px 0; }';
            html += '.detail-item { padding: 12px; background: #f8f9fa; border-radius: 8px; border-left: 4px solid #10b981; }';
            html += '.detail-item strong { display: block; margin-bottom: 5px; color: #333; font-size: 13px; }';
            html += '.detail-item span { color: #555; font-size: 15px; }';
            html += '.detail-item-full { padding: 12px; background: #f8f9fa; border-radius: 8px; border-left: 4px solid #10b981; margin-top: 15px; }';
            html += '.detail-item-full strong { display: block; margin-bottom: 5px; color: #333; font-size: 13px; }';
            html += '.detail-item-full span { color: #555; font-size: 15px; }';
            html += '.images-container { display: flex; gap: 15px; margin: 25px 0; flex-wrap: wrap; }';
            html += '.images-container img { width: 120px; height: 120px; object-fit: cover; border-radius: 8px; cursor: pointer; border: 2px solid #e5e7eb; }';
            html += '</style>';

            html += '<div class="details-grid">';
            html += '<div class="detail-item"><strong>Device Type</strong><span>' + (data.device_type || 'N/A') + '</span></div>';
            html += '<div class="detail-item"><strong>Brand</strong><span>' + (data.brand || 'N/A') + '</span></div>';
            html += '<div class="detail-item"><strong>Model</strong><span>' + (data.model || 'N/A') + '</span></div>';
            html += '<div class="detail-item"><strong>Estimated Price</strong><span>RM' + (data.estimated_price ? parseFloat(data.estimated_price).toFixed(2) : '0.00') + '</span></div>';
            html += '<div class="detail-item"><strong>Location</strong><span>' + (data.location || 'Not specified') + '</span></div>';
            html += '<div class="detail-item"><strong>Condition</strong><span>' + (data.device_condition || 'Not specified') + '</span></div>';
            html += '<div class="detail-item"><strong>Submitted By</strong><span>' + (data.username || 'N/A') + '</span></div>';
            html += '<div class="detail-item"><strong>Email</strong><span>' + (data.email || 'N/A') + '</span></div>';
            html += '<div class="detail-item"><strong>Phone</strong><span>' + (data.phone || 'Not provided') + '</span></div>';
            html += '<div class="detail-item"><strong>Status</strong><span>' + (data.status || 'N/A') + '</span></div>';
            html += '<div class="detail-item"><strong>Submission ID</strong><span>#' + (data.id || 'N/A') + '</span></div>';
            html += '</div>';

            html += '<div class="detail-item-full"><strong>Description</strong><span>' + (data.description || 'Not specified') + '</span></div>';
            html += '<div class="detail-item-full"><strong>Accessories</strong><span>' + (data.accessories || 'None') + '</span></div>';

            if (data.reject_reason) {
                html += '<div class="detail-item-full" style="border-left-color: #dc3545;"><strong>Rejection Reason</strong><span>' + data.reject_reason + '</span></div>';
            }
            if (data.flag_reason) {
                html += '<div class="detail-item-full" style="border-left-color: #ffc107;"><strong>Flag Reason</strong><span>' + data.flag_reason + '</span></div>';
            }

            if (data.price_change_action) {
                let priceStateClass = 'detail-item-full';
                let priceStateTitle = 'Price Change Response';
                let priceStateText = 'User action: ' + (data.price_change_action === 're_evaluated' ? 'Awaiting customer\'s response to the revised price.' : (data.price_change_action || 'N/A'));
                let priceStateColor = '#f59e0b';

                if (data.price_change_action === 'approved') {
                    priceStateColor = '#f59e0b';
                    priceStateText = 'The user accepted the final price and is waiting for the clerk to re-approve.';
                } else if (data.price_change_action === 'declined') {
                    priceStateColor = '#dc2626';
                    priceStateText = 'The user declined the final price and closed the submission.';
                } else if (data.price_change_action === 'counter_offer') {
                    priceStateColor = '#f59e0b';
                    priceStateText = 'Awaiting customer\'s response to the revised price.';
                } else if (data.price_change_action === 'closed') {
                    priceStateColor = '#6b7280';
                    priceStateText = 'The submission was closed by the clerk.';
                }

                html += '<div class="detail-item-full" style="border-left-color: ' + priceStateColor + ';"><strong>' + priceStateTitle + '</strong><span>' + priceStateText + '</span>';
                if (data.price_change_new_price) {
                    html += '<br><span>Requested price: RM' + parseFloat(data.price_change_new_price).toFixed(2) + '</span>';
                }
                if (data.price_change_appeal_reason) {
                    html += '<br><span>Appeal reason: ' + data.price_change_appeal_reason + '</span>';
                }
                html += '</div>';
            }

            html += '<div style="margin-top: 25px;"><strong>Images</strong><div class="images-container">';
            if (data.has_image1) {
                html += '<img src="get_image.php?id=' + id + '&field=image1" onclick="showImageModal(this.src)" onerror="this.style.display=\'none\'">';
            }
            if (data.has_image2) {
                html += '<img src="get_image.php?id=' + id + '&field=image2" onclick="showImageModal(this.src)" onerror="this.style.display=\'none\'">';
            }
            if (data.has_image3) {
                html += '<img src="get_image.php?id=' + id + '&field=image3" onclick="showImageModal(this.src)" onerror="this.style.display=\'none\'">';
            }
            html += '</div></div>';

            if (data.has_video) {
                html += '<div style="margin-top: 25px;"><strong>Video</strong><br><video controls style="max-width: 100%; max-height: 400px; border-radius: 8px;" onerror="this.parentElement.innerHTML=\'<p style=color:red>Video failed to load. The video file may be too large or corrupted.</p>\'"><source src="get_image.php?id=' + id + '&field=video" type="video/mp4">Your browser does not support video.</video></div>';
            } else {
                html += '<div style="margin-top: 25px;"><strong>Video</strong><p>No video uploaded</p></div>';
            }

            if (data.drop_pin) {
                html += '<div style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); padding: 30px; border-radius: 12px; text-align: center; font-size: 48px; font-weight: bold; letter-spacing: 8px; margin: 25px 0; color: white; box-shadow: 0 4px 15px rgba(16, 185, 129, 0.4);">';
                html += data.drop_pin;
                html += '</div>';
                html += '<p style="text-align: center; color: #666;">Drop-off PIN</p>';
            }

            const finalOfferActions = ['counter_offer', 'declined', 'closed', 'appealed'];
            const isClosed = (data.status || '').toLowerCase() === 'closed';
            const needsClerkReapproval = !isClosed && (data.status || '').toLowerCase() === 'pending' && data.price_change_action === 'approved';
            if (!isClosed && data.price_change_action === 'rejected') {
                html += '<div class="action-buttons" style="margin-top: 20px;">';
                html += '<button type="button" class="btn btn-secondary" onclick="closeSubmission(' + data.id + ', false)">Close Submission</button>';
                html += '</div>';
            } else if (!isClosed && finalOfferActions.includes(data.price_change_action)) {
                html += '<div class="action-buttons" style="margin-top: 20px;">';
                html += '<button type="button" class="btn btn-primary" onclick="reEvaluatePrice(' + data.id + ', ' + (data.price_change_new_price !== null && data.price_change_new_price !== undefined ? data.price_change_new_price : 'null') + ')">Counter-Offer</button>';
                html += '<button type="button" class="btn btn-secondary" onclick="closeSubmission(' + data.id + ', true)">Close Submission</button>';
                html += '</div>';
            } else if (needsClerkReapproval) {
                html += '<div class="action-buttons" style="margin-top: 20px;">';
                html += '<button type="button" class="btn btn-primary" onclick="approveSubmission(' + data.id + ', ' + (data.estimated_price !== null && data.estimated_price !== undefined ? data.estimated_price : 'null') + ')">Approve</button>';
                html += '<button type="button" class="btn btn-secondary" onclick="closeSubmission(' + data.id + ', true)">Close Submission</button>';
                html += '</div>';
            } else if (data.status === 'pending') {
                html += '<div class="action-buttons" style="margin-top: 20px;">';
                html += '<button type="button" class="btn btn-primary" onclick="approveSubmission(' + data.id + ', ' + (data.estimated_price !== null && data.estimated_price !== undefined ? data.estimated_price : 'null') + ')">Approve</button>';
                html += '<button type="button" class="btn btn-danger" onclick="rejectSubmission(' + data.id + ')">Reject</button>';
                html += '<button type="button" class="btn btn-warning" onclick="flagSubmission(' + data.id + ')">Flag</button>';
                html += '<button type="button" class="btn btn-secondary" onclick="editPrice(' + data.id + ', ' + (data.estimated_price !== null && data.estimated_price !== undefined ? data.estimated_price : 'null') + ')">Edit Price</button>';
                html += '</div>';
            } else if (data.status === 'approved' || data.status === 'awaiting_drop_off') {
                html += '<div style="margin-top: 20px; padding: 15px; background: #d1fae5; border-radius: 8px; border-left: 4px solid #10b981;">';
                html += '<strong>PIN:</strong> ' + (data.drop_pin || 'N/A') + '<br>';
                html += '<strong>Submission ID:</strong> #' + (data.id || 'N/A') + '<br><br>';
                html += '<p style="color: #059669; margin: 0;">Use the Device Collection/Sale tab to mark this device as received.</p>';
                html += '</div>';
            } else if (data.status === 'received') {
                html += '<div class="action-buttons" style="margin-top: 20px;">';
                html += '<button type="button" class="btn btn-primary" onclick="markForRepair(' + data.id + ')">Mark for Repair</button>';
                html += '</div>';
            } else if (data.status === 'completed') {
                html += '<div class="action-buttons" style="margin-top: 20px;">';
                html += '<button type="button" class="btn btn-primary" onclick="markForRepair(' + data.id + ')">Mark for Repair</button>';
                html += '</div>';
            }

            document.getElementById('submissionDetails').innerHTML = html;
            openModal('viewSubmissionModal');
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('submissionDetails').innerHTML = '<p>Error loading submission details.</p>';
            openModal('viewSubmissionModal');
        });
}

function openEditModal(submissionId) {
    fetch('clerk_submissions.php?get_details=1&id=' + submissionId)
        .then(response => response.json())
        .then(data => {
            document.getElementById('editSubmissionId').value = data.id;
            document.getElementById('edit_device_type').value = data.device_type;
            document.getElementById('edit_brand').value = data.brand;
            document.getElementById('edit_model').value = data.model;
            document.getElementById('edit_estimated_price').value = data.estimated_price;
            document.getElementById('edit_location').value = data.location || '';
            openModal('editSubmissionModal');
        });
}

function editPrice(id, currentPrice) {
    document.getElementById('editPriceSubmissionId').value = id;
    document.getElementById('newPrice').value = currentPrice;
    document.querySelector('#editPriceModal form input[name="action"]').value = 'edit_price';
    document.querySelector('#editPriceModal .modal-header h3').textContent = 'Edit Price';
    openModal('editPriceModal');
}

function reEvaluatePrice(id, currentPrice) {
    document.getElementById('editPriceSubmissionId').value = id;
    document.getElementById('newPrice').value = currentPrice;
    document.querySelector('#editPriceModal form input[name="action"]').value = 're_evaluate_price';
    document.querySelector('#editPriceModal .modal-header h3').textContent = 'Re-evaluate Price';
    openModal('editPriceModal');
}

function rejectSubmission(id) {
    document.getElementById('rejectSubmissionId').value = id;
    openModal('rejectModal');
}

function flagSubmission(id) {
    document.getElementById('flagSubmissionId').value = id;
    openModal('flagModal');
}

function closeSubmission(id, requireReason = true) {
    if (!requireReason) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.style.display = 'none';
        form.innerHTML = `
            <input type="hidden" name="action" value="close_submission">
            <input type="hidden" name="submission_id" value="${id}">
            <input type="hidden" name="close_reason" value="The submission was closed.">
        `;
        document.body.appendChild(form);
        form.submit();
        return;
    }

    document.getElementById('closeSubmissionId').value = id;
    openModal('closeSubmissionModal');
}

function approveSubmission(id, currentPrice) {
    document.getElementById('approveSubmissionId').value = id;
    document.getElementById('finalPrice').value = currentPrice !== null && currentPrice !== undefined ? currentPrice : '';
    openModal('approveModal');
}

function showImageModal(src) {
    const modal = document.createElement('div');
    modal.className = 'image-modal';
    modal.innerHTML = `
        <div class="image-modal-content">
            <span class="close-image-modal">&times;</span>
            <img src="${src}" alt="Submission Image">
        </div>
    `;
    document.body.appendChild(modal);
    
    modal.querySelector('.close-image-modal').onclick = function() {
        document.body.removeChild(modal);
    };
    
    modal.onclick = function(e) {
        if (e.target === modal) {
            document.body.removeChild(modal);
        }
    };
}

function markReceived(submissionId) {
    document.getElementById('markReceivedSubmissionId').value = submissionId;
    openModal('markReceivedModal');
}

function markForRepair(submissionId) {
    document.getElementById('markForRepairSubmissionId').value = submissionId;
    openModal('markForRepairModal');
}
</script>

<style>
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
    transition: transform 0.3s;
}

.submission-images img:hover {
    transform: scale(1.1);
}

.image-modal {
    display: flex;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.9);
    z-index: 2000;
    justify-content: center;
    align-items: center;
}

.image-modal-content {
    position: relative;
    max-width: 90%;
    max-height: 90%;
}

.image-modal-content img {
    max-width: 100%;
    max-height: 90vh;
    border-radius: 8px;
}

.close-image-modal {
    position: absolute;
    top: -40px;
    right: 0;
    color: white;
    font-size: 40px;
    cursor: pointer;
    line-height: 1;
}

.action-buttons {
    display: flex;
    gap: 10px;
}
</style>
