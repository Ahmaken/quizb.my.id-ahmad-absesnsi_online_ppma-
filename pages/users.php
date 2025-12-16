<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// pages/users.php
require_once '../includes/init.php';

if (!check_auth()) {
    header("Location: ../index.php");
    exit();
}

// Hanya admin yang boleh akses manajemen user
if ($_SESSION['role'] !== 'admin') {
    header("Location: unauthorized.php");
    exit();
}

// ===== FILTER DATA UNTUK ROLE GURU =====
$guru_id = null;
if ($_SESSION['role'] === 'guru') {
    // Cari guru_id berdasarkan user_id
    $sql_guru = "SELECT guru_id FROM guru WHERE user_id = ?";
    $stmt_guru = $conn->prepare($sql_guru);
    $stmt_guru->bind_param("i", $_SESSION['user_id']);
    $stmt_guru->execute();
    $result_guru = $stmt_guru->get_result();
    
    if ($result_guru->num_rows > 0) {
        $guru_data = $result_guru->fetch_assoc();
        $guru_id = $guru_data['guru_id'];
    }
}

// Hanya admin yang boleh akses manajemen user
if ($_SESSION['role'] !== 'admin') {
    header("Location: unauthorized.php");
    exit();
}

$message = '';
$current_user = null;
$show_modal = false; // TAMBAHKAN VARIABLE INI

// PERBAIKAN: Fungsi untuk mendapatkan foto profil
function getProfilePhoto($user) {
    if (!empty($user['foto_profil']) && $user['foto_profil'] != 'default-avatar.png') {
        $photo_path = "../uploads/foto_profil/" . $user['foto_profil'];
        if (file_exists($photo_path)) {
            return $photo_path;
        }
    }
    return "../assets/img/default-avatar.png";
}

// Fungsi untuk memproses update pengguna
function processUserUpdate($conn, $id, $username, $role, $kelas_id, $murid_id, $new_password, $current_user) {
    // Validasi username unik (kecuali untuk dirinya sendiri)
    $sql_cek = "SELECT * FROM users WHERE username = ? AND id <> ?";
    $stmt_cek = $conn->prepare($sql_cek);
    $stmt_cek->bind_param("si", $username, $id);
    $stmt_cek->execute();
    $result_cek = $stmt_cek->get_result();

    if ($result_cek->num_rows > 0) {
        $GLOBALS['message'] = "danger|Username sudah digunakan!";
        return false;
    }
    
    // Dapatkan foto lama
    $sql_old = "SELECT foto_profil FROM users WHERE id = ?";
    $stmt_old = $conn->prepare($sql_old);
    $stmt_old->bind_param("i", $id);
    $stmt_old->execute();
    $result_old = $stmt_old->get_result();
    $old_photo = $result_old->fetch_assoc()['foto_profil'] ?? '';
    $stmt_old->close();
    
    $foto_profil = $old_photo;

    // PERBAIKAN: Jika tidak ada foto lama, set default
    if (empty($foto_profil)) {
        $foto_profil = 'default-avatar.png';
    }
    
    // Proses upload foto baru
    if (isset($_FILES['foto_profil']) && $_FILES['foto_profil']['error'] == UPLOAD_ERR_OK) {
        $target_dir = "../uploads/foto_profil/";
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        
        $file_ext = strtolower(pathinfo($_FILES['foto_profil']['name'], PATHINFO_EXTENSION));
        $new_filename = uniqid() . '.' . $file_ext;
        $target_file = $target_dir . $new_filename;
        
        // Validasi tipe file
        $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
        if (in_array($file_ext, $allowed_types)) {
            if (move_uploaded_file($_FILES['foto_profil']['tmp_name'], $target_file)) {
                $foto_profil = $new_filename;
                
                // Hapus foto lama jika ada dan bukan default
                if (!empty($old_photo) && $old_photo != 'default-avatar.png' && file_exists($target_dir . $old_photo)) {
                    unlink($target_dir . $old_photo);
                }
            }
        }
    }
    
    // Update query dengan foto_profil
    $params = [$username, $role, $kelas_id, $murid_id, $foto_profil];
    $types = "ssiis";
    
    $password_update = "";
    if (!empty($new_password)) {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $password_update = ", password = ?";
        $params[] = $hashed_password;
        $types .= "s";
    }
    
    $params[] = $id;
    $types .= "i";
    
    $sql = "UPDATE users SET 
            username = ?, 
            role = ?, 
            kelas_id = ?, 
            murid_id = ?,
            foto_profil = ?
            $password_update 
            WHERE id = ?";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    
    if ($stmt->execute()) {
        $GLOBALS['message'] = "success|Pengguna berhasil diperbarui!";
        return true;
    } else {
        $GLOBALS['message'] = "danger|Error: " . $stmt->error;
        return false;
    }
}

