<?php
$mysqli = new mysqli("localhost", "root", "", "fyp");

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

// Get products that were created from approved technician listings
$products = $mysqli->query("
    SELECT p.id, p.name, p.brand, tl.id as listing_id, tl.image1, tl.image2, tl.image3
    FROM products p
    LEFT JOIN technician_listings tl ON tl.name = p.name AND tl.brand = p.brand AND tl.status = 'approved'
    WHERE tl.image1 IS NOT NULL OR tl.image2 IS NOT NULL OR tl.image3 IS NOT NULL
    ORDER BY p.id DESC
    LIMIT 10
");

echo "<h2>Transferring images to products...</h2>";

$count = 0;
while ($row = $products->fetch_assoc()) {
    // Check if this product already has images
    $image_check = $mysqli->query("SELECT COUNT(*) as cnt FROM product_images WHERE product_id = {$row['id']}")->fetch_assoc();
    
    if ($image_check['cnt'] == 0) {
        // Add images from technician listing
        if ($row['image1']) {
            $mysqli->query("INSERT INTO product_images (product_id, image) VALUES ({$row['id']}, x'" . bin2hex($row['image1']) . "')");
            echo "Added image1 to product {$row['id']}<br>";
        }
        if ($row['image2']) {
            $mysqli->query("INSERT INTO product_images (product_id, image) VALUES ({$row['id']}, x'" . bin2hex($row['image2']) . "')");
            echo "Added image2 to product {$row['id']}<br>";
        }
        if ($row['image3']) {
            $mysqli->query("INSERT INTO product_images (product_id, image) VALUES ({$row['id']}, x'" . bin2hex($row['image3']) . "')");
            echo "Added image3 to product {$row['id']}<br>";
        }
        $count++;
    }
}

echo "<br><strong>Updated $count products with images from technician listings.</strong>";

$mysqli->close();
?>
