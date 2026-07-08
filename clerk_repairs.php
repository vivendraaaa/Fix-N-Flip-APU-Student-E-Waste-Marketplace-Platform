<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$mysqli = new mysqli("localhost", "root", "", "fyp");

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in'] || strtolower($_SESSION['role'] ?? '') !== 'clerk') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }

    header('Location: Login.php');
    exit;
}

function ensurePartsRequestColumn($mysqli, $column, $definition) {
    $check = $mysqli->query("SHOW COLUMNS FROM parts_requests LIKE '" . $mysqli->real_escape_string($column) . "'");
    if (!$check || $check->num_rows === 0) {
        $mysqli->query("ALTER TABLE parts_requests ADD COLUMN " . $definition);
    }
}

ensurePartsRequestColumn($mysqli, 'location', 'location VARCHAR(255) NULL');
ensurePartsRequestColumn($mysqli, 'shelf', 'shelf VARCHAR(50) NULL');

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_location') {
        $product_id = intval($_POST['product_id']);
        $product_type = $_POST['product_type'];
        $location = $_POST['location'];
        $shelf = $_POST['shelf'];
        $clerk_id = $_SESSION['user_id'];
        
        // Check if location exists
        $check = $mysqli->query("SELECT id FROM product_locations WHERE product_id = $product_id AND product_type = '$product_type'");
        if ($check && $check->num_rows > 0) {
            $update = $mysqli->prepare("UPDATE product_locations SET location = ?, shelf = ?, updated_at = NOW() WHERE product_id = ? AND product_type = ?");
            $update->bind_param("ssis", $location, $shelf, $product_id, $product_type);
            $update->execute();
        } else {
            $insert = $mysqli->prepare("INSERT INTO product_locations (product_id, product_type, location, shelf, added_by) VALUES (?, ?, ?, ?, ?)");
            $insert->bind_param("isssi", $product_id, $product_type, $location, $shelf, $clerk_id);
            $insert->execute();
        }
        
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        exit;
        
    } elseif ($action === 'ping_technician') {
        $repair_id = intval($_POST['repair_id']);
        $message = $_POST['message'];
        $clerk_id = $_SESSION['user_id'];
        
        // Get technician ID from repair request
        $repair = $mysqli->query("SELECT technician_id FROM repair_requests WHERE id = $repair_id")->fetch_assoc();
        
        if ($repair) {
            $insert = $mysqli->prepare("INSERT INTO chat_messages (sender_id, receiver_id, message) VALUES (?, ?, ?)");
            $insert->bind_param("iis", $clerk_id, $repair['technician_id'], $message);
            $insert->execute();
            
            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
        } else {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Repair not found']);
        }
        exit;
        
    } elseif ($action === 'approve_parts') {
        $repair_id = intval($_POST['repair_id']);
        $location = $_POST['location'] ?? '';
        $shelf = $_POST['shelf'] ?? '';
        
        $parts_request = $mysqli->query("SELECT id, technician_id FROM parts_requests WHERE repair_request_id = $repair_id ORDER BY id DESC LIMIT 1")->fetch_assoc();

        if ($parts_request) {
            $update = $mysqli->prepare("UPDATE parts_requests SET status = 'approved', location = ?, shelf = ? WHERE id = ?");
            $update->bind_param("ssi", $location, $shelf, $parts_request['id']);
            $update->execute();

            // Notify technician
            $notification = "Your parts request for repair #$repair_id has been approved and is ready for pickup at $location / $shelf.";
            $mysqli->query("INSERT INTO notifications (user_id, message, type, created_at) VALUES ({$parts_request['technician_id']}, '$notification', 'parts_approved', NOW())");
        }
        
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        exit;
        
    } elseif ($action === 'reject_parts') {
        $repair_id = intval($_POST['repair_id']);
        
        $parts_request = $mysqli->query("SELECT id, technician_id FROM parts_requests WHERE repair_request_id = $repair_id ORDER BY id DESC LIMIT 1")->fetch_assoc();

        if ($parts_request) {
            $update = $mysqli->prepare("UPDATE parts_requests SET status = 'rejected' WHERE id = ?");
            $update->bind_param("i", $parts_request['id']);
            $update->execute();

            // Notify technician
            $notification = "Your parts request for repair #$repair_id has been rejected.";
            $mysqli->query("INSERT INTO notifications (user_id, message, type, created_at) VALUES ({$parts_request['technician_id']}, '$notification', 'parts_rejected', NOW())");
        }
        
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        exit;
    }
}

