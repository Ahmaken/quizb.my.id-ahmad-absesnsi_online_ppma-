<?php
// Pastikan tidak ada output sebelum ini
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Pindahkan require ke atas sebelum apapun
require_once '../includes/init.php';

if (!check_auth()) {
    header("Location: ../index.php");
    exit();
}

// Buat tabel jurnal jika belum ada
$create_tables_sql = [
    "CREATE TABLE IF NOT EXISTS jurnal_madin (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tanggal DATE NOT NULL,
        guru_id INT NOT NULL,
        kelas_id INT NOT NULL,
        materi TEXT NOT NULL,
        catatan TEXT NOT NULL,
        kendala TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (guru_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (kelas_id) REFERENCES kelas_madin(kelas_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
    
    "CREATE TABLE IF NOT EXISTS jurnal_quran (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tanggal DATE NOT NULL,
        guru_id INT NOT NULL,
        kelas_quran_id INT NOT NULL,
        materi TEXT NOT NULL,
        catatan TEXT NOT NULL,
        kendala TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (guru_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (kelas_quran_id) REFERENCES kelas_quran(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
    
    "CREATE TABLE IF NOT EXISTS jurnal_kamar (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tanggal DATE NOT NULL,
        pembina_id INT NOT NULL,
        kamar_id INT NOT NULL,
        kegiatan TEXT NOT NULL,
        catatan TEXT NOT NULL,
        kendala TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (pembina_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (kamar_id) REFERENCES kamar(kamar_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
];

foreach ($create_tables_sql as $sql) {
    if (!$conn->query($sql)) {
        error_log("Gagal membuat tabel: " . $conn->error);
    }
}

// Inisialisasi variabel
$message = '';
$current_jurnal = null;

// PROSES CRUD JURNAL
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['tambah_jurnal_madin'])) {
        $tanggal = $_POST['tanggal'];
        $guru_id = $_SESSION['user_id']; // ID guru dari session
        $kelas_id = $_POST['kelas_id'];
        $materi = $_POST['materi'];
        $catatan = $_POST['catatan'];
        $kendala = $_POST['kendala'] ?? '';
        
        $sql = "INSERT INTO jurnal_madin (tanggal, guru_id, kelas_id, materi, catatan, kendala) 
                VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        
        if ($stmt) {
            $stmt->bind_param("siisss", $tanggal, $guru_id, $kelas_id, $materi, $catatan, $kendala);
            
            if ($stmt->execute()) {
                $message = "success|Jurnal Madin berhasil ditambahkan!";
            } else {
                $message = "danger|Error: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $message = "danger|Error preparing statement: " . $conn->error;
        }
    }
    elseif (isset($_POST['tambah_jurnal_quran'])) {
        $tanggal = $_POST['tanggal'];
        $guru_id = $_SESSION['user_id']; // ID guru dari session
        $kelas_quran_id = $_POST['kelas_quran_id'];
        $materi = $_POST['materi'];
        $catatan = $_POST['catatan'];
        $kendala = $_POST['kendala'] ?? '';
        
        $sql = "INSERT INTO jurnal_quran (tanggal, guru_id, kelas_quran_id, materi, catatan, kendala) 
                VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        
        if ($stmt) {
            $stmt->bind_param("siisss", $tanggal, $guru_id, $kelas_quran_id, $materi, $catatan, $kendala);
            
            if ($stmt->execute()) {
                $message = "success|Jurnal Qur'an berhasil ditambahkan!";
            } else {
                $message = "danger|Error: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $message = "danger|Error preparing statement: " . $conn->error;
        }
    }
    elseif (isset($_POST['tambah_jurnal_kamar'])) {
        $tanggal = $_POST['tanggal'];
        $pembina_id = $_SESSION['user_id']; // ID pembina dari session
        $kamar_id = $_POST['kamar_id'];
        $kegiatan = $_POST['kegiatan'];
        $catatan = $_POST['catatan'];
        $kendala = $_POST['kendala'] ?? '';
        
        $sql = "INSERT INTO jurnal_kamar (tanggal, pembina_id, kamar_id, kegiatan, catatan, kendala) 
                VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        
        if ($stmt) {
            $stmt->bind_param("siisss", $tanggal, $pembina_id, $kamar_id, $kegiatan, $catatan, $kendala);
            
            if ($stmt->execute()) {
                $message = "success|Jurnal Kamar berhasil ditambahkan!";
            } else {
                $message = "danger|Error: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $message = "danger|Error preparing statement: " . $conn->error;
        }
    }
    
    // Proses edit jurnal
    elseif (isset($_POST['edit_jurnal_madin'])) {
        $id = $_POST['id'];
        $tanggal = $_POST['tanggal'];
        $kelas_id = $_POST['kelas_id'];
        $materi = $_POST['materi'];
        $catatan = $_POST['catatan'];
        $kendala = $_POST['kendala'] ?? '';
        
        $sql = "UPDATE jurnal_madin SET tanggal = ?, kelas_id = ?, materi = ?, catatan = ?, kendala = ? 
                WHERE id = ?";
        $stmt = $conn->prepare($sql);
        
        if ($stmt) {
            $stmt->bind_param("sisssi", $tanggal, $kelas_id, $materi, $catatan, $kendala, $id);
            
            if ($stmt->execute()) {
                $message = "success|Jurnal Madin berhasil diperbarui!";
            } else {
                $message = "danger|Error: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $message = "danger|Error preparing statement: " . $conn->error;
        }
    }
    // Proses edit jurnal quran dan kamar serupa...
}

// PROSES HAPUS JURNAL
if (isset($_GET['hapus_jurnal_madin'])) {
    $id = intval($_GET['hapus_jurnal_madin']);
    
    $sql = "DELETE FROM jurnal_madin WHERE id = ?";
    $stmt = $conn->prepare($sql);
    
    if ($stmt) {
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            $message = "success|Jurnal Madin berhasil dihapus!";
        } else {
            $message = "danger|Error: " . $stmt->error;
        }
        $stmt->close();
    } else {
        $message = "danger|Error preparing statement: " . $conn->error;
    }
}

// Proses hapus jurnal quran dan kamar serupa...

// Ambil parameter filter
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');
$kelas_id = $_GET['kelas_id'] ?? null;
$kelas_quran_id = $_GET['kelas_quran_id'] ?? null;
$kamar_id = $_GET['kamar_id'] ?? null;

// Query untuk jurnal madin
$jurnal_madin = [];
$sql = "SELECT jm.*, km.nama_kelas, u.username as guru_nama 
        FROM jurnal_madin jm
        JOIN kelas_madin km ON jm.kelas_id = km.kelas_id
        JOIN users u ON jm.guru_id = u.id
        WHERE jm.tanggal BETWEEN ? AND ?";

// Menyusun parameter dan tipe data
$types = "ss";
$params = [$start_date, $end_date];

if ($kelas_id) {
    $sql .= " AND jm.kelas_id = ?";
    $types .= "i";
    $params[] = $kelas_id;
}

$stmt = $conn->prepare($sql);
if ($stmt) {
    // Bind parameter secara manual
    if ($types === "ss") {
        $stmt->bind_param("ss", ...$params);
    } elseif ($types === "ssi") {
        $stmt->bind_param("ssi", ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $jurnal_madin[] = $row;
    }
    $stmt->close();
} else {
    $message = "danger|Error preparing statement: " . $conn->error;
}

// Query untuk jurnal quran
$jurnal_quran = [];
$sql = "SELECT jq.*, kq.nama_kelas, u.username as guru_nama 
        FROM jurnal_quran jq
        JOIN kelas_quran kq ON jq.kelas_quran_id = kq.id
        JOIN users u ON jq.guru_id = u.id
        WHERE jq.tanggal BETWEEN ? AND ?";

// Menyusun parameter dan tipe data
$types = "ss";
$params = [$start_date, $end_date];

if ($kelas_quran_id) {
    $sql .= " AND jq.kelas_quran_id = ?";
    $types .= "i";
    $params[] = $kelas_quran_id;
}

$stmt = $conn->prepare($sql);
if ($stmt) {
    // Bind parameter secara manual
    if ($types === "ss") {
        $stmt->bind_param("ss", ...$params);
    } elseif ($types === "ssi") {
        $stmt->bind_param("ssi", ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $jurnal_quran[] = $row;
    }
    $stmt->close();
} else {
    $message = "danger|Error preparing statement: " . $conn->error;
}

// Query untuk jurnal kamar
$jurnal_kamar = [];
$sql = "SELECT jk.*, k.nama_kamar, u.username as pembina_nama 
        FROM jurnal_kamar jk
        JOIN kamar k ON jk.kamar_id = k.kamar_id
        JOIN users u ON jk.pembina_id = u.id
        WHERE jk.tanggal BETWEEN ? AND ?";

// Menyusun parameter dan tipe data
$types = "ss";
$params = [$start_date, $end_date];

if ($kamar_id) {
    $sql .= " AND jk.kamar_id = ?";
    $types .= "i";
    $params[] = $kamar_id;
}

$stmt = $conn->prepare($sql);
if ($stmt) {
    // Bind parameter secara manual
    if ($types === "ss") {
        $stmt->bind_param("ss", ...$params);
    } elseif ($types === "ssi") {
        $stmt->bind_param("ssi", ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $jurnal_kamar[] = $row;
    }
    $stmt->close();
} else {
    $message = "danger|Error preparing statement: " . $conn->error;
}

// Ambil semua kelas madin untuk dropdown
$sql_kelas = "SELECT * FROM kelas_madin";
$result_kelas = $conn->query($sql_kelas);
$kelas_list = [];
if ($result_kelas->num_rows > 0) {
    while ($row = $result_kelas->fetch_assoc()) {
        $kelas_list[] = $row;
    }
}

// Ambil semua kelas quran untuk dropdown
$sql_kelas_quran = "SELECT * FROM kelas_quran";
$result_kelas_quran = $conn->query($sql_kelas_quran);
$kelas_quran_list = [];
if ($result_kelas_quran->num_rows > 0) {
    while ($row = $result_kelas_quran->fetch_assoc()) {
        $kelas_quran_list[] = $row;
    }
}

// Ambil semua kamar untuk dropdown
$sql_kamar = "SELECT * FROM kamar";
$result_kamar = $conn->query($sql_kamar);
$kamar_list = [];
if ($result_kamar->num_rows > 0) {
    while ($row = $result_kamar->fetch_assoc()) {
        $kamar_list[] = $row;
    }
}

// API PENCARIAN UNTUK FILTER DROPDOWN
if (isset($_GET['q']) && isset($_GET['type'])) {
    header('Content-Type: application/json');
    $searchTerm = $_GET['q'];
    $type = $_GET['type'];
    $items = [];

    switch ($type) {
        case 'kelas_quran':
            $sql = "SELECT id, nama_kelas FROM kelas_quran";
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
                    'id' => $row['id'],
                    'text' => $row['nama_kelas']
                ];
            }
            break;
        
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
        
        case 'kamar':
            $sql = "SELECT kamar_id, nama_kamar FROM kamar";
            if (!empty($searchTerm)) {
                $sql .= " WHERE nama_kamar LIKE ?";
            }
            $sql .= " ORDER BY nama_kamar";
            
            $stmt = $conn->prepare($sql);
            if (!empty($searchTerm)) {
                $searchPattern = '%' . $searchTerm . '%';
                $stmt->bind_param("s", $searchPattern);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $items[] = [
                    'id' => $row['kamar_id'],
                    'text' => $row['nama_kamar']
                ];
            }
            break;
        
        case 'kegiatan':
            $sql = "SELECT jk.kegiatan_id, jk.nama_kegiatan, k.nama_kamar 
                    FROM jadwal_kegiatan jk 
                    JOIN kamar k ON jk.kamar_id = k.kamar_id";
            if (!empty($searchTerm)) {
                $sql .= " WHERE jk.nama_kegiatan LIKE ? OR k.nama_kamar LIKE ?";
            }
            $sql .= " ORDER BY jk.nama_kegiatan";
            
            $stmt = $conn->prepare($sql);
            if (!empty($searchTerm)) {
                $searchPattern = '%' . $searchTerm . '%';
                $stmt->bind_param("ss", $searchPattern, $searchPattern);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $items[] = [
                    'id' => $row['kegiatan_id'],
                    'text' => $row['nama_kegiatan'] . ' (' . $row['nama_kamar'] . ')'
                ];
            }
            break;
    }

    echo json_encode(['items' => $items]);
    exit();
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
    <title>Jurnal - Sistem Absensi Online</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.5/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
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
        
        .jurnal-card {
            transition: transform 0.2s;
        }
        
        .jurnal-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        /*<!-- untuk select2-dropdown-wide Styles -->*/
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
        
        /* Tambahkan style untuk Select2 yang konsisten dengan database.php */
        .select2-container--bootstrap-5 .select2-selection {
            min-height: 38px;
            border: 1px solid #ced4da;
        }
        
        .select2-container--bootstrap-5 .select2-selection--single .select2-selection__rendered {
            line-height: 36px;
        }
        
        .select2-container--bootstrap-5 .select2-dropdown {
            border: 1px solid #ced4da;
            border-radius: 0.375rem;
        }
        
        .select2-container--bootstrap-5 .select2-results__option--highlighted[aria-selected] {
            background-color: #0d6efd;
            color: white;
        }
        
        /* Style untuk placeholder */
        .select2-container--bootstrap-5 .select2-selection--single .select2-selection__placeholder {
            color: #6c757d !important;
            font-style: italic;
        }
        
        /* Hover state */
        .select2-container--bootstrap-5 .select2-selection--single:hover {
            border-color: #86b7fe;
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
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
            <h2><i class="bi bi-clipboard-data me-2"></i> Manajemen Jurnal</h2>
            <div>
                <?php if ($_SESSION['role'] == 'admin' || $_SESSION['role'] == 'staff'): ?>
                <button class="btn btn-primary p-0">
                    <a href="https://docs.google.com/spreadsheets/d/1o8Q5i4Wk2x2o_kT9Hfaud6DYhbrSznrqX9EVQySfRr0/edit#gid=1913039132" 
                       target="_blank" 
                       class="btn btn-primary p-2">
                        <i class="bi bi-file-earmark-excel me-0"></i> Google Sheet
                    </a>
                </button>
                <?php endif; ?>
                <button class="btn btn-success">
                    <i class="bi bi-printer me-1"></i> Cetak Laporan
                </button>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header bg-light">
                <h5 class="card-title mb-0"><i class="bi bi-funnel me-1"></i> Filter Jurnal</h5>
            </div>
            <div class="card-body">
                <form method="GET" action="">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Periode Mulai</label>
                            <input type="date" class="form-control" name="start_date" value="<?= $start_date ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Periode Akhir</label>
                            <input type="date" class="form-control" name="end_date" value="<?= $end_date ?>">
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Kelas Qur'an</label>
                            <select class="form-select select2-kelas-quran" name="kelas_quran_id">
                                <option value="">-- Semua Kelas Qur'an --</option>
                                <?php foreach ($kelas_quran_list as $kelas): ?>
                                <option value="<?= $kelas['id'] ?>" <?= $kelas_quran_id == $kelas['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($kelas['nama_kelas']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Kelas Madin</label>
                            <select class="form-select select2-kelas-madin" name="kelas_id">
                                <option value="">-- Semua Kelas Madin --</option>
                                <?php foreach ($kelas_list as $kelas): ?>
                                <option value="<?= $kelas['kelas_id'] ?>" <?= $kelas_id == $kelas['kelas_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($kelas['nama_kelas']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Kamar</label>
                            <select class="form-select select2-kamar" name="kamar_id">
                                <option value="">-- Semua Kamar --</option>
                                <?php foreach ($kamar_list as $kamar): ?>
                                <option value="<?= $kamar['kamar_id'] ?>" <?= $kamar_id == $kamar['kamar_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($kamar['nama_kamar']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-secondary">Tampilkan</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-body">
                <ul class="nav nav-tabs mb-4" id="jurnalTab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="quran-tab" data-bs-toggle="tab" data-bs-target="#quran" type="button">Guru Qur'an</button>
                    </li>
                    
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="madin-tab" data-bs-toggle="tab" data-bs-target="#madin" type="button">Guru Madin</button>
                    </li>
                    
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="kamar-tab" data-bs-toggle="tab" data-bs-target="#kamar" type="button">Pembina Kamar</button>
                    </li>
                </ul>
                
                <div class="tab-content" id="jurnalTabContent">
                    <!-- Tab Jurnal Madin -->
                    <div class="tab-pane fade show active" id="madin" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h5 class="card-title mb-0">Jurnal Guru Madin</h5>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#jurnalMadinModal">
                                <i class="bi bi-plus-circle me-1"></i> Tambah Jurnal
                            </button>
                        </div>
                        
                        <?php if ($jurnal_madin): ?>
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead class="table-primary">
                                    <tr>
                                        <th>Tanggal</th>
                                        <th>Guru</th>
                                        <th>Kelas</th>
                                        <th>Materi</th>
                                        <th>Catatan</th>
                                        <th>Kendala</th>
                                        <th width="100">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($jurnal_madin as $jurnal): ?>
                                    <tr>
                                        <td><?= $jurnal['tanggal'] ?></td>
                                        <td><?= htmlspecialchars($jurnal['guru_nama']) ?></td>
                                        <td><?= htmlspecialchars($jurnal['nama_kelas']) ?></td>
                                        <td><?= htmlspecialchars($jurnal['materi']) ?></td>
                                        <td><?= htmlspecialchars($jurnal['catatan']) ?></td>
                                        <td><?= htmlspecialchars($jurnal['kendala']) ?></td>
                                        <td>
                                            <a href="?edit_jurnal_madin=<?= $jurnal['id'] ?>" class="btn btn-sm btn-primary">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <a href="?hapus_jurnal_madin=<?= $jurnal['id'] ?>" class="btn btn-sm btn-danger" 
                                               onclick="return confirm('Yakin ingin menghapus jurnal ini?')">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <div class="alert alert-info">Tidak ada data jurnal untuk Guru Madin.</div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Tab Jurnal Quran -->
                    <div class="tab-pane fade" id="quran" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h5 class="card-title mb-0">Jurnal Guru Qur'an</h5>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#jurnalQuranModal">
                                <i class="bi bi-plus-circle me-1"></i> Tambah Jurnal
                            </button>
                        </div>
                        
                        <?php if ($jurnal_quran): ?>
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead class="table-primary">
                                    <tr>
                                        <th>Tanggal</th>
                                        <th>Guru</th>
                                        <th>Kelas</th>
                                        <th>Materi</th>
                                        <th>Catatan</th>
                                        <th>Kendala</th>
                                        <th width="100">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($jurnal_quran as $jurnal): ?>
                                    <tr>
                                        <td><?= $jurnal['tanggal'] ?></td>
                                        <td><?= htmlspecialchars($jurnal['guru_nama']) ?></td>
                                        <td><?= htmlspecialchars($jurnal['nama_kelas']) ?></td>
                                        <td><?= htmlspecialchars($jurnal['materi']) ?></td>
                                        <td><?= htmlspecialchars($jurnal['catatan']) ?></td>
                                        <td><?= htmlspecialchars($jurnal['kendala']) ?></td>
                                        <td>
                                            <a href="?edit_jurnal_quran=<?= $jurnal['id'] ?>" class="btn btn-sm btn-primary">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <a href="?hapus_jurnal_quran=<?= $jurnal['id'] ?>" class="btn btn-sm btn-danger" 
                                               onclick="return confirm('Yakin ingin menghapus jurnal ini?')">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <div class="alert alert-info">Tidak ada data jurnal untuk Guru Qur'an.</div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Tab Jurnal Kamar -->
                    <div class="tab-pane fade" id="kamar" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h5 class="card-title mb-0">Jurnal Pembina Kamar</h5>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#jurnalKamarModal">
                                <i class="bi bi-plus-circle me-1"></i> Tambah Jurnal
                            </button>
                        </div>
                        
                        <?php if ($jurnal_kamar): ?>
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead class="table-primary">
                                    <tr>
                                        <th>Tanggal</th>
                                        <th>Pembina</th>
                                        <th>Kamar</th>
                                        <th>Kegiatan</th>
                                        <th>Catatan</th>
                                        <th>Kendala</th>
                                        <th width="100">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($jurnal_kamar as $jurnal): ?>
                                    <tr>
                                        <td><?= $jurnal['tanggal'] ?></td>
                                        <td><?= htmlspecialchars($jurnal['pembina_nama']) ?></td>
                                        <td><?= htmlspecialchars($jurnal['nama_kamar']) ?></td>
                                        <td><?= htmlspecialchars($jurnal['kegiatan']) ?></td>
                                        <td><?= htmlspecialchars($jurnal['catatan']) ?></td>
                                        <td><?= htmlspecialchars($jurnal['kendala']) ?></td>
                                        <td>
                                            <a href="?edit_jurnal_kamar=<?= $jurnal['id'] ?>" class="btn btn-sm btn-primary">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <a href="?hapus_jurnal_kamar=<?= $jurnal['id'] ?>" class="btn btn-sm btn-danger" 
                                               onclick="return confirm('Yakin ingin menghapus jurnal ini?')">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <div class="alert alert-info">Tidak ada data jurnal untuk Pembina Kamar.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Jurnal Madin -->
    <div class="modal fade" id="jurnalMadinModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Tambah Jurnal Madin</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Tanggal <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" name="tanggal" value="<?= date('Y-m-d') ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Kelas <span class="text-danger">*</span></label>
                                <select class="form-select" name="kelas_id" required>
                                    <option value="">-- Pilih Kelas --</option>
                                    <?php foreach ($kelas_list as $kelas): ?>
                                    <option value="<?= $kelas['kelas_id'] ?>"><?= htmlspecialchars($kelas['nama_kelas']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12 mb-3">
                                <label class="form-label">Materi <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="materi" required>
                            </div>
                            <div class="col-12 mb-3">
                                <label class="form-label">Catatan <span class="text-danger">*</span></label>
                                <textarea class="form-control" name="catatan" rows="3" required></textarea>
                            </div>
                            <div class="col-12 mb-3">
                                <label class="form-label">Kendala</label>
                                <textarea class="form-control" name="kendala" rows="2"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="tambah_jurnal_madin" class="btn btn-primary">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Jurnal Quran -->
    <div class="modal fade" id="jurnalQuranModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Tambah Jurnal Qur'an</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Tanggal <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" name="tanggal" value="<?= date('Y-m-d') ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Kelas Qur'an <span class="text-danger">*</span></label>
                                <select class="form-select" name="kelas_quran_id" required>
                                    <option value="">-- Pilih Kelas Qur'an --</option>
                                    <?php foreach ($kelas_quran_list as $kelas): ?>
                                    <option value="<?= $kelas['id'] ?>"><?= htmlspecialchars($kelas['nama_kelas']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12 mb-3">
                                <label class="form-label">Materi <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="materi" required>
                            </div>
                            <div class="col-12 mb-3">
                                <label class="form-label">Catatan <span class="text-danger">*</span></label>
                                <textarea class="form-control" name="catatan" rows="3" required></textarea>
                            </div>
                            <div class="col-12 mb-3">
                                <label class="form-label">Kendala</label>
                                <textarea class="form-control" name="kendala" rows="2"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="tambah_jurnal_quran" class="btn btn-primary">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Jurnal Kamar -->
    <div class="modal fade" id="jurnalKamarModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Tambah Jurnal Kamar</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Tanggal <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" name="tanggal" value="<?= date('Y-m-d') ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Kamar <span class="text-danger">*</span></label>
                                <select class="form-select" name="kamar_id" required>
                                    <option value="">-- Pilih Kamar --</option>
                                    <?php foreach ($kamar_list as $kamar): ?>
                                    <option value="<?= $kamar['kamar_id'] ?>"><?= htmlspecialchars($kamar['nama_kamar']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12 mb-3">
                                <label class="form-label">Kegiatan <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="kegiatan" required>
                            </div>
                            <div class="col-12 mb-3">
                                <label class="form-label">Catatan <span class="text-danger">*</span></label>
                                <textarea class="form-control" name="catatan" rows="3" required></textarea>
                            </div>
                            <div class="col-12 mb-3">
                                <label class="form-label">Kendala</label>
                                <textarea class="form-control" name="kendala" rows="2"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="tambah_jurnal_kamar" class="btn btn-primary">Simpan</button>
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
    
    // PERBAIKAN: Inisialisasi Select2 untuk filter dropdown - KONSISTEN dengan database.php
    function initSelect2Filter(selector, type, placeholder) {
        try {
            if ($(selector).length === 0) {
                console.error('Elemen ' + selector + ' tidak ditemukan');
                return;
            }
            
            $(selector).select2({
                theme: 'bootstrap-5',
                placeholder: placeholder,
                allowClear: true,
                width: '100%',
                ajax: {
                    url: 'jurnal.php',
                    dataType: 'json',
                    delay: 300,
                    data: function (params) {
                        return {
                            q: params.term || '',
                            type: type
                        };
                    },
                    processResults: function (data) {
                        if (!data.items || data.items.length === 0) {
                            return {
                                results: []
                            };
                        }
                        return {
                            results: data.items
                        };
                    },
                    cache: true
                },
                minimumInputLength: 0
            }).on('select2:open', function () {
                // Trigger pencarian kosong saat dropdown dibuka
                setTimeout(function() {
                    $('.select2-search__field').val('').trigger('input');
                }, 100);
            });
            
        } catch (error) {
            console.error('Error inisialisasi Select2 untuk ' + selector + ':', error);
        }
    }
    
    // Inisialisasi DataTables dan Select2
    $(document).ready(function() {
        // Inisialisasi semua filter Select2
        initSelect2Filter('.select2-kelas-quran', 'kelas_quran', 'Ketik untuk Cari Kelas Qur\'an...');
        initSelect2Filter('.select2-kelas-madin', 'kelas_madin', 'Ketik untuk Cari Kelas Madin...');
        initSelect2Filter('.select2-kamar', 'kamar', 'Ketik untuk Cari Kamar...');
        
        $('table').DataTable({
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.5/i18n/id.json'
            },
            pageLength: 10,
            lengthMenu: [[5, 10, 25, 50, -1], [5, 10, 25, 50, "Semua"]]
        });
    });
    
    // Force load all data when dropdown is opened for the first time
    $('.select2-kelas-quran, .select2-kelas-madin, .select2-kamar, .select2-kegiatan').on('select2:open', function (e) {
        var $dropdown = $(this);
        setTimeout(function() {
            var $search = $dropdown.data('select2').$dropdown.find('.select2-search__field');
            if ($search.val() === '') {
                $search.trigger('input');
            }
        }, 100);
    });
    </script>
</body>
</html>