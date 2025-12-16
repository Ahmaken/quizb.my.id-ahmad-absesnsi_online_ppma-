<?php
// TAMBAHKAN DI AWAL FILE
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Pindahkan require ke atas
require_once '../includes/init.php';

if (!check_auth()) {
    header("Location: ../index.php");
    exit();
}

// Simpan tab aktif dalam session
if (isset($_GET['active_tab'])) {
    $_SESSION['active_tab'] = $_GET['active_tab'];
}
$active_tab = $_SESSION['active_tab'] ?? 'perizinan';

// PERBAIKI QUERY PENCARIAN MURID - FIXED VERSION
if (isset($_GET['q']) && isset($_GET['page'])) {
    header('Content-Type: application/json');
    
    $searchTerm = $_GET['q'];
    $page = intval($_GET['page']);
    $limit = 30;
    $offset = ($page - 1) * $limit;

    try {
        // PERBAIKAN: Query yang konsisten antara data dan count
        $sql = "SELECT m.murid_id, m.nama, km.nama_kelas 
                FROM murid m 
                JOIN kelas_madin km ON m.kelas_madin_id = km.kelas_id 
                WHERE m.nama LIKE ? OR km.nama_kelas LIKE ?
                ORDER BY m.nama 
                LIMIT ? OFFSET ?";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception('Query preparation failed: ' . $conn->error);
        }
        
        $searchPattern = '%' . $searchTerm . '%';
        $stmt->bind_param("ssii", $searchPattern, $searchPattern, $limit, $offset);
        
        if (!$stmt->execute()) {
            throw new Exception('Query execution failed: ' . $stmt->error);
        }

        $result = $stmt->get_result();
        $items = [];
        while ($row = $result->fetch_assoc()) {
            $items[] = [
                'id' => $row['murid_id'],
                'nama' => $row['nama'],
                'nama_kelas' => $row['nama_kelas'],
                'text' => $row['nama'] . ' (' . $row['nama_kelas'] . ')'
            ];
        }

        // PERBAIKAN PENTING: Query count yang konsisten dengan tabel yang sama
        $sql_count = "SELECT COUNT(*) as total 
                      FROM murid m 
                      JOIN kelas_madin km ON m.kelas_madin_id = km.kelas_id 
                      WHERE m.nama LIKE ? OR km.nama_kelas LIKE ?";
        
        $stmt_count = $conn->prepare($sql_count);
        if (!$stmt_count) {
            throw new Exception('Count query preparation failed: ' . $conn->error);
        }
        
        $stmt_count->bind_param("ss", $searchPattern, $searchPattern);
        
        if (!$stmt_count->execute()) {
            throw new Exception('Count query execution failed: ' . $stmt_count->error);
        }
        
        $result_count = $stmt_count->get_result();
        $total_count = $result_count->fetch_assoc()['total'];

        echo json_encode([
            'items' => $items,
            'total_count' => $total_count,
            'success' => true
        ]);
        
    } catch (Exception $e) {
        error_log("Search error: " . $e->getMessage());
        echo json_encode([
            'error' => 'Terjadi kesalahan sistem',
            'debug' => $e->getMessage(), // Hapus ini di production
            'success' => false
        ]);
    }
    exit();
} // TAMBAHKAN INI - KURUNG KURAWAL YANG HILANG UNTUK MENUTUP BLOK IF PENCARIAN

// PROSES CRUD PELANGGARAN DAN PERIZINAN
$message = '';
$current_pelanggaran = null;
$current_perizinan = null;

