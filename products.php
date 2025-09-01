<?php
require_once 'sessions.php';
include('db.php');
$loginBg = 'cover/cover1/cover2.jpg';
require_once 'product_picker.php';

// Initialize an empty result set
$result = null;

// Handle search functionality
$whereClauses = [];
$searchParams = [];

// Only run the query if the search button was clicked
if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['search'])) {
    // Check if no search inputs are entered (including on_sale now)
    if (
        empty($_GET['name']) &&
        empty($_GET['min_price']) &&
        empty($_GET['max_price']) &&
        empty($_GET['code']) &&
        empty($_GET['class']) &&
        empty($_GET['color']) &&
        empty($_GET['comment']) &&
        (!isset($_GET['on_sale']) || $_GET['on_sale'] != '1')
    ) {
        // No search input provided, return empty result
        $result = null;
    } else {
        // Proceed with search if inputs are provided

        // Handle name search
        if (!empty($_GET['name'])) {
            $whereClauses[] = "Name LIKE ?";
            $searchParams[] = '%' . $_GET['name'] . '%';
        }

        // Handle price range search
        $min_price = isset($_GET['min_price']) && $_GET['min_price'] !== '' ? $_GET['min_price'] : null;
        $max_price = isset($_GET['max_price']) && $_GET['max_price'] !== '' ? $_GET['max_price'] : null;

        if ($min_price !== null && $max_price !== null && $min_price > $max_price) {
            list($min_price, $max_price) = [$max_price, $min_price];
        }

        if ($min_price !== null && $max_price !== null) {
            $whereClauses[] = "Price BETWEEN ? AND ?";
            $searchParams[] = $min_price;
            $searchParams[] = $max_price;
        } elseif ($min_price !== null) {
            $whereClauses[] = "Price = ?";
            $searchParams[] = $min_price;
        } elseif ($max_price !== null) {
            $whereClauses[] = "Price = ?";
            $searchParams[] = $max_price;
        }

        // Handle code search
        if (isset($_GET['code'])) {
            $whereClauses[] = "Code LIKE ?";
            $searchParams[] = '%' . $_GET['code'] . '%';
        }

        // Handle class search
        if (!empty($_GET['class'])) {
            $whereClauses[] = "Class = ?";
            $searchParams[] = $_GET['class'];
        }

        // Handle color search
        if (!empty($_GET['color'])) {
            $whereClauses[] = "Color = ?";
            $searchParams[] = $_GET['color'];
        }

        // Handle comment search
        if (!empty($_GET['comment'])) {
            $commentSearch = trim($_GET['comment']);
            $commentSearch = preg_replace('/\s+/', ' ', $commentSearch);
            $whereClauses[] = "REPLACE(comment, '\n', '') LIKE ?";
            $searchParams[] = '%' . $commentSearch . '%';
        }

        // Handle "Active Sale" checkbox
        if (isset($_GET['on_sale']) && $_GET['on_sale'] == '1') {
            $whereClauses[] = "s.id IS NOT NULL";
        }

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
            WHERE 1=1
        ";

        if (!empty($whereClauses)) {
            $query .= " AND " . implode(' AND ', $whereClauses);
        }

        $query .= " ORDER BY Price DESC";

        $stmt = $conn->prepare($query);
        if (!empty($searchParams)) {
            $types = str_repeat('s', count($searchParams));
            $stmt->bind_param($types, ...$searchParams);
        }
        $stmt->execute();
        $result = $stmt->get_result();
    }
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Products</title>
    <link rel="stylesheet" type="text/css" href="styles.css">
    <link rel="icon" href="merkaza.jpeg" type="image/jpeg">
    <link rel="stylesheet" href="products.css">


   <!-- Page-only layout to keep cards small even with 1–2 results -->
    
    <!-- <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script> -->

 
   
</head>
<body 
    class="scrollable with-cover " 
    style="--cover-image: url('<?= htmlspecialchars($loginBg) ?>');">

    <div class="wrapper d-flex flex-column min-vh-100">

    <?php include('navbar.php'); ?>
    <?php include 'header.php'; ?>

<div class=" flex-grow-1">

    <?php if (!empty($success_msg)): ?>
      <div style="text-align:center; color:green; font-weight:bold;"><?= $success_msg ?></div>
    <?php endif; ?>

<div class="container ">
        <h2>Products</h2>

        <!-- Search Form -->
        <form method="GET" action="products.php">
            <?php
            render_product_picker($conn, 'product_id', null, '', 'name');
            ?>
            <div class="price-range">
                <input type="text" name="min_price" placeholder="Min Price" value="<?php echo isset($_GET['min_price']) ? $_GET['min_price'] : ''; ?>">
                <input type="text" name="max_price" placeholder="Max Price" value="<?php echo isset($_GET['max_price']) ? $_GET['max_price'] : ''; ?>">
            </div>
            <input type="text" name="code" placeholder="Search by code" value="<?php echo isset($_GET['code']) ? $_GET['code'] : ''; ?>">

            <div class="form-group">
                <label for="class">Product Class:</label>
                <input name="class" id="class" list="class-list" placeholder="Type to search class" autocomplete="off">
                <datalist id="class-list">
                    <option value="meat">
                    <option value="m2fea">
                    <option value="shtea 7arefa">
                    <option value="shtea">
                    <option value="kle bait">
                </datalist>
            </div>

            <div class="form-group">
                <!-- Color search dropdown -->
                <label for="color">Search by Color:</label>
                <input id="color" name="color" onchange="updateColor(this)" list="color-list" placeholder="Type to search color" autocomplete="off">
                <datalist id="color-list">
                    <option value="red">
                    <option value="blue">
                    <option value="green">
                </datalist>
            </div>

            <script>
            function updateColor(selectElement) {
                selectElement.style.backgroundColor = selectElement.value;
            }
            </script>

            <label for="comment">Search by Keyword:</label>
            <textarea id="comment" name="comment" placeholder="Enter comment..."></textarea>

            <div class="form-group">
              <input type="checkbox" name="on_sale" value="1" <?= isset($_GET['on_sale']) && $_GET['on_sale'] == '1' ? 'checked' : '' ?>>
              <label for="on_sale">Active sale</label>
            </div>

            <button type="submit" name="search">Search</button>
        </form>




        
        <!-- Only display products if a search has been made and results are available -->
        <?php if ($result && $result->num_rows > 0): ?>
            <!-- 🔧 grid container -->
            <div class="products products-grid">
                <?php while ($row = $result->fetch_assoc()): ?>
                    <!-- 🔧 card element -->
                    <div class="product product-card">
                        <!-- Fetch and display all images for the product -->
                        <?php
                        $product_id = $row['ID'];
                        $sql_images = "SELECT image_path FROM product_images WHERE product_id = ? ORDER BY id DESC";
                        $stmt_images = $conn->prepare($sql_images);
                        $stmt_images->bind_param('i', $product_id);
                        $stmt_images->execute();
                        $result_images = $stmt_images->get_result();
                        $images = [];
                        while ($img = $result_images->fetch_assoc()) {
                            $images[] = $img['image_path'];
                        }
                        ?>

                        <!-- Image Slider -->
                        <?php if (count($images) > 0): ?>
                            <div class="image-slider">
                                <?php foreach ($images as $index => $image): ?>
                                    <img src="<?php echo htmlspecialchars($image); ?>" alt="<?php echo htmlspecialchars($row['Name']); ?>" class="slider-image" style="display: <?php echo $index === 0 ? 'block' : 'none'; ?>;">
                                <?php endforeach; ?>
                                <button class="prev" onclick="changeImage(this, -1)">&#10094;</button>
                                <button class="next" onclick="changeImage(this, 1)">&#10095;</button>
                            </div>
                        <?php else: ?>
                            <!-- Default image wrapped in slider box so size is consistent -->
                            <div class="image-slider">
                                <img src="uploads/default.png" alt="Default Image" class="slider-image" style="display:block;">
                            </div>
                        <?php endif; ?>

                        <!-- Display product details -->
                        <h3>
                          <a href="details.php?id=<?php echo (int)$row['ID']; ?>" style="text-decoration: none; color: inherit;">
                              <?php echo htmlspecialchars($row['Name']); ?>
                          </a>
                        </h3>
                        <p>Class: <?php echo htmlspecialchars($row['Class']); ?></p>

                        <?php
                          $displayPrice = $row['sale_price'] !== null ? $row['sale_price'] : $row['Price'];
                        ?>
                        <p>Price: <?= htmlspecialchars($displayPrice) ?> ₪</p>

                        <?php if ($row['sale_price'] !== null): ?>
                            <p style="color: red;">On Sale! (Original: <?= htmlspecialchars($row['Price']) ?> ₪)</p>
                        <?php endif; ?>

                        <p>Code: <?php echo htmlspecialchars($row['Code']); ?></p>
                        <p>Quantity: <?php echo htmlspecialchars($row['quantity']); ?></p>
                        <?php if (!empty($row['Color'])): ?>
                            <p>Color: <?= htmlspecialchars($row['Color']) ?></p>
                        <?php endif; ?>

                        <?php if (!empty($row['comment'])): ?>
                            <p>Comment: <?= htmlspecialchars($row['comment']) ?></p>
                        <?php endif; ?>

                        <!-- Only display edit and delete buttons if the user is an admin -->
                        <?php if (isset($_SESSION['user_type']) && $_SESSION['user_type'] == 'admin'): ?>
                            <form method="POST" action="admin/edit.php" style="display:inline;">
                                <input type="hidden" name="product_id" value="<?php echo (int)$row['ID']; ?>">
                                <button type="submit" name="edit_product">Edit</button>
                            </form>

                            <form method="POST" action="admin/delete.php" style="display:inline;">
                                <input type="hidden" name="product_id" value="<?php echo (int)$row['ID']; ?>">
                                <button type="submit" name="delete_product" onclick="return confirm('Are you sure you want to delete this product?');">Delete</button>
                            </form>
                        <?php endif; ?>

                        <?php if ($_SESSION['user_type'] === 'customer'): ?>
                            <!-- Logged-in customer -->
                            <form method="POST" action="admin/add_to_cart.php" style="margin-top: 10px;">
                                <input type="hidden" name="product_id" value="<?= (int)$row['ID'] ?>">
                                <input type="number" name="quantity" min="1" max="<?= (int)$row['quantity'] ?>" value="1" style="width: 60px; padding: 5px;" required>
                                <button type="submit" name="add_to_cart">Add to Cart</button>
                            </form>

                            <?php if (!empty($cart_error_msg) && $cart_error_product_id == $row['ID']): ?>
                                <div style="color: red; font-size: 13px; margin-top: 5px;">
                                    <?= htmlspecialchars($cart_error_msg) ?>
                                </div>
                            <?php endif; ?>

                        <?php elseif ($_SESSION['user_type'] === 'guest'): ?>
                            <!-- Guest (not logged in) -->
                            <button onclick="window.location.href='auth/index.php?msg=login_required'" style="margin-top: 10px;">
                                Add to Cart
                            </button>
                        <?php endif; ?>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php elseif (isset($_GET['search'])): ?>
            <p>No products found based on your search criteria.</p>
        <?php endif; ?>

        <!-- Modal for enlarged image -->
        <div id="myModal" class="modal">
            <span class="close">&times;</span>
            <img class="modal-content" id="modal-image">
        </div>

    </div></div>
    <?php include 'footer/footer.php'; ?>

    </div>

    <!-- Script for image slider and modal -->
    <script>
    // Image Slider
    function changeImage(button, direction) {
        var slider = button.parentElement;
        var images = slider.querySelectorAll('.slider-image');
        var currentImage = slider.querySelector('.slider-image:not([style*="display: none"])');
        var currentIndex = Array.prototype.indexOf.call(images, currentImage);
        var newIndex = currentIndex + direction;

        if (newIndex < 0) newIndex = images.length - 1;
        if (newIndex >= images.length) newIndex = 0;

        images.forEach(function(img) { img.style.display = 'none'; });
        images[newIndex].style.display = 'block';
    }

    // Image Zoom Modal
    var modal = document.getElementById('myModal');
    var modalImg = document.getElementById("modal-image");
    var span = document.getElementsByClassName("close")[0];

    var images = document.querySelectorAll('.slider-image');
    images.forEach(function(image) {
        image.addEventListener('click', function() {
            modal.style.display = "block";
            modalImg.src = this.src;
        });
    });

    span.onclick = function() { modal.style.display = "none"; }
    modal.addEventListener('click', function(event) {
        if (event.target === modal) modal.style.display = "none";
    });

    // Touch swipe
    var startX = 0, endX = 0;
    images.forEach(function(image) {
        image.addEventListener('touchstart', function(event) {
            startX = event.touches[0].clientX;
        });
        image.addEventListener('touchend', function(event) {
            endX = event.changedTouches[0].clientX;
            if (startX > endX + 50) changeImage(this, 1);
            else if (startX < endX - 50) changeImage(this, -1);
        });
    });
    </script>
</body>
</html>
