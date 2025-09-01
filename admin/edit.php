<?php
require_once '../sessions.php';
include('../db.php');
require_role([ 'admin']);
if (!isset($_SESSION['username']) || $_SESSION['user_type'] != 'admin') {
    header('location: ../index.php');
    exit();
}

$error   = '';
$success = '';
$product = null;

/* ---------------------------
   Resolve product_id
---------------------------- */
if (isset($_POST['edit_product']) && !empty($_POST['product_id'])) {
    $product_id = (int)$_POST['product_id'];
} elseif (isset($_GET['id'])) {
    $product_id = (int)$_GET['id'];
} else {
    $product_id = 0;
}

/* ---------------------------
   Fetch product to edit
---------------------------- */
if ($product_id > 0) {
    // Use the same column name casing you use elsewhere: ID
    $stmt = $conn->prepare("SELECT * FROM products WHERE ID = ?");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $result  = $stmt->get_result();
    $product = $result->fetch_assoc();
    $stmt->close();
}

/* ---------------------------
   Handle product update
---------------------------- */
if (isset($_POST['update_product'])) {
    $product_id = (int)$_POST['product_id'];

    $name    = trim($_POST['name'] ?? '');
    $price   = (float)($_POST['price'] ?? 0);
    $code    = trim($_POST['code'] ?? '');
    $class   = trim($_POST['class'] ?? '');
    $color   = trim($_POST['color'] ?? '');
    $comment = trim($_POST['comment'] ?? '');

    // 1) Validate required
    if ($name === '' || $price <= 0 || $code === '' || $class === '') {
        $error = 'Please fill all required fields.';
    }

    // 2) Check duplicate Code (Barcode) for OTHER products
    if ($error === '') {
        $dup = $conn->prepare("SELECT 1 FROM products WHERE Code = ? AND ID <> ?");
        $dup->bind_param("si", $code, $product_id);
        $dup->execute();
        $dup->store_result();
        if ($dup->num_rows > 0) {
            $error = 'This Code/Barcode is already used by another product.';
        }
        $dup->close();
    }

    // 3) If OK, run the UPDATE
    if ($error === '') {
        $q = "UPDATE products 
              SET Name = ?, Price = ?, Code = ?, Class = ?, Color = ?, comment = ?
              WHERE ID = ?";
        $stmt = $conn->prepare($q);
        $stmt->bind_param("sdssssi", $name, $price, $code, $class, $color, $comment, $product_id);
        $stmt->execute();
        $stmt->close();

        // Replace images if new files uploaded
        if (!empty($_FILES['product_images']['name'][0])) {
            $targetDir = __DIR__ . '/../uploads/';
            if (!is_dir($targetDir)) { mkdir($targetDir, 0755, true); }

            // delete old files + rows
            $oldRes = $conn->prepare("SELECT image_path FROM product_images WHERE product_id = ?");
            $oldRes->bind_param('i', $product_id);
            $oldRes->execute();
            $oldFiles = $oldRes->get_result();
            while ($row = $oldFiles->fetch_assoc()) {
                $oldPath = __DIR__ . '/../' . $row['image_path']; // e.g. ../uploads/xyz.jpg
                if (is_file($oldPath)) { @unlink($oldPath); }
            }
            $oldRes->close();
            $conn->query("DELETE FROM product_images WHERE product_id = " . (int)$product_id);

            // insert new files
            $ins = $conn->prepare("INSERT INTO product_images (product_id, image_path, uploaded_at) VALUES (?, ?, NOW())");

            foreach ($_FILES['product_images']['name'] as $i => $origName) {
                if (empty($origName)) continue;
                if (!is_uploaded_file($_FILES['product_images']['tmp_name'][$i])) continue;

                $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
                if (!in_array($ext, ['jpg','jpeg','png','gif','webp'])) continue;

                $safeBase   = preg_replace('/[^A-Za-z0-9._-]/', '_', pathinfo($origName, PATHINFO_FILENAME));
                $newFileName = 'p' . $product_id . '_' . time() . "_$i." . $ext;
                $fsPath = $targetDir . $newFileName;

                if (move_uploaded_file($_FILES['product_images']['tmp_name'][$i], $fsPath)) {
                    $dbPath = 'uploads/' . $newFileName; // stored in DB; URL will be ../uploads/...
                    $ins->bind_param('is', $product_id, $dbPath);
                    $ins->execute();
                }
            }
            $ins->close();
        }

        // success → back to products
        header('Location: ../products.php');
        exit();
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Edit Product</title>
    <link rel="icon" href="../merkaza.jpeg" type="image/jpeg">
    <link rel="stylesheet" type="text/css" href="admin.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</head>
<body class="scrollable" style="background: #faf1f1ff">
<?php include('../navbar.php'); ?>

<div class="container py-4">
    <h2 class="mb-4">Edit Product</h2>

    <?php if (!$product): ?>
        <div class="alert alert-danger">Product not found.</div>
    <?php else: ?>
        <?php
        // Load images (newest first)
        $imgs = [];
        $si = $conn->prepare("SELECT image_path FROM product_images WHERE product_id = ? ORDER BY id DESC");
        $si->bind_param('i', $product['ID']);
        $si->execute();
        $ri = $si->get_result();
        while ($row = $ri->fetch_assoc()) { $imgs[] = $row['image_path']; }
        $si->close();
        ?>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="edit.php" enctype="multipart/form-data" class="card p-3 shadow-sm">
            <input type="hidden" name="product_id" value="<?= (int)$product['ID']; ?>">

            <div class="row g-3">
                <div class="col-md-6">
                    <label for="name" class="form-label">Product Name</label>
                    <input class="form-control" type="text" id="name" name="name" value="<?= htmlspecialchars($product['Name']); ?>" required>
                </div>

                <div class="col-md-3">
                    <label for="price" class="form-label">Price</label>
                    <input class="form-control" type="number" step="0.01" id="price" name="price" value="<?= htmlspecialchars($product['Price']); ?>" required>
                </div>

                <div class="col-md-3">
                    <label for="code" class="form-label">Code</label>
                    <input class="form-control" type="text" id="code" name="code" value="<?= htmlspecialchars($product['Code']); ?>" required>
                </div>

                <div class="col-md-4">
                    <label for="class" class="form-label">Class</label>
                    <select class="form-select" id="class" name="class" required>
                        <option value="" disabled>Select a class</option>
                        <?php
                        $classes = ['kle bait','shtea','shtea 7arefa','m2fea','meat'];
                        foreach ($classes as $c) {
                            $sel = ($product['Class'] === $c) ? 'selected' : '';
                            echo "<option value=\"".htmlspecialchars($c)."\" $sel>".htmlspecialchars($c)."</option>";
                        }
                        ?>
                    </select>
                </div>

                <div class="col-md-4">
                    <label for="color" class="form-label">Color</label>
                    <select class="form-select" id="color" name="color">
                        <?php
                        $colors = ['red','blue','green'];
                        foreach ($colors as $c) {
                            $sel = ($product['Color'] === $c) ? 'selected' : '';
                            echo "<option value=\"$c\" $sel>".ucfirst($c)."</option>";
                        }
                        ?>
                    </select>
                </div>

                <div class="col-md-12">
                    <label for="comment" class="form-label">Comment</label>
                    <textarea class="form-control" id="comment" name="comment" rows="3"><?= htmlspecialchars($product['comment'] ?? '') ?></textarea>
                </div>

                <div class="col-md-12">
                    <label class="form-label">Product Images (uploading will replace old images)</label>
                    <input class="form-control" type="file" name="product_images[]" id="product_images" accept="image/*" multiple>
                    <div class="form-text">You can select multiple images; old images will be removed.</div>
                </div>

                <?php if (!empty($imgs)): ?>
                <div class="col-md-12">
                    <label class="form-label mt-3">Current Images (newest first)</label>
                    <div class="d-flex flex-wrap gap-3">
                        <?php foreach ($imgs as $img): ?>
                            <img src="../<?= htmlspecialchars($img) ?>" alt="Current Image" style="max-width:220px; border-radius:8px;">
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <div class="col-12 mt-3">
                    <button class="btn px-4" type="submit" name="update_product">Update Product</button>
                    <a class="btn btn-outline-secondary ms-2" href="../products.php">Back</a>
                </div>
            </div>
        </form>
    <?php endif; ?>
</div>

<?php include '../footer/footer.php'; ?>
</body>
</html>
