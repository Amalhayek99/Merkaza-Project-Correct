<?php
require_once '../sessions.php';
require_once '../db.php';

$status_msg  = '';
$status_type = ''; // success | warning | danger

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $code  = trim($_POST['code'] ?? '');

    if ($email === '' || $code === '') {
        $status_msg  = 'Please enter your email and the verification code.';
        $status_type = 'warning';
    } else {
        $stmt = $conn->prepare("SELECT id, email, is_verified FROM users WHERE email = ? AND verification_code = ?");
        $stmt->bind_param("ss", $email, $code);
        $stmt->execute();
        $result = $stmt->get_result();
        $user   = $result->fetch_assoc();

        if ($user) {
            if ((int)$user['is_verified'] === 1) {
                $status_msg  = 'Email is already verified.';
                $status_type = 'success';
            } else {
                // mark verified; also clear the code and set timestamp if you have such columns
                $upd = $conn->prepare("UPDATE users SET is_verified = 1, verification_code = NULL WHERE id = ?");
                $upd->bind_param("i", $user['id']);
                if ($upd->execute()) {
                    $status_msg  = 'Your email has been successfully verified.';
                    $status_type = 'success';
                } else {
                    $status_msg  = 'Something went wrong while verifying. Please try again.';
                    $status_type = 'danger';
                }
                $upd->close();
            }
        } else {
            $status_msg  = 'Invalid verification code or email.';
            $status_type = 'danger';
        }

        $stmt->close();
    }
}

// optional: prefill email via query ?email=
$prefill_email = htmlspecialchars($_GET['email'] ?? '', ENT_QUOTES);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Verify Email</title>
    <link rel="icon" href="../merkaza.jpeg" type="image/jpeg">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Global styles (your shared file) -->
    <link rel="stylesheet" href="verify.css">
    <link rel="stylesheet" href="auth.css">
    <link rel="icon" href="/Merkaza-Almost-Done/public_html/merkaza.jpeg" type="image/jpeg">

</head>
<body class="auth-bg">
    <div class="auth-topbar">
    </div>

    <div class="auth-wrap">
        <div class="card auth-card">
            <div class="card-header">
                <div class="brand-mini">M</div>
                <h2 class="auth-title mt-3 mb-1">Verify your email</h2>
                <div class="auth-sub">Enter the code we sent to your inbox</div>
            </div>

            <?php if ($status_msg): ?>
                <div class="result-box">
                    <div class="alert alert-<?php echo $status_type ?: 'info'; ?> mb-0" role="alert">
                        <?php echo htmlspecialchars($status_msg, ENT_QUOTES); ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="card-body">
                <form method="POST" novalidate>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input
                            type="email"
                            name="email"
                            class="form-control"
                            placeholder="you@example.com"
                            value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email'], ENT_QUOTES) : $prefill_email; ?>"
                            required>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Verification Code</label>
                        <input
                            type="text"
                            name="code"
                            class="form-control"
                            inputmode="numeric"
                            autocomplete="one-time-code"
                            placeholder="6-digit code"
                            value="<?php echo htmlspecialchars($_POST['code'] ?? '', ENT_QUOTES); ?>"
                            required>
                    </div>

                    <button type="submit" class="btn  w-100 btn-auth">Verify</button>
                </form>
            </div>

            <div class="card-footer">
                <div class="text-muted">Didn’t receive a code? Check spam or request a new one on the sign up page.</div>
                <div class="mt-3">
                    <a href="index.php">Back to Login</a>
                </div>
            </div>
        </div>

        <p class="text-center mt-3 mb-0" style="opacity:.75;font-size:.9rem;">
            Having trouble? Contact support.
        </p>
    </div>
</body>
</html>
