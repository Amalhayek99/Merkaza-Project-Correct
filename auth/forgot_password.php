<?php
require_once '../sessions.php';
require_once '../db.php';
$loginBg = 'cover/login/cover27.jpg';

// Clear reset session if page was reloaded directly (GET request)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    unset($_SESSION['reset_email']);
    unset($_SESSION['reset_code']);
}

// ✅ include PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/PHPMailer.php';
require 'PHPMailer/SMTP.php';
require 'PHPMailer/Exception.php';

$error = '';
$success = '';

// Handle sending code
if (isset($_POST['send_code'])) {
    $email = trim($_POST['email']);

    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $code = rand(100000, 999999);
        $_SESSION['reset_email'] = $email;
        $_SESSION['reset_code'] = $code;

        // ✅ Send email using PHPMailer
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'amalhayek10@gmail.com'; // your email
            $mail->Password   = 'buet ybii ogil domj';    // Gmail App Password
            $mail->SMTPSecure = 'tls';
            $mail->Port       = 587;

            $mail->setFrom('your_email@gmail.com', 'YourAppName');
            $mail->addAddress($email);
            $mail->Subject = 'Password Reset Code';
            $mail->Body    = "Your verification code is: $code";

            $mail->send();
            $success = "Code has been sent to your email.";
        } catch (Exception $e) {
            $error = "Mailer Error: {$mail->ErrorInfo}";
        }
    } else {
        $error = "This email is not registered.";
    }
}

// Handle code verification
if (isset($_POST['submit_code'])) {
    $userCode = trim($_POST['code']);
    if ($userCode == $_SESSION['reset_code']) {
    // Flip is_verified to 1 if needed
    $verifiedEmail = $_SESSION['reset_email'] ?? '';

    if (!empty($verifiedEmail)) {
        $verifyStmt = $conn->prepare("UPDATE users SET is_verified = 1 WHERE email = ? AND is_verified = 0");
        $verifyStmt->bind_param("s", $verifiedEmail);
        $verifyStmt->execute();
        $verifyStmt->close();
    }

    header("Location: passReset.php");
    exit;
}
 else {
        $error = "Invalid code. Please try again.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Forgot Password</title>
    <link rel="icon" href="/Merkaza-Almost-Done/public_html/merkaza.jpeg" type="image/jpeg">


</head>
 <body class="with-cover  "
      style="--cover-image: url('<?= htmlspecialchars($loginBg) ?>');">
    <?php include '../header.php'; ?>
    <?php include '../navbar.php'; ?>

    <h2>Reset Password</h2>

    <?php if ($error): ?>
        <p style="color:red;"><?= $error ?></p>
    <?php endif; ?>

    <?php if ($success): ?>
        <p style="color:green;"><?= $success ?></p>
    <?php endif; ?>

    <form method="POST">
        <label>Email:</label><br>
        <input type="email" name="email" value="<?= isset($_SESSION['reset_email']) ? $_SESSION['reset_email'] : '' ?>" required <?= isset($_SESSION['reset_email']) ? 'readonly' : '' ?>><br><br>

        <?php if (!isset($_SESSION['reset_email'])): ?>
            <button type="submit" name="send_code">Send Code</button>
        <?php else: ?>
            <label>Code:</label><br>
            <input type="text" name="code" placeholder="Code" required><br><br>
            <button type="submit" name="submit_code">Submit Code</button>
        <?php endif; ?>
    </form>
</body>
</html>
