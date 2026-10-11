<?php
// public/categories/index.php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/escape.php';
require_once __DIR__ . '/../../helpers/validation.php';

require_login();

$page_title = 'Master Kategori';

// Laporan Kategori dengan LEFT JOIN & GROUP BY & COUNT (Slide 14)
$query = "
    SELECT c.*, COUNT(p.id) AS product_count, COALESCE(SUM(p.stock), 0) AS total_stock
    FROM categories c
    LEFT JOIN products p ON p.category_id = c.id
    GROUP BY c.id, c.name, c.description, c.created_at
    ORDER BY c.name ASC
";
$categories = $pdo->query($query)->fetchAll();

$success = isset($_GET['success']) ? $_GET['success'] : '';
$error = isset($_GET['error']) ? $_GET['error'] : '';

// Handle Tambah Kategori (Admin Only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    require_role('admin');
    $name = isset($_POST['name']) ? trim($_POST['name']) : '';
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';

    if ($err = validate_required_string($name, 'Nama Kategori', 2, 100)) {
        $error = $err;
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO categories (name, description) VALUES (:name, :desc)");
            $stmt->execute(array('name' => $name, 'desc' => $description));
            header("Location: /inventory-app/public/categories/index.php?success=" . urlencode("Kategori berhasil ditambahkan!"));
            exit;
        } catch (PDOException $e) {
            $error = "Gagal menambah kategori (mungkin nama sudah ada): " . $e->getMessage();
        }
    }
}

// Handle Hapus Kategori (Admin Only)
if (isset($_GET['delete_id'])) {
    require_role('admin');
    $del_id = (int)$_GET['delete_id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM categories WHERE id = :id");
        $stmt->execute(array('id' => $del_id));
        header("Location: /inventory-app/public/categories/index.php?success=" . urlencode("Kategori berhasil dihapus!"));
        exit;
    } catch (PDOException $e) {
        $error = "Kategori tidak dapat dihapus karena masih digunakan oleh produk aktif (Integritas Data RESTRICT).";
    }
}

include __DIR__ . '/../../views/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-0">Master Data Kategori</h3>
        <p class="text-muted small mb-0">Relasi One-to-Many: 1 Kategori memiliki N Produk (Slide 07)</p>
    </div>
</div>

<?php if (!empty($success)): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="bi bi-check-circle me-1"></i> <?= e($success) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="bi bi-exclamation-triangle me-1"></i> <?= e($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row g-4">
    <?php if (has_role('admin')): ?>
    <!-- Form Tambah Kategori -->
    <div class="col-lg-4">
        <div class="card shadow-sm">
            <div class="card-header bg-white py-3">
                <h6 class="fw-bold mb-0"><i class="bi bi-plus-circle text-primary me-1"></i> Tambah Kategori Baru</h6>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <input type="hidden" name="action" value="create">
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Nama Kategori <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" required placeholder="Contoh: Aksesoris Komputer">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Deskripsi</label>
                        <textarea name="description" class="form-control" rows="3" placeholder="Keterangan singkat kategori..."></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-save me-1"></i> Simpan Kategori
                    </button>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Tabel Kategori -->
    <div class="<?= has_role('admin') ? 'col-lg-8' : 'col-12' ?>">
        <div class="card shadow-sm">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="fw-bold mb-0"><i class="bi bi-list-ul me-1"></i> Daftar Kategori</h6>
                <span class="badge bg-secondary"><?= count($categories) ?> Total</span>
            </div>
            <div class="card-body p-0 table-responsive">
                <table class="table table-hover table-striped mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 50px;">No</th>
                            <th>Nama Kategori</th>
                            <th>Deskripsi</th>
                            <th class="text-center">Jml Produk</th>
                            <th class="text-center">Total Stok</th>
                            <?php if (has_role('admin')): ?>
                            <th class="text-center" style="width: 100px;">Aksi</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($categories)): ?>
                        <tr><td colspan="6" class="text-center py-4 text-muted">Belum ada kategori.</td></tr>
                        <?php else: ?>
                            <?php foreach ($categories as $idx => $c): ?>
                            <tr>
                                <td><?= $idx + 1 ?></td>
                                <td class="fw-semibold">
                                    <a href="/inventory-app/public/products/index.php?category_id=<?= $c['id'] ?>" class="text-decoration-none">
                                        <?= e($c['name']) ?>
                                    </a>
                                </td>
                                <td class="small text-muted"><?= isset($c['description']) ? e($c['description']) : '-' ?></td>
                                <td class="text-center">
                                    <span class="badge bg-info text-dark"><?= number_format($c['product_count']) ?></span>
                                </td>
                                <td class="text-center fw-bold"><?= number_format($c['total_stock']) ?></td>
                                <?php if (has_role('admin')): ?>
                                <td class="text-center">
                                    <a href="?delete_id=<?= $c['id'] ?>" class="btn btn-outline-danger btn-sm" onclick="return confirm('Hapus kategori ini? Jika memiliki produk, database akan menolak untuk menjaga integritas data.');">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                </td>
                                <?php endif; ?>
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