// Proses Tambah Pengguna - PERBAIKAN: Set default avatar
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['tambah_pengguna'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $role = $_POST['role'] ?? '';
    $kelas_id = ($role == 'wali_kelas') ? ($_POST['kelas_id'] ?? NULL) : NULL;
    $murid_id = ($role == 'wali_murid') ? ($_POST['murid_id'] ?? NULL) : NULL;

    // PERBAIKAN: Validasi input wajib
    if (empty($username) || empty($password) || empty($role)) {
        $message = "danger|Semua field wajib diisi!";
    } else {
        // Validasi username unik
        $sql_cek = "SELECT * FROM users WHERE username = ?";
        $stmt_cek = $conn->prepare($sql_cek);
        $stmt_cek->bind_param("s", $username);
        $stmt_cek->execute();
        $result_cek = $stmt_cek->get_result();

        if ($result_cek->num_rows > 0) {
            $message = "danger|Username sudah digunakan!";
        } else {
            // Handle upload foto
            $foto_profil = 'default-avatar.png'; // PERBAIKAN: Default value
            if (isset($_FILES['foto_profil']) && $_FILES['foto_profil']['error'] == UPLOAD_ERR_OK) {
                $target_dir = "../uploads/foto_profil/";
                if (!file_exists($target_dir)) {
                    mkdir($target_dir, 0777, true);
                }
                $file_ext = strtolower(pathinfo($_FILES['foto_profil']['name'], PATHINFO_EXTENSION));
                $new_filename = uniqid() . '.' . $file_ext;
                $target_file = $target_dir . $new_filename;
                
                // Validasi tipe file
                $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
                if (in_array($file_ext, $allowed_types)) {
                    if (move_uploaded_file($_FILES['foto_profil']['tmp_name'], $target_file)) {
                        $foto_profil = $new_filename;
                    }
                }
            }

            // PERBAIKAN: Gunakan password hash untuk keamanan
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $sql = "INSERT INTO users (username, password, role, kelas_id, murid_id, foto_profil) 
                    VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssiis", $username, $hashed_password, $role, $kelas_id, $murid_id, $foto_profil);
            
            if ($stmt->execute()) {
                $message = "success|Pengguna berhasil ditambahkan!";
            } else {
                $message = "danger|Error: " . $stmt->error;
            }
        }
    }
}

// Proses Edit Pengguna
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_pengguna'])) {
    $id = intval($_POST['id']);
    $username = trim($_POST['username']);
    $role = $_POST['role'] ?? '';
    $kelas_id = ($role == 'wali_kelas') ? ($_POST['kelas_id'] ?? NULL) : NULL;
    $murid_id = ($role == 'wali_murid') ? ($_POST['murid_id'] ?? NULL) : NULL;
    $new_password = $_POST['new_password'] ?? '';

    // PERBAIKAN: Validasi input
    if (empty($username) || empty($role)) {
        $message = "danger|Username dan Role wajib diisi!";
    } else {
        // Panggil fungsi processUserUpdate yang sudah diperbaiki
        processUserUpdate($conn, $id, $username, $role, $kelas_id, $murid_id, $new_password, $current_user);
    }
}

