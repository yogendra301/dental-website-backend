<?php
// Copy reviews and gallery from clinic_001 to clinic_003
// Run this via: http://localhost/dental-website-backend/copy_reviews_gallery.php

require 'application/config/database.php';

$mysqli = new mysqli($db['default']['hostname'], $db['default']['username'], $db['default']['password'], $db['default']['database']);

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

// Get clinic IDs
$clinic001 = $mysqli->query("SELECT id FROM clinics WHERE username = 'clinic_001'")->fetch_assoc()['id'] ?? null;
$clinic003 = $mysqli->query("SELECT id FROM clinics WHERE username = 'clinic_003'")->fetch_assoc()['id'] ?? null;

if (!$clinic001 || !$clinic003) {
    die("Error: Could not find clinic_001 or clinic_003 in database.\n");
}

echo "clinic_001 ID: $clinic001\n";
echo "clinic_003 ID: $clinic003\n\n";

// Copy reviews from clinic_001 to clinic_003
$reviewsQuery = "SELECT reviews FROM clinics WHERE id = $clinic001";
$reviewsResult = $mysqli->query($reviewsQuery);
if ($reviewsResult) {
    $reviews = $reviewsResult->fetch_assoc()['reviews'];
    $updateReviews = "UPDATE clinics SET reviews = '" . $mysqli->real_escape_string($reviews) . "' WHERE id = $clinic003";
    if ($mysqli->query($updateReviews)) {
        echo "✓ Reviews copied from clinic_001 to clinic_003\n";
    } else {
        echo "✗ Failed to copy reviews: " . $mysqli->error . "\n";
    }
}

// Copy gallery entries from clinic_001 to clinic_003
$galleryQuery = "SELECT * FROM gallery WHERE clinic_id = $clinic001";
$galleryResult = $mysqli->query($galleryQuery);

if ($galleryResult) {
    $copiedCount = 0;
    while ($row = $galleryResult->fetch_assoc()) {
        $oldId = $row['id'];
        unset($row['id']);
        $row['clinic_id'] = $clinic003;
        
        // Update image URLs to point to clinic_003 folder
        if ($row['image_url']) {
            $row['image_url'] = str_replace('/uploads/assets/clinic_001/', '/uploads/assets/clinic_003/', $row['image_url']);
        }
        if ($row['before_url']) {
            $row['before_url'] = str_replace('/uploads/assets/clinic_001/', '/uploads/assets/clinic_003/', $row['before_url']);
        }
        if ($row['after_url']) {
            $row['after_url'] = str_replace('/uploads/assets/clinic_001/', '/uploads/assets/clinic_003/', $row['after_url']);
        }
        
        $columns = implode(',', array_keys($row));
        $values = "'" . implode("','", array_map([$mysqli, 'real_escape_string'], $row)) . "'";
        
        $insertQuery = "INSERT INTO gallery ($columns) VALUES ($values)";
        if ($mysqli->query($insertQuery)) {
            $copiedCount++;
        } else {
            echo "✗ Failed to copy gallery entry $oldId: " . $mysqli->error . "\n";
        }
    }
    echo "✓ Copied $copiedCount gallery entries from clinic_001 to clinic_003\n";
} else {
    echo "✗ Failed to fetch gallery: " . $mysqli->error . "\n";
}

$mysqli->close();
echo "\nDone!\n";
