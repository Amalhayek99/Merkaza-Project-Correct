<?php
require_once '../sessions.php';

include('../db.php');
$loginBg = 'cover/cover37.jpg';

// Ensure the user is logged in
if (!isset($_SESSION['username'])) {
    header('location: index.php');
    exit();
}

$error = '';
$success = '';
$user = $_SESSION['username'];

// Handle password change form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // Fetch the current password from the database
    $query = "SELECT password FROM users WHERE username = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('s', $user);
    $stmt->execute();
    $stmt->bind_result($db_password);
    $stmt->fetch();
    $stmt->close();

    // Verify current password
    if ($current_password === $db_password) {
        // Check if the new password and confirm password match
        if ($new_password === $confirm_password) {
            // Update the password in the database
            $update_query = "UPDATE users SET password = ? WHERE username = ?";
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->bind_param('ss', $new_password, $user);

            if ($update_stmt->execute()) {
                $success = "Password updated successfully!";
            } else {
                $error = "Error updating password: " . $update_stmt->error;
            }

            $update_stmt->close();
        } else {
            $error = "New password and confirm password do not match!";
        }
    } else {
        $error = "Current password is incorrect!";
    }
}


?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Settings</title>
    <link rel="icon" href="merkaza.jpeg" type="image/jpeg">
    <link rel="stylesheet" href="auth.css">
    <link rel="stylesheet" type="text/css" href="settings.css">
    <link rel="icon" href="/Merkaza-Almost-Done/public_html/merkaza.jpeg" type="image/jpeg">


</head>
<body class="with-cover  "
      style="--cover-image: url('<?= htmlspecialchars($loginBg) ?>');">
      
    <div class="wrapper d-flex flex-column min-vh-100">

    <?php include('../header.php'); ?>
    <?php include('../navbar.php'); ?>
<div class="container surface-none flex-grow-1 py-4">
        <h2>Settings</h2>

        <!-- Display error or success messages -->
        <?php if ($error): ?>
            <p style="color: red;"><?php echo $error; ?></p>
        <?php endif; ?>

        <?php if ($success): ?>
            <p style="color: green;"><?php echo $success; ?></p>
        <?php endif; ?>

        <!-- Form to change password -->
        <form method="POST" action="settings.php">
            <div class="form-group">
                <label for="current_password">Current Password:</label>
                <input type="password" name="current_password" id="current_password" required>
            </div>

            <div class="form-group">
                <label for="new_password">New Password:</label>
                <input type="password" name="new_password" id="new_password" required>
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm New Password:</label>
                <input type="password" name="confirm_password" id="confirm_password" required>
            </div>

            <button type="submit" name="update_password">Update Password</button>
        </form>


    </div>
            <?php include '../footer/footer.php'; ?>

    </div>

</body>
</html>