// PROSES CRUD PELANGGARAN
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['tambah_pelanggaran'])) {
        $tanggal = $_POST['tanggal'];
        $murid_id = $_POST['murid_id'];
        $jenis = $_POST['jenis'];
        $deskripsi = $_POST['deskripsi'];
        
        // PERBAIKAN: Gunakan kolom yang benar
        $sql = "INSERT INTO pelanggaran (tanggal, murid_id, jenis, deskripsi) 
                VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("siss", $tanggal, $murid_id, $jenis, $deskripsi);
            
            if ($stmt->execute()) {
                $message = "success|Pelanggaran berhasil ditambahkan!";
            } else {
                $message = "danger|Error: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $message = "danger|Error: " . $conn->error;
        }
    }
    elseif (isset($_POST['edit_pelanggaran'])) {
        $id = $_POST['id'];
        $tanggal = $_POST['tanggal'];
        $murid_id = $_POST['murid_id'];
        $jenis = $_POST['jenis'];
        $deskripsi = $_POST['deskripsi'];
        
        // PERBAIKAN: Gunakan kolom yang benar
        $sql = "UPDATE pelanggaran SET 
                tanggal = ?, 
                murid_id = ?, 
                jenis = ?, 
                deskripsi = ?
                WHERE pelanggaran_id = ?";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("sissi", $tanggal, $murid_id, $jenis, $deskripsi, $id);
            
            if ($stmt->execute()) {
                $message = "success|Pelanggaran berhasil diperbarui!";
            } else {
                $message = "danger|Error: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $message = "danger|Error: " . $conn->error;
        }
    }
    
    // PROSES TAMBAH PERIZINAN
    if (isset($_POST['tambah_perizinan'])) {
        $tanggal = $_POST['tanggal'];
        $murid_id = $_POST['murid_id'];
        $jenis = $_POST['jenis'];
        $deskripsi = $_POST['deskripsi'];
        $status_izin = $_POST['status_izin'];
        
        $sql = "INSERT INTO perizinan (tanggal, murid_id, jenis, deskripsi, status_izin) 
                VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("sisss", $tanggal, $murid_id, $jenis, $deskripsi, $status_izin);
            
            if ($stmt->execute()) {
                $message = "success|Perizinan berhasil ditambahkan!";
            } else {
                $message = "danger|Error: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $message = "danger|Error: " . $conn->error;
        }
    }
    
    // PROSES EDIT PERIZINAN
    elseif (isset($_POST['edit_perizinan'])) {
        $id = $_POST['id'];
        $tanggal = $_POST['tanggal'];
        $murid_id = $_POST['murid_id'];
        $jenis = $_POST['jenis'];
        $deskripsi = $_POST['deskripsi'];
        $status_izin = $_POST['status_izin'];
        
        $sql = "UPDATE perizinan SET 
                tanggal = ?, 
                murid_id = ?, 
                jenis = ?, 
                deskripsi = ?,
                status_izin = ?
                WHERE perizinan_id = ?";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("sisssi", $tanggal, $murid_id, $jenis, $deskripsi, $status_izin, $id);
            
            if ($stmt->execute()) {
                $message = "success|Perizinan berhasil diperbarui!";
            } else {
                $message = "danger|Error: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $message = "danger|Error: " . $conn->error;
        }
    }
}

// PROSES HAPUS PELANGGARAN
if (isset($_GET['hapus'])) {
    $id = intval($_GET['hapus']);
    
    $sql = "DELETE FROM pelanggaran WHERE pelanggaran_id = ?";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            $message = "success|Pelanggaran berhasil dihapus!";
        } else {
            $message = "danger|Error: " . $stmt->error;
        }
        $stmt->close();
    } else {
        $message = "danger|Error: " . $conn->error;
    }
}

// PROSES HAPUS PERIZINAN
if (isset($_GET['hapus_perizinan'])) {
    $id = intval($_GET['hapus_perizinan']);
    
    $sql = "DELETE FROM perizinan WHERE perizinan_id = ?";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            $message = "success|Perizinan berhasil dihapus!";
        } else {
            $message = "danger|Error: " . $stmt->error;
        }
        $stmt->close();
    } else {
        $message = "danger|Error: " . $conn->error;
    }
}

// AMBIL DATA UNTUK EDIT PELANGGARAN
if (isset($_GET['edit'])) {
    $id = intval($_GET['edit']);
    
    $sql = "SELECT * FROM pelanggaran WHERE pelanggaran_id = ?";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $current_pelanggaran = $result->fetch_assoc();
        }
        $stmt->close();
    }
}

// AMBIL DATA UNTUK EDIT PERIZINAN
if (isset($_GET['edit_perizinan'])) {
    $id = intval($_GET['edit_perizinan']);
    
    $sql = "SELECT * FROM perizinan WHERE perizinan_id = ?";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $current_perizinan = $result->fetch_assoc();
        }
        $stmt->close();
    }
}

// AMBIL DATA PELANGGARAN (DENGAN JOIN) - PERBAIKAN QUERY
$sql = "SELECT 
          p.pelanggaran_id, 
          p.tanggal, 
          p.murid_id, 
          p.jenis AS jenis_pelanggaran, 
          p.deskripsi,
          m.nama,
          km.nama_kelas 
        FROM pelanggaran p
        JOIN murid m ON p.murid_id = m.murid_id
        LEFT JOIN kelas_madin km ON m.kelas_madin_id = km.kelas_id
        ORDER BY p.tanggal DESC";
