<?php
// public/dashboard.php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/escape.php';

require_login();

$page_title = 'Dashboard Ringkasan';

// 1. Total Produk: COUNT(products.id) (Slide 29)
$stmt = $pdo->query("SELECT COUNT(id) AS total_products FROM products");
$total_products = (int)$stmt->fetchColumn();

// 2. Nilai Inventory: SUM(stock * price) (Slide 29)
$stmt = $pdo->query("SELECT COALESCE(SUM(stock * price), 0) AS total_inventory_value FROM products");
$inventory_value = (float)$stmt->fetchColumn();

// 3. Produk Stok Rendah: stock <= minimum_stock (Slide 29)
$stmt = $pdo->query("SELECT COUNT(id) AS low_stock_count FROM products WHERE stock <= minimum_stock");
$low_stock_count = (int)$stmt->fetchColumn();

// 4. Barang Masuk: SUM(quantity) WHERE type = 'IN' (Slide 29)
$stmt = $pdo->query("SELECT COALESCE(SUM(quantity), 0) AS total_in FROM stock_movements WHERE type = 'IN'");
$total_in = (int)$stmt->fetchColumn();

// 5. Barang Keluar: SUM(quantity) WHERE type = 'OUT' (Slide 29)
$stmt = $pdo->query("SELECT COALESCE(SUM(quantity), 0) AS total_out FROM stock_movements WHERE type = 'OUT'");
$total_out = (int)$stmt->fetchColumn();

