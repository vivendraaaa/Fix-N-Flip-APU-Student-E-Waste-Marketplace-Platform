<?php
$mysqli = new mysqli("localhost", "root", "", "fyp");

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

$clerk_id = $_SESSION['user_id'];

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'approve') {
        $review_id = intval($_POST['review_id']);
        $approved_date = $_POST['approved_date'];
        $approved_time = $_POST['approved_time'];
        $approved_location = $_POST['approved_location'];
        
        $stmt = $mysqli->prepare("UPDATE in_person_reviews SET clerk_id = ?, status = 'approved', approved_date = ?, approved_time = ?, approved_location = ? WHERE id = ?");
        $stmt->bind_param("isssi", $clerk_id, $approved_date, $approved_time, $approved_location, $review_id);
        $stmt->execute();
        
        header('Location: clerk.php?section=in_person');
        exit;
        
    } elseif ($action === 'request_time_change') {
        $review_id = intval($_POST['review_id']);
        $new_date = $_POST['new_date'];
        $new_time = $_POST['new_time'];
        $new_location = $_POST['new_location'];
        $reason = $_POST['reason'];
        
        $stmt = $mysqli->prepare("UPDATE in_person_reviews SET clerk_id = ?, status = 'time_change_requested', approved_date = ?, approved_time = ?, approved_location = ?, clerk_notes = ? WHERE id = ?");
        $stmt->bind_param("issssi", $clerk_id, $new_date, $new_time, $new_location, $reason, $review_id);
        $stmt->execute();
        
        header('Location: clerk.php?section=in_person');
        exit;
        
    } elseif ($action === 'clerk_arrived') {
        $review_id = intval($_POST['review_id']);
        
        $stmt = $mysqli->prepare("UPDATE in_person_reviews SET clerk_arrived_at = NOW() WHERE id = ?");
        $stmt->bind_param("i", $review_id);
        $stmt->execute();
        
        echo json_encode(['success' => true]);
        exit;
        
    } elseif ($action === 'user_arrived') {
        $review_id = intval($_POST['review_id']);
        
        $stmt = $mysqli->prepare("UPDATE in_person_reviews SET user_arrived_at = NOW() WHERE id = ?");
        $stmt->bind_param("i", $review_id);
        $stmt->execute();
        
        echo json_encode(['success' => true]);
        exit;
        
    } elseif ($action === 'end_consultation') {
        $review_id = intval($_POST['review_id']);
        $end_reason = $_POST['end_reason'];
        
        $stmt = $mysqli->prepare("UPDATE in_person_reviews SET status = 'cancelled', ended_at = NOW(), end_reason = ? WHERE id = ?");
        $stmt->bind_param("si", $end_reason, $review_id);
        $stmt->execute();
        
        echo json_encode(['success' => true]);
        exit;
    }
}

// Get in-person reviews
$reviews = $mysqli->query("
    SELECT ipr.*, ds.device_type, ds.brand, ds.model, u.username, u.email, u.phone
    FROM in_person_reviews ipr
    JOIN device_submissions ds ON ipr.submission_id = ds.id
    JOIN users u ON ipr.user_id = u.id
    WHERE ipr.clerk_id = $clerk_id OR ipr.clerk_id IS NULL
    ORDER BY ipr.created_at DESC
");
?>

<div class="content-section">
    <h2>In-Person Reviews</h2>
    
    <?php if ($reviews && $reviews->num_rows > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Device</th>
                    <th>User</th>
                    <th>Phone</th>
                    <th>Requested Date</th>
                    <th>Requested Time</th>
                    <th>Requested Location</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($review = $reviews->fetch_assoc()): ?>
                    <tr>
                        <td>#<?php echo $review['id']; ?></td>
                        <td><?php echo htmlspecialchars($review['device_type']); ?></td>
                        <td><?php echo htmlspecialchars($review['username']); ?></td>
                        <td><?php echo htmlspecialchars($review['phone'] ?? 'N/A'); ?></td>
                        <td><?php echo $review['requested_date'] ? date('M d, Y', strtotime($review['requested_date'])) : 'N/A'; ?></td>
                        <td><?php echo $review['requested_time'] ? date('H:i', strtotime($review['requested_time'])) : 'N/A'; ?></td>
                        <td><?php echo htmlspecialchars($review['requested_location'] ?? 'N/A'); ?></td>
                        <td><span class="status-badge status-<?php echo str_replace('_', '-', $review['status']); ?>"><?php echo ucfirst(str_replace('_', ' ', $review['status'])); ?></span></td>
                        <td>
                            <?php if ($review['status'] === 'pending'): ?>
                                <button class="btn btn-sm btn-primary" onclick="approveInPerson(<?php echo $review['id']; ?>)">Approve</button>
                                <button class="btn btn-sm btn-warning" onclick="requestTimeChange(<?php echo $review['id']; ?>)">Request Change</button>
                            <?php elseif ($review['status'] === 'approved' && !$review['clerk_arrived_at']): ?>
                                <button class="btn btn-sm btn-primary" onclick="markClerkArrived(<?php echo $review['id']; ?>)">Mark Arrived</button>
                            <?php elseif ($review['status'] === 'approved' && $review['clerk_arrived_at'] && !$review['user_arrived_at']): ?>
                                <button class="btn btn-sm btn-secondary" onclick="markUserArrived(<?php echo $review['id']; ?>)">User Arrived</button>
                                <button class="btn btn-sm btn-danger" onclick="endConsultation(<?php echo $review['id']; ?>)">End</button>
                            <?php elseif ($review['status'] === 'approved' && $review['clerk_arrived_at'] && $review['user_arrived_at']): ?>
                                <button class="btn btn-sm btn-danger" onclick="endConsultation(<?php echo $review['id']; ?>)">End</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>No in-person review requests found.</p>
    <?php endif; ?>
</div>

<!-- Approve Review Modal -->
<div id="approveReviewModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Approve In-Person Review</h3>
            <button class="close-modal" onclick="closeModal('approveReviewModal')">&times;</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="action" value="approve">
            <input type="hidden" name="review_id" id="approveReviewId">
            
            <div class="form-group">
                <label for="approved_date">Approved Date</label>
                <input type="date" id="approved_date" name="approved_date" required>
            </div>
            
            <div class="form-group">
                <label for="approved_time">Approved Time</label>
                <input type="time" id="approved_time" name="approved_time" required>
            </div>
            
            <div class="form-group">
                <label for="approved_location">Approved Location</label>
                <input type="text" id="approved_location" name="approved_location" required>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('approveReviewModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Approve</button>
            </div>
        </form>
    </div>
</div>

<!-- Time Change Modal -->
<div id="timeChangeModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Request Time/Location Change</h3>
            <button class="close-modal" onclick="closeModal('timeChangeModal')">&times;</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="action" value="request_time_change">
            <input type="hidden" name="review_id" id="timeChangeReviewId">
            
            <div class="form-group">
                <label for="new_date">New Date</label>
                <input type="date" id="new_date" name="new_date" required>
            </div>
            
            <div class="form-group">
                <label for="new_time">New Time</label>
                <input type="time" id="new_time" name="new_time" required>
            </div>
            
            <div class="form-group">
                <label for="new_location">New Location</label>
                <input type="text" id="new_location" name="new_location" required>
            </div>
            
            <div class="form-group">
                <label for="reason">Reason for Change</label>
                <textarea id="reason" name="reason" rows="3" required></textarea>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('timeChangeModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Request Change</button>
            </div>
        </form>
    </div>
</div>
