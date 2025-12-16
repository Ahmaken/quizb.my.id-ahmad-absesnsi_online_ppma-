<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../includes/init.php';

if (!check_auth()) {
    header("Location: ../index.php");
    exit();
}

require_once '../includes/navigation.php';
?>

<!DOCTYPE html>
<html lang="id" data-bs-theme="<?= $dark_mode ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Akses Ditolak - Sistem Absensi Online</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow">
                    <div class="card-header bg-danger text-white">
                        <h4 class="mb-0"><i class="bi bi-exclamation-octagon me-2"></i> Akses Ditolak</h4>
                    </div>
                    <div class="card-body text-center py-5">
                        <div class="mb-4">
                            <i class="bi bi-shield-lock" style="font-size: 5rem; color: #dc3545;"></i>
                        </div>
                        <h3 class="card-title mb-3">Anda tidak memiliki izin untuk mengakses halaman ini</h3>
                        <p class="card-text">Hanya administrator yang dapat mengakses halaman manajemen pengguna.</p>
                        <a href="dashboard.php" class="btn btn-primary mt-3">
                            <i class="bi bi-house-door me-1"></i> Kembali ke Dashboard
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>