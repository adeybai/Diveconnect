<?php
session_start();

// Destroy all session data
session_unset();
session_destroy();

// Prevent page from being cached
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

// Redirect to login
header("Location: login_diver.php");
exit;
