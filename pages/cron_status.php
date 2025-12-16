<?php
session_start();
require_once '../includes/init.php';

// Hanya admin dan staff yang bisa mengakses
if (!check_auth() || !in_array($_SESSION['role'], ['admin', 'staff'])) {
    header("Location: ../index.php");
    exit();
}

// Baca file log
$log_content = 'Log tidak ditemukan atau kosong.';
if (file_exists('cron_absensi_log.txt')) {
    $log_content = file_get_contents('cron_absensi_log.txt');
    if (empty($log_content)) {
        $log_content = 'Log file ada tetapi kosong.';
    }
}

require_once '../includes/navigation.php';
?>

<!DOCTYPE html>
<html lang="id" data-bs-theme="<?= $dark_mode ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log Cron Job - Sistem Absensi Online</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
</head>
<body>
    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3><i class="bi bi-clock-history me-0"></i> Log Cron Job Absensi Otomatis</h3>
            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                <a href="absensi_otomatis_setting.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i> Kembali ke Pengaturan
                </a>
                <a href="cron_status.php?refresh=1" class="btn btn-outline-primary">
                    <i class="bi bi-arrow-clockwise me-1"></i> Refresh
                </a>
                
            </div>
        </div>

        <div class="card">
            <div class="card-header bg-dark text-white">
                <h5 class="card-title mb-0">
                    <i class="bi bi-file-text me-1"></i> 
                    Riwayat Eksekusi Cron Job
                </h5>
            </div>
            <div class="card-body">
                
                <div class="d-grid gap-2 d-md-flex align-items-center justify-content-md-end">
                    <a href="cron_absensi_guru_per_jadwal.php" class="btn btn-outline-info">
                        <i class="bi bi-file-text me-1"></i> cron absensi guru per jadwal
                    </a>
                    <a href="cron_absensi_guru_per_jadwal_log.txt" class="btn btn-outline-success">
                        <i class="bi bi-file-text me-1"></i> cron absensi guru per jadwal log
                    </a>
                </div>
            </div>
            <div class="card-footer text-muted">
                <small>
                    <i class="bi bi-info-circle me-1"></i>
                    File log: <code>cron_absensi_log.txt</code> | 
                    Terakhir diupdate: <?= date('d/m/Y H:i:s') ?>
                </small>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>