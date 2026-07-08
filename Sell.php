<?php
session_start();

// Database connection
$mysqli = new mysqli("localhost", "root", "", "fyp");
if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

// Include price scraper
require_once 'price_scraper.php';

// Restore device type selection if user was redirected from login
$restored_device_type = '';
if (isset($_SESSION['sell_device_type'])) {
    $restored_device_type = $_SESSION['sell_device_type'];
    unset($_SESSION['sell_device_type']);
}

// Fetch cart count
$cart_count = 0;
$notification_count = 0;
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in']) {
    $user_id = $_SESSION['user_id'];
    $check_table = "SHOW TABLES LIKE 'cart'";
    $table_result = $mysqli->query($check_table);
    if ($table_result && $table_result->num_rows > 0) {
        $query = "SELECT SUM(quantity) as total FROM cart WHERE user_id = ?";
        $stmt = $mysqli->prepare($query);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $cart_count = $row['total'] ?: 0;
    }

    // Fetch unread notification count
    $check_table = "SHOW TABLES LIKE 'notifications'";
    $table_result = $mysqli->query($check_table);
    if ($table_result && $table_result->num_rows > 0) {
        $query = "SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND is_read = FALSE";
        $stmt = $mysqli->prepare($query);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $notification_count = $row['total'] ?: 0;
    }
}

