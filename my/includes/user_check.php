<?php
/**
 * User Authentication Check
 *
 * This script verifies if a user is logged in by checking the session.
 * If the user is not logged in, it redirects them to the login page.
 *
 * This script should be included at the beginning of any page within the '/my/'
 * directory (or any user-restricted area) AFTER including the main 'init.php'
 * (which should define BASE_URL and potentially start the session).
 */

// Ensure session is started. It's often started in init.php,
// but checking here provides robustness.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if the user ID session variable is set and not empty.
// Adjust 'user_id' if you use a different session variable name for login status.
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {

    // Optional: Store the requested URL to redirect back after login
    // Note: Avoid storing potentially sensitive data from POST requests here.
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
         $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
    } else {
        // For POST requests, maybe redirect to a default page after login
        $_SESSION['redirect_url'] = BASE_URL . '/my/dashboard.php'; // Or '/my/index.php' etc.
    }


    // Set an error message to display on the login page
    $_SESSION['error'] = "Bu sayfayı görüntülemek için giriş yapmanız gerekmektedir.";

    // Redirect to the login page. Adjust '/login.php' if your login page has a different path.
    // Ensure BASE_URL is defined (usually in init.php).
    if (!defined('BASE_URL')) {
        // Fallback if BASE_URL is not defined (should not happen if init.php is included first)
        // You might want to log an error here instead of guessing the path.
        header("Location: /login.php");
        exit;
    } else {
        header("Location: " . BASE_URL . "/login.php"); // Redirect to the main login page
        exit;
    }
}

// If the script reaches this point, the user is considered logged in.
// The rest of the page that included this file will continue to execute.

?>