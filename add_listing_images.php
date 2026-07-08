<?php
$mysqli = new mysqli("localhost", "root", "", "fyp");

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

// Add image and video columns to technician_listings table
$alter_table = "
ALTER TABLE technician_listings
ADD COLUMN IF NOT EXISTS image1 LONGBLOB NULL,
ADD COLUMN IF NOT EXISTS image2 LONGBLOB NULL,
ADD COLUMN IF NOT EXISTS image3 LONGBLOB NULL,
ADD COLUMN IF NOT EXISTS video LONGBLOB NULL;
";

// Check if columns exist
$check_image1 = $mysqli->query("SHOW COLUMNS FROM technician_listings LIKE 'image1'");
$check_image2 = $mysqli->query("SHOW COLUMNS FROM technician_listings LIKE 'image2'");
$check_image3 = $mysqli->query("SHOW COLUMNS FROM technician_listings LIKE 'image3'");
$check_video = $mysqli->query("SHOW COLUMNS FROM technician_listings LIKE 'video'");

if (!$check_image1 || $check_image1->num_rows === 0) {
    if ($mysqli->query("ALTER TABLE technician_listings ADD COLUMN image1 LONGBLOB NULL")) {
        echo "Column 'image1' added successfully.<br>";
    } else {
        echo "Error adding 'image1': " . $mysqli->error . "<br>";
    }
} else {
    echo "Column 'image1' already exists.<br>";
}

if (!$check_image2 || $check_image2->num_rows === 0) {
    if ($mysqli->query("ALTER TABLE technician_listings ADD COLUMN image2 LONGBLOB NULL")) {
        echo "Column 'image2' added successfully.<br>";
    } else {
        echo "Error adding 'image2': " . $mysqli->error . "<br>";
    }
} else {
    echo "Column 'image2' already exists.<br>";
}

if (!$check_image3 || $check_image3->num_rows === 0) {
    if ($mysqli->query("ALTER TABLE technician_listings ADD COLUMN image3 LONGBLOB NULL")) {
        echo "Column 'image3' added successfully.<br>";
    } else {
        echo "Error adding 'image3': " . $mysqli->error . "<br>";
    }
} else {
    echo "Column 'image3' already exists.<br>";
}

if (!$check_video || $check_video->num_rows === 0) {
    if ($mysqli->query("ALTER TABLE technician_listings ADD COLUMN video LONGBLOB NULL")) {
        echo "Column 'video' added successfully.<br>";
    } else {
        echo "Error adding 'video': " . $mysqli->error . "<br>";
    }
} else {
    echo "Column 'video' already exists.<br>";
}

echo "<br>Migration completed.";