// Proses Hapus Pengguna
if (isset($_GET['hapus'])) {
    $id = intval($_GET['hapus']);

    // Tidak boleh hapus diri sendiri
    if ($id == $_SESSION['user_id']) {
        $message = "danger|Tidak dapat menghapus akun sendiri!";
    } else {
        // Hapus foto profil jika ada
        $sql_foto = "SELECT foto_profil FROM users WHERE id = ?";
        $stmt_foto = $conn->prepare($sql_foto);
        $stmt_foto->bind_param("i", $id);
        $stmt_foto->execute();
        $result_foto = $stmt_foto->get_result();
        
        if ($result_foto->num_rows > 0) {
            $user_data = $result_foto->fetch_assoc();
            $foto_profil = $user_data['foto_profil'];
            
            // Hapus file foto dari server
            if (!empty($foto_profil)) {
                $target_file = "../uploads/foto_profil/" . $foto_profil;
                if (file_exists($target_file)) {
                    unlink($target_file);
                }
            }
        }
        $stmt_foto->close();

        $sql = "DELETE FROM users WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            $message = "success|Pengguna berhasil dihapus!";
        } else {
            $message = "danger|Error: " . $stmt->error;
        }
    }
}

// Ambil data untuk edit
if (isset($_GET['edit'])) {
    $id = intval($_GET['edit']);
    
    $sql = "SELECT * FROM users WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $current_user = $result->fetch_assoc();
        $show_modal = true; // SET VARIABLE INI KE TRUE
    }
    $stmt->close();
}

// Ambil semua pengguna
$sql = "SELECT * FROM users";
$result = $conn->query($sql);
$users_list = [];

// PERBAIKAN: Tambahkan pengecekan error yang lebih baik
if ($result) {
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $users_list[] = $row;
        }
    }
} else {
    // Jika query gagal, tampilkan error
    error_log("Error executing query: " . $conn->error);
    $users_list = [];
}

// Ambil semua kelas untuk dropdown
$sql_kelas = "SELECT * FROM kelas";
$result_kelas = $conn->query($sql_kelas);
$kelas_list = [];
if ($result_kelas && $result_kelas->num_rows > 0) {
    while ($row = $result_kelas->fetch_assoc()) {
        $kelas_list[] = $row;
    }
}

// Ambil semua murid untuk dropdown
$sql_murid = "SELECT * FROM murid";
$result_murid = $conn->query($sql_murid);
$murid_list = [];
if ($result_murid && $result_murid->num_rows > 0) {
    while ($row = $result_murid->fetch_assoc()) {
        $murid_list[] = $row;
    }
}

