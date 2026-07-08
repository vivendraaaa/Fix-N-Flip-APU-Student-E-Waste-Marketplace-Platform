<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$mysqli = new mysqli("localhost", "root", "", "fyp");

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}


function ensureTableColumn($mysqli, $table, $column, $definition) {
    $tableSafe = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    $check = $mysqli->query("SHOW COLUMNS FROM `$tableSafe` LIKE '" . $mysqli->real_escape_string($column) . "'");
    if (!$check || $check->num_rows === 0) {
        $mysqli->query("ALTER TABLE `$tableSafe` ADD COLUMN " . $definition);
    }
}

function tableHasColumn($mysqli, $table, $column) {
    $tableSafe = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    $check = $mysqli->query("SHOW COLUMNS FROM `$tableSafe` LIKE '" . $mysqli->real_escape_string($column) . "'");
    return $check && $check->num_rows > 0;
}

ensureTableColumn($mysqli, 'disassembly_requests', 'pickup_code', 'pickup_code VARCHAR(8) NULL');
ensureTableColumn($mysqli, 'disassembly_requests', 'pickup_confirmed_at', 'pickup_confirmed_at DATETIME NULL');
ensureTableColumn($mysqli, 'disassembly_requests', 'completed_at', 'completed_at DATETIME NULL');
ensureTableColumn($mysqli, 'disassembly_requests', 'rejection_reason', 'rejection_reason TEXT NULL');
ensureTableColumn($mysqli, 'disassembly_requests', 'technician_notes', 'technician_notes TEXT NULL');
ensureTableColumn($mysqli, 'parts_inventory', 'device_model', 'device_model VARCHAR(255) NULL');
ensureTableColumn($mysqli, 'parts_inventory', 'device_specs', 'device_specs TEXT NULL');
ensureTableColumn($mysqli, 'parts_inventory', 'part_name', 'part_name VARCHAR(255) NULL');
ensureTableColumn($mysqli, 'parts_inventory', 'quantity', 'quantity INT DEFAULT 1');
ensureTableColumn($mysqli, 'parts_inventory', 'location', "location VARCHAR(255) DEFAULT 'Pending Clerk Location'");
ensureTableColumn($mysqli, 'parts_inventory', 'shelf', 'shelf VARCHAR(100) NULL');
ensureTableColumn($mysqli, 'parts_inventory', 'source_type', 'source_type VARCHAR(50) NULL');
ensureTableColumn($mysqli, 'parts_inventory', 'source_id', 'source_id INT NULL');
ensureTableColumn($mysqli, 'repair_requests', 'disassembly_request_id', 'disassembly_request_id INT NULL');

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
        
        $stmt = $mysqli->prepare("UPDATE disassembly_requests SET status = 'accepted', pickup_code = ? WHERE id = ? AND technician_id = ?");
        $stmt->bind_param("sii", $pickup_code, $request_id, $technician_id);
        $stmt->execute();
        
        header('Location: technician.php?section=disassembly');
        exit;
        
    } elseif ($action === 'reject') {
        $request_id = intval($_POST['request_id']);
        $reason = $_POST['rejection_reason'] ?? '';
        
        $stmt = $mysqli->prepare("UPDATE disassembly_requests SET status = 'rejected', rejection_reason = ? WHERE id = ? AND technician_id = ?");
        $stmt->bind_param("sii", $reason, $request_id, $technician_id);
        $stmt->execute();
        
        header('Location: technician.php?section=disassembly');
        exit;
        
    } elseif ($action === 'confirm_pickup') {
        $request_id = intval($_POST['request_id']);
        $code = $_POST['pickup_code'] ?? '';
        
        $stmt = $mysqli->prepare("SELECT pickup_code FROM disassembly_requests WHERE id = ? AND technician_id = ?");
        $stmt->bind_param("ii", $request_id, $technician_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $disassembly = $result->fetch_assoc();
        
        if ($disassembly && strtoupper($disassembly['pickup_code']) === strtoupper($code)) {
            $update = $mysqli->prepare("UPDATE disassembly_requests SET status = 'in_progress', pickup_confirmed_at = NOW() WHERE id = ?");
            $update->bind_param("i", $request_id);
            $update->execute();
            
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Invalid pickup code']);
        }
        exit;
        
} elseif ($action === 'start_disassembly') {
    $request_id = intval($_POST['request_id']);

    $stmt = $mysqli->prepare("UPDATE disassembly_requests SET status = 'in_progress' WHERE id = ? AND technician_id = ?");
    $stmt->bind_param("ii", $request_id, $technician_id);
    $stmt->execute();

    header('Location: technician.php?section=disassembly');
    exit;
        }
    }

