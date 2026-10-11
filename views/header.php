<?php
// views/header.php
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/escape.php';

$user = current_user();
$base_url = '/inventory-app/public';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($page_title) ? e($page_title) . ' - ' : '' ?>Inventory Management System</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body {
            background-color: #f8fafc;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
        }
        .navbar-brand {
            font-weight: 700;
            letter-spacing: -0.5px;
        }
        .card {
            border: 1px solid #e2e8f0;
            border-radius: 0.75rem;
        }
        .card-header {
            background-color: #ffffff;
            border-bottom: 1px solid #e2e8f0;
            font-weight: 600;
        }
        .table > :not(caption) > * > * {
            padding: 0.8rem 0.75rem;
        }
        .badge-low-stock {
            background-color: #ef4444;
            color: #ffffff;
        }
    </style>
</head>
<body class="d-flex flex-column min-vh-100">

<nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top shadow-sm">
    <div class="container-fluid px-4">
        <a class="navbar-brand d-flex align-items-center gap-2" href="<?= $base_url ?>/dashboard.php">
            <i class="bi bi-boxes text-info fs-4"></i>
            <span>Inventory System</span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navContent">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navContent">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'dashboard.php') !== false ? 'active text-info fw-semibold' : '' ?>" href="<?= $base_url ?>/dashboard.php">
                        <i class="bi bi-speedometer2 me-1"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= strpos($_SERVER['PHP_SELF'], '/products/') !== false ? 'active text-info fw-semibold' : '' ?>" href="<?= $base_url ?>/products/index.php">
                        <i class="bi bi-box-seam me-1"></i> Produk
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= strpos($_SERVER['PHP_SELF'], '/categories/') !== false ? 'active text-info fw-semibold' : '' ?>" href="<?= $base_url ?>/categories/index.php">
                        <i class="bi bi-tags me-1"></i> Kategori
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= strpos($_SERVER['PHP_SELF'], '/suppliers/') !== false ? 'active text-info fw-semibold' : '' ?>" href="<?= $base_url ?>/suppliers/index.php">
                        <i class="bi bi-truck me-1"></i> Supplier
                    </a>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?= strpos($_SERVER['PHP_SELF'], '/stock/') !== false ? 'active text-info fw-semibold' : '' ?>" href="#" id="stockDrop" role="button" data-bs-toggle="dropdown">
                        <i class="bi bi-arrow-left-right me-1"></i> Transaksi Stok
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="<?= $base_url ?>/stock/history.php"><i class="bi bi-clock-history me-2"></i> Histori Transaksi</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-success" href="<?= $base_url ?>/stock/in.php"><i class="bi bi-box-arrow-in-down me-2"></i> Barang Masuk (IN)</a></li>
                        <li><a class="dropdown-item text-danger" href="<?= $base_url ?>/stock/out.php"><i class="bi bi-box-arrow-up-right me-2"></i> Barang Keluar (OUT)</a></li>
                    </ul>
                </li>
                <?php if (has_role('admin')): ?>
                <li class="nav-item">
                    <a class="nav-link <?= strpos($_SERVER['PHP_SELF'], '/users/') !== false ? 'active text-info fw-semibold' : '' ?>" href="<?= $base_url ?>/users/index.php">
                        <i class="bi bi-people me-1"></i> Kelola User <span class="badge bg-danger ms-1">Admin</span>
                    </a>
                </li>
                <?php endif; ?>
            </ul>

            <ul class="navbar-nav ms-auto align-items-lg-center">
                <?php if ($user): ?>
                    <li class="nav-item me-3 text-light d-flex align-items-center gap-2">
                        <span class="badge bg-secondary"><?= strtoupper(e($user['role'])) ?></span>
                        <span class="fw-medium"><i class="bi bi-person-circle me-1"></i> <?= isset($user['username']) ? e($user['username']) : 'User' ?></span>
                    </li>
                    <li class="nav-item">
                        <a href="<?= $base_url ?>/logout.php" class="btn btn-outline-danger btn-sm">
                            <i class="bi bi-box-arrow-right"></i> Logout
                        </a>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a href="<?= $base_url ?>/login.php" class="btn btn-primary btn-sm">
                            <i class="bi bi-box-arrow-in-right"></i> Login
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<main class="container-fluid px-4 py-4 flex-grow-1">
