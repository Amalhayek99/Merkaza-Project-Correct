<?php
require_once '../sessions.php';
require_once '../db.php';

if (!isset($_SESSION['reset_email'])) {
    header("Location: forgot_password.php");
    exit;
}

$error = '';
$success = '';

if (isset($_POST['reset_password'])) {
    $pass1 = $_POST['password'];
    $pass2 = $_POST['confirm_password'];

    if ($pass1 !== $pass2) {
        $error = "Passwords do not match.";
    } else {
        // Save password as plain text (⚠️ NOT secure)
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
        $stmt->bind_param("ss", $pass1, $_SESSION['reset_email']);
        $stmt->execute();

        // Clear session
        unset($_SESSION['reset_email']);
        unset($_SESSION['reset_code']);

        // Redirect to login
        header("Location: index.php?msg=password_reset");
        exit;
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Set New Password</title>
    <link rel="icon" href="/Merkaza-Almost-Done/public_html/merkaza.jpeg" type="image/jpeg">


</head>
<body>
    <?php include('../navbar.php'); ?>
    <?php include '../header.php'; ?>
    <h2>Set New Password</h2>

    <?php if ($error): ?>
        <p style="color:red;"><?= $error ?></p>
    <?php endif; ?>

    <form method="POST">
        <label>New Password:</label><br>
        <input type="password" name="password" required><br><br>

        <label>Confirm Password:</label><br>
        <input type="password" name="confirm_password" required><br><br>

        <button type="submit" name="reset_password">Save Password</button>
    </form>
</body>
</html>
