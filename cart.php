<?php
require_once 'sessions.php';
require_role(['customer']);
include_once('db.php');

/* === Background: rotate images in cover/cart on each load === */
function nextCartBackground(string $webDir = 'cover/cart'): string {
    $fsDir = __DIR__ . '/' . $webDir;
    $files = glob($fsDir . '/*.{jpg,jpeg,png,webp}', GLOB_BRACE) ?: [];
    if (!$files) return $webDir . '/cover34.jpg';   // fallback
    sort($files, SORT_NATURAL | SORT_FLAG_CASE);
    $i = $_SESSION['cart_bg_idx'] ?? 0;
    $pick = $files[$i % count($files)];
    $_SESSION['cart_bg_idx'] = ($i + 1) % count($files);
    return $webDir . '/' . basename($pick);
}
$loginBg = nextCartBackground();

/* === Only customers can access === */
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'customer') {
    header('location: index.php'); exit();
}

$user_id = (int)$_SESSION['user_id'];
$quantity_error = '';
$purchase_success = false;
$purchase_blocked = false;
$purchase_error_message = '';

/* === Update quantity === */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_cart'])) {
    $product_id   = (int)($_POST['product_id'] ?? 0);
    $new_quantity = (int)($_POST['quantity'] ?? 0);

    $stock_stmt = $conn->prepare("SELECT quantity FROM products WHERE id = ?");
    $stock_stmt->bind_param("i", $product_id);
    $stock_stmt->execute();
    $available_stock = ($stock_stmt->get_result()->fetch_assoc()['quantity'] ?? 0);
    $stock_stmt->close();

    if ($new_quantity <= 0) {
        $quantity_error = "Quantity must be at least 1.";
    } elseif ($new_quantity > $available_stock) {
        $quantity_error = "Only {$available_stock} item(s) available in stock.";
    } else {
        $stmt = $conn->prepare("UPDATE cart SET quantity = ? WHERE user_id = ? AND product_id = ?");
        $stmt->bind_param("iii", $new_quantity, $user_id, $product_id);
        $stmt->execute();
        $stmt->close();
    }
}

/* === Remove item === */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_item'])) {
    $product_id = (int)($_POST['product_id'] ?? 0);
    $stmt = $conn->prepare("DELETE FROM cart WHERE user_id = ? AND product_id = ?");
    $stmt->bind_param("ii", $user_id, $product_id);
    $stmt->execute();
    $stmt->close();
}