$result = $conn->query($sql);
$pelanggaran_list = [];

// PERBAIKAN: Cek apakah query berhasil dijalankan
if ($result !== false) {
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $pelanggaran_list[] = $row;
        }
    }
} else {
    // Handle error jika query gagal
    error_log("Error dalam query pelanggaran: " . $conn->error);
    $pelanggaran_list = [];
}

// AMBIL DATA PERIZINAN (DENGAN JOIN) - PERBAIKAN QUERY
$sql_perizinan = "SELECT 
          p.perizinan_id, 
          p.tanggal, 
          p.murid_id, 
          p.jenis AS jenis_perizinan, 
          p.deskripsi,
          p.status_izin,
          m.nama,
          km.nama_kelas 
        FROM perizinan p
        JOIN murid m ON p.murid_id = m.murid_id
        LEFT JOIN kelas_madin km ON m.kelas_madin_id = km.kelas_id
        ORDER BY p.tanggal DESC";
$result_perizinan = $conn->query($sql_perizinan);
$perizinan_list = [];

// PERBAIKAN: Cek apakah query berhasil dijalankan
if ($result_perizinan !== false) {
    if ($result_perizinan->num_rows > 0) {
        while ($row = $result_perizinan->fetch_assoc()) {
            $perizinan_list[] = $row;
        }
    }
} else {
    // Handle error jika query gagal
    error_log("Error dalam query perizinan: " . $conn->error);
    $perizinan_list = [];
}

// AMBIL DATA MURID UNTUK DROPDOWN (DENGAN KELAS) - PERBAIKAN QUERY
$sql_murid = "SELECT m.*, km.nama_kelas 
              FROM murid m 
              LEFT JOIN kelas_madin km ON m.kelas_madin_id = km.kelas_id";
$result_murid = $conn->query($sql_murid);
$murid_list = [];

// PERBAIKAN: Cek apakah query berhasil dijalankan
if ($result_murid !== false) {
    if ($result_murid->num_rows > 0) {
        while ($row = $result_murid->fetch_assoc()) {
            $murid_list[] = $row;
        }
    }
} else {
    // Handle error jika query gagal
    error_log("Error dalam query murid: " . $conn->error);
    $murid_list = [];
}

require_once '../includes/navigation.php';

