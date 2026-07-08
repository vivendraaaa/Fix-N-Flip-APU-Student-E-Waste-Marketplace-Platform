<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$mysqli = new mysqli("localhost", "root", "", "fyp");

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

function ensureRepairRequestColumn($mysqli, $column, $definition) {
    $check = $mysqli->query("SHOW COLUMNS FROM repair_requests LIKE '" . $mysqli->real_escape_string($column) . "'");
    if (!$check || $check->num_rows === 0) {
        $mysqli->query("ALTER TABLE repair_requests ADD COLUMN " . $definition);
    }
}

ensureRepairRequestColumn($mysqli, 'repair_started_at', 'repair_started_at DATETIME NULL');
ensureRepairRequestColumn($mysqli, 'repair_ended_at', 'repair_ended_at DATETIME NULL');
ensureRepairRequestColumn($mysqli, 'disposed_at', 'disposed_at DATETIME NULL');
ensureRepairRequestColumn($mysqli, 'rejection_reason', 'rejection_reason TEXT NULL');
ensureRepairRequestColumn($mysqli, 'parts_requested', 'parts_requested TEXT NULL');
ensureRepairRequestColumn($mysqli, 'parts_request_pin', 'parts_request_pin VARCHAR(4) NULL');
ensureRepairRequestColumn($mysqli, 'parts_request_status', "parts_request_status ENUM('pending','approved','rejected') DEFAULT 'pending'");


function ensureTableColumn($mysqli, $table, $column, $definition) {
    $tableSafe = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    $check = $mysqli->query("SHOW COLUMNS FROM `$tableSafe` LIKE '" . $mysqli->real_escape_string($column) . "'");
    if (!$check || $check->num_rows === 0) {
        $mysqli->query("ALTER TABLE `$tableSafe` ADD COLUMN " . $definition);
    }
}

ensureRepairRequestColumn($mysqli, 'disassembly_request_id', 'disassembly_request_id INT NULL');
ensureRepairRequestColumn($mysqli, 'repair_completed_at', 'repair_completed_at DATETIME NULL');
ensureRepairRequestColumn($mysqli, 'unrepairable_reason', 'unrepairable_reason TEXT NULL');
ensureRepairRequestColumn($mysqli, 'pickup_confirmed_at', 'pickup_confirmed_at DATETIME NULL');

// Keep this page safe even when some columns were not created by older SQL files.
ensureTableColumn($mysqli, 'parts_requests', 'parts_location', 'parts_location VARCHAR(255) NULL');
ensureTableColumn($mysqli, 'parts_requests', 'picked_up_at', 'picked_up_at DATETIME NULL');
ensureTableColumn($mysqli, 'parts_requests', 'clerk_notes', 'clerk_notes TEXT NULL');
ensureTableColumn($mysqli, 'disassembly_requests', 'pickup_code', 'pickup_code VARCHAR(8) NULL');
ensureTableColumn($mysqli, 'disassembly_requests', 'pickup_confirmed_at', 'pickup_confirmed_at DATETIME NULL');
ensureTableColumn($mysqli, 'disassembly_requests', 'completed_at', 'completed_at DATETIME NULL');
ensureTableColumn($mysqli, 'disassembly_requests', 'rejection_reason', 'rejection_reason TEXT NULL');
ensureTableColumn($mysqli, 'disassembly_requests', 'technician_notes', 'technician_notes TEXT NULL');

// Check if user is logged in and is technician
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in'] || strtolower($_SESSION['role']) !== 'technician') {
    header('Location: Login.php');
    exit;
}

