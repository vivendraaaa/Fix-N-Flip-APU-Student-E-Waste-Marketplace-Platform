<?php
$mysqli = new mysqli("localhost", "root", "", "fyp");

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

// Handle submission actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'approve') {
            $submission_id = intval($_POST['submission_id']);
            $final_price = floatval($_POST['final_price']);
            $drop_off_point = intval($_POST['drop_off_point']);

            // Generate QR code
            $qr_code = 'FIXNFLIP-' . strtoupper(uniqid());
            
            // Generate PIN for drop-off
            $drop_pin = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);

            // Update submission with approved status, price, drop-off point, QR code, and PIN
            $mysqli->query("UPDATE device_submissions SET status = 'approved', estimated_price = $final_price, drop_off_point_id = $drop_off_point, qr_code = '$qr_code', drop_pin = '$drop_pin' WHERE id = $submission_id");

            // Create repair tracking record
            $user_result = $mysqli->query("SELECT user_id FROM device_submissions WHERE id = $submission_id");
            if ($user_result) {
                $user_row = $user_result->fetch_assoc();
                $user_id = $user_row['user_id'];
                $mysqli->query("INSERT INTO device_repairs (submission_id, user_id, drop_off_point_id, qr_code, status, created_at) VALUES ($submission_id, $user_id, $drop_off_point, '$qr_code', 'awaiting_dropoff', NOW())");

                // Notify user
                $point_result = $mysqli->query("SELECT name FROM drop_off_points WHERE id = $drop_off_point");
                $point_name = $point_result ? $point_result->fetch_assoc()['name'] : 'Unknown';
                $message = "Your submission has been approved! Please drop off your device at: $point_name. QR Code: $qr_code";
                $mysqli->query("INSERT INTO notifications (user_id, message, type, created_at) VALUES ($user_id, '$message', 'approval', NOW())");
            }
        } elseif ($_POST['action'] === 'reject') {
            $submission_id = intval($_POST['submission_id']);
            $reject_reason = $mysqli->real_escape_string($_POST['reject_reason']);
            
            // Get submission details including current status
            $submission = $mysqli->query("SELECT user_id, brand, model, status FROM device_submissions WHERE id = $submission_id")->fetch_assoc();
            $current_status = $submission ? $submission['status'] : '';
            
            $mysqli->query("UPDATE device_submissions SET status = 'rejected', reject_reason = '$reject_reason' WHERE id = $submission_id");

            // Only notify user if device hasn't been picked up yet (not received or repair_requested)
            if ($submission && $current_status !== 'received' && $current_status !== 'repair_requested') {
                $user_id = $submission['user_id'];
                $device_name = $submission['brand'] . ' ' . $submission['model'];
                $message = "Your request for device ($device_name) was rejected\n\nReason: $reject_reason";
                $mysqli->query("INSERT INTO notifications (user_id, message, type, created_at) VALUES ($user_id, '$message', 'rejection', NOW())");
            }
        } elseif ($_POST['action'] === 'delete') {
            $submission_id = intval($_POST['submission_id']);
            $mysqli->query("DELETE FROM device_submissions WHERE id = $submission_id");
        } elseif ($_POST['action'] === 'edit_price') {
            $submission_id = intval($_POST['submission_id']);
            $new_price = floatval($_POST['new_price']);
            $reason = $mysqli->real_escape_string($_POST['price_change_reason']);
            
            // Get submission details including current status
            $submission = $mysqli->query("SELECT user_id, brand, model, status FROM device_submissions WHERE id = $submission_id")->fetch_assoc();
            $current_status = $submission ? $submission['status'] : '';
            
            $mysqli->query("UPDATE device_submissions SET estimated_price = $new_price WHERE id = $submission_id");

            // Only notify user if device hasn't been picked up yet (not received or repair_requested)
            if ($submission && $current_status !== 'received' && $current_status !== 'repair_requested') {
                $user_id = $submission['user_id'];
                $message = "Your submission price has been updated to RM$new_price. Reason: $reason";
                $mysqli->query("INSERT INTO notifications (user_id, message, type, created_at) VALUES ($user_id, '$message', 'price_change', NOW())");
            }
        } elseif ($_POST['action'] === 'flag') {
            $submission_id = intval($_POST['submission_id']);
            $flag_reason = $mysqli->real_escape_string($_POST['flag_reason']);
            $flag_notes = $mysqli->real_escape_string($_POST['flag_notes']);
            
            // Get submission details including current status
            $submission = $mysqli->query("SELECT user_id, brand, model, status FROM device_submissions WHERE id = $submission_id")->fetch_assoc();
            $current_status = $submission ? $submission['status'] : '';
            
            $mysqli->query("UPDATE device_submissions SET status = 'flagged', flag_reason = '$flag_reason', flag_notes = '$flag_notes' WHERE id = $submission_id");

            // Only notify user if device hasn't been picked up yet (not received or repair_requested)
            if ($submission && $current_status !== 'received' && $current_status !== 'repair_requested') {
                $user_id = $submission['user_id'];
                $device_name = $submission['brand'] . ' ' . $submission['model'];
                $message = "Your request for device ($device_name) was flagged\n\nReason: $flag_reason\n\nNotes: $flag_notes";
                $mysqli->query("INSERT INTO notifications (user_id, message, type, created_at) VALUES ($user_id, '$message', 'flag', NOW())");
            }
        } elseif ($_POST['action'] === 'update_submission') {
            $submission_id = intval($_POST['submission_id']);
            $device_type = $_POST['device_type'] ?? '';
            $brand = $_POST['brand'] ?? '';
            $model = $_POST['model'] ?? '';
            $estimated_price = floatval($_POST['estimated_price'] ?? 0);
            $location = $_POST['location'] ?? '';
            
            $stmt = $mysqli->prepare("UPDATE device_submissions SET device_type = ?, brand = ?, model = ?, estimated_price = ?, location = ? WHERE id = ?");
            $stmt->bind_param("sssdii", $device_type, $brand, $model, $estimated_price, $location, $submission_id);
            $stmt->execute();
            
            header('Location: admin.php?section=submissions');
            exit;
        }
    }
}

