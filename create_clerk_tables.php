<?php
$mysqli = new mysqli("localhost", "root", "", "fyp");

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

// Add phone number column to users table
$check_phone = $mysqli->query("SHOW COLUMNS FROM users LIKE 'phone'");
if (!$check_phone || $check_phone->num_rows === 0) {
    $mysqli->query("ALTER TABLE users ADD COLUMN phone VARCHAR(20) AFTER email");
    echo "Added phone column to users table.<br>";
}

// Create in_person_reviews table
$create_in_person_reviews = "
CREATE TABLE IF NOT EXISTS in_person_reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    submission_id INT NOT NULL,
    user_id INT NOT NULL,
    clerk_id INT,
    status ENUM('pending', 'approved', 'time_change_requested', 'in_progress', 'completed', 'cancelled', 'no_show') DEFAULT 'pending',
    requested_date DATE,
    requested_time TIME,
    requested_location VARCHAR(255),
    approved_date DATE,
    approved_time TIME,
    approved_location VARCHAR(255),
    clerk_arrived_at DATETIME,
    user_arrived_at DATETIME,
    ended_at DATETIME,
    end_reason TEXT,
    clerk_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (submission_id) REFERENCES device_submissions(id),
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (clerk_id) REFERENCES users(id)
)";

if ($mysqli->query($create_in_person_reviews)) {
    echo "Table 'in_person_reviews' created successfully or already exists.<br>";
} else {
    echo "Error creating in_person_reviews table: " . $mysqli->error . "<br>";
}

// Create product_locations table
$create_product_locations = "
CREATE TABLE IF NOT EXISTS product_locations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    product_type ENUM('submission', 'repair', 'listing') NOT NULL,
    location VARCHAR(100),
    shelf VARCHAR(50),
    added_by INT NOT NULL,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (added_by) REFERENCES users(id)
)";

if ($mysqli->query($create_product_locations)) {
    echo "Table 'product_locations' created successfully or already exists.<br>";
} else {
    echo "Error creating product_locations table: " . $mysqli->error . "<br>";
}

// Create chat_messages table
$create_chat_messages = "
CREATE TABLE IF NOT EXISTS chat_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES users(id),
    FOREIGN KEY (receiver_id) REFERENCES users(id)
)";

if ($mysqli->query($create_chat_messages)) {
    echo "Table 'chat_messages' created successfully or already exists.<br>";
} else {
    echo "Error creating chat_messages table: " . $mysqli->error . "<br>";
}

// Create live_chat_requests table
$create_live_chat_requests = "
CREATE TABLE IF NOT EXISTS live_chat_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    clerk_id INT NULL,
    question TEXT NOT NULL,
    status ENUM('pending', 'accepted', 'completed', 'cancelled') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (clerk_id) REFERENCES users(id)
)";

if ($mysqli->query($create_live_chat_requests)) {
    echo "Table 'live_chat_requests' created successfully or already exists.<br>";
} else {
    echo "Error creating live_chat_requests table: " . $mysqli->error . "<br>";
}

// Create clerk_notifications table
$create_clerk_notifications = "
CREATE TABLE IF NOT EXISTS clerk_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    clerk_id INT NOT NULL,
    type ENUM('new_submission', 'in_person_request', 'listing_review', 'repair_delay', 'chat_message') NOT NULL,
    title VARCHAR(255),
    message TEXT,
    related_id INT,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (clerk_id) REFERENCES users(id)
)";

if ($mysqli->query($create_clerk_notifications)) {
    echo "Table 'clerk_notifications' created successfully or already exists.<br>";
} else {
    echo "Error creating clerk_notifications table: " . $mysqli->error . "<br>";
}

// Add location column to device_submissions if not exists
$check_sub_location = $mysqli->query("SHOW COLUMNS FROM device_submissions LIKE 'location'");
if (!$check_sub_location || $check_sub_location->num_rows === 0) {
    $mysqli->query("ALTER TABLE device_submissions ADD COLUMN location VARCHAR(255) AFTER estimated_price");
    echo "Added location column to device_submissions table.<br>";
}

// Add qr_code column to device_submissions if not exists (legacy)
$check_qr_code = $mysqli->query("SHOW COLUMNS FROM device_submissions LIKE 'qr_code'");
if (!$check_qr_code || $check_qr_code->num_rows === 0) {
    $mysqli->query("ALTER TABLE device_submissions ADD COLUMN qr_code VARCHAR(50) AFTER location");
    echo "Added qr_code column to device_submissions table.<br>";
}

// Add drop_pin column to device_submissions if not exists
$check_drop_pin = $mysqli->query("SHOW COLUMNS FROM device_submissions LIKE 'drop_pin'");
if (!$check_drop_pin || $check_drop_pin->num_rows === 0) {
    $mysqli->query("ALTER TABLE device_submissions ADD COLUMN drop_pin VARCHAR(6) AFTER qr_code");
    echo "Added drop_pin column to device_submissions table.<br>";
}

// Add location column to repair_requests if not exists
$check_repair_location = $mysqli->query("SHOW COLUMNS FROM repair_requests LIKE 'location'");
if (!$check_repair_location || $check_repair_location->num_rows === 0) {
    $mysqli->query("ALTER TABLE repair_requests ADD COLUMN location VARCHAR(255) AFTER pickup_code");
    echo "Added location column to repair_requests table.<br>";
}

// Add estimated_completion column to repair_requests if not exists
$check_est_completion = $mysqli->query("SHOW COLUMNS FROM repair_requests LIKE 'estimated_completion'");
if (!$check_est_completion || $check_est_completion->num_rows === 0) {
    $mysqli->query("ALTER TABLE repair_requests ADD COLUMN estimated_completion DATETIME AFTER repair_started_at");
    echo "Added estimated_completion column to repair_requests table.<br>";
}

echo "<br>Clerk database setup complete!";
?>
