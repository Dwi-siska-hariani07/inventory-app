<?php
// helpers/auth.php
// Helper otentikasi session dan otorisasi role

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Cek apakah user sudah login
 */
function is_logged_in() {
    return isset($_SESSION['user']) && !empty($_SESSION['user']['id']);
}

/**
 * Ambil data user yang sedang login
 */
function current_user() {
    return isset($_SESSION['user']) ? $_SESSION['user'] : null;
}

/**
 * Wajibkan user login, jika belum redirect ke login
 */
function require_login() {
    if (!is_logged_in()) {
        header("Location: /inventory-app/public/login.php?msg=" . urlencode("Silakan login terlebih dahulu"));
        exit;
    }
}

/**
 * Otorisasi role: batasi akses fitur hanya untuk role tertentu (misal: 'admin')
 * Sesuai slide 24: http_response_code(403); exit('Forbidden');
 */
function require_role($role) {
    require_login();
    if ($_SESSION['user']['role'] !== $role) {
        http_response_code(403);
        include __DIR__ . '/../views/403.php';
        exit;
    }
}

/**
 * Cek apakah user memiliki role tertentu (untuk conditional rendering UI)
 */
function has_role($role) {
    return is_logged_in() && $_SESSION['user']['role'] === $role;
}