// Handle view specific repair
$view_id = isset($_GET['view']) ? intval($_GET['view']) : 0;

if ($view_id > 0) {
    $repair = $mysqli->query("
        SELECT rr.*, ds.device_type, ds.brand, ds.model, ds.storage, ds.ram, ds.battery_health, ds.device_condition, ds.accessories, ds.description, 
               u.username as technician_name, u.email as technician_email, u.phone as technician_phone, pl.location, pl.shelf,
               pr.id AS parts_request_id, pr.parts_needed AS parts_requested, pr.pin AS parts_request_pin, pr.status AS parts_request_status, pr.location AS parts_location, pr.shelf AS parts_shelf
        FROM repair_requests rr
        JOIN device_submissions ds ON rr.submission_id = ds.id
        JOIN users u ON rr.technician_id = u.id
        LEFT JOIN product_locations pl ON pl.product_id = rr.id AND pl.product_type = 'repair'
        LEFT JOIN parts_requests pr ON pr.id = (
            SELECT pr2.id
            FROM parts_requests pr2
            WHERE pr2.repair_request_id = rr.id
            ORDER BY pr2.id DESC
            LIMIT 1
        )
        WHERE rr.id = $view_id
    ")->fetch_assoc();
    
    if (!$repair) {
        echo '<p>Repair request not found.</p>';
        exit;
    }
    ?>
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
        
        .action-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 20px;
        }
    </style>
    
    <div class="details-container">
        <a href="clerk.php?section=repairs" class="back-link">&larr; Back to Repairs</a>
        
        <div class="details-card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
                <h2>Repair Request #<?php echo $repair['id']; ?></h2>
                <span class="status-badge status-<?php echo str_replace('_', '-', $repair['status']); ?>">
                    <?php echo ucfirst(str_replace('_', ' ', $repair['status'])); ?>
                </span>
            </div>
            
            <div class="details-grid">
                <div class="detail-item">
                    <strong>Device Type</strong>
                    <span><?php echo htmlspecialchars($repair['device_type']); ?></span>
                </div>
                <div class="detail-item">
                    <strong>Brand</strong>
                    <span><?php echo htmlspecialchars($repair['brand']); ?></span>
                </div>
                <div class="detail-item">
                    <strong>Model</strong>
                    <span><?php echo htmlspecialchars($repair['model']); ?></span>
                </div>
                <div class="detail-item">
                    <strong>Technician</strong>
                    <span><?php echo htmlspecialchars($repair['technician_name']); ?></span>
                </div>
                <div class="detail-item">
                    <strong>Technician Email</strong>
                    <span><?php echo htmlspecialchars($repair['technician_email']); ?></span>
                </div>
                <div class="detail-item">
                    <strong>Technician Phone</strong>
                    <span><?php echo htmlspecialchars($repair['technician_phone'] ?? 'Not provided'); ?></span>
                </div>
                <div class="detail-item">
                    <strong>Storage</strong>
                    <span><?php echo htmlspecialchars($repair['storage'] ?? 'N/A'); ?></span>
                </div>
                <div class="detail-item">
                    <strong>RAM</strong>
                    <span><?php echo htmlspecialchars($repair['ram'] ?? 'N/A'); ?></span>
                </div>
                <div class="detail-item">
                    <strong>Battery Health</strong>
                    <span><?php echo htmlspecialchars($repair['battery_health'] ?? 'N/A'); ?>%</span>
                </div>
                <div class="detail-item">
                    <strong>Device Condition</strong>
                    <span><?php echo htmlspecialchars($repair['device_condition'] ?? 'N/A'); ?></span>
                </div>
                <div class="detail-item">
                    <strong>Current Location</strong>
                    <span><?php echo htmlspecialchars($repair['location'] ?? 'Not set'); ?></span>
                </div>
                <div class="detail-item">
                    <strong>Shelf</strong>
                    <span><?php echo htmlspecialchars($repair['shelf'] ?? 'Not set'); ?></span>
                </div>
                <div class="detail-item">
                    <strong>Est. Completion</strong>
                    <span><?php echo ($repair['estimated_completion'] ?? '') ? date('M d, Y H:i', strtotime($repair['estimated_completion'])) : 'N/A'; ?></span>
                </div>
                <div class="detail-item">
                    <strong>Submitted</strong>
                    <span><?php echo date('M d, Y H:i', strtotime($repair['created_at'])); ?></span>
                </div>
            </div>
            
            <div class="detail-item" style="margin-top: 20px;">
                <strong>Description</strong>
                <span><?php echo nl2br(htmlspecialchars($repair['description'] ?? 'Not provided')); ?></span>
            </div>
            
            <div class="detail-item" style="margin-top: 20px;">
                <strong>Accessories</strong>
                <span><?php echo nl2br(htmlspecialchars($repair['accessories'] ?? 'None')); ?></span>
            </div>
            
            <?php if ($repair['parts_requested'] ?? false): ?>
                <div class="detail-item" style="margin-top: 20px; border-left-color: #ffc107;">
                    <strong>Parts Requested</strong>
                    <span><?php echo nl2br(htmlspecialchars($repair['parts_requested'])); ?></span>
                </div>
                <div class="detail-item" style="margin-top: 20px; border-left-color: #ffc107;">
                    <strong>Parts Request Status</strong>
                    <span class="status-badge status-<?php echo $repair['parts_request_status'] ?? 'pending'; ?>">
                        <?php echo ucfirst($repair['parts_request_status'] ?? 'pending'); ?>
                    </span>
                </div>
                <?php if ($repair['parts_request_pin'] ?? ''): ?>
                    <div class="detail-item" style="margin-top: 20px; border-left-color: #ffc107;">
                        <strong>Pickup PIN</strong>
                        <span style="font-size: 24px; font-weight: bold; letter-spacing: 4px;"><?php echo htmlspecialchars($repair['parts_request_pin']); ?></span>
                    </div>
                <?php endif; ?>
                <?php if (!empty($repair['parts_location'])): ?>
                    <div class="detail-item" style="margin-top: 20px; border-left-color: #ffc107;">
                        <strong>Parts Location</strong>
                        <span><?php echo htmlspecialchars($repair['parts_location']); ?>
                        <?php if (!empty($repair['parts_shelf'])): ?>
                            / <?php echo htmlspecialchars($repair['parts_shelf']); ?>
                        <?php endif; ?>
                        </span>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
            
            <div class="action-buttons">
                <?php if (($repair['parts_request_id'] ?? false) && ($repair['parts_request_status'] ?? 'pending') === 'pending'): ?>
                    <button class="btn btn-success" onclick="approvePartsRequest(<?php echo $repair['id']; ?>)">Approve Parts</button>
                    <button class="btn btn-danger" onclick="rejectPartsRequest(<?php echo $repair['id']; ?>)">Reject Parts</button>
                <?php endif; ?>
                <?php if ($repair['status'] === 'repairing' && !empty($repair['estimated_completion']) && strtotime($repair['estimated_completion']) < time()): ?>
                    <button class="btn btn-warning" onclick="pingTechnician(<?php echo $repair['id']; ?>)">Ping Technician</button>
                <?php endif; ?>
            </div>
            
            <button class="btn btn-secondary" style="margin-top: 15px;" onclick="window.location.href='clerk.php?section=repairs'">Back to List</button>
        </div>
    </div>
    
    <!-- Approve Parts Modal -->
    <div id="approvePartsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Approve Parts Request</h3>
                <button class="close-modal" onclick="closeModal('approvePartsModal')">&times;</button>
            </div>
            <form method="POST" id="approvePartsForm">
                <input type="hidden" name="action" value="approve_parts">
                <input type="hidden" name="repair_id" id="approvePartsRepairId">
                
                <div class="form-group">
                    <label for="approveLocation">Location *</label>
                    <input type="text" id="approveLocation" name="location" placeholder="e.g., Warehouse A" required>
                </div>
                
                <div class="form-group">
                    <label for="approveShelf">Shelf *</label>
                    <input type="text" id="approveShelf" name="shelf" placeholder="e.g., 8A" required>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('approvePartsModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Approve</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Reject Parts Modal -->
    <div id="rejectPartsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Reject Parts Request</h3>
                <button class="close-modal" onclick="closeModal('rejectPartsModal')">&times;</button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="reject_parts">
                <input type="hidden" name="repair_id" id="rejectPartsRepairId">
                <p>Are you sure you want to reject this parts request?</p>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('rejectPartsModal')">Cancel</button>
                    <button type="submit" class="btn btn-danger">Reject</button>
                </div>
            </form>
        </div>
    </div>

    <script src="clerk.js"></script>
    <script>
    function approvePartsRequest(repairId) {
        document.getElementById('approvePartsRepairId').value = repairId;
        openModal('approvePartsModal');
    }

    function rejectPartsRequest(repairId) {
        document.getElementById('rejectPartsRepairId').value = repairId;
        openModal('rejectPartsModal');
    }

    // Handle approve parts form submission via AJAX
    document.getElementById('approvePartsForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        
        fetch('clerk_repairs.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Parts approved successfully!');
                closeModal('approvePartsModal');
                location.reload();
            } else {
                alert('Error approving parts');
            }
        });
    });
    </script>
    
    <?php
    exit;
}

