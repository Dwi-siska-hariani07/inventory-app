<?php
// public/products/index.php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/escape.php';
require_once __DIR__ . '/../../helpers/validation.php';

require_login();

$page_title = 'Daftar Produk';

// 1. Search (nama atau SKU) - Slide 13 & 16
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// 2. Filter Kategori - Slide 16
$category_id = filter_input(INPUT_GET, 'category_id', FILTER_VALIDATE_INT);
if (!$category_id) {
    $category_id = null;
}

// 3. Sorting (Allowlist Protection) - Slide 16
$allowed_sort = array('name', 'price', 'stock', 'sku', 'id');
$sort_by = validate_sort_column(isset($_GET['sort']) ? $_GET['sort'] : '', $allowed_sort, 'id');
$order = validate_sort_order(isset($_GET['order']) ? $_GET['order'] : '', 'DESC');

// 4. Pagination - Slide 17
$page = max(1, (int)(isset($_GET['page']) ? $_GET['page'] : 1));
$perPage = 10;
$offset = ($page - 1) * $perPage;

// Buat Klausul WHERE Dinamis & Prepared Statement
$where_clauses = array();
$params = array();

if ($search !== '') {
    $where_clauses[] = "(p.name LIKE :search_name OR p.sku LIKE :search_sku)";
    $params['search_name'] = "%$search%";
    $params['search_sku'] = "%$search%";
}

if ($category_id !== null && $category_id > 0) {
    $where_clauses[] = "p.category_id = :cat_id";
    $params['cat_id'] = $category_id;
}

$where_sql = '';
if (!empty($where_clauses)) {
    $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
}

// Hitung Total Data (untuk pagination)
$count_query = "SELECT COUNT(p.id) FROM products p $where_sql";
$count_stmt = $pdo->prepare($count_query);
$count_stmt->execute($params);
$total_data = (int)$count_stmt->fetchColumn();
$total_pages = max(1, (int)ceil($total_data / $perPage));

// Ambil Data Produk dengan JOIN categories
$query = "
    SELECT p.*, c.name AS category_name
    FROM products p
    JOIN categories c ON c.id = p.category_id
    $where_sql
    ORDER BY p.$sort_by $order
    LIMIT :limit OFFSET :offset
";

