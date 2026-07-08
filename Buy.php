<?php
session_start();

// Database connection
$mysqli = new mysqli("localhost", "root", "", "fyp");

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
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

// Fetch unique device types from database
$device_types = [];
$result = $mysqli->query("SELECT DISTINCT device_type FROM products WHERE status = 'available' ORDER BY device_type");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $device_types[] = $row['device_type'];
    }
}

// Ensure common device types are included even if no products exist
$common_device_types = ['Smartphone', 'Tablet', 'Laptop', 'Smartwatch'];
foreach ($common_device_types as $type) {
    if (!in_array($type, $device_types)) {
        $device_types[] = $type;
    }
}
sort($device_types);

// Fetch unique brands from database
$brands = [];
$result = $mysqli->query("SELECT DISTINCT brand FROM products WHERE status = 'available' ORDER BY brand");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $brands[] = $row['brand'];
    }
}

// Fetch unique conditions from database
$conditions = [];
$result = $mysqli->query("SELECT DISTINCT device_condition FROM products WHERE status = 'available' ORDER BY device_condition");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $conditions[] = $row['device_condition'];
    }
}

// Fetch products from database
$products = [];
$query = "SELECT * FROM products WHERE status = 'available' ORDER BY created_at DESC";
$result = $mysqli->query($query);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        // Fetch images for this product
        $product_id = $row['id'];
        $images_result = $mysqli->query("SELECT id FROM product_images WHERE product_id = $product_id ORDER BY id ASC");
        $images = [];
        if ($images_result) {
            while ($img_row = $images_result->fetch_assoc()) {
                $images[] = $img_row['id'];
            }
        }
        $row['image_ids'] = $images;
        $products[] = $row;
    }
}