// Get filter
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

// Build query
$query = "SELECT ds.*, u.username FROM device_submissions ds LEFT JOIN users u ON ds.user_id = u.id";
if ($status_filter) {
    $query .= " WHERE ds.status = '$status_filter'";
}
$query .= " ORDER BY ds.submitted_at DESC";

$submissions = $mysqli->query($query);

// Get drop-off points
$drop_off_points_result = $mysqli->query("SELECT id, name FROM drop_off_points ORDER BY name ASC");
$drop_off_points = [];
while ($row = $drop_off_points_result->fetch_assoc()) {
    $drop_off_points[] = $row;
}
?>

<style>
    .modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.5);
        z-index: 1000;
        justify-content: center;
        align-items: center;
    }

    .modal-content {
        background: white;
        padding: 25px;
        border-radius: 12px;
        max-width: 500px;
        width: 90%;
        max-height: 90vh;
        overflow-y: auto;
    }

    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
    }

    .close-modal {
        background: none;
        border: none;
        font-size: 24px;
        cursor: pointer;
        color: #666;
    }

    .form-group {
        margin-bottom: 20px;
    }

    .form-group label {
        display: block;
        margin-bottom: 8px;
        color: #333;
        font-weight: 500;
    }

    .form-group input,
    .form-group select,
    .form-group textarea {
        width: 100%;
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 6px;
        font-size: 14px;
    }

    .modal-footer {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        margin-top: 20px;
    }

    .btn {
        padding: 8px 16px;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        font-size: 14px;
        transition: all 0.3s;
    }

    .btn-primary {
        background: #10b981;
        color: white;
    }

    .btn-primary:hover {
        background: #059669;
    }

    .btn-danger {
        background: #ef4444;
        color: white;
    }

    .btn-danger:hover {
        background: #dc2626;
    }

    .btn-secondary {
        background: #6b7280;
        color: white;
    }

    .btn-secondary:hover {
        background: #4b5563;
    }

    .btn-warning {
        background: #f59e0b;
        color: white;
    }

    .btn-warning:hover {
        background: #d97706;
    }

    .btn-sm {
        padding: 5px 10px;
        font-size: 12px;
    }

    .content-section {
        background: white;
        padding: 25px;
        border-radius: 12px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        margin-bottom: 30px;
    }

    .content-section h2 {
        margin-bottom: 20px;
        color: #333;
    }

    table {
        width: 100%;
        border-collapse: collapse;
    }

    table th,
    table td {
        padding: 12px;
        text-align: left;
        border-bottom: 1px solid #e5e7eb;
    }

    table th {
        background: #f9fafb;
        font-weight: 600;
        color: #374151;
    }

    .status-badge {
        padding: 4px 8px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 500;
    }

    .status-pending {
        background: #fef3c7;
        color: #d97706;
    }

    .status-approved {
        background: #d1fae5;
        color: #059669;
    }

    .status-rejected {
        background: #fee2e2;
        color: #dc2626;
    }

    .status-flagged {
        background: #fce7f3;
        color: #db2777;
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
    }

    .submission-images img:hover {
        transform: scale(1.1);
    }

    .action-buttons {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }
