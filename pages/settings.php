<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// pages/settings.php
require_once '../includes/init.php';

if (!check_auth()) {
    header("Location: ../index.php");
    exit();
}

$message = '';
$current_user = null;

// Ambil data user saat ini
$user_id = $_SESSION['user_id'];
$sql = "SELECT * FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$current_user = $result->fetch_assoc();

// PERBAIKAN: Ambil preferensi dark mode dari session
$dark_mode = $_SESSION['dark_mode'] ?? 0;

// PERBAIKAN: Fungsi untuk mendapatkan foto profil
function getProfilePhoto($user) {
    if (!empty($user['foto_profil']) && $user['foto_profil'] != 'default-avatar.png') {
        $photo_path = "../uploads/profil/" . $user['foto_profil'];
        if (file_exists($photo_path)) {
            return $photo_path;
        }
    }
    return "../assets/img/default-avatar.png";
}

// Proses update pengaturan
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // PERBAIKAN: Struktur if yang benar
    if (isset($_POST['update_foto'])) {
        // Proses upload foto profil
        if (isset($_FILES['foto_profil']) && $_FILES['foto_profil']['error'] == UPLOAD_ERR_OK) {
            $target_dir = "../uploads/profil/";
            if (!file_exists($target_dir)) {
                mkdir($target_dir, 0777, true);
            }
            
            $file_ext = pathinfo($_FILES['foto_profil']['name'], PATHINFO_EXTENSION);
            $new_filename = "user_" . $user_id . '_' . time() . '.' . $file_ext;
            $target_file = $target_dir . $new_filename;
            
            // Hapus foto lama jika ada dan bukan default
            if (!empty($current_user['foto_profil']) && $current_user['foto_profil'] != 'default-avatar.png') {
                $old_file = $target_dir . $current_user['foto_profil'];
                if (file_exists($old_file)) {
                    unlink($old_file);
                }
            }
            
            if (move_uploaded_file($_FILES['foto_profil']['tmp_name'], $target_file)) {
                $foto_profil = $new_filename;
                
                $sql = "UPDATE users SET foto_profil = ? WHERE id = ?";
                $stmt = $conn->prepare($sql);
                // PERBAIKAN: Cek apakah prepare berhasil
                if ($stmt === false) {
                    $message = "danger|Error: " . $conn->error;
                } else {
                    $stmt->bind_param("si", $foto_profil, $user_id);
                    
                    if ($stmt->execute()) {
                        // Update session
                        $_SESSION['foto_profil'] = $foto_profil;
                        $message = "success|Foto profil berhasil diperbarui!";
                        
                        // Refresh data user
                        $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
                        $stmt->bind_param("i", $user_id);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $current_user = $result->fetch_assoc();
                    } else {
                        $message = "danger|Error: " . $stmt->error;
                    }
                }
            } else {
                $message = "danger|Error: Gagal mengupload foto profil.";
            }
        } else {
            $message = "danger|Silakan pilih file foto yang valid";
        }
    } 
    // Handle form update data akun
    elseif (isset($_POST['update_account'])) {
        $username = $_POST['username'] ?? '';
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        // Validasi hanya untuk form akun
        if (empty($username) || empty($current_password)) {
            $message = "danger|Username dan password saat ini harus diisi!";
        } else {
            // Validasi password saat ini
            if (!password_verify($current_password, $current_user['password'])) {
                $message = "danger|Password saat ini salah!";
            } else {
                $update_username = false;
                $update_password = false;

                // Update username jika berubah
                if ($username !== $current_user['username']) {
                    $update_username = true;
                }
                
                // Update password jika diisi
                if (!empty($new_password)) {
                    if ($new_password !== $confirm_password) {
                        $message = "danger|Password baru dan konfirmasi password tidak cocok!";
                    } else {
                        $update_password = true;
                    }
                }

                // Jika tidak ada error, lakukan update
                if (empty($message)) {
                    // Mulai transaksi
                    $conn->begin_transaction();
                    try {
                        // Update username
                        if ($update_username) {
                            $sql = "UPDATE users SET username = ? WHERE id = ?";
                            $stmt = $conn->prepare($sql);
                            if ($stmt === false) {
                                throw new Exception($conn->error);
                            }
                            $stmt->bind_param("si", $username, $user_id);
                            $stmt->execute();
                            $_SESSION['username'] = $username;
                        }

                        // Update password
                        if ($update_password) {
                            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                            $sql = "UPDATE users SET password = ? WHERE id = ?";
                            $stmt = $conn->prepare($sql);
                            if ($stmt === false) {
                                throw new Exception($conn->error);
                            }
                            $stmt->bind_param("si", $hashed_password, $user_id);
                            $stmt->execute();
                        }
                        
                        // Commit transaksi
                        $conn->commit();
                        
                        $message = "success|Pengaturan akun berhasil diperbarui!";
                        
                        // Refresh data user
                        $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
                        $stmt->bind_param("i", $user_id);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $current_user = $result->fetch_assoc();
                    } catch (Exception $e) {
                        // Rollback transaksi jika terjadi error
                        $conn->rollback();
                        $message = "danger|Error: " . $e->getMessage();
                    }
                }
            }
        }
    }
    // Handle form update dark mode
    elseif (isset($_POST['update_dark_mode'])) {
        $dark_mode = isset($_POST['dark_mode']) ? 1 : 0;
        
        $sql = "UPDATE users SET dark_mode = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            $message = "danger|Error: " . $conn->error;
        } else {
            $stmt->bind_param("ii", $dark_mode, $user_id);
            
            if ($stmt->execute()) {
                $_SESSION['dark_mode'] = $dark_mode;
                // PERBAIKAN: Simpan preferensi di cookie untuk halaman login
                setcookie('dark_mode_pref', $dark_mode, time() + (86400 * 30), "/");
                $message = "success|Mode gelap berhasil diperbarui!";
                
                // Refresh data user
                $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $current_user = $result->fetch_assoc();
            } else {
                $message = "danger|Error: " . $stmt->error;
            }
        }
    }
}

