<?php
require_once '../sessions.php';
require_role(['owner']);
require_once '../db.php';

date_default_timezone_set('Asia/Jerusalem');

$success = '';
$error   = '';

// --- Validate product id ---
$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($product_id <= 0) {
    $error = 'Missing or invalid product id.';
}

// --- Handle manual order submission ---
if (empty($error) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['manual_order'])) {
    $order_qty = (int)($_POST['order_qty'] ?? 0);

    if ($order_qty <= 0) {
        $error = 'Please enter a valid order quantity.';
    } else {
        // Fetch current qty for the log row
        $stmt = $conn->prepare("SELECT quantity FROM products WHERE ID = ?");
        $stmt->bind_param("i", $product_id);
        $stmt->execute();
        $stmt->bind_result($current_qty_now);
        $stmt->fetch();
        $stmt->close();

        if ($current_qty_now === null) {
            $error = 'Product not found.';
        } else {
            $now = date('Y-m-d H:i:s');
            $autoTime = date('Y-m-d H:i:s', strtotime('+1 hour'));

            $ins = $conn->prepare("
                INSERT INTO pending_orders (product_id, order_date, suggested_order, current_qty, auto_order_time, processed)
                VALUES (?, ?, ?, ?, ?, 0)
            ");
            $ins->bind_param("isiss", $product_id, $now, $order_qty, $current_qty_now, $autoTime);

            if ($ins->execute()) {
                $success = "Pending order created for $order_qty unit(s). It will auto-approve in ~1 hour if not handled.";
            } else {
                $error = 'Failed to create pending order. Please try again.';
            }
            $ins->close();
        }
    }
}

// --- Fetch product core data (select * so we can pick image field flexibly) ---
$product = null;
$current_qty = 0;
$image_url = null;

if (empty($error)) {
    $stmt = $conn->prepare("SELECT * FROM products WHERE ID = ?");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $product = $result->fetch_assoc();
    $stmt->close();

    if (!$product) {
        $error = 'Product not found.';
    } else {
        $current_qty = (int)$product['quantity'];

        // --- Resolve image URL (supports several field names + common folders) ---
// === IMAGE: latest image from product_images, else uploads/default.png ===
$image_url = null;

// paths
$WEB_PREFIX   = '../';                 // because qtyedit.php is in /owner/
$UPLOADS_REL  = 'uploads/';            // under public_html
$DEFAULT_FILE = 'default.png';

$defaultWeb = $WEB_PREFIX . $UPLOADS_REL . $DEFAULT_FILE;                 // ../uploads/default.png
$defaultFs  = dirname(__DIR__) . '/' . $UPLOADS_REL . $DEFAULT_FILE;      // server path

$pi = $conn->prepare("
    SELECT image_path
    FROM product_images
    WHERE product_id = ?
    ORDER BY id DESC
    LIMIT 1
");
$pi->bind_param("i", $product_id);
$pi->execute();
$pi->bind_result($image_path);
$hasImg = $pi->fetch();
$pi->close();

if ($hasImg && $image_path) {
    // DB stores "uploads/filename.jpg" (relative to public_html)
    if (preg_match('#^https?://#i', $image_path)) {
        // absolute URL saved in DB
        $image_url = $image_path;
    } else {
        $image_url = $WEB_PREFIX . ltrim($image_path, '/');                 // ../uploads/filename.jpg
        $fs_path   = dirname(__DIR__) . '/' . ltrim($image_path, '/');      // server file path
        if (!file_exists($fs_path)) {
            $image_url = file_exists($defaultFs) ? $defaultWeb : 'https://via.placeholder.com/600x420?text=No+Image';
        }
    }
} else {
    // no image row → use default
    $image_url = file_exists($defaultFs) ? $defaultWeb : 'https://via.placeholder.com/600x420?text=No+Image';
}

    }
}

// --- Compute expected qty (avg of same month for last 2 years from statics_sales) ---
$expected_qty = null;
if (empty($error)) {
    $month = (int)date('n');
    $y1 = (int)date('Y') - 1;
    $y2 = (int)date('Y') - 2;

    $q = $conn->prepare("
        SELECT year, SUM(quantity_sold) AS total_qty
        FROM statics_sales
        WHERE product_id = ? AND month = ? AND year IN (?, ?)
        GROUP BY year
    ");
    $q->bind_param("iiii", $product_id, $month, $y1, $y2);
    $q->execute();
    $rs = $q->get_result();

    $totals = [];
    while ($row = $rs->fetch_assoc()) {
        $totals[] = (int)$row['total_qty'];
    }
    $q->close();

    $expected_qty = count($totals) ? (int)round(array_sum($totals) / count($totals)) : 0;
}

// --- Calculations (stock % + suggested) ---
$stock_percent = null;
$suggested_order = 0;
if (empty($error)) {
    $stock_percent   = $expected_qty > 0 ? round(($current_qty / $expected_qty) * 100) : null;
    $suggested_order = max($expected_qty - $current_qty, 0);
}

function badge_for_percent(?int $p): string {
    if ($p === null) return 'badge-gray';
    if ($p <= 20)   return 'badge-red';
    if ($p <= 49)   return 'badge-orange';
    if ($p <= 99)   return 'badge-yellow';
    return 'badge-green';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Qty Edit • <?php echo htmlspecialchars($product['Name'] ?? ''); ?></title>
    <link rel="icon" href="../merkaza.jpeg" type="image/jpeg">
<link rel="stylesheet" href="owner.css">
<link rel="stylesheet" href="qtyedit.css">


</head>
<body>
<div class="wrapper d-flex flex-column min-vh-100">

    <?php include('../header.php'); ?>
    <?php include('../navbar.php'); ?>

<div class="container py-4 flex-grow-1 ">

        <div class="page-head d-flex align-items-center justify-content-between mb-3">
            <div>
                <h2 class="mb-1">Quantity Editor</h2>
                <div class="subhead text-muted">Manage stock & orders for a single product.</div>
            </div>
            <a class="btn btn-outline-secondary" href="currentproducts.php">← Back to Current Products</a>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger shadow-sm"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success shadow-sm"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <?php if (!$error && $product): ?>
        <!-- Product Card -->
        <div class="card fancy shadow-lg mb-4">
            <div class="card-body p-4">
                <div class="d-flex flex-wrap align-items-start justify-content-between gap-3">
                    <div class="prod-title">
                        <div class=" badge-ghost">Product</div>
                        <h3 class="m-0"><?php echo htmlspecialchars($product['Name']); ?></h3>
                        <div class="muted-line">Product ID: #<?php echo (int)$product['ID']; ?></div>
                    </div>

                    <div class="grid-4">
                        <div class="metric">
                            <div class="metric-label">Current Qty</div>
                            <div class="metric-value"><?php echo (int)$current_qty; ?></div>
                        </div>
                        <div class="metric">
                            <div class="metric-label">Expected Qty <span class="hint">(avg same month, last 2 yrs)</span></div>
                            <div class="metric-value"><?php echo (int)$expected_qty; ?></div>
                        </div>
                        <div class="metric">
                            <div class="metric-label">Suggested Order</div>
                            <div class="metric-value"><?php echo (int)$suggested_order; ?></div>
                        </div>
                        <div class="metric">
                            <div class="metric-label">Stock %</div>
                            <div class="metric-value">
                                <?php if ($stock_percent === null): ?>
                                    <span class="badge badge-gray">N/A</span>
                                <?php else: ?>
                                    <span class="badge <?php echo badge_for_percent($stock_percent); ?>">
                                        <?php echo $stock_percent; ?>%
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <!-- Manual Order Card with IMAGE on the left -->
        <div class="card action-card shadow">
            <div class="card-body p-4">

                <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-3">
                    <div>
                        <h4 class="mb-1">Create Manual Order</h4>
                        <div class="text-muted">Order now without waiting for the 20% rule.</div>
                    </div>
                    <div class="quick-badges">
                        <span class="legend badge-red">≤20% critical</span>
                        <span class="legend badge-orange">21–49% low</span>
                        <span class="legend badge-yellow">50–99% ok</span>
                        <span class="legend badge-green">≥100% sufficient</span>
                    </div>
                </div>

                <!-- Two-column grid: image + form -->
                <div class="order-grid">
                    <div class="product-visual">
                        <img src="<?php echo htmlspecialchars($image_url); ?>"
                             alt="<?php echo htmlspecialchars($product['Name']); ?>">
                    </div>

                    <form method="post" class="order-form d-flex align-items-end flex-wrap gap-3">
                        <input type="hidden" name="manual_order" value="1">
                        <div class="form-group flex-grow-1">
                            <label for="order_qty" class="form-label">Order Quantity</label>
                            <input type="number" class="form-control form-control-lg" id="order_qty" name="order_qty" min="1" placeholder="e.g. 10" required>
                            <div class="small text-muted mt-1">
                                Tip: click a chip below to fill quickly.
                            </div>
                        </div>

                        <div class="chips-group">
                            <button type="button" class="chip" data-fill="5">+5</button>
                            <button type="button" class="chip" data-fill="10">+10</button>
                            <button type="button" class="chip" data-fill="20">+20</button>
                            <button type="button" class="chip" data-fill="<?php echo (int)$suggested_order; ?>">Use Suggested (<?php echo (int)$suggested_order; ?>)</button>
                        </div>

                        <div class="ms-auto">
                            <button class="btn btn-primary btn-lg px-4">Add Pending Order</button>
                        </div>
                    </form>
                </div>

                <div class="mt-3 small text-muted">
                    Once created, your order appears in <strong>Orders</strong>. If not manually approved, it will be auto-approved after ~1 hour.
                </div>
            </div>
        </div>
        <?php endif; ?>

    </div>

    <?php include('../footer/footer.php'); ?>
</div>

<script>
// quick chips
document.querySelectorAll('.chip').forEach(ch => {
    ch.addEventListener('click', () => {
        const v = parseInt(ch.getAttribute('data-fill'), 10) || 0;
        const input = document.getElementById('order_qty');
        input.value = v;
        input.focus();
    });
});
</script>
</body>
</html>
