<?php
session_start();

// Database connection
$mysqli = new mysqli("localhost", "root", "", "fyp");

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

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

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    header("Location: Login.php");
    exit();
}

// Fetch cart items
$cart_items = [];
$total = 0;
$checkout_type = 'cart'; // 'single', 'selected', or 'cart'

// Check if single product checkout
if (isset($_GET['id'])) {
    $checkout_type = 'single';
    $product_id = intval($_GET['id']);
    $query = "SELECT p.*, 1 as quantity, 0 as id 
              FROM products p 
              WHERE p.id = ? AND p.status = 'available'";
    $stmt = $mysqli->prepare($query);
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $cart_items[] = $row;
        $total += $row['price'];
    }
}
// Check if selected cart items checkout
elseif (isset($_GET['cart_ids'])) {
    $checkout_type = 'selected';
    $cart_ids = explode(',', $_GET['cart_ids']);
    $cart_ids = array_map('intval', $cart_ids);
    
    if (!empty($cart_ids)) {
        $placeholders = str_repeat('?,', count($cart_ids) - 1) . '?';
        $query = "SELECT c.*, p.brand, p.model, p.price, p.image_1, p.device_condition 
                  FROM cart c 
                  JOIN products p ON c.product_id = p.id 
                  WHERE c.id IN ($placeholders) AND c.user_id = ? AND p.status = 'available'";
        $stmt = $mysqli->prepare($query);
        
        $types = str_repeat('i', count($cart_ids)) . 'i';
        $params = array_merge($cart_ids, [$user_id]);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $cart_items[] = $row;
            $total += $row['price'] * $row['quantity'];
        }
    }
}
// Default: fetch all cart items
else {
    $check_table = "SHOW TABLES LIKE 'cart'";
    $table_result = $mysqli->query($check_table);
    
    if ($table_result && $table_result->num_rows > 0) {
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
        }
    }
}

// Fetch user information
$user_info = [];
$query = "SELECT * FROM users WHERE id = ?";
$stmt = $mysqli->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user_info = $result->fetch_assoc();

