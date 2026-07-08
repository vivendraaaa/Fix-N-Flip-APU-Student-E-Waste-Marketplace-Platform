<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Privacy Policy | Fix N Flip</title>
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
                <h1>Privacy Policy</h1>
                <p>Last Updated: July 1, 2026</p>
            </div>
        </section>

        <section class="policy-shell">
            <div class="policy-card">
                <h2>Introduction</h2>
                <p>Welcome to Fix N Flip. This Privacy Policy explains how we collect, use, store, and protect information when you use our platform to buy, sell, and manage device listings.</p>

                <h2>Information We Collect</h2>
                <h3>Account Information</h3>
                <ul>
                    <li>Name, email address, username, and password</li>
                    <li>Profile details such as student or staff role</li>
                    <li>Contact details provided when you reach out to us</li>
                </ul>

                <h3>Usage Information</h3>
                <ul>
                    <li>Pages visited, actions taken, and device details</li>
                    <li>Cart activity, listings, orders, and message history</li>
                    <li>Feedback submitted through forms or email</li>
                </ul>

                <h2>How We Use Your Information</h2>
                <ul>
                    <li>Create and manage user accounts</li>
                    <li>Process listings, orders, and device requests</li>
                    <li>Support communication between users and staff</li>
                    <li>Improve platform features and user experience</li>
                    <li>Maintain platform security and prevent misuse</li>
                </ul>

                <h2>Information Sharing</h2>
                <p>We do not sell your personal information. We may share information only when needed to operate the platform, respond to support requests, comply with legal requirements, or process a transaction or service request.</p>

                <h2>Data Security</h2>
                <p>We use reasonable technical and organizational safeguards to protect your data. However, no online platform can guarantee absolute security.</p>

                <h2>Your Choices</h2>
                <ul>
                    <li>Review or update your account details</li>
                    <li>Request assistance regarding stored information</li>
                    <li>Contact us about removal or correction requests</li>
                </ul>

                <h2>Contact Us</h2>
                <div class="policy-note">
                    <p style="margin: 0;"><strong>Email:</strong> <a href="mailto:fixnflip.platform@gmail.com">fixnflip.platform@gmail.com</a></p>
                    <p style="margin: 10px 0 0 0;"><strong>Platform:</strong> Fix N Flip</p>
                </div>
            </div>
        </section>
    </main>
</body>
</html>