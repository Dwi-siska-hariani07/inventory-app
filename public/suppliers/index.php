<?php
// public/suppliers/index.php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/escape.php';
require_once __DIR__ . '/../../helpers/validation.php';

require_login();

$page_title = 'Master Supplier';

// Query suppliers dengan count relasi produk (Many-to-Many - Slide 08)
$query = "
    SELECT s.*, COUNT(ps.product_id) AS total_supplied_products
    FROM suppliers s
    LEFT JOIN product_suppliers ps ON ps.supplier_id = s.id
    GROUP BY s.id, s.name, s.contact_person, s.phone, s.email, s.address, s.created_at
    ORDER BY s.name ASC
";
$suppliers = $pdo->query($query)->fetchAll();

$success = isset($_GET['success']) ? $_GET['success'] : '';
$error = isset($_GET['error']) ? $_GET['error'] : '';

// Handle Tambah Supplier (Admin Only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    require_role('admin');
    $name    = isset($_POST['name']) ? trim($_POST['name']) : '';
    $contact = isset($_POST['contact_person']) ? trim($_POST['contact_person']) : '';
    $phone   = isset($_POST['phone']) ? trim($_POST['phone']) : '';
    $email   = isset($_POST['email']) ? trim($_POST['email']) : '';
    $address = isset($_POST['address']) ? trim($_POST['address']) : '';

    if ($err = validate_required_string($name, 'Nama Supplier', 2, 150)) {
        $error = $err;
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO suppliers (name, contact_person, phone, email, address)
                VALUES (:name, :contact, :phone, :email, :address)
            ");
            $stmt->execute(array(
                'name'    => $name,
                'contact' => $contact,
                'phone'   => $phone,
                'email'   => $email,
                'address' => $address
            ));
            header("Location: /inventory-app/public/suppliers/index.php?success=" . urlencode("Supplier baru berhasil ditambahkan!"));
            exit;
        } catch (PDOException $e) {
            $error = "Gagal menambah supplier: " . $e->getMessage();
        }
    }
}

// Handle Hapus Supplier (Admin Only)
if (isset($_GET['delete_id'])) {
    require_role('admin');
    $del_id = (int)$_GET['delete_id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM suppliers WHERE id = :id");
        $stmt->execute(array('id' => $del_id));
        header("Location: /inventory-app/public/suppliers/index.php?success=" . urlencode("Supplier berhasil dihapus!"));
        exit;
    } catch (PDOException $e) {
        $error = "Gagal menghapus supplier: " . $e->getMessage();
    }
}

include __DIR__ . '/../../views/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-0">Master Data Supplier</h3>
        <p class="text-muted small mb-0">Relasi Many-to-Many dengan Produk melalui Junction Table <code>product_suppliers</code> (Slide 08)</p>
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
    <!-- Form Tambah Supplier -->
    <div class="col-lg-4">
        <div class="card shadow-sm">
            <div class="card-header bg-white py-3">
                <h6 class="fw-bold mb-0"><i class="bi bi-plus-circle text-primary me-1"></i> Tambah Supplier</h6>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <input type="hidden" name="action" value="create">
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Nama Supplier / PT <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" required placeholder="PT Contoh Logistik">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Kontak PIC</label>
                        <input type="text" name="contact_person" class="form-control" placeholder="Nama penanggung jawab">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Nomor Telepon</label>
                        <input type="text" name="phone" class="form-control" placeholder="08xxxxxxxxxx">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Email</label>
                        <input type="email" name="email" class="form-control" placeholder="supplier@email.com">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Alamat</label>
                        <textarea name="address" class="form-control" rows="2" placeholder="Alamat kantor / gudang..."></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-save me-1"></i> Simpan Supplier
                    </button>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Tabel Daftar Supplier -->
    <div class="<?= has_role('admin') ? 'col-lg-8' : 'col-12' ?>">
        <div class="card shadow-sm">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="fw-bold mb-0"><i class="bi bi-truck me-1"></i> Daftar Supplier</h6>
                <span class="badge bg-secondary"><?= count($suppliers) ?> Total</span>
            </div>
            <div class="card-body p-0 table-responsive">
                <table class="table table-hover table-striped mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 50px;">No</th>
                            <th>Nama Supplier</th>
                            <th>PIC & Kontak</th>
                            <th>Alamat</th>
                            <th class="text-center">Jml Produk Dipasok</th>
                            <?php if (has_role('admin')): ?>
                            <th class="text-center" style="width: 90px;">Aksi</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($suppliers)): ?>
                        <tr><td colspan="6" class="text-center py-4 text-muted">Belum ada data supplier.</td></tr>
                        <?php else: ?>
                            <?php foreach ($suppliers as $idx => $s): ?>
                            <tr>
                                <td><?= $idx + 1 ?></td>
                                <td class="fw-semibold"><?= e($s['name']) ?></td>
                                <td class="small">
                                    <div><strong>PIC:</strong> <?= isset($s['contact_person']) ? e($s['contact_person']) : '-' ?></div>
                                    <div class="text-muted"><i class="bi bi-phone"></i> <?= isset($s['phone']) ? e($s['phone']) : '-' ?> &bull; <i class="bi bi-envelope"></i> <?= isset($s['email']) ? e($s['email']) : '-' ?></div>
                                </td>
                                <td class="small text-muted"><?= isset($s['address']) ? e($s['address']) : '-' ?></td>
                                <td class="text-center">
                                    <span class="badge bg-secondary"><?= number_format($s['total_supplied_products']) ?> produk</span>
                                </td>
                                <?php if (has_role('admin')): ?>
                                <td class="text-center">
                                    <a href="?delete_id=<?= $s['id'] ?>" class="btn btn-outline-danger btn-sm" onclick="return confirm('Hapus supplier ini? Pasangan pada junction table product_suppliers akan dihapus otomatis (CASCADE).');">
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
