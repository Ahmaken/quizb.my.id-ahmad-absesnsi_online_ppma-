<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// pages/kelas.php
require_once '../includes/init.php';

if (!check_auth()) {
    header("Location: ../index.php");
    exit();
}

// Simpan tab aktif dalam session
if (isset($_GET['active_tab'])) {
    $_SESSION['active_tab'] = $_GET['active_tab'];
}
$active_tab = $_SESSION['active_tab'] ?? 'kelas_quran';


// Ambil guru_id dari user yang login jika role adalah guru
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

$message = '';
$current_kelas = null;
$current_kelas_quran = null;

// Proses CRUD Kelas dan Kelas Qur'an
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Kelas Madin
    if (isset($_POST['tambah_kelas'])) {
        $nama_kelas = $_POST['nama_kelas'];
        
        $sql = "INSERT INTO kelas_madin (nama_kelas) VALUES (?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $nama_kelas);
        
        if ($stmt->execute()) {
            $message = "success|Kelas berhasil ditambahkan!";
        } else {
            $message = "danger|Error: " . $stmt->error;
        }
    }
    elseif (isset($_POST['edit_kelas'])) {
        $id = $_POST['id'];
        $nama_kelas = $_POST['nama_kelas'];
        
        $sql = "UPDATE kelas_madin SET nama_kelas = ? WHERE kelas_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $nama_kelas, $id);
        
        if ($stmt->execute()) {
            $message = "success|Kelas berhasil diperbarui!";
        } else {
            $message = "danger|Error: " . $stmt->error;
        }
    }
    
    // Kelas Qur'an
    if (isset($_POST['tambah_kelas_quran'])) {
        $nama_kelas = $_POST['nama_kelas_quran'];
        
        $sql = "INSERT INTO kelas_quran (nama_kelas) VALUES (?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $nama_kelas);
        
        if ($stmt->execute()) {
            $message = "success|Kelas Qur'an berhasil ditambahkan!";
            $current_kelas_quran = null; // Reset setelah tambah
        } else {
            $message = "danger|Error: " . $stmt->error;
        }
    }
    elseif (isset($_POST['edit_kelas_quran'])) {
        $id = $_POST['id_quran'];
        $nama_kelas = $_POST['nama_kelas_quran'];
        
        $sql = "UPDATE kelas_quran SET nama_kelas = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $nama_kelas, $id);
        
        if ($stmt->execute()) {
            $message = "success|Kelas Qur'an berhasil diperbarui!";
        } else {
            $message = "danger|Error: " . $stmt->error;
        }
    }
}

// Proses Hapus Kelas
if (isset($_GET['hapus'])) {
    $id = intval($_GET['hapus']);
    
    $sql = "DELETE FROM kelas_madin WHERE kelas_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        $message = "success|Kelas berhasil dihapus!";
    } else {
        $message = "danger|Error: " . $stmt->error;
    }
}

// Proses Hapus Kelas Qur'an
if (isset($_GET['hapus_quran'])) {
    $id = intval($_GET['hapus_quran']);
    
    $sql = "DELETE FROM kelas_quran WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        $message = "success|Kelas Qur'an berhasil dihapus!";
    } else {
        $message = "danger|Error: " . $stmt->error;
    }
}

// Ambil data untuk edit Kelas Madin
if (isset($_GET['edit'])) {
    $id = intval($_GET['edit']);
    
    $sql = "SELECT * FROM kelas_madin WHERE kelas_id = ?";
    if ($_SESSION['role'] === 'guru' && $guru_id) {
        $sql .= " AND guru_id = ?";
    }
    $stmt = $conn->prepare($sql);
    if ($_SESSION['role'] === 'guru' && $guru_id) {
        $stmt->bind_param("ii", $id, $guru_id);
    } else {
        $stmt->bind_param("i", $id);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $current_kelas = $result->fetch_assoc();
    } else {
        header("Location: kelas.php?message=danger|Data kelas tidak ditemukan atau Anda tidak berhak mengakses!");
        exit();
    }
}

// Ambil data untuk edit Kelas Qur'an
if (isset($_GET['edit_quran'])) {
    $id = intval($_GET['edit_quran']);
    
    $sql = "SELECT * FROM kelas_quran WHERE id = ?";
    if ($_SESSION['role'] === 'guru' && $guru_id) {
        $sql .= " AND guru_id = ?";
    }
    $stmt = $conn->prepare($sql);
    if ($_SESSION['role'] === 'guru' && $guru_id) {
        $stmt->bind_param("ii", $id, $guru_id);
    } else {
        $stmt->bind_param("i", $id);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $current_kelas_quran = $result->fetch_assoc();
    } else {
        header("Location: kelas.php?message=danger|Data kelas Qur'an tidak ditemukan atau Anda tidak berhak mengakses!");
        exit();
    }
}

// Reset variabel edit jika ada parameter cancel
if (isset($_GET['cancel'])) {
    $current_kelas = null;
    $current_kelas_quran = null;
    
    // Hapus parameter dari URL
    header("Location: kelas.php");
    exit();
}