// Handle form submission
$message = "";
$estimate = 0;

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_device'])) {
    // Debug: Log received POST data
    error_log("POST data: " . print_r($_POST, true));
    
    // Process device submission
    $device_type = $_POST['device_type'] ?? '';
    error_log("Device type received: " . $device_type);
    
    if (empty($device_type)) {
        $message = "Error: Device type is required.";
    } else {
        $brand = $_POST['brand'];
    $model = '';
    // Handle model: check model_text (laptop or custom from dialog) or model dropdown
    if (isset($_POST['model_text']) && !empty($_POST['model_text'])) {
        $model = $_POST['model_text'];
    } elseif (isset($_POST['model']) && !empty($_POST['model'])) {
        $model = $_POST['model'];
    }
    // Handle storage: check storage_custom (other) or storage dropdown
    $storage = $_POST['storage'];
    if ($storage === 'other' && isset($_POST['storage_custom']) && !empty($_POST['storage_custom'])) {
        $storage = $_POST['storage_custom'];
    }
    // Handle RAM: check ram_custom (other) or ram dropdown
    $ram = $_POST['ram'];
    if ($ram === 'other' && isset($_POST['ram_custom']) && !empty($_POST['ram_custom'])) {
        $ram = $_POST['ram_custom'];
    }
    $battery_health = $_POST['battery_health'];
    $accessories = $_POST['accessories'];
    $description = $_POST['description'];
    
    // Collect question answers
    $question_answers = [];
    foreach ($_POST as $key => $value) {
        if (strpos($key, 'question_') === 0) {
            // Handle checkbox arrays (e.g., question_problems[])
            if (is_array($value)) {
                $question_answers[$key] = implode(',', $value);
            } else {
                $question_answers[$key] = $value;
            }
        }
    }
    
    // AI-like pricing: Use web scraping for real-time prices
    $model_lower = strtolower($model);
    $brand_lower = strtolower($brand);
    
    // Get device condition from question answers
    $condition = 'Good';
    if (isset($question_answers['question_screen']) && $question_answers['question_screen'] === 'yes') {
        $condition = 'Fair';
    }
    if (isset($question_answers['question_water']) && $question_answers['question_water'] === 'yes') {
        $condition = 'Poor';
    }
    if (isset($question_answers['question_battery']) && $question_answers['question_battery'] === 'no') {
        $condition = 'Fair';
    }
    
    // Use price scraper to get real-time pricing
    $estimate = getUsedDevicePrice($device_type, $brand, $model, $condition);
    
    // Battery health adjustment (additional factor on top of scraped price)
    $battery_health_num = floatval($battery_health);
    $device_type_lower = strtolower($device_type);
    if (in_array($device_type_lower, ['smartphone', 'tablet', 'smartwatch'])) {
        $estimate *= ($battery_health_num / 100);
    }
    
    // Storage bonus
    $storage_num = floatval($storage);
    $storage_multiplier = ($device_type_lower === 'laptop') ? 0.05 : 0.08;
    if ($storage_num >= 1024) $estimate *= (1 + $storage_multiplier * 4);
    elseif ($storage_num >= 512) $estimate *= (1 + $storage_multiplier * 3);
    elseif ($storage_num >= 256) $estimate *= (1 + $storage_multiplier * 2);
    elseif ($storage_num >= 128) $estimate *= (1 + $storage_multiplier);
    
    // RAM bonus
    $ram_num = floatval($ram);
    if ($ram_num >= 32) $estimate *= 1.2;
    elseif ($ram_num >= 16) $estimate *= 1.15;
    elseif ($ram_num >= 12) $estimate *= 1.1;
    elseif ($ram_num >= 8) $estimate *= 1.05;
    
    // Accessories bonus
    $accessories_lower = strtolower($accessories);
    $accessory_bonus = 0;
    if (strpos($accessories_lower, 'box') !== false) $accessory_bonus += 50;
    if (strpos($accessories_lower, 'charger') !== false) $accessory_bonus += 30;
    if (strpos($accessories_lower, 'case') !== false) $accessory_bonus += 20;
    if (strpos($accessories_lower, 'headphone') !== false) $accessory_bonus += 25;
    if (strpos($accessories_lower, 'cable') !== false) $accessory_bonus += 15;
    
    $estimate += $accessory_bonus;
    
    // Ensure minimum price
    $estimate = max(50, $estimate);
    
    // Determine condition for database
    $condition = 'Good';
    if (isset($question_answers['question_screen'])) {
        if ($question_answers['question_screen'] == 'none') {
            $condition = 'Excellent';
        } elseif ($question_answers['question_screen'] == 'deep_scratches' || $question_answers['question_screen'] == 'cracked') {
            $condition = 'Fair';
        }
    }

    $estimate = round($estimate, 2);

    // Insert into database
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;
    $condition_escaped = $mysqli->real_escape_string($condition);
    $accessories_escaped = $mysqli->real_escape_string($accessories);
    
    // Read image files as binary data
    $image1_data = null;
    $image2_data = null;
    $image3_data = null;
    $video_data = null;
    
    $allowed_image_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $allowed_video_types = ['video/mp4', 'video/avi', 'video/mov'];
    
    error_log("FILES received: " . print_r($_FILES, true));
    error_log("POST data: " . print_r($_POST, true));
    
    // Read images from single input with multiple files
    if (isset($_FILES['image_1']) && is_array($_FILES['image_1']['name'])) {
        $image_files = $_FILES['image_1'];
        error_log("Image files array: " . print_r($image_files, true));
        
        for ($i = 0; $i < count($image_files['name']); $i++) {
            if ($image_files['error'][$i] == 0) {
                $file_type = $image_files['type'][$i];
                error_log("Processing image $i: type=$file_type, size=" . $image_files['size'][$i]);
                
                if (in_array($file_type, $allowed_image_types)) {
                    $image_data = file_get_contents($image_files['tmp_name'][$i]);
                    
                    if ($i == 0) $image1_data = $image_data;
                    elseif ($i == 1) $image2_data = $image_data;
                    elseif ($i == 2) $image3_data = $image_data;
                }
            }
        }
    } else {
        error_log("image_1 not set");
    }
    
    if (isset($_FILES['video']) && $_FILES['video']['error'] == 0) {
        $file_type = $_FILES['video']['type'];
        if (in_array($file_type, $allowed_video_types)) {
            $video_data = file_get_contents($_FILES['video']['tmp_name']);
            error_log("Video data processed successfully, length: " . strlen($video_data));
        } else {
            error_log("Video type not allowed: $file_type");
        }
    }
    
    error_log("Before INSERT - device_type value: " . $device_type);
    
    // Use direct query with hex encoding for binary data
    $image1_hex = $image1_data ? '0x' . bin2hex($image1_data) : 'NULL';
    $image2_hex = $image2_data ? '0x' . bin2hex($image2_data) : 'NULL';
    $image3_hex = $image3_data ? '0x' . bin2hex($image3_data) : 'NULL';
    $video_hex = $video_data ? '0x' . bin2hex($video_data) : 'NULL';
    
    $insert = $mysqli->query("INSERT INTO device_submissions (user_id, device_type, brand, model, device_condition, storage, ram, battery_health, screen_condition, accessories, description, estimated_price, image1, image2, image3, video, status, submitted_at) VALUES ('$user_id', '$device_type', '$brand', '$model', '$condition_escaped', '$storage', '$ram', '$battery_health', '', '$accessories_escaped', '$description', '$estimate', $image1_hex, $image2_hex, $image3_hex, $video_hex, 'pending', NOW())");
    
    error_log("INSERT result: " . ($insert ? "SUCCESS" : "FAILED"));
    if (!$insert) {
        error_log("INSERT error: " . $mysqli->error);
    }

    if ($insert) {
        $submission_id = $mysqli->insert_id;
        $show_success_modal = true;

        // Add notification for submission
        $notification_message = "Your device submission has been received. Device: $brand $model. Estimated price: RM$estimate. We will review it shortly.";
        $mysqli->query("INSERT INTO notifications (user_id, message, type, created_at) VALUES ($user_id, '$notification_message', 'submission', NOW())");
    } else {
        $message = "Error submitting device. Please try again.";
    }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fix N Flip - Sell Your Devices</title>
    <link rel="stylesheet" href="sell.css?v=20260706">
    <script src="toast.js"></script>
    <script src="sell.js?v=20260706" defer></script>
    <script>
        // Check if user is logged in
        const isLoggedIn = <?php echo isset($_SESSION['logged_in']) && $_SESSION['logged_in'] ? 'true' : 'false'; ?>;

        // Restore device type selection if user was redirected from login
        <?php if (!empty($restored_device_type)): ?>
            const restoredDeviceType = '<?php echo $restored_device_type; ?>';
            window.addEventListener('DOMContentLoaded', function() {
                const deviceTypeCards = document.querySelectorAll('.device-type-card');
                deviceTypeCards.forEach(card => {
                    if (card.dataset.type === restoredDeviceType) {
                        card.click();
                    }
                });
            });
        <?php endif; ?>

        // Add click handler to device type cards
        window.addEventListener('DOMContentLoaded', function() {
            const deviceTypeCards = document.querySelectorAll('.device-type-card');
            deviceTypeCards.forEach(card => {
                card.addEventListener('click', function(e) {
                    if (!isLoggedIn) {
                        e.preventDefault();
                        e.stopPropagation();
                        // Store selected device type in session via AJAX
                        const deviceType = this.dataset.type;
                        fetch('store_device_type.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: 'device_type=' + encodeURIComponent(deviceType)
                        }).then(() => {
                            // Show login modal - no way to close except Login button
                            document.getElementById('loginModal').style.display = 'flex';
                        });
                    }
                });
            });
        });
    </script>
    <script src="https://cdn.botpress.cloud/webchat/v3.6/inject.js"></script>
    <script src="https://files.bpcontent.cloud/2026/06/29/06/20260629061033-TO72SUAN.js" defer></script>