$stmt = $pdo->prepare($query);
// Bind parameter search/filter
foreach ($params as $key => $val) {
    $stmt->bindValue(":$key", $val);
}
// Bind limit dan offset sebagai integer (PDO requirement)
$stmt->bindValue(':limit', (int)$perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
$stmt->execute();
$products = $stmt->fetchAll();

// Ambil daftar kategori untuk dropdown filter
$categories = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC")->fetchAll();

// Flash message
$success = isset($_GET['success']) ? $_GET['success'] : '';
$error = isset($_GET['error']) ? $_GET['error'] : '';

include __DIR__ . '/../../views/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-0">Manajemen Produk</h3>
        <p class="text-muted small mb-0">Katalog master data produk, relasi kategori, serta kontrol stok</p>
    </div>
    <?php if (has_role('admin')): ?>
    <a href="/inventory-app/public/products/create.php" class="btn btn-primary">
        <i class="bi bi-plus-circle me-1"></i> Tambah Produk Baru
    </a>
    <?php endif; ?>
</div>

<?php if (!empty($success)): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle me-1"></i> <?= e($success) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle me-1"></i> <?= e($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Filter & Search Toolbar (Slide 16) -->
<div class="card shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" action="" class="row g-2 align-items-center">
            <div class="col-md-4">
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="Cari nama produk / SKU..." value="<?= e($search) ?>">
                </div>
            </div>

            <div class="col-md-3">
                <select name="category_id" class="form-select">
                    <option value="">-- Semua Kategori --</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= $category_id == $cat['id'] ? 'selected' : '' ?>>
                            <?= e($cat['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-2">
                <select name="sort" class="form-select">
                    <option value="id" <?= $sort_by === 'id' ? 'selected' : '' ?>>Urut: Terbaru</option>
                    <option value="name" <?= $sort_by === 'name' ? 'selected' : '' ?>>Urut: Nama</option>
                    <option value="price" <?= $sort_by === 'price' ? 'selected' : '' ?>>Urut: Harga</option>
                    <option value="stock" <?= $sort_by === 'stock' ? 'selected' : '' ?>>Urut: Stok</option>
                </select>
            </div>

            <div class="col-md-2">
                <select name="order" class="form-select">
                    <option value="ASC" <?= $order === 'ASC' ? 'selected' : '' ?>>Ascending (A-Z / Min)</option>
                    <option value="DESC" <?= $order === 'DESC' ? 'selected' : '' ?>>Descending (Z-A / Max)</option>
                </select>
            </div>

            <div class="col-md-1 d-flex gap-1">
                <button type="submit" class="btn btn-secondary w-100" title="Terapkan Filter">
                    Filter
                </button>
                <a href="/inventory-app/public/products/index.php" class="btn btn-outline-secondary" title="Reset Filter">
                    <i class="bi bi-arrow-clockwise"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Tabel Daftar Produk -->
<div class="card shadow-sm">
    <div class="card-body p-0 table-responsive">
        <table class="table table-hover table-striped mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th style="width: 50px;">No</th>
                    <th>SKU</th>
                    <th>Nama Produk</th>
                    <th>Kategori</th>
                    <th class="text-end">Harga</th>
                    <th class="text-center">Stok</th>
                    <th class="text-center">Min. Stok</th>
                    <th class="text-center">Status</th>
                    <th class="text-center" style="width: 160px;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($products)): ?>
                <tr>
                    <td colspan="9" class="text-center py-4 text-muted">
                        <i class="bi bi-inbox fs-3 d-block mb-1"></i> Tidak ada produk yang sesuai dengan kriteria pencarian.
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($products as $idx => $p): ?>
                    <tr>
                        <td><?= $offset + $idx + 1 ?></td>
                        <td><span class="badge bg-light text-dark border font-monospace"><?= e($p['sku']) ?></span></td>
                        <td class="fw-semibold">
                            <a href="/inventory-app/public/products/detail.php?id=<?= $p['id'] ?>" class="text-decoration-none text-dark">
                                <?= e($p['name']) ?>
                            </a>
                        </td>
                        <td><span class="badge bg-info text-dark"><?= e($p['category_name']) ?></span></td>
                        <td class="text-end fw-semibold"><?= format_rupiah($p['price']) ?></td>
                        <td class="text-center fw-bold fs-6">
                            <?= number_format($p['stock']) ?>
                        </td>
                        <td class="text-center text-muted"><?= number_format($p['minimum_stock']) ?></td>
                        <td class="text-center">
                            <?php if ($p['stock'] <= 0): ?>
                                <span class="badge bg-danger">Habis</span>
                            <?php elseif ($p['stock'] <= $p['minimum_stock']): ?>
                                <span class="badge bg-warning text-dark"><i class="bi bi-exclamation-triangle"></i> Menipis</span>
                            <?php else: ?>
                                <span class="badge bg-success">Aman</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <div class="btn-group btn-group-sm">
                                <a href="/inventory-app/public/products/detail.php?id=<?= $p['id'] ?>" class="btn btn-outline-info" title="Lihat Detail & Relasi Supplier">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <?php if (has_role('admin')): ?>
                                <a href="/inventory-app/public/products/edit.php?id=<?= $p['id'] ?>" class="btn btn-outline-warning" title="Edit Produk">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <a href="/inventory-app/public/products/delete.php?id=<?= $p['id'] ?>" class="btn btn-outline-danger" title="Hapus Produk" onclick="return confirm('Apakah Anda yakin ingin menghapus produk ini?');">
                                    <i class="bi bi-trash"></i>
                                </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination (Slide 17: Mempertahankan parameter filter & pagination) -->
    <?php if ($total_pages > 1): ?>
    <div class="card-footer bg-white d-flex justify-content-between align-items-center py-3">
        <small class="text-muted">
            Menampilkan data ke-<?= min($offset + 1, $total_data) ?> s/d <?= min($offset + count($products), $total_data) ?> dari total <?= $total_data ?> produk
        </small>
        <nav>
            <ul class="pagination pagination-sm mb-0">
                <?php
                // Build query params string untuk mempertahankan filter
                $qs = http_build_query(array(
                    'search'      => $search,
                    'category_id' => $category_id,
                    'sort'        => $sort_by,
                    'order'       => $order
                ));
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
