<?php
// config/database.php
// Konfigurasi koneksi database PDO dengan Error Mode Exception

$host = '127.0.0.1'; // atau 'localhost'
$db   = 'inventory_db';
$user = 'root';
$pass = ''; // Sesuaikan jika MySQL XAMPP Anda memiliki password
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    // Tampilan pesan error yang informatif jika database belum dibuat
    die("Koneksi Database Gagal: " . $e->getMessage() . "<br><br><strong>Petunjuk:</strong> Pastikan MySQL di XAMPP sudah berjalan dan file <code>database/schema.sql</code> sudah di-import.");
}
