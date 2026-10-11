<?php
// public/products/edit.php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/escape.php';
require_once __DIR__ . '/../../helpers/validation.php';

require_role('admin');

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    header("Location: /inventory-app/public/products/index.php");
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM products WHERE id = :id");
$stmt->execute(array('id' => $id));
$product = $stmt->fetch();

if (!$product) {
    die("Produk tidak ditemukan.");
}

$categories = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC")->fetchAll();
$suppliers = $pdo->query("SELECT id, name FROM suppliers ORDER BY name ASC")->fetchAll();

// Ambil supplier yang sudah terhubung saat ini
$stmt = $pdo->prepare("SELECT supplier_id FROM product_suppliers WHERE product_id = :pid");
$stmt->execute(array('pid' => $id));
$current_supplier_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

$errors = array();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $category_id  = filter_input(INPUT_POST, 'category_id', FILTER_VALIDATE_INT);
    $sku          = isset($_POST['sku']) ? trim($_POST['sku']) : '';
    $name         = isset($_POST['name']) ? trim($_POST['name']) : '';
    $price        = isset($_POST['price']) ? $_POST['price'] : '';
    $min_stock    = isset($_POST['minimum_stock']) ? $_POST['minimum_stock'] : 5;
    $supplier_ids = isset($_POST['supplier_ids']) ? $_POST['supplier_ids'] : array();

    if (!$category_id) {
        $errors[] = 'Kategori tidak valid.';
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
    if ($err = validate_non_negative_number($min_stock, 'Minimum Stok')) {
        $errors[] = $err;
    }

    if (empty($errors) && $sku !== $product['sku']) {
        $stmt = $pdo->prepare("SELECT COUNT(id) FROM products WHERE sku = :sku AND id != :id");
        $stmt->execute(array('sku' => $sku, 'id' => $id));
        if ($stmt->fetchColumn() > 0) {
            $errors[] = "SKU '$sku' sudah digunakan produk lain.";
        }
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                UPDATE products
                SET category_id = :cat_id, sku = :sku, name = :name, price = :price, minimum_stock = :min_stock
                WHERE id = :id
            ");
            $stmt->execute(array(
                'cat_id'    => $category_id,
                'sku'       => $sku,
                'name'      => $name,
                'price'     => (float)$price,
                'min_stock' => (int)$min_stock,
                'id'        => $id
            ));

            $pdo->prepare("DELETE FROM product_suppliers WHERE product_id = :pid")->execute(array('pid' => $id));
            if (!empty($supplier_ids) && is_array($supplier_ids)) {
                $ps_stmt = $pdo->prepare("
                    INSERT INTO product_suppliers (product_id, supplier_id, supplier_price)
                    VALUES (:pid, :sid, :sprice)
                ");
                foreach ($supplier_ids as $sid) {
                    $sid = (int)$sid;
                    if ($sid > 0) {
                        $ps_stmt->execute(array(
                            'pid'    => $id,
                            'sid'    => $sid,
                            'sprice' => (float)$price * 0.8
                        ));
                    }
                }
            }

            $pdo->commit();
            header("Location: /inventory-app/public/products/index.php?success=" . urlencode("Data produk berhasil diperbarui!"));
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = "Gagal memperbarui produk: " . $e->getMessage();
        }
    }
}

$page_title = 'Edit Produk: ' . $product['name'];
include __DIR__ . '/../../views/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3">
                <h5 class="fw-bold mb-0"><i class="bi bi-pencil-square text-warning me-2"></i> Edit Data Produk</h5>
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
                            <label class="form-label fw-semibold small">SKU <span class="text-danger">*</span></label>
                            <input type="text" name="sku" class="form-control" required value="<?= isset($_POST['sku']) ? e($_POST['sku']) : e($product['sku']) ?>">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Kategori <span class="text-danger">*</span></label>
                            <select name="category_id" class="form-select" required>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>" <?= (($product['category_id'] == $cat['id']) ? 'selected' : '') ?>>
                                        <?= e($cat['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold small">Nama Produk <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" required value="<?= isset($_POST['name']) ? e($_POST['name']) : e($product['name']) ?>">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Harga Satuan (Rp) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" name="price" class="form-control" required min="0" value="<?= isset($_POST['price']) ? e($_POST['price']) : e($product['price']) ?>">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Batas Minimum Stok <span class="text-danger">*</span></label>
                            <input type="number" name="minimum_stock" class="form-control" required min="1" value="<?= isset($_POST['minimum_stock']) ? e($_POST['minimum_stock']) : e($product['minimum_stock']) ?>">
                        </div>

                        <div class="col-12">
                            <div class="alert alert-secondary py-2 small mb-0">
                                <strong>Info Stok:</strong> Stok saat ini adalah <strong><?= (int)$product['stock'] ?> unit</strong>. Perubahan stok harus dilakukan melalui menu <a href="/inventory-app/public/stock/in.php">Barang Masuk</a> atau <a href="/inventory-app/public/stock/out.php">Barang Keluar</a> untuk menjaga konsistensi database transaction.
                            </div>
                        </div>

                        <div class="col-12 mt-2">
                            <label class="form-label fw-semibold small">Supplier Terhubung (Many-to-Many)</label>
                            <div class="card p-3 bg-light border-0">
                                <div class="row g-2">
                                    <?php foreach ($suppliers as $sup): ?>
                                    <div class="col-md-6">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="supplier_ids[]" value="<?= $sup['id'] ?>" id="sup_<?= $sup['id'] ?>"
                                                <?= in_array($sup['id'], $current_supplier_ids) ? 'checked' : '' ?>>
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
                            <button type="submit" class="btn btn-warning px-4 text-dark fw-semibold">
                                <i class="bi bi-check2-circle me-1"></i> Simpan Perubahan
                            </button>
                        </div>
                    </div>
                </form>

            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../views/footer.php'; ?>
