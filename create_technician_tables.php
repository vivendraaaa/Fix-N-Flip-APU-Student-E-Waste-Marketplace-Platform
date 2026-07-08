<?php
$mysqli = new mysqli("localhost", "root", "", "fyp");

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

// Create repair_requests table
$create_repair_requests = "
CREATE TABLE IF NOT EXISTS repair_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    submission_id INT NOT NULL,
    technician_id INT NOT NULL,
    status ENUM('pending', 'accepted', 'rejected', 'picked_up', 'repairing', 'completed', 'unrepairable', 'disassembled') DEFAULT 'pending',
    pickup_code VARCHAR(20) UNIQUE,
    pickup_confirmed_at DATETIME,
    repair_started_at DATETIME,
    repair_ended_at DATETIME,
    repair_completed_at DATETIME,
    rejection_reason TEXT,
    unrepairable_reason TEXT,
    parts_requested TEXT,
    parts_request_pin VARCHAR(4),
    parts_request_status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (submission_id) REFERENCES device_submissions(id),
    FOREIGN KEY (technician_id) REFERENCES users(id)
)";

// Check if table exists and has correct structure
$check_table = $mysqli->query("SHOW TABLES LIKE 'repair_requests'");
if ($check_table && $check_table->num_rows > 0) {
    // Table exists, check for missing columns
    $check_submission = $mysqli->query("SHOW COLUMNS FROM repair_requests LIKE 'submission_id'");
    if (!$check_submission || $check_submission->num_rows === 0) {
        // Table exists but missing critical columns, drop and recreate
        $mysqli->query("SET FOREIGN_KEY_CHECKS = 0");
        $mysqli->query("DROP TABLE repair_requests");
        $mysqli->query("SET FOREIGN_KEY_CHECKS = 1");
        if ($mysqli->query($create_repair_requests)) {
            echo "Recreated repair_requests table with correct structure.<br>";
        } else {
            echo "Error recreating repair_requests table: " . $mysqli->error . "<br>";
        }
    } else {
        echo "Table 'repair_requests' already exists with correct structure.<br>";
    }
} else {
    if ($mysqli->query($create_repair_requests)) {
        echo "Table 'repair_requests' created successfully.<br>";
    } else {
        echo "Error creating repair_requests table: " . $mysqli->error . "<br>";
    }
}

// Create technician_listings table
$create_technician_listings = "
CREATE TABLE IF NOT EXISTS technician_listings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    repair_request_id INT NOT NULL,
    technician_id INT NOT NULL,
    brand VARCHAR(100),
    name VARCHAR(255),
    category VARCHAR(100),
    `condition` VARCHAR(50),
    specs TEXT,
    accessories TEXT,
    price DECIMAL(10, 2),
    repairs_done TEXT,
    status ENUM('pending_review', 'approved', 'rejected', 'needs_revision', 'sold') DEFAULT 'pending_review',
    clerk_notes TEXT,
    pickup_pin VARCHAR(6),
    pickup_status ENUM('pending', 'picked_up') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (repair_request_id) REFERENCES repair_requests(id),
    FOREIGN KEY (technician_id) REFERENCES users(id)
)";

// Check if table exists and has correct structure
$check_listings = $mysqli->query("SHOW TABLES LIKE 'technician_listings'");
if ($check_listings && $check_listings->num_rows > 0) {
    // Table exists, check for missing columns
    $check_repair_req = $mysqli->query("SHOW COLUMNS FROM technician_listings LIKE 'repair_request_id'");
    if (!$check_repair_req || $check_repair_req->num_rows === 0) {
        // Table exists but missing critical columns, drop and recreate
        $mysqli->query("SET FOREIGN_KEY_CHECKS = 0");
        $mysqli->query("DROP TABLE technician_listings");
        $mysqli->query("SET FOREIGN_KEY_CHECKS = 1");
        if ($mysqli->query($create_technician_listings)) {
            echo "Recreated technician_listings table with correct structure.<br>";
        } else {
            echo "Error recreating technician_listings table: " . $mysqli->error . "<br>";
        }
    } else {
        echo "Table 'technician_listings' already exists with correct structure.<br>";
    }
} else {
    if ($mysqli->query($create_technician_listings)) {
        echo "Table 'technician_listings' created successfully.<br>";
    } else {
        echo "Error creating technician_listings table: " . $mysqli->error . "<br>";
    }
}

// Create disassembly_requests table
$create_disassembly_requests = "
CREATE TABLE IF NOT EXISTS disassembly_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    submission_id INT NOT NULL,
    technician_id INT NOT NULL,
    status ENUM('pending', 'accepted', 'rejected', 'in_progress', 'completed') DEFAULT 'pending',
    pickup_code VARCHAR(20) UNIQUE,
    pickup_confirmed_at DATETIME,
    started_at DATETIME,
    completed_at DATETIME,
    rejection_reason TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (submission_id) REFERENCES device_submissions(id),
    FOREIGN KEY (technician_id) REFERENCES users(id)
)";

if ($mysqli->query($create_disassembly_requests)) {
    echo "Table 'disassembly_requests' created successfully or already exists.<br>";
} else {
    echo "Error creating disassembly_requests table: " . $mysqli->error . "<br>";
}

// Create parts_inventory table
$create_parts_inventory = "
CREATE TABLE IF NOT EXISTS parts_inventory (
    id INT AUTO_INCREMENT PRIMARY KEY,
    disassembly_request_id INT NOT NULL,
    device_model VARCHAR(255),
    device_specs TEXT,
    part_name VARCHAR(255),
    part_condition ENUM('working', 'damaged', 'unusable') DEFAULT 'working',
    quantity INT DEFAULT 1,
    location VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (disassembly_request_id) REFERENCES disassembly_requests(id)
)";

if ($mysqli->query($create_parts_inventory)) {
    echo "Table 'parts_inventory' created successfully or already exists.<br>";
} else {
    echo "Error creating parts_inventory table: " . $mysqli->error . "<br>";
}

// Add technician-specific columns to users table if not exists
$check_role = $mysqli->query("SHOW COLUMNS FROM users LIKE 'role'");
if ($check_role && $check_role->num_rows > 0) {
    // Check if technician role exists in role enum
    $role_info = $mysqli->query("SHOW COLUMNS FROM users WHERE Field = 'role'");
    $row = $role_info->fetch_assoc();
    $type = $row['Type'];
    
    if (strpos($type, 'technician') === false) {
        // Modify column to add technician
        $mysqli->query("ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'clerk', 'technician', 'user') DEFAULT 'user'");
        echo "Added 'technician' role to users table.<br>";
    }
} else {
    echo "Role column does not exist in users table.<br>";
}

// Add clerk role as well
$role_info = $mysqli->query("SHOW COLUMNS FROM users WHERE Field = 'role'");
$row = $role_info->fetch_assoc();
$type = $row['Type'];

if (strpos($type, 'clerk') === false) {
    $mysqli->query("ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'clerk', 'technician', 'user') DEFAULT 'user'");
    echo "Added 'clerk' role to users table.<br>";
}

echo "<br>Database setup complete!";
?>
