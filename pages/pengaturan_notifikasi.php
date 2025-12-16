<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../includes/init.php';

if (!check_auth() || ($_SESSION['role'] != 'admin' && $_SESSION['role'] != 'staff')) {
    header("Location: ../index.php");
    exit();
}

$message = '';

// Proses update pengaturan
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $pengaturan = [
        'notifikasi_aktif' => $_POST['notifikasi_aktif'] ?? '0',
        'waktu_tampil_notifikasi' => intval($_POST['waktu_tampil_notifikasi']),
        'batas_waktu_notifikasi' => intval($_POST['batas_waktu_notifikasi']),
        'refresh_otomatis' => intval($_POST['refresh_otomatis'])
    ];
    
    foreach ($pengaturan as $nama => $nilai) {
        $sql = "INSERT INTO pengaturan_notifikasi (nama_pengaturan, nilai) 
                VALUES (?, ?) 
                ON DUPLICATE KEY UPDATE nilai = ?, updated_at = CURRENT_TIMESTAMP";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("sss", $nama, $nilai, $nilai);
            $stmt->execute();
            $stmt->close();
        }
    }
    
    $message = "success|Pengaturan notifikasi berhasil disimpan!";
}

// Ambil pengaturan saat ini
$pengaturan_sekarang = [];
$sql = "SELECT nama_pengaturan, nilai FROM pengaturan_notifikasi";
$result = $conn->query($sql);
while ($row = $result->fetch_assoc()) {
    $pengaturan_sekarang[$row['nama_pengaturan']] = $row['nilai'];
}

require_once '../includes/navigation.php';
?>

