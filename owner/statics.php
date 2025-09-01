<?php
require_once '../sessions.php';
require_role(['owner']);
include('../db.php');
require_once '../product_picker.php';
$loginBg = 'cover/owner/cover24.jpg';

/* =========================
   Filters (date-range mode)
   ========================= */
$productFilter = $_GET['product'] ?? '';

/* NEW: date range */
$fromDate = $_GET['from_date'] ?? '';
$toDate   = $_GET['to_date']   ?? '';

/* === NEW: month-only field (1–12) === */
$focusMonth = isset($_GET['focus_month']) ? (int)$_GET['focus_month'] : 0;

/* OLD (commented – replaced by date range)
$yearFilter    = $_GET['year'] ?? '';
$yearToFilter  = $_GET['year_to'] ?? '';
$monthFilter   = $_GET['month'] ?? '';
*/

$where  = [];
$params = [];
$types  = '';

/* Weekly mode (OLD) – not used with date range, keep false to preserve later logic */
$isWeeklyMode = false;
// $isWeeklyMode = !empty($productFilter) && !empty($monthFilter) && empty($yearToFilter);

if (!empty($productFilter)) {
    $where[]  = 'p.Name = ?';
    $params[] = $productFilter;
    $types   .= 's';
}

/* === Date range normalization ===
   If only one date is provided, we’ll treat it as both ends (single-day search). */
if ($fromDate !== '' && $toDate === '')   $toDate   = $fromDate;
if ($toDate   !== '' && $fromDate === '') $fromDate = $toDate;

/* Build WHERE using a real date composed from year/month/day */
if ($fromDate !== '' && $toDate !== '') {
    if ($fromDate > $toDate) { $tmp = $fromDate; $fromDate = $toDate; $toDate = $tmp; }

    $where[]  =
      "STR_TO_DATE(CONCAT(ss.year,'-',LPAD(ss.month,2,'0'),'-',LPAD(ss.day,2,'0')), '%Y-%m-%d')
       BETWEEN ? AND ?";
    $types   .= 'ss';
    $params[] = $fromDate;
    $params[] = $toDate;
}

/* OLD year/month filters (kept as comments)
if (!empty($yearFilter) && !empty($yearToFilter)) {
    $where[] = 'ss.year BETWEEN ? AND ?';
    $params[] = $yearFilter;
    $params[] = $yearToFilter;
    $types .= 'ii';
} elseif (!empty($yearFilter)) {
    $where[] = 'ss.year = ?';
    $params[] = $yearFilter;
    $types .= 'i';
}
if (!empty($monthFilter)) {
    $where[] = 'ss.month = ?';
    $params[] = $monthFilter;
    $types .= 'i';
}
*/

$filterQuery = '';
if (!empty($where)) {
    $filterQuery = 'WHERE ' . implode(' AND ', $where);
}

/* Determine grouping mode for the bar chart labels:
   - diff years  -> group by year
   - same year   -> group by month (even if same month => just that month)
*/
$groupMode = 'month'; // default
if ($fromDate !== '' && $toDate !== '') {
    $y1 = (int)date('Y', strtotime($fromDate));
    $y2 = (int)date('Y', strtotime($toDate));
    $groupMode = ($y1 !== $y2) ? 'year' : 'month';
}

$profitResults = [];
$rawChart = [];

/* ============================================================
   === NEW: Month-only across years mode (works ONLY when   ===
   === both dates are empty AND focus_month is 1..12).       ===
   ============================================================ */
$useMonthOnly = ($fromDate === '' && $toDate === '' && $focusMonth >= 1 && $focusMonth <= 12);

