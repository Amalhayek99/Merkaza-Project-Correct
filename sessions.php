<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
session_start(); // is OK here

}

// Default to guest if not logged in
if (!isset($_SESSION['user_type'])) {
    $_SESSION['user_type'] = 'guest';
}

// Set type variable
$userType = $_SESSION['user_type'];

// Define flags for convenience
$isGuest    = ($userType === 'guest');
$isCustomer = ($userType === 'customer');
$isWorker   = ($userType === 'worker');
$isAdmin    = ($userType === 'admin');
$isOwner    = ($userType === 'owner');

// === Unified display name used across the site ===
$displayName = '';
if (!$isGuest) {
    // Prefer a single value set at login; fall back gracefully
    $displayName =
        $_SESSION['display_name'] ??
        $_SESSION['username'] ??
        $_SESSION['name'] ??
        trim(($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? ''));
    $displayName = trim($displayName);
}

// First name convenience (optional)
$firstName = $displayName !== '' ? explode(' ', $displayName)[0] : '';

// Define login status
$isLoggedIn = !$isGuest;


/**
 * Helper: require user to be logged in
 */
function require_login() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: ..public_html//auth/index.php?msg=login_required");
        exit();
    }
}

/**
 * Helper: restrict access by user type
 * @param array $allowed_roles - e.g., ['admin', 'owner']
 */
function require_role($allowed_roles = []) {
    if (!isset($_SESSION['user_type']) || !in_array($_SESSION['user_type'], $allowed_roles)) {
        header("Location: ../public_html/auth/index.php?msg=no_permission");
        exit();
    }
}
?>