<!DOCTYPE html>
<html lang="id" data-bs-theme="<?= $dark_mode ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengaturan Notifikasi - Sistem Absensi Online</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .notification-badge {
            position: relative;
            display: inline-block;
        }
        .notification-count {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #dc3545;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 0.7rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .notification-panel {
            transition: all 0.3s ease;
            max-height: 400px;
            overflow-y: auto;
        }
        .notification-panel.collapsed {
            max-height: 40px;
            overflow: hidden;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-bell me-2"></i> Pengaturan Notifikasi</h2>
            <a href="dashboard.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left me-1"></i> Kembali ke Dashboard
            </a>
        </div>

        <?php if ($message): ?>
        <div class="alert alert-<?= explode('|', $message)[0] ?> alert-dismissible fade show">
            <?= explode('|', $message)[1] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- Pengaturan Notifikasi -->
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white">
                <h5 class="card-title mb-0"><i class="bi bi-sliders me-1"></i> Atur Waktu Notifikasi</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Status Notifikasi</label>
                            <select class="form-select" name="notifikasi_aktif">
                                <option value="1" <?= ($pengaturan_sekarang['notifikasi_aktif'] ?? '1') == '1' ? 'selected' : '' ?>>Aktif</option>
                                <option value="0" <?= ($pengaturan_sekarang['notifikasi_aktif'] ?? '1') == '0' ? 'selected' : '' ?>>Nonaktif</option>
                            </select>
                            <small class="form-text text-muted">Aktifkan atau nonaktifkan notifikasi jadwal belum diisi</small>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Refresh Otomatis</label>
                            <div class="input-group">
                                <input type="number" class="form-control" name="refresh_otomatis" 
                                       value="<?= $pengaturan_sekarang['refresh_otomatis'] ?? '5' ?>" min="1" max="60">
                                <span class="input-group-text">menit</span>
                            </div>
                            <small class="form-text text-muted">Interval pengecekan notifikasi otomatis</small>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Notifikasi Muncul Setelah</label>
                            <div class="input-group">
                                <input type="number" class="form-control" name="waktu_tampil_notifikasi" 
                                       value="<?= $pengaturan_sekarang['waktu_tampil_notifikasi'] ?? '1' ?>" min="0" max="24">
                                <span class="input-group-text">jam</span>
                            </div>
                            <small class="form-text text-muted">Waktu notifikasi mulai muncul setelah jadwal dimulai (0 = langsung muncul)</small>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Batas Waktu Notifikasi</label>
                            <div class="input-group">
                                <input type="number" class="form-control" name="batas_waktu_notifikasi" 
                                       value="<?= $pengaturan_sekarang['batas_waktu_notifikasi'] ?? '24' ?>" min="1" max="168">
                                <span class="input-group-text">jam</span>
                            </div>
                            <small class="form-text text-muted">Notifikasi akan hilang setelah waktu ini (maksimal 7 hari/168 jam)</small>
                        </div>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>
                        <strong>Contoh:</strong> Jika jadwal dimulai jam 08:00, notifikasi muncul setelah 1 jam (09:00) dan akan hilang setelah 24 jam (besok jam 09:00)
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save me-1"></i> Simpan Pengaturan
                    </button>
                </form>
            </div>
        </div>
        
        <!-- Panel Notifikasi Aktif -->
        <!--<div class="card shadow-sm mb-4">-->
        <!--    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">-->
        <!--        <h5 class="card-title mb-0">-->
        <!--            <i class="bi bi-bell me-1"></i> Notifikasi Aktif-->
        <!--            <span class="notification-badge ms-2">-->
        <!--                <i class="bi bi-bell-fill"></i>-->
        <!--                <span class="notification-count">3</span>-->
        <!--            </span>-->
        <!--        </h5>-->
        <!--        <button class="btn btn-sm btn-light" id="toggleNotifications">-->
        <!--            <i class="bi bi-chevron-down" id="toggleIcon"></i>-->
        <!--        </button>-->
        <!--    </div>-->
        <!--    <div class="card-body notification-panel" id="notificationPanel">-->
        <!--        <div class="list-group">-->
        <!--            <a href="#" class="list-group-item list-group-item-action">-->
        <!--                <div class="d-flex w-100 justify-content-between">-->
        <!--                    <h6 class="mb-1">Jadwal Madin belum diisi</h6>-->
        <!--                    <small>5 menit lalu</small>-->
        <!--                </div>-->
        <!--                <p class="mb-1">Kelas VII-A - Matematika (08:00-09:30)</p>-->
        <!--            </a>-->
        <!--            <a href="#" class="list-group-item list-group-item-action">-->
        <!--                <div class="d-flex w-100 justify-content-between">-->
        <!--                    <h6 class="mb-1">Absensi belum lengkap</h6>-->
        <!--                    <small>10 menit lalu</small>-->
        <!--                </div>-->
        <!--                <p class="mb-1">2 santri belum diabsen pada pelajaran Fiqih</p>-->
        <!--            </a>-->
        <!--            <a href="#" class="list-group-item list-group-item-action">-->
        <!--                <div class="d-flex w-100 justify-content-between">-->
        <!--                    <h6 class="mb-1">Izin menunggu persetujuan</h6>-->
        <!--                    <small>15 menit lalu</small>-->
        <!--                </div>-->
        <!--                <p class="mb-1">3 permohonan izin perlu ditinjau</p>-->
        <!--            </a>-->
        <!--        </div>-->
        <!--    </div>-->
        <!--</div>-->

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const toggleBtn = document.getElementById('toggleNotifications');
            const notificationPanel = document.getElementById('notificationPanel');
            const toggleIcon = document.getElementById('toggleIcon');
            
            // Set awal panel notifikasi collapsed
            notificationPanel.classList.add('collapsed');
            
            toggleBtn.addEventListener('click', function() {
                notificationPanel.classList.toggle('collapsed');
                
                if (notificationPanel.classList.contains('collapsed')) {
                    toggleIcon.className = 'bi bi-chevron-down';
                } else {
                    toggleIcon.className = 'bi bi-chevron-up';
                }
            });
        });
    </script>
</body>
</html>