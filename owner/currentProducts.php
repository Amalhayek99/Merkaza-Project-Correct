<?php
require_once '../sessions.php';
require_role([ 'owner']);
include('../db.php');
$loginBg = 'cover/owner/cover25.jpg';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once '../auth/PHPMailer/PHPMailer.php';
require_once '../auth/PHPMailer/SMTP.php';
require_once '../auth/PHPMailer/Exception.php';

$currentMonth = date('n');
$currentYear  = date('Y');
$prevYear1    = $currentYear - 1;
$prevYear2    = $currentYear - 2;

// >>> choose where to show the "no history" products
$showNoHistoryFirst = false; // set true if you want "no history" first

$products = $conn->query("SELECT * FROM products");

$withHistory = [];
$noHistory   = [];

while ($product = $products->fetch_assoc()) {
    $productId  = (int)$product['ID'];
    $name       = $product['Name'];
    $currentQty = (int)$product['quantity'];

    // fetch totals for same month in previous 2 years
    $stmt = $conn->prepare("
        SELECT SUM(quantity_sold) AS total_sold, year
        FROM statics_sales
        WHERE product_id = ? AND month = ? AND year IN (?, ?)
        GROUP BY year
    ");
    $stmt->bind_param("iiii", $productId, $currentMonth, $prevYear1, $prevYear2);
    $stmt->execute();
    $result = $stmt->get_result();

    $sum = 0;
    $yearsCount = 0;
    while ($row = $result->fetch_assoc()) {
        $sum += (int)$row['total_sold'];
        $yearsCount++;
    }
    $stmt->close();

    if ($yearsCount > 0) {
        $expectedQty  = max(0, (int)round($sum / $yearsCount));
        $needed       = max(0, $expectedQty - $currentQty);
        $stockPercent = $expectedQty > 0 ? (int)round(($currentQty / $expectedQty) * 100) : 0;

        // === 20% auto-pending + email logic (unchanged) ===
        if ($needed > 0 && $stockPercent <= 20) {
            $check = $conn->prepare("SELECT id, current_qty FROM pending_orders WHERE product_id = ? AND processed = 0");
            $check->bind_param("i", $productId);
            $check->execute();
            $check->store_result();

            if ($check->num_rows === 0) {
                $insert = $conn->prepare("
                    INSERT INTO pending_orders
                        (product_id, order_date, auto_order_time, current_qty, expected_qty, suggested_order, stock_percent, processed)
                    VALUES
                        (?, NOW(), DATE_ADD(NOW(), INTERVAL 1 HOUR), ?, ?, ?, ?, 0)
                ");
                $insert->bind_param("iiiii", $productId, $currentQty, $expectedQty, $needed, $stockPercent);
                $insert->execute();
                $insert->close();
                $sendMail = true;
            } else {
                $check->bind_result($pendingId, $pendingQty);
                $check->fetch();

                if ((int)$pendingQty !== $currentQty) {
                    $update = $conn->prepare("
                        UPDATE pending_orders
                        SET current_qty = ?, stock_percent = ?, suggested_order = ?
                        WHERE id = ?
                    ");
                    $update->bind_param("iiii", $currentQty, $stockPercent, $needed, $pendingId);
                    $update->execute();
                    $update->close();
                    $sendMail = true;
                } else {
                    $sendMail = false;
                }
            }
            $check->close();

            if (!empty($sendMail)) {
                $ownerEmail = '';
                $ownerQuery = $conn->query("SELECT email FROM users WHERE usertype = 'owner' LIMIT 1");
                if ($rowE = $ownerQuery->fetch_assoc()) {
                    $ownerEmail = $rowE['email'];
                }

                if (!empty($ownerEmail)) {
                    $mail = new PHPMailer(true);
                    try {
                        $mail->isSMTP();
                        $mail->Host       = 'smtp.gmail.com';
                        $mail->SMTPAuth   = true;
                        $mail->Username   = 'amalhayek10@gmail.com';
                        $mail->Password   = 'buet ybii ogil domj';
                        $mail->SMTPSecure = 'tls';
                        $mail->Port       = 587;

                        $mail->setFrom('amalhayek10@gmail.com', 'Stock Monitor');
                        $mail->addAddress($ownerEmail);
                        $mail->Subject = "Merkaza Low Stock Alert - $name";
                        $mail->Body    = "Merkaza \nProduct: $name\nCurrent Quantity: $currentQty\nExpected: $expectedQty\nSuggested Order: $needed\nStock %: $stockPercent%";
                        $mail->send();
                        echo "<script>console.log('✅ Mail sent to $ownerEmail');</script>";
                    } catch (Exception $e) {
                        echo "<div style='color: red;'>❌ Mail Error: " . $mail->ErrorInfo . "</div>";
                    }
                } else {
                    echo "<div style='color: red;'>❌ No owner email found in users table.</div>";
                }
            }
        }
        // ==========================================

        $withHistory[] = [
            'product_id'      => $productId,          // <— keep id for links
            'Name'            => $name,
            'Current'         => $currentQty,
            'Expected'        => $expectedQty,
            'ExpectedDisplay' => $expectedQty,
            'Order'           => $needed,
            'Percent'         => $stockPercent,
            'PercentDisplay'  => $stockPercent . '%'
        ];
    } else {
        $noHistory[] = [
            'product_id'      => $productId,          // <— keep id for links
            'Name'            => $name,
            'Current'         => $currentQty,
            'Expected'        => null,
            'ExpectedDisplay' => '—',
            'Order'           => 0,
            'Percent'         => null,
            'PercentDisplay'  => '—'
        ];
    }
}

// Sort: with-history by lowest stock% first (most needy → least needy).
usort($withHistory, function ($a, $b) {
    $pa = $a['Percent'] ?? PHP_INT_MAX;
    $pb = $b['Percent'] ?? PHP_INT_MAX;
    if ($pa === $pb) {
        return ($b['Order'] <=> $a['Order']); // larger suggested order first
    }
    return $pa <=> $pb;
});

// Optional: keep no-history alphabetical
usort($noHistory, function ($a, $b) {
    return strcasecmp($a['Name'], $b['Name']);
});

// Merge according to preference
$orders = $showNoHistoryFirst
    ? array_merge($noHistory, $withHistory)
    : array_merge($withHistory, $noHistory);
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Current Products - Low Stock Preview</title>
    <meta http-equiv="refresh" content="10">
    <link rel="icon" href="/Merkaza-Almost-Done/public_html/merkaza.jpeg" type="image/jpeg">

<link rel="stylesheet" href="owner.css">
<link rel="stylesheet" href="currentProducts.css">

</head>
<body class="with-cover  "
      style="--cover-image: url('<?= htmlspecialchars($loginBg) ?>');">
  <div class="wrapper d-flex flex-column min-vh-100">

    <?php include('../header.php'); ?>
    <?php include('../navbar.php'); ?>

    <main class="flex-grow-1">
        <div class="cp-container">

            <div class="cp-header d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-3">
                    <div class="cp-icon">📦</div>
                    <div>
                        <h1 class="cp-title mb-0">Current Product Status</h1>
                        <div class="cp-subtitle">Low Stock Preview • Avg. of <?= ($currentYear - 1) ?> & <?= ($currentYear - 2) ?></div>
                    </div>
                </div>
                <div class="cp-legend">
                    <span class="badge bg-danger-subtle text-danger">≤ 20% (critical)</span>
                    <span class="badge bg-warning-subtle text-warning">21–59% (low)</span>
                    <span class="badge bg-info-subtle text-info">60–99% (ok)</span>
                    <span class="badge bg-success-subtle text-success">≥ 100% (sufficient)</span>
                </div>
            </div>

            <?php if (count($orders) > 0): ?>
                <div class="table-responsive cp-table-wrap">
                    <table class="table cp-table align-middle">
                        <thead class="cp-thead">
                        <tr>
                            <th>Product</th>
                            <th>Current Qty</th>
                            <th>Expected Qty</th>
                            <th>Suggested Order</th>
                            <th>Stock %</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($orders as $row): 
                            $productId = (int)$row['product_id'];
                            $name     = htmlspecialchars($row['Name']);
                            $current  = $row['Current'];
                            $expected = $row['ExpectedDisplay'];
                            $order    = $row['Order'];
                            $percent  = $row['Percent'];      // may be null for no-history
                            $pDisp    = $row['PercentDisplay'];

                            // Row highlight
                            $rowClass = '';
                            if ($percent !== null) {
                                if ($percent <= 20) {
                                    $rowClass = 'cp-row-crit';
                                } elseif ($percent >= 100) {
                                    $rowClass = 'cp-row-ok';
                                }
                            }

                            // --- STOCK BADGE COLOR (decide first) ---
                            if ($percent === null) {
                                $badgeClass = 'bg-secondary-subtle text-secondary';
                            } elseif ($percent <= 20) {
                                $badgeClass = 'bg-danger-subtle text-danger';
                            } elseif ($percent < 60) {
                                $badgeClass = 'bg-warning-subtle text-warning';
                            } elseif ($percent < 100) {
                                $badgeClass = 'bg-info-subtle text-info';
                            } else {
                                $badgeClass = 'bg-success-subtle text-success';
                            }

                            // --- SUGGESTED ORDER COLOR = SAME text-* AS BADGE ---
                            // default fallback
                            $orderTextColor = 'text-secondary';
                            if (strpos($badgeClass, 'text-danger') !== false) {
                                $orderTextColor = 'text-danger';
                            } elseif (strpos($badgeClass, 'text-warning') !== false) {
                                $orderTextColor = 'text-warning';
                            } elseif (strpos($badgeClass, 'text-info') !== false) {
                                $orderTextColor = 'text-info';
                            } elseif (strpos($badgeClass, 'text-success') !== false) {
                                $orderTextColor = 'text-success';
                            }
                            $orderClass = $orderTextColor . ' fw-semibold';
                        ?>
                            <tr class="<?= $rowClass ?>">
                                <td class="text-start">
                                    <a class="link-underline-none cp-product" href="qtyedit.php?id=<?= $productId ?>">
                                        <?= $name ?>
                                    </a>
                                </td>
                                <td><?= $current ?></td>
                                <td><?= $expected ?></td>
                                <td><span class="<?= $orderClass ?>"><?= $order ?></span></td>
                                <td>
                                    <span class="badge <?= $badgeClass ?> cp-badge">
                                        <?= $pDisp ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="cp-empty">
                    ✅ All products are sufficiently stocked for this month!
                </div>
            <?php endif; ?>

        </div>
    </main>

    <?php include '../footer/footer.php'; ?>
</div>
</body>
</html>
