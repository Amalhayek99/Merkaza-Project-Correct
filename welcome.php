<?php
require_once 'sessions.php';
include('db.php');
require_role(['guest']);

$today = date('Y-m-d');

/* === Active sales (with image + original/new price) === */
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

/* === Fallback: last 7 purchases (if no active sales) === */
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
    <title>Welcome</title>
    <link rel="icon" href="merkaza.jpeg" type="image/jpeg">
    <link rel="stylesheet" type="text/css" href="styles.css">

    <style>
      /* soft background image overlay for this page only */
      body { position: relative; }
      body::before{
        content:"";
        position: fixed;
        inset: 0;
        background: url('cover/cover1/cover5.jpg') center/cover no-repeat;
        opacity: .30;
        z-index: -1;
        pointer-events: none;
      }
      .main-content{ text-align:center; margin-top:34px; }
    </style>
<!-- 
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script> -->
</head>
<body>
<div class="wrapper d-flex flex-column min-vh-100">

<?php include 'header.php'; ?>
<?php include 'navbar.php'; ?>

<div class="main-content flex-grow-1">

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
                            $startDate = date('d/m/Y', strtotime($sale['date_start']));
                            $endDate   = date('d/m/Y', strtotime($sale['date_end']));
                        ?>
                        <div class="sale-details">
                            <div class="sd-left rv-name" title="<?= htmlspecialchars($sale['Name']) ?>">
                                <?= htmlspecialchars($sale['Name']) ?>
                            </div>
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
            <!-- Fallback: show last purchases as simple product cards -->
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

</div>

<?php include 'footer/footer.php'; ?>
</div>
</body>
</html>
