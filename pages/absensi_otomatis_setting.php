<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../includes/init.php';

// Hanya admin dan staff yang bisa mengakses
if (!check_auth() || !in_array($_SESSION['role'], ['admin', 'staff'])) {
    header("Location: ../index.php");
    exit();
}

// PROSES UPDATE PENGATURAN
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_pengaturan'])) {
    $absensi_otomatis = isset($_POST['absensi_otomatis']) ? '1' : '0';
    $waktu_tenggang = $_POST['waktu_tenggang'] ?? '2';
    
    // Debug: Lihat data yang dikirim
    error_log("Absensi Otomatis: " . $absensi_otomatis);
    error_log("Waktu Tenggang: " . $waktu_tenggang);
    
    // Update absensi otomatis
    $sql1 = "UPDATE pengaturan_absensi_otomatis SET nilai = ? WHERE nama_pengaturan = 'absensi_otomatis_guru'";
    $stmt1 = $conn->prepare($sql1);
    $stmt1->bind_param("s", $absensi_otomatis);
    
    // Update waktu tenggang
    $sql2 = "UPDATE pengaturan_absensi_otomatis SET nilai = ? WHERE nama_pengaturan = 'waktu_tenggang_absensi'";
    $stmt2 = $conn->prepare($sql2);
    $stmt2->bind_param("s", $waktu_tenggang);
    
    $success = true;
    
    if (!$stmt1->execute()) {
        $success = false;
        error_log("Error update absensi_otomatis: " . $stmt1->error);
    }
    
    if (!$stmt2->execute()) {
        $success = false;
        error_log("Error update waktu_tenggang: " . $stmt2->error);
    }
    
    if ($success) {
        $_SESSION['message'] = "success|Pengaturan absensi otomatis berhasil diperbarui!";
    } else {
        $_SESSION['message'] = "danger|Gagal memperbarui pengaturan. Silakan coba lagi.";
    }
    
    header("Location: absensi_otomatis_setting.php");
    exit();
}

// Ambil pengaturan saat ini dengan error handling
$sql_pengaturan = "SELECT * FROM pengaturan_absensi_otomatis";
$result_pengaturan = $conn->query($sql_pengaturan);

$pengaturan = [];
if ($result_pengaturan) {
    if ($result_pengaturan->num_rows > 0) {
        while ($row = $result_pengaturan->fetch_assoc()) {
            $pengaturan[$row['nama_pengaturan']] = $row;
        }
    }
} else {
    error_log("Error query pengaturan: " . $conn->error);
    $_SESSION['message'] = "danger|Error mengambil data pengaturan: " . $conn->error;
}

$absensi_otomatis_aktif = $pengaturan['absensi_otomatis_guru']['nilai'] ?? '0';

// Debug: Lihat nilai yang didapat
error_log("Nilai absensi_otomatis_aktif: " . $absensi_otomatis_aktif);

require_once '../includes/navigation.php';
?>

