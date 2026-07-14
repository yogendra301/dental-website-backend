<?php
// Check current reviews and gallery for clinic_003
require 'application/config/database.php';

$mysqli = new mysqli($db['default']['hostname'], $db['default']['username'], $db['default']['password'], $db['default']['database']);

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

$clinic003 = $mysqli->query("SELECT id, username FROM clinics WHERE username = 'clinic_003'")->fetch_assoc();

if (!$clinic003) {
    die("Error: clinic_003 not found in database.\n");
}

echo "clinic_003 ID: " . $clinic003['id'] . "\n\n";

// Check reviews
$reviews = $mysqli->query("SELECT reviews FROM clinics WHERE id = " . $clinic003['id'])->fetch_assoc()['reviews'];
echo "=== REVIEWS ===\n";
if ($reviews) {
    echo $reviews . "\n";
} else {
    echo "No reviews found (NULL)\n";
}

// Check gallery
echo "\n=== GALLERY ===\n";
$gallery = $mysqli->query("SELECT * FROM gallery WHERE clinic_id = " . $clinic003['id']);
$galleryCount = $gallery->num_rows;
echo "Gallery entries: $galleryCount\n";

if ($galleryCount > 0) {
    while ($row = $gallery->fetch_assoc()) {
        echo "- ID: " . $row['id'] . ", Type: " . $row['type'] . ", Image: " . $row['image_url'] . "\n";
    }
} else {
    echo "No gallery entries found\n";
}

$mysqli->close();
