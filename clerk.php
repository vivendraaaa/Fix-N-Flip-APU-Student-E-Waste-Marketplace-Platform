<?php
session_start();
$mysqli = new mysqli("localhost", "root", "", "fyp");

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

// Check if user is logged in and is clerk
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in'] || strtolower($_SESSION['role']) !== 'clerk') {
    header('Location: Login.php');
    exit;
}

$clerk_id = $_SESSION['user_id'];

// Get statistics
$pending_submissions = $mysqli->query("SELECT COUNT(*) as count FROM device_submissions WHERE status = 'pending'")->fetch_assoc()['count'] ?? 0;
$pending_reviews = $mysqli->query("SELECT COUNT(*) as count FROM in_person_reviews WHERE clerk_id = $clerk_id AND status = 'pending'")->fetch_assoc()['count'] ?? 0;
$pending_listings = $mysqli->query("SELECT COUNT(*) as count FROM technician_listings WHERE status = 'pending_review'")->fetch_assoc()['count'] ?? 0;
$pending_repairs = $mysqli->query("SELECT COUNT(*) as count FROM repair_requests WHERE status = 'pending'")->fetch_assoc()['count'] ?? 0;
$pending_parts_requests = $mysqli->query("SELECT COUNT(*) as count FROM parts_requests WHERE status = 'pending'")->fetch_assoc()['count'] ?? 0;
$unread_messages = $mysqli->query("SELECT COUNT(*) as count FROM chat_messages WHERE receiver_id = $clerk_id AND is_read = FALSE")->fetch_assoc()['count'] ?? 0;

// Handle section display
$section = isset($_GET['section']) ? $_GET['section'] : 'dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clerk Dashboard - Fix N Flip</title>
    <!-- <link rel="stylesheet" href="style.css"> -->
    <link rel="stylesheet" href="clerk.css">
