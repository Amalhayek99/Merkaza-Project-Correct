<?php
require_once '../sessions.php';
include('../db.php');
require_role(['owner', 'admin']);
$loginBg = 'cover/owner/cover28.jpg';

// Fetch emergency stock orders
$query = "
    SELECT ordered.*, products.Name
    FROM ordered
    JOIN products ON ordered.product_id = products.ID
    ORDER BY ordered.order_date DESC
";
$orders = $conn->query($query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta http-equiv="refresh" content="10" />
  <title>Passed Restock Orders</title>
<link rel="stylesheet" href="owner.css">
<link rel="stylesheet" href="passedorders.css">
<link rel="icon" href="/Merkaza-Almost-Done/public_html/merkaza.jpeg" type="image/jpeg">


</head>
<body class="with-cover  "
      style="--cover-image: url('<?= htmlspecialchars($loginBg) ?>');">
   <div class="wrapper d-flex flex-column min-vh-100">

  <?php include('../header.php'); ?>
  <?php include('../navbar.php'); ?>

  <div class="flex-grow-1">
<div class="container surface-none">

      <h2>📦 Emergency Restock History (≤ 20% stock)</h2>

      <?php if ($orders->num_rows > 0): ?>
        <div class="orders-table-wrap">
          <table class="orders-table">
            <thead>
              <tr>
                <th>Product</th>
                <th>Order Date</th>
                <th>Before Quantity</th>
                <th>Ordered Quantity</th>
                <th>After Quantity</th>
              </tr>
            </thead>
            <tbody>
              <?php while ($row = $orders->fetch_assoc()): ?>
                <tr>
                  <td><?= htmlspecialchars($row['Name']) ?></td>
                  <td><?= htmlspecialchars($row['order_date']) ?></td>
                  <td><?= (int)$row['before_quantity'] ?></td>
                  <td class="qty-added"><strong><?= (int)$row['ordered_quantity'] ?></strong></td>
                  <td><?= (int)$row['after_quantity'] ?></td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <p class="note" style="text-align:center; color:var(--muted); margin-top:32px;">
          ✅ No critical restock actions have been triggered yet.
        </p>
      <?php endif; ?>

    </div>
  </div>

  <?php include '../footer/footer.php'; ?>
</div>
</body>
</html>