// Query Laporan Agregat Stok per Kategori (Slide 14)
// SELECT c.name, COUNT(p.id) AS total_products, SUM(p.stock) AS total_stock, AVG(p.price) AS average_price ...
$stmt = $pdo->query("
    SELECT c.id, c.name,
           COUNT(p.id) AS category_products,
           COALESCE(SUM(p.stock), 0) AS total_stock,
           COALESCE(AVG(p.price), 0) AS average_price
    FROM categories c
    LEFT JOIN products p ON p.category_id = c.id
    GROUP BY c.id, c.name
    ORDER BY c.name ASC
");
$category_summaries = $stmt->fetchAll();

// Query Laporan Akumulasi Barang Masuk dan Keluar per Produk (Slide 28)
// SELECT p.sku, p.name, SUM(CASE WHEN sm.type='IN' THEN sm.quantity ELSE 0 END) AS total_in ...
$stmt = $pdo->query("
    SELECT p.sku, p.name, p.stock, p.minimum_stock,
           COALESCE(SUM(CASE WHEN sm.type = 'IN' THEN sm.quantity ELSE 0 END), 0) AS total_in,
           COALESCE(SUM(CASE WHEN sm.type = 'OUT' THEN sm.quantity ELSE 0 END), 0) AS total_out
    FROM products p
    LEFT JOIN stock_movements sm ON sm.product_id = p.id
    GROUP BY p.id, p.sku, p.name, p.stock, p.minimum_stock
    ORDER BY p.name ASC
    LIMIT 10
");
$product_movements = $stmt->fetchAll();

// Daftar Produk dengan Stok Menipis (Peringatan Dini)
$stmt = $pdo->query("
    SELECT p.id, p.sku, p.name, p.stock, p.minimum_stock, c.name AS category_name
    FROM products p
    JOIN categories c ON c.id = p.category_id
    WHERE p.stock <= p.minimum_stock
    ORDER BY p.stock ASC
");
$low_stock_items = $stmt->fetchAll();

include __DIR__ . '/../views/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-0">Dashboard Ringkasan</h3>
        <p class="text-muted small mb-0">Informasi ringkas dan laporan agregat untuk pengambilan keputusan bisnis</p>
    </div>
    <div class="d-flex gap-2">
        <a href="/inventory-app/public/stock/in.php" class="btn btn-success btn-sm">
            <i class="bi bi-box-arrow-in-down me-1"></i> Input Masuk (IN)
        </a>
        <a href="/inventory-app/public/stock/out.php" class="btn btn-danger btn-sm">
            <i class="bi bi-box-arrow-up-right me-1"></i> Input Keluar (OUT)
        </a>
    </div>
</div>

<!-- 5 Widget Sesuai Slide 29 -->
<div class="row g-3 mb-4">
    <div class="col-md-4 col-xl">
        <div class="card h-100 shadow-sm border-0 border-start border-primary border-4">
            <div class="card-body">
                <div class="text-muted small text-uppercase fw-bold">Total Produk</div>
                <div class="d-flex align-items-center justify-content-between mt-2">
                    <h2 class="fw-bold mb-0"><?= number_format($total_products) ?></h2>
                    <i class="bi bi-box-seam text-primary fs-2"></i>
                </div>
                <small class="text-muted">COUNT(products.id)</small>
            </div>
        </div>
    </div>

    <div class="col-md-4 col-xl">
        <div class="card h-100 shadow-sm border-0 border-start border-success border-4">
            <div class="card-body">
                <div class="text-muted small text-uppercase fw-bold">Nilai Inventory</div>
                <div class="d-flex align-items-center justify-content-between mt-2">
                    <h3 class="fw-bold mb-0 text-success"><?= format_rupiah($inventory_value) ?></h3>
                    <i class="bi bi-cash-stack text-success fs-2"></i>
                </div>
                <small class="text-muted">SUM(stock &times; price)</small>
            </div>
        </div>
    </div>

    <div class="col-md-4 col-xl">
        <div class="card h-100 shadow-sm border-0 border-start border-danger border-4">
            <div class="card-body">
                <div class="text-muted small text-uppercase fw-bold">Produk Stok Rendah</div>
                <div class="d-flex align-items-center justify-content-between mt-2">
                    <h2 class="fw-bold mb-0 text-danger"><?= number_format($low_stock_count) ?></h2>
                    <i class="bi bi-exclamation-triangle text-danger fs-2"></i>
                </div>
                <small class="text-muted">stock &le; minimum_stock</small>
            </div>
        </div>
    </div>

    <div class="col-md-6 col-xl">
        <div class="card h-100 shadow-sm border-0 border-start border-info border-4">
            <div class="card-body">
                <div class="text-muted small text-uppercase fw-bold">Barang Masuk</div>
                <div class="d-flex align-items-center justify-content-between mt-2">
                    <h2 class="fw-bold mb-0 text-info"><?= number_format($total_in) ?></h2>
                    <i class="bi bi-arrow-down-left-square text-info fs-2"></i>
                </div>
                <small class="text-muted">SUM(quantity) [IN]</small>
            </div>
        </div>
    </div>

    <div class="col-md-6 col-xl">
        <div class="card h-100 shadow-sm border-0 border-start border-warning border-4">
            <div class="card-body">
                <div class="text-muted small text-uppercase fw-bold">Barang Keluar</div>
                <div class="d-flex align-items-center justify-content-between mt-2">
                    <h2 class="fw-bold mb-0 text-warning"><?= number_format($total_out) ?></h2>
                    <i class="bi bi-arrow-up-right-square text-warning fs-2"></i>
                </div>
                <small class="text-muted">SUM(quantity) [OUT]</small>
            </div>
        </div>
    </div>
</div>

<!-- Peringatan Stok Rendah -->
<?php if (!empty($low_stock_items)): ?>
<div class="alert alert-danger shadow-sm mb-4">
    <div class="d-flex align-items-center mb-2">
        <i class="bi bi-bell-fill fs-5 me-2"></i>
        <strong class="fs-6">Peringatan: Ada <?= count($low_stock_items) ?> produk yang mencapai atau berada di bawah batas minimum stok!</strong>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-danger table-striped mb-0 align-middle">
            <thead>
                <tr>
                    <th>SKU</th>
                    <th>Nama Produk</th>
                    <th>Kategori</th>
                    <th>Stok Saat Ini</th>
                    <th>Batas Minimum</th>
                    <th>Aksi Cepat</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($low_stock_items as $l): ?>
                <tr>
                    <td class="fw-bold"><?= e($l['sku']) ?></td>
                    <td><?= e($l['name']) ?></td>
                    <td><span class="badge bg-secondary"><?= e($l['category_name']) ?></span></td>
                    <td class="text-danger fw-bold"><?= e($l['stock']) ?> unit</td>
                    <td><?= e($l['minimum_stock']) ?> unit</td>
                    <td>
                        <a href="/inventory-app/public/stock/in.php?product_id=<?= (int)$l['id'] ?>" class="btn btn-xs btn-outline-danger btn-sm">
                            <i class="bi bi-plus-circle me-1"></i> Restock
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<div class="row g-4">
    <!-- Laporan Kategori & Fungsi Agregat (Slide 14) -->
    <div class="col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-pie-chart me-2"></i> Ringkasan Kategori (GROUP BY & Aggregate)</span>
                <span class="badge bg-primary">Slide 14</span>
            </div>
            <div class="card-body p-0 table-responsive">
                <table class="table table-hover table-striped mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Kategori</th>
                            <th class="text-center">Jml Produk</th>
                            <th class="text-center">Total Stok</th>
                            <th class="text-end">Rata-rata Harga</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($category_summaries as $cs): ?>
                        <tr>
                            <td class="fw-semibold"><?= e($cs['name']) ?></td>
                            <td class="text-center"><span class="badge bg-info text-dark"><?= e($cs['category_products']) ?></span></td>
                            <td class="text-center fw-bold"><?= number_format($cs['total_stock']) ?></td>
                            <td class="text-end"><?= format_rupiah($cs['average_price']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Laporan Akumulasi Transaksi Produk (Slide 28) -->
    <div class="col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-file-earmark-bar-graph me-2"></i> Laporan Arus Masuk vs Keluar</span>
                <span class="badge bg-primary">Slide 28</span>
            </div>
            <div class="card-body p-0 table-responsive">
                <table class="table table-hover table-striped mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>SKU & Produk</th>
                            <th class="text-center text-success">Total IN</th>
                            <th class="text-center text-danger">Total OUT</th>
                            <th class="text-center">Stok Sisa</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($product_movements as $pm): ?>
                        <tr>
                            <td>
                                <div class="fw-semibold"><?= e($pm['name']) ?></div>
                                <small class="text-muted"><?= e($pm['sku']) ?></small>
                            </td>
                            <td class="text-center text-success fw-bold">+<?= number_format($pm['total_in']) ?></td>
                            <td class="text-center text-danger fw-bold">-<?= number_format($pm['total_out']) ?></td>
                            <td class="text-center">
                                <span class="badge <?= $pm['stock'] <= $pm['minimum_stock'] ? 'bg-danger' : 'bg-success' ?>">
                                    <?= number_format($pm['stock']) ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-white text-muted small">
                <em>Rumus konsistensi stok: <code>stok akhir = stok awal + total masuk - total keluar</code></em>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../views/footer.php'; ?>
