<?php
$mysqli = new mysqli("localhost", "root", "", "fyp");

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

// Check if subject column exists
$check_query = "SHOW COLUMNS FROM notifications LIKE 'subject'";
$result = $mysqli->query($check_query);

if ($result && $result->num_rows > 0) {
    echo "✓ Subject column already exists<br>";
} else {
    // Add subject column
    $add_query = "ALTER TABLE notifications ADD COLUMN subject VARCHAR(255) DEFAULT NULL AFTER type";
    if ($mysqli->query($add_query)) {
        echo "✓ Added subject column to notifications table<br>";
    } else {
        echo "✗ Error adding column: " . $mysqli->error . "<br>";
    }
}

// Update existing notifications to have subjects based on their type
$update_queries = [
    "UPDATE notifications SET subject = 'Price Change Notification' WHERE type = 'price_change' AND subject IS NULL",
    "UPDATE notifications SET subject = 'Flagged Item' WHERE type = 'flag' AND subject IS NULL",
    "UPDATE notifications SET subject = 'Approval Notification' WHERE type = 'approval' AND subject IS NULL",
    "UPDATE notifications SET subject = 'Rejection Notification' WHERE type = 'rejection' AND subject IS NULL",
    "UPDATE notifications SET subject = 'Order Notification' WHERE type IS NULL AND subject IS NULL"
];

foreach ($update_queries as $query) {
    $mysqli->query($query);
}

echo "<br><strong>Migration complete! Added subject column and updated existing notifications.</strong>";
$mysqli->close();
?>