if ($useMonthOnly) {
    // Force chart labels to be by YEAR (we’re comparing the same month across years)
    $groupMode = 'year';

    // Build WHERE for month-only (and optional product filter)
    $monthWhere = "WHERE ss.month = ?";
    $monthTypes = "i";
    $monthParams = [$focusMonth];

    if (!empty($productFilter)) {
        $monthWhere .= " AND p.Name = ?";
        $monthTypes .= "s";
        $monthParams[] = $productFilter;
    }

    $stmt = $conn->prepare("
        SELECT ss.product_id, ss.quantity_sold, ss.year, ss.month, ss.day,
               p.Name, p.Price as regular_price, p.net_price
        FROM statics_sales ss
        JOIN products p ON ss.product_id = p.ID
        $monthWhere
    ");
    $stmt->bind_param($monthTypes, ...$monthParams);
    $stmt->execute();
    $res = $stmt->get_result();

    $grouped = [];

    while ($row = $res->fetch_assoc()) {
        $product_id     = $row['product_id'];
        $product_name   = $row['Name'];
        $net_price      = $row['net_price'];
        $regular_price  = $row['regular_price'];
        $quantity       = $row['quantity_sold'];
        $year           = $row['year'];
        $month          = $row['month'];
        $day            = $row['day'];
        $dateStr        = sprintf('%04d-%02d-%02d', $year, $month, $day);

        // Sale check for that exact day (reuse your existing logic)
        $saleStmt = $conn->prepare("
            SELECT price_after_discount FROM sales
            WHERE product_id = ? AND date_start <= ? AND date_end >= ?
            LIMIT 1
        ");
        $saleStmt->bind_param("iss", $product_id, $dateStr, $dateStr);
        $saleStmt->execute();
        $saleResult = $saleStmt->get_result();
        $saleRow = $saleResult->fetch_assoc();
        $saleStmt->close();

        $sale_price = null;
        if ($saleRow && isset($saleRow['price_after_discount']) && is_numeric($saleRow['price_after_discount'])) {
            $sale_price = (float)$saleRow['price_after_discount'];
        }

        $selled_price     = $sale_price ?? $regular_price;
        $profit_per_item  = $selled_price - $net_price;
        $total_profit     = $profit_per_item * $quantity;
        $total_cost       = $net_price * $quantity;
        $total_revenue    = $selled_price * $quantity;

        // Group by product + YEAR (since it’s one month across years)
        $groupKey = $product_name . '_' . $year;

        if (!isset($grouped[$groupKey])) {
            $grouped[$groupKey] = [
                'Name'           => $product_name,
                'year'           => $year,
                'month'          => $month, // same for all rows in this mode
                'net_price'      => $net_price,
                'regular_price'  => $regular_price,
                'quantity_sold'  => 0,
                'total_profit'   => 0,
                'total_cost'     => 0,
                'total_revenue'  => 0,
                'sale_breakdown' => [],
            ];
        }

        $grouped[$groupKey]['quantity_sold'] += $quantity;
        $grouped[$groupKey]['total_profit']  += $total_profit;
        $grouped[$groupKey]['total_cost']    += $total_cost;
        $grouped[$groupKey]['total_revenue'] += $total_revenue;

        $price_key = $sale_price === null ? 'Regular' : number_format($sale_price, 2);
        if (!isset($grouped[$groupKey]['sale_breakdown'][$price_key])) {
            $grouped[$groupKey]['sale_breakdown'][$price_key] = 0;
        }
        $grouped[$groupKey]['sale_breakdown'][$price_key] += $quantity;

        // Chart label: YEAR
        $chartLabel = (string)$year;
        $rawChart[$product_name][$chartLabel] =
            ($rawChart[$product_name][$chartLabel] ?? 0) + $quantity;
    }

    $profitResults = array_values($grouped);

    // IMPORTANT: ensure the old block below doesn't run in month-only mode
    // by clearing $where (we're not using date-range WHERE in this mode).
    $where = [];
    $filterQuery = '';
}

/* ===== Existing date-range logic (unchanged); will be skipped if month-only ran ===== */
if (!empty($where)) {
    $stmt = $conn->prepare("
        SELECT ss.product_id, ss.quantity_sold, ss.year, ss.month, ss.day,
               p.Name, p.Price as regular_price, p.net_price
        FROM statics_sales ss
        JOIN products p ON ss.product_id = p.ID
        $filterQuery
    ");
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $res = $stmt->get_result();

    $grouped = [];

    while ($row = $res->fetch_assoc()) {
        $product_id     = $row['product_id'];
        $product_name   = $row['Name'];
        $net_price      = $row['net_price'];
        $regular_price  = $row['regular_price'];
        $quantity       = $row['quantity_sold'];
        $year           = $row['year'];
        $month          = $row['month'];
        $day            = $row['day'];
        $dateStr        = sprintf('%04d-%02d-%02d', $year, $month, $day);

        // Sale check for that day
        $saleStmt = $conn->prepare("
            SELECT price_after_discount FROM sales
            WHERE product_id = ? AND date_start <= ? AND date_end >= ?
            LIMIT 1
        ");
        $saleStmt->bind_param("iss", $product_id, $dateStr, $dateStr);
        $saleStmt->execute();
        $saleResult = $saleStmt->get_result();
        $saleRow = $saleResult->fetch_assoc();
        $saleStmt->close();

        $sale_price = null;
        if ($saleRow && isset($saleRow['price_after_discount']) && is_numeric($saleRow['price_after_discount'])) {
            $sale_price = (float)$saleRow['price_after_discount'];
        }

        $selled_price     = $sale_price ?? $regular_price;
        $profit_per_item  = $selled_price - $net_price;
        $total_profit     = $profit_per_item * $quantity;
        $total_cost       = $net_price * $quantity;
        $total_revenue    = $selled_price * $quantity;

        $groupKey = $product_name . '_' . $year . '_' . $month;

        if (!isset($grouped[$groupKey])) {
            $grouped[$groupKey] = [
                'Name'           => $product_name,
                'year'           => $year,
                'month'          => $month,
                'net_price'      => $net_price,
                'regular_price'  => $regular_price,
                'quantity_sold'  => 0,
                'total_profit'   => 0,
                'total_cost'     => 0,
                'total_revenue'  => 0,
                'sale_breakdown' => [],
            ];
        }

        $grouped[$groupKey]['quantity_sold'] += $quantity;
        $grouped[$groupKey]['total_profit']  += $total_profit;
        $grouped[$groupKey]['total_cost']    += $total_cost;
        $grouped[$groupKey]['total_revenue'] += $total_revenue;

        /* ===== Chart Data label =====
           - If groupMode=year  -> label is the numeric year (e.g., 2024)
           - If groupMode=month -> label is month name (e.g., August)
        */
        if ($groupMode === 'year') {
            $chartLabel = (string)$year;
        } else {
            $chartLabel = date("F", mktime(0, 0, 0, (int)$month, 10));
        }

        $rawChart[$product_name][$chartLabel] =
            ($rawChart[$product_name][$chartLabel] ?? 0) + $quantity;

        $price_key = $sale_price === null ? 'Regular' : number_format($sale_price, 2);
        if (!isset($grouped[$groupKey]['sale_breakdown'][$price_key])) {
            $grouped[$groupKey]['sale_breakdown'][$price_key] = 0;
        }
        $grouped[$groupKey]['sale_breakdown'][$price_key] += $quantity;
    }

    $profitResults = array_values($grouped);
}

/* =========================
   Prepare chart X labels and datasets
   ========================= */
$xLabels = [];
$datasets = [];

/* Gather all x-axis points found */
foreach ($rawChart as $product => $dataPoints) {
    foreach ($dataPoints as $x => $val) {
        if (!in_array($x, $xLabels)) $xLabels[] = $x;
    }
}

/* Keep original natural sort rule when weekly mode (always false now, but preserved) */
$xLabels = array_values(array_unique($xLabels));
if ($isWeeklyMode) { sort($xLabels, SORT_NATURAL); }

/* Limit top 5 products for chart – keep original selection logic */
$topProducts = array_slice(
    array_keys(array_map(fn($item) => array_sum($item), $rawChart)),
    0,
    5
);

foreach ($topProducts as $index => $product) {
    $color = "hsl(" . ($index * 60) . ", 70%, 60%)";
    $datasets[] = [
        'label'           => $product,
        'data'            => array_map(fn($x) => $rawChart[$product][$x] ?? 0, $xLabels),
        'backgroundColor' => $color
    ];
}

/* Donut chart data – unchanged */
$donutLabels = [];
$donutData   = [];
$donutColors = [];

foreach ($topProducts as $index => $product) {
    $totalQuantity = array_sum($rawChart[$product] ?? []);
    $donutLabels[] = $product;
    $donutData[]   = $totalQuantity;
    $donutColors[] = "hsl(" . ($index * 60) . ", 70%, 60%)";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
   <title>Statics</title>
<link rel="stylesheet" href="owner.css">
<link rel="stylesheet" href="statics.css?">
<link rel="icon" href="/Merkaza-Almost-Done/public_html/merkaza.jpeg" type="image/jpeg">


</head>
<body class="with-cover  "
      style="--cover-image: url('<?= htmlspecialchars($loginBg) ?>');">
      <div class="wrapper d-flex flex-column min-vh-100">

<?php include('../header.php'); ?>
<?php include('../navbar.php'); ?>
<div class="flex-grow-1">

<div class="container surface-none">
    
<h2>📊 Products Statistics</h2>

<form class="filter-form" method="GET">
  <?php
    // Product picker (same component)
    render_product_picker($conn, 'product_id', null, '', 'product');
  ?>

  <!-- NEW: date range -->
  <input type="date" name="from_date" placeholder="From"
         value="<?= htmlspecialchars($fromDate) ?>">
  <input type="date" name="to_date" placeholder="To"
         value="<?= htmlspecialchars($toDate) ?>">

  <!-- === NEW (ADDED): Month-only field, used ONLY if both dates are empty === -->
  <input type="number" name="focus_month" placeholder="Month (1–12) --> Use it only if both dates are empty"
         min="1" max="12"
         value="<?= htmlspecialchars($_GET['focus_month'] ?? '') ?>">
  <!-- === END NEW === -->

  <!-- OLD fields (commented)
  <input type="number" name="year"  placeholder="From Year"
         value="<?= htmlspecialchars($_GET['year'] ?? '') ?>">
  <input type="number" name="year_to" placeholder="To Year (optional)"
         value="<?= htmlspecialchars($_GET['year_to'] ?? '') ?>">
  <input type="number" name="month" placeholder="Month (1-12)"
         value="<?= htmlspecialchars($_GET['month'] ?? '') ?>">
  -->

  <button type="submit" class="btn">Filter</button>
</form>

<!-- === NEW (ADDED): Month-only summary block so results show even when $where is empty === -->
<?php
if (!empty($profitResults) && !empty($datasets) && (isset($useMonthOnly) && $useMonthOnly)) {
    $totalQty    = 0;
    $totalProfit = 0;
    $maxQty      = 0;
    $maxProduct  = '';
    $maxMonth    = '';
    $maxYear     = '';
    $chartData   = [];

    foreach ($profitResults as $row) {
        $totalQty    += $row['quantity_sold'];
        $totalProfit += $row['total_profit'];
        if ($row['quantity_sold'] > $maxQty) {
            $maxQty     = $row['quantity_sold'];
            $maxProduct = $row['Name'];
            $maxMonth   = $row['month'];
            $maxYear    = $row['year'];
        }
        $chartData[$row['Name']] = $row['quantity_sold'];
    }

    arsort($chartData);
    $topProductsTable = array_slice($chartData, 0, 5);

    echo '<h3 style="color: rgba(77, 173, 190, 1); text-align: center; margin-top: 10px; line-height:1.6">';
    echo 'The result of your search will be:<br>';
    echo 'Total Quantity: ' . $totalQty . ' | Total Profit: ₪' . number_format($totalProfit, 2) . '<br>';
    echo 'Top Selled: ' . htmlspecialchars($maxProduct) . ' (Quantity: ' . $maxQty . ') in ' . $maxMonth . '/' . $maxYear;
    echo '</h3>';
}
?>
<!-- === END NEW === -->

<?php
if (!empty($where) && !empty($profitResults)) {
    $totalQty    = 0;
    $totalProfit = 0;
    $maxQty      = 0;
    $maxProduct  = '';
    $maxMonth    = '';
    $maxYear     = '';
    $chartData   = [];

    foreach ($profitResults as $row) {
        $totalQty    += $row['quantity_sold'];
        $totalProfit += $row['total_profit'];
        if ($row['quantity_sold'] > $maxQty) {
            $maxQty     = $row['quantity_sold'];
            $maxProduct = $row['Name'];
            $maxMonth   = $row['month'];
            $maxYear    = $row['year'];
        }
        $chartData[$row['Name']] = $row['quantity_sold'];
    }

    arsort($chartData);
    $topProductsTable = array_slice($chartData, 0, 5);

    echo '<h3 style="color: rgba(77, 173, 190, 1); text-align: center; margin-top: 10px; line-height:1.6">';
    echo 'The result of your search will be:<br>';
    echo 'Total Quantity: ' . $totalQty . ' | Total Profit: ₪' . number_format($totalProfit, 2) . '<br>';
    echo 'Top Selled: ' . htmlspecialchars($maxProduct) . ' (Quantity: ' . $maxQty . ') in ' . $maxMonth . '/' . $maxYear;
    echo '</h3>';
}
?>

<?php if (!empty($datasets)): ?>
<div class="card card-success mt-4">
  <div class="card-header d-flex justify-content-between align-items-center">
    <h3 class="card-title" id="chartTitle">Bar Chart</h3>
    <button class="btn" style="background:#43C9E0; opacity:0.8" id="toggleChartBtn">Circle</button>
  </div>
  <div class="card-body">
    <div class="chart-container">
      <canvas id="chartCanvas" width="500" height="300"></canvas>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
  let currentType = 'bar';

  /* Keep original texts; x-axis labels themselves are produced server-side as months or years. */
  const chartTitleText = <?= json_encode('Top Products by Quantity Sold') ?>;
  const xAxisLabel     = <?= json_encode($groupMode === 'year' ? 'Year' : 'Month') ?>;

  const barConfig = {
    type: 'bar',
    data: {
      labels: <?= json_encode($xLabels) ?>,
      datasets: <?= json_encode($datasets) ?>
    },
    options: {
      responsive: true,
      plugins: {
        legend: { position: 'top' },
        title: { display: true, text: chartTitleText }
      },
      scales: {
        y: { beginAtZero: true, title: { display: true, text: 'Quantity Sold' } },
        x: { title: { display: true, text: xAxisLabel } }
      }
    }
  };

  const donutConfig = {
    type: 'doughnut',
    data: {
      labels: <?= json_encode($donutLabels) ?>,
      datasets: [{
        data: <?= json_encode($donutData) ?>,
        backgroundColor: <?= json_encode($donutColors) ?>,
        hoverOffset: 40
      }]
    },
    options: {
      responsive: true,
      plugins: {
        legend: { position: 'top' },
        tooltip: {
          callbacks: {
            label: function(context) {
              let label = context.label || '';
              let value = context.raw || 0;
              let total = context.chart._metasets[context.datasetIndex].total;
              let percentage = ((value / total) * 100).toFixed(1);
              return `${label}: ${value} units (${percentage}%)`;
            }
          }
        },
        title: { display: true, text: 'Product Share (Donut)' }
      }
    }
  };

  let chartInstance = new Chart(document.getElementById('chartCanvas'), barConfig);

  document.getElementById('toggleChartBtn').addEventListener('click', () => {
    chartInstance.destroy();
    if (currentType === 'bar') {
      chartInstance = new Chart(document.getElementById('chartCanvas'), donutConfig);
      document.getElementById('toggleChartBtn').textContent = 'Chart';
      document.getElementById('chartTitle').textContent = 'Donut Chart';
      currentType = 'donut';
    } else {
      chartInstance = new Chart(document.getElementById('chartCanvas'), barConfig);
      document.getElementById('toggleChartBtn').textContent = 'Circle';
      document.getElementById('chartTitle').textContent = 'Bar Chart';
      currentType = 'bar';
    }
  });

  // === NEW (ADDED): disable/clear month field if either date is set ===
  const fromEl = document.querySelector('input[name="from_date"]');
  const toEl   = document.querySelector('input[name="to_date"]');
  const mEl    = document.querySelector('input[name="focus_month"]');
  function syncMonthField() {
    if (!mEl) return;
    const hasDates = (fromEl && fromEl.value) || (toEl && toEl.value);
    mEl.disabled = !!hasDates;
    if (hasDates) mEl.value = '';
  }
  if (fromEl) { fromEl.addEventListener('input', syncMonthField); fromEl.addEventListener('change', syncMonthField); }
  if (toEl)   { toEl.addEventListener('input', syncMonthField);   toEl.addEventListener('change', syncMonthField); }
  document.addEventListener('DOMContentLoaded', syncMonthField);
  // === END NEW ===
</script>
<?php endif; ?>

<!-- === NEW (ADDED): Month-only results table block so it's visible when $where is empty === -->
<?php if ((isset($useMonthOnly) && $useMonthOnly) && !empty($profitResults)): ?>
  <div class="stats-card">
    <div class="premium-scroll">
      <table class="table-premium">
        <thead>
          <tr>
            <th>Product</th>
            <th>Year</th>
            <th>Month</th>
            <th>Quantity Sold</th>
            <th>Net Cost (₪)</th>
            <th>Selling Revenue (₪)</th>
            <th>Total Profit (₪)</th>
            <th>Sale Info</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($profitResults as $row): ?>
          <tr>
            <td><?= htmlspecialchars($row['Name']) ?></td>
            <td><?= $row['year'] ?></td>
            <td><?= $row['month'] ?></td>
            <td><?= $row['quantity_sold'] ?></td>
            <td><?= number_format($row['total_cost'], 2) ?></td>
            <td><?= number_format($row['total_revenue'], 2) ?></td>
            <td><?= number_format($row['total_profit'], 2) ?></td>
            <td>
              <?php if (!empty($row['sale_breakdown']) && is_array($row['sale_breakdown'])): ?>
                <?php foreach ($row['sale_breakdown'] as $price => $qty): ?>
                  <?php if ($price === 'Regular'): ?>
                    <?= $qty ?> units @ Regular Price (₪<?= number_format($row['regular_price'], 2) ?>)<br>
                  <?php else: ?>
                    <?= $qty ?> units @ ₪<?= $price ?><br>
                  <?php endif; ?>
                <?php endforeach; ?>
              <?php else: ?>
                —
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>
<!-- === END NEW === -->

<?php if (!empty($where) && !empty($profitResults)): ?>
  <div class="stats-card">
    <div class="premium-scroll">
      <table class="table-premium">
        <thead>
          <tr>
            <th>Product</th>
            <th>Year</th>
            <th>Month</th>
            <th>Quantity Sold</th>
            <th>Net Cost (₪)</th>
            <th>Selling Revenue (₪)</th>
            <th>Total Profit (₪)</th>
            <th>Sale Info</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($profitResults as $row): ?>
          <tr>
            <td><?= htmlspecialchars($row['Name']) ?></td>
            <td><?= $row['year'] ?></td>
            <td><?= $row['month'] ?></td>
            <td><?= $row['quantity_sold'] ?></td>
            <td><?= number_format($row['total_cost'], 2) ?></td>
            <td><?= number_format($row['total_revenue'], 2) ?></td>
            <td><?= number_format($row['total_profit'], 2) ?></td>
            <td>
              <?php if (!empty($row['sale_breakdown']) && is_array($row['sale_breakdown'])): ?>
                <?php foreach ($row['sale_breakdown'] as $price => $qty): ?>
                  <?php if ($price === 'Regular'): ?>
                    <?= $qty ?> units @ Regular Price (₪<?= number_format($row['regular_price'], 2) ?>)<br>
                  <?php else: ?>
                    <?= $qty ?> units @ ₪<?= $price ?><br>
                  <?php endif; ?>
                <?php endforeach; ?>
              <?php else: ?>
                —
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php elseif (!empty($where)): ?>
  <p>No results found for selected filters.</p>
<?php endif; ?>

</div></div>
<?php include '../footer/footer.php'; ?>

<script>
  const table = document.querySelector('.table-premium');
  if (table) {
    table.querySelectorAll('tbody tr').forEach(row => {
      const cell = row.cells[6]; // 7th column
      if (!cell) return;
      const num = parseFloat(cell.textContent.replace(/[^\d\-.]/g, ''));
      if (isNaN(num)) return;
      cell.classList.add(num > 0 ? 'profit-positive' : (num < 0 ? 'profit-negative' : 'profit-zero'));
    });
  }
</script>

<script>
/* === Table sorting (additive) ========================================= */
(function () {
  const SORT_TYPES = ['text','num','num','num','num','num','num']; // by column index 0..6
  const ARROW_UP = ' ▲';
  const ARROW_DOWN = ' ▼';

  // Strip to numeric (keeps minus & dot), else NaN
  function toNumber(txt) {
    const n = parseFloat(String(txt).replace(/[^\d\-.]/g, ''));
    return isNaN(n) ? 0 : n;
  }
  function compare(type, a, b) {
    if (type === 'num') return toNumber(a) - toNumber(b);
    // text: case-insensitive
    return String(a).toLowerCase().localeCompare(String(b).toLowerCase(), undefined, {numeric:true});
  }

  document.querySelectorAll('.table-premium').forEach((table) => {
    const thead = table.tHead;
    const tbody = table.tBodies[0];
    if (!thead || !tbody) return;

    const ths = Array.from(thead.rows[0].cells);
    let activeIndex = -1;
    let activeDir = null; // 'asc' | 'desc'

    // Set cursor and title on sortable headers (0..6)
    ths.forEach((th, idx) => {
      if (idx <= 6) {
        th.style.cursor = 'pointer';
        th.title = (SORT_TYPES[idx] === 'text')
          ? 'Click to sort A→Z (toggle)'
          : 'Click to sort largest→smallest (toggle)';
      }
    });

    function clearArrows() {
      ths.forEach((th, idx) => {
        if (idx <= 6) {
          th.textContent = th.textContent.replace(/\s[▲▼]$/, '');
        }
      });
    }

    function sortBy(idx) {
      const type = SORT_TYPES[idx] || 'text';
      let dir;

      // First click default: text=asc, num=desc (newest/biggest first)
      if (activeIndex !== idx) {
        dir = (type === 'text') ? 'asc' : 'desc';
      } else {
        dir = (activeDir === 'asc') ? 'desc' : 'asc';
      }

      const rows = Array.from(tbody.rows);
      rows.sort((r1, r2) => {
        const a = r1.cells[idx]?.textContent || '';
        const b = r2.cells[idx]?.textContent || '';
        const c = compare(type, a, b);
        return (dir === 'asc') ? c : -c;
      });

      // Re-attach rows in new order
      const frag = document.createDocumentFragment();
      rows.forEach(r => frag.appendChild(r));
      tbody.appendChild(frag);

      // Update header arrows (single active)
      clearArrows();
      const base = ths[idx].textContent.replace(/\s[▲▼]$/, '');
      ths[idx].textContent = base + (dir === 'asc' ? ARROW_UP : ARROW_DOWN);

      activeIndex = idx;
      activeDir = dir;
    }

    ths.forEach((th, idx) => {
      if (idx <= 6) {
        th.addEventListener('click', () => sortBy(idx));
      }
    });
  });
})();
</script>

</body>
</html>