// Calculate min and max prices
$min_price = 0;
$max_price = 10000;
if (!empty($products)) {
    $prices = array_column($products, 'price');
    $min_price = min($prices);
    $max_price = max($prices);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fix N Flip - Buy Devices</title>
    <link rel="stylesheet" href="buy.css">
    <script src="toast.js"></script>
    <style>
        * { box-sizing: border-box; }
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; padding-top: 155px; background: #f4f4f4; line-height: 1.6; }
        header { background-color: #10b981; color: white; padding: 1rem; text-align: center; position: fixed; top: 0; left: 0; right: 0; z-index: 1000; }

        /* Image Carousel Styles */
        .image-carousel {
            position: relative;
            width: 100%;
            height: 200px;
            overflow: hidden;
        }

        .carousel-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            position: absolute;
            top: 0;
            left: 0;
            opacity: 0;
            transition: opacity 0.5s ease;
        }

        .carousel-image:first-child {
            opacity: 1;
        }

        .image-carousel:hover .carousel-image.active {
            opacity: 1;
        }

        header h1 { margin: 0; font-size: 2rem; }
        header p { margin: 0.5rem 0 0 0; font-size: 1rem; }
        nav { background-color: #059669; padding: 0.5rem; display: flex; justify-content: space-between; align-items: center; position: fixed; top: 117px; left: 0; right: 0; z-index: 999; }
        nav ul { list-style: none; margin: 0; padding: 0; display: flex; }
        nav li { margin: 0 1rem; }
        nav a { color: white; text-decoration: none; display: flex; align-items: center; gap: 5px; position: relative; }
        .cart-badge {
            position: absolute;
            top: -8px;
            left: -12px;
            background: #ef4444;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 12px;
            font-weight: bold;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .main-container { display: flex; padding: 2rem; gap: 2rem; width: 100%; }
        .sidebar { width: 250px; flex-shrink: 0; background: white; padding: 1.5rem; border-radius: 10px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); height: fit-content; }
        .sidebar h3 { margin-top: 0; color: #10b981; border-bottom: 2px solid #10b981; padding-bottom: 0.5rem; }
        .filter-group { margin-bottom: 1.5rem; }
        .filter-group label { display: block; margin-bottom: 0.5rem; cursor: pointer; }
        .filter-group input { margin-right: 0.5rem; cursor: pointer; }
        .apply-btn { width: 100%; padding: 0.75rem; background: #10b981; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 1rem; }
        .apply-btn:hover { background: #059669; }
        .content { flex: 1; }
        .device-buttons { display: flex; gap: 0.5rem; flex-wrap: wrap; margin-bottom: 1.5rem; }
        .search-bar { margin-bottom: 1.5rem; }
        .search-bar input { width: 100%; padding: 0.75rem; border: 2px solid #ddd; border-radius: 25px; font-size: 1rem; }
        .search-bar input:focus { outline: none; border-color: #10b981; }
        .device-btn { padding: 0.5rem 1rem; background: white; border: 2px solid #10b981; border-radius: 20px; cursor: pointer; transition: all 0.2s; color: #10b981; }
        .device-btn:hover, .device-btn.active { background: #10b981; color: white; }
        .products-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 20px; }
        .card { background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 5px rgba(0,0,0,0.1); transition: transform 0.2s; cursor: pointer; }
        .card:hover { transform: translateY(-5px); }
        .card img { width: 100%; height: 200px; object-fit: cover; }
        .card-content { padding: 1rem; }
        .card h3 { margin: 0 0 0.5rem 0; color: #333; }
        .card .price { color: #10b981; font-weight: bold; font-size: 1.2rem; margin: 0.5rem 0; }
        .card .specs { color: #666; font-size: 0.9rem; }
        .card button { width: 100%; padding: 0.5rem; margin-top: 0.5rem; border: none; border-radius: 5px; cursor: pointer; }
        .btn-buy { background: #10b981; color: white; }
        .btn-cart { background: #fff; color: #10b981; border: 1px solid #10b981; }
        .no-products { text-align: center; padding: 2rem; color: #999; }
    </style>
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
                    <path d="M3 3H5L5.4 5M7 13H17L21 5H5.4M7 13L5.4 5M7 13L4.707 15.293C4.077 15.923 4.523 17 5.414 17H17M17 17C15.895 17 15 17.895 15 19C15 20.105 15.895 21 17 21C18.105 21 19 20.105 19 19C19 17.895 18.105 17 17 17ZM9 19C9 20.105 8.105 21 7 21C5.895 21 5 20.105 5 19C5 17.895 5.895 17 7 17C8.105 17 9 17.895 9 19Z" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
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

    <div class="main-container">
        <div class="sidebar">
            <h3>Filters</h3>
            
            <div class="filter-group">
                <h4>Device Type</h4>
                <?php foreach ($device_types as $type): ?>
                    <label>
                        <input type="checkbox" name="device_type" value="<?php echo htmlspecialchars($type); ?>">
                        <?php echo htmlspecialchars($type); ?>
                    </label>
                <?php endforeach; ?>
            </div>

            <div class="filter-group">
                <h4>Brand</h4>
                <?php foreach ($brands as $brand): ?>
                    <label>
                        <input type="checkbox" name="brand" value="<?php echo htmlspecialchars($brand); ?>">
                        <?php echo htmlspecialchars($brand); ?>
                    </label>
                <?php endforeach; ?>
            </div>

            <div class="filter-group">
                <h4>Condition</h4>
                <?php foreach ($conditions as $condition): ?>
                    <label>
                        <input type="checkbox" name="condition" value="<?php echo htmlspecialchars($condition); ?>">
                        <?php echo htmlspecialchars($condition); ?>
                    </label>
                <?php endforeach; ?>
            </div>

            <div class="filter-group">
                <h4>Price Range</h4>
                <div class="price-slider-container">
                    <div class="price-display">
                        <span>RM <input type="number" id="min-price-input" value="<?php echo number_format($min_price, 0); ?>" min="<?php echo $min_price; ?>" max="<?php echo $max_price; ?>" oninput="updatePriceFromInput()"></span>
                        <span>-</span>
                        <span>RM <input type="number" id="max-price-input" value="<?php echo number_format($max_price, 0); ?>" min="<?php echo $min_price; ?>" max="<?php echo $max_price; ?>" oninput="updatePriceFromInput()"></span>
                    </div>
                    <div class="slider-wrapper">
                        <input type="range" id="min-price" class="price-slider" min="<?php echo $min_price; ?>" max="<?php echo $max_price; ?>" value="<?php echo $min_price; ?>" oninput="updatePriceDisplay()">
                        <input type="range" id="max-price" class="price-slider" min="<?php echo $min_price; ?>" max="<?php echo $max_price; ?>" value="<?php echo $max_price; ?>" oninput="updatePriceDisplay()">
                    </div>
                </div>
            </div>

            <button class="apply-btn" onclick="applyFilters()">Apply Filters</button>
            <button class="apply-btn" style="background: #666; margin-top: 0.5rem;" onclick="resetFilters()">Reset</button>
        </div>

        <div class="content">
            <div class="device-buttons">
                <button class="device-btn active" onclick="filterByType('all')">All Devices</button>
                <?php foreach ($device_types as $type): ?>
                    <button class="device-btn" onclick="filterByType('<?php echo htmlspecialchars($type); ?>')"><?php echo htmlspecialchars($type); ?></button>
                <?php endforeach; ?>
            </div>

            <div class="search-bar">
                <input type="text" id="searchInput" placeholder="Search products..." oninput="searchProducts()">
            </div>

            <div class="products-grid" id="productsGrid">
                <?php if (empty($products)): ?>
                    <div class="no-products">No products available</div>
                <?php else: ?>
                    <?php foreach ($products as $product): ?>
                        <?php
                        $image_ids = $product['image_ids'] ?? [];
                        $first_image_id = !empty($image_ids) ? $image_ids[0] : null;
                        $images_json = htmlspecialchars(json_encode($image_ids));
                        $specs = isset($product['specs']) ? $product['specs'] : '';
                        $specs_array = array_map('trim', explode(',', $specs));
                        $specs_display = array_slice($specs_array, 0, 5);
                        $specs_formatted = implode('<br>', array_map('htmlspecialchars', $specs_display));
                        if (count($specs_array) > 5) {
                            $specs_formatted .= '<br>...';
                        }
                        ?>
                        <div class="card" data-device-type="<?php echo htmlspecialchars($product['category'] ?? ''); ?>" data-brand="<?php echo htmlspecialchars($product['brand'] ?? ''); ?>" data-condition="<?php echo htmlspecialchars($product['device_condition'] ?? ''); ?>" data-price="<?php echo $product['price']; ?>" onclick="window.location.href='ProductDetail.php?id=<?php echo $product['id']; ?>'">
                            <div class="image-carousel" data-images="<?php echo $images_json; ?>">
                                <?php if (!empty($image_ids)): ?>
                                    <?php foreach ($image_ids as $idx => $img_id): ?>
                                        <img src="get_product_image.php?id=<?php echo $img_id; ?>" alt="<?php echo htmlspecialchars($product['name'] ?? ''); ?>" class="carousel-image <?php echo $idx === 0 ? 'active' : ''; ?>">
                                    <?php endforeach; ?>
                                    <div class="carousel-dots">
                                        <?php foreach ($image_ids as $idx => $img_id): ?>
                                            <span class="carousel-dot <?php echo $idx === 0 ? 'active' : ''; ?>" data-index="<?php echo $idx; ?>"></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <img src="https://placehold.co/250x200?text=No+Image" alt="No Image" class="carousel-image active">
                                <?php endif; ?>
                            </div>
                            <div class="card-content">
                                <h3><?php echo htmlspecialchars($product['brand'] ?? '') . ' ' . htmlspecialchars($product['name'] ?? ''); ?></h3>
                                <div class="specs">
                                    <?php
                                    $condition = $product['condition'] ?? 'Good';
                                    $conditionColor = '#666666';
                                    if ($condition === 'Excellent') {
                                        $conditionColor = '#10b981';
                                    } elseif ($condition === 'Like New') {
                                        $conditionColor = '#f59e0b';
                                    } elseif ($condition === 'Good') {
                                        $conditionColor = '#f59e0b';
                                    } elseif ($condition === 'Fair') {
                                        $conditionColor = '#ef4444';
                                    }
                                    ?>
                                    Condition: <span style="color: <?php echo $conditionColor; ?>; font-weight: bold;"><?php echo htmlspecialchars($condition); ?></span><br>
                                    <?php if ($specs_formatted): ?>
                                        <?php echo $specs_formatted; ?>
                                    <?php else: ?>
                                        No specs available
                                    <?php endif; ?>
                                </div>
                                <div class="price">RM <?php echo number_format($product['price'], 2); ?></div>
                                <div class="button-container">
                                    <?php if (isset($_SESSION['logged_in']) && $_SESSION['logged_in']): ?>
                                        <button class="btn-buy" onclick="event.stopPropagation(); window.location.href='Checkout.php?id=<?php echo $product['id']; ?>'">Buy</button>
                                        <button class="btn-cart" onclick="event.stopPropagation(); addToCart(<?php echo $product['id']; ?>)">Add to Cart</button>
                                    <?php else: ?>
                                        <button class="btn-buy" onclick="event.stopPropagation(); window.location.href='Login.php'">Login to Buy</button>
                                        <button class="btn-cart" onclick="event.stopPropagation(); window.location.href='Login.php'">Login to Add to Cart</button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        let allProducts = <?php echo json_encode($products); ?>;

        function addToCart(productId) {
            fetch('add_to_cart.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'product_id=' + productId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Product added to cart!', 'success');
                } else {
                    showToast(data.message || 'Failed to add to cart');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Error adding to cart');
            });
        }

        function searchProducts() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const cards = document.querySelectorAll('.card');
            
            cards.forEach(card => {
                const text = card.textContent.toLowerCase();
                if (text.includes(searchTerm)) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        }

        function filterByType(type) {
            // Update button styles
            document.querySelectorAll('.device-btn').forEach(btn => btn.classList.remove('active'));
            event.target.classList.add('active');

            const cards = document.querySelectorAll('.card');
            cards.forEach(card => {
                if (type === 'all' || card.getAttribute('data-device-type') === type) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        }

        function applyFilters() {
            const selectedDeviceTypes = Array.from(document.querySelectorAll('input[name="device_type"]:checked')).map(cb => cb.value);
            const selectedBrands = Array.from(document.querySelectorAll('input[name="brand"]:checked')).map(cb => cb.value);
            const selectedConditions = Array.from(document.querySelectorAll('input[name="condition"]:checked')).map(cb => cb.value);
            const minPrice = parseFloat(document.getElementById('min-price').value);
            const maxPrice = parseFloat(document.getElementById('max-price').value);

            const cards = document.querySelectorAll('.card');
            cards.forEach(card => {
                const deviceType = card.getAttribute('data-device-type');
                const brand = card.getAttribute('data-brand');
                const condition = card.getAttribute('data-condition');
                const price = parseFloat(card.getAttribute('data-price'));

                let show = true;

                if (selectedDeviceTypes.length > 0 && !selectedDeviceTypes.includes(deviceType)) {
                    show = false;
                }

                if (selectedBrands.length > 0 && !selectedBrands.includes(brand)) {
                    show = false;
                }

                if (selectedConditions.length > 0 && !selectedConditions.includes(condition)) {
                    show = false;
                }

                if (price < minPrice || price > maxPrice) {
                    show = false;
                }

                card.style.display = show ? 'block' : 'none';
            });
        }

        function updatePriceDisplay() {
            const minPrice = parseFloat(document.getElementById('min-price').value);
            const maxPrice = parseFloat(document.getElementById('max-price').value);

            // Ensure min doesn't exceed max
            if (minPrice > maxPrice) {
                document.getElementById('min-price').value = maxPrice;
                document.getElementById('max-price').value = minPrice;
            }

            document.getElementById('min-price-input').value = Math.round(document.getElementById('min-price').value);
            document.getElementById('max-price-input').value = Math.round(document.getElementById('max-price').value);

            // Update slider track background to show green range
            updateSliderTrack();
        }

        function updatePriceFromInput() {
            const minInput = parseFloat(document.getElementById('min-price-input').value);
            const maxInput = parseFloat(document.getElementById('max-price-input').value);
            const minSlider = document.getElementById('min-price');
            const maxSlider = document.getElementById('max-price');
            const min = parseFloat(minSlider.min);
            const max = parseFloat(minSlider.max);

            // Ensure values are within bounds
            const clampedMin = Math.max(min, Math.min(max, minInput));
            const clampedMax = Math.max(min, Math.min(max, maxInput));

            // Ensure min doesn't exceed max
            if (clampedMin > clampedMax) {
                document.getElementById('min-price-input').value = clampedMax;
                document.getElementById('max-price-input').value = clampedMin;
                minSlider.value = clampedMax;
                maxSlider.value = clampedMin;
            } else {
                minSlider.value = clampedMin;
                maxSlider.value = clampedMax;
            }

            // Update slider track background
            updateSliderTrack();
        }

        function updateSliderTrack() {
            const minSlider = document.getElementById('min-price');
            const maxSlider = document.getElementById('max-price');
            const min = parseFloat(minSlider.min);
            const max = parseFloat(minSlider.max);
            const minVal = parseFloat(minSlider.value);
            const maxVal = parseFloat(maxSlider.value);

            const minPercent = ((minVal - min) / (max - min)) * 100;
            const maxPercent = ((maxVal - min) / (max - min)) * 100;

            const trackStyle = `linear-gradient(to right, #ddd ${minPercent}%, #10b981 ${minPercent}%, #10b981 ${maxPercent}%, #ddd ${maxPercent}%)`;

            minSlider.style.background = trackStyle;
            maxSlider.style.background = trackStyle;
        }

        // Initialize slider track on page load
        window.addEventListener('DOMContentLoaded', function() {
            updateSliderTrack();
            initImageCarousels();
        });

        // Image carousel functionality
        function initImageCarousels() {
            const carousels = document.querySelectorAll('.image-carousel');

            carousels.forEach(carousel => {
                const images = carousel.querySelectorAll('.carousel-image');
                const dots = carousel.querySelectorAll('.carousel-dot');
                if (images.length <= 1) return;

                let currentIndex = 0;
                let interval = null;

                carousel.addEventListener('mouseenter', function() {
                    currentIndex = 0;
                    // Start with first image active
                    images.forEach(img => img.classList.remove('active'));
                    images[0].classList.add('active');
                    dots.forEach(dot => dot.classList.remove('active'));
                    dots[0].classList.add('active');

                    interval = setInterval(() => {
                        images.forEach(img => img.classList.remove('active'));
                        dots.forEach(dot => dot.classList.remove('active'));
                        currentIndex = (currentIndex + 1) % images.length;
                        images[currentIndex].classList.add('active');
                        dots[currentIndex].classList.add('active');
                    }, 1000);
                });

                carousel.addEventListener('mouseleave', function() {
                    if (interval) {
                        clearInterval(interval);
                        interval = null;
                    }
                    images.forEach(img => img.classList.remove('active'));
                    dots.forEach(dot => dot.classList.remove('active'));
                    images[0].classList.add('active');
                    dots[0].classList.add('active');
                });
            });
        }

        function resetFilters() {
            document.querySelectorAll('input[type="checkbox"]').forEach(cb => cb.checked = false);
            document.querySelectorAll('.card').forEach(card => card.style.display = 'block');
            document.querySelectorAll('.device-btn').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('.device-btn')[0].classList.add('active');
        }
    </script>\
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
