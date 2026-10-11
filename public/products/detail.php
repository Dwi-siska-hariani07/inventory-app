<?php
// public/products/detail.php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/escape.php';

require_login();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    header("Location: /inventory-app/public/products/index.php");
    exit;
}

// Ambil info produk dan kategori (JOIN - Slide 12)
$stmt = $pdo->prepare("
    SELECT p.*, c.name AS category_name, c.description AS category_desc
    FROM products p
    JOIN categories c ON c.id = p.category_id
    WHERE p.id = :id
");
$stmt->execute(array('id' => $id));
$product = $stmt->fetch();

if (!$product) {
    die("Produk tidak ditemukan.");
}

// Ambil daftar supplier penyedia produk ini (Many-to-Many JOIN melalui product_suppliers - Slide 8 & 12)
$stmt = $pdo->prepare("
    SELECT s.*, ps.supplier_price
    FROM suppliers s
    JOIN product_suppliers ps ON ps.supplier_id = s.id
    WHERE ps.product_id = :id
    ORDER BY s.name ASC
");
$stmt->execute(array('id' => $id));
$suppliers = $stmt->fetchAll();

// Ambil riwayat histori stock movements produk ini
$stmt = $pdo->prepare("
    SELECT sm.*, u.username
    FROM stock_movements sm
    JOIN users u ON u.id = sm.user_id
    WHERE sm.product_id = :id
    ORDER BY sm.created_at DESC
");
$stmt->execute(array('id' => $id));
$movements = $stmt->fetchAll();

$page_title = 'Detail Produk: ' . $product['name'];
include __DIR__ . '/../../views/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-0"><?= e($product['name']) ?></h3>
        <p class="text-muted small mb-0">Kode SKU: <span class="badge bg-light text-dark border font-monospace"><?= e($product['sku']) ?></span> &bull; Kategori: <?= e($product['category_name']) ?></p>
    </div>
    <div class="d-flex gap-2">
        <a href="/inventory-app/public/products/index.php" class="btn btn-secondary btn-sm">
            &larr; Kembali
        </a>
        <a href="/inventory-app/public/stock/in.php?product_id=<?= $product['id'] ?>" class="btn btn-success btn-sm">
            <i class="bi bi-box-arrow-in-down me-1"></i> Masuk (IN)
        </a>
        <a href="/inventory-app/public/stock/out.php?product_id=<?= $product['id'] ?>" class="btn btn-danger btn-sm">
            <i class="bi bi-box-arrow-up-right me-1"></i> Keluar (OUT)
        </a>
        <?php if (has_role('admin')): ?>
        <a href="/inventory-app/public/products/edit.php?id=<?= $product['id'] ?>" class="btn btn-warning btn-sm">
            <i class="bi bi-pencil me-1"></i> Edit
        </a>
        <?php endif; ?>
    </div>
</div>

<div class="row g-4">
    <!-- Info Ringkas -->
    <div class="col-lg-4">
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white">
                <i class="bi bi-info-circle me-1"></i> Informasi Produk
            </div>
            <div class="card-body">
                <ul class="list-group list-group-flush">
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <span class="text-muted">Harga Jual</span>
                        <strong class="text-success fs-5"><?= format_rupiah($product['price']) ?></strong>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <span class="text-muted">Stok Tersedia</span>
                        <span class="badge <?= $product['stock'] <= $product['minimum_stock'] ? 'bg-danger' : 'bg-primary' ?> fs-6">
                            <?= number_format($product['stock']) ?> unit
                        </span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <span class="text-muted">Batas Stok Minimum</span>
                        <span class="fw-semibold"><?= number_format($product['minimum_stock']) ?> unit</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <span class="text-muted">Total Nilai Stok</span>
                        <span class="fw-bold"><?= format_rupiah($product['stock'] * $product['price']) ?></span>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Supplier Terkait (Many-to-Many Junction) -->
        <div class="card shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span><i class="bi bi-truck me-1"></i> Supplier Pemasok</span>
                <span class="badge bg-secondary">Many-to-Many</span>
            </div>
            <div class="card-body p-0">
                <?php if (empty($suppliers)): ?>
                    <div class="p-3 text-muted small text-center">Belum ada data supplier yang dihubungkan dengan produk ini.</div>
                <?php else: ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($suppliers as $s): ?>
                        <li class="list-group-item">
                            <div class="fw-semibold"><?= e($s['name']) ?></div>
                            <small class="text-muted d-block"><i class="bi bi-telephone me-1"></i> <?= isset($s['phone']) ? e($s['phone']) : '-' ?> &bull; <?= isset($s['contact_person']) ? e($s['contact_person']) : '-' ?></small>
                            <small class="text-info fw-bold">Harga Beli: <?= format_rupiah($s['supplier_price']) ?></small>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Riwayat Transaksi Stok Produk Ini -->
    <div class="col-lg-8">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span><i class="bi bi-clock-history me-1"></i> Riwayat Transaksi Barang Masuk & Keluar</span>
                <span class="badge bg-info text-dark"><?= count($movements) ?> Transaksi</span>
            </div>
            <div class="card-body p-0 table-responsive">
                <table class="table table-hover table-striped mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Waktu</th>
                            <th class="text-center">Jenis</th>
                            <th class="text-center">Jumlah</th>
                            <th>Keterangan / Catatan</th>
                            <th>Petugas</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($movements)): ?>
                        <tr>
                            <td colspan="5" class="text-center py-4 text-muted">Belum ada transaksi pergerakan stok untuk produk ini.</td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($movements as $m): ?>
                            <tr>
                                <td class="small text-muted"><?= date('d/m/Y H:i', strtotime($m['created_at'])) ?></td>
                                <td class="text-center">
                                    <?php if ($m['type'] === 'IN'): ?>
                                        <span class="badge bg-success"><i class="bi bi-arrow-down-left"></i> MASUK</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger"><i class="bi bi-arrow-up-right"></i> KELUAR</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center fw-bold <?= $m['type'] === 'IN' ? 'text-success' : 'text-danger' ?>">
                                    <?= ($m['type'] === 'IN' ? '+' : '-') . number_format($m['quantity']) ?>
                                </td>
                                <td class="small"><?= isset($m['notes']) ? e($m['notes']) : '-' ?></td>
                                <td class="small fw-semibold"><?= e($m['username']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../views/footer.php'; ?>