</head>
<body>
    <header>
        <h1>Fix N Flip</h1>
        <p>Buy & Sell Used Devices for APU Students</p>
    </header>
    <nav>
        <ul>
            <li><a href="Index.php">Home</a></li>
            <li><a href="Buy.php">Buy</a></li>
            <li><a href="Sell.php">Sell</a></li>
            <li><a href="AboutUs.php">About Us</a></li>
        </ul>
        <ul>
            <li><a href="AddToCart.php">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M3 3H5L5.4 5M7 13H17L21 5H5.4M7 13L5.4 5M7 13L4.707 15.293C4.077 15.923 4.523 17 5.414 17H17M17 17C15.895 17 15 17.895 15 19C15 20.105 15.895 21 17 21C18.105 21 19 20.105 19 19C19 17.895 18.105 17 17 17ZM9 19C9 20.105 8.105 21 7 21C5.895 21 5 20.105 5 19C5 17.895 5.895 17 7 17C8.105 17 9 17.895 9 19Z" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                </svg>
                Cart
                <?php if ($cart_count > 0): ?>
                    <span class="cart-badge"><?php echo $cart_count; ?></span>
                <?php endif; ?>
            </a></li>
            <?php if (isset($_SESSION['logged_in']) && $_SESSION['logged_in']): ?>
                <li><a href="notifications.php">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                        <path d="M13.73 21a2 2 0 0 1-3.46 0" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                    </svg>
                    Notifications
                    <?php if ($notification_count > 0): ?>
                        <span class="cart-badge"><?php echo $notification_count; ?></span>
                    <?php endif; ?>
                </a></li>
            <?php endif; ?>
            <li>
                <?php if (isset($_SESSION['logged_in']) && $_SESSION['logged_in']): ?>
                    <a href="profile.php">
                        <img src="<?php echo $_SESSION['pfp']; ?>" alt="Profile" class="profile-pic">
                        <?php echo $_SESSION['username']; ?>
                    </a>
                <?php else: ?>
                    <a href="Login.php">Sign Up / Log In</a>
                <?php endif; ?>
            </li>
        </ul>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <h1>Sell Your Used Devices with Fix N Flip</h1>
        <p>Get the best value for your old smartphones, tablets, and gadgets. Quick, secure, and hassle-free selling process.</p>
        <button class="sell-button" onclick="scrollToForm()">Sell Your Devices NOW</button>
    </section>

    <!-- How It Works -->
    <section class="how-it-works">
        <h2>How It Works</h2>
        <div class="steps">
            <div class="step">
                <h3>1. Choose Your Device</h3>
                <p>Select the device you want to sell and provide details about its condition.</p>
            </div>
            <div class="step">
                <h3>2. Get Instant Estimate</h3>
                <p>Our system calculates a fair price based on your device's specifications and condition.</p>
            </div>
            <div class="step">
                <h3>3. Upload Media</h3>
                <p>Send us 2-3 photos and a video to help our experts evaluate your device accurately.</p>
            </div>
            <div class="step">
                <h3>4. Clerk Review</h3>
                <p>Our clerk will review your submission, confirm the price, and arrange pickup or meetup.</p>
            </div>
        </div>
    </section>

    <!-- Sell Form Section - Multi-step Wizard -->
    <section class="sell-form-section" id="sell-form">
        <div class="form-container">
            <h2>Sell Your Device</h2>

            <!-- Progress Steps -->
            <div class="progress-bar">
                <div class="progress-step active" data-step="1">
                    <span class="step-number">1</span>
                    <span class="step-label">Device Type</span>
                </div>
                <div class="progress-step" data-step="2">
                    <span class="step-number">2</span>
                    <span class="step-label">Device Details</span>
                </div>
                <div class="progress-step" data-step="3">
                    <span class="step-number">3</span>
                    <span class="step-label">Condition</span>
                </div>
                <div class="progress-step" data-step="4">
                    <span class="step-number">4</span>
                    <span class="step-label">Quotation</span>
                </div>
            </div>

            <?php if (!empty($message)): ?>
                <div class="message <?php echo strpos($message, 'successfully') !== false ? 'success' : 'error'; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" id="sell-wizard-form" onsubmit="return validateForm()">
                <!-- Step 1: Device Type Selection -->
                <div class="wizard-step active" data-step="1">
                    <h3>What type of device do you want to sell?</h3>
                    <div class="device-type-grid">
                        <div class="device-type-card" data-type="Smartphone">
                            <div class="device-icon">📱</div>
                            <h4>Smartphone</h4>
                            <p>iPhone, Samsung, Pixel, OnePlus</p>
                        </div>
                        <div class="device-type-card" data-type="Tablet">
                            <div class="device-icon">📱</div>
                            <h4>Tablet</h4>
                            <p>iPad, Galaxy Tab</p>
                        </div>
                        <div class="device-type-card" data-type="Laptop">
                            <div class="device-icon">💻</div>
                            <h4>Laptop</h4>
                            <p>MacBook, Dell, HP, Lenovo</p>
                        </div>
                        <div class="device-type-card" data-type="Smartwatch">
                            <div class="device-icon">⌚</div>
                            <h4>Smartwatch</h4>
                            <p>Apple Watch, Galaxy Watch</p>
                        </div>
                    </div>
                    <input type="hidden" name="device_type" id="device_type" required>
                </div>

                <!-- Step 2: Device Details (Brand, Model, Storage, RAM) -->
                <div class="wizard-step" data-step="2">
                    <h3>Tell us about your device</h3>
                    <div class="form-group">
                        <label for="brand">Brand *</label>
                        <select name="brand" id="brand" required onchange="updateModels();">
                            <option value="">Select Brand</option>
                        </select>
                        <input type="hidden" name="custom_brand" id="custom_brand" value="">
                    </div>

                    <div class="form-group">
                        <label for="model">Model *</label>
                        <select name="model" id="model" required style="display: none;">
                            <option value="">Select Model</option>
                        </select>
                        <input type="text" name="model_text" id="model_text" placeholder="Enter laptop model (e.g., MacBook Pro 14, Dell XPS 13)" style="display: none;">
                    </div>

                    <div class="form-group">
                        <label for="storage">Storage (GB) *</label>
                        <select name="storage" id="storage" required>
                            <option value="">Select Storage</option>
                            <option value="64">64 GB</option>
                            <option value="128">128 GB</option>
                            <option value="256">256 GB</option>
                            <option value="512">512 GB</option>
                            <option value="1024">1 TB</option>
                            <option value="2048">2 TB</option>
                        </select>
                        <input type="number" name="storage_custom" id="storage_custom" placeholder="Enter custom storage (GB)" style="display: none; margin-top: 10px; padding: 10px; border: 1px solid #ddd; border-radius: 5px; width: 100%;">
                    </div>

                    <div class="form-group">
                        <label for="ram">RAM (GB) *</label>
                        <select name="ram" id="ram" required>
                            <option value="">Select RAM</option>
                            <option value="4">4 GB</option>
                            <option value="8">8 GB</option>
                            <option value="12">12 GB</option>
                            <option value="16">16 GB</option>
                            <option value="32">32 GB</option>
                            <option value="64">64 GB</option>
                        </select>
                        <input type="number" name="ram_custom" id="ram_custom" placeholder="Enter custom RAM (GB)" style="display: none; margin-top: 10px; padding: 10px; border: 1px solid #ddd; border-radius: 5px; width: 100%;">
                    </div>

                    <div class="form-group">
                        <label for="battery_health">Battery Health (%) *</label>
                        <input type="number" name="battery_health" id="battery_health" required min="0" max="100" placeholder="e.g., 85, 90, 100">
                    </div>

                    <div class="form-group">
                        <label for="accessories">Accessories Included *</label>
                        <input type="text" name="accessories" id="accessories" required placeholder="e.g., Original Box, Charger, Case (separate with commas)">
                    </div>


                    <div class="wizard-buttons">
                        <button type="button" class="btn btn-secondary" onclick="goToStep(1)">Back</button>
                        <button type="button" class="btn btn-primary" onclick="goToStep(3)">Next</button>
                    </div>
                </div>

                <!-- Step 3: 7 Questions about Device Condition -->
                <div class="wizard-step" data-step="3">
                    <h3>How is your device condition?</h3>
                    <div id="questions-section"></div>

                    <div class="wizard-buttons">
                        <button type="button" class="btn btn-secondary" onclick="goToStep(2)">Back</button>
                        <button type="button" class="btn btn-primary" onclick="calculateEstimate(); goToStep(4);">Get Quotation</button>
                    </div>
                </div>

                <!-- Step 4: Quotation and Final Submission -->
                <div class="wizard-step" data-step="4">
                    <h3>Your Device Quotation</h3>
                    <div class="quotation-summary">
                        <div class="quotation-item">
                            <span class="label">Device Type:</span>
                            <span class="value" id="quote-device-type">-</span>
                        </div>
                        <div class="quotation-item">
                            <span class="label">Brand:</span>
                            <span class="value" id="quote-brand">-</span>
                        </div>
                        <div class="quotation-item">
                            <span class="label">Model:</span>
                            <span class="value" id="quote-model">-</span>
                        </div>
                        <div class="quotation-item">
                            <span class="label">Storage:</span>
                            <span class="value" id="quote-storage">-</span>
                        </div>
                        <div class="quotation-item">
                            <span class="label">RAM:</span>
                            <span class="value" id="quote-ram">-</span>
                        </div>
                        <div class="quotation-item highlight">
                            <span class="label">Estimated Value:</span>
                            <span class="value" id="quote-estimate">RM0.00</span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="description">Additional Description</label>
                        <textarea name="description" id="description" placeholder="Describe any additional details about your device..."></textarea>
                    </div>

                    <div class="form-group">
                        <label>Upload Photos (2-3 images) *</label>
                        <div class="file-upload-container">
                            <div class="file-upload-box" id="imageUploadBox">
                                <input type="file" id="sellImages" name="image_1[]" accept="image/*" multiple required>
                                <div class="upload-placeholder">
                                    <span class="upload-icon">📷</span>
                                    <p>Click or drag to upload images</p>
                                    <p class="upload-hint">2-3 images required. Max 10MB each.</p>
                                </div>
                            </div>
                            <div class="image-preview-list" id="sellImageList"></div>
                            <p class="upload-instruction">Upload clear photos of your device from different angles</p>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Upload Video *</label>
                        <div class="file-upload-container">
                            <div class="file-upload-box" id="videoUploadBox">
                                <input type="file" id="sellVideo" name="video" accept="video/*" required>
                                <div class="upload-placeholder">
                                    <span class="upload-icon">🎥</span>
                                    <p>Click or drag to upload video</p>
                                    <p class="upload-hint">Required. Max 40MB.</p>
                                </div>
                            </div>
                            <div class="video-preview-container" id="sellVideoPreview"></div>
                            <p class="upload-instruction">Upload a short video showing the device working</p>
                        </div>
                    </div>

                    <div class="wizard-buttons">
                        <button type="button" class="btn btn-secondary" onclick="goToStep(3)">Back</button>
                        <button type="submit" name="submit_device" class="btn btn-primary">Submit for Review</button>
                    </div>
                </div>
            </form>
        </div>
    </section>

    <!-- Custom Device Dialog -->
    <div id="custom_device_dialog" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center;">
        <div style="background: white; padding: 30px; border-radius: 10px; max-width: 500px; width: 90%;">
            <h3 style="margin-top: 0;">Custom Device Details</h3>
            <div class="form-group">
                <label for="dialog_brand">Brand *</label>
                <input type="text" id="dialog_brand" placeholder="Enter brand name (e.g., Asus, Acer)">
            </div>
            <div class="form-group">
                <label for="dialog_model">Model *</label>
                <input type="text" id="dialog_model" placeholder="Enter model name">
            </div>
            <div class="form-group">
                <label for="dialog_storage">Storage (GB) *</label>
                <input type="number" id="dialog_storage" placeholder="Enter storage (e.g., 256)">
            </div>
            <div class="form-group">
                <label for="dialog_ram">RAM (GB) *</label>
                <input type="number" id="dialog_ram" placeholder="Enter RAM (e.g., 8)">
            </div>
            <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                <button type="button" class="btn btn-secondary" onclick="closeCustomDialog()">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveCustomDialog()">Save</button>
            </div>
        </div>
    </div>

    <!-- Login Modal -->
    <div id="loginModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center;">
        <div style="background: white; padding: 30px; border-radius: 10px; max-width: 400px; width: 90%; text-align: center;">
            <h3 style="margin-top: 0; color: #333;">Login Required</h3>
            <p style="color: #666; margin-bottom: 20px;">Login to sell your devices</p>
            <div style="display: flex; gap: 10px; justify-content: center; margin-top: 20px;">
                <button type="button" class="btn btn-primary" onclick="window.location.href='Login.php'">Login</button>
            </div>
        </div>
    </div>

    <!-- Success Modal -->
    <?php if (isset($show_success_modal) && $show_success_modal): ?>
    <div id="successModal" style="display: flex; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center;">
        <div style="background: white; padding: 30px; border-radius: 10px; max-width: 500px; width: 90%; text-align: center;">
            <div style="font-size: 60px; color: #10b981; margin-bottom: 20px;">✓</div>
            <h3 style="margin-top: 0; color: #333;">Request Submitted</h3>
            <p style="color: #666; margin-bottom: 20px;">View profile to see movement</p>
            <button type="button" class="btn btn-primary" onclick="window.location.href='profile.php#submissions'">Go to Profile</button>
        </div>
    </div>
    <?php endif; ?>

    <footer id="contact">
        <p>&copy; 2026 Fix N Flip. All rights reserved.</p>
        <div class="footer-links">
            <a href="https://mail.google.com/mail/?view=cm&fs=1&to=fixnflip.platform@gmail.com&su=Subject%20Line&body=Hi Fix N Flip, I would like more info on this.!" target="_blank" class="footer-icon" title="Contact via Gmail">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                    <polyline points="22,6 12,13 2,6"></polyline>
                </svg>
            </a>
            <a href="https://wa.me/+60109629725" target="_blank" class="footer-icon" title="Contact via WhatsApp">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                </svg>
            </a>
        </div>
    </footer>

    </body>
</html>

