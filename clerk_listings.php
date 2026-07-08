<?php
$mysqli = new mysqli("localhost", "root", "", "fyp");

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

// Ensure products and product_images primary keys use auto-increment.
$schemaCheck = $mysqli->query("SHOW COLUMNS FROM products LIKE 'id'")->fetch_assoc();
if ($schemaCheck && strpos($schemaCheck['Extra'], 'auto_increment') === false) {
    $mysqli->query("ALTER TABLE products MODIFY COLUMN id INT(11) NOT NULL AUTO_INCREMENT");
}
$schemaCheck = $mysqli->query("SHOW COLUMNS FROM product_images LIKE 'id'")->fetch_assoc();
if ($schemaCheck && strpos($schemaCheck['Extra'], 'auto_increment') === false) {
    $mysqli->query("ALTER TABLE product_images MODIFY COLUMN id INT(11) NOT NULL AUTO_INCREMENT");
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'approve') {
        $listing_id = intval($_POST['listing_id']);
        
        // Get listing details
        $listing = $mysqli->query("SELECT * FROM technician_listings WHERE id = $listing_id")->fetch_assoc();
        
        if ($listing) {
            // Generate pickup PIN
            $pickup_pin = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
            
            // Add to products table
            $insert = $mysqli->prepare("INSERT INTO products (name, brand, category, `condition`, specs, accessories, price, description, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            $insert->bind_param("ssssssds", 
                $listing['name'],
                $listing['brand'],
                $listing['category'],
                $listing['condition'],
                $listing['specs'],
                $listing['accessories'],
                $listing['price'],
                $listing['repairs_done']
            );
            $insert->execute();
            
            $product_id = $mysqli->insert_id;
            
            // Transfer images to product_images table
            if ($listing['image1']) {
                $mysqli->query("INSERT INTO product_images (product_id, image) VALUES ($product_id, x'" . bin2hex($listing['image1']) . "')");
            }
            if ($listing['image2']) {
                $mysqli->query("INSERT INTO product_images (product_id, image) VALUES ($product_id, x'" . bin2hex($listing['image2']) . "')");
            }
            if ($listing['image3']) {
                $mysqli->query("INSERT INTO product_images (product_id, image) VALUES ($product_id, x'" . bin2hex($listing['image3']) . "')");
            }
            
            // Update listing status and set pickup PIN
            $update = $mysqli->prepare("UPDATE technician_listings SET status = 'approved', pickup_pin = ? WHERE id = ?");
            $update->bind_param("si", $pickup_pin, $listing_id);
            $update->execute();
        }
        
        header('Location: clerk.php?section=listings');
        exit;
        
    } elseif ($action === 'reject') {
        $listing_id = intval($_POST['listing_id']);
        $reason = $_POST['rejection_reason'] ?? '';
        
        $stmt = $mysqli->prepare("UPDATE technician_listings SET status = 'rejected', clerk_notes = ? WHERE id = ?");
        $stmt->bind_param("si", $reason, $listing_id);
        $stmt->execute();
        
        header('Location: clerk.php?section=listings');
        exit;
        
    } elseif ($action === 'request_revision') {
        $listing_id = intval($_POST['listing_id']);
        $notes = $_POST['clerk_notes'] ?? '';
        
        $stmt = $mysqli->prepare("UPDATE technician_listings SET status = 'needs_revision', clerk_notes = ? WHERE id = ?");
        $stmt->bind_param("si", $notes, $listing_id);
        $stmt->execute();
        
        header('Location: clerk.php?section=listings');
        exit;
        
    } elseif ($action === 'update_listing') {
        $listing_id = intval($_POST['listing_id']);
        $brand = $_POST['brand'] ?? '';
        $name = $_POST['name'] ?? '';
        $category = $_POST['category'] ?? '';
        $condition = $_POST['condition'] ?? '';
        $specs = $_POST['specs'] ?? '';
        $accessories = $_POST['accessories'] ?? '';
        $price = floatval($_POST['price'] ?? 0);
        
        $stmt = $mysqli->prepare("UPDATE technician_listings SET brand = ?, name = ?, category = ?, `condition` = ?, specs = ?, accessories = ?, price = ? WHERE id = ?");
        $stmt->bind_param("ssssssdi", $brand, $name, $category, $condition, $specs, $accessories, $price, $listing_id);
        $stmt->execute();
        
        header('Location: clerk.php?section=listings&view=' . $listing_id);
        exit;
    }
}

