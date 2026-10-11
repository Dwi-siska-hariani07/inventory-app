<?php
// public/stock/out.php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/escape.php';
require_once __DIR__ . '/../../helpers/validation.php';

require_login();

$page_title = 'Pencatatan Barang Keluar (OUT)';

// Ambil list produk untuk dropdown
$products = $pdo->query("SELECT id, sku, name, stock FROM products ORDER BY name ASC")->fetchAll();

$selected_product_id = filter_input(INPUT_GET, 'product_id', FILTER_VALIDATE_INT);
$errors = array();
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_id = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
    $quantity   = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT);
    $notes      = isset($_POST['notes']) ? trim($_POST['notes']) : '';

    if (!$product_id) {
        $errors[] = 'Silakan pilih produk yang valid.';
    }
    if ($quantity === false || $quantity <= 0) {
        $errors[] = 'Jumlah (quantity) barang keluar harus berupa angka positif lebih dari nol.';
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("SELECT id, name, stock FROM products WHERE id = :id FOR UPDATE");
            $stmt->execute(array('id' => $product_id));
            $prod = $stmt->fetch();

            if (!$prod) {
                throw new Exception("Produk tidak ditemukan di database.");
            }

            if ($quantity > $prod['stock']) {
                throw new Exception("Stok tidak mencukupi! Anda meminta $quantity unit, tetapi stok yang tersedia hanya tersisa {$prod['stock']} unit. Aturan bisnis: stok tidak boleh menjadi negatif.");
            }

            $update_stmt = $pdo->prepare("UPDATE products SET stock = stock - :qty WHERE id = :id");
            $update_stmt->execute(array(
                'qty' => $quantity,
                'id'  => $product_id
            ));

            $hist_stmt = $pdo->prepare("
                INSERT INTO stock_movements (product_id, user_id, type, quantity, notes)
                VALUES (:pid, :uid, 'OUT', :qty, :notes)
            ");
            $currUser = current_user();
            $hist_stmt->execute(array(
                'pid'   => $product_id,
                'uid'   => $currUser['id'],
                'qty'   => $quantity,
                'notes' => $notes ? $notes : 'Pengeluaran barang dari gudang'
            ));

            $pdo->commit();
            $remaining = $prod['stock'] - $quantity;
            $success = "Berhasil! Pengeluaran produk '{$prod['name']}' sebanyak $quantity unit telah dicatat. Sisa stok saat ini: $remaining unit.";

            $products = $pdo->query("SELECT id, sku, name, stock FROM products ORDER BY name ASC")->fetchAll();

        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = "Transaksi dibatalkan (Rollback): " . $e->getMessage();
        }
    }
}

include __DIR__ . '/../../views/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-danger text-white py-3">
                <h5 class="fw-bold mb-0"><i class="bi bi-box-arrow-up-right me-2"></i> Transaksi Barang Keluar (OUT)</h5>
                <small>Business Logic 10: SELECT FOR UPDATE, Validasi Stok Cukup, Pengurangan Stok, Commit/Rollback</small>
            </div>
            <div class="card-body p-4">

                <?php if (!empty($success)): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="bi bi-check-circle-fill me-1"></i> <?= e($success) ?>
                        <div class="mt-2">
                            <a href="/inventory-app/public/stock/history.php" class="alert-link">Lihat Riwayat Transaksi &rarr;</a>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger py-2">
                        <ul class="mb-0 ps-3">
                            <?php foreach ($errors as $err): ?>
                                <li><?= e($err) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Pilih Produk <span class="text-danger">*</span></label>
                        <select name="product_id" class="form-select" required>
                            <option value="">-- Pilih Produk yang Dikeluarkan --</option>
                            <?php foreach ($products as $p): ?>
                                <option value="<?= $p['id'] ?>" <?= (($selected_product_id == $p['id']) || ((isset($_POST['product_id']) ? $_POST['product_id'] : '') == $p['id'])) ? 'selected' : '' ?>>
                                    [<?= e($p['sku']) ?>] <?= e($p['name']) ?> (Tersedia: <?= number_format($p['stock']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Jumlah Keluar (Quantity) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="number" name="quantity" class="form-control" required min="1" placeholder="Jumlah unit yang dikeluarkan" value="<?= isset($_POST['quantity']) ? e($_POST['quantity']) : '' ?>">
                            <span class="input-group-text">Unit</span>
                        </div>
                        <small class="text-danger fw-medium">Aturan: Jumlah keluar tidak boleh melebihi stok yang tersedia.</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Tujuan Pengeluaran / Alasan</label>
                        <textarea name="notes" class="form-control" rows="3" placeholder="Contoh: Pengiriman ke Divisi Marketing / Penjualan Toko..."><?= isset($_POST['notes']) ? e($_POST['notes']) : '' ?></textarea>
                    </div>

                    <div class="alert alert-warning py-2 small mb-4">
                        <i class="bi bi-shield-lock me-1"></i>
                        <strong>Pertahanan Stok Negatif:</strong> Sistem mengeksekusi <code>SELECT FOR UPDATE</code> dan memeriksa batas sisa stok sebelum melakukan update tabel produk.
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <a href="/inventory-app/public/dashboard.php" class="btn btn-secondary">Batal</a>
                        <button type="submit" class="btn btn-danger px-4 fw-semibold">
                            <i class="bi bi-dash-circle me-1"></i> Simpan Transaksi Keluar
                        </button>
                    </div>
                </form>

            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../views/footer.php'; ?>
