<?php
require_once '../sessions.php';  
require_role(['owner']);
require_once '../db.php';
$loginBg = 'cover/owner/cover26.jpg';

// ✅ Auto-process pending orders where auto_order_time has passed
date_default_timezone_set('Asia/Jerusalem');
$now = date('Y-m-d H:i:s');

$autoOrders = $conn->query("
    SELECT * FROM pending_orders
    WHERE auto_order_time <= '$now' AND processed = 0
");

while ($row = $autoOrders->fetch_assoc()) {
    $product_id = $row['product_id'];
    $order_id = $row['id'];
    $order_date = $row['order_date'];
    $suggested_order = $row['suggested_order'];
    $current_qty = $row['current_qty'];

    $after_quantity = $current_qty + $suggested_order;

    // 1. Insert into ordered
    $insert = $conn->prepare("INSERT INTO ordered (product_id, order_date, ordered_quantity, before_quantity, after_quantity) VALUES (?, ?, ?, ?, ?)");
    $insert->bind_param("isiii", $product_id, $order_date, $suggested_order, $current_qty, $after_quantity);
    $insert->execute();
    $insert->close();

    // 2. Update product quantity
    $update = $conn->prepare("UPDATE products SET quantity = quantity + ? WHERE ID = ?");
    $update->bind_param("ii", $suggested_order, $product_id);
    $update->execute();
    $update->close();

    // 3. Delete from pending_orders
    $delete = $conn->prepare("DELETE FROM pending_orders WHERE id = ?");
    $delete->bind_param("i", $order_id);
    $delete->execute();
    $delete->close();

    echo "<!-- ✅ Auto-approved order for product ID: $product_id -->";
}

// 📅 Define current date for filter
$currentDate = date('Y-m-d');

// 🗑 Handle Delete
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM pending_orders WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    header("Location: orders.php");
    exit();
}

// ✏️ Handle Edit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_id'])) {
    $editId = intval($_POST['edit_id']);
    $newQty = intval($_POST['new_qty']);

    $stmt = $conn->prepare("UPDATE pending_orders SET suggested_order = ? WHERE id = ?");
    $stmt->bind_param("ii", $newQty, $editId);
    $stmt->execute();
    $stmt->close();
    header("Location: orders.php");
    exit();
}

// 📦 Fetch Pending Orders (< 1 day old, not processed)
$query = "
    SELECT po.*, p.Name
    FROM pending_orders po
    JOIN products p ON po.product_id = p.ID
    WHERE po.processed = 0 AND DATEDIFF(?, po.order_date) < 1
    ORDER BY po.order_date DESC
";

$stmt = $conn->prepare($query);
$stmt->bind_param("s", $currentDate);
$stmt->execute();
$results = $stmt->get_result();
?>





<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Pending Restock Orders</title>
    <meta http-equiv="refresh" content="10">
<link rel="stylesheet" href="owner.css">
<link rel="stylesheet" href="orders.css">
<link rel="icon" href="/Merkaza-Almost-Done/public_html/merkaza.jpeg" type="image/jpeg">

</head>
<body class="with-cover  "
      style="--cover-image: url('<?= htmlspecialchars($loginBg) ?>');">
      
    <div class="wrapper d-flex flex-column min-vh-100">

<?php include('../header.php'); ?>
<?php include('../navbar.php'); ?>
<div class="flex-grow-1">

<div class="container">
    <!-- <div class="container surface-none"> -->

    <h2>📦 Pending Restock Orders (Editable by Owner)</h2>

    <?php if ($results->num_rows > 0): ?>
        <table>
            <tr>
                <th>Product</th>
                <th>Current Quantity</th>
                <th>Expected Quantity</th>
                <th>Suggested Order</th>
                <th>Stock %</th>
                <th>Order Date</th>
                <th>Actions</th>
            </tr>
            <?php while ($row = $results->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($row['Name']) ?></td>
                    <td><?= $row['current_qty'] ?></td>
                    <td><?= $row['expected_qty'] ?></td>
                    <td><strong style="color: red;"><?= $row['suggested_order'] ?></strong></td>
                    <td><?= $row['stock_percent'] ?>%</td>
                    <td><?= $row['order_date'] ?></td>
                    <td class="actions">
                        <!-- Inline Edit Form -->
                        <form method="POST">
                            <input type="hidden" name="edit_id" value="<?= $row['id'] ?>">
                            <input type="number" name="new_qty" value="<?= $row['suggested_order'] ?>" min="1" required>
                            <button title="Update Quantity">💾</button>
                        </form>

                        <!-- Delete -->
                        <a href="orders.php?delete=<?= $row['id'] ?>" onclick="return confirm('Delete this order?')">🗑</a>

                        <!-- Approve Now -->
                        <a href="approveOrder.php?id=<?= $row['id'] ?>" onclick="return confirm('Approve and execute this order now?')">✅</a>
                    </td>
                </tr>
            <?php endwhile; ?>
        </table>
    <?php else: ?>
        <div class="no-orders">✅ No pending restock orders at the moment.</div>
    <?php endif; ?>
</div></div>
<?php include '../footer/footer.php'; ?>
</div>

</body>
</html>