require_once '../includes/navigation.php';

// Tampilkan pesan jika ada
if ($message) {
    list($type, $text) = explode('|', $message, 2);
    echo '<div class="alert alert-'.$type.' alert-dismissible fade show" role="alert">';
    echo $text;
    echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
    echo '</div>';
}
?>

<!DOCTYPE html>
<html lang="id" data-bs-theme="<?= $dark_mode ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengaturan Akun - Sistem Absensi Online</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.5/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    
    <!-- Loading Animation Styles -->
    <style>
        #loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.9);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            transition: opacity 0.3s ease-out;
        }
        
        .loading-content {
            text-align: center;
        }
        
        .loading-spinner {
            width: 364px;
            height: 364px;
            margin-bottom: 15px;
        }
        
        .loading-text {
            font-size: 18px;
            color: #333;
        }
        
        .theme-switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 34px;
        }
        
        .theme-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .theme-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 34px;
        }
        
        .theme-slider:before {
            position: absolute;
            content: "";
            height: 26px;
            width: 26px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        
        input:checked + .theme-slider {
            background-color: #2196F3;
        }
        
        input:checked + .theme-slider:before {
            transform: translateX(26px);
        }
        
        .theme-label {
            margin-left: 10px;
            vertical-align: middle;
        }
        
        /* Dark mode styles */
        [data-bs-theme="dark"] {
            --bs-body-bg: #121212;
            --bs-body-color: #f8f9fa;
            --bs-card-bg: #1e1e1e;
            --bs-card-color: #f8f9fa;
            --bs-border-color: #444;
        }
        
        [data-bs-theme="dark"] .card {
            background-color: var(--bs-card-bg);
            color: var(--bs-card-color);
            border-color: var(--bs-border-color);
        }
        
        [data-bs-theme="dark"] .card-header {
            background-color: #2e7d32;
        }
        
        [data-bs-theme="dark"] .form-control,
        [data-bs-theme="dark"] .form-select {
            background-color: #333;
            color: #fff;
            border-color: #444;
        }
        
        [data-bs-theme="dark"] .btn-outline-primary {
            border-color: #0d6efd;
            color: #0d6efd;
        }
        
        [data-bs-theme="dark"] .btn-outline-primary:hover {
            background-color: #0d6efd;
            color: #fff;
        }

        /* pages/settings.php */
        [data-bs-theme="dark"] .theme-label {
            color: #f8f9fa !important;
        }
    </style>
</head>
<body>
    <!-- Loading Overlay -->
    <div id="loading-overlay">
        <div class="loading-content">
            <img src="../assets/img/logo_ppma_loading.gif" class="loading-spinner" alt="Loading...">
        </div>
    </div>
    
    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-gear me-2"></i> Pengaturan Akun</h2>
        </div>

        <div class="row">
            <!-- Di bagian HTML, perbaiki tampilan foto profil -->
            <div class="col-md-4 mb-4">
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h5 class="card-title mb-0"><i class="bi bi-person-circle me-2"></i> Foto Profil</h5>
                    </div>
                    <div class="card-body text-center">
                        <!-- PERBAIKAN: Gunakan fungsi getProfilePhoto -->
                        <img src="<?= getProfilePhoto($current_user) ?>" class="rounded-circle mb-3" width="150" height="150" alt="Foto Profil">
                        
                        <form method="POST" action="" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label class="form-label">Ubah Foto Profil</label>
                                <input type="file" class="form-control" name="foto_profil" accept="image/*">
                            </div>
                            <button type="submit" name="update_foto" class="btn btn-secondary w-100">Simpan Foto</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-8">
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h5 class="card-title mb-0"><i class="bi bi-person-badge me-2"></i> Informasi Akun</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label class="form-label">Username</label>
                                <input type="text" class="form-control" name="username" value="<?= htmlspecialchars($current_user['username']) ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Password Saat Ini</label>
                                <input type="password" class="form-control" name="current_password" required>
                                <small class="text-muted">Diperlukan untuk verifikasi perubahan</small>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Password Baru</label>
                                    <input type="password" class="form-control" name="new_password">
                                    <small class="text-muted">Biarkan kosong jika tidak ingin mengubah</small>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Konfirmasi Password Baru</label>
                                    <input type="password" class="form-control" name="confirm_password">
                                </div>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" name="update_account" class="btn btn-secondary">Simpan Perubahan</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mt-4">
            <div class="col-md-12">
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h5 class="card-title mb-0"><i class="bi bi-display me-2"></i> Tampilan</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="" id="darkModeForm">
                            <div class="mb-3">
                                <label class="form-label d-flex align-items-center">
                                    <label class="theme-switch">
                                        <input type="checkbox" name="dark_mode" <?= $dark_mode ? 'checked' : '' ?>>
                                        <span class="theme-slider"></span>
                                    </label>
                                    <span class="theme-label">Mode Gelap</span>
                                </label>
                                <small class="text-muted">Ubah tampilan aplikasi menjadi mode gelap</small>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" name="update_dark_mode" class="btn btn-secondary">Simpan Perubahan</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Preview foto sebelum upload
        document.querySelector('input[name="foto_profil"]')?.addEventListener('change', function(e) {
            if (this.files && this.files[0]) {
                const reader = new FileReader();
                const preview = document.querySelector('.card-body.text-center img');
                
                reader.onload = function(e) {
                    preview.src = e.target.result;
                }
                
                reader.readAsDataURL(this.files[0]);
            }
        });

        // Reload setelah update dark mode
        document.getElementById('darkModeForm')?.addEventListener('submit', function(e) {
            setTimeout(() => {
                location.reload();
            }, 500);
        });
        
        // Fungsi untuk menyembunyikan loading overlay
        function hideLoading() {
            $('#loading-overlay').fadeOut(300, function() {
                $(this).remove();
            });
        }
    
        // Tunggu sampai DOM siap dan semua asset termasuk gambar selesai dimuat
        $(window).on('load', function() {
            // Beri sedikit delay agar animasi terlihat (opsional)
            setTimeout(hideLoading, 500);
        });
    
        // Fallback: Sembunyikan loading setelah 5 detik maksimal
        setTimeout(hideLoading, 5000);
    </script>
</body>
</html>