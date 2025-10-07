<?php
session_start();

// Uničimo vse sejne spremenljivke
$_SESSION = array();

// Uničimo sejo
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();

// Preusmerimo na prijavno stran
header('Location: login.php');
exit();
?>