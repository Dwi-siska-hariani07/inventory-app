<?php
// public/logout.php
require_once __DIR__ . '/../helpers/auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$_SESSION = [];

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();
header("Location: /inventory-app/public/login.php?msg=" . urlencode("Anda telah berhasil keluar dari sistem"));
exit;