// AMBIL SEMUA KELAS DENGAN JUMLAH MURID
$sql = "SELECT k.kelas_id, k.nama_kelas, COUNT(m.murid_id) as jumlah_murid 
        FROM kelas_madin k 
        LEFT JOIN murid m ON k.kelas_id = m.kelas_madin_id 
        WHERE 1=1";
        
// Jika role guru, batasi hanya kelas yang diajar
if ($_SESSION['role'] === 'guru' && $guru_id) {
    $sql .= " AND k.guru_id = ?";
}

$sql .= " GROUP BY k.kelas_id";

$stmt = $conn->prepare($sql);
if ($_SESSION['role'] === 'guru' && $guru_id) {
    $stmt->bind_param("i", $guru_id);
}
$stmt->execute();
$result = $stmt->get_result();
$kelas_list = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $kelas_list[] = $row;
    }
}

// AMBIL SEMUA KELAS QUR'AN DENGAN JUMLAH MURID
$sql_quran = "SELECT kq.id, kq.nama_kelas, COUNT(m.murid_id) as jumlah_murid 
              FROM kelas_quran kq 
              LEFT JOIN murid m ON kq.id = m.kelas_quran_id 
              WHERE 1=1";
              
// Jika role guru, batasi hanya kelas yang diajar
if ($_SESSION['role'] === 'guru' && $guru_id) {
    $sql_quran .= " AND kq.guru_id = ?";
}

$sql_quran .= " GROUP BY kq.id";

