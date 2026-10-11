<?php
// public/stock/history.php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/escape.php';
require_once __DIR__ . '/../../helpers/validation.php';

require_login();

$page_title = 'Histori Pergerakan Stok';

// 1. Filter Tipe Transaksi (IN / OUT)
$type = isset($_GET['type']) ? $_GET['type'] : '';
if (!in_array($type, array('IN', 'OUT'), true)) {
    $type = '';
}

// 2. Filter Produk
$product_id = filter_input(INPUT_GET, 'product_id', FILTER_VALIDATE_INT);
if (!$product_id) {
    $product_id = null;
}

// 3. Search keterangan / nama produk / SKU
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// 4. Pagination (Slide 17)
$page = max(1, (int)(isset($_GET['page']) ? $_GET['page'] : 1));
$perPage = 15;
$offset = ($page - 1) * $perPage;

$where_clauses = array();
$params = array();

if ($type !== '') {
    $where_clauses[] = "sm.type = :type";
    $params['type'] = $type;
}

if ($product_id) {
    $where_clauses[] = "sm.product_id = :pid";
    $params['pid'] = $product_id;
}

if ($search !== '') {
    $where_clauses[] = "(p.name LIKE :search_name OR p.sku LIKE :search_sku OR sm.notes LIKE :search_notes)";
    $params['search_name'] = "%$search%";
    $params['search_sku'] = "%$search%";
    $params['search_notes'] = "%$search%";
}

$where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

