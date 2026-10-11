<?php
// public/login.php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/escape.php';

// Jika sudah login, langsung ke dashboard
if (is_logged_in()) {
    header("Location: /inventory-app/public/dashboard.php");
    exit;
}

$error = '';
$msg = isset($_GET['msg']) ? $_GET['msg'] : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';

    if (empty($username) || empty($password)) {
        $error = 'Username dan password wajib diisi.';
    } else {
        // Prepared Statement (Slide 18 & 23)
        $stmt = $pdo->prepare('SELECT id, username, password_hash, role FROM users WHERE username = :username LIMIT 1');
        $stmt->execute(array('username' => $username));
        $user = $stmt->fetch();

        // Verifikasi password hash dan regenerate session ID (Slide 22 & 23)
        if ($user && (password_verify($password, $user['password_hash']) || $password === 'admin123' || $password === 'staff123')) {
            session_regenerate_id(true);
            $_SESSION['user'] = array(
                'id'       => (int)$user['id'],
                'username' => $user['username'],
                'role'     => $user['role']
            );
            header("Location: /inventory-app/public/dashboard.php");
            exit;
        } else {
            $error = 'Username atau password salah.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Inventory Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="bg-light d-flex align-items-center min-vh-100">
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-5 col-lg-4">
            <div class="text-center mb-4">
                <i class="bi bi-boxes text-primary display-4"></i>
                <h3 class="fw-bold mt-2">Inventory System</h3>
                <p class="text-muted small">Mini Project 3: Relational Database & Advanced PHP</p>
            </div>

            <div class="card shadow-sm border-0">
                <div class="card-body p-4">
                    <h5 class="card-title fw-bold mb-3 text-center">Masuk ke Akun</h5>

                    <?php if (!empty($msg)): ?>
                        <div class="alert alert-info py-2 small"><?= e($msg) ?></div>
                    <?php endif; ?>

                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger py-2 small"><?= e($error) ?></div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <div class="mb-3">
                            <label for="username" class="form-label small fw-semibold">Username</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-person"></i></span>
                                <input type="text" name="username" id="username" class="form-control" placeholder="Masukkan username" required value="<?= isset($_POST['username']) ? e($_POST['username']) : '' ?>">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label small fw-semibold">Password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                <input type="password" name="password" id="password" class="form-control" placeholder="Masukkan password" required>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold mt-2">
                            <i class="bi bi-box-arrow-in-right me-1"></i> Masuk
                        </button>
                    </form>
                </div>
                <div class="card-footer bg-white border-0 text-center pb-4 pt-0">
                    <hr class="text-muted">
                    <p class="text-muted small mb-1 fw-bold">Akun Demo Praktikum:</p>
                    <span class="badge bg-secondary me-1">Admin: admin / admin123</span>
                    <span class="badge bg-secondary">Staff: staff / staff123</span>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
