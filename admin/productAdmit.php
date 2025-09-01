<?php
require_once '../sessions.php';
include('../db.php');
require_role(['admin']);
$loginBg = 'cover/cover44.jpg';

if (!isset($_SESSION['username']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: index.php");
    exit();
}

// Approve a product
if (isset($_GET['approve'])) {
    $id = $_GET['approve'];
    $stmt = $conn->prepare("SELECT * FROM product_check WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $product = $result->fetch_assoc();
    $stmt->close();
    $net_price = $price;


    if ($product) {
        $insert = $conn->prepare("INSERT INTO products (Name, Price,net_price, Code, Class, Color, Comment, added_by_user_id, date_added,quantity) VALUES (?,?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $insert->bind_param("sddssssisi",
            $product['name'],
            $product['price'],
            $product['price'],
            $product['code'],
            $product['class'],
            $product['color'],
            $product['comment'],
            $product['added_by'],
            $product['created_at'],
            $product['quantity']

        );
        $insert->execute();
        $new_id = $insert->insert_id;
        $insert->close();

        if (!empty($product['image_path'])) {
            $img = $conn->prepare("INSERT INTO product_images (product_id, image_path) VALUES (?, ?)");
            $img->bind_param("is", $new_id, $product['image_path']);
            $img->execute();
            $img->close();
        }

        $delete = $conn->prepare("DELETE FROM product_check WHERE id = ?");
        $delete->bind_param("i", $id);
        $delete->execute();
        $delete->close();
    }

    header("Location: productAdmit.php");
    exit();
}

// Reject a product
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $getImage = $conn->prepare("SELECT image_path FROM product_check WHERE id = ?");
    $getImage->bind_param("i", $id);
    $getImage->execute();
    $res = $getImage->get_result();
    $imgRow = $res->fetch_assoc();
    if ($imgRow && file_exists($imgRow['image_path'])) {
        unlink($imgRow['image_path']);
    }
    $getImage->close();

    $del = $conn->prepare("DELETE FROM product_check WHERE id = ?");
    $del->bind_param("i", $id);
    $del->execute();
    $del->close();

    header("Location: productAdmit.php");
    exit();
}
$products = $conn->query("
    SELECT pc.*, u.username 
    FROM product_check pc
    JOIN users u ON pc.added_by = u.id
    ORDER BY pc.created_at DESC
");

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Product Approval</title>
    <link rel="stylesheet" href="admin.css">
    <link rel="stylesheet" href="productAdmit.css">

    <link rel="icon" href="/Merkaza-Almost-Done/public_html/merkaza.jpeg" type="image/jpeg">


</head>
<body class="with-cover  "
      style="--cover-image: url('<?= htmlspecialchars($loginBg) ?>');">
      <div class="wrapper d-flex flex-column min-vh-100">


<?php include '../header.php'; ?>
<?php include('../navbar.php'); ?>

<div class=" flex-grow-1">

<div class="container surface-none">

    <h2>🛠️ Pending Product Approval</h2>

    <input type="text" id="searchInput" placeholder="Search by name or barcode..." class="search-bar">

    <?php if ($products->num_rows > 0): ?>
        <div class="grid">
            <?php while ($row = $products->fetch_assoc()): ?>
<div class="card" data-name="<?= strtolower($row['name']) ?>" data-code="<?= strtolower($row['code']) ?>">
                    <?php
                    $image_path = (!empty($row['image_path']) && file_exists(__DIR__ . '/../' . $row['image_path']))
                        ? '../' . $row['image_path']
                        : '../uploads/default.png';
                    ?>
                    <img src="<?= $image_path ?>" alt="Product Image" onclick="openModal(this.src)">
                    <h3><?= htmlspecialchars($row['name']) ?></h3>
                    <p>Price: ₪<?= $row['price'] ?></p>
                    <p>Code: <?= $row['code'] ?></p>
                    <p>Class: <?= $row['class'] ?></p>
                    <p>Color: <?= $row['color'] ?></p>
                    <p>Comment: <?= $row['comment'] ?></p>
                    <p>Qauntity: <?= $row['quantity'] ?></p>
                    <small class="adder-chip">Added by: <?= htmlspecialchars($row['username']) ?></small>
                    
                    <div class="actions">
                        <a href="?approve=<?= $row['id'] ?>" class="btn approve">Approve</a>
                        <a href="?delete=<?= $row['id'] ?>" class="btn delete" onclick="return confirm('Delete this product?');">Delete</a>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    <?php else: ?>
        <p class="no-items">No products waiting for approval.</p>
    <?php endif; ?>
</div>

<!-- Modal HTML -->
<div id="imageModal" class="modal" onclick="closeModal()">
    <span class="close" onclick="closeModal()">&times;</span>
    <img class="modal-content" id="modalImage">
</div>
</div>
<?php include '../footer/footer.php'; ?>
<!-- JS for Image Modal -->
<script>
    function openModal(src) {
        const modal = document.getElementById("imageModal");
        const modalImg = document.getElementById("modalImage");
        modalImg.src = src;
        modal.style.display = "block";
    }

    function closeModal() {
        const modal = document.getElementById("imageModal");
        modal.style.display = "none";
    }

    // Close on outside click
    document.getElementById("imageModal").addEventListener("click", function (e) {
        if (e.target === this) {
            closeModal();
        }
    });
</script>

<script>
document.getElementById("searchInput").addEventListener("keyup", function () {
    const filter = this.value.toLowerCase();
    const cards = document.querySelectorAll(".card");

    cards.forEach(card => {
        const name = card.getAttribute("data-name");
        const code = card.getAttribute("data-code");
        if (name.includes(filter) || code.includes(filter)) {
            card.style.display = "";
        } else {
            card.style.display = "none";
        }
    });
});
</script>

<!--   /-insert field   +    esc remove the search   -->
<script>
document.addEventListener('keydown', e=>{
  const input=document.getElementById('searchInput');
  if(e.key==='/' && document.activeElement!==input){ e.preventDefault(); input.focus(); }
  if(e.key==='Escape' && document.activeElement===input){ input.value=''; input.dispatchEvent(new Event('keyup')); }
});
</script>

</body>
</html>

