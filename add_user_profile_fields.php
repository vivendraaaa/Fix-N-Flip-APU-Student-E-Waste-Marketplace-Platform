<?php
$mysqli = new mysqli("localhost", "root", "", "fyp");

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

// Add full_name column if it doesn't exist
$check_full_name = $mysqli->query("SHOW COLUMNS FROM users LIKE 'full_name'");
if ($check_full_name && $check_full_name->num_rows == 0) {
    $mysqli->query("ALTER TABLE users ADD COLUMN full_name VARCHAR(100) AFTER email");
    echo "Added full_name column to users table.<br>";
} else {
    echo "full_name column already exists.<br>";
}

// Add phone column if it doesn't exist
$check_phone = $mysqli->query("SHOW COLUMNS FROM users LIKE 'phone'");
if ($check_phone && $check_phone->num_rows == 0) {
    $mysqli->query("ALTER TABLE users ADD COLUMN phone VARCHAR(20) AFTER full_name");
    echo "Added phone column to users table.<br>";
} else {
    echo "phone column already exists.<br>";
}

echo "<br>Database update complete!";
?>
