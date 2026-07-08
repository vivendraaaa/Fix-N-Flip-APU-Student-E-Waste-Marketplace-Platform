<?php
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

// Get all listings for this technician
$listings = $mysqli->query("
    SELECT tl.*, rr.submission_id, ds.device_type, ds.brand as original_brand, ds.model as original_model
    FROM technician_listings tl
    LEFT JOIN repair_requests rr ON tl.repair_request_id = rr.id
    LEFT JOIN device_submissions ds ON rr.submission_id = ds.id
    WHERE tl.technician_id = $technician_id
    ORDER BY tl.created_at DESC
");
?>

<div class="content-section">
    <h2>My Product Listings</h2>
    <?php if ($listings && $listings->num_rows > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Product</th>
                    <th>Brand</th>
                    <th>Category</th>
                    <th>Condition</th>
                    <th>Price</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($listing = $listings->fetch_assoc()): ?>
                    <tr>
                        <td>#<?php echo $listing['id']; ?></td>
                        <td><?php echo htmlspecialchars($listing['name']); ?></td>
                        <td><?php echo htmlspecialchars($listing['brand']); ?></td>
                        <td><?php echo htmlspecialchars($listing['category']); ?></td>
                        <td><?php echo htmlspecialchars($listing['condition']); ?></td>
                        <td>RM<?php echo number_format($listing['price'], 2); ?></td>
                        <td>
                            <span class="status-badge status-<?php echo str_replace('_', '-', $listing['status']); ?>">
                                <?php 
                                echo match($listing['status']) {
                                    'pending_review' => 'Pending Review',
                                    'approved' => 'Approved',
                                    'rejected' => 'Rejected',
                                    'needs_revision' => 'Needs Revision',
                                    default => ucfirst($listing['status'])
                                };
                                ?>
                            </span>
                        </td>
                        <td><?php echo date('M d, Y', strtotime($listing['created_at'])); ?></td>
                        <td>
                            <button class="btn btn-sm btn-secondary" onclick="viewListingDetails(<?php echo $listing['id']; ?>)">View</button>
                            <button class="btn btn-sm btn-primary" onclick="editListing(<?php echo $listing['id']; ?>)">Edit</button>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>No listings yet. Complete a repair to create a listing.</p>
    <?php endif; ?>
</div>

<!-- Listing Details Modal -->
<div id="listingDetailsModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Listing Details</h3>
            <button class="close-modal" onclick="closeModal('listingDetailsModal')">&times;</button>
        </div>
        <div id="listingDetailsContent"></div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('listingDetailsModal')">Close</button>
        </div>
    </div>
</div>

<script>
function viewListingDetails(listingId) {
    fetch('get_technician_listing.php?id=' + listingId)
        .then(response => response.json())
        .then(data => {
            let html = '<style>';
            html += '.details-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin: 20px 0; }';
            html += '.detail-item { padding: 12px; background: #f8f9fa; border-radius: 8px; border-left: 4px solid #10b981; }';
            html += '.detail-item strong { display: block; margin-bottom: 5px; color: #333; font-size: 13px; }';
            html += '.detail-item span { color: #555; font-size: 15px; }';
            html += '.detail-item-full { padding: 12px; background: #f8f9fa; border-radius: 8px; border-left: 4px solid #10b981; margin-top: 15px; }';
            html += '.detail-item-full strong { display: block; margin-bottom: 5px; color: #333; font-size: 13px; }';
            html += '.detail-item-full span { color: #555; font-size: 15px; }';
            html += '.image-gallery { display: flex; gap: 10px; margin: 15px 0; flex-wrap: wrap; }';
            html += '.image-gallery img { width: 120px; height: 120px; object-fit: cover; border-radius: 8px; border: 1px solid #ddd; }';
            html += '.video-container { margin: 15px 0; }';
            html += '.video-container video { width: 100%; max-width: 400px; border-radius: 8px; }';
            html += '</style>';

            html += '<div class="details-grid">';
            html += '<div class="detail-item"><strong>Product Name</strong><span>' + data.name + '</span></div>';
            html += '<div class="detail-item"><strong>Brand</strong><span>' + data.brand + '</span></div>';
            html += '<div class="detail-item"><strong>Category</strong><span>' + data.category + '</span></div>';
            html += '<div class="detail-item"><strong>Condition</strong><span>' + data.condition + '</span></div>';
            html += '<div class="detail-item"><strong>Price</strong><span>RM' + parseFloat(data.price).toFixed(2) + '</span></div>';
            html += '<div class="detail-item"><strong>Status</strong><span>' + data.status + '</span></div>';
            html += '</div>';

            // Display images
            if (data.image1 || data.image2 || data.image3) {
                html += '<div class="detail-item-full"><strong>Product Images</strong>';
                html += '<div class="image-gallery">';
                if (data.image1) html += '<img src="' + data.image1 + '" alt="Product Image 1">';
                if (data.image2) html += '<img src="' + data.image2 + '" alt="Product Image 2">';
                if (data.image3) html += '<img src="' + data.image3 + '" alt="Product Image 3">';
                html += '</div></div>';
            }

            // Display video
            if (data.video) {
                html += '<div class="detail-item-full"><strong>Product Video</strong>';
                html += '<div class="video-container">';
                html += '<video controls><source src="' + data.video + '" type="video/mp4">Your browser does not support the video tag.</video>';
                html += '</div></div>';
            }

            html += '<div class="detail-item-full"><strong>Specs</strong><span>' + (data.specs || 'Not specified') + '</span></div>';
            html += '<div class="detail-item-full"><strong>Accessories</strong><span>' + (data.accessories || 'None') + '</span></div>';
            html += '<div class="detail-item-full"><strong>Repairs Done</strong><span>' + (data.repairs_done || 'None') + '</span></div>';

            if (data.clerk_notes) {
                html += '<div class="detail-item-full" style="border-left-color: #ffc107;"><strong>Clerk Notes</strong><span>' + data.clerk_notes + '</span></div>';
            }

            if (data.pickup_pin) {
                html += '<div style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); padding: 25px; border-radius: 12px; text-align: center; font-size: 36px; font-weight: bold; letter-spacing: 6px; margin: 20px 0; color: white; box-shadow: 0 4px 15px rgba(16, 185, 129, 0.4);">';
                html += data.pickup_pin;
                html += '</div>';
                html += '<p style="text-align: center; color: #666; font-size: 14px;">Pickup PIN for customer</p>';
            }

            document.getElementById('listingDetailsContent').innerHTML = html;
            openModal('listingDetailsModal');
        });
}

function editListing(listingId) {
    // Redirect to edit page or open edit modal
    window.location.href = 'technician_edit_listing.php?id=' + listingId;
}
</script>