// API PENCARIAN UNTUK FILTER DROPDOWN - tambahkan sebelum require_once navigation
if (isset($_GET['q']) && isset($_GET['type'])) {
    header('Content-Type: application/json');
    $searchTerm = $_GET['q'] ?? '';
    $type = $_GET['type'];
    $items = [];

    try {
        switch ($type) {
            case 'kelas_madin':
                $sql = "SELECT kelas_id, nama_kelas FROM kelas_madin";
                if (!empty($searchTerm)) {
                    $sql .= " WHERE nama_kelas LIKE ?";
                }
                $sql .= " ORDER BY nama_kelas";
                
                $stmt = $conn->prepare($sql);
                if (!empty($searchTerm)) {
                    $searchPattern = '%' . $searchTerm . '%';
                    $stmt->bind_param("s", $searchPattern);
                }
                $stmt->execute();
                $result = $stmt->get_result();
                while ($row = $result->fetch_assoc()) {
                    $items[] = [
                        'id' => $row['kelas_id'],
                        'text' => $row['nama_kelas']
                    ];
                }
                break;
            
            case 'murid':
                $sql = "SELECT murid_id, nama FROM murid";
                if (!empty($searchTerm)) {
                    $sql .= " WHERE nama LIKE ?";
                }
                $sql .= " ORDER BY nama";
                
                $stmt = $conn->prepare($sql);
                if (!empty($searchTerm)) {
                    $searchPattern = '%' . $searchTerm . '%';
                    $stmt->bind_param("s", $searchPattern);
                }
                $stmt->execute();
                $result = $stmt->get_result();
                while ($row = $result->fetch_assoc()) {
                    $items[] = [
                        'id' => $row['murid_id'],
                        'text' => $row['nama']
                    ];
                }
                break;
        }

        echo json_encode(['items' => $items]);
        exit();
    } catch (Exception $e) {
        echo json_encode(['items' => []]);
        exit();
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

<!-- Kode HTML tetap sama seperti sebelumnya -->
<!DOCTYPE html>
<html lang="id" data-bs-theme="<?= $dark_mode ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Pengguna - Sistem Absensi Online</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.5/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <!-- Tambahkan di bagian head setelah CSS lainnya -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />
    
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
        
        body.dark-mode #loading-overlay {
            background: rgba(0, 0, 0, 0.9);
        }
        
        body.dark-mode .loading-text {
            color: #fff;
        }
        
        /* Select2 dropdown wide Styles */
        .select2-dropdown-wide {
            width: 100% !important;
            max-width: 500px;
        }
        
        .select2-container--bootstrap-5 .select2-dropdown {
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
        }
        
        [data-bs-theme="dark"] .select2-container--bootstrap-5 .select2-dropdown {
            background-color: #212529;
            border-color: #495057;
            color: #f8f9fa;
        }
        
        @media (max-width: 768px) {
            .select2-dropdown-wide {
                max-width: 300px !important;
            }
            
            .select2-container {
                width: 100% !important;
            }
        }
        
        @media (max-width: 576px) {
            .select2-dropdown-wide {
                max-width: 250px !important;
            }
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
            <h2><i class="bi bi-people me-2"></i> Manajemen Pengguna</h2>
            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#userModal">
                <i class="bi bi-plus-circle me-1"></i> Tambah Pengguna
            </button>
        </div>

        <!-- CARD STATISTIK USER UNTUK USERS.PHP -->
        <?php if (in_array($_SESSION['role'], ['admin', 'staff'])): ?>
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card bg-primary bg-opacity-10 border-primary">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="card-title text-primary">Total Users</h6>
                                <h4 class="mb-0"><?= count($users_list) ?></h4>
                            </div>
                            <div class="align-self-center">
                                <i class="bi bi-people-fill text-primary fs-3"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card bg-info bg-opacity-10 border-info">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="card-title text-info">Guru/Staff</h6>
                                <h4 class="mb-0">
                                    <?= count(array_filter($users_list, function($user) { 
                                        return in_array($user['role'], ['guru', 'staff']); 
                                    })) ?>
                                </h4>
                            </div>
                            <div class="align-self-center">
                                <i class="bi bi-person-badge text-info fs-3"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-warning bg-opacity-10 border-warning">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="card-title text-warning">Wali</h6>
                                <h4 class="mb-0">
                                    <?= count(array_filter($users_list, function($user) { 
                                        return in_array($user['role'], ['wali_kelas', 'wali_murid']); 
                                    })) ?>
                                </h4>
                            </div>
                            <div class="align-self-center">
                                <i class="bi bi-person-gear text-warning fs-3"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card bg-success bg-opacity-10 border-success">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="card-title text-success">Admin</h6>
                                <h4 class="mb-0">
                                    <?= count(array_filter($users_list, function($user) { return $user['role'] === 'admin'; })) ?>
                                </h4>
                            </div>
                            <div class="align-self-center">
                                <i class="bi bi-shield-check text-success fs-3"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
        </div>
        <?php endif; ?>
        
        <div class="card shadow-sm">
            <div class="card-body">
                <div class="table-responsive">
                    <table id="usersTable" class="table table-hover">
                        <thead>
                            <tr>
                                <th>Foto</th>
                                <th>Username</th>
                                <th>Role</th>
                                <th>Kelas</th>
                                <th>Murid</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <!-- Di bagian HTML tabel users -->
                        <tbody>
                            <?php foreach ($users_list as $user): 
                                $kelas_nama = '-';
                                if ($user['kelas_id']) {
                                    foreach ($kelas_list as $kelas) {
                                        if ($kelas['kelas_id'] == $user['kelas_id']) {
                                            $kelas_nama = $kelas['nama_kelas'];
                                            break;
                                        }
                                    }
                                }
                        
                                $murid_nama = '-';
                                if ($user['murid_id']) {
                                    foreach ($murid_list as $murid) {
                                        if ($murid['murid_id'] == $user['murid_id']) {
                                            $murid_nama = $murid['nama'];
                                            break;
                                        }
                                    }
                                }
                                
                                // Tambahkan mapping untuk semua role
                                $role_names = [
                                    'admin' => 'Administrator',
                                    'wali_kelas' => 'Wali Kelas',
                                    'wali_murid' => 'Wali Murid',
                                    'guru' => 'Guru',
                                    'staff' => 'Staff'
                                ];
                                $role_display = $role_names[$user['role']] ?? $user['role'];
                            ?>
                            <tr>
                                <!-- PERBAIKAN: Gunakan fungsi getProfilePhoto -->
                                <td>
                                    <img src="<?= getProfilePhoto($user) ?>" 
                                         class="rounded-circle" 
                                         width="40" 
                                         height="40" 
                                         alt="Foto Profil"
                                         style="cursor:pointer"
                                         onerror="this.src='../assets/img/default-avatar.png'"
                                         onclick="showPhoto('<?= getProfilePhoto($user) ?>')">
                                </td>
                                <td><?= htmlspecialchars($user['username']) ?></td>
                                <td><?= htmlspecialchars($role_display) ?></td>
                                <td><?= htmlspecialchars($kelas_nama) ?></td>
                                <td><?= htmlspecialchars($murid_nama) ?></td>
                                <td>
                                    <button onclick="openEditModal(<?= $user['id'] ?>)" class="btn btn-sm btn-primary">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <a href="?hapus=<?= $user['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Yakin ingin menghapus pengguna ini?')">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
    </div>

    <!-- Modal Tambah/Edit Pengguna -->
    <div class="modal fade" id="userModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <?= $current_user ? 'Edit Pengguna' : 'Tambah Pengguna' ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="" enctype="multipart/form-data">
                    <div class="modal-body">
                        <?php if ($current_user): ?>
                        <input type="hidden" name="id" value="<?= $current_user['id'] ?>">
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <label class="form-label">Username <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="username" 
                                value="<?= $current_user['username'] ?? '' ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Role <span class="text-danger">*</span></label>
                            <select class="form-select" name="role" id="roleSelect" required>
                                <option value="">-- Pilih Role --</option>
                                <option value="admin" <?= ($current_user['role'] ?? '') == 'admin' ? 'selected' : '' ?>>Admin</option>
                                <option value="guru" <?= ($current_user['role'] ?? '') == 'guru' ? 'selected' : '' ?>>Guru</option>
                                <option value="wali_kelas" <?= ($current_user['role'] ?? '') == 'wali_kelas' ? 'selected' : '' ?>>Wali Kelas</option>
                                <option value="wali_murid" <?= ($current_user['role'] ?? '') == 'wali_murid' ? 'selected' : '' ?>>Wali Murid</option>
                                <option value="staff" <?= ($current_user['role'] ?? '') == 'staff' ? 'selected' : '' ?>>Staff</option>
                            </select>
                        </div>
                        
                        <!-- Ganti bagian ini di modal form -->
                        
                        <div class="mb-3" id="kelasField" style="display: none;">
                            <label class="form-label">Kelas <span class="text-danger">*</span></label>
                            <select class="form-select select2-kelas" name="kelas_id" data-placeholder="Ketik untuk Cari Kelas...">
                                <option value=""></option>
                                <!-- Options akan diisi oleh Select2 via AJAX -->
                            </select>
                        </div>
                        
                        <div class="mb-3" id="muridField" style="display: none;">
                            <label class="form-label">Murid <span class="text-danger">*</span></label>
                            <select class="form-select select2-murid" name="murid_id" data-placeholder="Ketik untuk Cari Murid...">
                                <option value=""></option>
                                <!-- Options akan diisi oleh Select2 via AJAX -->
                            </select>
                        </div>
                        
                        <?php if ($current_user): ?>
                        <div class="mb-3">
                            <label class="form-label">Password Baru</label>
                            <input type="password" class="form-control" name="new_password">
                            <small class="text-muted">Biarkan kosong jika tidak ingin mengubah</small>
                        </div>
                        <?php else: ?>
                        <div class="mb-3">
                            <label class="form-label">Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" name="password" required>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Di modal form -->
                        <div class="mb-3">
                            <label class="form-label">Foto Profil</label>
                            <input type="file" class="form-control" name="foto_profil" id="fotoInput" accept="image/*">
                            <div class="mt-2">
                                <!-- PERBAIKAN: Gunakan fungsi getProfilePhoto untuk preview -->
                                <img src="<?= !empty($current_user) ? getProfilePhoto($current_user) : '../assets/img/default-avatar.png' ?>" 
                                     id="fotoPreview" class="img-thumbnail" width="150">
                            </div>
                        </div>
                        
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="<?= $current_user ? 'edit_pengguna' : 'tambah_pengguna' ?>" class="btn btn-primary">
                            <?= $current_user ? 'Simpan Perubahan' : 'Simpan' ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Modal Foto Besar -->
    <div class="modal fade" id="photoModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-body text-center p-0">
                    <img id="modalPhoto" src="" class="img-fluid" alt="Foto Profil">
                </div>
                <div class="modal-footer justify-content-center">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.5/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    
    <script>
        // PERBAIKAN: Mobile-optimized JavaScript
        $(document).ready(function() {
            // Inisialisasi DataTable dengan konfigurasi mobile
            $('#usersTable').DataTable({
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.5/i18n/id.json'
                },
                responsive: true,
                pageLength: 10,
                lengthMenu: [[5, 10, 25, 50, -1], [5, 10, 25, 50, "All"]],
                dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>rt<"row"<"col-sm-12 col-md-6"i><"col-sm-12 col-md-6"p>>'
            });
            
            // PERBAIKAN: Touch event handling untuk mobile
            function initSelect2Kelas() {
                $('.select2-kelas').select2({
                    theme: 'bootstrap-5',
                    placeholder: 'Ketik untuk Cari Kelas...',
                    allowClear: true,
                    width: '100%',
                    dropdownCssClass: "select2-dropdown-wide",
                    dropdownParent: $('#userModal'),
                    ajax: {
                        url: 'users.php',
                        dataType: 'json',
                        delay: 300,
                        data: function (params) {
                            return {
                                q: params.term || '',
                                type: 'kelas_madin'
                            };
                        },
                        processResults: function (data) {
                            if (!data || !data.items || data.items.length === 0) {
                                return { results: [] };
                            }
                            return { results: data.items };
                        },
                        cache: true
                    },
                    minimumInputLength: 0
                });
            }
            
            function initSelect2Murid() {
                $('.select2-murid').select2({
                    theme: 'bootstrap-5',
                    placeholder: 'Ketik untuk Cari Murid...',
                    allowClear: true,
                    width: '100%',
                    dropdownCssClass: "select2-dropdown-wide",
                    dropdownParent: $('#userModal'),
                    ajax: {
                        url: 'users.php',
                        dataType: 'json',
                        delay: 300,
                        data: function (params) {
                            return {
                                q: params.term || '',
                                type: 'murid'
                            };
                        },
                        processResults: function (data) {
                            if (!data || !data.items || data.items.length === 0) {
                                return { results: [] };
                            }
                            return { results: data.items };
                        },
                        cache: true
                    },
                    minimumInputLength: 0
                });
            }
            
            // PERBAIKAN: Improved toggle fields function
            function toggleFields() {
                const role = $('#roleSelect').val();
                const kelasField = $('#kelasField');
                const muridField = $('#muridField');
                
                // Sembunyikan semua field terlebih dahulu
                kelasField.hide();
                muridField.hide();
                
                // Reset required attributes
                $('select[name="kelas_id"]').prop('required', false);
                $('select[name="murid_id"]').prop('required', false);
                
                // Tampilkan field yang sesuai dengan role
                if (role === 'wali_kelas') {
                    kelasField.show();
                    $('select[name="kelas_id"]').prop('required', true);
                    
                    // Inisialisasi Select2 untuk kelas
                    setTimeout(function() {
                        if ($('.select2-kelas').length > 0) {
                            if ($('.select2-kelas').hasClass('select2-hidden-accessible')) {
                                $('.select2-kelas').select2('destroy');
                            }
                            $('.select2-kelas').val('').trigger('change');
                            initSelect2Kelas();
                        }
                    }, 100);
                } else if (role === 'wali_murid') {
                    muridField.show();
                    $('select[name="murid_id"]').prop('required', true);
                    
                    // Inisialisasi Select2 untuk murid
                    setTimeout(function() {
                        if ($('.select2-murid').length > 0) {
                            if ($('.select2-murid').hasClass('select2-hidden-accessible')) {
                                $('.select2-murid').select2('destroy');
                            }
                            $('.select2-murid').val('').trigger('change');
                            initSelect2Murid();
                        }
                    }, 100);
                }
            }
            
            // Event handlers dengan debounce untuk mobile
            $('#roleSelect').on('change touchstart', function() {
                toggleFields();
            });
            
            // PERBAIKAN: Modal event handlers untuk mobile
            $('#userModal').on('shown.bs.modal', function () {
                setTimeout(function() {
                    toggleFields();
                    
                    if ($('#kelasField').is(':visible')) {
                        initSelect2Kelas();
                    }
                    if ($('#muridField').is(':visible')) {
                        initSelect2Murid();
                    }
                }, 300);
            });
            
            // Reset modal ketika ditutup
            $('#userModal').on('hidden.bs.modal', function () {
                if ($('.select2-kelas').hasClass('select2-hidden-accessible')) {
                    $('.select2-kelas').select2('destroy');
                }
                if ($('.select2-murid').hasClass('select2-hidden-accessible')) {
                    $('.select2-murid').select2('destroy');
                }
                
                $(this).find('form')[0].reset();
                $('.select2-kelas').val('').trigger('change');
                $('.select2-murid').val('').trigger('change');
                
                // Redirect jika sedang edit
                if (window.location.href.includes('edit=')) {
                    window.location.href = 'users.php';
                }
            });
            
            // PERBAIKAN: Auto-show modal untuk edit di mobile
            <?php if ($show_modal && $current_user): ?>
                // Tunggu sampai semua element siap
                setTimeout(function() {
                    $('#userModal').modal('show');
                }, 500);
            <?php endif; ?>
        });
        
        // PERBAIKAN: Improved loading handler untuk mobile
        function hideLoading() {
            $('#loading-overlay').fadeOut(300, function() {
                $(this).remove();
            });
        }
    
        // Handle loading state untuk mobile
        $(window).on('load', function() {
            setTimeout(hideLoading, 800);
        });
    
        // Fallback untuk mobile
        setTimeout(hideLoading, 3000);
        
        // PERBAIKAN: Touch-friendly photo preview
        document.addEventListener('DOMContentLoaded', function() {
            const fotoInput = document.getElementById('fotoInput');
            const fotoPreview = document.getElementById('fotoPreview');
            
            if (fotoInput) {
                fotoInput.addEventListener('change', function(e) {
                    if (e.target.files[0]) {
                        const reader = new FileReader();
                        reader.onload = function() {
                            fotoPreview.src = reader.result;
                        };
                        reader.readAsDataURL(e.target.files[0]);
                    }
                });
            }
        });
        
        // PERBAIKAN: Improved touch functions
        function showPhoto(photoUrl) {
            const modalPhoto = document.getElementById('modalPhoto');
            if (modalPhoto) {
                modalPhoto.src = photoUrl;
                const photoModal = new bootstrap.Modal(document.getElementById('photoModal'));
                photoModal.show();
            }
        }
        
        // PERBAIKAN: Mobile-optimized edit function
        function openEditModal(userId) {
            // Gunakan location href untuk konsistensi di mobile
            window.location.href = 'users.php?edit=' + userId;
            
            // Fallback: Tampilkan loading state
            $('#loading-overlay').show();
        }
        
        // PERBAIKAN: Prevent double tap zoom pada tombol
        document.addEventListener('touchstart', function() {}, {passive: true});
    </script>
</body>
</html>