$checkout_error = '';
$checkout_values = [
    'full_name' => $user_info['username'] ?? '',
    'email' => $user_info['email'] ?? '',
    'phone' => '',
    'address' => '',
    'city' => '',
    'postal_code' => '',
    'payment_method' => ''
];

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['place_order'])) {
    $checkout_type = $_POST['checkout_type'] ?? $checkout_type;
    $cart_ids = isset($_POST['cart_ids']) ? explode(',', $_POST['cart_ids']) : [];
    $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;

    $checkout_values['full_name'] = trim($_POST['full_name'] ?? '');
    $checkout_values['email'] = trim($_POST['email'] ?? '');
    $checkout_values['phone'] = trim($_POST['phone'] ?? '');
    $checkout_values['address'] = trim($_POST['address'] ?? '');
    $checkout_values['city'] = trim($_POST['city'] ?? '');
    $checkout_values['postal_code'] = trim($_POST['postal_code'] ?? '');
    $checkout_values['payment_method'] = trim($_POST['payment_method'] ?? '');

    if (empty($checkout_values['full_name']) || empty($checkout_values['email']) || empty($checkout_values['phone']) || empty($checkout_values['address']) || empty($checkout_values['city']) || empty($checkout_values['postal_code']) || empty($checkout_values['payment_method'])) {
        $checkout_error = 'Please fill in all required shipping and payment fields.';
    } else {
        $items = [];
        $total_amount = 0;

        if ($checkout_type == 'single' && $product_id > 0) {
            $query = "SELECT p.id, p.price FROM products p WHERE p.id = ? AND p.status = 'available'";
            $stmt = $mysqli->prepare($query);
            $stmt->bind_param('i', $product_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($product = $result->fetch_assoc()) {
                $items[] = ['product_id' => $product['id'], 'quantity' => 1, 'price' => $product['price']];
                $total_amount += $product['price'];
            }
        } elseif ($checkout_type == 'selected' && !empty($cart_ids)) {
            $placeholders = str_repeat('?,', count($cart_ids) - 1) . '?';
            $query = "SELECT c.product_id, c.quantity, p.price FROM cart c JOIN products p ON c.product_id = p.id WHERE c.id IN ($placeholders) AND c.user_id = ? AND p.status = 'available'";
            $stmt = $mysqli->prepare($query);
            $types = str_repeat('i', count($cart_ids)) . 'i';
            $params = array_merge($cart_ids, [$user_id]);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $items[] = ['product_id' => $row['product_id'], 'quantity' => $row['quantity'], 'price' => $row['price']];
                $total_amount += $row['price'] * $row['quantity'];
            }
        } else {
            $query = "SELECT c.product_id, c.quantity, p.price FROM cart c JOIN products p ON c.product_id = p.id WHERE c.user_id = ? AND p.status = 'available'";
            $stmt = $mysqli->prepare($query);
            $stmt->bind_param('i', $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $items[] = ['product_id' => $row['product_id'], 'quantity' => $row['quantity'], 'price' => $row['price']];
                $total_amount += $row['price'] * $row['quantity'];
            }
        }

        if (empty($items)) {
            $checkout_error = 'No available products were found for checkout.';
        } else {
            $shipping_address = $checkout_values['address'] . ', ' . $checkout_values['city'] . ' ' . $checkout_values['postal_code'];
            $payment_method = $checkout_values['payment_method'];

            $order_query = "INSERT INTO orders (user_id, total_amount, status, shipping_address, payment_method, created_at, updated_at) VALUES (?, ?, 'pending', ?, ?, NOW(), NOW())";
            $stmt = $mysqli->prepare($order_query);
            $stmt->bind_param('idss', $user_id, $total_amount, $shipping_address, $payment_method);
            $stmt->execute();
            $order_id = $mysqli->insert_id;

            foreach ($items as $item) {
                $item_query = "INSERT INTO order_items (order_id, product_id, quantity, price_at_purchase) VALUES (?, ?, ?, ?)";
                $item_stmt = $mysqli->prepare($item_query);
                $item_stmt->bind_param('iiid', $order_id, $item['product_id'], $item['quantity'], $item['price']);
                $item_stmt->execute();
            }

            $product_ids = array_column($items, 'product_id');
            $product_placeholders = str_repeat('?,', count($product_ids) - 1) . '?';
            $update_products = "UPDATE products SET status = 'reserved' WHERE id IN ($product_placeholders)";
            $update_stmt = $mysqli->prepare($update_products);
            $update_stmt->bind_param(str_repeat('i', count($product_ids)), ...$product_ids);
            $update_stmt->execute();

            if ($checkout_type === 'selected' && !empty($cart_ids)) {
                $delete_query = "DELETE FROM cart WHERE id IN ($placeholders) AND user_id = ?";
                $delete_stmt = $mysqli->prepare($delete_query);
                $delete_stmt->bind_param($types, ...$params);
                $delete_stmt->execute();
            } elseif ($checkout_type === 'cart') {
                $mysqli->query("DELETE FROM cart WHERE user_id = $user_id");
            }

            $user_notification = "Your order #$order_id has been placed successfully. Current status: Pending. We will update you as it moves through packing, ready for pickup, shipping, and delivery.";
            $notification_query = "INSERT INTO notifications (user_id, message, type, created_at) VALUES (?, ?, 'order', NOW())";
            $notif_stmt = $mysqli->prepare($notification_query);
            $notif_stmt->bind_param('is', $user_id, $user_notification);
            $notif_stmt->execute();

            $team_notification = "New order #$order_id has been placed and is ready for processing.";
            $mysqli->query("INSERT INTO notifications (user_id, message, type, created_at) SELECT id, '" . $mysqli->real_escape_string($team_notification) . "', 'order', NOW() FROM users WHERE role IN ('admin','clerk')");

            header("Location: OrderSuccess.php?order_id=" . $order_id);
            exit();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fix N Flip - Checkout</title>
    <style>
        /* From Uiverse.io by shah1345 */
        .button2 {
            display: inline-block;
            transition: all 0.2s ease-in;
            position: relative;
            overflow: hidden;
            z-index: 1;
            color: #090909;
            padding: 0.7em 1.7em;
            cursor: pointer;
            font-size: 18px;
            border-radius: 0.5em;
            background: #e8e8e8;
            border: 1px solid #e8e8e8;
            box-shadow: 6px 6px 12px #c5c5c5, -6px -6px 12px #ffffff;
            text-decoration: none;
        }

        .button2:active {
            color: #666;
            box-shadow: inset 4px 4px 12px #c5c5c5, inset -4px -4px 12px #ffffff;
        }

        .button2:before {
            content: "";
            position: absolute;
            left: 50%;
            transform: translateX(-50%) scaleY(1) scaleX(1.25);
            top: 100%;
            width: 140%;
            height: 180%;
            background-color: rgba(0, 0, 0, 0.05);
            border-radius: 50%;
            display: block;
            transition: all 0.5s 0.1s cubic-bezier(0.55, 0, 0.1, 1);
            z-index: -1;
        }

        .button2:after {
            content: "";
            position: absolute;
            left: 55%;
            transform: translateX(-50%) scaleY(1) scaleX(1.45);
            top: 180%;
            width: 160%;
            height: 190%;
            background-color: #009087;
            border-radius: 50%;
            display: block;
            transition: all 0.5s 0.1s cubic-bezier(0.55, 0, 0.1, 1);
            z-index: -1;
        }

        .button2:hover {
            color: #ffffff;
            border: 1px solid #009087;
        }

        .button2:hover:before {
            top: -35%;
            background-color: #009087;
            transform: translateX(-50%) scaleY(1.3) scaleX(0.8);
        }

        .button2:hover:after {
            top: -45%;
            background-color: #009087;
            transform: translateX(-50%) scaleY(1.3) scaleX(0.8);
        }
    </style>
    <style>
        * { box-sizing: border-box; }
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; background: #f4f4f4; }
        header { background: #10b981; color: white; padding: 1rem; text-align: center; }
        nav { background: #059669; padding: 0.5rem; display: flex; justify-content: space-between; }
        nav ul { list-style: none; margin: 0; padding: 0; display: flex; gap: 1rem; }
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
        .container { max-width: 1200px; margin: 2rem auto; padding: 0 1rem; }
        
        .checkout-form {
            width: 100%;
        }
        .checkout-container {
            display: grid;
            grid-template-columns: minmax(0, 2fr) minmax(300px, 1fr);
            gap: 2rem;
            align-items: start;
        }
        .checkout-sidebar {
            position: sticky;
            top: 100px;
            align-self: start;
        }
        .checkout-section {
            min-width: 0;
        }
        
        .checkout-section {
            background: white;
            border-radius: 10px;
            padding: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }

        /* Footer Styles */
        footer {
            background-color: #10b981;
            color: white;
            text-align: center;
            padding: 20px;
            margin-top: 40px;
        }

        footer p {
            margin: 0 0 15px 0;
        }

        .footer-links {
            display: flex;
            justify-content: center;
            gap: 20px;
            align-items: center;
        }

        .footer-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(145deg, rgba(255, 255, 255, 0.2), rgba(255, 255, 255, 0.1));
            color: white;
            text-decoration: none;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            position: relative;
            overflow: hidden;
        }

        .footer-icon::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(145deg, rgba(255, 255, 255, 0.3), rgba(255, 255, 255, 0.1));
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .footer-icon:hover::before {
            opacity: 1;
        }

        .footer-icon:hover {
            background: linear-gradient(145deg, rgba(255, 255, 255, 0.4), rgba(255, 255, 255, 0.2));
            transform: scale(1.15) translateY(-3px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
        }

        .footer-icon svg {
            width: 24px;
            height: 24px;
            position: relative;
            z-index: 1;
            transition: transform 0.3s ease;
        }

        .footer-icon:hover svg {
            transform: scale(1.1);
        }
        
        .checkout-section h2 {
            margin-top: 0;
            color: #10b981;
            border-bottom: 2px solid #10b981;
            padding-bottom: 0.5rem;
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #333;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #10b981;
        }
        
        .cart-items {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        
        .cart-item {
            display: flex;
            gap: 1rem;
            padding: 1rem;
            background: #f9f9f9;
            border-radius: 5px;
            align-items: center;
        }
        
        .cart-item img {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 5px;
        }
        
        .cart-item-details {
            flex: 1;
        }
        
        .cart-item-details h4 {
            margin: 0 0 0.5rem 0;
            color: #333;
        }
        
        .cart-item-details p {
            margin: 0;
            color: #666;
            font-size: 0.9rem;
        }
        
        .cart-item-price {
            font-weight: bold;
            color: #10b981;
        }
        
        .order-summary {
            background: #f9f9f9;
            padding: 1rem;
            border-radius: 5px;
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            border-bottom: 1px solid #ddd;
        }
        
        .summary-row:last-child {
            border-bottom: none;
            font-weight: bold;
            font-size: 1.2rem;
            color: #10b981;
            padding-top: 1rem;
        }
        
        .btn-checkout {
            width: 100%;
            padding: 1rem;
            background: #10b981;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 1.1rem;
            font-weight: bold;
            cursor: pointer;
            margin-top: 1rem;
        }
        
        .btn-checkout:hover {
            background: #059669;
        }
        
        .empty-cart {
            text-align: center;
            padding: 3rem;
            color: #999;
        }
        
        .empty-cart a {
            color: #10b981;
            text-decoration: none;
        }
        
        @media (max-width: 768px) {
            .checkout-container {
                grid-template-columns: 1fr;
            }
        }
    </style>
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
            <li>
                <a href="profile.php">
                    <img src="<?php echo $_SESSION['pfp']; ?>" alt="Profile" style="width:20px; height:20px; border-radius:50%;">
                    <?php echo $_SESSION['username']; ?>
                </a>
            </li>
        </ul>
    </nav>

    <div class="container">
        <?php if (empty($cart_items)): ?>
            <div class="empty-cart">
                <p>Your cart is empty.</p>
                <a href="Buy.php" class="button2">Continue Shopping</a>
            </div>
        <?php else: ?>
            <?php if (!empty($checkout_error)): ?>
                <div style="background: #fee2e2; color: #9b2c2c; border: 1px solid #f5c2c7; padding: 1rem; border-radius: 8px; margin-bottom: 1rem;">
                    <?php echo htmlspecialchars($checkout_error); ?>
                </div>
            <?php endif; ?>
            <form method="POST" class="checkout-form">
                <div class="checkout-container">
                    <div class="checkout-main">
                        <!-- Shipping Information -->
                        <div class="checkout-section">
                            <h2>Shipping Information</h2>
                            <div class="form-group">
                                <label for="full_name">Full Name</label>
                                <input type="text" id="full_name" name="full_name" value="<?php echo htmlspecialchars($checkout_values['full_name']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="email">Email</label>
                                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($checkout_values['email']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="phone">Phone Number</label>
                                <input type="tel" id="phone" name="phone" placeholder="01XXXXXXXXX" value="<?php echo htmlspecialchars($checkout_values['phone']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="address">Address</label>
                                <textarea id="address" name="address" rows="3" required placeholder="Enter your full address"><?php echo htmlspecialchars($checkout_values['address']); ?></textarea>
                            </div>
                            <div class="form-group">
                                <label for="city">City</label>
                                <input type="text" id="city" name="city" value="<?php echo htmlspecialchars($checkout_values['city']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="postal_code">Postal Code</label>
                                <input type="text" id="postal_code" name="postal_code" value="<?php echo htmlspecialchars($checkout_values['postal_code']); ?>" required>
                            </div>
                            <input type="hidden" name="checkout_type" value="<?php echo htmlspecialchars($checkout_type); ?>">
                            <?php if ($checkout_type === 'single'): ?>
                                <input type="hidden" name="product_id" value="<?php echo intval($_GET['id'] ?? 0); ?>">
                            <?php elseif ($checkout_type === 'selected'): ?>
                                <input type="hidden" name="cart_ids" value="<?php echo htmlspecialchars($_GET['cart_ids'] ?? ''); ?>">
                            <?php endif; ?>

                            <!-- Payment Information -->
                    <div class="checkout-section">
                        <h2>Payment Information</h2>
                        <div class="form-group">
                            <label for="payment_method">Payment Method</label>
                            <select id="payment_method" name="payment_method" required>
                                <option value="">Select Payment Method</option>
                                <option value="cash" <?php echo $checkout_values['payment_method'] === 'cash' ? 'selected' : ''; ?>>Cash on Delivery</option>
                                <option value="bank_transfer" <?php echo $checkout_values['payment_method'] === 'bank_transfer' ? 'selected' : ''; ?>>Bank Transfer</option>
                                <option value="online_payment" <?php echo $checkout_values['payment_method'] === 'online_payment' ? 'selected' : ''; ?>>Online Payment (Coming Soon)</option>
                            </select>
                        </div>
                        <p style="color: #666; font-size: 0.9rem;">
                            Note: For Cash on Delivery, payment will be collected upon delivery. For Bank Transfer, details will be provided after order confirmation.
                        </p>
                    </div>
                </div>

                <div class="checkout-sidebar">
                    <!-- Order Summary -->
                    <div class="checkout-section">
                        <h2>Order Summary</h2>
                        <div class="cart-items">
                            <?php foreach ($cart_items as $item): ?>
                                <div class="cart-item">
                                    <img src="<?php echo htmlspecialchars($item['image_1'] ?: 'https://placehold.co/60x60?text=No+Image'); ?>" alt="<?php echo htmlspecialchars($item['brand'] . ' ' . $item['model']); ?>" onerror="this.src='https://placehold.co/60x60?text=No+Image'">
                                    <div class="cart-item-details">
                                        <h4><?php echo htmlspecialchars($item['brand'] . ' ' . $item['model']); ?></h4>
                                        <p>Condition: <?php echo htmlspecialchars($item['device_condition']); ?></p>
                                    </div>
                                    <div class="cart-item-price">RM <?php echo number_format($item['price'], 2); ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="order-summary">
                            <div class="summary-row">
                                <span>Subtotal</span>
                                <span>RM <?php echo number_format($total, 2); ?></span>
                            </div>
                            <div class="summary-row">
                                <span>Shipping Fee</span>
                                <span>RM 10.00</span>
                            </div>
                            <div class="summary-row">
                                <span>Total</span>
                                <span>RM <?php echo number_format($total + 10, 2); ?></span>
                            </div>
                        </div>
                        
                        <button type="submit" name="place_order" class="btn-checkout" formmethod="POST">Place Order</button>
                    </div>
                </div>
            </form>
            </div>
        <?php endif; ?>
    </div>

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