<!DOCTYPE html>
<html lang="id" data-bs-theme="<?= $dark_mode ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengaturan Absensi Otomatis - Sistem Absensi Online</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .form-check-input:checked {
            background-color: #198754;
            border-color: #198754;
        }
        
        .status-badge {
            font-size: 0.9rem;
            padding: 0.5rem 1rem;
        }
        
        .card {
            border: none;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }
        
        /* Animasi smooth untuk toggle */
        .form-check-input {
            transition: all 0.3s ease;
        }
        
        .status-indicator {
            transition: all 0.3s ease;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <div class="row">
            <div class="d-flex justify-content-between align-items-center mb-0">
                <h2><i class="bi bi-gear me-0"></i> Pengaturan Absensi Otomatis</h2>
                <nav aria-label="breadcrumb" class="mb-0">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item active">Pengaturan Absensi Otomatis</li>
                    </ol>
                </nav>
                
            </div>
            
        </div>

        <?php if (isset($_SESSION['message'])): 
            list($type, $message) = explode('|', $_SESSION['message']);
            unset($_SESSION['message']);
        ?>
            <div class="alert alert-<?= $type ?> alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-toggle-on me-1"></i> 
                            Kontrol Absensi Otomatis Guru
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <div class="row align-items-center mb-3">
                                <div class="col-md-12">
                                    <h6>Status Absensi Otomatis</h6>
                                    <!-- Toggle Switch -->
                                    <div class="col-md-12 text-end">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" 
                                                   name="absensi_otomatis" 
                                                   value="1" 
                                                   id="flexSwitchCheckDefault" 
                                                   <?= $absensi_otomatis_aktif == '1' ? 'checked' : '' ?>
                                                   style="transform: scale(1.5);">
                                            <label class="form-check-label d-flex align-items-center ms-2" for="flexSwitchCheckDefault">
                                                <strong id="toggleLabel">
                                                    <?= $absensi_otomatis_aktif == '1' ? 'AKTIF' : 'NONAKTIF' ?>
                                                </strong>
                                            </label>
                                        </div>
                                    </div>
                                    
                                    <!-- Status Text -->
                                    <p class="text-muted mb-0 mt-2">
                                        <span id="statusText" class="status-indicator <?= $absensi_otomatis_aktif == '1' ? 'text-success' : 'text-danger' ?>">
                                            <i class="bi <?= $absensi_otomatis_aktif == '1' ? 'bi-check-circle-fill' : 'bi-x-circle-fill' ?>"></i> 
                                            <span id="statusMessage">
                                                <?php if ($absensi_otomatis_aktif == '1'): ?>
                                                    ABSENSI OTOMATIS AKTIF - Sistem akan mencatat absensi otomatis untuk guru yang memiliki jadwal tetapi tidak mengisi absensi.
                                                <?php else: ?>
                                                    ABSENSI OTOMATIS NONAKTIF - Sistem tidak akan mencatat absensi otomatis.
                                                <?php endif; ?>
                                            </span>
                                        </span>
                                    </p>
                                </div>
                            </div>
                            
                            <hr>
                            
                            <!-- Waktu Tenggang -->
                            <div class="mb-3">
                                <label class="form-label">Waktu Tenggang Absensi (Jam)</label>
                                <input type="number" class="form-control" 
                                       name="waktu_tenggang" 
                                       value="<?= htmlspecialchars($pengaturan['waktu_tenggang_absensi']['nilai'] ?? '2') ?>"
                                       min="1" max="24" 
                                       placeholder="Waktu tenggang dalam jam">
                                <div class="form-text">
                                    Waktu tenggang setelah jadwal dimulai sebelum sistem mencatat absensi otomatis (dalam jam).
                                </div>
                            </div>
                            
                            <!-- Submit Button -->
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <a href="absensi_guru.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-arrow-left me-1"></i> Kembali ke Absensi Guru
                                </a>
                                
                                <button type="submit" name="update_pengaturan" value="1" class="btn btn-primary">
                                    <i class="bi bi-save me-1"></i> Simpan Pengaturan
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Bagian lainnya tetap sama -->
            <div class="col-md-12">
                <!-- Status Cron Job -->
                <div class="card mt-4">
                    <div class="card-header bg-secondary text-white">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-clock me-1"></i> Status Cron Job
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span>Terakhir Diperiksa:</span>
                            <span class="badge bg-light text-dark"><?= date('d/m/Y H:i:s') ?></span>
                        </div>
                        
                        <hr>
                        
                        
                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <a href="cron_status.php" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-eye me-1"></i> Lihat Log Cron
                            </a>
                        </div>    
                        
                    </div>
                </div>
            </div>
            
            <div class="col-md-12">
                <div class="card mt-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-info-circle me-1"></i> Informasi
                        </h5>
                    </div>
                    <div class="card-body">
                        <h6>Cara Kerja Sistem:</h6>
                        <ul class="small">
                            <li>Sistem berjalan setiap hari jam 16:00 via cron job</li>
                            <li>Mencatat guru yang memiliki jadwal hari itu</li>
                            <li>Jika guru belum mengisi absensi, sistem akan mencatat status "Alpa"</li>
                            <li>Absensi otomatis ditandai dengan kolom <code>is_otomatis = 1</code></li>
                        </ul>
                        
                        <h6>Kapan Mematikan Sistem:</h6>
                        <ul class="small">
                            <li>Hari besar islam</li>
                            <li>Hari libur nasional</li>
                            <li>Perbaikan sistem</li>
                            <li>Kegiatan khusus pesantren</li>
                            <li>Maintenance database</li>
                        </ul>
                        
                        <div class="alert alert-warning mt-3">
                            <small>
                                <i class="bi bi-exclamation-triangle me-1"></i>
                                <strong>Perhatian:</strong> Pastikan untuk mematikan sistem saat hari libur atau kegiatan khusus.
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        $(document).ready(function() {
            // Fungsi untuk update status text
            function updateStatusText(isActive) {
                const statusText = $('#statusText');
                const statusMessage = $('#statusMessage');
                const toggleLabel = $('#toggleLabel');
                
                if (isActive) {
                    toggleLabel.text('AKTIF');
                    statusText.removeClass('text-danger').addClass('text-success');
                    statusText.find('i').removeClass('bi-x-circle-fill').addClass('bi-check-circle-fill');
                    statusMessage.text('ABSENSI OTOMATIS AKTIF - Sistem akan mencatat absensi otomatis untuk guru yang memiliki jadwal tetapi tidak mengisi absensi.');
                } else {
                    toggleLabel.text('NONAKTIF');
                    statusText.removeClass('text-success').addClass('text-danger');
                    statusText.find('i').removeClass('bi-check-circle-fill').addClass('bi-x-circle-fill');
                    statusMessage.text('ABSENSI OTOMATIS NONAKTIF - Sistem tidak akan mencatat absensi otomatis.');
                }
            }

            // Update label dan status teks saat toggle diubah
            $('input[name="absensi_otomatis"]').change(function() {
                const isChecked = $(this).is(':checked');
                updateStatusText(isChecked);
            });

            // Konfirmasi sebelum mengubah status
            $('form').submit(function(e) {
                const isChecked = $('input[name="absensi_otomatis"]').is(':checked');
                const action = isChecked ? 'mengaktifkan' : 'menonaktifkan';
                
                if (!confirm(`Anda yakin ingin ${action} absensi otomatis?`)) {
                    e.preventDefault();
                    return false;
                }
                return true;
            });
        });
    </script>
</body>
</html>