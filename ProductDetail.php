<?php
session_start();

// Database connection
$mysqli = new mysqli("localhost", "root", "", "fyp");

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

// Create cart table if it doesn't exist
$create_cart = "CREATE TABLE IF NOT EXISTS cart (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT DEFAULT 1,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
$mysqli->query($create_cart);

// Fetch cart count
$cart_count = 0;
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
}

// Get product ID from URL
$product_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Fetch product details
$product = null;
if ($product_id > 0) {
    $query = "SELECT p.* FROM products p WHERE p.id = ?";
    $stmt = $mysqli->prepare($query);
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $product = $result->fetch_assoc();
    $stmt->close();

    // Fetch images for this product
    if ($product) {
        $images_result = $mysqli->query("SELECT id FROM product_images WHERE product_id = $product_id ORDER BY id ASC");
        $product['images'] = [];
        if ($images_result) {
            while ($img_row = $images_result->fetch_assoc()) {
                $product['images'][] = $img_row['id'];
            }
        }
    }
}

// Handle Add to Cart
$cart_message = "";
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_to_cart']) && isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    
    // Check if item already in cart
    $check_cart = $mysqli->query("SELECT id FROM cart WHERE user_id = $user_id AND product_id = $product_id");
    if ($check_cart->num_rows > 0) {
        // Item already in cart - since this is per device, don't allow duplicates
        $cart_message = "This item is already in your cart";
    } else {
        // Add to cart with quantity 1 (per device)
        $mysqli->query("INSERT INTO cart (user_id, product_id, quantity) VALUES ($user_id, $product_id, 1)");
        $cart_message = "Item added to cart!";
    }
}

// Handle Buy Now
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['buy_now']) && isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    
    // Add to cart and redirect to checkout
    $check_cart = $mysqli->query("SELECT id FROM cart WHERE user_id = $user_id AND product_id = $product_id");
    if ($check_cart->num_rows == 0) {
        $mysqli->query("INSERT INTO cart (user_id, product_id, quantity) VALUES ($user_id, $product_id, 1)");
    }
    
    header("Location: AddToCart.php");
    exit();
}

