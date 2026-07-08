<?php
$mysqli = new mysqli("localhost", "root", "", "fyp");

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

$id = intval($_GET['id'] ?? 0);

$stmt = $mysqli->prepare("
    SELECT tl.*, rr.submission_id, ds.device_type
    FROM technician_listings tl
    LEFT JOIN repair_requests rr ON tl.repair_request_id = rr.id
    LEFT JOIN device_submissions ds ON rr.submission_id = ds.id
    WHERE tl.id = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$listing = $result->fetch_assoc();

if ($listing) {
    // Convert image and video data to base64 for display
    // Data might be stored as binary or hex, so we handle both cases
    
    if ($listing['image1']) {
        $image_data = $listing['image1'];
        // Check if it's already binary data
        if (strlen($image_data) > 0) {
            $listing['image1'] = 'data:image/jpeg;base64,' . base64_encode($image_data);
        }
    }
    
    if ($listing['image2']) {
        $image_data = $listing['image2'];
        if (strlen($image_data) > 0) {
            $listing['image2'] = 'data:image/jpeg;base64,' . base64_encode($image_data);
        }
    }
    
    if ($listing['image3']) {
        $image_data = $listing['image3'];
        if (strlen($image_data) > 0) {
            $listing['image3'] = 'data:image/jpeg;base64,' . base64_encode($image_data);
        }
    }
    
    if ($listing['video']) {
        $video_data = $listing['video'];
        if (strlen($video_data) > 0) {
            $listing['video'] = 'data:video/mp4;base64,' . base64_encode($video_data);
        }
    }
    
    echo json_encode($listing);
} else {
    echo json_encode(['error' => 'Listing not found']);
}
?>