</style>

<div class="content-section">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h2>Device Submissions</h2>
        <div>
            <select onchange="window.location.href='admin.php?section=submissions&status='+this.value" style="padding: 8px; border-radius: 6px; border: 1px solid #ddd;">
                <option value="">All Status</option>
                <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
            </select>
        </div>
    </div>
    
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>User</th>
                <th>Device</th>
                <th>Brand</th>
                <th>Model</th>
                <th>Storage</th>
                <th>RAM</th>
                <th>Est. Price</th>
                <th>Status</th>
                <th>Date</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($sub = $submissions->fetch_assoc()): ?>
                <tr>
                    <td><?php echo $sub['id']; ?></td>
                    <td><?php echo htmlspecialchars($sub['username']); ?></td>
                    <td><?php echo htmlspecialchars($sub['device_type']); ?></td>
                    <td><?php echo htmlspecialchars($sub['brand']); ?></td>
                    <td><?php echo htmlspecialchars($sub['model']); ?></td>
                    <td><?php echo htmlspecialchars($sub['storage']); ?></td>
                    <td><?php echo htmlspecialchars($sub['ram']); ?></td>
                    <td>RM<?php echo number_format($sub['estimated_price'], 2); ?></td>
                    <td><span class="status-badge status-<?php echo $sub['status']; ?>"><?php echo ucfirst($sub['status']); ?></span></td>
                    <td><?php echo date('M d, Y', strtotime($sub['submitted_at'])); ?></td>
                    <td>
                        <button class="btn btn-primary btn-sm" onclick="viewSubmission(<?php echo $sub['id']; ?>)">View</button>
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
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
                <select name="drop_off_point" id="dropOffPoint" required>
                    <!-- Options will be loaded by JavaScript -->
                </select>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('approveModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Approve</button>
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

<!-- Mark for Repair Modal -->
<div id="markForRepairModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Mark for Repair</h3>
            <button class="close-modal" onclick="closeModal('markForRepairModal')">&times;</button>
        </div>
        <form method="POST" action="clerk_submissions.php">
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

<!-- Delete Submission Modal -->
<div id="deleteSubmissionModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Delete Submission</h3>
            <button class="close-modal" onclick="closeModal('deleteSubmissionModal')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="submission_id" id="deleteSubmissionId">
            <p>Are you sure you want to delete this submission? This action cannot be undone.</p>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('deleteSubmissionModal')">Cancel</button>
                <button type="submit" class="btn btn-danger">Delete Submission</button>
            </div>
        </form>
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

<!-- Image Viewer Modal -->
<div id="imageViewerModal" class="modal">
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

<script>
// Pass PHP data to JavaScript
const dropOffPoints = <?php echo json_encode($drop_off_points); ?>;

function viewSubmission(id) {
    fetch('get_submission_details.php?id=' + id)
        .then(response => response.json())
        .then(data => {
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
            html += '<div class="detail-item"><strong>User</strong><span>' + (data.username || 'N/A') + '</span></div>';
            html += '<div class="detail-item"><strong>Device Type</strong><span>' + (data.device_type || 'N/A') + '</span></div>';
            html += '<div class="detail-item"><strong>Brand</strong><span>' + (data.brand || 'N/A') + '</span></div>';
            html += '<div class="detail-item"><strong>Model</strong><span>' + (data.model || 'N/A') + '</span></div>';
            html += '<div class="detail-item"><strong>Storage</strong><span>' + (data.storage || 'N/A') + '</span></div>';
            html += '<div class="detail-item"><strong>RAM</strong><span>' + (data.ram || 'N/A') + '</span></div>';
            html += '<div class="detail-item"><strong>Battery Health</strong><span>' + (data.battery_health ? data.battery_health + '%' : 'N/A') + '</span></div>';
            html += '<div class="detail-item"><strong>Condition</strong><span>' + (data.device_condition || 'N/A') + '</span></div>';
            html += '<div class="detail-item"><strong>Estimated Price</strong><span>RM' + (data.estimated_price ? parseFloat(data.estimated_price).toFixed(2) : '0.00') + '</span></div>';
            html += '<div class="detail-item"><strong>Status</strong><span>' + (data.status || 'N/A') + '</span></div>';
            html += '<div class="detail-item"><strong>Submitted</strong><span>' + (data.submitted_at ? new Date(data.submitted_at).toLocaleDateString() : 'N/A') + '</span></div>';
            html += '</div>';

            if (data.description) {
                html += '<div class="detail-item-full"><strong>Description</strong><span>' + data.description + '</span></div>';
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
                html += '<div style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); padding: 30px; border-radius: 12px; text-align: center; font-size: 48px; font-weight: bold; letter-spacing: 8px; margin: 25px 0; color: white; box-shadow: 0 4px 15px rgba(16, 185, 129, 0.4);">\n';
                html += data.drop_pin;
                html += '</div>';
                html += '<p style="text-align: center; color: #666;">Drop-off PIN</p>';
            }

            if (data.status === 'pending') {
                html += '<div class="action-buttons" style="margin-top: 20px;">';
                html += '<button type="button" class="btn btn-primary" onclick="approveSubmission(' + data.id + ', ' + (data.estimated_price || 0) + ')">Approve</button>';
                html += '<button type="button" class="btn btn-danger" onclick="rejectSubmission(' + data.id + ')">Reject</button>';
                html += '<button type="button" class="btn btn-warning" onclick="flagSubmission(' + data.id + ')">Flag</button>';
                html += '<button type="button" class="btn btn-secondary" onclick="editPrice(' + data.id + ', ' + (data.estimated_price || 0) + ')">Edit Price</button>';
                html += '<button type="button" class="btn btn-secondary" onclick="openEditModal(' + data.id + ')">Edit</button>';
                html += '<button type="button" class="btn btn-danger" onclick="deleteSubmission(' + data.id + ')">Delete</button>';
                html += '</div>';
            } else if (data.status === 'received') {
                html += '<div class="action-buttons" style="margin-top: 20px;">';
                html += '<button type="button" class="btn btn-primary" onclick="markForRepair(' + data.id + ')">Mark for Repair</button>';
                html += '</div>';
            } else if (data.status === 'completed') {
                html += '<div class="action-buttons" style="margin-top: 20px;">';
                html += '<button type="button" class="btn btn-success" onclick="window.location.href=\'admin.php?section=listings\'">View Listings</button>';
                html += '</div>';
            }

            document.getElementById('submissionDetails').innerHTML = html;
            openModal('viewSubmissionModal');
        });
}

function approveSubmission(id, currentPrice) {
    document.getElementById('approveSubmissionId').value = id;
    document.getElementById('finalPrice').value = currentPrice;

    // Populate drop-off points
    const dropOffPointSelect = document.getElementById('dropOffPoint');
    dropOffPointSelect.innerHTML = ''; // Clear previous options
    dropOffPoints.forEach(point => {
        const option = document.createElement('option');
        option.value = point.id;
        option.textContent = point.name;
        dropOffPointSelect.appendChild(option);
    });

    openModal('approveModal');
}

function editPrice(id, currentPrice) {
    document.getElementById('editPriceSubmissionId').value = id;
    document.getElementById('newPrice').value = currentPrice;
    openModal('editPriceModal');
}

function flagSubmission(id) {
    document.getElementById('flagSubmissionId').value = id;
    openModal('flagModal');
}

function markForRepair(submissionId) {
    document.getElementById('markForRepairSubmissionId').value = submissionId;
    openModal('markForRepairModal');
}

function showImageModal(src) {
    document.getElementById('viewerImage').src = src;
    openModal('imageViewerModal');
}

function openModal(modalId) {
    console.log('Opening modal:', modalId);
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'flex';
        console.log('Modal found and display set to flex');
    } else {
        console.error('Modal not found:', modalId);
    }
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

function rejectSubmission(id) {
    document.getElementById('rejectSubmissionId').value = id;
    console.log('Opening reject modal for id:', id);
    openModal('rejectModal');
}

function deleteSubmission(id) {
    document.getElementById('deleteSubmissionId').value = id;
    openModal('deleteSubmissionModal');
}

function openEditModal(submissionId) {
    fetch('get_submission_details.php?id=' + submissionId)
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
</script>