/* === Purchase === */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['purchase'])) {
    $cart_stmt = $conn->prepare("
        SELECT c.product_id, c.quantity, c.selled_price,
               p.net_price, p.quantity AS stock, p.name AS product_name
        FROM cart c
        JOIN products p ON c.product_id = p.id
        WHERE c.user_id = ?");
    $cart_stmt->bind_param("i", $user_id);
    $cart_stmt->execute();
    $cart_result = $cart_stmt->get_result();

    $cart_items_temp = [];
    while ($item = $cart_result->fetch_assoc()) {
        if ($item['quantity'] > $item['stock']) {
            $purchase_blocked = true;
            $purchase_error_message .= "❌ <strong>{$item['product_name']}</strong> has only {$item['stock']} in stock.<br>";
        }
        $cart_items_temp[] = $item;
    }
    $cart_stmt->close();

    if (!$purchase_blocked) {
        foreach ($cart_items_temp as $item) {
            $product_id     = (int)$item['product_id'];
            $qty_purchased  = (int)$item['quantity'];
            $final_price    = (float)$item['selled_price'];
            $profit_per_item= $final_price - (float)$item['net_price'];
            $total_profit   = $profit_per_item * $qty_purchased;
            $year = (int)date('Y'); $month = (int)date('n'); $day = (int)date('j');

            $u = $conn->prepare("UPDATE products SET quantity = quantity - ? WHERE id = ?");
            $u->bind_param("ii", $qty_purchased, $product_id);
            $u->execute(); $u->close();

            $s = $conn->prepare("
              INSERT INTO statics_sales (product_id, year, month, day, quantity_sold, profit_per_item, total_profit)
              VALUES (?, ?, ?, ?, ?, ?, ?)");
            $s->bind_param("iiiiidd", $product_id, $year, $month, $day, $qty_purchased, $profit_per_item, $total_profit);
            $s->execute(); $s->close();
        }
        $del = $conn->prepare("DELETE FROM cart WHERE user_id = ?");
        $del->bind_param("i", $user_id); $del->execute(); $del->close();
        $purchase_success = true;
    }
}

/* === Fetch cart (+ one product image) === */
$sql = "
  SELECT
      p.name,
      c.selled_price AS price,                       -- current price used in totals
      p.price        AS original_price,              -- regular/original price
      s.price_after_discount,                        -- from sales (if active)
      s.is_active    AS sale_active,
      c.quantity,
      (c.selled_price * c.quantity) AS total,
      p.id AS product_id,
      (SELECT MIN(pi.image_path) FROM product_images pi WHERE pi.product_id = p.id) AS image_path
  FROM cart c
  JOIN products p ON c.product_id = p.id
  LEFT JOIN sales s 
         ON s.product_id = p.id
        AND s.is_active = 1
        AND (CURDATE() BETWEEN DATE(s.date_start) AND DATE(s.date_end))
  WHERE c.user_id = ?
";

;
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();

$cart_items = [];
$grand_total = 0;
while ($row = $res->fetch_assoc()) {
    $cart_items[] = $row;
    $grand_total += $row['total'];
}
$stmt->close();
$conn->close();

$isEmpty = count($cart_items) === 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Your Cart</title>
  <link rel="stylesheet" href="styles.css">
  <link rel="stylesheet" href="cart.css">
  <link rel="icon" href="merkaza.jpeg" type="image/jpeg">


</head>

<body class="with-cover" data-cover="<?= htmlspecialchars($loginBg) ?>">

<div class="wrapper d-flex flex-column min-vh-100">
  <?php include 'header.php'; ?>
  <?php include 'navbar.php'; ?>

  <div class="flex-grow-1 <?= $isEmpty ? 'd-flex align-items-center justify-content-center' : '' ?>">
    <div class="cart-wrap <?= $isEmpty ? '' : 'mt-4 mb-5' ?>">
      <div class="cart-card <?= $isEmpty ? 'empty' : '' ?>">

        <h2 class="cart-title">🛒 Your Cart</h2>

        <?php if ($purchase_success): ?>
          <div class="alert alert-success text-center">✅ Purchase successful! Your items are on the way.</div>
        <?php endif; ?>

        <?php if (!empty($purchase_error_message)): ?>
          <div class="alert alert-danger text-center"><?= $purchase_error_message ?></div>
        <?php endif; ?>

        <?php if (!$isEmpty): ?>
          <table class="cart-table">
            <thead>
              <tr>
                <th>Product</th>
                <th>Price (₪)</th>
                <th>Quantity</th>
                <th>Total (₪)</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ($cart_items as $item):
                  // Normalize path: DB stores 'uploads/...', but add fallback if empty/odd
                  $img = $item['image_path'] ?: 'uploads/default.png';
                  if (!preg_match('#^https?://#', $img) && strpos($img, 'uploads/') !== 0) {
                      $img = 'uploads/' . ltrim($img, '/');
                  }
            ?>
              <tr>
                <td>
                  <div class="prod-name"><?= htmlspecialchars($item['name']) ?></div>
                  <div class="thumb-wrap">
                    <img src="<?= htmlspecialchars($img) ?>" alt="<?= htmlspecialchars($item['name']) ?>"
                         class="thumb" onerror="this.src='uploads/default.png';">
                    <div class="thumb-preview">
                      <img src="<?= htmlspecialchars($img) ?>" alt="<?= htmlspecialchars($item['name']) ?>">
                    </div>
                  </div>
                </td>

                <td>
                  <?php
                    // Current/sale price used for calculations
                    $price = isset($item['price']) ? (float)$item['price'] : 0.0;

                    // Detect original/regular price (use whatever key you store it in)
                    $orig = null;
                    if (isset($item['original_price']))            $orig = (float)$item['original_price'];
                    elseif (isset($item['regular_price']))         $orig = (float)$item['regular_price'];
                    elseif (isset($item['price_before_discount'])) $orig = (float)$item['price_before_discount'];

                    // Simple sale flag: either an explicit flag or any original > current
                    $onSale = (!empty($item['on_sale']) || ($orig !== null && $orig > $price));

                    if ($onSale && $orig !== null && $orig > $price): ?>
                      <div class="old-price"><del><?= number_format($orig, 2) ?></del></div>
                      <div class="new-price"><?= number_format($price, 2) ?></div>
                    <?php else: ?>
                      <?= number_format($price, 2) ?>
                  <?php endif; ?>
                </td>

                <td>
                  <form method="POST" class="quantity-form">
                    <input type="hidden" name="product_id" value="<?= (int)$item['product_id'] ?>">
                    <input type="number" name="quantity" min="1" value="<?= (int)$item['quantity'] ?>" class="qty-input">
                    <button type="submit" name="update_cart" class="btn-update">Update</button>
                    <?php
                      if (isset($_POST['update_cart'], $_POST['product_id']) &&
                          (int)$_POST['product_id'] === (int)$item['product_id'] &&
                          !empty($quantity_error)) {
                          echo "<div class='qty-error'>{$quantity_error}</div>";
                      }
                    ?>
                  </form>
                </td>

                <td><?= number_format($item['total'], 2) ?></td>

                <td>
                  <form method="POST">
                    <input type="hidden" name="product_id" value="<?= (int)$item['product_id'] ?>">
                    <button type="submit" name="remove_item" class="btn-remove"
                            onclick="return confirm('Remove this item from cart?');">Remove</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>

          <div class="total-row">
            <div class="fw-semibold">Total:</div>
            <div class="fw-bold">₪<?= number_format($grand_total, 2) ?></div>
          </div>

          <form method="POST" class="text-end mt-3">
            <button type="submit" name="purchase" class="btn-buy"
                    onclick="return confirm('Are you sure you want to purchase all items?');">
              ✅ Purchase
            </button>
          </form>

        <?php else: ?>
          <div class="cart-empty">Your cart is currently empty.</div>
        <?php endif; ?>

      </div>
    </div>
  </div>

  <?php include 'footer/footer.php'; ?>
</div>

<!-- Cover image CSS var (no inline styles) -->
<script>
  (function () {
    var cover = document.body.dataset.cover;
    if (cover) document.body.style.setProperty('--cover-image', "url('" + cover + "')");
  })();
</script>

<!-- Click-to-enlarge logic -->
<script>
document.addEventListener('click', function(e){
  // Close any open previews if click is outside
  document.querySelectorAll('.thumb-wrap.open').forEach(function(w){
    if (!w.contains(e.target)) w.classList.remove('open');
  });
  // Toggle when clicking a thumb
  const t = e.target.closest('.thumb-wrap .thumb');
  if (t){
    const wrap = t.closest('.thumb-wrap');
    wrap.classList.toggle('open');
  }
});
</script>
</body>
</html>