// Handle Inspection Request
$inspection_message = "";
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['request_inspection']) && isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $requested_date = $_POST['requested_date'];
    $preferred_time = $_POST['preferred_time'];
    $notes = $_POST['notes'];
    
    $query = "INSERT INTO inspection_requests (user_id, product_id, requested_date, preferred_time, notes, status) 
              VALUES (?, ?, ?, ?, ?, 'pending')";
    $stmt = $mysqli->prepare($query);
    $stmt->bind_param("iisss", $user_id, $product_id, $requested_date, $preferred_time, $notes);
    $stmt->execute();
    $stmt->close();
    
    $inspection_message = "Inspection request submitted! We'll contact you shortly.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fix N Flip - Product Details</title>
    <link rel="stylesheet" href="ProductDetail.css">
    <script src="ProductDetail.js" defer></script>
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
            <li>
                <?php if (isset($_SESSION['logged_in']) && $_SESSION['logged_in']): ?>
                    <a href="profile.php">
                        <img src="<?php echo $_SESSION['pfp']; ?>" class="profile-pic" style="width: 20px; height: 20px; border-radius: 50%; object-fit: cover;">
                        <?php echo $_SESSION['username']; ?>
                    </a>
                <?php else: ?>
                    <a href="Login.php">Sign Up / Log In</a>
                <?php endif; ?>
            </li>
        </ul>
    </nav>

    <?php if ($product): ?>
    <div class="product-detail-container">
        <div class="product-gallery">
            <div class="main-image">
                <?php if (!empty($product['images'])): ?>
                    <img src="get_product_image.php?id=<?php echo $product['images'][0]; ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" id="mainProductImage">
                <?php else: ?>
                    <img src="https://via.placeholder.com/500x500?text=No+Image" alt="Product Image" id="mainProductImage">
                <?php endif; ?>
            </div>
            <div class="thumbnail-container">
                <?php if (!empty($product['images'])): ?>
                    <?php foreach ($product['images'] as $idx => $img_id): ?>
                        <div class="thumbnail <?php echo $idx === 0 ? 'active' : ''; ?>" onclick="changeImage(<?php echo $img_id; ?>)">
                            <img src="get_product_image.php?id=<?php echo $img_id; ?>" alt="Image <?php echo $idx + 1; ?>">
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="product-info">
            <div class="product-header">
                <h1><?php echo htmlspecialchars($product['brand'] . ' ' . $product['name']); ?></h1>
                <?php
                $condition = $product['condition'] ?? 'Good';
                $conditionClass = strtolower(str_replace(' ', '-', $condition));
                ?>
                <div class="condition-badge <?php echo $conditionClass; ?>">
                    <?php echo htmlspecialchars($condition); ?>
                </div>
            </div>

            <div class="product-price">
                RM <?php echo number_format($product['price'], 2); ?>
            </div>

            <div class="product-specs">
                <h3>Specifications</h3>
                <div class="spec-grid">
                    <div class="spec-item">
                        <span class="spec-label">Brand:</span>
                        <span class="spec-value"><?php echo htmlspecialchars($product['brand']); ?></span>
                    </div>
                    <div class="spec-item">
                        <span class="spec-label">Category:</span>
                        <span class="spec-value"><?php echo htmlspecialchars($product['category']); ?></span>
                    </div>
                    <?php if ($product['specs']): ?>
                    <div class="spec-item" style="grid-column: span 2;">
                        <span class="spec-label">Specs:</span>
                        <span class="spec-value"><?php echo str_replace(',', '<br>', htmlspecialchars($product['specs'])); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($product['accessories']): ?>
            <div class="product-accessories">
                <h3>Accessories Included</h3>
                <p><?php echo str_replace(',', '<br>', htmlspecialchars($product['accessories'])); ?></p>
            </div>
            <?php endif; ?>

            <?php if (!empty($cart_message)): ?>
                <div class="message success"><?php echo $cart_message; ?></div>
            <?php endif; ?>

            <?php if (!empty($inspection_message)): ?>
                <div class="message success"><?php echo $inspection_message; ?></div>
            <?php endif; ?>

            <form class="purchase-form" method="POST">
                <div class="action-buttons">
                    <?php if (isset($_SESSION['logged_in']) && $_SESSION['logged_in']): ?>
                        <button type="submit" name="add_to_cart" class="btn btn-secondary">Add to Cart</button>
                        <button type="submit" name="buy_now" class="btn btn-primary">Buy Now</button>
                    <?php else: ?>
                        <a href="Login.php" class="btn btn-secondary">Login to Add to Cart</a>
                        <a href="Login.php" class="btn btn-primary">Login to Buy</a>
                    <?php endif; ?>
                </div>
            </form>

            <?php if (isset($_SESSION['logged_in']) && $_SESSION['logged_in']): ?>
            <div class="inspection-section">
                <button class="btn btn-outline" onclick="toggleInspectionForm()">Request In-Person Inspection</button>
                <form class="inspection-form" id="inspectionForm" method="POST" style="display: none;">
                    <h3>Request Inspection</h3>
                    <div class="form-group">
                        <label for="requested_date">Preferred Date:</label>
                        <input type="date" id="requested_date" name="requested_date" required>
                    </div>
                    <div class="form-group">
                        <label for="preferred_time">Preferred Time:</label>
                        <select id="preferred_time" name="preferred_time" required>
                            <option value="">Select Time</option>
                            <option value="09:00-11:00">9:00 AM - 11:00 AM</option>
                            <option value="11:00-13:00">11:00 AM - 1:00 PM</option>
                            <option value="14:00-16:00">2:00 PM - 4:00 PM</option>
                            <option value="16:00-18:00">4:00 PM - 6:00 PM</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="notes">Additional Notes:</label>
                        <textarea id="notes" name="notes" placeholder="Any specific requirements or questions..."></textarea>
                    </div>
                    <button type="submit" name="request_inspection" class="btn btn-primary">Submit Request</button>
                </form>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Video Modal -->
    <div class="modal" id="videoModal" style="display: none;">
        <div class="modal-content">
            <span class="close" onclick="closeVideoModal()">&times;</span>
            <video id="productVideo" controls>
                <source src="" type="video/mp4">
                Your browser does not support the video tag.
            </video>
        </div>
    </div>
    <?php else: ?>
    <div class="no-product">
        <h2>Product Not Found</h2>
        <p>The product you're looking for doesn't exist or is no longer available.</p>
        <a href="Buy.php" class="btn btn-primary">Browse Products</a>
    </div>
    <?php endif; ?>

    <footer id="contact">
        <p>&copy; 2026 Fix N Flip. All rights reserved.</p>
        <div class="footer-links">
            <a href="https://mail.google.com/mail/?view=cm&fs=1&to=mohdkaiser1806@gmail.com&su=Subject%20Line&body=Hi Fix N Flip, I would like more info on this.!" target="_blank" class="footer-icon" title="Contact via Gmail">
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
