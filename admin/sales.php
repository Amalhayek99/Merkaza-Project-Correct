<?php
require_once '../sessions.php';
include('../db.php');
require_role(['admin']);
require_once '../product_picker.php';
// include('../auth/darkmode.php');
$loginBg = 'cover/cover41.jpg';

if (!isset($_SESSION['username']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: index.php");
    exit();
}

// Variables for edit mode
$edit_mode = false;
$sale_to_edit = null;

// Handle edit button click
if (isset($_POST['edit'])) {
    $edit_id = $_POST['edit_id'];
    $edit_mode = true;

    $stmt = $conn->prepare("SELECT * FROM sales WHERE ID = ?");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $sale_to_edit = $result->fetch_assoc();
    $stmt->close();
}

// Handle delete sale (prepared for safety)
if (isset($_POST['delete'])) {
    $delete_id = $_POST['delete_id'];
    $stmt = $conn->prepare("DELETE FROM sales WHERE ID = ?");
    $stmt->bind_param("i", $delete_id);
    $stmt->execute();
    $stmt->close();
    echo "<script>alert('Sale deleted successfully!'); window.location.href='sales.php';</script>";
    exit;
}

// Handle add new sale or update existing sale
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['product_id'])) {
    $product_id      = (int)$_POST['product_id'];
    $date_start      = $_POST['date_start'];
    $date_end        = $_POST['date_end'];
    $discount_value  = (float)$_POST['discount_value'];
    $conditioon      = $_POST['conditioon'];

    // Get original price
    $getPrice = $conn->prepare("SELECT Price FROM products WHERE id = ?");
    $getPrice->bind_param("i", $product_id);
    $getPrice->execute();
    $result = $getPrice->get_result();
    $product = $result->fetch_assoc();
    $getPrice->close();

    $original_price = (float)$product['Price'];
    $price_after_discount = $original_price * (1 - ($discount_value / 100));

    // Update
    if (!empty($_POST['update_id'])) {
        $update_id = (int)$_POST['update_id'];
        $update_sql = "UPDATE sales
                       SET product_id = ?, date_start = ?, date_end = ?, discount_value = ?, conditioon = ?, price_after_discount = ?
                       WHERE ID = ?";
        $stmt = $conn->prepare($update_sql);
        // i ss d s d i
        $stmt->bind_param("issdsdi", $product_id, $date_start, $date_end, $discount_value, $conditioon, $price_after_discount, $update_id);
        $stmt->execute();
        $stmt->close();

        echo "<script>alert('Sale updated successfully!'); window.location.href='sales.php';</script>";
        exit;
    }
    // Insert
    else {
        $sql = "INSERT INTO sales (product_id, date_start, date_end, discount_value, conditioon, price_after_discount)
                VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        // i ss d s d
        $stmt->bind_param("issdsd", $product_id, $date_start, $date_end, $discount_value, $conditioon, $price_after_discount);
        $stmt->execute();
        $stmt->close();

        echo "<script>alert('Sale added successfully!'); window.location.href='sales.php';</script>";
        exit;
    }
}

// Fetch products
$products = $conn->query("SELECT id, Name FROM products");

// === Fetch existing sales with desired ordering ===
// Active first (today between start/end), sorted by soonest end.
// Then ended ones, sorted by most recently ended.
// Then upcoming (future), sorted by soonest start.
$today = date('Y-m-d');

$sql = "
SELECT s.*, p.Name
FROM sales s
JOIN products p ON s.product_id = p.id
ORDER BY
  CASE
    WHEN s.date_start <= ? AND s.date_end >= ? THEN 0      -- active
    WHEN s.date_end < ? THEN 1                              -- ended
    ELSE 2                                                  -- upcoming
  END,
  CASE WHEN s.date_start <= ? AND s.date_end >= ? THEN s.date_end END ASC,
  CASE WHEN s.date_end < ? THEN s.date_end END DESC,
  CASE WHEN s.date_start > ? THEN s.date_start END ASC,
  s.ID DESC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param('sssssss', $today, $today, $today, $today, $today, $today, $today);