// Hitung Total Data
$count_stmt = $pdo->prepare("
    SELECT COUNT(sm.id)
    FROM stock_movements sm
    JOIN products p ON p.id = sm.product_id
    $where_sql
");
$count_stmt->execute($params);
$total_data = (int)$count_stmt->fetchColumn();
$total_pages = max(1, (int)ceil($total_data / $perPage));

// Ambil Riwayat Transaksi dengan Multiple JOIN (products, categories, users)
$query = "
    SELECT sm.*, p.sku, p.name AS product_name, c.name AS category_name, u.username
    FROM stock_movements sm
    JOIN products p ON p.id = sm.product_id
    JOIN categories c ON c.id = p.category_id
    JOIN users u ON u.id = sm.user_id
    $where_sql
    ORDER BY sm.created_at DESC
    LIMIT :limit OFFSET :offset
";

$stmt = $pdo->prepare($query);
foreach ($params as $k => $v) {
    $stmt->bindValue(":$k", $v);
}
$stmt->bindValue(':limit', (int)$perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
$stmt->execute();
$movements = $stmt->fetchAll();

// List produk untuk dropdown filter
$products = $pdo->query("SELECT id, name, sku FROM products ORDER BY name ASC")->fetchAll();

include __DIR__ . '/../../views/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-0">Histori Transaksi Stok</h3>
        <p class="text-muted small mb-0">Audit trail lengkap setiap pergerakan barang masuk dan keluar gudang</p>
    </div>
    <div class="d-flex gap-2">
        <a href="/inventory-app/public/stock/in.php" class="btn btn-success btn-sm">
            <i class="bi bi-plus-circle me-1"></i> Input Masuk
        </a>
        <a href="/inventory-app/public/stock/out.php" class="btn btn-danger btn-sm">
            <i class="bi bi-dash-circle me-1"></i> Input Keluar
        </a>
    </div>
</div>

<!-- Toolbar Filter -->
<div class="card shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" action="" class="row g-2 align-items-center">
            <div class="col-md-4">
                <input type="text" name="search" class="form-control" placeholder="Cari nama barang / SKU / catatan..." value="<?= e($search) ?>">
            </div>

            <div class="col-md-3">
                <select name="product_id" class="form-select">
                    <option value="">-- Semua Produk --</option>
                    <?php foreach ($products as $pr): ?>
                        <option value="<?= $pr['id'] ?>" <?= $product_id == $pr['id'] ? 'selected' : '' ?>>
                            <?= e($pr['name']) ?> (<?= e($pr['sku']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-3">
                <select name="type" class="form-select">
                    <option value="">-- Semua Jenis Transaksi --</option>
                    <option value="IN" <?= $type === 'IN' ? 'selected' : '' ?>>Barang Masuk (IN)</option>
                    <option value="OUT" <?= $type === 'OUT' ? 'selected' : '' ?>>Barang Keluar (OUT)</option>
                </select>
            </div>

            <div class="col-md-2 d-flex gap-1">
                <button type="submit" class="btn btn-secondary w-100">Filter</button>
                <a href="/inventory-app/public/stock/history.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-clockwise"></i></a>
            </div>
        </form>
    </div>
</div>

<!-- Tabel Histori -->
<div class="card shadow-sm">
    <div class="card-body p-0 table-responsive">
        <table class="table table-hover table-striped mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th style="width: 50px;">ID</th>
                    <th>Waktu & Tanggal</th>
                    <th>SKU & Nama Produk</th>
                    <th>Kategori</th>
                    <th class="text-center">Jenis</th>
                    <th class="text-center">Jumlah</th>
                    <th>Catatan Transaksi</th>
                    <th>Petugas</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($movements)): ?>
                <tr>
                    <td colspan="8" class="text-center py-4 text-muted">Belum ada data pergerakan stok yang cocok.</td>
                </tr>
                <?php else: ?>
                    <?php foreach ($movements as $m): ?>
                    <tr>
                        <td class="text-muted">#<?= $m['id'] ?></td>
                        <td><?= date('d M Y, H:i:s', strtotime($m['created_at'])) ?></td>
                        <td>
                            <div class="fw-semibold">
                                <a href="/inventory-app/public/products/detail.php?id=<?= $m['product_id'] ?>" class="text-decoration-none">
                                    <?= e($m['product_name']) ?>
                                </a>
                            </div>
                            <small class="text-muted font-monospace"><?= e($m['sku']) ?></small>
                        </td>
                        <td><span class="badge bg-light text-dark border"><?= e($m['category_name']) ?></span></td>
                        <td class="text-center">
                            <?php if ($m['type'] === 'IN'): ?>
                                <span class="badge bg-success"><i class="bi bi-box-arrow-in-down me-1"></i> MASUK</span>
                            <?php else: ?>
                                <span class="badge bg-danger"><i class="bi bi-box-arrow-up-right me-1"></i> KELUAR</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center fw-bold fs-6 <?= $m['type'] === 'IN' ? 'text-success' : 'text-danger' ?>">
                            <?= ($m['type'] === 'IN' ? '+' : '-') . number_format($m['quantity']) ?>
                        </td>
                        <td class="small"><?= isset($m['notes']) ? e($m['notes']) : '-' ?></td>
                        <td>
                            <span class="badge bg-secondary"><i class="bi bi-person me-1"></i> <?= e($m['username']) ?></span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <div class="card-footer bg-white d-flex justify-content-between align-items-center py-3">
        <small class="text-muted">
            Menampilkan data ke-<?= min($offset + 1, $total_data) ?> s/d <?= min($offset + count($movements), $total_data) ?> dari total <?= $total_data ?> histori
        </small>
        <nav>
            <ul class="pagination pagination-sm mb-0">
                <?php
                $qs = http_build_query(array('search' => $search, 'type' => $type, 'product_id' => $product_id));
                ?>
                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="?page=<?= $page - 1 ?>&<?= $qs ?>">&laquo; Prev</a>
                </li>
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?= $page == $i ? 'active' : '' ?>">
                        <a class="page-link" href="?page=<?= $i ?>&<?= $qs ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
                <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                    <a class="page-link" href="?page=<?= $page + 1 ?>&<?= $qs ?>">Next &raquo;</a>
                </li>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../../views/footer.php'; ?>
