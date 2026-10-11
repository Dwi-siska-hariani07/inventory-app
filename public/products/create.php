<?php
// public/products/create.php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/escape.php';
require_once __DIR__ . '/../../helpers/validation.php';

// Otorisasi khusus Admin
require_role('admin');

$page_title = 'Tambah Produk Baru';
$errors = array();

// Ambil list kategori dan supplier
$categories = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC")->fetchAll();
$suppliers = $pdo->query("SELECT id, name FROM suppliers ORDER BY name ASC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $category_id  = filter_input(INPUT_POST, 'category_id', FILTER_VALIDATE_INT);
    $sku          = isset($_POST['sku']) ? trim($_POST['sku']) : '';
    $name         = isset($_POST['name']) ? trim($_POST['name']) : '';
    $price        = isset($_POST['price']) ? $_POST['price'] : '';
    $stock        = isset($_POST['stock']) ? $_POST['stock'] : 0;
    $min_stock    = isset($_POST['minimum_stock']) ? $_POST['minimum_stock'] : 5;
    $supplier_ids = isset($_POST['supplier_ids']) ? $_POST['supplier_ids'] : array();

    if (!$category_id) {
        $errors[] = 'Silakan pilih kategori yang valid.';
    }
    if ($err = validate_required_string($sku, 'SKU Produk', 3, 50)) {
        $errors[] = $err;
    }
    if ($err = validate_required_string($name, 'Nama Produk', 3, 150)) {
        $errors[] = $err;
    }
    if ($err = validate_non_negative_number($price, 'Harga')) {
        $errors[] = $err;
    }
    if ($err = validate_non_negative_number($stock, 'Stok Awal')) {
        $errors[] = $err;
    }
    if ($err = validate_non_negative_number($min_stock, 'Minimum Stok')) {
        $errors[] = $err;
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT COUNT(id) FROM products WHERE sku = :sku");
        $stmt->execute(array('sku' => $sku));
        if ($stmt->fetchColumn() > 0) {
            $errors[] = "SKU '$sku' sudah digunakan. Harap gunakan kode SKU lain.";
        }
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                INSERT INTO products (category_id, sku, name, price, stock, minimum_stock)
                VALUES (:cat_id, :sku, :name, :price, :stock, :min_stock)
            ");
            $stmt->execute(array(
                'cat_id'    => $category_id,
                'sku'       => $sku,
                'name'      => $name,
                'price'     => (float)$price,
                'stock'     => (int)$stock,
                'min_stock' => (int)$min_stock
            ));
            $product_id = (int)$pdo->lastInsertId();

            if (!empty($supplier_ids) && is_array($supplier_ids)) {
                $ps_stmt = $pdo->prepare("
                    INSERT INTO product_suppliers (product_id, supplier_id, supplier_price)
                    VALUES (:pid, :sid, :sprice)
                ");
                foreach ($supplier_ids as $sid) {
                    $sid = (int)$sid;
                    if ($sid > 0) {
                        $ps_stmt->execute(array(
                            'pid'    => $product_id,
                            'sid'    => $sid,
                            'sprice' => (float)$price * 0.8
                        ));
                    }
                }
            }

            if ((int)$stock > 0) {
                $sm_stmt = $pdo->prepare("
                    INSERT INTO stock_movements (product_id, user_id, type, quantity, notes)
                    VALUES (:pid, :uid, 'IN', :qty, 'Saldo stok awal saat pembuatan master produk')
                ");
                $currentUser = current_user();
                $sm_stmt->execute(array(
                    'pid' => $product_id,
                    'uid' => $currentUser['id'],
                    'qty' => (int)$stock
                ));
            }

            $pdo->commit();
            header("Location: /inventory-app/public/products/index.php?success=" . urlencode("Produk baru berhasil ditambahkan!"));
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = "Gagal menyimpan produk: " . $e->getMessage();
        }
    }
}

include __DIR__ . '/../../views/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3">
                <h5 class="fw-bold mb-0"><i class="bi bi-plus-circle text-primary me-2"></i> Tambah Produk Baru</h5>
            </div>
            <div class="card-body p-4">

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
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">SKU (Kode Unik Produk) <span class="text-danger">*</span></label>
                            <input type="text" name="sku" class="form-control" required placeholder="Contoh: PRD-001" value="<?= isset($_POST['sku']) ? e($_POST['sku']) : '' ?>">
                            <small class="text-muted">Harus unik di seluruh database</small>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Kategori <span class="text-danger">*</span></label>
                            <select name="category_id" class="form-select" required>
                                <option value="">-- Pilih Kategori --</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>" <?= ((isset($_POST['category_id']) && $_POST['category_id'] == $cat['id']) ? 'selected' : '') ?>>
                                        <?= e($cat['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold small">Nama Produk <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" required placeholder="Nama lengkap produk..." value="<?= isset($_POST['name']) ? e($_POST['name']) : '' ?>">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">Harga Satuan (Rp) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" name="price" class="form-control" required min="0" placeholder="0" value="<?= isset($_POST['price']) ? e($_POST['price']) : '0' ?>">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">Stok Awal <span class="text-danger">*</span></label>
                            <input type="number" name="stock" class="form-control" required min="0" placeholder="0" value="<?= isset($_POST['stock']) ? e($_POST['stock']) : '0' ?>">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">Batas Minimum Stok <span class="text-danger">*</span></label>
                            <input type="number" name="minimum_stock" class="form-control" required min="1" placeholder="5" value="<?= isset($_POST['minimum_stock']) ? e($_POST['minimum_stock']) : '5' ?>">
                        </div>

                        <!-- Many to Many Supplier (Slide 8 & 32) -->
                        <div class="col-12 mt-3">
                            <label class="form-label fw-semibold small">Supplier Penyedia (Relasi Many-to-Many)</label>
                            <div class="card p-3 bg-light border-0">
                                <small class="text-muted mb-2 d-block">Pilih satu atau lebih supplier yang memasok produk ini:</small>
                                <div class="row g-2">
                                    <?php foreach ($suppliers as $sup): ?>
                                    <div class="col-md-6">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="supplier_ids[]" value="<?= $sup['id'] ?>" id="sup_<?= $sup['id'] ?>"
                                                <?= (isset($_POST['supplier_ids']) && in_array($sup['id'], $_POST['supplier_ids'])) ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="sup_<?= $sup['id'] ?>">
                                                <?= e($sup['name']) ?>
                                            </label>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 d-flex justify-content-end gap-2 mt-4">
                            <a href="/inventory-app/public/products/index.php" class="btn btn-secondary">Batal</a>
                            <button type="submit" class="btn btn-primary px-4">
                                <i class="bi bi-save me-1"></i> Simpan Produk
                            </button>
                        </div>
                    </div>
                </form>

            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../views/footer.php'; ?>
