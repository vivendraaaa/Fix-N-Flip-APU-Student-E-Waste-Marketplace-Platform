<?php
// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'approve_parts_request') {
        $request_id = intval($_POST['request_id']);
        $location = $_POST['location'] ?? 'Fix N Flip Storage office Shelf 8A';
        $shelf = $_POST['shelf'] ?? '';
        
        // Get parts request details
        $req = $mysqli->query("SELECT * FROM parts_requests WHERE id = $request_id")->fetch_assoc();
        
        if ($req) {
            // Update parts request status
            $mysqli->query("UPDATE parts_requests SET status = 'approved' WHERE id = $request_id");
            
            // Update repair_requests with parts location
            $mysqli->query("UPDATE repair_requests SET parts_request_status = 'approved', parts_location = '$location' WHERE id = {$req['repair_request_id']}");
            
            // Notify technician
            $notification = "Your parts request (PIN: {$req['pin']}) has been approved. Location: $location";
            $mysqli->query("INSERT INTO notifications (user_id, message, type, created_at) VALUES ({$req['technician_id']}, '$notification', 'parts_approved', NOW())");
        }
        
        header('Location: clerk.php?section=parts');
        exit;
        
    } elseif ($action === 'reject_parts_request') {
        $request_id = intval($_POST['request_id']);
        
        // Get parts request details
        $req = $mysqli->query("SELECT * FROM parts_requests WHERE id = $request_id")->fetch_assoc();
        
        if ($req) {
            // Update parts request status
            $mysqli->query("UPDATE parts_requests SET status = 'rejected' WHERE id = $request_id");
            
            // Update repair_requests
            $mysqli->query("UPDATE repair_requests SET parts_request_status = 'rejected' WHERE id = {$req['repair_request_id']}");
            
            // Notify technician
            $notification = "Your parts request (PIN: {$req['pin']}) has been rejected.";
            $mysqli->query("INSERT INTO notifications (user_id, message, type, created_at) VALUES ({$req['technician_id']}, '$notification', 'parts_rejected', NOW())");
        }
        
        header('Location: clerk.php?section=parts');
        exit;
        
    } elseif ($action === 'update_part_location') {
        $part_id = intval($_POST['part_id']);
        $location = $_POST['location'] ?? '';
        $shelf = $_POST['shelf'] ?? '';
        
        $update = $mysqli->prepare("UPDATE parts_inventory SET location = ?, shelf = ? WHERE id = ?");
        $update->bind_param("ssi", $location, $shelf, $part_id);
        $update->execute();
        
        header('Location: clerk.php?section=parts');
        exit;
        
    } elseif ($action === 'add_part') {
        $part_name = $_POST['part_name'] ?? '';
        $device_model = $_POST['device_model'] ?? '';
        $condition = $_POST['condition'] ?? 'working';
        $quantity = intval($_POST['quantity'] ?? 1);
        $location = $_POST['location'] ?? 'Fix N Flip Storage office Shelf 8A';
        $shelf = $_POST['shelf'] ?? '';
        $source_type = $_POST['source_type'] ?? 'disassembly';
        $source_id = intval($_POST['source_id'] ?? 0);
        
        $insert = $mysqli->prepare("INSERT INTO parts_inventory (part_name, device_model, part_condition, quantity, location, shelf, source_type, source_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $insert->bind_param("sssisssi", $part_name, $device_model, $condition, $quantity, $location, $shelf, $source_type, $source_id);
        $insert->execute();
        
        header('Location: clerk.php?section=parts');
        exit;
    }
}

