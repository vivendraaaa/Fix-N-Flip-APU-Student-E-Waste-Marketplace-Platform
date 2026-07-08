<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Terms of Service | Fix N Flip</title>
    <link rel="stylesheet" href="index.css">
    <style>
        body.home-page {
            background: #f4f7fb;
        }

        .policy-shell {
            width: min(900px, calc(100% - 32px));
            margin: 0 auto 24px;
        }

        .policy-card {
            background: #ffffff;
            border: 1px solid var(--border);
            border-radius: 28px;
            box-shadow: var(--shadow);
            padding: 32px;
        }

        .policy-card h2,
        .policy-card h3 {
            color: var(--text);
        }

        .policy-card p,
        .policy-card li {
            color: var(--muted);
            line-height: 1.7;
        }

        .policy-card ul {
            padding-left: 22px;
        }

        .policy-note {
            background: #ecfdf5;
            border-left: 4px solid var(--brand);
            padding: 18px;
            border-radius: 14px;
            margin-top: 18px;
        }
    </style>
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
            <li><a href="Login.php">Sign Up / Log In</a></li>
        </ul>
    </nav>

    <main>
        <section class="hero about-hero">
            <div class="hero-copy reveal-card">
                <span class="eyebrow">Legal information</span>
                <h1>Terms of Service</h1>
                <p>Last Updated: July 1, 2026</p>
            </div>
        </section>

        <section class="policy-shell">
            <div class="policy-card">
                <h2>Agreement to Terms</h2>
                <p>By accessing or using Fix N Flip, you agree to follow these Terms of Service. If you do not agree, you should not use the platform.</p>

                <h2>Platform Description</h2>
                <p>Fix N Flip is a student-focused marketplace for buying, selling, and managing used devices. The platform may include listings, cart features, notifications, order processing, and support communication.</p>

                <h2>Eligibility and Account Use</h2>
                <ul>
                    <li>You must provide accurate account information.</li>
                    <li>You are responsible for keeping your login details secure.</li>
                    <li>You must not use the platform for fraudulent or harmful activity.</li>
                </ul>

                <h2>User Responsibilities</h2>
                <ul>
                    <li>Only submit truthful listing and contact information.</li>
                    <li>Use the platform in a respectful and lawful way.</li>
                    <li>Do not interfere with platform security or operation.</li>
                    <li>Follow any additional rules shown during sales or support workflows.</li>
                </ul>

                <h2>Transactions and Listings</h2>
                <p>Users are responsible for the accuracy of their listings, communication, and transaction details. Fix N Flip may moderate, update, or remove content when required to protect the platform or its users.</p>

                <h2>Intellectual Property</h2>
                <p>All platform content, branding, and design elements remain the property of Fix N Flip unless otherwise stated. Users may not copy or reuse content without permission.</p>

                <h2>Limitation of Liability</h2>
                <p>Fix N Flip is provided on an "as is" basis. We are not responsible for losses caused by user actions, third-party services, technical issues, or misuse of the platform.</p>

                <h2>Contact Information</h2>
                <div class="policy-note">
                    <p style="margin: 0;"><strong>Email:</strong> <a href="mailto:fixnflip.platform@gmail.com">fixnflip.platform@gmail.com</a></p>
                    <p style="margin: 10px 0 0 0;"><strong>Platform:</strong> Fix N Flip</p>
                </div>
            </div>
        </section>
    </main>
</body>
</html>