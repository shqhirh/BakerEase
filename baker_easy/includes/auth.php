<?php
// auth.php - Session-based authentication check

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

/**
 * Checks if the user is authenticated as an admin.
 * If not, redirects to the login page.
 */
function check_auth() {
    if (!isset($_SESSION['admin_id'])) {
        header("Location: login.php");
        exit;
    }
}

/**
 * Checks if the user is already logged in, redirecting to dashboard.
 */
function check_logged_in() {
    if (isset($_SESSION['admin_id'])) {
        header("Location: dashboard.php");
        exit;
    }
}
?>
