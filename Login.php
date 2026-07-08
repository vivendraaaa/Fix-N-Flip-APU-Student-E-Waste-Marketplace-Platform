<?php
session_start();
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

$login_error = "";
$signup_error = "";
$signup_success = "";
$show_signup = false;

// Redirect if already logged in as admin
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] && isset($_SESSION['role']) && strtolower($_SESSION['role']) === 'admin') {
    header("Location: admin.php");
    exit;
}

// Handle Sign Up
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['signup'])) {
    $username = trim($_POST['username']);
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm-password'];
    
    // Validation
    if (empty($username) || empty($full_name) || empty($email) || empty($phone) || empty($password) || empty($confirm_password)) {
        $signup_error = "All fields are required";
        $show_signup = true;
    } elseif ($password !== $confirm_password) {
        $signup_error = "Passwords do not match";
        $show_signup = true;
    } elseif (strlen($password) < 6) {
        $signup_error = "Password must be at least 6 characters";
        $show_signup = true;
    } else {
        // Check if username already exists
        $check_username = $mysqli->query("SELECT id FROM users WHERE BINARY username = '$username'");
        if ($check_username->num_rows > 0) {
            $signup_error = "Username already exists";
            $show_signup = true;
        } else {
            // Hash password and insert user with 'user' role
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $insert = $mysqli->query("INSERT INTO users (username, full_name, email, phone, password, role, created_at) VALUES ('$username', '$full_name', '$email', '$phone', '$hashed_password', 'user', NOW())");
            
            if ($insert) {
                $signup_success = "Account created successfully! You can now log in.";
                $show_signup = false;
            } else {
                $signup_error = "Error creating account. Please try again.";
                $show_signup = true;
            }
        }
    }
}

// Handle Login
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['login'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    if (empty($username) || empty($password)) {
        $login_error = "Username and password are required";
    } else {
        // Check if user exists
        $result = $mysqli->query("SELECT id, username, password, email, role, pfp FROM users WHERE BINARY username = '$username'");
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            
            // Verify password
            if (password_verify($password, $user['password'])) {
                // Login successful
                $_SESSION['logged_in'] = true;
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'] ?? 'user'; // Default to 'user' if role is null
                $_SESSION['pfp'] = $user['pfp'] ?? "https://via.placeholder.com/20"; // Use profile picture from database or default

                // Check if user was redirected from Sell page
                $redirect_to = 'Index.php';
                if (isset($_SESSION['sell_redirect']) && $_SESSION['sell_redirect']) {
                    $redirect_to = 'Sell.php';
                    unset($_SESSION['sell_redirect']);
                }

                // Redirect based on role (case-insensitive)
                if (strtolower($_SESSION['role']) === 'admin') {
                    session_write_close();
                    header("Location: admin.php");
                } elseif (strtolower($_SESSION['role']) === 'technician') {
                    session_write_close();
                    header("Location: technician.php");
                } elseif (strtolower($_SESSION['role']) === 'clerk') {
                    session_write_close();
                    header("Location: clerk.php");
                } else {
                    session_write_close();
                    header("Location: " . $redirect_to);
                }
                exit();
            } else {
                $login_error = "Invalid password";
            }
        } else {
            $login_error = "Username does not exist";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fix N Flip - Login & Sign Up</title>
    <link rel="stylesheet" href="login.css">
    <script src="login.js" defer></script>
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
        <div class="form-container" id="login-form" style="<?php echo $show_signup ? 'display: none;' : ''; ?>">
            <p class="title">Login</p>
            <?php if (!empty($login_error)): ?>
                <div class="error-message"><?php echo $login_error; ?></div>
            <?php endif; ?>
            <?php if (!empty($signup_success)): ?>
                <div class="success-message"><?php echo $signup_success; ?></div>
            <?php endif; ?>
            <form class="form" method="POST">
                <div class="input-group">
                    <label for="username">Username</label>
                    <input type="text" name="username" id="username" placeholder="" required>
                </div>
                <div class="input-group">
                    <label for="password">Password</label>
                    <input type="password" name="password" id="password" placeholder="" required>
                    <!-- <div class="forgot">
                        <a rel="noopener noreferrer" href="#">Forgot Password ?</a>
                    </div> -->
                </div>
                <button type="submit" name="login" class="sign">Sign in</button>
                
            </form>
            <div class="toggle">
                <p>Don't have an account? <a href="#" onclick="showSignup()">Sign up</a></p>
            </div>
        </div>
        <div class="form-container" id="signup-form" style="display: <?php echo $show_signup ? 'block' : 'none'; ?>;">
            <p class="title">Sign Up</p>
            <?php if (!empty($signup_error)): ?>
                <div class="error-message"><?php echo $signup_error; ?></div>
            <?php endif; ?>
            <form class="form" method="POST">
                <div class="input-group">
                    <label for="signup-username">Username</label>
                    <input type="text" name="username" id="signup-username" placeholder="" required>
                </div>
                <div class="input-group">
                    <label for="full_name">Full Name</label>
                    <input type="text" name="full_name" id="full_name" placeholder="" required>
                </div>
                <div class="input-group">
                    <label for="phone">Phone Number</label>
                    <input type="tel" name="phone" id="phone" placeholder="" required>
                </div>
                <div class="input-group">
                    <label for="email">Email</label>
                    <input type="email" name="email" id="email" placeholder="" required>
                </div>
                <div class="input-group">
                    <label for="signup-password">Password</label>
                    <input type="password" name="password" id="signup-password" placeholder="" required>
                </div>
                <div class="input-group">
                    <label for="confirm-password">Confirm Password</label>
                    <input type="password" name="confirm-password" id="confirm-password" placeholder="" required>
                </div>
                <button type="submit" name="signup" class="sign">Sign up</button>
            </form>
            <div class="toggle">
                <p>Already have an account? <a href="#" onclick="showLogin()">Login</a></p>
            </div>
        </div>
    </div>
    <footer id="contact">
        <p>&copy; 2026 Fix N Flip. All rights reserved.</p>
        <div class="footer-links">
            <a href="https://mail.google.com/mail/?view=cm&fs=1&to=fixnflip.platform@gmai.com&su=Subject%20Line&body=Hi Fix N Flip, I would like more info on this.!" target="_blank" class="footer-icon" title="Contact via Gmail">
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