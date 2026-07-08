<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
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
$listing_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Get listing details
$listing = $mysqli->query("
    SELECT tl.*, rr.submission_id, ds.device_type, ds.brand as original_brand, ds.model as original_model, ds.storage, ds.ram, ds.battery_health
    FROM technician_listings tl
    LEFT JOIN repair_requests rr ON tl.repair_request_id = rr.id
    LEFT JOIN device_submissions ds ON rr.submission_id = ds.id
    WHERE tl.id = $listing_id AND tl.technician_id = $technician_id
")->fetch_assoc();

if (!$listing) {
    echo '<p>Listing not found.</p>';
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $brand = $_POST['brand'] ?? $listing['brand'];
    $name = $_POST['name'] ?? $listing['name'];
    $category = $_POST['category'] ?? $listing['category'];
    $condition = $_POST['condition'] ?? $listing['condition'];
    $specs = $_POST['specs'] ?? $listing['specs'];
    $accessories = $_POST['accessories'] ?? $listing['accessories'];
    $price = floatval($_POST['price'] ?? $listing['price']);
    $repairs_done = $_POST['repairs_done'] ?? $listing['repairs_done'];

    // Process image uploads
    $image1_data = $listing['image1'];
    $image2_data = $listing['image2'];
    $image3_data = $listing['image3'];
    $video_data = $listing['video'];

    $allowed_image_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $allowed_video_types = ['video/mp4', 'video/avi', 'video/mov'];

    // Process images
    if (isset($_FILES['images']) && is_array($_FILES['images']['name'])) {
        $image_files = $_FILES['images'];
        for ($i = 0; $i < count($image_files['name']); $i++) {
            if ($image_files['error'][$i] == 0) {
                $file_type = $image_files['type'][$i];
                if (in_array($file_type, $allowed_image_types)) {
                    $image_data = file_get_contents($image_files['tmp_name'][$i]);
                    if ($i == 0) $image1_data = $image_data;
                    elseif ($i == 1) $image2_data = $image_data;
                    elseif ($i == 2) $image3_data = $image_data;
                }
            }
        }
    }

    // Process video
    if (isset($_FILES['video']) && $_FILES['video']['error'] == 0) {
        $file_type = $_FILES['video']['type'];
        if (in_array($file_type, $allowed_video_types)) {
            $video_data = file_get_contents($_FILES['video']['tmp_name']);
        }
    }

    if ($price > 0) {
        // Use hex encoding for binary data
        $image1_hex = $image1_data ? '0x' . bin2hex($image1_data) : 'NULL';
        $image2_hex = $image2_data ? '0x' . bin2hex($image2_data) : 'NULL';
        $image3_hex = $image3_data ? '0x' . bin2hex($image3_data) : 'NULL';
        $video_hex = $video_data ? '0x' . bin2hex($video_data) : 'NULL';

        $update = $mysqli->query("
            UPDATE technician_listings
            SET brand = '$brand', name = '$name', category = '$category', `condition` = '$condition', 
                specs = '$specs', accessories = '$accessories', price = $price, repairs_done = '$repairs_done',
                image1 = $image1_hex, image2 = $image2_hex, image3 = $image3_hex, video = $video_hex,
                status = 'pending_review', clerk_notes = NULL
            WHERE id = $listing_id AND technician_id = $technician_id
        ");

        if ($update) {
            header('Location: technician.php?section=listings');
            exit;
        } else {
            $error = "Error updating listing: " . $mysqli->error;
        }
    } else {
        $error = "Please enter a valid price.";
    }
}
?>

<style>
    .form-container {
        max-width: 800px;
        margin: 0 auto;
        padding: 30px;
        background: white;
        border-radius: 12px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    
    .form-group {
        margin-bottom: 20px;
    }
    
    .form-group label {
        display: block;
        margin-bottom: 8px;
        font-weight: 600;
        color: #333;
    }
    
    .form-group input,
    .form-group select,
    .form-group textarea {
        width: 100%;
        padding: 12px;
        border: 1px solid #ddd;
        border-radius: 6px;
        font-size: 14px;
        box-sizing: border-box;
    }
    
    .form-group textarea {
        min-height: 100px;
        resize: vertical;
    }
    
    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
    }
    
    .btn {
        padding: 12px 24px;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        font-size: 14px;
        font-weight: 600;
        transition: all 0.3s;
    }
    
    .btn-primary {
        background: #10b981;
        color: white;
    }
    
    .btn-primary:hover {
        background: #059669;
    }
    
    .btn-secondary {
        background: #6c757d;
        color: white;
    }
    
    .btn-secondary:hover {
        background: #5a6268;
    }
    
    .device-info {
        background: #f8f9fa;
        padding: 20px;
        border-radius: 8px;
        margin-bottom: 25px;
        border-left: 4px solid #10b981;
    }
    
    .device-info h3 {
        margin-top: 0;
        color: #10b981;
    }
    
    .error {
        background: #fee2e2;
        color: #dc2626;
        padding: 12px;
        border-radius: 6px;
        margin-bottom: 20px;
        border-left: 4px solid #dc2626;
    }
    
    .info {
        background: #dbeafe;
        color: #1e40af;
        padding: 12px;
        border-radius: 6px;
        margin-bottom: 20px;
        border-left: 4px solid #1e40af;
    }
</style>

<div class="form-container">
    <h2>Edit Product Listing</h2>
    
    <?php if (isset($error)): ?>
        <div class="error"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <div class="info">
        <strong>Note:</strong> After editing, this listing will be reset to "Pending Review" status and will require clerk approval again.
    </div>
    
    <div class="device-info">
        <h3>Device Information</h3>
        <p><strong>Device Type:</strong> <?php echo htmlspecialchars($listing['device_type'] ?? 'N/A'); ?></p>
        <p><strong>Original Brand:</strong> <?php echo htmlspecialchars($listing['original_brand'] ?? 'N/A'); ?></p>
        <p><strong>Original Model:</strong> <?php echo htmlspecialchars($listing['original_model'] ?? 'N/A'); ?></p>
        <p><strong>Storage:</strong> <?php echo htmlspecialchars($listing['storage'] ?? 'N/A'); ?></p>
        <p><strong>RAM:</strong> <?php echo htmlspecialchars($listing['ram'] ?? 'N/A'); ?></p>
        <p><strong>Battery Health:</strong> <?php echo htmlspecialchars($listing['battery_health'] ?? 'N/A'); ?>%</p>
    </div>
    
    <form method="POST" enctype="multipart/form-data">
        <div class="form-row">
            <div class="form-group">
                <label for="brand">Brand *</label>
                <input type="text" id="brand" name="brand" value="<?php echo htmlspecialchars($listing['brand']); ?>" required>
            </div>
            <div class="form-group">
                <label for="name">Product Name *</label>
                <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($listing['name']); ?>" required>
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label for="category">Category *</label>
                <select id="category" name="category" required>
                    <option value="Smartphone" <?php echo $listing['category'] === 'Smartphone' ? 'selected' : ''; ?>>Smartphone</option>
                    <option value="Tablet" <?php echo $listing['category'] === 'Tablet' ? 'selected' : ''; ?>>Tablet</option>
                    <option value="Laptop" <?php echo $listing['category'] === 'Laptop' ? 'selected' : ''; ?>>Laptop</option>
                    <option value="Desktop" <?php echo $listing['category'] === 'Desktop' ? 'selected' : ''; ?>>Desktop</option>
                    <option value="Smartwatch" <?php echo $listing['category'] === 'Smartwatch' ? 'selected' : ''; ?>>Smartwatch</option>
                </select>
            </div>
            <div class="form-group">
                <label for="condition">Condition *</label>
                <select id="condition" name="condition" required>
                    <option value="Like New" <?php echo $listing['condition'] === 'Like New' ? 'selected' : ''; ?>>Like New</option>
                    <option value="Excellent" <?php echo $listing['condition'] === 'Excellent' ? 'selected' : ''; ?>>Excellent</option>
                    <option value="Good" <?php echo $listing['condition'] === 'Good' ? 'selected' : ''; ?>>Good</option>
                    <option value="Fair" <?php echo $listing['condition'] === 'Fair' ? 'selected' : ''; ?>>Fair</option>
                    <option value="Poor" <?php echo $listing['condition'] === 'Poor' ? 'selected' : ''; ?>>Poor</option>
                </select>
            </div>
        </div>
        
        <div class="form-group">
            <label for="specs">Specifications *</label>
            <textarea id="specs" name="specs" required><?php echo htmlspecialchars($listing['specs']); ?></textarea>
        </div>
        
        <div class="form-group">
            <label for="accessories">Accessories</label>
            <textarea id="accessories" name="accessories"><?php echo htmlspecialchars($listing['accessories'] ?? ''); ?></textarea>
        </div>
        
        <div class="form-group">
            <label for="repairs_done">Repairs Done *</label>
            <textarea id="repairs_done" name="repairs_done" required><?php echo htmlspecialchars($listing['repairs_done']); ?></textarea>
        </div>
        
        <div class="form-group">
            <label for="price">Price (RM) *</label>
            <input type="number" id="price" name="price" step="0.01" min="0" value="<?php echo htmlspecialchars($listing['price']); ?>" required>
        </div>

        <div class="form-group">
            <label>Update Product Images (Leave empty to keep existing images)</label>
            <div class="file-upload-container" style="border: 2px dashed #ddd; border-radius: 8px; padding: 20px; text-align: center; background: #f8f9fa;">
                <input type="file" id="images" name="images[]" accept="image/*" multiple style="display: none;">
                <div class="upload-placeholder" onclick="document.getElementById('images').click()" style="cursor: pointer;">
                    <span style="font-size: 40px;">📷</span>
                    <p>Click to upload new images</p>
                    <p style="font-size: 12px; color: #666;">Leave empty to keep existing images</p>
                </div>
                <div id="imagePreview" style="display: flex; gap: 10px; margin-top: 15px; flex-wrap: wrap;"></div>
            </div>
        </div>

        <div class="form-group">
            <label>Update Product Video (Leave empty to keep existing video)</label>
            <div class="file-upload-container" style="border: 2px dashed #ddd; border-radius: 8px; padding: 20px; text-align: center; background: #f8f9fa;">
                <input type="file" id="video" name="video" accept="video/*" style="display: none;">
                <div class="upload-placeholder" onclick="document.getElementById('video').click()" style="cursor: pointer;">
                    <span style="font-size: 40px;">🎥</span>
                    <p>Click to upload new video (optional)</p>
                    <p style="font-size: 12px; color: #666;">Leave empty to keep existing video</p>
                </div>
                <div id="videoPreview" style="margin-top: 15px;"></div>
            </div>
        </div>

        <div style="display: flex; gap: 10px; margin-top: 30px;">
            <button type="submit" class="btn btn-primary">Update Listing</button>
            <button type="button" class="btn btn-secondary" onclick="window.location.href='technician.php?section=listings'">Cancel</button>
        </div>
    </form>
</div>

<script>
// Image preview
document.getElementById('images').addEventListener('change', function(e) {
    const preview = document.getElementById('imagePreview');
    preview.innerHTML = '';
    const files = e.target.files;
    for (let i = 0; i < files.length; i++) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const img = document.createElement('img');
            img.src = e.target.result;
            img.style.width = '100px';
            img.style.height = '100px';
            img.style.objectFit = 'cover';
            img.style.borderRadius = '6px';
            img.style.border = '1px solid #ddd';
            preview.appendChild(img);
        }
        reader.readAsDataURL(files[i]);
    }
});

// Video preview
document.getElementById('video').addEventListener('change', function(e) {
    const preview = document.getElementById('videoPreview');
    preview.innerHTML = '';
    const file = e.target.files[0];
    if (file) {
        const video = document.createElement('video');
        video.src = URL.createObjectURL(file);
        video.style.width = '200px';
        video.style.borderRadius = '6px';
        video.controls = true;
        preview.appendChild(video);
    }
});
</script>