// Get all disassembly requests for this technician
$all_disassembly = $mysqli->query("
    SELECT dr.*, ds.device_type, ds.brand, ds.model, u.username as seller_name
    FROM disassembly_requests dr
    JOIN device_submissions ds ON dr.submission_id = ds.id
    JOIN users u ON ds.user_id = u.id
    WHERE dr.technician_id = $technician_id
    ORDER BY dr.created_at DESC
");

// Get pending requests
$pending_requests = $mysqli->query("
    SELECT dr.*, ds.device_type, ds.brand, ds.model, u.username as seller_name
    FROM disassembly_requests dr
    JOIN device_submissions ds ON dr.submission_id = ds.id
    JOIN users u ON ds.user_id = u.id
    WHERE dr.technician_id = $technician_id AND dr.status = 'pending'
    ORDER BY dr.created_at DESC
");

// Get active disassembly
$active_disassembly = $mysqli->query("
    SELECT dr.*, ds.device_type, ds.brand, ds.model, u.username as seller_name
    FROM disassembly_requests dr
    JOIN device_submissions ds ON dr.submission_id = ds.id
    JOIN users u ON ds.user_id = u.id
    WHERE dr.technician_id = $technician_id AND dr.status IN('accepted', 'in_progress')
    ORDER BY dr.created_at DESC
");
?>

<div class="content-section">
    <h2>Pending Disassembly Requests</h2>
    <?php if ($pending_requests && $pending_requests->num_rows > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>Device</th>
                    <th>Brand</th>
                    <th>Model</th>
                    <th>Seller</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($req = $pending_requests->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($req['device_type']); ?></td>
                        <td><?php echo htmlspecialchars($req['brand']); ?></td>
                        <td><?php echo htmlspecialchars($req['model']); ?></td>
                        <td><?php echo htmlspecialchars($req['seller_name']); ?></td>
                        <td><span class="status-badge status-pending">Waiting for Clerk Approval</span></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>No pending disassembly requests.</p>
    <?php endif; ?>
</div>

<div class="content-section">
    <h2>Active Disassembly</h2>
    <?php if ($active_disassembly && $active_disassembly->num_rows > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>Device</th>
                    <th>Brand</th>
                    <th>Model</th>
                    <th>Status</th>
                    <th>Clerk Status</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($dis = $active_disassembly->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($dis['device_type']); ?></td>
                        <td><?php echo htmlspecialchars($dis['brand']); ?></td>
                        <td><?php echo htmlspecialchars($dis['model']); ?></td>
                        <td><span class="status-badge status-<?php echo $dis['status']; ?>"><?php echo ucfirst($dis['status']); ?></span></td>
                        <td><?php 
                        if ($dis['status'] == 'accepted') {echo 'Approved by Clerk';} 
                        elseif ($dis['status'] == 'in_progress') {echo 'Disassembly In Progress';}
                        else {echo ucfirst(str_replace('_', ' ', $dis['status']));}
                        ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>No active disassembly tasks.</p>
    <?php endif; ?>
</div>

<div class="content-section">
    <h2>All Disassembly History</h2>
    <?php if ($all_disassembly && $all_disassembly->num_rows > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Device</th>
                    <th>Brand</th>
                    <th>Model</th>
                    <th>Status</th>
                    <th>Created</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($dis = $all_disassembly->fetch_assoc()): ?>
                    <tr>
                        <td>#<?php echo $dis['id']; ?></td>
                        <td><?php echo htmlspecialchars($dis['device_type']); ?></td>
                        <td><?php echo htmlspecialchars($dis['brand']); ?></td>
                        <td><?php echo htmlspecialchars($dis['model']); ?></td>
                        <td><span class="status-badge status-<?php echo $dis['status']; ?>"><?php echo ucfirst($dis['status']); ?></span></td>
                        <td><?php echo date('M d, Y', strtotime($dis['created_at'])); ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>No disassembly history found.</p>
    <?php endif; ?>
</div>

<!-- Complete Disassembly Modal -->
<div id="completeDisassemblyModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Complete Disassembly</h3>
            <button class="close-modal" onclick="closeModal('completeDisassemblyModal')">&times;</button>
        </div>
        <form method="POST" action="" id="disassemblyForm">
            <input type="hidden" name="action" value="complete_disassembly">
            <input type="hidden" name="request_id" id="disassemblyRequestId">
            
            <div class="form-group">
                <label for="device_model">Device Model</label>
                <input type="text" id="device_model" name="device_model" required>
            </div>
            
            <div class="form-group">
                <label for="device_specs">Device Specifications</label>
                <textarea id="device_specs" name="device_specs" rows="2" placeholder="e.g., CPU, RAM, Storage, etc."></textarea>
            </div>
            
            <div class="form-group">
                <label>Salvaged Parts</label>
                <div id="partsContainer">
                    <div class="part-entry">
                        <input type="text" name="parts[0][name]" placeholder="Part name" required>
                        <select name="parts[0][condition]">
                            <option value="working">Working</option>
                            <option value="damaged">Damaged</option>
                            <option value="unusable">Unusable</option>
                        </select>
                        <input type="number" name="parts[0][quantity]" value="1" min="1" required>
                        <button type="button" class="btn btn-sm btn-danger" onclick="removePart(this)">Remove</button>
                    </div>
                </div>
                <button type="button" class="btn btn-sm btn-secondary" onclick="addPart()">+ Add Part</button>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('completeDisassemblyModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Submit</button>
            </div>
        </form>
    </div>
</div>

<style>
.part-entry {
    display: flex;
    gap: 10px;
    margin-bottom: 10px;
}

.part-entry input,
.part-entry select {
    flex: 1;
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.part-entry input[type="number"] {
    width: 80px;
    flex: none;
}
</style>

<script>
function confirmDisassemblyPickup(requestId) {
    const code = prompt('Enter the pickup code to confirm:');
    if (code) {
        fetch('technician_disassembly.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=confirm_pickup&request_id=' + requestId + '&pickup_code=' + encodeURIComponent(code)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Pickup confirmed successfully!');
                location.reload();
            } else {
                alert(data.error || 'Invalid pickup code');
            }
        });
    }
}

function openCompleteDisassemblyModal(requestId, deviceModel) {
    document.getElementById('disassemblyRequestId').value = requestId;
    document.getElementById('device_model').value = deviceModel;
    openModal('completeDisassemblyModal');
}

function addPart() {
    const container = document.getElementById('partsContainer');
    const index = container.children.length;
    const div = document.createElement('div');
    div.className = 'part-entry';
    div.innerHTML = `
        <input type="text" name="parts[${index}][name]" placeholder="Part name" required>
        <select name="parts[${index}][condition]">
            <option value="working">Working</option>
            <option value="damaged">Damaged</option>
            <option value="unusable">Unusable</option>
        </select>
        <input type="number" name="parts[${index}][quantity]" value="1" min="1" required>
        <button type="button" class="btn btn-sm btn-danger" onclick="removePart(this)">Remove</button>
    `;
    container.appendChild(div);
}

function removePart(button) {
    button.parentElement.remove();
}
</script>
