<?php
function ensureTechnicianPartsColumn($mysqli, $table, $column, $definition) {
    $tableSafe = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    $check = $mysqli->query("SHOW COLUMNS FROM `$tableSafe` LIKE '" . $mysqli->real_escape_string($column) . "'");
    if (!$check || $check->num_rows === 0) {
        $mysqli->query("ALTER TABLE `$tableSafe` ADD COLUMN " . $definition);
    }
}

function technicianPartsHasColumn($mysqli, $table, $column) {
    $tableSafe = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    $check = $mysqli->query("SHOW COLUMNS FROM `$tableSafe` LIKE '" . $mysqli->real_escape_string($column) . "'");
    return $check && $check->num_rows > 0;
}

ensureTechnicianPartsColumn($mysqli, 'parts_requests', 'parts_location', 'parts_location VARCHAR(255) NULL');
ensureTechnicianPartsColumn($mysqli, 'parts_requests', 'picked_up_at', 'picked_up_at DATETIME NULL');
ensureTechnicianPartsColumn($mysqli, 'parts_requests', 'clerk_notes', 'clerk_notes TEXT NULL');
ensureTechnicianPartsColumn($mysqli, 'parts_inventory', 'location', "location VARCHAR(255) DEFAULT 'Pending Clerk Location'");
ensureTechnicianPartsColumn($mysqli, 'parts_inventory', 'shelf', 'shelf VARCHAR(100) NULL');
ensureTechnicianPartsColumn($mysqli, 'parts_inventory', 'quantity', 'quantity INT DEFAULT 1');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'pickup_parts') {
    $parts_request_id = intval($_POST['parts_request_id'] ?? 0);
    $stmt = $mysqli->prepare("UPDATE parts_requests SET status = 'picked_up', picked_up_at = NOW() WHERE id = ? AND technician_id = ? AND status IN ('approved','accepted','ready','ready_for_pickup')");
    $stmt->bind_param("ii", $parts_request_id, $technician_id);
    $stmt->execute();
    header('Location: technician.php?section=parts');
    exit;
}

// Get technician's parts requests
$parts_requests = $mysqli->query("
    SELECT pr.*, rr.id as repair_id, ds.device_type, ds.brand, ds.model
    FROM parts_requests pr
    JOIN repair_requests rr ON pr.repair_request_id = rr.id
    JOIN device_submissions ds ON rr.submission_id = ds.id
    WHERE pr.technician_id = $technician_id
    ORDER BY pr.created_at DESC
");

// Get available parts inventory
$parts_inventory = $mysqli->query("
    SELECT * FROM parts_inventory
    WHERE quantity > 0
    ORDER BY created_at DESC
");
?>

<div class="content-section">
    <h2>My Parts Requests</h2>
    <?php if ($parts_requests && $parts_requests->num_rows > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>Repair ID</th>
                    <th>Device</th>
                    <th>Parts Needed</th>
                    <th>PIN</th>
                    <th>Status</th>
                    <th>Requested</th>
                    <th>Location</th>
                    
                </tr>
            </thead>
            <tbody>
                <?php while ($req = $parts_requests->fetch_assoc()): ?>
                    <tr>
                        <td>#<?php echo $req['repair_id']; ?></td>
                        <td><?php echo htmlspecialchars($req['device_type']); ?> - <?php echo htmlspecialchars($req['brand']); ?> <?php echo htmlspecialchars($req['model']); ?></td>
                        <td><?php echo nl2br(htmlspecialchars($req['parts_needed'])); ?></td>
                        <td><?php echo $req['pin'] ? htmlspecialchars($req['pin']) : '-'; ?></td>
                        <td>
                            <span class="status-badge status-<?php echo $req['status']; ?>">
                                <?php echo match($req['status']) { 'approved', 'accepted', 'ready', 'ready_for_pickup' => 'Ready for Pickup', 'picked_up' => 'Picked Up', default => ucfirst(str_replace('_', ' ', $req['status'])) }; ?>
                            </span>
                        </td>
<td><?php echo date('M d, Y', strtotime($req['created_at'])); ?></td>

<td>
    <?php
    echo !empty($req['parts_location'])
        ? htmlspecialchars($req['parts_location'])
        : '-';
    ?>
</td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>No parts requests found.</p>
    <?php endif; ?>
</div>

<div class="content-section">
    <h2>Available Parts Inventory</h2>
    <?php if ($parts_inventory && $parts_inventory->num_rows > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>Part Name</th>
                    <th>Device Model</th>
                    <th>Condition</th>
                    <th>Quantity</th>
                    <th>Location</th>
                    <th>Shelf</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($part = $parts_inventory->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($part['part_name']); ?></td>
                        <td><?php echo htmlspecialchars($part['device_model'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars(ucfirst($part['condition'] ?? $part['part_condition'] ?? 'N/A')); ?></td>
                        <td><?php echo $part['quantity']; ?></td>
                        <td><?php echo htmlspecialchars($part['location']); ?></td>
                        <td><?php echo htmlspecialchars($part['shelf'] ?? 'N/A'); ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>No parts available in inventory.</p>
    <?php endif; ?>
</div>
