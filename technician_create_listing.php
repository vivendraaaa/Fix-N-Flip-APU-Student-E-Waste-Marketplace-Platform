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
$repair_id = isset($_GET['repair_id']) ? intval($_GET['repair_id']) : 0;

// Get repair request details
$repair = $mysqli->query("
    SELECT rr.*, ds.device_type, ds.brand, ds.model, ds.storage, ds.ram, ds.battery_health, ds.device_condition, ds.accessories, ds.description, ds.estimated_price as purchase_price
    FROM repair_requests rr
    JOIN device_submissions ds ON rr.submission_id = ds.id
    WHERE rr.id = $repair_id AND rr.technician_id = $technician_id
")->fetch_assoc();

if (!$repair) {
    echo '<p>Repair request not found.</p>';
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $brand = $_POST['brand'] ?? $repair['brand'];
    $name = $_POST['name'] ?? ($repair['brand'] . ' ' . $repair['model']);
    $category = $_POST['category'] ?? $repair['device_type'];
    $condition = $_POST['condition'] ?? 'Good';
    $specs = $_POST['specs'] ?? "Storage: {$repair['storage']}, RAM: {$repair['ram']}, Battery: {$repair['battery_health']}%";
    $accessories = $_POST['accessories'] ?? $repair['accessories'];
    $price = floatval($_POST['price'] ?? 0);
    $repairs_done = $_POST['repairs_done'] ?? 'Device repaired and tested';

    // Process image uploads
    $image1_data = null;
    $image2_data = null;
    $image3_data = null;
    $video_data = null;

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

        $stmt = $mysqli->prepare("INSERT INTO technician_listings
            (id, repair_request_id, technician_id, brand, title, name, category, `condition`, description, specs, accessories, price, repairs_done, image1, image2, image3, video, status)
            VALUES (NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, $image1_hex, $image2_hex, $image3_hex, $video_hex, 'pending_review')");
        $description = $repairs_done;
        $stmt->bind_param("iissssssssds", $repair_id, $technician_id, $brand, $name, $name, $category, $condition, $description, $specs, $accessories, $price, $repairs_done);
        $insert = $stmt->execute();

        if ($insert) {
            $update = $mysqli->prepare("UPDATE repair_requests SET status = 'completed', repair_completed_at = NOW() WHERE id = ? AND technician_id = ?");
            $update->bind_param("ii", $repair_id, $technician_id);
            $update->execute();
            if (!empty($repair['submission_id'])) {
                $mysqli->query("UPDATE device_submissions SET status = 'completed' WHERE id = " . intval($repair['submission_id']));
            }
            header('Location: technician.php?section=listings');
            exit;
        } else {
            $error = "Error creating listing: " . $mysqli->error;
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
</style>

<div class="form-container">
    <h2>Create Product Listing</h2>
    
    <?php if (isset($error)): ?>
        <div class="error"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <div class="device-info">
        <h3>Device Information</h3>
        <p><strong>Device Type:</strong> <?php echo htmlspecialchars($repair['device_type']); ?></p>
        <p><strong>Brand:</strong> <?php echo htmlspecialchars($repair['brand']); ?></p>
        <p><strong>Model:</strong> <?php echo htmlspecialchars($repair['model']); ?></p>
        <p><strong>Storage:</strong> <?php echo htmlspecialchars($repair['storage']); ?></p>
        <p><strong>RAM:</strong> <?php echo htmlspecialchars($repair['ram']); ?></p>
        <p><strong>Battery Health:</strong> <?php echo htmlspecialchars($repair['battery_health']); ?>%</p>
        <p><strong>Original Condition:</strong> <?php echo htmlspecialchars($repair['device_condition']); ?></p>
        <?php if ($repair['description']): ?>
            <p><strong>Description:</strong> <?php echo htmlspecialchars($repair['description']); ?></p>
        <?php endif; ?>
    </div>
    
    <form method="POST" enctype="multipart/form-data">
        <div class="form-row">
            <div class="form-group">
                <label for="brand">Brand *</label>
                <input type="text" id="brand" name="brand" value="<?php echo htmlspecialchars($repair['brand']); ?>" required>
            </div>
            <div class="form-group">
                <label for="name">Product Name *</label>
                <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($repair['brand'] . ' ' . $repair['model']); ?>" required>
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label for="category">Category *</label>
                <select id="category" name="category" required>
                    <option value="Smartphone" <?php echo $repair['device_type'] === 'Smartphone' ? 'selected' : ''; ?>>Smartphone</option>
                    <option value="Tablet" <?php echo $repair['device_type'] === 'Tablet' ? 'selected' : ''; ?>>Tablet</option>
                    <option value="Laptop" <?php echo $repair['device_type'] === 'Laptop' ? 'selected' : ''; ?>>Laptop</option>
                    <option value="Desktop" <?php echo $repair['device_type'] === 'Desktop' ? 'selected' : ''; ?>>Desktop</option>
                    <option value="Smartwatch" <?php echo $repair['device_type'] === 'Smartwatch' ? 'selected' : ''; ?>>Smartwatch</option>
                </select>
            </div>
            <div class="form-group">
                <label for="condition">Condition *</label>
                <select id="condition" name="condition" required>
                    <option value="Like New">Like New</option>
                    <option value="Excellent">Excellent</option>
                    <option value="Good" selected>Good</option>
                    <option value="Fair">Fair</option>
                    <option value="Poor">Poor</option>
                </select>
            </div>
        </div>
        
        <div class="form-group">
            <label for="specs">Specifications *</label>
            <textarea id="specs" name="specs" required><?php echo htmlspecialchars("Storage: {$repair['storage']}, RAM: {$repair['ram']}, Battery: {$repair['battery_health']}%"); ?></textarea>
        </div>
        
        <div class="form-group">
            <label for="accessories">Accessories</label>
            <textarea id="accessories" name="accessories"><?php echo htmlspecialchars($repair['accessories'] ?? ''); ?></textarea>
        </div>
        
        <div class="form-group">
            <label for="repairs_done">Repairs Done *</label>
            <textarea id="repairs_done" name="repairs_done" required>Device repaired and tested</textarea>
        </div>
        
        <div class="form-group">
            <label for="price">Price (RM) *</label>
            <p style="font-size: 13px; color: #666; margin-bottom: 8px;">
                <strong>Estimated Selling Price:</strong> RM <span id="estimatedPrice">--</span> (for profit after repair)
            </p>
            <input type="number" id="price" name="price" step="0.01" min="0" required>
        </div>

        <div class="form-group">
            <label>Upload Product Images (2-3 images) *</label>
            <div class="file-upload-container" style="border: 2px dashed #ddd; border-radius: 8px; padding: 20px; text-align: center; background: #f8f9fa;">
                <input type="file" id="images" name="images[]" accept="image/*" multiple required style="display: none;">
                <div class="upload-placeholder" onclick="document.getElementById('images').click()" style="cursor: pointer;">
                    <span style="font-size: 40px;">📷</span>
                    <p>Click to upload images</p>
                    <p style="font-size: 12px; color: #666;">2-3 images required</p>
                </div>
                <div id="imagePreview" style="display: flex; gap: 10px; margin-top: 15px; flex-wrap: wrap;"></div>
            </div>
        </div>

        <div class="form-group">
            <label>Upload Product Video</label>
            <div class="file-upload-container" style="border: 2px dashed #ddd; border-radius: 8px; padding: 20px; text-align: center; background: #f8f9fa;">
                <input type="file" id="video" name="video" accept="video/*" style="display: none;">
                <div class="upload-placeholder" onclick="document.getElementById('video').click()" style="cursor: pointer;">
                    <span style="font-size: 40px;">🎥</span>
                    <p>Click to upload video (optional)</p>
                </div>
                <div id="videoPreview" style="margin-top: 15px;"></div>
            </div>
        </div>

        <div style="display: flex; gap: 10px; margin-top: 30px;">
            <button type="submit" class="btn btn-primary">Create Listing</button>
            <button type="button" class="btn btn-secondary" onclick="window.location.href='technician.php?section=repairs'">Cancel</button>
        </div>
    </form>
</div>

<script>
// Calculate estimated selling price
function calculateEstimatedPrice() {
    const deviceType = '<?php echo htmlspecialchars($repair['device_type']); ?>';
    const condition = document.getElementById('condition').value;
    const storage = '<?php echo htmlspecialchars($repair['storage']); ?>';
    const ram = '<?php echo htmlspecialchars($repair['ram']); ?>';
    const batteryHealth = parseInt('<?php echo htmlspecialchars($repair['battery_health']); ?>');
    const purchasePrice = parseFloat('<?php echo htmlspecialchars($repair['purchase_price'] ?? 0); ?>');

    // Base prices by device type
    let basePrice = 0;
    switch(deviceType) {
        case 'Smartphone': basePrice = 800; break;
        case 'Tablet': basePrice = 600; break;
        case 'Laptop': basePrice = 1200; break;
        case 'Desktop': basePrice = 1000; break;
        case 'Smartwatch': basePrice = 300; break;
        default: basePrice = 500;
    }

    // Condition multiplier
    const conditionMultipliers = {
        'Like New': 1.0,
        'Excellent': 0.9,
        'Good': 0.75,
        'Fair': 0.6,
        'Poor': 0.4
    };
    const conditionMultiplier = conditionMultipliers[condition] || 0.75;

    // Storage bonus
    let storageBonus = 0;
    if (storage.includes('512') || storage.includes('500')) storageBonus = 200;
    else if (storage.includes('256')) storageBonus = 100;
    else if (storage.includes('128')) storageBonus = 50;

    // RAM bonus
    let ramBonus = 0;
    if (ram.includes('16') || ram.includes('32')) ramBonus = 150;
    else if (ram.includes('12')) ramBonus = 100;
    else if (ram.includes('8')) ramBonus = 50;

    // Battery health bonus (for smartphones/tablets)
    let batteryBonus = 0;
    if (deviceType === 'Smartphone' || deviceType === 'Tablet') {
        if (batteryHealth >= 90) batteryBonus = 100;
        else if (batteryHealth >= 80) batteryBonus = 50;
        else if (batteryHealth >= 70) batteryBonus = 0;
        else batteryBonus = -50;
    }

    // Calculate final estimated price
    let estimatedPrice = (basePrice * conditionMultiplier) + storageBonus + ramBonus + batteryBonus;
    estimatedPrice = Math.round(estimatedPrice);

    // Ensure minimum 30% profit margin over purchase price
    const minProfitPrice = purchasePrice * 1.3;
    if (estimatedPrice < minProfitPrice) {
        estimatedPrice = Math.round(minProfitPrice);
    }

    // Update display
    document.getElementById('estimatedPrice').textContent = estimatedPrice;
}

// Calculate on page load and when condition changes
calculateEstimatedPrice();
document.getElementById('condition').addEventListener('change', calculateEstimatedPrice);

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
