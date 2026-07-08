<?php
session_start();

// Database connection
$mysqli = new mysqli("localhost", "root", "", "fyp");

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : null;
$order_status = null;
$order_total = null;
if ($order_id) {
    $order_result = $mysqli->query("SELECT total_amount, status FROM orders WHERE id = $order_id");
    if ($order_result && $order_result->num_rows > 0) {
        $order = $order_result->fetch_assoc();
        $order_total = $order['total_amount'];
        $order_status = $order['status'];
    }
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

$user_id = $_SESSION['user_id'];

// Process the order (create order record, order items, and clear cart)
// For now, this is a placeholder implementation
// In a real implementation, you would:
// 1. Validate the form data
// 2. Create an order record in the orders table
// 3. Create order items in the order_items table
// 4. Update product status to 'sold'
// 5. Clear the cart (or selected items)
// 6. Send confirmation email

// Clear cart based on checkout type
$checkout_type = isset($_GET['type']) ? $_GET['type'] : 'all';

if ($checkout_type == 'single') {
    // For single product checkout, no cart items to clear
    // The product was never added to cart
} elseif ($checkout_type == 'selected') {
    // Clear only selected cart items
    $cart_ids = isset($_GET['ids']) ? explode(',', $_GET['ids']) : [];
    $cart_ids = array_map('intval', $cart_ids);
    
    if (!empty($cart_ids)) {
        $placeholders = str_repeat('?,', count($cart_ids) - 1) . '?';
        $query = "DELETE FROM cart WHERE id IN ($placeholders) AND user_id = ?";
        $stmt = $mysqli->prepare($query);
        
        $types = str_repeat('i', count($cart_ids)) . 'i';
        $params = array_merge($cart_ids, [$user_id]);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
    }
} else {
    // Clear the entire cart
    $check_table = "SHOW TABLES LIKE 'cart'";
    $table_result = $mysqli->query($check_table);
    
    if ($table_result && $table_result->num_rows > 0) {
        $mysqli->query("DELETE FROM cart WHERE user_id = $user_id");
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fix N Flip - Order Successful</title>
    <style>
        /* From Uiverse.io by shah1345 */
        .button2 {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 220px;
            transition: all 0.2s ease-in;
            position: relative;
            overflow: hidden;
            z-index: 1;
            color: #090909;
            padding: 1rem 1rem;
            cursor: pointer;
            font-size: 16px;
            border-radius: 0.5em;
            background: #e8e8e8;
            border: 1px solid #e8e8e8;
            box-shadow: 6px 6px 12px #c5c5c5, -6px -6px 12px #ffffff;
            text-decoration: none;
            text-align: center;
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
        .container { max-width: 600px; margin: 2rem auto; padding: 0 1rem; }
        
        .success-container {
            background: white;
            border-radius: 10px;
            padding: 3rem;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .success-icon {
            width: 100px;
            height: 100px;
            background: #10b981;
            border-radius: 50%;
            margin: 0 auto 2rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .success-icon svg {
            width: 60px;
            height: 60px;
            fill: white;
        }
        
        .success-container h1 {
            color: #10b981;
            margin-bottom: 1rem;
        }
        
        .success-container p {
            color: #666;
            margin-bottom: 2rem;
            line-height: 1.6;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 220px;
            padding: 1rem 1rem;
            background: #10b981;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
            margin: 0.5rem;
            line-height: 1.2;
        }
        .success-actions {
            display: flex;
            justify-content: center;
            gap: 1rem;
            flex-wrap: wrap;
            margin-top: 1.5rem;
        }
        
        .btn:hover {
            background: #059669;
        }
        
        .btn-light {
            background: #e8e8e8;
            color: #090909;
            border: 1px solid #d1d5db;
        }
        
        .btn-light:hover {
            background: #d1d5db;
        }

        .btn-secondary {
            background: #666;
        }
        
        .btn-secondary:hover {
            background: #555;
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
        <div class="success-container">
            <div class="success-icon">
                <svg viewBox="0 0 24 24">
                    <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>
                </svg>
            </div>
            <h1>Order Successful!</h1>
            <p>
                Thank you for your purchase. Your order has been placed successfully.<br>
                You will receive a confirmation email shortly with your order details.
            </p>
            <?php if ($order_id): ?>
                <p>
                    Order ID: #<?php echo $order_id; ?><br>
                    Status: <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $order_status ?? 'pending'))); ?><br>
                    Total: RM <?php echo number_format($order_total ?? 0, 2); ?>
                </p>
            <?php else: ?>
                <p>
                    Order ID: #<?php echo strtoupper(uniqid('ORD')); ?><br>
                    Date: <?php echo date('F j, Y, g:i a'); ?>
                </p>
            <?php endif; ?>
            <div class="success-actions">
                <a href="Buy.php" class="btn btn-light">Continue Shopping</a>
                <a href="profile.php" class="btn btn-secondary">View Orders</a>
            </div>
        </div>
    </div>

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
