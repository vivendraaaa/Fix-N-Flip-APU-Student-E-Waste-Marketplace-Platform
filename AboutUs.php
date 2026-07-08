<?php
session_start();

$cart_count = 0;
$notification_count = 0;
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in']) {
    $mysqli = new mysqli("localhost", "root", "", "fyp");
    if (!$mysqli->connect_error) {
        $user_id = $_SESSION['user_id'];

        $table_result = $mysqli->query("SHOW TABLES LIKE 'cart'");
        if ($table_result && $table_result->num_rows > 0) {
            $stmt = $mysqli->prepare("SELECT SUM(quantity) as total FROM cart WHERE user_id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $cart_count = $row['total'] ?: 0;
        }

        $table_result = $mysqli->query("SHOW TABLES LIKE 'notifications'");
        if ($table_result && $table_result->num_rows > 0) {
            $stmt = $mysqli->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND is_read = FALSE");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $notification_count = $row['total'] ?: 0;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fix N Flip - About Us</title>
    <link rel="stylesheet" href="index.css">
    <style>
        body.about-page {
            background:
                radial-gradient(circle at top left, rgba(16, 185, 129, 0.12), transparent 26%),
                radial-gradient(circle at right center, rgba(5, 150, 105, 0.1), transparent 22%),
                #f4f7fb;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        main {
            flex: 1;
        }

        .about-hero .hero-copy {
            background: linear-gradient(135deg, #064e3b 0%, #059669 55%, #10b981 100%);
            color: #fff;
        }

        .about-hero .hero-copy p,
        .about-hero .hero-copy .eyebrow,
        .about-hero .hero-copy .hero-points span {
            color: rgba(255, 255, 255, 0.92);
        }

        .about-hero .hero-points span {
            background: rgba(255, 255, 255, 0.12);
        }

        .about-hero .hero-panel {
            background: linear-gradient(180deg, #ffffff 0%, #f0fdf4 100%);
        }

        .about-stat {
            display: grid;
            gap: 10px;
        }

        .about-stat strong {
            font-size: 1.3rem;
        }

        .about-stat span {
            color: var(--muted);
        }

        .about-card.feature-card {
            border-radius: 28px;
            padding: 28px;
            background: #ffffff;
            border: 1px solid var(--border);
            box-shadow: var(--shadow);
        }

        .about-card.feature-card h2 {
            margin-top: 0;
        }

        .about-grid {
            display: grid;
            gap: 16px;
        }

        .about-grid.two-col {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .about-grid.three-col {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }

        .about-card .card-label {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 7px 12px;
            border-radius: 999px;
            background: #ecfdf5;
            color: var(--brand-dark);
            font-size: 0.8rem;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            margin-bottom: 14px;
        }

        .about-card h2,
        .about-card h3 {
            color: var(--text);
        }

        .about-card p {
            color: var(--muted);
        }

        .about-kpi {
            display: grid;
            gap: 6px;
            padding: 16px 0;
            border-top: 1px solid rgba(15, 23, 42, 0.08);
        }

        .about-kpi strong {
            font-size: 1.6rem;
            color: var(--text);
        }

        .about-kpi span {
            color: var(--muted);
        }

        .about-grid .step-card,
        .about-grid .trust-item,
        .about-grid .category-card {
            height: 100%;
        }

        .about-grid .trust-item {
            border-radius: 24px;
            padding: 22px;
        }

        .about-grid .category-card {
            min-height: auto;
        }

        .about-cta {
            margin-top: 18px;
        }

        .about-section {
            width: min(1180px, calc(100% - 32px));
            margin-left: auto;
            margin-right: auto;
        }

        .feedback-section {
            width: min(1180px, calc(100% - 32px));
            margin: 0 auto 18px;
        }

        .feedback-card {
            border-radius: 28px;
            padding: 30px;
            background: linear-gradient(135deg, #0f172a 0%, #0f766e 100%);
            color: #fff;
            box-shadow: var(--shadow);
            display: grid;
            gap: 16px;
            justify-items: center;
            text-align: center;
        }

        .feedback-card h2,
        .feedback-card p {
            margin: 0;
        }

        .feedback-card p {
            max-width: 760px;
            color: rgba(255, 255, 255, 0.84);
        }

        .feedback-card .btn-primary {
            background: #ffffff;
            color: #0f172a;
            box-shadow: none;
        }

        .feedback-card .btn-primary:hover {
            transform: translateY(-2px) scale(1.04);
        }


        @media (max-width: 900px) {
            .about-grid.two-col,
            .about-grid.three-col {
                grid-template-columns: 1fr;
            }

            .about-hero .hero-copy,
            .about-hero .hero-panel {
                border-radius: 22px;
            }
        }

        @media (max-width: 768px) {
            .about-section,
            .hero,
            .trust-strip,
            .section,
            .cta-band,
            footer {
                width: min(100% - 20px, 1180px);
            }

            .about-hero .hero-copy {
                padding: 28px;
            }
        }
    </style>
</head>
<body class="home-page about-page">
    <header>
        <h1>Fix N Flip</h1>
        <p>Buy & Sell Used Devices for APU Students</p>
        <script src="https://cdn.botpress.cloud/webchat/v3.6/inject.js"></script>
        <script src="https://files.bpcontent.cloud/2026/06/29/06/20260629061033-TO72SUAN.js" defer></script>
    </header>
    <main>
        <section class="hero about-hero" id="home">
            <div class="hero-copy reveal-card">
                <span class="eyebrow">Built for the APU community</span>
                <h1>About Fix N Flip</h1>
                <p>Fix N Flip helps APU students buy, sell, and repair used devices through one clean platform that makes each step easier to follow.</p>
                <div class="hero-actions">
                    <a href="Buy.php" class="btn btn-primary">Browse Devices</a>
                    <a href="Sell.php" class="btn btn-secondary">List a Device</a>
                </div>
                <div class="hero-points">
                    <span>Student focused</span>
                    <span>Safer handoff</span>
                    <span>Repair aware</span>
                </div>
            </div>
            <aside class="hero-panel reveal-card">
                <div class="panel-card panel-highlight">
                    <small>What we do</small>
                    <strong>Make second-hand device trading simpler and more trustworthy.</strong>
                    <p>Our workflows help students move from browsing to collection with less friction.</p>
                </div>
                <div class="panel-grid">
                    <div class="about-stat">
                        <span>Focus</span>
                        <strong>APU students</strong>
                    </div>
                    <div class="about-stat">
                        <span>Support</span>
                        <strong>Repairs + parts</strong>
                    </div>
                    <div class="about-stat">
                        <span>Process</span>
                        <strong>Clear workflows</strong>
                    </div>
                    <div class="about-stat">
                        <span>Goal</span>
                        <strong>Better value</strong>
                    </div>
                </div>
            </aside>
        </section>

        <section class="trust-strip">
            <div class="trust-item reveal-card">
                <strong>Community-based trading</strong>
                <p>Built for the devices APU students actually buy, sell, and repair.</p>
            </div>
            <div class="trust-item reveal-card">
                <strong>Safer transactions</strong>
                <p>Structured pickup, collection, and verification flows help reduce risk.</p>
            </div>
            <div class="trust-item reveal-card">
                <strong>Repair-aware platform</strong>
                <p>Repair requests, parts approval, and technician workflows stay in one place.</p>
            </div>
        </section>

        <section class="section about-section" id="mission">
            <div class="section-heading">
                <span class="section-kicker">Our mission</span>
                <h2>Keep second-hand device trading simple, affordable, and trustworthy.</h2>
                <p>We want students to have a place where buying and selling used devices feels organized instead of messy.</p>
            </div>
            <div class="about-grid two-col">
                <div class="split-copy reveal-card about-card feature-card">
                    <span class="card-label">Why it exists</span>
                    <h2>One platform for listings, collections, and repairs</h2>
                    <p>Fix N Flip reduces friction by bringing the whole lifecycle into one system. From the first listing to the final handoff, the flow stays clear for buyers, sellers, clerks, and technicians.</p>
                    <p class="about-cta">We built it to help students find better deals, move devices they no longer need, and keep every step easy to track.</p>
                </div>
                <div class="steps-grid">
                    <article class="step-card reveal-card">
                        <span>Step 1</span>
                        <h3>Browse or list</h3>
                        <p>Students can shop for devices or create listings for the items they want to sell.</p>
                    </article>
                    <article class="step-card reveal-card">
                        <span>Step 2</span>
                        <h3>Review and collect</h3>
                        <p>Clerks keep submission, collection, and pickup workflows organized and visible.</p>
                    </article>
                    <article class="step-card reveal-card">
                        <span>Step 3</span>
                        <h3>Repair when needed</h3>
                        <p>Devices that need work can move into repair with technician and parts support.</p>
                    </article>
                </div>
            </div>
        </section>

        <section class="section about-section" id="values">
            <div class="section-heading">
                <span class="section-kicker">Our values</span>
                <h2>Trust, affordability, and convenience shape the experience.</h2>
                <p>The page and workflows are designed to keep users informed while making each action straightforward.</p>
            </div>
            <div class="about-grid three-col">
                <a class="category-card reveal-card" href="Buy.php">
                    <span class="category-icon">01</span>
                    <h3>Trust</h3>
                    <p>Clear statuses and structured workflows help users know what is happening.</p>
                </a>
                <a class="category-card reveal-card" href="Sell.php">
                    <span class="category-icon">02</span>
                    <h3>Affordability</h3>
                    <p>Students can access used devices without paying full retail prices.</p>
                </a>
                <a class="category-card reveal-card" href="Index.php#contact">
                    <span class="category-icon">03</span>
                    <h3>Convenience</h3>
                    <p>Listings, collections, and repairs stay in one place instead of across channels.</p>
                </a>
            </div>
        </section>

        <section class="section about-section" id="users">
            <div class="section-heading">
                <span class="section-kicker">Who uses it</span>
                <h2>Designed for buyers, sellers, and the staff behind the scenes.</h2>
                <p>The platform supports each role with the same goal: making device trading easier to manage.</p>
            </div>
            <div class="about-grid three-col">
                <div class="trust-item reveal-card">
                    <strong>Buyers</strong>
                    <p class="team-role">Students looking for affordable used devices.</p>
                    <p>Compare listings, view device details, and purchase with a simpler browsing experience.</p>
                </div>
                <div class="trust-item reveal-card">
                    <strong>Sellers</strong>
                    <p class="team-role">Students who want to resell devices they no longer need.</p>
                    <p>List a device, wait for review, and move through the sale process with guidance.</p>
                </div>
                <div class="trust-item reveal-card">
                    <strong>Clerks & Technicians</strong>
                    <p class="team-role">Staff handling verification, repairs, and collections.</p>
                    <p>Specialized dashboards help keep the workflow transparent and operational.</p>
                </div>
            </div>
        </section>

        <section class="cta-band reveal-card">
            <div>
                <span class="section-kicker">Ready to use it?</span>
                <h2>Browse devices, list your own, or jump back to the home page.</h2>
            </div>
            <div class="cta-actions">
                <a href="Buy.php" class="btn btn-primary">Browse Listings</a>
                <a href="Index.php#contact" class="btn btn-secondary">Go to Home</a>
            </div>
        </section>

        <section class="feedback-section">
            <div class="feedback-card reveal-card">
                <h2>Feedback Form</h2>
                <p>We appreciate your feedback! Please take a moment to provide your comments and/or recommendations to better our overall experience with the Fix and Filp Platform.</p>
                <a href="https://forms.gle/hDKXDxTidMg99DKYA" target="_blank" class="btn btn-primary btn-lg">📝Share your feedback!</a>
                <p>Thank you for your consideration and we hope to hear from you soon!</p>
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