$technician_id = $_SESSION['user_id'];

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'accept') {
        $request_id = intval($_POST['request_id']);
        // Generate pickup code
        $pickup_code = strtoupper(substr(md5(uniqid()), 0, 8));
        
        $stmt = $mysqli->prepare("UPDATE repair_requests SET status = 'accepted', pickup_code = ? WHERE id = ? AND technician_id = ?");
        $stmt->bind_param("sii", $pickup_code, $request_id, $technician_id);
        $stmt->execute();
        
        header('Location: technician.php?section=repairs');
        exit;
        
    } elseif ($action === 'reject') {
        $request_id = intval($_POST['request_id']);
        $reason = $_POST['rejection_reason'] ?? '';
        
        $stmt = $mysqli->prepare("UPDATE repair_requests SET status = 'rejected', rejection_reason = ? WHERE id = ? AND technician_id = ?");
        $stmt->bind_param("sii", $reason, $request_id, $technician_id);
        $stmt->execute();
        
        header('Location: technician.php?section=repairs');
        exit;
        
    } elseif ($action === 'start_repair') {
        $request_id = intval($_POST['request_id']);
        
        // Get submission_id from repair request
        $repair = $mysqli->query("SELECT submission_id FROM repair_requests WHERE id = $request_id AND technician_id = $technician_id")->fetch_assoc();
        
        if ($repair) {
            // Update repair_requests status
            $stmt = $mysqli->prepare("UPDATE repair_requests SET status = 'repairing', repair_started_at = NOW() WHERE id = ? AND technician_id = ?");
            $stmt->bind_param("ii", $request_id, $technician_id);
            $stmt->execute();
            
            // Update device_submissions status to reflect repair in progress
            $mysqli->query("UPDATE device_submissions SET status = 'repairing' WHERE id = {$repair['submission_id']}");
        }
        
        header('Location: technician.php?section=repairs');
        exit;
        
    } elseif ($action === 'end_repair') {
        $request_id = intval($_POST['request_id']);

        // Get submission_id from repair request
        $repair = $mysqli->query("SELECT submission_id FROM repair_requests WHERE id = $request_id AND technician_id = $technician_id")->fetch_assoc();

        $stmt = $mysqli->prepare("UPDATE repair_requests SET status = 'completed', repair_ended_at = NOW() WHERE id = ? AND technician_id = ?");
        $stmt->bind_param("ii", $request_id, $technician_id);
        $stmt->execute();

        // Update device_submissions status to completed
        if ($repair) {
            $mysqli->query("UPDATE device_submissions SET status = 'completed' WHERE id = {$repair['submission_id']}");
        }

        header('Location: technician.php?section=repairs');
        exit;
        
    } elseif ($action === 'disassemble') {
        $request_id = intval($_POST['request_id']);
        $reason = trim($_POST['disassembly_reason'] ?? $_POST['parts_list'] ?? 'Technician requested disassembly permission.');

        $stmt = $mysqli->prepare("SELECT * FROM repair_requests WHERE id = ? AND technician_id = ?");
        $stmt->bind_param("ii", $request_id, $technician_id);
        $stmt->execute();
        $repair = $stmt->get_result()->fetch_assoc();

        if ($repair) {
            $existing = null;
            $check = $mysqli->prepare("SELECT id FROM disassembly_requests WHERE submission_id = ? AND technician_id = ? AND status IN ('pending','accepted','in_progress') ORDER BY id DESC LIMIT 1");
            $check->bind_param("ii", $repair['submission_id'], $technician_id);
            $check->execute();
            $existing = $check->get_result()->fetch_assoc();

            if ($existing) {
                $disassembly_request_id = intval($existing['id']);
            } else {
                $insert = $mysqli->prepare("INSERT INTO disassembly_requests (submission_id, technician_id, status, technician_notes, created_at) VALUES (?, ?, 'pending', ?, NOW())");
                $insert->bind_param("iis", $repair['submission_id'], $technician_id, $reason);
                $insert->execute();
                $disassembly_request_id = $mysqli->insert_id;
            }

            $update = $mysqli->prepare("UPDATE repair_requests SET status = 'pending_disassembly', disassembly_request_id = ? WHERE id = ? AND technician_id = ?");
            $update->bind_param("iii", $disassembly_request_id, $request_id, $technician_id);
            $update->execute();
            $mysqli->query("UPDATE device_submissions SET status = 'pending_disassembly' WHERE id = " . intval($repair['submission_id']));

            $notification = $mysqli->real_escape_string("Technician requested permission to disassemble repair #$request_id. Reason: $reason");
            $mysqli->query("INSERT INTO notifications (user_id, message, type, created_at) SELECT id, '$notification', 'disassembly_permission', NOW() FROM users WHERE LOWER(role) = 'clerk'");
        }

        header('Location: technician.php?section=disassembly');
        exit;
        
    } elseif ($action === 'request_parts') {
        $request_id = intval($_POST['request_id']);
        $parts_needed = $_POST['parts_needed'] ?? '';
        
        if (!trim($parts_needed)) {
            header('Location: technician.php?section=repairs');
            exit;
        }
        
        // Generate a 4-digit PIN for the parts request
        try {
            $parts_pin = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        } catch (Exception $e) {
            $parts_pin = sprintf('%04d', rand(0, 9999));
        }
        
        // Create parts request in parts_requests table
        $insert = $mysqli->prepare("INSERT INTO parts_requests (repair_request_id, technician_id, parts_needed, pin, status) VALUES (?, ?, ?, ?, 'pending')");
        $insert->bind_param("iiss", $request_id, $technician_id, $parts_needed, $parts_pin);
        $insert->execute();
        
        // Update repair_requests to show parts requested
        $stmt = $mysqli->prepare("UPDATE repair_requests SET parts_request_status = 'pending', parts_request_pin = ?, parts_requested = ? WHERE id = ?");
        $stmt->bind_param("ssi", $parts_pin, $parts_needed, $request_id);
        $stmt->execute();
        
        // Create notification for clerk
        $clerk_notification = "Technician requested parts for repair #$request_id (PIN: $parts_pin): $parts_needed";
        $mysqli->query("INSERT INTO notifications (user_id, message, type, created_at) SELECT id, '$clerk_notification', 'parts_request', NOW() FROM users WHERE role = 'clerk'");
        
        header('Location: technician.php?section=repairs');
        exit;
        
    } elseif ($action === 'confirm_pickup') {
        $request_id = intval($_POST['request_id']);
        $code = $_POST['pickup_code'] ?? '';
        
        $stmt = $mysqli->prepare("SELECT pickup_code FROM repair_requests WHERE id = ? AND technician_id = ?");
        $stmt->bind_param("ii", $request_id, $technician_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $repair = $result->fetch_assoc();
        
        if ($repair && strtoupper($repair['pickup_code']) === strtoupper($code)) {
            $update = $mysqli->prepare("UPDATE repair_requests SET status = 'repairing', pickup_confirmed_at = NOW() WHERE id = ?");
            $update->bind_param("i", $request_id);
            $update->execute();
            
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Invalid pickup code']);
        }
        exit;
        
    } elseif ($action === 'declare_unrepairable') {
        $request_id = intval($_POST['request_id']);
        $reason = $_POST['unrepairable_reason'] ?? '';
        
        $stmt = $mysqli->prepare("UPDATE repair_requests SET status = 'unrepairable', unrepairable_reason = ? WHERE id = ? AND technician_id = ?");
        $stmt->bind_param("sii", $reason, $request_id, $technician_id);
        $stmt->execute();
        
        header('Location: technician.php?section=repairs');
        exit;
        
    } elseif ($action === 'complete_repair') {
        $request_id = intval($_POST['request_id']);
        $brand = $_POST['brand'] ?? '';
        $name = $_POST['name'] ?? '';
        $category = $_POST['category'] ?? '';
        $condition = $_POST['condition'] ?? '';
        $specs = $_POST['specs'] ?? '';
        $accessories = $_POST['accessories'] ?? '';
        $price = floatval($_POST['price'] ?? 0);
        $repairs_done = $_POST['repairs_done'] ?? '';

        // Get repair request details
        $stmt = $mysqli->prepare("SELECT * FROM repair_requests WHERE id = ? AND technician_id = ?");
        $stmt->bind_param("ii", $request_id, $technician_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $repair = $result->fetch_assoc();

        if ($repair) {
            // Create technician listing
            $insert = $mysqli->prepare("INSERT INTO technician_listings (repair_request_id, technician_id, brand, name, category, `condition`, specs, accessories, price, repairs_done, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending_review')");
            $insert->bind_param("iissssssds", $request_id, $technician_id, $brand, $name, $category, $condition, $specs, $accessories, $price, $repairs_done);
            $insert->execute();

            // Update repair request status
            $update = $mysqli->prepare("UPDATE repair_requests SET status = 'completed', repair_completed_at = NOW() WHERE id = ?");
            $update->bind_param("i", $request_id);
            $update->execute();

            // Update device_submissions status to completed
            $mysqli->query("UPDATE device_submissions SET status = 'completed' WHERE id = {$repair['submission_id']}");

            header('Location: technician.php?section=listings');
            exit;
        }
    }
}

// Get all repair requests for this technician
$all_repairs = $mysqli->query("
    SELECT rr.id AS request_id, rr.*, ds.device_type, ds.brand, ds.model, ds.estimated_price, u.username as seller_name
    FROM repair_requests rr
    JOIN device_submissions ds ON rr.submission_id = ds.id
    JOIN users u ON ds.user_id = u.id
    WHERE rr.technician_id = $technician_id
    ORDER BY rr.created_at DESC
");
?>

<div class="content-section">
    <h2>All Repair Requests</h2>
    <?php if ($all_repairs && $all_repairs->num_rows > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Device</th>
                    <th>Brand</th>
                    <th>Model</th>
                    <th>Seller</th>
                    <th>Status</th>
                    <th>Pickup Code</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($repair = $all_repairs->fetch_assoc()): ?>
                    <tr>
                        <td>#<?php echo $repair['request_id']; ?></td>
                        <td><?php echo htmlspecialchars($repair['device_type']); ?></td>
                        <td><?php echo htmlspecialchars($repair['brand']); ?></td>
                        <td><?php echo htmlspecialchars($repair['model']); ?></td>
                        <td><?php echo htmlspecialchars($repair['seller_name']); ?></td>
                        <td><span class="status-badge status-<?php echo $repair['status']; ?>"><?php echo ucfirst(str_replace('_', ' ', $repair['status'])); ?></span></td>
                        <td><?php echo $repair['pickup_code'] ? htmlspecialchars($repair['pickup_code']) : 'N/A'; ?></td>
                        <td><?php echo date('M d, Y', strtotime($repair['created_at'])); ?></td>
                        <td>
                            <button type="button" class="btn btn-primary btn-sm" onclick="viewRepairRequest(<?php echo $repair['request_id']; ?>)">View</button>
                            <?php if ($repair['status'] == 'completed'): ?>
                        <a href="technician.php?section=create_listing&repair_id=<?php echo $repair['request_id']; ?>"
                            class="btn btn-success btn-sm">
                            Create Listing
                        </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>No repair requests found.</p>
    <?php endif; ?>
</div>

<!-- View Repair Request Modal -->
<div id="viewRepairModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Repair Request Details</h3>
            <button class="close-modal" onclick="closeModal('viewRepairModal')">&times;</button>
        </div>
        <div id="repairRequestDetails"></div>
    </div>
</div>

<!-- Complete Repair Modal -->
<div id="completeRepairModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Complete Repair & List for Sale</h3>
            <button class="close-modal" onclick="closeModal('completeRepairModal')">&times;</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="action" value="complete_repair">
            <input type="hidden" name="request_id" id="completeRepairRequestId">
            
            <div class="form-group">
                <label for="brand">Brand</label>
                <input type="text" id="brand" name="brand" required>
            </div>
            
            <div class="form-group">
                <label for="name">Product Name</label>
                <input type="text" id="name" name="name" required>
            </div>
            
            <div class="form-group">
                <label for="category">Category</label>
                <select id="category" name="category" required>
                    <option value="">Select Category</option>
                    <option value="Phone">Phone</option>
                    <option value="Laptop">Laptop</option>
                    <option value="Tablet">Tablet</option>
                    <option value="Watch">Watch</option>
                    <option value="Other">Other</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="condition">Condition</label>
                <select id="condition" name="condition" required>
                    <option value="">Select Condition</option>
                    <option value="Excellent">Excellent</option>
                    <option value="Like New">Like New</option>
                    <option value="Good">Good</option>
                    <option value="Fair">Fair</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="specs">Specifications (comma-separated)</label>
                <textarea id="specs" name="specs" rows="3" placeholder="e.g., CPU: Snapdragon 888, RAM: 8GB, Storage: 256GB"></textarea>
            </div>
            
            <div class="form-group">
                <label for="accessories">Accessories (comma-separated)</label>
                <textarea id="accessories" name="accessories" rows="2" placeholder="e.g., Charger, Case, Screen Protector"></textarea>
            </div>
            
            <div class="form-group">
                <label for="price">Price (RM)</label>
                <input type="number" id="price" name="price" step="0.01" min="0" required>
            </div>
            
            <div class="form-group">
                <label for="repairs_done">Repairs Performed</label>
                <textarea id="repairs_done" name="repairs_done" rows="3" required placeholder="Describe what repairs were done to the device"></textarea>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('completeRepairModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Submit for Review</button>
            </div>
        </form>
    </div>
</div>

<script>
function viewRepairRequest(requestId) {
    fetch('get_repair_request_details.php?id=' + requestId, { credentials: 'same-origin' })
        .then(async response => {
            const text = await response.text();
            const contentType = response.headers.get('content-type') || '';

            if (!response.ok) {
                throw new Error('Server error ' + response.status + ': ' + text.trim().slice(0, 200));
            }
            if (!contentType.includes('application/json')) {
                throw new Error('Invalid JSON response: ' + text.trim().slice(0, 200));
            }

            try {
                return JSON.parse(text);
            } catch (error) {
                throw new Error('JSON parse error: ' + error.message + ' - ' + text.trim().slice(0, 200));
            }
        })
        .then(data => {
            if (data.error) {
                document.getElementById('repairRequestDetails').innerHTML = '<p>Error: ' + data.error + '</p>';
            } else {
                let html = '<div class="details-container">';
                html += '<div class="details-grid">';
                html += '<div class="detail-item"><strong>Device Type:</strong> ' + data.device_type + '</div>';
                html += '<div class="detail-item"><strong>Brand:</strong> ' + data.brand + '</div>';
                html += '<div class="detail-item"><strong>Model:</strong> ' + data.model + '</div>';
                html += '<div class="detail-item"><strong>Condition:</strong> ' + data.device_condition + '</div>';
                html += '<div class="detail-item"><strong>Storage:</strong> ' + (data.storage || 'N/A') + 'GB</div>';
                html += '<div class="detail-item"><strong>RAM:</strong> ' + (data.ram || 'N/A') + 'GB</div>';
                html += '<div class="detail-item"><strong>Battery Health:</strong> ' + (data.battery_health || 'N/A') + '%</div>';
                html += '<div class="detail-item"><strong>Screen Condition:</strong> ' + (data.screen_condition || 'N/A') + '</div>';
                html += '<div class="detail-item"><strong>Est. Price:</strong> RM' + (data.estimated_price || '0') + '</div>';
                html += '<div class="detail-item"><strong>Seller:</strong> ' + data.seller_name + '</div>';
                html += '<div class="detail-item"><strong>Submitted:</strong> ' + data.submitted_at + '</div>';
                html += '</div>';
                
                if (data.issues) {
                    html += '<div class="detail-item" style="grid-column: 1 / -1;"><strong>Issues:</strong> ' + data.issues + '</div>';
                }
                
                if (data.accessories) {
                    html += '<div class="detail-item" style="grid-column: 1 / -1;"><strong>Accessories:</strong> ' + data.accessories + '</div>';
                }
                
                if (data.description) {
                    html += '<div class="detail-item" style="grid-column: 1 / -1;"><strong>Description:</strong> ' + data.description + '</div>';
                }
                
                html += '</div>';
                
                html += '<div class="action-buttons" style="margin-top: 20px;">';
                html += '<form method="POST" style="display: inline;">';
                html += '<input type="hidden" name="action" value="accept">';
                html += '<input type="hidden" name="request_id" value="' + requestId + '">';
                html += '<button type="submit" class="btn btn-success">Accept</button>';
                html += '</form>';
                html += '<button type="button" class="btn btn-danger" onclick="showRejectForm(' + requestId + ')">Reject</button>';
                html += '</div>';
                
                html += '<div id="rejectForm" style="display: none; margin-top: 15px;">';
                html += '<form method="POST">';
                html += '<input type="hidden" name="action" value="reject">';
                html += '<input type="hidden" name="request_id" value="' + requestId + '">';
                html += '<div class="form-group">';
                html += '<label>Rejection Reason:</label>';
                html += '<textarea name="rejection_reason" rows="3" required placeholder="Please explain why you are rejecting this repair request"></textarea>';
                html += '</div>';
                html += '<button type="submit" class="btn btn-danger">Submit Rejection</button>';
                html += '<button type="button" class="btn btn-secondary" onclick="hideRejectForm()">Cancel</button>';
                html += '</form>';
                html += '</div>';
                
                document.getElementById('repairRequestDetails').innerHTML = html;
            }
            openModal('viewRepairModal');
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('repairRequestDetails').innerHTML = '<p>Error loading repair request details.</p>';
        });
}

function showRejectForm(requestId) {
    document.getElementById('rejectForm').style.display = 'block';
}

function hideRejectForm() {
    document.getElementById('rejectForm').style.display = 'none';
}

function openModal(modalId) {
    document.getElementById(modalId).style.display = 'block';
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

function submitDisassembly() {
    const partsList = document.getElementById('partsList').value;
    if (!partsList.trim()) {
        alert('Please list the parts you have removed.');
        return;
    }
    
    const requestId = document.getElementById('disassembleRequestId').value;
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'technician_repairs.php';
    form.innerHTML = '<input type="hidden" name="action" value="disassemble"><input type="hidden" name="request_id" value="' + requestId + '"><input type="hidden" name="parts_list" value="' + encodeURIComponent(partsList) + '">';
    document.body.appendChild(form);
    form.submit();
}
</script>

<!-- Disassemble Modal -->
<div id="disassembleModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Disassemble Device</h3>
            <button class="close-modal" onclick="closeModal('disassembleModal')">&times;</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="action" value="disassemble">
            <input type="hidden" name="request_id" id="disassembleRequestId">
            
            <div class="form-group">
                <label for="partsList">Parts Removed (one per line)</label>
                <textarea id="partsList" name="parts_list" rows="8" required placeholder="List each part on a new line. You can specify condition using format: Part Name - condition&#10;&#10;Example:&#10;Screen - working&#10;Battery - damaged&#10;Motherboard - working&#10;Camera - working"></textarea>
                <small style="color: #666; margin-top: 5px; display: block;">Available conditions: working, damaged, unusable</small>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('disassembleModal')">Cancel</button>
                <button type="button" class="btn btn-danger" onclick="submitDisassembly()">Submit Disassembly</button>
            </div>
        </form>
    </div>
</div>
