<?php
require_once '../sessions.php';
include('../db.php');

// Only admins can upload here
if (!isset($_SESSION['username']) || $_SESSION['user_type'] !== 'admin') {
    header('location: ../index.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;

    if ($product_id <= 0) {
        echo "Missing or invalid product_id.";
        exit();
    }

    if (!isset($_FILES['product_images'])) {
        echo "No images uploaded!";
        exit();
    }

    $image_count = count($_FILES['product_images']['name']);

    // Filesystem dir (this file is in /admin)
    $upload_dir_fs = __DIR__ . '/../uploads/';   // disk
    $upload_dir_db = 'uploads/';                 // what we store in DB / serve in <img src>

    if (!is_dir($upload_dir_fs)) {
        @mkdir($upload_dir_fs, 0775, true);
    }

    for ($i = 0; $i < $image_count; $i++) {
        $image_name      = $_FILES['product_images']['name'][$i];
        $image_tmp_name  = $_FILES['product_images']['tmp_name'][$i];
        $image_error     = $_FILES['product_images']['error'][$i];

        if ($image_error !== UPLOAD_ERR_OK) {
            echo "Error uploading image: " . htmlspecialchars($image_name) . " (code: $image_error)<br>";
            continue;
        }

        // Allow-list extensions (optional)
        $ext = strtolower(pathinfo($image_name, PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg','jpeg','png','gif','webp'])) {
            echo "Skipped (unsupported type): " . htmlspecialchars($image_name) . "<br>";
            continue;
        }

        // Safe unique filename
        $base     = preg_replace('/[^A-Za-z0-9._-]/', '_', pathinfo($image_name, PATHINFO_FILENAME));
        $new_name = 'p' . $product_id . '_' . uniqid('', true) . '.' . $ext;

        $fs_path  = $upload_dir_fs . $new_name;           // disk
        $db_path  = $upload_dir_db . $new_name;           // DB / URL

        if (move_uploaded_file($image_tmp_name, $fs_path)) {
            $sql  = "INSERT INTO product_images (product_id, image_path) VALUES (?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('is', $product_id, $db_path);
            $stmt->execute();

            if ($stmt->affected_rows > 0) {
                echo "Image successfully uploaded: " . htmlspecialchars($new_name) . "<br>";
            } else {
                echo "Failed to save image to database: " . htmlspecialchars($new_name) . "<br>";
            }
            $stmt->close();
        } else {
            echo "Failed to move uploaded file: " . htmlspecialchars($image_name) . "<br>";
        }
    }
}