$stmt_quran = $conn->prepare($sql_quran);
if ($_SESSION['role'] === 'guru' && $guru_id) {
    $stmt_quran->bind_param("i", $guru_id);
}
$stmt_quran->execute();
$result_quran = $stmt_quran->get_result();
$kelas_quran_list = [];
if ($result_quran->num_rows > 0) {
    while ($row = $result_quran->fetch_assoc()) {
        $kelas_quran_list[] = $row;
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
    <title>Manajemen Kelas - Sistem Absensi Online</title>
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
        
        body.dark-mode #loading-overlay {
            background: rgba(0, 0, 0, 0.9);
        }
        
        body.dark-mode .loading-text {
            color: #fff;
        }
        
        /* Tab styling */
        .nav-tabs .nav-link.active {
            background-color: #0d6efd;
            color: white;
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
            <h2 class="mb-4"><i class="bi bi-journal-bookmark me-2"></i> Manajemen Kelas</h2>
        </div>
        
        <!-- Tab Navigasi -->
        <ul class="nav nav-tabs mb-3" id="myTab" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active <?= $active_tab == 'kelas_quran' ? 'active' : '' ?>" id="kelasQuran-tab" data-bs-toggle="tab" data-bs-target="#kelasQuran" type="button" role="tab">Kelas Qur'an</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link <?= $active_tab == 'kelas' ? 'active' : '' ?>" id="kelasMadin-tab" data-bs-toggle="tab" data-bs-target="#kelasMadin" type="button" role="tab">Kelas Madin</button>
            </li>
            
        </ul>
        
        <div class="tab-content" id="myTabContent">
            
            <!-- Tab Kelas Qur'an -->
            <div class="tab-pane fade show active" id="kelasQuran" role="tabpanel">    
                <!-- Card Kelas Qur'an -->
                <div class="card shadow-sm mt-4">
                    <div class="card-header bg-primary text-white">
                        <h3 class="card-title mb-0"><i class="bi bi-book me-2"></i> Kelas Qur'an</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive mb-3">
                            <table id="kelasQuranTable" class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Nama Kelas Qur'an</th>
                                        <th>Jumlah Murid</th>
                                        <?php if (in_array($_SESSION['role'], ['admin', 'staff'])): ?>
                                        <th>Edit</th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($kelas_quran_list as $kelas): ?>
                                    <tr>
                                        <td><?= $kelas['id'] ?></td>
                                        <td><?= htmlspecialchars($kelas['nama_kelas']) ?></td>
                                        <td><?= $kelas['jumlah_murid'] ?></td>
                                        <?php if (in_array($_SESSION['role'], ['admin', 'staff'])): ?>
                                        <td>
                                            <a href="?edit_quran=<?= $kelas['id'] ?>" class="btn btn-sm btn-primary">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <a href="?hapus_quran=<?= $kelas['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Yakin ingin menghapus kelas Qur\'an ini?')">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        </td>
                                        <?php endif; ?>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div>
                            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#kelasModalQuran">
                                <i class="bi bi-plus-circle me-1"></i> Tambah Kelas Qur'an
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Tab Kelas Madin -->
            <div class="tab-pane fade show active" id="kelasMadin" role="tabpanel">        
                <!-- Card Kelas Madin -->
                <div class="card shadow-sm mt-4">
                    <div class="card-header bg-primary text-white">
                        <h3 class="card-title mb-0"><i class="bi bi-journal-bookmark me-2"></i> Kelas Madin</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive mb-3">
                            <table id="kelasTable" class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Nama Kelas</th>
                                        <th>Jumlah Murid</th>
                                        <?php if (in_array($_SESSION['role'], ['admin', 'staff'])): ?>
                                        <th>Edit</th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($kelas_list as $kelas): ?>
                                    <tr>
                                        <td><?= $kelas['kelas_id'] ?></td>
                                        <td><?= htmlspecialchars($kelas['nama_kelas']) ?></td>
                                        <td><?= $kelas['jumlah_murid'] ?></td>
                                        <?php if (in_array($_SESSION['role'], ['admin', 'staff'])): ?>
                                        <td>
                                            <a href="?edit=<?= $kelas['kelas_id'] ?>" class="btn btn-sm btn-primary">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <a href="?hapus=<?= $kelas['kelas_id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Yakin ingin menghapus kelas ini?')">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        </td>
                                        <?php endif; ?>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div>
                            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#kelasModal">
                                <i class="bi bi-plus-circle me-1"></i> Tambah Kelas Madin
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
        </div>
        
        <!-- Modal Tambah/Edit Kelas Qur'an -->
        <div class="modal fade" id="kelasModalQuran" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <?= $current_kelas_quran ? 'Edit Kelas Qur\'an' : 'Tambah Kelas Qur\'an' ?>
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="">
                        <div class="modal-body">
                            <?php if ($current_kelas_quran): ?>
                            <input type="hidden" name="id_quran" value="<?= $current_kelas_quran['id'] ?>">
                            <?php endif; ?>
                            
                            <div class="mb-3">
                                <label class="form-label">Nama Kelas Qur'an <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="nama_kelas_quran" 
                                    value="<?= $current_kelas_quran['nama_kelas'] ?? '' ?>" required>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <a href="?cancel" class="btn btn-secondary">Batal</a>
                            <button type="submit" name="<?= $current_kelas_quran ? 'edit_kelas_quran' : 'tambah_kelas_quran' ?>" class="btn btn-primary">
                                <?= $current_kelas_quran ? 'Simpan Perubahan' : 'Simpan' ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Modal Tambah/Edit Kelas Madin -->
        <div class="modal fade" id="kelasModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <?= $current_kelas ? 'Edit Kelas Madin' : 'Tambah Kelas Madin' ?>
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="">
                        <div class="modal-body">
                            <?php if ($current_kelas): ?>
                            <input type="hidden" name="id" value="<?= $current_kelas['kelas_id'] ?>">
                            <?php endif; ?>
                            
                            <div class="mb-3">
                                <label class="form-label">Nama Kelas <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="nama_kelas" 
                                    value="<?= $current_kelas['nama_kelas'] ?? '' ?>" required>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <a href="?cancel" class="btn btn-secondary">Batal</a>
                            <button type="submit" name="<?= $current_kelas ? 'edit_kelas' : 'tambah_kelas' ?>" class="btn btn-primary">
                                <?= $current_kelas ? 'Simpan Perubahan' : 'Simpan' ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.5/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#kelasTable').DataTable({
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.5/i18n/id.json'
                }
            });
            
            $('#kelasQuranTable').DataTable({
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.5/i18n/id.json'
                }
            });
            
            // Tampilkan modal jika ada parameter edit
            <?php if ($current_kelas): ?>
            $('#kelasModal').modal('show');
            <?php endif; ?>
            
            <?php if ($current_kelas_quran): ?>
            $('#kelasModalQuran').modal('show');
            <?php endif; ?>
            
            // Reset form saat modal tambah ditampilkan
            $('#kelasModal').on('show.bs.modal', function (event) {
                if (!<?= $current_kelas ? 'true' : 'false' ?>) {
                    $(this).find('form')[0].reset();
                    $(this).find('.modal-title').text('Tambah Kelas Madin');
                    $(this).find('button[type="submit"]').text('Simpan').attr('name', 'tambah_kelas');
                    $(this).find('input[name="id"]').remove();
                }
            });
            
            $('#kelasModalQuran').on('show.bs.modal', function (event) {
                if (!<?= $current_kelas_quran ? 'true' : 'false' ?>) {
                    $(this).find('form')[0].reset();
                    $(this).find('.modal-title').text('Tambah Kelas Qur\'an');
                    $(this).find('button[type="submit"]').text('Simpan').attr('name', 'tambah_kelas_quran');
                    $(this).find('input[name="id_quran"]').remove();
                }
            });
            
            // Tutup modal dan reset state setelah operasi sukses
            <?php if ($message): ?>
            setTimeout(() => {
                $('#kelasModal, #kelasModalQuran').modal('hide');
                
                // Hapus parameter edit dari URL
                const url = new URL(window.location);
                url.searchParams.delete('edit');
                url.searchParams.delete('edit_quran');
                window.history.replaceState({}, document.title, url.toString());
            }, 1000);
            <?php endif; ?>
        });
    
        // <!-- Script untuk mengontrol tampilan loading -->
    
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