<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>403 Terlarang - Akses Ditolak</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center min-vh-100">
    <div class="container text-center py-5">
        <div class="card shadow-sm border-danger mx-auto" style="max-width: 500px;">
            <div class="card-body p-4">
                <span class="badge bg-danger fs-6 mb-3">HTTP 403 Forbidden</span>
                <h1 class="display-5 text-danger fw-bold">Akses Ditolak!</h1>
                <p class="lead text-muted mt-3">
                    Role akun Anda tidak memiliki hak akses untuk membuka halaman ini. Fitur ini hanya diperuntukkan bagi <strong>Administrator</strong>.
                </p>
                <div class="alert alert-secondary text-start small mb-4">
                    <strong>Business Rule 09:</strong> <em>"Hak akses diterapkan pada server, bukan hanya disembunyikan dari menu. Staff tidak boleh mengakses administrasi user."</em>
                </div>
                <a href="/inventory-app/public/dashboard.php" class="btn btn-primary px-4">
                    &larr; Kembali ke Dashboard
                </a>
            </div>
        </div>
    </div>
</body>
</html>