// TAMPILKAN PESAN
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
    <title>Pelanggaran - Sistem Absensi Online</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.5/css/dataTables.bootstrap5.min.css">
    <!-- Tambahkan CSS Select2 -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />
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
            <h2><i class="bi bi-file-earmark-medical icon"></i> Perizinan & Pelanggaran</h2>
            
        </div>
        
        <!-- Tab Navigasi -->
        <ul class="nav nav-tabs mb-3" id="myTab" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link <?= $active_tab == 'perizinan' ? 'active' : '' ?>" id="perizinan-tab" data-bs-toggle="tab" data-bs-target="#perizinan" type="button" role="tab">Perizinan</button>
            </li>
            
            <li class="nav-item" role="presentation">
                <button class="nav-link <?= $active_tab == 'pelanggaran' ? 'active' : '' ?>" id="pelanggaran-tab" data-bs-toggle="tab" data-bs-target="#pelanggaran" type="button" role="tab">Pelanggaran</button>
            </li>
        </ul>

        <div class="tab-content" id="myTabContent">
            
            <!-- Tab Perizinan -->
            <div class="tab-pane fade <?= $active_tab == 'perizinan' ? 'show active' : '' ?>" id="perizinan" role="tabpanel">
                <div class="card shadow-sm">
                    
                    <div class="card-body">
                        <div class="p-2">
                            <button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#perizinanModal">
                                <i class="bi bi-plus-circle me-1"></i> Tambah Perizinan
                            </button>
                        </div>
                        
                        <div class="table-responsive">
                            <table id="perizinanTable" class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Tanggal</th>
                                        <th>Nama Murid</th>
                                        <th>Kelas</th>
                                        <th>Jenis Perizinan</th>
                                        <th>Deskripsi</th>
                                        <th>Status</th>
                                        <th>Edit</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($perizinan_list as $p): ?>
                                    <tr>
                                        <td><?= $p['tanggal'] ?></td>
                                        <td><?= htmlspecialchars($p['nama']) ?></td>
                                        <td><?= htmlspecialchars($p['nama_kelas']) ?></td>
                                        <td><?= htmlspecialchars($p['jenis_perizinan'] ?? '') ?></td>
                                        <td><?= htmlspecialchars($p['deskripsi'] ?? '') ?></td>
                                        <td>
                                            <span class="badge bg-<?= 
                                                $p['status_izin'] == 'Disetujui' ? 'success' : 
                                                ($p['status_izin'] == 'Ditolak' ? 'danger' : 'warning') 
                                            ?>">
                                                <?= $p['status_izin'] ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="?edit_perizinan=<?= $p['perizinan_id'] ?>" class="btn btn-sm btn-primary">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <a href="?hapus_perizinan=<?= $p['perizinan_id'] ?>" class="btn btn-sm btn-danger" 
                                               onclick="return confirm('Yakin ingin menghapus perizinan ini?')">
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
            
            <!-- Tab Pelanggaran -->
            <div class="tab-pane fade <?= $active_tab == 'pelanggaran' ? 'show active' : '' ?>" id="pelanggaran" role="tabpanel">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <div class="p-2">
                            <button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#pelanggaranModal">
                                <i class="bi bi-plus-circle me-1"></i> Tambah Pelanggaran
                            </button>
                        </div>
                        <div class="table-responsive">
                            <table id="pelanggaranTable" class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Tanggal</th>
                                        <th>Nama Murid</th>
                                        <th>Kelas</th>
                                        <th>Jenis Pelanggaran</th>
                                        <th>Deskripsi</th>
                                        <th>Edit</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pelanggaran_list as $pel): ?>
                                    <tr>
                                        <td><?= $pel['tanggal'] ?></td>
                                        <td><?= htmlspecialchars($pel['nama']) ?></td>
                                        <td><?= htmlspecialchars($pel['nama_kelas']) ?></td>
                                        <!-- PERBAIKAN DI SINI: TAMBAHKAN NULL COALESCING -->
                                        <td><?= htmlspecialchars($pel['jenis_pelanggaran'] ?? '') ?></td>
                                        <td><?= htmlspecialchars($pel['deskripsi'] ?? '') ?></td>
                                        <td>
                                            <a href="?edit=<?= $pel['pelanggaran_id'] ?>" class="btn btn-sm btn-primary">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <a href="?hapus=<?= $pel['pelanggaran_id'] ?>" class="btn btn-sm btn-danger" 
                                               onclick="return confirm('Yakin ingin menghapus pelanggaran ini?')">
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
        
        </div>
    </div>
    
    <!-- MODAL PERIZINAN -->
    <div class="modal fade" id="perizinanModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <?= $current_perizinan ? 'Edit Perizinan' : 'Tambah Perizinan' ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="">
                    <input type="hidden" name="active_tab" value="perizinan">
                    <input type="hidden" name="filter" value="1">
                    <div class="modal-body">
                        <?php if ($current_perizinan): ?>
                        <input type="hidden" name="id" value="<?= $current_perizinan['perizinan_id'] ?>">
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <label class="form-label">Tanggal</label>
                            <input type="date" class="form-control" name="tanggal" 
                                value="<?= $current_perizinan['tanggal'] ?? date('Y-m-d') ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Murid</label>
                            <!-- Ganti dengan select2 dengan pencarian yang diperbaiki -->
                            <select class="form-select select2-murid" name="murid_id" required>
                                <?php if ($current_perizinan): ?>
                                    <?php 
                                    $current_murid = null;
                                    foreach ($murid_list as $murid) {
                                        if ($murid['murid_id'] == $current_perizinan['murid_id']) {
                                            $current_murid = $murid;
                                            break;
                                        }
                                    }
                                    ?>
                                    <option value="<?= $current_murid['murid_id'] ?>" selected>
                                        <?= htmlspecialchars($current_murid['nama']) ?> 
                                        (<?= htmlspecialchars($current_murid['nama_kelas']) ?>)
                                    </option>
                                <?php else: ?>
                                    <option value="" selected disabled>Cari nama murid...</option>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Jenis Perizinan</label>
                            <input type="text" class="form-control" name="jenis" 
                                value="<?= $current_perizinan['jenis'] ?? '' ?>" required
                                placeholder="Contoh: Izin Sakit, Izin Pulang">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Deskripsi</label>
                            <textarea class="form-control" name="deskripsi" rows="3"
                                placeholder="Jelaskan alasan perizinan"><?= $current_perizinan['deskripsi'] ?? '' ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Status Perizinan</label>
                            <select class="form-select" name="status_izin" required>
                                <option value="Menunggu" <?= ($current_perizinan['status_izin'] ?? '') == 'Menunggu' ? 'selected' : '' ?>>Menunggu</option>
                                <option value="Disetujui" <?= ($current_perizinan['status_izin'] ?? '') == 'Disetujui' ? 'selected' : '' ?>>Disetujui</option>
                                <option value="Ditolak" <?= ($current_perizinan['status_izin'] ?? '') == 'Ditolak' ? 'selected' : '' ?>>Ditolak</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="<?= $current_perizinan ? 'edit_perizinan' : 'tambah_perizinan' ?>" 
                                class="btn btn-primary">
                            <?= $current_perizinan ? 'Simpan Perubahan' : 'Simpan' ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL PELANGGARAN -->
    <div class="modal fade" id="pelanggaranModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <?= $current_pelanggaran ? 'Edit Pelanggaran' : 'Tambah Pelanggaran' ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="">
                    <input type="hidden" name="active_tab" value="pelanggaran">
                    <input type="hidden" name="filter" value="1">
                    <div class="modal-body">
                        <?php if ($current_pelanggaran): ?>
                        <input type="hidden" name="id" value="<?= $current_pelanggaran['pelanggaran_id'] ?>">
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <label class="form-label">Tanggal</label>
                            <input type="date" class="form-control" name="tanggal" 
                                value="<?= $current_pelanggaran['tanggal'] ?? date('Y-m-d') ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Murid</label>
                            <!-- Ganti dengan select2 dengan pencarian yang diperbaiki -->
                            <select class="form-select select2-murid" name="murid_id" required>
                                <?php if ($current_pelanggaran): ?>
                                    <?php 
                                    $current_murid = null;
                                    foreach ($murid_list as $murid) {
                                        if ($murid['murid_id'] == $current_pelanggaran['murid_id']) {
                                            $current_murid = $murid;
                                            break;
                                        }
                                    }
                                    ?>
                                    <option value="<?= $current_murid['murid_id'] ?>" selected>
                                        <?= htmlspecialchars($current_murid['nama']) ?> 
                                        (<?= htmlspecialchars($current_murid['nama_kelas']) ?>)
                                    </option>
                                <?php else: ?>
                                    <option value="" selected disabled>Cari nama murid...</option>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Jenis Pelanggaran</label>
                            <input type="text" class="form-control" name="jenis" 
                                value="<?= $current_pelanggaran['jenis_pelanggaran'] ?? '' ?>" required
                                placeholder="Contoh: Terlambat, Tidak mengerjakan PR">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Deskripsi</label>
                            <textarea class="form-control" name="deskripsi" rows="3"
                                placeholder="Jelaskan detail pelanggaran"><?= $current_pelanggaran['deskripsi'] ?? '' ?></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="<?= $current_pelanggaran ? 'edit_pelanggaran' : 'tambah_pelanggaran' ?>" 
                                class="btn btn-primary">
                            <?= $current_pelanggaran ? 'Simpan Perubahan' : 'Simpan' ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.5/js/dataTables.bootstrap5.min.js"></script>
    <!-- Tambahkan JavaScript Select2 -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function() {
            // Inisialisasi DataTables
            $('#pelanggaranTable, #perizinanTable').DataTable({
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.5/i18n/id.json'
                },
                pageLength: 10,
                lengthMenu: [[5, 10, 25, 50, -1], [5, 10, 25, 50, "Semua"]]
            });
            
            // Fungsi untuk inisialisasi Select2 dengan AJAX - DIPERBAIKI
            function initSelect2(selector) {
                $(selector).select2({
                    theme: 'bootstrap-5',
                    placeholder: "Ketik nama murid atau kelas...",
                    allowClear: true,
                    width: '100%',
                    dropdownParent: $(selector).closest('.modal'),
                    ajax: {
                        url: window.location.href, // Gunakan URL saat ini
                        dataType: 'json',
                        delay: 300, // Beri sedikit delay
                        data: function (params) {
                            return {
                                q: params.term,
                                page: params.page || 1
                            };
                        },
                        processResults: function (data, params) {
                            console.log('Search results:', data); // Debug
                            if (data.error) {
                                console.error('Search error:', data);
                                return { results: [] };
                            }
                            
                            params.page = params.page || 1;
                            return {
                                results: data.items || [],
                                pagination: {
                                    more: (params.page * 30) < (data.total_count || 0)
                                }
                            };
                        },
                        cache: true
                    },
                    minimumInputLength: 2,
                    templateResult: formatMurid,
                    templateSelection: formatMuridSelection,
                    // PERBAIKAN: Handle error saat loading
                    language: {
                        errorLoading: function () {
                            return 'Data tidak dapat dimuat.';
                        },
                        searching: function () {
                            return 'Mencari...';
                        },
                        noResults: function () {
                            return 'Tidak ada hasil ditemukan.';
                        },
                        loadingMore: function () {
                            return 'Memuat lebih banyak hasil...';
                        }
                    }
                }).on('select2:open', function () {
                    // PERBAIKAN: Focus pada search box saat dropdown dibuka
                    setTimeout(function() {
                        document.querySelector('.select2-search__field').focus();
                    }, 100);
                });
            }
            
            // Format tampilan hasil pencarian
            function formatMurid(murid) {
                if (murid.loading) {
                    return '<div class="text-muted">Mencari...</div>';
                }
                
                if (!murid.id) {
                    return murid.text;
                }
                
                var $container = $(
                    "<div class='select2-result-murid'>" +
                        "<div class='fw-bold'>" + murid.nama + "</div>" +
                        "<div class='text-muted small'>Kelas: " + (murid.nama_kelas || '-') + "</div>" +
                    "</div>"
                );
                
                return $container;
            }
            
            // Format tampilan yang dipilih
            function formatMuridSelection(murid) {
                if (!murid.id) {
                    return murid.text;
                }
                
                // PERBAIKAN: Handle ketika data tidak lengkap
                if (murid.nama && murid.nama_kelas) {
                    return murid.nama + ' (' + murid.nama_kelas + ')';
                } else if (murid.nama) {
                    return murid.nama;
                } else {
                    return murid.text;
                }
            }
            
            // PERBAIKAN: Inisialisasi Select2 saat modal ditampilkan
            $('#pelanggaranModal, #perizinanModal').on('shown.bs.modal', function () {
                var selectElement = $(this).find('.select2-murid');
                if (!selectElement.hasClass('select2-hidden-accessible')) {
                    initSelect2(selectElement);
                }
            });
            
            // PERBAIKAN: Handle modal hidden dengan lebih baik
            $('#pelanggaranModal, #perizinanModal').on('hidden.bs.modal', function () {
                var selectElement = $(this).find('.select2-murid');
                if (selectElement.hasClass('select2-hidden-accessible')) {
                    selectElement.select2('destroy');
                }
                // Reset form jika perlu
                $(this).find('form')[0]?.reset();
            });
            
            // Tampilkan modal jika ada parameter edit
            <?php if ($current_pelanggaran): ?>
            $(window).on('load', function() {
                $('#pelanggaranModal').modal('show');
            });
            <?php endif; ?>
            
            <?php if ($current_perizinan): ?>
            $(window).on('load', function() {
                $('#perizinanModal').modal('show');
            });
            <?php endif; ?>
            
            // Redirect ke halaman tanpa parameter edit ketika modal ditutup
            $('#pelanggaranModal, #perizinanModal').on('hidden.bs.modal', function () {
                var url = new URL(window.location.href);
                url.searchParams.delete('edit');
                url.searchParams.delete('edit_perizinan');
                window.history.replaceState({}, document.title, url.toString());
            });
            
            // PERBAIKAN: Handle form submission untuk menjaga Select2 value
            $('form').on('submit', function() {
                // Pastikan value Select2 tersimpan
                $(this).find('.select2-murid').each(function() {
                    var selectedValue = $(this).val();
                    if (!selectedValue) {
                        alert('Pilih murid terlebih dahulu!');
                        return false;
                    }
                });
            });
        });
        
        // Script loading overlay (tetap sama)
        function hideLoading() {
            $('#loading-overlay').fadeOut(300, function() {
                $(this).remove();
            });
        }
        
        $(window).on('load', function() {
            setTimeout(hideLoading, 500);
        });
        
        setTimeout(hideLoading, 5000);
    </script>
</body>
</html>