// Get all repair requests with search
$search = isset($_GET['search']) ? $mysqli->real_escape_string($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $mysqli->real_escape_string($_GET['status']) : '';

$where = "WHERE 1=1";
if ($search) {
    $where .= " AND (rr.id LIKE '%$search%' OR ds.brand LIKE '%$search%' OR ds.model LIKE '%$search%' OR u.username LIKE '%$search%')";
}
if ($status_filter) {
    $where .= " AND rr.status = '$status_filter'";
}

$repairs = $mysqli->query("
    SELECT rr.*, ds.device_type, ds.brand, ds.model, u.username as technician_name, u.phone as technician_phone, pl.location, pl.shelf,
           pr.id AS parts_request_id, pr.parts_needed AS parts_requested, pr.pin AS parts_request_pin, pr.status AS parts_request_status, pr.location AS parts_location, pr.shelf AS parts_shelf
    FROM repair_requests rr
    JOIN device_submissions ds ON rr.submission_id = ds.id
    JOIN users u ON rr.technician_id = u.id
    LEFT JOIN product_locations pl ON pl.product_id = rr.id AND pl.product_type = 'repair'
    LEFT JOIN parts_requests pr ON pr.id = (
        SELECT pr2.id
        FROM parts_requests pr2
        WHERE pr2.repair_request_id = rr.id
        ORDER BY pr2.id DESC
        LIMIT 1
    )
    $where
    ORDER BY rr.created_at DESC
");
?>

<div class="content-section">
    <h2>Repairs Tracking</h2>
    
    <div class="search-bar">
        <input type="text" id="searchInput" placeholder="Search by ID, device, brand, model, or technician..." value="<?php echo htmlspecialchars($search); ?>">
        <select id="statusFilter">
            <option value="">All Status</option>
            <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
            <option value="accepted" <?php echo $status_filter === 'accepted' ? 'selected' : ''; ?>>Accepted</option>
            <option value="picked_up" <?php echo $status_filter === 'picked_up' ? 'selected' : ''; ?>>Picked Up</option>
            <option value="repairing" <?php echo $status_filter === 'repairing' ? 'selected' : ''; ?>>Repairing</option>
            <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
            <option value="unrepairable" <?php echo $status_filter === 'unrepairable' ? 'selected' : ''; ?>>Unrepairable</option>
        </select>
        <button class="btn btn-primary" onclick="applyFilters()">Search</button>
    </div>
    
    <?php if ($repairs && $repairs->num_rows > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Device</th>
                    <th>Brand</th>
                    <th>Model</th>
                    <th>Technician</th>
                    <th>Status</th>
                    <th>Parts Request</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($repair = $repairs->fetch_assoc()): ?>
                    <tr>
                        <td>#<?php echo $repair['id']; ?></td>
                        <td><?php echo htmlspecialchars($repair['device_type']); ?></td>
                        <td><?php echo htmlspecialchars($repair['brand']); ?></td>
                        <td><?php echo htmlspecialchars($repair['model']); ?></td>
                        <td><?php echo htmlspecialchars($repair['technician_name']); ?></td>
                        <td><span class="status-badge status-<?php echo str_replace('_', '-', $repair['status']); ?>"><?php echo ucfirst(str_replace('_', ' ', $repair['status'])); ?></span></td>
                        <td>
                            <?php if ($repair['parts_requested'] ?? false): ?>
                                <span class="status-badge status-<?php echo $repair['parts_request_status'] ?? 'pending'; ?>">
                                    <?php echo ucfirst($repair['parts_request_status'] ?? 'pending'); ?>
                                </span>
                            <?php else: ?>
                                N/A
                            <?php endif; ?>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-primary" onclick="window.location.href='clerk.php?section=repairs&view=<?php echo $repair['id']; ?>'">View</button>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>No repair requests found.</p>
    <?php endif; ?>
</div>

<script>
function applyFilters() {
    const search = document.getElementById('searchInput').value;
    const status = document.getElementById('statusFilter').value;
    window.location.href = 'clerk.php?section=repairs&search=' + encodeURIComponent(search) + '&status=' + encodeURIComponent(status);
}
</script>
