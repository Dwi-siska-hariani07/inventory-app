<?php
// public/stock/in.php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/escape.php';
require_once __DIR__ . '/../../helpers/validation.php';

require_login();

$page_title = 'Pencatatan Barang Masuk (IN)';

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
        $errors[] = 'Jumlah (quantity) barang masuk harus berupa angka positif lebih dari nol.';
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

            $update_stmt = $pdo->prepare("UPDATE products SET stock = stock + :qty WHERE id = :id");
            $update_stmt->execute(array(
                'qty' => $quantity,
                'id'  => $product_id
            ));

            $hist_stmt = $pdo->prepare("
                INSERT INTO stock_movements (product_id, user_id, type, quantity, notes)
                VALUES (:pid, :uid, 'IN', :qty, :notes)
            ");
            $currUser = current_user();
            $hist_stmt->execute(array(
                'pid'   => $product_id,
                'uid'   => $currUser['id'],
                'qty'   => $quantity,
                'notes' => $notes ? $notes : 'Penerimaan barang masuk ke gudang'
            ));

            $pdo->commit();
            $success = "Berhasil! Stok produk '{$prod['name']}' berhasil ditambah sebanyak $quantity unit. Total stok sekarang: " . ($prod['stock'] + $quantity) . " unit.";

            $products = $pdo->query("SELECT id, sku, name, stock FROM products ORDER BY name ASC")->fetchAll();

        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = "Transaksi gagal: " . $e->getMessage();
        }
    }
}

include __DIR__ . '/../../views/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-success text-white py-3">
                <h5 class="fw-bold mb-0"><i class="bi bi-box-arrow-in-down me-2"></i> Transaksi Barang Masuk (IN)</h5>
                <small>Business Logic 10: Validasi, Tambah Stok, Insert Histori, Database Transaction</small>
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
                            <option value="">-- Pilih Produk yang Masuk --</option>
                            <?php foreach ($products as $p): ?>
                                <option value="<?= $p['id'] ?>" <?= (($selected_product_id == $p['id']) || ((isset($_POST['product_id']) ? $_POST['product_id'] : '') == $p['id'])) ? 'selected' : '' ?>>
                                    [<?= e($p['sku']) ?>] <?= e($p['name']) ?> (Stok saat ini: <?= number_format($p['stock']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Jumlah Masuk (Quantity) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="number" name="quantity" class="form-control" required min="1" placeholder="Jumlah unit yang masuk" value="<?= isset($_POST['quantity']) ? e($_POST['quantity']) : '' ?>">
                            <span class="input-group-text">Unit</span>
                        </div>
                        <small class="text-muted">Harus bernilai bilangan bulat positif (&gt; 0)</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Catatan / No. Dokumen Pengadaan</label>
                        <textarea name="notes" class="form-control" rows="3" placeholder="Contoh: Surat Jalan #SJ-2026-001 dari supplier PT Megah..."><?= isset($_POST['notes']) ? e($_POST['notes']) : '' ?></textarea>
                    </div>

                    <div class="alert alert-info py-2 small mb-4">
                        <i class="bi bi-shield-check me-1"></i>
                        Transaksi ini diikat dengan <strong>PDO Transaction (Commit/Rollback)</strong>. Jika terjadi error di tengah jalan, stok tidak akan bertambah dan histori tidak akan tersimpan secara terpisah.
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <a href="/inventory-app/public/dashboard.php" class="btn btn-secondary">Batal</a>
                        <button type="submit" class="btn btn-success px-4 fw-semibold">
                            <i class="bi bi-save me-1"></i> Simpan Transaksi Masuk
                        </button>
                    </div>
                </form>

            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../views/footer.php'; ?>
