<?php
$mysqli = new mysqli("localhost", "root", "", "fyp");

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

// Check if proof_pictures column exists
$check_query = "SHOW COLUMNS FROM orders LIKE 'proof_pictures'";
$result = $mysqli->query($check_query);

if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    
    // Drop the existing JSON column
    $drop_query = "ALTER TABLE orders DROP COLUMN proof_pictures";
    if ($mysqli->query($drop_query)) {
        echo "✓ Dropped existing proof_pictures column<br>";
    } else {
        echo "✗ Error dropping column: " . $mysqli->error . "<br>";
    }
}

// Add new LONGBLOB column for binary image data
$add_query = "ALTER TABLE orders ADD COLUMN proof_pictures LONGBLOB DEFAULT NULL";
if ($mysqli->query($add_query)) {
    echo "✓ Added proof_pictures column as LONGBLOB<br>";
} else {
    echo "✗ Error adding column: " . $mysqli->error . "<br>";
}

echo "<br><strong>Migration complete! The proof_pictures column now stores binary image data directly in the database.</strong>";
$mysqli->close();
?>
