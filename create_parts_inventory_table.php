<?php
$mysqli = new mysqli("localhost", "root", "", "fyp");

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

// Create parts_inventory table
$create_parts_inventory = "
CREATE TABLE IF NOT EXISTS parts_inventory (
    id INT AUTO_INCREMENT PRIMARY KEY,
    part_name VARCHAR(255) NOT NULL,
    device_model VARCHAR(255),
    `condition` ENUM('working', 'damaged', 'unusable') DEFAULT 'working',
    quantity INT DEFAULT 1,
    location VARCHAR(100) DEFAULT 'Fix N Flip Storage office Shelf 8A',
    shelf VARCHAR(50),
    source_type ENUM('disassembly', 'disposal', 'purchase') DEFAULT 'disassembly',
    source_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

if ($mysqli->query($create_parts_inventory)) {
    echo "Table 'parts_inventory' created successfully or already exists.<br>";
} else {
    echo "Error creating parts_inventory table: " . $mysqli->error . "<br>";
}

// Create parts_requests table (separate from repair_requests)
$create_parts_requests = "
CREATE TABLE IF NOT EXISTS parts_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    repair_request_id INT NOT NULL,
    technician_id INT NOT NULL,
    parts_needed TEXT NOT NULL,
    pin VARCHAR(4),
    status ENUM('pending', 'approved', 'rejected', 'fulfilled') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (repair_request_id) REFERENCES repair_requests(id),
    FOREIGN KEY (technician_id) REFERENCES users(id)
)";

if ($mysqli->query($create_parts_requests)) {
    echo "Table 'parts_requests' created successfully or already exists.<br>";
} else {
    echo "Error creating parts_requests table: " . $mysqli->error . "<br>";
}

// Add location column to repair_requests for parts
$check_location = $mysqli->query("SHOW COLUMNS FROM repair_requests LIKE 'parts_location'");
if ($check_location && $check_location->num_rows > 0) {
    echo "Column 'parts_location' already exists in repair_requests table.<br>";
} else {
    $result = $mysqli->query("ALTER TABLE repair_requests ADD COLUMN parts_location VARCHAR(100) AFTER parts_request_status");
    if ($result) {
        echo "Column 'parts_location' added successfully to repair_requests table.<br>";
    } else {
        echo "Error adding parts_location column: " . $mysqli->error . "<br>";
    }
}

echo "<br>Parts management tables setup complete!";
?>
