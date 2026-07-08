<?php
session_start();

// Database connection
$mysqli = new mysqli("localhost", "root", "", "fyp");

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

// Fetch cart items
$cart_items = [];
$total = 0;
$cart_count = 0;
$notification_count = 0;

if (isset($_SESSION['logged_in']) && $_SESSION['logged_in']) {
    $user_id = $_SESSION['user_id'];
    
    // Check if cart table exists
    $check_table = "SHOW TABLES LIKE 'cart'";
    $table_result = $mysqli->query($check_table);
    
    if ($table_result && $table_result->num_rows > 0) {
        // Fetch cart items with product details
        $query = "SELECT c.*, p.brand, p.model, p.price, p.image_1, p.device_condition 
                  FROM cart c 
                  JOIN products p ON c.product_id = p.id 
                  WHERE c.user_id = ? AND p.status = 'available'";
        $stmt = $mysqli->prepare($query);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $cart_items[] = $row;
            $total += $row['price'] * $row['quantity'];
            $cart_count += $row['quantity'];
        }
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fix N Flip - Your Cart</title>
    <link rel="stylesheet" href="addtocart.css">
    <script src="toast.js"></script>
</head>
<body>
    <header>
        <h1>Fix N Flip</h1>
        <p>Buy & Sell Used Devices for APU Students</p>
        <script src="https://cdn.botpress.cloud/webchat/v3.6/inject.js"></script>
        <script src="https://files.bpcontent.cloud/2026/06/29/06/20260629061033-TO72SUAN.js" defer></script>
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

    <div class="container">
        <?php if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']): ?>
            <div class="empty-cart">
                <p>Please <a href="Login.php">login</a> to view your cart.</p>
            </div>
        <?php elseif (empty($cart_items)): ?>
            <div class="empty-cart">
                <p>Your cart is empty.</p>
                <a href="Buy.php" class="button2">Continue Shopping</a>
            </div>
        <?php else: ?>
            <!-- From Uiverse.io by zanina-yassine -->
            <div class="master-container">
                <div class="card cart">
                    <label class="title">Your cart</label>
                    <div class="products">
                        <?php foreach ($cart_items as $item): ?>
                            <div class="product">
                                <input type="checkbox" class="cart-item-checkbox" data-id="<?php echo $item['id']; ?>" data-price="<?php echo $item['price']; ?>" checked onchange="updateTotal()">
                                <img src="<?php echo htmlspecialchars($item['image_1'] ?: 'https://placehold.co/60x60?text=No+Image'); ?>" alt="<?php echo htmlspecialchars($item['brand'] . ' ' . $item['model']); ?>" onerror="this.src='https://placehold.co/60x60?text=No+Image'">
                                <div>
                                    <span><?php echo htmlspecialchars($item['brand'] . ' ' . $item['model']); ?></span>
                                    <?php
                                    $conditionColor = '#7a7c81';
                                    $conditionText = strtolower($item['device_condition']);
                                    if (strpos($conditionText, 'excellent') !== false) {
                                        $conditionColor = '#10b981'; // Green
                                    } elseif (strpos($conditionText, 'good') !== false) {
                                        $conditionColor = '#f59e0b'; // Yellow
                                    } elseif (strpos($conditionText, 'fair') !== false) {
                                        $conditionColor = '#ef4444'; // Red
                                    }
                                    ?>
                                    <p>Condition: <span style="color: <?php echo $conditionColor; ?>; font-weight: bold;"><?php echo htmlspecialchars($item['device_condition']); ?></span></p>
                                    <button class="remove-btn" onclick="removeFromCart(<?php echo $item['id']; ?>)">Remove</button>
                                </div>
                                <label class="price small">RM <?php echo number_format($item['price'], 2); ?></label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="card coupons">
                    <label class="title">Apply coupons</label>
                    <form class="form" onsubmit="return false;">
                        <input type="text" placeholder="Apply your coupons here" class="input_field">
                        <button onclick="showToast('Coupon functionality coming soon!')">Apply</button>
                    </form>
                </div>

                <div class="card checkout">
                    <label class="title">Checkout</label>
                    <div class="details">
                        <span>Your cart subtotal:</span>
                        <span>RM <?php echo number_format($total, 2); ?></span>
                        <span>Discount through applied coupons:</span>
                        <span>RM 0.00</span>
                        <span>Shipping fees:</span>
                        <span>RM 0.00</span>
                    </div>
                    <div class="checkout--footer">
                        <label class="price">RM <?php echo number_format($total, 2); ?></label>
                        <button class="checkout-btn" onclick="checkout()">Checkout</button>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function removeFromCart(cartId) {
            document.getElementById('removeCartId').value = cartId;
            openModal('removeFromCartModal');
        }

        function openModal(modalId) {
            const modal = document.getElementById(modalId);
            modal.style.display = 'flex';
        }

        function closeModal(modalId) {
            const modal = document.getElementById(modalId);
            modal.style.display = 'none';
        }
    </script>

    <!-- Remove from Cart Modal -->
    <div id="removeFromCartModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Remove from Cart</h3>
                <button class="close-modal" onclick="closeModal('removeFromCartModal')">&times;</button>
            </div>
            <form method="POST" action="remove_from_cart.php">
                <input type="hidden" name="cart_id" id="removeCartId">
                <p>Are you sure you want to remove this item from cart?</p>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('removeFromCartModal')">Cancel</button>
                    <button type="submit" class="btn btn-danger">Remove</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function updateTotal() {
            const checkboxes = document.querySelectorAll('.cart-item-checkbox');
            let selectedTotal = 0;
            checkboxes.forEach(cb => {
                if (cb.checked) {
                    selectedTotal += parseFloat(cb.dataset.price);
                }
            });
            
            // Update the displayed total
            const priceElements = document.querySelectorAll('.price');
            priceElements.forEach(el => {
                if (el.closest('.checkout--footer')) {
                    el.textContent = 'RM ' + selectedTotal.toFixed(2);
                }
            });
            
            // Update subtotal in details
            const detailsSpans = document.querySelectorAll('.checkout .details span');
            for (let i = 0; i < detailsSpans.length; i++) {
                if (detailsSpans[i].textContent.includes('subtotal')) {
                    detailsSpans[i + 1].textContent = 'RM ' + selectedTotal.toFixed(2);
                }
            }
        }

        function checkout() {
            const checkboxes = document.querySelectorAll('.cart-item-checkbox');
            const selectedIds = [];
            checkboxes.forEach(cb => {
                if (cb.checked) {
                    selectedIds.push(cb.dataset.id);
                }
            });

            if (selectedIds.length === 0) {
                showToast('Please select at least one item to checkout');
                return;
            }
            
            window.location.href = 'Checkout.php?cart_ids=' + selectedIds.join(',');
        }
    </script>

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