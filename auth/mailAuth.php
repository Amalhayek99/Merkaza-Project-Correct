<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/PHPMailer.php';
require 'PHPMailer/SMTP.php';
require 'PHPMailer/Exception.php';
require '../db.php';
require_once '../sessions.php';

// Expect data from session (set by signup.php)
if (empty($_SESSION['signup'])) {
    header('Location: signup.php?error=' . urlencode('Please fill the signup form first.'));
    exit;
}

$username    = trim($_SESSION['signup']['username']);
$password    = trim($_SESSION['signup']['password']);
$phone       = trim($_SESSION['signup']['phone']);
$email       = trim($_SESSION['signup']['email']);
$signup_date = $_SESSION['signup']['date'] ?? date('Y-m-d');
$usertype    = 'customer';

// OPTIONAL: hash password here if you want it stored hashed
// $password = password_hash($password, PASSWORD_BCRYPT);

// Generate 6-digit verification code
$verification_code = random_int(100000, 999999);
$sent_time = date('Y-m-d H:i:s');

// Re-check email just in case (safety)
$check = $conn->prepare("SELECT id FROM users WHERE email = ?");
$check->bind_param("s", $email);
$check->execute();
$check->store_result();
if ($check->num_rows > 0) {
    $check->close();
    // show error back on signup page
    header('Location: signup.php?error=' . urlencode('This email is already registered.'));
    exit;
}
$check->close();

// Insert user with is_verified = 0
$insert = $conn->prepare("INSERT INTO users (username, phone, password, email, usertype, signup_date, verification_code, is_verified, verification_sent_at)
                          VALUES (?, ?, ?, ?, ?, ?, ?, 0, ?)");
$insert->bind_param("ssssssss", $username, $phone, $password, $email, $usertype, $signup_date, $verification_code, $sent_time);
$insert->execute();
$insert->close();

// Send the verification email
$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'amalhayek10@gmail.com';
    $mail->Password   = 'buet ybii ogil domj'; // Gmail App Password
    $mail->SMTPSecure = 'tls';
    $mail->Port       = 587;

    $mail->setFrom('your-email@gmail.com', 'Merkaza App');
    $mail->addAddress($email, $username);
    $mail->Subject = 'Your Verification Code';
    $mail->Body    = "Hello $username,\n\nYour email verification code is: $verification_code\n\nPlease enter it to verify your account.";

    $mail->send();

    // pass email to verification page
    $_SESSION['pending_email'] = $email;
    // Clear signup cache
    unset($_SESSION['signup']);

    header("Location: verify.php");
    exit();

} catch (Exception $e) {
    // On mail error, roll back user or at least inform and let them retry
    header('Location: signup.php?error=' . urlencode('Could not send verification email. Please try again.'));
    exit;
}