</head>
<body>
    <div class="clerk-container">
        <aside class="sidebar">
            <h2>Fix N Flip</h2>
            <nav>
                <ul>
                    <li><a href="clerk.php?section=dashboard" class="<?php echo $section === 'dashboard' ? 'active' : ''; ?>">Dashboard</a></li>
                    <li><a href="clerk.php?section=submissions" class="<?php echo $section === 'submissions' ? 'active' : ''; ?>">
                        Submissions
                        <?php if ($pending_submissions > 0): ?>
                            <span class="badge"><?php echo $pending_submissions; ?></span>
                        <?php endif; ?>
                    </a></li>
                    <li><a href="clerk.php?section=in_person" class="<?php echo $section === 'in_person' ? 'active' : ''; ?>">
                        In-Person Reviews
                        <?php if ($pending_reviews > 0): ?>
                            <span class="badge"><?php echo $pending_reviews; ?></span>
                        <?php endif; ?>
                    </a></li>
                    <li><a href="clerk.php?section=listings" class="<?php echo $section === 'listings' ? 'active' : ''; ?>">
                        Technician Listings
                        <?php if ($pending_listings > 0): ?>
                            <span class="badge"><?php echo $pending_listings; ?></span>
                        <?php endif; ?>
                    </a></li>
                    <li><a href="clerk.php?section=repairs" class="<?php echo $section === 'repairs' ? 'active' : ''; ?>">
                        Repairs Tracking
                        <?php if ($pending_repairs > 0): ?>
                            <span class="badge"><?php echo $pending_repairs; ?></span>
                        <?php endif; ?>
                    </a></li>
                    <!-- <li><a href="clerk.php?section=parts" class="<?php echo $section === 'parts' ? 'active' : ''; ?>">
                        Parts
                        <?php if ($pending_parts_requests > 0): ?>
                            <span class="badge"><?php echo $pending_parts_requests; ?></span>
                        <?php endif; ?>
                    </a></li> -->
                    <li><a href="clerk.php?section=collection" class="<?php echo $section === 'collection' ? 'active' : ''; ?>">Device Collection/Sale</a></li>
                    <!-- <li><a href="clerk.php?section=chat" class="<?php echo $section === 'chat' ? 'active' : ''; ?>">
                        Live Chat
                        <?php if ($unread_messages > 0): ?>
                            <span class="badge"><?php echo $unread_messages; ?></span>
                        <?php endif; ?>
                    </a></li> -->
                    <li><a href="clerk.php?section=profile" class="<?php echo $section === 'profile' ? 'active' : ''; ?>">Profile</a></li>
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
                        <h3>Pending Submissions</h3>
                        <div class="value"><?php echo $pending_submissions; ?></div>
                        <div class="trend">Awaiting review</div>
                    </div>
                    <div class="stat-card">
                        <h3>In-Person Reviews</h3>
                        <div class="value"><?php echo $pending_reviews; ?></div>
                        <div class="trend">Scheduled reviews</div>
                    </div>
                    <div class="stat-card">
                        <h3>Pending Listings</h3>
                        <div class="value"><?php echo $pending_listings; ?></div>
                        <div class="trend">Technician submissions</div>
                    </div>
                    <div class="stat-card">
                        <h3>Unread Messages</h3>
                        <div class="value"><?php echo $unread_messages; ?></div>
                        <div class="trend">Chat support</div>
                    </div>
                    <div class="stat-card">
                        <h3>Pending Repairs</h3>
                        <div class="value"><?php echo $pending_repairs; ?></div>
                        <div class="trend">New repair requests</div>
                    </div>
                    <div class="stat-card">
                        <h3>Parts Requests</h3>
                        <div class="value"><?php echo $pending_parts_requests; ?></div>
                        <div class="trend">Awaiting approval</div>
                    </div>
                </div>

                <div class="content-section">
                    <h2>Recent Submissions</h2>
                    <?php
                    $recent_submissions = $mysqli->query("
                        SELECT ds.*, u.username 
                        FROM device_submissions ds
                        JOIN users u ON ds.user_id = u.id
                        WHERE ds.status = 'pending'
                        ORDER BY ds.id DESC
                        LIMIT 5
                    ");
                    ?>
                    <?php if ($recent_submissions && $recent_submissions->num_rows > 0): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Device</th>
                                    <th>Brand</th>
                                    <th>Model</th>
                                    <th>User</th>
                                    <th>Est. Price</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($sub = $recent_submissions->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($sub['device_type']); ?></td>
                                        <td><?php echo htmlspecialchars($sub['brand']); ?></td>
                                        <td><?php echo htmlspecialchars($sub['model']); ?></td>
                                        <td><?php echo htmlspecialchars($sub['username']); ?></td>
                                        <td>RM<?php echo number_format($sub['estimated_price'], 2); ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-primary" onclick="viewSubmissionClerk(<?php echo $sub['id']; ?>)">View</button>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p>No pending submissions.</p>
                    <?php endif; ?>
                </div>

            <?php elseif ($section === 'submissions'): ?>
                <?php include 'clerk_submissions.php'; ?>

            <?php elseif ($section === 'in_person'): ?>
                <?php include 'clerk_in_person.php'; ?>

            <?php elseif ($section === 'listings'): ?>
                <?php include 'clerk_listings.php'; ?>

            <?php elseif ($section === 'repairs'): ?>
                <?php include 'clerk_repairs.php'; ?>

            <?php elseif ($section === 'parts'): ?>
                <?php include 'clerk_parts.php'; ?>

            <?php elseif ($section === 'collection'): ?>
                <?php include 'clerk_collection.php'; ?>

            <?php elseif ($section === 'chat'): ?>
                <?php include 'clerk_chat.php'; ?>

            <?php elseif ($section === 'profile'): ?>
                <?php include 'clerk_profile.php'; ?>

            <?php endif; ?>
        </main>
    </div>

    <script src="clerk.js"></script>
</body>
</html>
