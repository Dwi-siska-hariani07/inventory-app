<?php
// public/users/index.php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/escape.php';
require_once __DIR__ . '/../../helpers/validation.php';

// Otorisasi Ketat Admin (Slide 24 & Business Rule: Staff tidak boleh mengakses administrasi user)
require_role('admin');

$page_title = 'Kelola Pengguna Sistem';

// Query Users JOIN One-to-One dengan User Profiles (Slide 06)
$query = "
    SELECT u.id, u.username, u.role, u.created_at,
           up.full_name, up.phone, up.address
    FROM users u
    LEFT JOIN user_profiles up ON up.user_id = u.id
    ORDER BY u.id ASC
";
$users = $pdo->query($query)->fetchAll();

$success = isset($_GET['success']) ? $_GET['success'] : '';
$error = isset($_GET['error']) ? $_GET['error'] : '';

// Handle Tambah User Baru
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_user') {
    $username  = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password  = isset($_POST['password']) ? $_POST['password'] : '';
    $role      = in_array(isset($_POST['role']) ? $_POST['role'] : '', array('admin', 'staff')) ? $_POST['role'] : 'staff';
    $full_name = isset($_POST['full_name']) ? trim($_POST['full_name']) : '';
    $phone     = isset($_POST['phone']) ? trim($_POST['phone']) : '';
    $address   = isset($_POST['address']) ? trim($_POST['address']) : '';

    $errs = array();
    if ($err = validate_required_string($username, 'Username', 3, 50)) $errs[] = $err;
    if ($err = validate_required_string($password, 'Password', 6, 100)) $errs[] = $err;
    if ($err = validate_required_string($full_name, 'Nama Lengkap', 2, 100)) $errs[] = $err;

    // Cek username unik
    if (empty($errs)) {
        $stmt = $pdo->prepare("SELECT COUNT(id) FROM users WHERE username = :u");
        $stmt->execute(array('u' => $username));
        if ($stmt->fetchColumn() > 0) {
            $errs[] = "Username '$username' sudah terdaftar!";
        }
    }

    if (!empty($errs)) {
        $error = implode('<br>', $errs);
    } else {
        try {
            $pdo->beginTransaction();

            // Hash password dengan bcrypt (Slide 23)
            $password_hash = password_hash($password, PASSWORD_BCRYPT);

            $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, role) VALUES (:u, :p, :r)");
            $stmt->execute(array(
                'u' => $username,
                'p' => $password_hash,
                'r' => $role
            ));
            $new_user_id = (int)$pdo->lastInsertId();

            // Insert ke tabel profil (One-to-One dengan UNIQUE user_id - Slide 06)
            $stmt = $pdo->prepare("
                INSERT INTO user_profiles (user_id, full_name, phone, address)
                VALUES (:uid, :fname, :phone, :addr)
            ");
            $stmt->execute(array(
                'uid'   => $new_user_id,
                'fname' => $full_name,
                'phone' => $phone,
                'addr'  => $address
            ));

            $pdo->commit();
            header("Location: /inventory-app/public/users/index.php?success=" . urlencode("Pengguna baru ($username) berhasil didaftarkan!"));
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Gagal membuat pengguna: " . $e->getMessage();
        }
    }
}

// Handle Hapus User
if (isset($_GET['delete_id'])) {
    $del_id = (int)$_GET['delete_id'];
    $currentUser = current_user();
    if ($del_id === $currentUser['id']) {
        $error = "Anda tidak dapat menghapus akun Anda sendiri yang sedang aktif digunakan!";
    } else {
        try {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = :id");
            $stmt->execute(array('id' => $del_id));
            header("Location: /inventory-app/public/users/index.php?success=" . urlencode("Pengguna berhasil dihapus!"));
            exit;
        } catch (PDOException $e) {
            $error = "Gagal menghapus user: User ini memiliki riwayat transaksi di stock movements (Integritas RESTRICT).";
        }
    }
}

include __DIR__ . '/../../views/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-0">Administrasi Pengguna & Role</h3>
        <p class="text-muted small mb-0">Relasi One-to-One (<code>users &harr; user_profiles</code>) dan Hak Akses Server-Side (Slide 06 & 24)</p>
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
        <i class="bi bi-exclamation-triangle me-1"></i> <?= $error ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row g-4">
    <!-- Form Tambah User -->
    <div class="col-lg-4">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3">
                <h6 class="fw-bold mb-0"><i class="bi bi-person-plus-fill text-primary me-1"></i> Tambah Pengguna Baru</h6>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <input type="hidden" name="action" value="create_user">
                    
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Username <span class="text-danger">*</span></label>
                        <input type="text" name="username" class="form-control" required placeholder="username_login">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Password <span class="text-danger">*</span></label>
                        <input type="password" name="password" class="form-control" required placeholder="Minimal 6 karakter">
                        <small class="text-muted">Akan di-hash otomatis dengan Bcrypt</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Role / Hak Akses <span class="text-danger">*</span></label>
                        <select name="role" class="form-select" required>
                            <option value="staff">Staff (Kelola Transaksi & Lihat Produk)</option>
                            <option value="admin">Admin (Akses Penuh Semua Modul)</option>
                        </select>
                    </div>

                    <hr class="my-3 text-muted">
                    <h6 class="text-muted small fw-bold mb-2">Data Profil (Tabel user_profiles)</h6>

                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Nama Lengkap <span class="text-danger">*</span></label>
                        <input type="text" name="full_name" class="form-control" required placeholder="Nama asli personel">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold small">No. HP / Telepon</label>
                        <input type="text" name="phone" class="form-control" placeholder="08xxxxxxxx">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Alamat / Bagian</label>
                        <textarea name="address" class="form-control" rows="2" placeholder="Unit kerja atau alamat..."></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-person-check me-1"></i> Simpan Pengguna
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Tabel User -->
    <div class="col-lg-8">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="fw-bold mb-0"><i class="bi bi-people-fill me-1"></i> Daftar Akun Pengguna</h6>
                <span class="badge bg-secondary"><?= count($users) ?> Akun</span>
            </div>
            <div class="card-body p-0 table-responsive">
                <table class="table table-hover table-striped mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 50px;">ID</th>
                            <th>Username</th>
                            <th>Nama Lengkap & Kontak</th>
                            <th class="text-center">Role</th>
                            <th class="text-center" style="width: 90px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $curr = current_user(); foreach ($users as $u): ?>
                        <tr>
                            <td class="text-muted">#<?= $u['id'] ?></td>
                            <td class="fw-bold">
                                <?= e($u['username']) ?>
                                <?php if ($u['id'] === $curr['id']): ?>
                                    <span class="badge bg-info text-dark ms-1">Anda</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div><strong><?= isset($u['full_name']) ? e($u['full_name']) : '-' ?></strong></div>
                                <small class="text-muted">
                                    <i class="bi bi-telephone"></i> <?= isset($u['phone']) ? e($u['phone']) : '-' ?> &bull; <?= isset($u['address']) ? e($u['address']) : '-' ?>
                                </small>
                            </td>
                            <td class="text-center">
                                <?php if ($u['role'] === 'admin'): ?>
                                    <span class="badge bg-danger">ADMIN</span>
                                <?php else: ?>
                                    <span class="badge bg-success">STAFF</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if ($u['id'] !== $curr['id']): ?>
                                    <a href="?delete_id=<?= $u['id'] ?>" class="btn btn-outline-danger btn-sm" onclick="return confirm('Apakah Anda yakin ingin menghapus user ini?');">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted small">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../views/footer.php'; ?>
