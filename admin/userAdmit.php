<?php
require_once '../sessions.php';
include('../db.php');
require_role(['admin']);
$loginBg = 'cover/cover43.jpg';

// Only admins can access
if (!isset($_SESSION['username']) || $_SESSION['user_type'] !== 'admin') {
    header('location: ../index.php');
    exit();
}

// Approve user (change usertype from customer to worker)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve'])) {
    $id = intval($_POST['id']);

    $stmt = $conn->prepare("UPDATE users SET usertype = 'worker' WHERE id = ? AND usertype = 'customer'");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
}

// Get all users who are pending approval (i.e., type = customer)
$result = $conn->query("SELECT * FROM users WHERE usertype = 'customer' ORDER BY signup_date DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Pending User Approvals</title>
    <link rel="stylesheet" href="admin.css">
    <link rel="stylesheet" href="userAdmit.css">


      <link rel="icon" href="/Merkaza-Almost-Done/public_html/merkaza.jpeg" type="image/jpeg">

</head>

<body class="with-cover  "
      style="--cover-image: url('<?= htmlspecialchars($loginBg) ?>');">
      <div class="wrapper d-flex flex-column min-vh-100">

    <?php include '../header.php'; ?>
    <?php include('../navbar.php'); ?>

    <div class="flex-grow-1">
        <!-- <div class="container"> -->
<div class="container surface-none">

            <h2>👤 Pending Customer Approvals</h2>

            <input type="text"
                   id="searchUserInput"
                   placeholder="Search by username or phone..."
                   class="search-bar" />

            <?php if ($result->num_rows > 0): ?>
                <div class="users-table-wrap">
                    <table class="users-table">
                        <thead>
                        <tr>
                            <th>ID</th>
                            <th>Username</th>
                            <th>Phone</th>
                            <th>Signup Date</th>
                            <th>Action</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php while ($user = $result->fetch_assoc()): ?>
                            <tr data-username="<?= strtolower($user['username']) ?>"
                                data-phone="<?= strtolower($user['phone']) ?>">
                                <td><?= $user['id'] ?></td>
                                <td><?= htmlspecialchars($user['username']) ?></td>
                                <td><?= htmlspecialchars($user['phone']) ?></td>
                                <td><?= date('d/m/Y', strtotime($user['signup_date'])) ?></td>
                                <td class="action-cell">
                                    <form method="POST"
                                          onsubmit="return confirm('Are you sure you want to approve this user?');"
                                          class="inline-form">
                                        <input type="hidden" name="id" value="<?= $user['id'] ?>">
                                        <button class="btn btn-approve" name="approve">Promote</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p style="text-align:center; color:var(--muted); margin-top:40px;">
                    No customers pending approval.
                </p>
            <?php endif; ?>

        </div>
    </div>

    <?php include '../footer/footer.php'; ?>
</div>

<script>
/* Filter by username/phone */
document.getElementById("searchUserInput").addEventListener("keyup", function () {
  const filter = this.value.toLowerCase();
  document.querySelectorAll(".users-table tbody tr").forEach(row => {
    const u = row.getAttribute("data-username") || "";
    const p = row.getAttribute("data-phone") || "";
    row.style.display = (u.includes(filter) || p.includes(filter)) ? "" : "none";
  });
});

/* Keyboard shortcuts: '/' focus, Esc clear */
(function () {
  const input = document.getElementById('searchUserInput');
  if (!input) return;
  document.addEventListener('keydown', (e) => {
    if ((e.key === '/' && document.activeElement !== input) ||
        (e.key === 'k' && (e.ctrlKey || e.metaKey))) {
      e.preventDefault();
      input.focus();
    }
    if (e.key === 'Escape' && document.activeElement === input) {
      input.value = '';
      input.dispatchEvent(new Event('keyup'));
    }
  });
})();
</script>

</body>
</html>
