<?php
if (session_status() === PHP_SESSION_NONE) {
    require_once 'sessions.php';
}
$base = "/Merkaza-Almost-Done/public_html/";
$userType = $_SESSION['user_type'] ?? 'guest';

// Figure out display name
$displayName = '';
if (!empty($_SESSION['username'])) {
    $displayName = $_SESSION['username'];
} elseif (!empty($_SESSION['name'])) {
    $displayName = $_SESSION['name'];
}
?>

<!-- Bootstrap Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark bg-dark px-3">
<?php
$redirectLink = $base . 'welcome.php'; // default for guest

if (in_array($userType, ['customer', 'worker'])) {
    $redirectLink = $base . 'home.php';
} elseif ($userType === 'owner') {
    $redirectLink = $base . 'owner/statics.php'; // or 'admin/statics.php' if it's there
}
?>

<a class="navbar-brand fw-bold" href="<?= $redirectLink ?>">Merkaza</a>
  <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar">
    <span class="navbar-toggler-icon"></span>
  </button>

  <div class="collapse navbar-collapse" id="mainNavbar">
    <ul class="navbar-nav me-auto mb-2 mb-lg-0">
      <?php if ($userType === 'guest'): ?>
          <li class="nav-item"><a class="nav-link" href="<?= $base ?>welcome.php">Home</a></li>
          <li class="nav-item"><a class="nav-link" href="<?= $base ?>products.php">Products</a></li>
          <li class="nav-item"><a class="nav-link" href="<?= $base ?>auth/index.php">Login</a></li>
      <?php endif; ?>

      <?php if (in_array($userType, ['customer', 'worker', 'admin'])): ?>
          <li class="nav-item"><a class="nav-link" href="<?= $base ?>home.php">Home</a></li>
          <li class="nav-item"><a class="nav-link" href="<?= $base ?>products.php">Products</a></li>
      <?php endif; ?>

      <?php if (in_array($userType, ['worker', 'admin'])): ?>
          <li class="nav-item"><a class="nav-link" href="<?= $base ?>admin/add.php">Add</a></li>
      <?php endif; ?>

      <?php if ($userType === 'admin'): ?>
          <li class="nav-item"><a class="nav-link" href="<?= $base ?>admin/productAdmit.php">Product Admit</a></li>
          <li class="nav-item"><a class="nav-link" href="<?= $base ?>admin/userAdmit.php">User Admit</a></li>
          <li class="nav-item"><a class="nav-link" href="<?= $base ?>admin/sales.php">Sales</a></li>
          <li class="nav-item"><a class="nav-link" href="<?= $base ?>owner/passedorders.php">Ordered</a></li>
      <?php endif; ?>

      <?php if ($userType === 'owner'): ?>
          <li class="nav-item"><a class="nav-link" href="<?= $base ?>owner/statics.php">Statics</a></li>
          <li class="nav-item"><a class="nav-link" href="<?= $base ?>owner/currentproducts.php">Current Quantity</a></li>
          <li class="nav-item"><a class="nav-link" href="<?= $base ?>owner/orders.php">Active Orders</a></li>
          <li class="nav-item"><a class="nav-link" href="<?= $base ?>owner/passedorders.php">Ordered</a></li>
      <?php endif; ?>

      <?php if ($userType === 'customer'): ?>
          <li class="nav-item"><a class="nav-link" href="<?= $base ?>cart.php">Cart</a></li>
      <?php endif; ?>
            <?php if ($userType !== 'guest'): ?>
        <li class="nav-item"><a class="nav-link" href="<?= $base ?>auth/settings.php">Settings</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= $base ?>auth/logout.php">Logout</a></li>
      <?php endif; ?>
    </ul>

    <!-- Right-side greeting + logout -->
    <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
      <?php if ($userType !== 'guest' && !empty($displayName)): ?>
        <li class="nav-item d-flex align-items-center text-white me-3">
          Hello, <?= htmlspecialchars($displayName) ?>
        </li>
      <?php endif; ?>


    </ul>
  </div>
</nav>
