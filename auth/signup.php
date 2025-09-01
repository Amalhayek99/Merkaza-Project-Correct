<?php
require_once '../sessions.php';
require_once '../db.php';

$loginBg = 'cover/login/cover26.jpg';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $signup_date = date('Y-m-d');

    // basic required checks
    if ($username === '' || $phone === '' || $email === '' || $password === '') {
        $error = 'All fields are required.';
    } else {
        // check username
        $stmt = $conn->prepare("SELECT 1 FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $error = "Username is already taken, please choose a different one.";
        }
        $stmt->close();

        // check email (only if username is ok)
        if ($error === '') {
            $stmt = $conn->prepare("SELECT 1 FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows > 0) {
                $error = "This email is already registered.";
            }
            $stmt->close();
        }
    }

    // If all good, pass to mailAuth via session and redirect
    if ($error === '') {
        $_SESSION['signup'] = [
            'username' => $username,
            'phone'    => $phone,
            'email'    => $email,
            'password' => $password,     // (optional: hash it before mailAuth/insert)
            'date'     => $signup_date,
        ];
        header('Location: mailAuth.php');
        exit;
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sign Up</title>
  <link rel="icon" href="/Merkaza-Almost-Done/public_html/merkaza.jpeg" type="image/jpeg">
  <link rel="stylesheet" href="auth.css">
  <link rel="stylesheet" type="text/css" href="signup.css">

</head>
<body class="with-cover"
      style="--cover-image: url('<?= htmlspecialchars($loginBg) ?>');">

  <?php include '../header.php'; ?>
  <?php include '../navbar.php'; ?>

  <div class="container surface-none add-shell" style="max-width:640px; margin-top:32px;">
    <div class="card shadow-sm" style="border-radius:18px;">
      <div class="card-body p-4">
        <h2 class="text-center mb-3">Sign Up</h2>

        <?php if (!empty($error)): ?>
          <div class="alert alert-danger" role="alert"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if (!empty($_GET['error'])): ?>
          <div class="alert alert-danger" role="alert"><?= htmlspecialchars($_GET['error']) ?></div>
        <?php endif; ?>

        <form action="signup.php" method="POST" novalidate>
          <input class="form-control mb-3" type="text"     name="username" placeholder="Username" required>
          <input class="form-control mb-3" type="text"     name="phone"    placeholder="Phone" required>
          <input class="form-control mb-3" type="email"    name="email"    placeholder="Email" required>
          <input class="form-control mb-3" type="password" name="password" placeholder="Password" required>
          <button class="btn  w-100" type="submit" name="signup">Sign Up</button>
        </form>

        <p class="mt-3 text-center">Already have an account? <a href="index.php">Login here</a></p>
      </div>
    </div>
  </div>
</body>
</html>
