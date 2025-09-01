<?php
require_once '../sessions.php';
include('../db.php');

// Must be admin
if (!isset($_SESSION['username']) || $_SESSION['user_type'] !== 'admin') {
    header('location: ../index.php');
    exit();
}

if (isset($_POST['delete_product']) && !empty($_POST['product_id'])) {
    $product_id = (int) $_POST['product_id'];

    /* ---- Move product images to /delete (archive) ---- */
    $sql_images = "SELECT image_path FROM product_images WHERE product_id = ?";
    $stmt_images = $conn->prepare($sql_images);
    $stmt_images->bind_param('i', $product_id);
    $stmt_images->execute();
    $result_images = $stmt_images->get_result();

    // Ensure root delete folder exists
    $delete_folder = __DIR__ . '/../delete/';
    if (!file_exists($delete_folder)) {
        @mkdir($delete_folder, 0775, true);
    }

    // Move each file from /uploads/... to /delete/...
    while ($row = $result_images->fetch_assoc()) {
        // DB stores e.g. "uploads/filename.jpg"
        $abs_path = __DIR__ . '/../' . ltrim($row['image_path'], '/'); // -> ../uploads/filename.jpg
        if (is_file($abs_path)) {
            $filename = basename($abs_path);
            $new_path = $delete_folder . $filename;

            // Prefer rename; fall back to copy+unlink if cross-device
            if (!@rename($abs_path, $new_path)) {
                @copy($abs_path, $new_path);
                @unlink($abs_path);
            }
        }
    }
    $stmt_images->close();

    /* ---- Delete DB rows in a safe order (transaction) ---- */
    $conn->begin_transaction();
    try {
        // Remove dependent rows that enforce FK to products
        // 1) statics_sales -> FK product_id -> products.ID
        $stmt = $conn->prepare("DELETE FROM statics_sales WHERE product_id = ?");
        $stmt->bind_param('i', $product_id);
        $stmt->execute();
        $stmt->close();

        // 2) sales table (if it references product_id)
        $stmt = $conn->prepare("DELETE FROM sales WHERE product_id = ?");
        $stmt->bind_param('i', $product_id);
        $stmt->execute();
        $stmt->close();

        // 3) product_images rows
        $stmt = $conn->prepare("DELETE FROM product_images WHERE product_id = ?");
        $stmt->bind_param('i', $product_id);
        $stmt->execute();
        $stmt->close();

        // 4) finally, delete the product itself
        $stmt_delete = $conn->prepare("DELETE FROM products WHERE id = ?");
        $stmt_delete->bind_param('i', $product_id);
        $stmt_delete->execute();
        $stmt_delete->close();

        $conn->commit();

        header('Location: ../products.php');
        exit();
    } catch (mysqli_sql_exception $e) {
        $conn->rollback();
        echo "Error deleting product: " . $e->getMessage();
    }
} else {
    // No POST / bad request -> go back
    header('Location: ../products.php');
    exit();
}
