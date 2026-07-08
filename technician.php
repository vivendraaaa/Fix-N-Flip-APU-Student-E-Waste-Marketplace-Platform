<?php
session_start();
$mysqli = new mysqli("localhost", "root", "", "fyp");

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

// Check if user is logged in and is technician
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in'] || strtolower($_SESSION['role']) !== 'technician') {
    header('Location: Login.php');
    exit;
}

$technician_id = $_SESSION['user_id'];

// Get statistics
$pending_repairs = $mysqli->query("SELECT COUNT(*) as count FROM repair_requests WHERE technician_id = $technician_id AND status = 'pending'")->fetch_assoc()['count'] ?? 0;
$active_repairs = $mysqli->query("SELECT COUNT(*) as count FROM repair_requests WHERE technician_id = $technician_id AND status IN('accepted', 'picked_up', 'repairing')")->fetch_assoc()['count'] ?? 0;
$completed_repairs = $mysqli->query("SELECT COUNT(*) as count FROM repair_requests WHERE technician_id = $technician_id AND status = 'completed'")->fetch_assoc()['count'] ?? 0;
$pending_disassembly = $mysqli->query("SELECT COUNT(*) as count FROM disassembly_requests WHERE technician_id = $technician_id AND status = 'pending'")->fetch_assoc()['count'] ?? 0;

