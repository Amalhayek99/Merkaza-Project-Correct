<?php
require_once 'sessions.php';
include('db.php');
include('barcode.php');


// Get the product ID from the URL
$product_id = $_GET['id'] ?? null;

// Get the logged-in user ID (assuming it's stored in the session)
$user_id = $_SESSION['user_id'] ?? null;

if ($product_id) {
    $query = "
        SELECT 
            p.*, 
            s.price_after_discount AS sale_price 
        FROM 
            products p
        LEFT JOIN 
            sales s 
        ON 
            p.id = s.product_id 
            AND s.is_active = 1 
            AND NOW() BETWEEN s.date_start AND s.date_end
        WHERE 
            p.id = ?
    ";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('s', $product_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $product = $result->fetch_assoc();
    } else {
        echo "<p>Product not found.</p>";
        exit;
    }
} else {
    echo "<p>No product selected.</p>";
    exit;
}

if ($user_id && $product_id) {
    // Insert the search history into the database
    $stmt = $conn->prepare("INSERT INTO search_history (user_id, product_id) VALUES (?, ?)");
    $stmt->bind_param('ii', $user_id, $product_id);
    $stmt->execute();

    // Ensure only the last 10 searches are saved
    $stmt = $conn->prepare("DELETE FROM search_history WHERE user_id = ? AND id NOT IN (SELECT id FROM (SELECT id FROM search_history WHERE user_id = ? ORDER BY search_time DESC LIMIT 10) as t)");
    $stmt->bind_param('ii', $user_id, $user_id);
    $stmt->execute();
}

// Barcode generation
$barcode_code = $product['Code']; // Get the code from the database
$generator = new barcode_generator();
$options = array();

if (strlen($barcode_code) == 10) {
    $type = 'code-128';
} elseif (strlen($barcode_code) == 13) {
    $type = 'ean-13';
} else {
    echo "Invalid barcode length in the database.";
    exit;
}

// Generate the barcode image
ob_start(); // Start output buffering to capture the image
$generator->output_image('svg', $type, $barcode_code, $options);
$barcode_image = ob_get_clean(); // Store the output in a variable
?>

<!DOCTYPE html>
<html >
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Details</title>
    <link rel="icon" href="merkaza.jpeg" type="image/jpeg">
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="details.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script> 
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body style="background-color: #faf1f1ff"class="scrollable">
    <?php include('navbar.php'); ?>
    <div class="details-container surface-none add-shell">
        <h1><?php echo $product['Name']; ?></h1>

        <div class="product-image-slider">
            <?php
            // Fetch the product images from the 'product_images' table
            $product_id = $product['ID'];   
            $sql_images = "SELECT image_path FROM product_images WHERE product_id = ?";
            $stmt_images = $conn->prepare($sql_images);
            $stmt_images->bind_param('i', $product_id);
            $stmt_images->execute();
            $result_images = $stmt_images->get_result();
            $images = [];

            while ($image = $result_images->fetch_assoc()) {
                $images[] = $image['image_path'];
            }

            // If images exist, display them in a slider
            if (count($images) > 0): ?>
                <div class="image-slider">
                    <img src="<?php echo $images[0]; ?>" alt="<?php echo $product['Name']; ?>" class="slider-image">
                    <div>
                    <button class="prev" onclick="changeImage(this, -1)">&#10094;</button>
                    <button class="next" onclick="changeImage(this, 1)">&#10095;</button>
                    </div>

                </div>
            <?php else: ?>
                <img src="uploads/default.png" alt="Default Image">
            <?php endif; ?>
        </div>



<?php
$displayPrice = $product['sale_price'] !== null ? $product['sale_price'] : $product['Price'];
?>
<p>Price: <?= $displayPrice ?> ₪</p>

<?php if ($product['sale_price'] !== null): ?>
    <p style="color:red;">On Sale! (Original: <?= $product['Price'] ?> ₪)</p>
<?php endif; ?>




        <p><strong>Code:</strong> <?php echo $product['Code']; ?></p>
        <p><strong>Class:</strong> <?php echo $product['Class']; ?></p>
        <p><strong>Color:</strong> <?php echo $product['Color']; ?></p>
        <p><strong>Comment:</strong> <?php echo $product['comment']; ?></p>
        <p><strong>Quantity:</strong> <?php echo $product['quantity']; ?></p>


        <!-- Display the generated barcode image -->
        <h2>Barcode</h2>
        <div>
            <?php
            // Assuming $barcode_image contains the SVG code
            echo str_replace('<svg', '<svg style="width: 500px; height: auto;"', $barcode_image);
            ?>
        </div>


<?php if ($_SESSION['user_type'] === 'guest'): ?>
    <!-- Guest -->
    <button onclick="window.location.href='auth/index.php?msg=login_required'" style="margin-top: 10px;">
        Add to Cart
    </button>

<?php elseif ($_SESSION['user_type'] === 'customer'): ?>
    <!-- Logged-in customer -->
    <form method="POST" action="admin/add_to_cart.php" style="margin-top: 10px;">
        <input type="hidden" name="product_id" value="<?= $product['ID'] ?>">
        <input type="number" name="quantity" min="1" max="<?= $product['quantity'] ?>" value="1" style="width: 60px; padding: 5px;" required>
        <input type="hidden" name="redirect" value="<?= $_SERVER['REQUEST_URI'] ?>">
        <button type="submit">Add to cart</button>
    </form>
<?php endif; ?>


</div>

    <!-- Script for image slider -->
    <script>
        var images = <?php echo json_encode($images); ?>;
        var currentIndex = 0;

        // Change the displayed image
        function changeImage(button, direction) {
            currentIndex += direction;

            // Loop through the images
            if (currentIndex < 0) {
                currentIndex = images.length - 1;
            } else if (currentIndex >= images.length) {
                currentIndex = 0;
            }

            // Update the displayed image
            var sliderImage = document.querySelector('.slider-image');
            sliderImage.src = images[currentIndex];
        }

        // Touch event for swipe functionality
        var startX = 0;
        var endX = 0;

        var sliderImage = document.querySelector('.slider-image');
        sliderImage.addEventListener('touchstart', function(event) {
            startX = event.touches[0].clientX;
        });

        sliderImage.addEventListener('touchend', function(event) {
            endX = event.changedTouches[0].clientX;
            if (startX > endX + 50) {
                changeImage(null, 1);  // Swipe left to right
            } else if (startX < endX - 50) {
                changeImage(null, -1); // Swipe right to left
            }
        });
    </script>
</body>


</html>
<?php include 'footer/footer.php'; ?>