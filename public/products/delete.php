<?php
// public/products/delete.php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/auth.php';

require_role('admin');

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    header("Location: /inventory-app/public/products/index.php");
    exit;
}

try {
    // Business Rule (Slide 33):
    // "Produk yang memiliki histori tidak boleh dihapus sembarangan."
    // Foreign Key ON DELETE RESTRICT dari stock_movements juga melindungi ini.
    
    // Cek apakah produk memiliki riwayat pergerakan stok
    $stmt = $pdo->prepare("SELECT COUNT(id) FROM stock_movements WHERE product_id = :id");
    $stmt->execute(['id' => $id]);
    $movement_count = (int)$stmt->fetchColumn();

    if ($movement_count > 0) {
        $error = "Gagal menghapus! Produk ini memiliki $movement_count riwayat transaksi stok. Aturan bisnis melarang penghapusan data master yang memiliki histori.";
        header("Location: /inventory-app/public/products/index.php?error=" . urlencode($error));
        exit;
    }

    // Jika tidak ada histori stok, hapus produk (junction product_suppliers otomatis CASCADE)
    $stmt = $pdo->prepare("DELETE FROM products WHERE id = :id");
    $stmt->execute(['id' => $id]);

    header("Location: /inventory-app/public/products/index.php?success=" . urlencode("Produk berhasil dihapus!"));
    exit;

} catch (PDOException $e) {
    // Tangkap error jika terkena batasan Foreign Key RESTRICT
    $error = "Terjadi pelanggaran integritas referensial (Foreign Key RESTRICT): " . $e->getMessage();
    header("Location: /inventory-app/public/products/index.php?error=" . urlencode($error));
    exit;
}