// Get pending parts requests
$pending_parts = $mysqli->query("
    SELECT pr.*, rr.id as repair_id, ds.device_type, ds.brand, ds.model, u.username as technician_name
    FROM parts_requests pr
    JOIN repair_requests rr ON pr.repair_request_id = rr.id
    JOIN device_submissions ds ON rr.submission_id = ds.id
    JOIN users u ON pr.technician_id = u.id
    WHERE pr.status = 'pending'
    ORDER BY pr.created_at DESC
");

// Get all parts requests
$all_parts_requests = $mysqli->query("
    SELECT pr.*, rr.id as repair_id, ds.device_type, ds.brand, ds.model, u.username as technician_name
    FROM parts_requests pr
    JOIN repair_requests rr ON pr.repair_request_id = rr.id
    JOIN device_submissions ds ON rr.submission_id = ds.id
    JOIN users u ON pr.technician_id = u.id
    ORDER BY pr.created_at DESC
");

// Get parts inventory with search
$inventory_search = isset($_GET['inventory_search']) ? $mysqli->real_escape_string($_GET['inventory_search']) : '';
$inventory_where = "WHERE 1=1";
if ($inventory_search) {
    $inventory_where .= " AND (pi.part_name LIKE '%$inventory_search%' OR pi.device_model LIKE '%$inventory_search%' OR u.username LIKE '%$inventory_search%')";
}

$parts_inventory = $mysqli->query("
    SELECT pi.*, rr.id as repair_id, ds.device_type, ds.brand, ds.model, u.username as technician_name
    FROM parts_inventory pi
    LEFT JOIN repair_requests rr ON pi.source_id = rr.id AND pi.source_type = 'disassembly'
    LEFT JOIN device_submissions ds ON rr.submission_id = ds.id
    LEFT JOIN users u ON rr.technician_id = u.id
    $inventory_where
    ORDER BY pi.created_at DESC
");
?>

<div class="content-section">
    <h2>Pending Parts Requests</h2>
    <?php if ($pending_parts && $pending_parts->num_rows > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>Repair ID</th>
                    <th>Technician</th>
                    <th>Device</th>
                    <th>Parts Needed</th>
                    <th>PIN</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($req = $pending_parts->fetch_assoc()): ?>
                    <tr>
                        <td>#<?php echo $req['repair_id']; ?></td>
                        <td><?php echo htmlspecialchars($req['technician_name']); ?></td>
                        <td><?php echo htmlspecialchars($req['device_type']); ?> - <?php echo htmlspecialchars($req['brand']); ?> <?php echo htmlspecialchars($req['model']); ?></td>
                        <td><?php echo nl2br(htmlspecialchars($req['parts_needed'])); ?></td>
                        <td><?php echo $req['pin'] ? htmlspecialchars($req['pin']) : '-'; ?></td>
                        <td>
                            <button class="btn btn-sm btn-success" onclick="approveParts(<?php echo $req['id']; ?>)">Approve</button>
                            <button class="btn btn-sm btn-danger" onclick="rejectParts(<?php echo $req['id']; ?>)">Reject</button>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>No pending parts requests.</p>
    <?php endif; ?>
</div>

<div class="content-section">
    <h2>All Parts Requests</h2>
    <?php if ($all_parts_requests && $all_parts_requests->num_rows > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>Repair ID</th>
                    <th>Technician</th>
                    <th>Device</th>
                    <th>Parts Needed</th>
                    <th>PIN</th>
                    <th>Status</th>
                    <th>Location</th>
                    <th>Requested</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($req = $all_parts_requests->fetch_assoc()): ?>
                    <tr>
                        <td>#<?php echo $req['repair_id']; ?></td>
                        <td><?php echo htmlspecialchars($req['technician_name']); ?></td>
                        <td><?php echo htmlspecialchars($req['device_type']); ?> - <?php echo htmlspecialchars($req['brand']); ?> <?php echo htmlspecialchars($req['model']); ?></td>
                        <td><?php echo nl2br(htmlspecialchars($req['parts_needed'])); ?></td>
                        <td><?php echo $req['pin'] ? htmlspecialchars($req['pin']) : '-'; ?></td>
                        <td>
                            <span class="status-badge status-<?php echo $req['status']; ?>">
                                <?php echo ucfirst($req['status']); ?>
                            </span>
                        </td>
                        <td><?php echo ($req['parts_location'] ?? '') ? htmlspecialchars($req['parts_location']) : '-'; ?></td>
                        <td><?php echo date('M d, Y', strtotime($req['created_at'])); ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>No parts requests found.</p>
    <?php endif; ?>
</div>

<div class="content-section">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h2>Parts Inventory</h2>
        <button class="btn btn-primary" onclick="showAddPartModal()" title="Add New Part">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="12" y1="5" x2="12" y2="19"></line>
                <line x1="5" y1="12" x2="19" y2="12"></line>
            </svg>
            Add Part
        </button>
    </div>

    <div class="search-bar">
        <input type="text" id="inventorySearchInput" placeholder="Search inventory by part name, model, or technician..." value="<?php echo htmlspecialchars($inventory_search); ?>">
        <button class="btn btn-primary" onclick="applyInventorySearch()">Search</button>
    </div>

    <?php if ($parts_inventory && $parts_inventory->num_rows > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>Part Name</th>
                    <th>Device Model</th>
                    <th>Condition</th>
                    <th>Quantity</th>
                    <th>Technician</th>
                    <th>Repair ID</th>
                    <th>Location</th>
                    <th>Shelf</th>
                    <th>Source</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($part = $parts_inventory->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($part['part_name']); ?></td>
                        <td><?php echo htmlspecialchars($part['device_model'] ?? 'N/A'); ?></td>
                        <td><?php echo ucfirst($part['part_condition']); ?></td>
                        <td><?php echo $part['quantity']; ?></td>
                        <td><?php echo $part['technician_name'] ? htmlspecialchars($part['technician_name']) : '-'; ?></td>
                        <td><?php echo $part['repair_id'] ? '#' . $part['repair_id'] : '-'; ?></td>
                        <td><?php echo htmlspecialchars($part['location']); ?></td>
                        <td><?php echo htmlspecialchars($part['shelf'] ?? 'N/A'); ?></td>
                        <td><?php echo ucfirst($part['source_type']); ?></td>
                        <td>
                            <button class="btn btn-sm btn-secondary" onclick="updatePartLocation(<?php echo $part['id']; ?>, '<?php echo htmlspecialchars($part['location']); ?>', '<?php echo htmlspecialchars($part['shelf'] ?? ''); ?>')">Update Location</button>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>No parts in inventory.</p>
    <?php endif; ?>
</div>

<!-- Approve Parts Modal -->
<div id="approvePartsModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Approve Parts Request</h3>
            <button class="close-modal" onclick="closeModal('approvePartsModal')">&times;</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="action" value="approve_parts_request">
            <input type="hidden" name="request_id" id="approvePartsRequestId">
            
            <div class="form-group">
                <label for="location">Location</label>
                <input type="text" id="location" name="location" placeholder="e.g., Warehouse A">
            </div>
            
            <div class="form-group">
                <label for="shelf">Shelf</label>
                <input type="text" id="shelf" name="shelf" placeholder="e.g., 8A">
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('approvePartsModal')">Cancel</button>
                <button type="submit" class="btn btn-success">Approve</button>
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
            <input type="hidden" name="action" value="reject_parts_request">
            <input type="hidden" name="request_id" id="rejectPartsRequestId">
            <p>Are you sure you want to reject this parts request?</p>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('rejectPartsModal')">Cancel</button>
                <button type="submit" class="btn btn-danger">Reject</button>
            </div>
        </form>
    </div>
</div>

<!-- Add Part Modal -->
<div id="addPartModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Add Part to Inventory</h3>
            <button class="close-modal" onclick="closeModal('addPartModal')">&times;</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="action" value="add_part">
            
            <div class="form-group">
                <label for="part_name">Part Name</label>
                <input type="text" id="part_name" name="part_name" required placeholder="e.g., Motherboard, Screen, Battery">
            </div>
            
            <div class="form-group">
                <label for="device_model">Device Model</label>
                <input type="text" id="device_model" name="device_model" placeholder="e.g., iPhone 13, Samsung S21">
            </div>
            
            <div class="form-group">
                <label for="condition">Condition</label>
                <select id="condition" name="condition" required>
                    <option value="working">Working</option>
                    <option value="damaged">Damaged</option>
                    <option value="unusable">Unusable</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="quantity">Quantity</label>
                <input type="number" id="quantity" name="quantity" value="1" min="1" required>
            </div>
            
            <div class="form-group">
                <label for="location">Location</label>
                <input type="text" id="location" name="location" value="Fix N Flip Storage office Shelf 8A" required>
            </div>
            
            <div class="form-group">
                <label for="shelf">Shelf</label>
                <input type="text" id="shelf" name="shelf" placeholder="e.g., 8A">
            </div>
            
            <div class="form-group">
                <label for="source_type">Source</label>
                <select id="source_type" name="source_type" required>
                    <option value="disassembly">Disassembly</option>
                    <option value="disposal">Disposal</option>
                    <option value="purchase">Purchase</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="source_id">Source ID (Optional)</label>
                <input type="number" id="source_id" name="source_id" placeholder="Related request ID">
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addPartModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Add Part</button>
            </div>
        </form>
    </div>
</div>

<script>
function approveParts(requestId) {
    document.getElementById('approvePartsRequestId').value = requestId;
    openModal('approvePartsModal');
}

function rejectParts(requestId) {
    document.getElementById('rejectPartsRequestId').value = requestId;
    openModal('rejectPartsModal');
}

function showAddPartModal() {
    openModal('addPartModal');
}

function updatePartLocation(partId, currentLocation, currentShelf) {
    document.getElementById('updatePartId').value = partId;
    document.getElementById('updateLocation').value = currentLocation;
    document.getElementById('updateShelf').value = currentShelf;
    openModal('updatePartModal');
}

function openModal(modalId) {
    document.getElementById(modalId).classList.add('active');
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('active');
}

function applyInventorySearch() {
    const search = document.getElementById('inventorySearchInput').value;
    window.location.href = 'clerk.php?section=parts&inventory_search=' + encodeURIComponent(search);
}

// Allow pressing Enter in search box
document.getElementById('inventorySearchInput').addEventListener('keypress', function (e) {
    if (e.key === 'Enter') {
        applyInventorySearch();
    }
});
</script>

<!-- Update Part Location Modal -->
<div id="updatePartModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Update Part Location</h3>
            <button class="close-modal" onclick="closeModal('updatePartModal')">&times;</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="action" value="update_part_location">
            <input type="hidden" name="part_id" id="updatePartId">
            
            <div class="form-group">
                <label for="updateLocation">Location</label>
                <input type="text" id="updateLocation" name="location" required>
            </div>
            
            <div class="form-group">
                <label for="updateShelf">Shelf</label>
                <input type="text" id="updateShelf" name="shelf" placeholder="e.g., 8A">
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('updatePartModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Update</button>
            </div>
        </form>
    </div>
</div>
