<?php
$mysqli = new mysqli("localhost", "root", "", "fyp");

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

// Drop the corrupted table
$mysqli->query("DROP TABLE IF EXISTS roles");

// Recreate the table
$create_table = "CREATE TABLE roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    permissions TEXT
)";

if ($mysqli->query($create_table)) {
    echo "Roles table recreated successfully.<br>";
    
    // Insert default roles with appropriate permissions
    $default_roles = [
        ['Admin', json_encode(['manage_users', 'manage_products', 'manage_orders', 'manage_submissions', 'manage_roles', 'view_reports'])],
        ['Clerk', json_encode(['manage_submissions', 'manage_orders', 'view_reports'])],
        ['Technician', json_encode(['manage_submissions', 'view_reports'])],
        ['User', json_encode([])]
    ];
    
    foreach ($default_roles as $role) {
        $mysqli->query("INSERT INTO roles (name, permissions) VALUES ('$role[0]', '$role[1]')");
    }
    
    echo "Default roles inserted successfully.<br>";
    echo "<a href='admin.php?section=roles'>Return to Role Management</a>";
} else {
    echo "Error creating table: " . $mysqli->error;
}

$mysqli->close();
?>
