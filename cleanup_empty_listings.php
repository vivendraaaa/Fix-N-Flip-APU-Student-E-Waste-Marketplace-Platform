<?php
$mysqli = new mysqli("localhost", "root", "", "fyp");

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

// Get the 3 listings without images
$listings = $mysqli->query("
    SELECT tl.id as listing_id, p.id as product_id
    FROM technician_listings tl
    LEFT JOIN products p ON p.name = tl.name AND p.brand = tl.brand
    WHERE tl.image1 IS NULL
    ORDER BY tl.created_at ASC
    LIMIT 3
");

echo "<h2>Cleaning up listings without images...</h2>";

$deleted_listings = 0;
$deleted_products = 0;

while ($row = $listings->fetch_assoc()) {
    // Delete product_images for this product
    if ($row['product_id']) {
        $mysqli->query("DELETE FROM product_images WHERE product_id = {$row['product_id']}");
        $mysqli->query("DELETE FROM products WHERE id = {$row['product_id']}");
        echo "Deleted product #{$row['product_id']}<br>";
        $deleted_products++;
    }
    
    // Delete listing
    $mysqli->query("DELETE FROM technician_listings WHERE id = {$row['listing_id']}");
    echo "Deleted listing #{$row['listing_id']}<br>";
    $deleted_listings++;
}

echo "<br><strong>Cleanup complete!</strong><br>";
echo "Deleted $deleted_listings listings and $deleted_products products<br>";
echo "<p>You can now create new listings with images. The form has been fixed to properly upload files.</p>";

$mysqli->close();
?>
