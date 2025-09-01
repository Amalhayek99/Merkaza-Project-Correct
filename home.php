<?php
require_once 'sessions.php';
require_role([ 'admin', 'guest', 'customer', 'worker','owner']);
include('db.php');
$loginBg = 'cover/cover1/cover5.jpg';

// Check if the user is logged in
if (!isset($_SESSION['username'])) {
    header('location: index.php');
    exit();
}

$username = $_SESSION['username'];
$user_id = $_SESSION['user_id'] ?? null;

$recentProducts = [];

if ($user_id) {
    // Fetch recently viewed products
$stmt = $conn->prepare("
        SELECT 
            p.id,
            p.Name,
            p.Price,
            MIN(pi.image_path) AS image_path   -- pick a stable first image if multiple
        FROM search_history sh
        JOIN products p ON sh.product_id = p.id
        LEFT JOIN product_images pi ON pi.product_id = p.id
        WHERE sh.user_id = ?
        GROUP BY p.id, p.Name, p.Price
        ORDER BY MAX(sh.search_time) DESC
        LIMIT 7
    ");

    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $historyResult = $stmt->get_result();

    while ($row = $historyResult->fetch_assoc()) {
        $recentProducts[] = $row;
    }

    // Track if user viewed new product
    if (isset($_GET['product_id'])) {
        $product_id = $_GET['product_id'];

        if (!in_array($product_id, array_column($recentProducts, 'id'))) {
            $insertStmt = $conn->prepare("
                INSERT INTO search_history (user_id, product_id, search_time)
                VALUES (?, ?, NOW())
            ");
            $insertStmt->bind_param('ii', $user_id, $product_id);
            $insertStmt->execute();
        }
    }
}

$today = date('Y-m-d');

$active_sales_query = $conn->prepare("
    SELECT 
        s.product_id,
        p.Name,
        p.Price AS original_price,
        s.date_start,
        s.date_end,
        s.discount_value,
        s.price_after_discount,
        MIN(pi.image_path) AS image_path
    FROM sales s
    JOIN products p ON s.product_id = p.id
    LEFT JOIN product_images pi ON pi.product_id = p.id
    WHERE s.date_start <= ? AND s.date_end >= ?
    GROUP BY 
        s.product_id, p.Name, p.Price, s.date_start, s.date_end, 
        s.discount_value, s.price_after_discount
    ORDER BY s.date_start ASC
");
$active_sales_query->bind_param("ss", $today, $today);
$active_sales_query->execute();
$active_sales = $active_sales_query->get_result();




// Fallback: last 10 purchase items (from statics_sales daily rows)
$recentPurchases = [];
$rp = $conn->prepare("
    SELECT 
        ss.product_id AS id,
        p.Name,
        p.Price,
        COALESCE(MIN(pi.image_path), 'uploads/default.png') AS image_path,
        ss.year, ss.month, COALESCE(ss.day,1) AS day,
        ss.quantity_sold
    FROM statics_sales ss
    JOIN products p ON p.id = ss.product_id
    LEFT JOIN product_images pi ON pi.product_id = p.id
    WHERE ss.quantity_sold > 0
    GROUP BY ss.product_id, p.Name, p.Price, ss.year, ss.month, ss.day, ss.quantity_sold
    ORDER BY ss.year DESC, ss.month DESC, ss.day DESC
    LIMIT 7
");
$rp->execute();
$recentPurchases = $rp->get_result()->fetch_all(MYSQLI_ASSOC);
$rp->close();

?>


<!DOCTYPE html>
<html>
<head>
<title>Home</title>
  <link rel="icon" href="merkaza.jpeg" type="image/jpeg">
</head>


<div class="home-bg with-cover"
     style="--cover-image: url('<?= htmlspecialchars($loginBg) ?>');">


    <div class="wrapper d-flex flex-column min-vh-100">

<?php include 'header.php'; ?>
<?php include 'navbar.php'; ?>
<div class="flex-grow-1">

<div class="main-content">

  
<!-- Recently Viewed -->
<?php if (!empty($recentProducts)): ?>
<section class="recently-viewed container surface-none add-shell">
    <div class="section-heading">Recently Viewed</div>

    <div class="rv-grid">
        <?php foreach ($recentProducts as $p): ?>
            <a class="rv-card" href="details.php?id=<?= (int)$p['id'] ?>">
                <div class="rv-thumb">
                    <img
                        src="<?= htmlspecialchars($p['image_path'] ?: 'uploads/default.png') ?>"
                        alt="<?= htmlspecialchars($p['Name']) ?>"
                        onerror="this.src='uploads/default.png';">
                </div>
                <div class="rv-meta">
                    <div class="rv-name" title="<?= htmlspecialchars($p['Name']) ?>">
                        <?= htmlspecialchars($p['Name']) ?>
                    </div>
                    <div class="rv-price">₪<?= number_format((float)$p['Price'], 2) ?></div>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<?php $hasSales = ($active_sales && $active_sales->num_rows > 0); ?>

<section class="section-outer container surface-none add-shell" style="margin-top:28px;">
    <div class="section-heading"><?= $hasSales ? 'Active Sales' : 'Recent Purchases' ?></div>

    <?php if ($hasSales): ?>
        <div class="sale-grid">
            <?php while($sale = $active_sales->fetch_assoc()): ?>
<a class="sale-card" href="details.php?id=<?= (int)$sale['product_id'] ?>">
    <div class="rv-thumb">
        <img
            src="<?= htmlspecialchars($sale['image_path'] ?: 'uploads/default.png') ?>"
            alt="<?= htmlspecialchars($sale['Name']) ?>"
            onerror="this.src='uploads/default.png';"
        >
        <div class="sale-badge">-<?= (int)$sale['discount_value'] ?>%</div>
    </div>

<?php
    // format to dd/mm/yyyy
    $startDate = date('d/m/Y', strtotime($sale['date_start']));
    $endDate   = date('d/m/Y', strtotime($sale['date_end']));
?>
<div class="sale-details">
    <!-- left: product name -->
    <div class="sd-left rv-name" title="<?= htmlspecialchars($sale['Name']) ?>">
        <?= htmlspecialchars($sale['Name']) ?>
    </div>

    <!-- right: three stacked lines -->
    <div class="sd-right">
        <div class="sd-row sd-date"><?= $startDate ?></div>
        <div class="sd-row sd-price">
            <span class="old">₪<?= number_format((float)$sale['original_price'], 2) ?></span>
            <span class="arrow">→</span>
            <span class="new">₪<?= number_format((float)$sale['price_after_discount'], 2) ?></span>
        </div>
        <div class="sd-row sd-date"><?= $endDate ?></div>
    </div>
</div>



</a>

            <?php endwhile; ?>
        </div>
    <?php else: ?>
        <div class="rv-grid">
            <?php foreach ($recentPurchases as $p): ?>
                <a class="rv-card" href="details.php?id=<?= (int)$p['id'] ?>">
                    <div class="rv-thumb">
                        <img
                            src="<?= htmlspecialchars($p['image_path']) ?>"
                            alt="<?= htmlspecialchars($p['Name']) ?>"
                            onerror="this.src='uploads/default.png';"
                        >
                    </div>
                    <div class="rv-meta">
                        <div class="rv-name" title="<?= htmlspecialchars($p['Name']) ?>">
                            <?= htmlspecialchars($p['Name']) ?>
                        </div>
                        <div class="rv-price">₪<?= number_format((float)$p['Price'], 2) ?></div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>


</div></div>
<?php include 'footer/footer.php'; ?>
</div>
</div>