// Get pending repair requests
$pending_repair_requests = $mysqli->query("
    SELECT rr.*, ds.device_type, ds.brand, ds.model, ds.estimated_price, u.username as seller_name
    FROM repair_requests rr
    JOIN device_submissions ds ON rr.submission_id = ds.id
    JOIN users u ON ds.user_id = u.id
    WHERE rr.technician_id = $technician_id AND rr.status = 'pending'
    ORDER BY rr.created_at DESC
");

// Get active repairs
$active_repairs_list = $mysqli->query("
    SELECT rr.*, ds.device_type, ds.brand, ds.model, ds.estimated_price, u.username as seller_name
    FROM repair_requests rr
    JOIN device_submissions ds ON rr.submission_id = ds.id
    JOIN users u ON ds.user_id = u.id
    WHERE rr.technician_id = $technician_id AND rr.status IN('accepted', 'picked_up', 'repairing')
    ORDER BY rr.created_at DESC
");

// Get pending disassembly requests
$pending_disassembly_requests = $mysqli->query("
    SELECT dr.*, ds.device_type, ds.brand, ds.model, u.username as seller_name
    FROM disassembly_requests dr
    JOIN device_submissions ds ON dr.submission_id = ds.id
    JOIN users u ON ds.user_id = u.id
    WHERE dr.technician_id = $technician_id AND dr.status = 'pending'
    ORDER BY dr.created_at DESC
");

// Handle section display
$section = isset($_GET['section']) ? $_GET['section'] : 'dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Technician Dashboard - Fix N Flip</title>
    <link rel="stylesheet" href="technician.css">
</head>
<body>
    <div class="technician-container">
        <aside class="sidebar">
            <h2>Fix N Flip</h2>
            <nav>
                <ul>
                    <li><a href="technician.php?section=dashboard" class="<?php echo $section === 'dashboard' ? 'active' : ''; ?>">Dashboard</a></li>
                    <li><a href="technician.php?section=repairs" class="<?php echo $section === 'repairs' ? 'active' : ''; ?>">Repair Requests</a></li>
                    <li><a href="technician.php?section=parts" class="<?php echo $section === 'parts' ? 'active' : ''; ?>">Parts</a></li>
                    <li><a href="technician.php?section=disassembly" class="<?php echo $section === 'disassembly' ? 'active' : ''; ?>">Disassembly</a></li>
                    <li><a href="technician.php?section=listings" class="<?php echo $section === 'listings' ? 'active' : ''; ?>">My Listings</a></li>
                    <li><a href="technician.php?section=profile" class="<?php echo $section === 'profile' ? 'active' : ''; ?>">Profile</a></li>
                </ul>
            </nav>
            <div class="sidebar-footer">
                <a href="logout.php" class="logout-btn">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                        <polyline points="16 17 21 12 16 7"></polyline>
                        <line x1="21" y1="12" x2="9" y2="12"></line>
                    </svg>
                    Logout
                </a>
            </div>
        </aside>

        <main class="main-content">
            <div class="header">
                <h1><?php echo ucfirst($section); ?></h1>
                <div class="user-info">
                    <span>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></span>
                </div>
            </div>

            <?php if ($section === 'dashboard'): ?>
                <div class="stats-grid">
                    <div class="stat-card">
                        <h3>Pending Repairs</h3>
                        <div class="value"><?php echo $pending_repairs; ?></div>
                        <div class="trend">Awaiting your response</div>
                    </div>
                    <div class="stat-card">
                        <h3>Active Repairs</h3>
                        <div class="value"><?php echo $active_repairs; ?></div>
                        <div class="trend">Currently in progress</div>
                    </div>
                    <div class="stat-card">
                        <h3>Completed Repairs</h3>
                        <div class="value"><?php echo $completed_repairs; ?></div>
                        <div class="trend">Successfully repaired</div>
                    </div>
                    <div class="stat-card">
                        <h3>Pending Disassembly</h3>
                        <div class="value"><?php echo $pending_disassembly; ?></div>
                        <div class="trend">Devices to dismantle</div>
                    </div>
                </div>

                <div class="content-section">
                    <h2>Pending Repair Requests</h2>
                    <?php if ($pending_repair_requests && $pending_repair_requests->num_rows > 0): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Device</th>
                                    <th>Brand</th>
                                    <th>Model</th>
                                    <th>Seller</th>
                                    <th>Est. Price</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($req = $pending_repair_requests->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($req['device_type']); ?></td>
                                        <td><?php echo htmlspecialchars($req['brand']); ?></td>
                                        <td><?php echo htmlspecialchars($req['model']); ?></td>
                                        <td><?php echo htmlspecialchars($req['seller_name']); ?></td>
                                        <td>RM<?php echo number_format($req['estimated_price'], 2); ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-primary" onclick="acceptRepair(<?php echo $req['id']; ?>)">Accept</button>
                                            <button class="btn btn-sm btn-danger" onclick="rejectRepair(<?php echo $req['id']; ?>)">Reject</button>
                                            <button type="button" class="btn btn-primary btn-sm" onclick="viewRepairRequest(<?php echo $req['id']; ?>)">View</button>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p>No pending repair requests.</p>
                    <?php endif; ?>
                </div>

                <div class="content-section">
                    <h2>Active Repairs</h2>
                    <?php if ($active_repairs_list && $active_repairs_list->num_rows > 0): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Device</th>
                                    <th>Brand</th>
                                    <th>Model</th>
                                    <th>Status</th>
                                    <th>Pickup Code</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($repair = $active_repairs_list->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($repair['device_type']); ?></td>
                                        <td><?php echo htmlspecialchars($repair['brand']); ?></td>
                                        <td><?php echo htmlspecialchars($repair['model']); ?></td>
                                        <td><span class="status-badge status-<?php echo $repair['status']; ?>"><?php echo ucfirst(str_replace('_', ' ', $repair['status'])); ?></span></td>
                                        <td><?php echo $repair['pickup_code'] ? htmlspecialchars($repair['pickup_code']) : 'N/A'; ?></td>
                                        <td>
                                            <?php if ($repair['status'] === 'accepted' || $repair['status'] === 'picked_up'): ?>
                                                <button class="btn btn-sm btn-primary" onclick="startRepair(<?php echo $repair['id']; ?>)">Start Repair</button>
                                            <?php elseif ($repair['status'] === 'repairing'): ?>
                                                <button class="btn btn-sm btn-primary" onclick="completeRepair(<?php echo $repair['id']; ?>)">Complete Repair</button>
                                                <button class="btn btn-sm btn-danger" onclick="declareUnrepairable(<?php echo $repair['id']; ?>)">Unrepairable</button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p>No active repairs.</p>
                    <?php endif; ?>
                </div>

            <?php elseif ($section === 'repairs'): ?>
                <?php include 'technician_repairs.php'; ?>

            <?php elseif ($section === 'parts'): ?>
                <?php include 'technician_parts.php'; ?>

            <?php elseif ($section === 'disassembly'): ?>
                <?php include 'technician_disassembly.php'; ?>

            <?php elseif ($section === 'listings'): ?>
                <?php include 'technician_listings.php'; ?>

            <?php elseif ($section === 'create_listing'): ?>
                <?php include 'technician_create_listing.php'; ?>

            <?php elseif ($section === 'profile'): ?>
                <?php include 'technician_profile.php'; ?>

            <?php endif; ?>
        </main>
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

    <script src="technician.js"></script>
</body>
</html>
