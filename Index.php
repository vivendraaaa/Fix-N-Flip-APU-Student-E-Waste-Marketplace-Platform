<?php 
session_start();

// Fetch cart count
$cart_count = 0;
$notification_count = 0;
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in']) {
    $mysqli = new mysqli("localhost", "root", "", "fyp");
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fix N Flip - Buy & Sell Used Devices</title>
    <link rel="stylesheet" href="index.css">
    <script src="index.js" defer></script>
    <script src="https://cdn.botpress.cloud/webchat/v3.6/inject.js"></script>
    <script src="https://files.bpcontent.cloud/2026/06/29/06/20260629061033-TO72SUAN.js" defer></script>
</head>
<body class="home-page">
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
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <path d="M3 3H5L5.4 5M7 13H17L21 5H5.4M7 13L5.4 5M7 13L4.707 15.293C4.077 15.923 4.523 17 5.414 17H17M17 17C15.895 17 15 17.895 15 19C15 20.105 15.895 21 17 21C18.105 21 19 20.105 19 19C19 17.895 18.105 17 17 17ZM9 19C9 20.105 8.105 21 7 21C5.895 21 5 20.105 5 19C5 17.895 5.895 17 7 17C8.105 17 9 17.895 9 19Z" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                </svg>
                Cart
                <?php if ($cart_count > 0): ?>
                    <span class="cart-badge"><?php echo $cart_count; ?></span>
                <?php endif; ?>
            </a></li>
            <?php if (isset($_SESSION['logged_in']) && $_SESSION['logged_in']): ?>
                <li><a href="notifications.php">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                        <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                        <path d="M13.73 21a2 2 0 0 1-3.46 0" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                    </svg>
                    Notifications
                    <?php if ($notification_count > 0): ?>
                        <span class="cart-badge"><?php echo $notification_count; ?></span>
                    <?php endif; ?>
                </a></li>
                <li>
                    <a href="profile.php">
                        <img src="<?php echo $_SESSION['pfp']; ?>" alt="Profile" class="profile-pic">
                        <?php echo $_SESSION['username']; ?>
                    </a>
                </li>
            <?php else: ?>
                <li><a href="Login.php">Sign Up / Log In</a></li>
            <?php endif; ?>
        </ul>
    </nav>

    <main>
        <section class="hero" id="home">
            <div class="hero-copy reveal-card">
                <span class="eyebrow">Campus tech, cleaner and cheaper</span>
                <h1>Find refurbished-quality value from people you can trust.</h1>
                <p>Fix N Flip gives APU students a polished place to buy, sell, and trade used devices with less friction and more confidence.</p>
                <div class="hero-actions">
                    <a href="Buy.php" class="btn btn-primary">Shop Devices</a>
                    <a href="Sell.php" class="btn btn-secondary">List Yours</a>
                </div>
                <div class="hero-points">
                    <span>Verified listings</span>
                    <span>Fast handoff</span>
                    <span>Student focused</span>
                </div>
            </div>
            <aside class="hero-panel reveal-card">
                <div class="panel-card panel-highlight">
                    <small>Trending now</small>
                    <strong>Phones, laptops, tablets and accessories</strong>
                    <p>Browse the most popular categories on the marketplace.</p>
                </div>
                <div class="panel-grid">
                    <div>
                        <span>Secure</span>
                        <strong>Community trade</strong>
                    </div>
                    <div>
                        <span>Quick</span>
                        <strong>Easy listing flow</strong>
                    </div>
                    <div>
                        <span>Smart</span>
                        <strong>Fair pricing</strong>
                    </div>
                    <div>
                        <span>Local</span>
                        <strong>APU only</strong>
                    </div>
                </div>
            </aside>
        </section>

        <section class="trust-strip">
            <div class="trust-item reveal-card">
                <strong>Trusted by students</strong>
                <p>A simple marketplace built for the APU community.</p>
            </div>
            <div class="trust-item reveal-card">
                <strong>Better device value</strong>
                <p>Buy and sell without paying retail prices.</p>
            </div>
            <div class="trust-item reveal-card">
                <strong>Faster decisions</strong>
                <p>Clear listings and direct conversation make it easy to move.</p>
            </div>
        </section>

        <section class="section" id="categories">
            <div class="section-heading">
                <span class="section-kicker">Browse categories</span>
                <h2>Shop by what people actually trade</h2>
                <p>Start with the most common device categories and jump straight into the listings.</p>
            </div>
            <div class="category-grid">
                <a class="category-card reveal-card" href="Buy.php">
                    <span class="category-icon">01</span>
                    <h3>Phones</h3>
                    <p>Pre-owned smartphones with strong value and solid condition.</p>
                </a>
                <a class="category-card reveal-card" href="Buy.php">
                    <span class="category-icon">02</span>
                    <h3>Laptops</h3>
                    <p>Reliable laptops for study, work, and everyday campus use.</p>
                </a>
                <a class="category-card reveal-card" href="Buy.php">
                    <span class="category-icon">03</span>
                    <h3>Tablets</h3>
                    <p>Lightweight devices for notes, media, and portable productivity.</p>
                </a>
                <a class="category-card reveal-card" href="Buy.php">
                    <span class="category-icon">04</span>
                    <h3>Accessories</h3>
                    <p>Chargers, cases, keyboards and extras to complete your setup.</p>
                </a>
            </div>
        </section>

        <section class="section split-section" id="why-us">
            <div class="split-copy reveal-card">
                <span class="section-kicker">Why Fix N Flip</span>
                <h2>A cleaner marketplace experience</h2>
                <p>We keep the layout simple, the actions obvious, and the experience focused on getting devices listed and sold faster.</p>
                <a href="AboutUs.php" class="btn btn-primary">Learn More</a>
            </div>
            <div class="steps-grid">
                <article class="step-card reveal-card">
                    <span>Step 1</span>
                    <h3>Browse or list</h3>
                    <p>Open the catalog or create a listing in a few clicks.</p>
                </article>
                <article class="step-card reveal-card">
                    <span>Step 2</span>
                    <h3>Connect directly</h3>
                    <p>Use the platform to talk to buyers or sellers before meeting.</p>
                </article>
                <article class="step-card reveal-card">
                    <span>Step 3</span>
                    <h3>Close the deal</h3>
                    <p>Complete the exchange with less hassle and more confidence.</p>
                </article>
            </div>
        </section>

        <section class="cta-band reveal-card">
            <div>
                <span class="section-kicker">Ready to start?</span>
                <h2>Shop, sell, and upgrade your device setup today.</h2>
            </div>
            <div class="cta-actions">
                <a href="Buy.php" class="btn btn-primary">Browse Listings</a>
                <a href="Sell.php" class="btn btn-secondary">Sell a Device</a>
            </div>
        </section>
    </main>

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