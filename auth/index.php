<?php
require_once '../sessions.php';
require_role([ 'admin', 'guest', 'customer', 'worker','owner']);
$loginBg = 'cover/login/cover28.jpg';


date_default_timezone_set('Asia/Jerusalem');

// Include the database connection file
require_once '../db.php';

// Check if the user is already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: ../home.php'); // Redirect to the home page if already logged in
    exit();
}

$error = '';


if (isset($_GET['msg']) && $_GET['msg'] === 'login_required') {
    echo '<p style="color: red; text-align: center;">Please log in to add products to your cart.</p>';
}

if (isset($_POST['login'])) {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

$query = "SELECT * FROM users WHERE (username = ? OR email = ?) AND is_verified = 1";
$stmt = $conn->prepare($query);
$stmt->bind_param("ss", $username, $username); // Bind username as both username and email
$stmt->execute();
$result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $row = $result->fetch_assoc();

        // Check plain-text password directly (since no hashing is used)
        if ($password == $row['password']) {
            // Set session variables for the logged-in user
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['username'] = $username;
            $_SESSION['user_type'] = $row['usertype']; // Admin or worker user

            // Update last login date and time
            $current_date_time = date('Y-m-d H:i:s');  // Current date and time in MySQL DATETIME format
            $update_login_time = "UPDATE users SET last_login = ? WHERE id = ?";
            $stmt_update = $conn->prepare($update_login_time);
            $stmt_update->bind_param('si', $current_date_time, $row['id']);
            $stmt_update->execute();

            // Redirect based on user type
            if ($row['usertype'] == 'admin') {
                header('Location: ../add.php'); // Redirect to admin page
            }
            if ($row['usertype'] == 'owner') {
                header('Location: ../owner/statics.php');
            }
            else {
                header('Location: ../home.php'); // Redirect to home page
            }
            exit();
        } else {
            $error = "Invalid Username or Password!";
        }
    } else {
        $error = "User not found!";
    }
    $stmt->close();
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link rel="icon" href="/Merkaza-Almost-Done/public_html/merkaza.jpeg" type="image/jpeg">
    <link rel="stylesheet" href="auth.css">
    <link rel="stylesheet" type="text/css" href="index.css">
    <?php if (!empty($loginNext)): ?>
    <link rel="preload" as="image" href="<?= htmlspecialchars($loginNext) ?>">
    <?php endif; ?>
</head>
 <body class="with-cover  "
      style="--cover-image: url('<?= htmlspecialchars($loginBg) ?>');">



  <div class="wrapper d-flex flex-column min-vh-100">

    <?php include('../navbar.php'); ?>
    <?php include('../header.php'); ?>

    <!-- ✅ Main content area -->
    <div class="container1 flex-grow-1 d-flex flex-column justify-content-center align-items-center surface-none add-shell">
        <form method="POST" action="">
            <h2>Login</h2>
            <input type="text" name="username" placeholder="Username" required style="margin-bottom: 10px;">
            <input type="password" name="password" placeholder="Password" required style="margin-bottom: 10px;">
            <button type="submit" name="login" style="margin-bottom: 10px;">Login</button>
            <p><?php if (isset($error)) { echo $error; } ?></p>
            <a href="forgot_password.php">Forgot Password?</a><br>
            <a href="signup.php">Sign Up</a>
        </form>
    </div>

    <?php include '../footer/footer.php'; ?>
  </div>

</body>
</html>