// Handle view specific listing
$view_id = isset($_GET['view']) ? intval($_GET['view']) : 0;

if ($view_id > 0) {
    $listing = $mysqli->query("
        SELECT tl.*, u.username as technician_name, u.email as technician_email, u.phone as technician_phone
        FROM technician_listings tl
        JOIN users u ON tl.technician_id = u.id
        WHERE tl.id = $view_id
    ")->fetch_assoc();
    
    if (!$listing) {
        echo '<p>Listing not found.</p>';
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
        
        .status-pending-review {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-approved {
            background: #d4edda;
            color: #155724;
        }
        
        .status-rejected {
            background: #f8d7da;
            color: #721c24;
        }
        
        .status-needs-revision {
            background: #fff3cd;
            color: #856404;
        }
    </style>
    
    <div class="details-container">
        <a href="clerk.php?section=listings" class="back-link">&larr; Back to Listings</a>
        
        <div class="details-card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
                <h2>Listing #<?php echo $listing['id']; ?></h2>
                <span class="status-badge status-<?php echo str_replace('_', '-', $listing['status']); ?>">
                    <?php echo match($listing['status']) { 'pending_review' => 'Pending Review', 'approved' => 'Approved', 'rejected' => 'Rejected', 'needs_revision' => 'Needs Revision', default => ucfirst($listing['status']) }; ?>
                </span>
            </div>
            
            <div class="details-grid">
                <div class="detail-item">
                    <strong>Product Name</strong>
                    <span><?php echo htmlspecialchars($listing['name']); ?></span>
                </div>
                <div class="detail-item">
                    <strong>Brand</strong>
                    <span><?php echo htmlspecialchars($listing['brand']); ?></span>
                </div>
                <div class="detail-item">
                    <strong>Category</strong>
                    <span><?php echo htmlspecialchars($listing['category']); ?></span>
                </div>
                <div class="detail-item">
                    <strong>Condition</strong>
                    <span><?php echo htmlspecialchars($listing['condition']); ?></span>
                </div>
                <div class="detail-item">
                    <strong>Price</strong>
                    <span>RM<?php echo number_format($listing['price'], 2); ?></span>
                </div>
                <div class="detail-item">
                    <strong>Technician</strong>
                    <span><?php echo htmlspecialchars($listing['technician_name']); ?></span>
                </div>
                <div class="detail-item">
                    <strong>Technician Email</strong>
                    <span><?php echo htmlspecialchars($listing['technician_email']); ?></span>
                </div>
                <div class="detail-item">
                    <strong>Technician Phone</strong>
                    <span><?php echo htmlspecialchars($listing['technician_phone'] ?? 'Not provided'); ?></span>
                </div>
                <div class="detail-item">
                    <strong>Submitted</strong>
                    <span><?php echo date('M d, Y', strtotime($listing['created_at'])); ?></span>
                </div>
            </div>
            
            <div class="detail-item" style="margin-top: 20px;">
                <strong>Specs</strong>
                <span><?php echo nl2br(htmlspecialchars($listing['specs'] ?? 'Not specified')); ?></span>
            </div>
            
            <div class="detail-item" style="margin-top: 20px;">
                <strong>Accessories</strong>
                <span><?php echo nl2br(htmlspecialchars($listing['accessories'] ?? 'None')); ?></span>
            </div>
            
            <div class="detail-item" style="margin-top: 20px;">
                <strong>Repairs Done</strong>
                <span><?php echo nl2br(htmlspecialchars($listing['repairs_done'] ?? 'None')); ?></span>
            </div>
            
            <div style="margin-top: 30px;">
                <h3>Images & Media</h3>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-top: 20px;">
                    <?php if ($listing['image1']): ?>
                        <div>
                            <strong>Image 1</strong>
                            <img src="data:image/jpeg;base64,<?php echo base64_encode($listing['image1']); ?>" alt="Product Image 1" style="width: 100%; max-height: 300px; object-fit: cover; border-radius: 8px; margin-top: 10px;">
                        </div>
                    <?php endif; ?>
                    <?php if ($listing['image2']): ?>
                        <div>
                            <strong>Image 2</strong>
                            <img src="data:image/jpeg;base64,<?php echo base64_encode($listing['image2']); ?>" alt="Product Image 2" style="width: 100%; max-height: 300px; object-fit: cover; border-radius: 8px; margin-top: 10px;">
                        </div>
                    <?php endif; ?>
                    <?php if ($listing['image3']): ?>
                        <div>
                            <strong>Image 3</strong>
                            <img src="data:image/jpeg;base64,<?php echo base64_encode($listing['image3']); ?>" alt="Product Image 3" style="width: 100%; max-height: 300px; object-fit: cover; border-radius: 8px; margin-top: 10px;">
                        </div>
                    <?php endif; ?>
                </div>
                <?php if ($listing['video']): ?>
                    <div style="margin-top: 20px;">
                        <strong>Video</strong>
                        <video width="100%" style="max-height: 400px; margin-top: 10px; border-radius: 8px;" controls>
                            <source src="data:video/mp4;base64,<?php echo base64_encode($listing['video']); ?>" type="video/mp4">
                            Your browser does not support the video tag.
                        </video>
                    </div>
                <?php endif; ?>
            </div>
            
            <?php if (isset($listing['clerk_notes']) && $listing['clerk_notes']): ?>
                <div class="detail-item" style="margin-top: 20px; border-left-color: #ffc107;">
                    <strong>Clerk Notes</strong>
                    <span><?php echo nl2br(htmlspecialchars($listing['clerk_notes'])); ?></span>
                </div>
            <?php endif; ?>
            
            <?php if (isset($listing['pickup_pin']) && $listing['pickup_pin']): ?>
                <div style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); padding: 30px; border-radius: 12px; text-align: center; font-size: 48px; font-weight: bold; letter-spacing: 8px; margin: 25px 0; color: white; box-shadow: 0 4px 15px rgba(16, 185, 129, 0.4);">
                    <?php echo htmlspecialchars($listing['pickup_pin']); ?>
                </div>
                <p style="text-align: center; color: #666;">Pickup PIN for customer</p>
            <?php endif; ?>
        
        <?php if ($listing['status'] === 'pending_review'): ?>
            <div class="action-buttons" style="margin-top: 20px;">
                <button class="btn btn-primary" onclick="approveListing(<?php echo $listing['id']; ?>)">Approve</button>
                <button class="btn btn-warning" onclick="requestRevision(<?php echo $listing['id']; ?>)">Request Revision</button>
                <button class="btn btn-danger" onclick="rejectListing(<?php echo $listing['id']; ?>)">Reject</button>
                <button class="btn btn-secondary" onclick="openEditListingModal(<?php echo $listing['id']; ?>)">Edit</button>
            </div>
        <?php elseif ($listing['status'] === 'needs_revision'): ?>
            <div class="action-buttons" style="margin-top: 20px;">
                <button class="btn btn-primary" onclick="approveListing(<?php echo $listing['id']; ?>)">Approve</button>
                <button class="btn btn-danger" onclick="rejectListing(<?php echo $listing['id']; ?>)">Reject</button>
                <button class="btn btn-secondary" onclick="openEditListingModal(<?php echo $listing['id']; ?>)">Edit</button>
            </div>
        <?php endif; ?>
        
        <button class="btn btn-secondary" style="margin-top: 15px;" onclick="window.location.href='clerk.php?section=listings'">Back to List</button>
    </div>
    
    <!-- Edit Listing Modal -->
    <div id="editListingModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit Listing</h3>
                <button class="close-modal" onclick="closeModal('editListingModal')">&times;</button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="update_listing">
                <input type="hidden" name="listing_id" value="<?php echo $listing['id']; ?>">
                
                <div class="form-group">
                    <label for="edit_brand">Brand</label>
                    <input type="text" id="edit_brand" name="brand" value="<?php echo htmlspecialchars($listing['brand']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="edit_name">Product Name</label>
                    <input type="text" id="edit_name" name="name" value="<?php echo htmlspecialchars($listing['name']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="edit_category">Category</label>
                    <select id="edit_category" name="category" required>
                        <option value="Phone" <?php echo $listing['category'] === 'Phone' ? 'selected' : ''; ?>>Phone</option>
                        <option value="Laptop" <?php echo $listing['category'] === 'Laptop' ? 'selected' : ''; ?>>Laptop</option>
                        <option value="Tablet" <?php echo $listing['category'] === 'Tablet' ? 'selected' : ''; ?>>Tablet</option>
                        <option value="Watch" <?php echo $listing['category'] === 'Watch' ? 'selected' : ''; ?>>Watch</option>
                        <option value="Other" <?php echo $listing['category'] === 'Other' ? 'selected' : ''; ?>>Other</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="edit_condition">Condition</label>
                    <select id="edit_condition" name="condition" required>
                        <option value="Excellent" <?php echo $listing['condition'] === 'Excellent' ? 'selected' : ''; ?>>Excellent</option>
                        <option value="Like New" <?php echo $listing['condition'] === 'Like New' ? 'selected' : ''; ?>>Like New</option>
                        <option value="Good" <?php echo $listing['condition'] === 'Good' ? 'selected' : ''; ?>>Good</option>
                        <option value="Fair" <?php echo $listing['condition'] === 'Fair' ? 'selected' : ''; ?>>Fair</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="edit_specs">Specifications</label>
                    <textarea id="edit_specs" name="specs" rows="3"><?php echo htmlspecialchars($listing['specs'] ?? ''); ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="edit_accessories">Accessories</label>
                    <textarea id="edit_accessories" name="accessories" rows="2"><?php echo htmlspecialchars($listing['accessories'] ?? ''); ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="edit_price">Price (RM)</label>
                    <input type="number" id="edit_price" name="price" step="0.01" min="0" value="<?php echo $listing['price']; ?>" required>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('editListingModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
    
    <script src="clerk.js"></script>
    <script>
    function openEditListingModal(listingId) {
        openModal('editListingModal');
    }
    </script>
    
    <?php
    exit;
}

// Get all listings with search
$search = isset($_GET['search']) ? $mysqli->real_escape_string($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $mysqli->real_escape_string($_GET['status']) : '';

$where = "WHERE 1=1";
if ($search) {
    $where .= " AND (tl.name LIKE '%$search%' OR tl.brand LIKE '%$search%' OR u.username LIKE '%$search%')";
}
if ($status_filter) {
    $where .= " AND tl.status = '$status_filter'";
}

$listings = $mysqli->query("
    SELECT tl.*, u.username as technician_name
    FROM technician_listings tl
    JOIN users u ON tl.technician_id = u.id
    $where
    ORDER BY tl.created_at DESC
");
?>

<div class="content-section">
    <h2>Technician Listings</h2>
    
    <div class="search-bar">
        <input type="text" id="searchInput" placeholder="Search by product, brand, or technician..." value="<?php echo htmlspecialchars($search); ?>">
        <select id="statusFilter">
            <option value="">All Status</option>
            <option value="pending_review" <?php echo $status_filter === 'pending_review' ? 'selected' : ''; ?>>Pending Review</option>
            <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>Approved</option>
            <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
            <option value="needs_revision" <?php echo $status_filter === 'needs_revision' ? 'selected' : ''; ?>>Needs Revision</option>
        </select>
        <button class="btn btn-primary" onclick="applyFilters()">Search</button>
    </div>
    
    <?php if ($listings && $listings->num_rows > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Product</th>
                    <th>Brand</th>
                    <th>Category</th>
                    <th>Technician</th>
                    <th>Price</th>
                    <th>Status</th>
                    <th>Submitted</th>
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
                        <td><?php echo htmlspecialchars($listing['technician_name']); ?></td>
                        <td>RM<?php echo number_format($listing['price'], 2); ?></td>
                        <td><span class="status-badge status-<?php echo str_replace('_', '-', $listing['status']); ?>"><?php echo match($listing['status']) { 'pending_review' => 'Pending Review', 'approved' => 'Approved', 'rejected' => 'Rejected', 'needs_revision' => 'Needs Revision', default => ucfirst($listing['status']) }; ?></span></td>
                        <td><?php echo date('M d, Y', strtotime($listing['created_at'])); ?></td>
                        <td>
                            <button class="btn btn-sm btn-primary" onclick="window.location.href='clerk.php?section=listings&view=<?php echo $listing['id']; ?>'">View</button>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>No listings found.</p>
    <?php endif; ?>
</div>

<script>
function applyFilters() {
    const search = document.getElementById('searchInput').value;
    const status = document.getElementById('statusFilter').value;
    window.location.href = 'clerk.php?section=listings&search=' + encodeURIComponent(search) + '&status=' + encodeURIComponent(status);
}
</script>
