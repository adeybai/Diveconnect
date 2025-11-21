<?php
// user/logout.php
session_start();

// Clear all session data
$_SESSION = [];
session_unset();
session_destroy();

// Optional: destroy session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Redirect to landing or login
header("Location: ../index.php");
exit;