$stmt->execute();
$sales = $stmt->get_result();
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Sales</title>

    <!-- Global site styles (needed for footer + layout consistency) -->
    <link rel="stylesheet" href="admin.css">
    <link rel="stylesheet" href="sales.css">


  <link rel="icon" href="/Merkaza-Almost-Done/public_html/merkaza.jpeg" type="image/jpeg">

</head>
<body class="with-cover <?= $darkModeEnabled ? 'dark-mode' : '' ?>"
      style="--cover-image: url('<?= htmlspecialchars($loginBg) ?>');">
      
<div class="wrapper d-flex flex-column min-vh-100">

    <?php include('../header.php'); ?>
    <?php include('../navbar.php'); ?>

    <!-- Main content grows to push footer down -->
    <main class="flex-grow-1 page-container">

        <h1>Manage Sales</h1>

        <!-- Add or Edit Sale Form -->
        <form method="POST">
            <h2><?= $edit_mode ? 'Edit Sale' : 'Add New Sale' ?></h2>

            <?php if ($edit_mode): ?>
                <input type="hidden" name="update_id" value="<?= $sale_to_edit['ID']; ?>">
            <?php endif; ?>

<?php
// One field with dropdown + live filter, submits product_id via hidden input
render_product_picker(
    $conn,
    'product_id',
    $edit_mode ? (int)$sale_to_edit['product_id'] : null,
    'Product'
);
?>


            <label>Start Date</label>
            <input type="date" name="date_start" value="<?= $edit_mode ? $sale_to_edit['date_start'] : '' ?>" required>

            <label>End Date</label>
            <input type="date" name="date_end" value="<?= $edit_mode ? $sale_to_edit['date_end'] : '' ?>" required>

            <label>Discount Value (%)</label>
            <input type="number" name="discount_value" step="0.01" value="<?= $edit_mode ? $sale_to_edit['discount_value'] : '' ?>" required>

            <label>Condition (optional)</label>
            <textarea name="conditioon" rows="4"><?= $edit_mode ? htmlspecialchars($sale_to_edit['conditioon']) : '' ?></textarea>

            <button type="submit" name="<?= $edit_mode ? 'update' : 'add' ?>">
                <?= $edit_mode ? 'Update Sale' : 'Add Sale' ?>
            </button>

            <?php if ($edit_mode): ?>
                <button type="submit" name="cancel">Cancel</button>
            <?php endif; ?>
        </form>

        <!-- Show Existing Sales -->
        <div class="sales-table-wrap surface-none add-shell">
            <table class="sales-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Product</th>
                        <th>Start Date</th>
                        <th>End Date</th>
                        <th>Discount</th>
                        <th>Condition</th>
                        <th>Price After Discount</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($sales->num_rows > 0): ?>
                        <?php while($sale = $sales->fetch_assoc()): ?>
                            <tr>
                                <td><?= $sale['ID']; ?></td>
                                <td><?= htmlspecialchars($sale['Name']); ?></td>
                                <td><?= $sale['date_start']; ?></td>
                                <td><?= $sale['date_end']; ?></td>
                                <td><?= $sale['discount_value']; ?>%</td>
                                <td class="conditioon-cell"><?= htmlspecialchars($sale['conditioon']); ?></td>
                                <td><?= number_format($sale['price_after_discount'], 2); ?> ₪</td>
                                <td class="action-buttons">
                                    <form method="POST">
                                        <input type="hidden" name="edit_id" value="<?= $sale['ID']; ?>">
                                        <button type="submit" name="edit" class="btn-edit">Edit</button>
                                    </form>
                                    <form method="POST" onsubmit="return confirm('Are you sure you want to delete it?!');">
                                        <input type="hidden" name="delete_id" value="<?= $sale['ID']; ?>">
                                        <button type="submit" name="delete" class="btn-delete">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="8">No sales yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </main>

    <?php include '../footer/footer.php'; ?>
</div>
</body>
</html>


