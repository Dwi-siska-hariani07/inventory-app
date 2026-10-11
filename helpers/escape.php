<?php
// helpers/escape.php
// Fungsi sanitasi output encoding untuk mencegah serangan XSS

if (!function_exists('e')) {
    function e($value) {
        if ($value === null) {
            return '';
        }
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('format_rupiah')) {
    function format_rupiah($nominal) {
        return 'Rp ' . number_format((float)$nominal, 0, ',', '.');
    }
}
