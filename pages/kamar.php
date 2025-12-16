<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// pages/kamar.php
require_once '../includes/init.php';

if (!check_auth()) {
    header("Location: ../index.php");
    exit();
}

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
$current_kamar = null;

// Proses CRUD Kamar
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['tambah_kamar'])) {
        $nama_kamar = trim($_POST['nama_kamar']);
        $kapasitas = intval($_POST['kapasitas']) ?? 0;
        $keterangan = trim($_POST['keterangan']) ?? '';
        $guru_id_post = $_POST['guru_id'] ?? null;
        if ($guru_id_post === '') $guru_id_post = null;
        
        // Validasi nama unik
        $sql_check = "SELECT * FROM kamar WHERE nama_kamar = ?";
        $stmt_check = $conn->prepare($sql_check);
        $stmt_check->bind_param("s", $nama_kamar);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();
        
        if ($result_check->num_rows > 0) {
            $message = "danger|Nama kamar sudah digunakan!";
        } else {
            $sql = "INSERT INTO kamar (nama_kamar, kapasitas, keterangan, guru_id) VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sisi", $nama_kamar, $kapasitas, $keterangan, $guru_id_post);
            
            if ($stmt->execute()) {
                $message = "success|Kamar berhasil ditambahkan!";
            } else {
                $message = "danger|Error: " . $stmt->error;
            }
        }
    }
    elseif (isset($_POST['edit_kamar'])) {
        $id = intval($_POST['id']);
        $nama_kamar = trim($_POST['nama_kamar']);
        $kapasitas = intval($_POST['kapasitas']) ?? 0;
        $keterangan = trim($_POST['keterangan']) ?? '';
        $guru_id_post = $_POST['guru_id'] ?? null;
        if ($guru_id_post === '') $guru_id_post = null;
        
        // Validasi nama unik (kecuali diri sendiri)
        $sql_check = "SELECT * FROM kamar WHERE nama_kamar = ? AND kamar_id <> ?";
        $stmt_check = $conn->prepare($sql_check);
        $stmt_check->bind_param("si", $nama_kamar, $id);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();
        
        if ($result_check->num_rows > 0) {
            $message = "danger|Nama kamar sudah digunakan!";
        } else {
            $sql = "UPDATE kamar SET nama_kamar = ?, kapasitas = ?, keterangan = ?, guru_id = ? WHERE kamar_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sisii", $nama_kamar, $kapasitas, $keterangan, $guru_id_post, $id);
            
            if ($stmt->execute()) {
                $message = "success|Kamar berhasil diperbarui!";
                // Redirect untuk menghindari parameter edit tetap di URL
                header("Location: kamar.php?message=success|Kamar berhasil diperbarui!");
                exit();
            } else {
                $message = "danger|Error: " . $stmt->error;
            }
        }
    }
}

// Proses Hapus Kamar
if (isset($_GET['hapus'])) {
    $id = intval($_GET['hapus']);
    
    // Perbaikan validasi penggunaan kamar
    $sql_check = "SELECT COUNT(*) as jumlah FROM murid WHERE kamar_id = ?";
    $stmt_check = $conn->prepare($sql_check);
    $stmt_check->bind_param("i", $id);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    $row_check = $result_check->fetch_assoc();
    
    if ($row_check['jumlah'] > 0) {
        $message = "danger|Kamar tidak dapat dihapus karena masih digunakan oleh ".$row_check['jumlah']." murid!";
    } else {
        $sql = "DELETE FROM kamar WHERE kamar_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            $message = "success|Kamar berhasil dihapus!";
        } else {
            $message = "danger|Error: " . $stmt->error;
        }
    }
}

// Ambil data untuk edit
if (isset($_GET['edit'])) {
    $id = intval($_GET['edit']);
    
    $sql = "SELECT * FROM kamar WHERE kamar_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $current_kamar = $result->fetch_assoc();
    }
}

// PERBAIKAN UTAMA: Query data kamar dengan logika yang benar
$kamar_list = [];
$result = null; // Inisialisasi variabel result

if ($_SESSION['role'] === 'guru' && $guru_id) {
    // Untuk guru, hanya tampilkan kamar yang memiliki murid yang diajar oleh guru
    $sql = "SELECT DISTINCT k.*, g.nama as nama_guru, g.no_hp as no_hp_guru 
            FROM kamar k 
            LEFT JOIN guru g ON k.guru_id = g.guru_id
            WHERE k.kamar_id IN (
                SELECT m.kamar_id 
                FROM murid m
                LEFT JOIN kelas_madin km ON m.kelas_madin_id = km.kelas_id
                LEFT JOIN kelas_quran kq ON m.kelas_quran_id = kq.id
                WHERE (km.guru_id = ? OR kq.guru_id = ?) AND m.kamar_id IS NOT NULL
            )";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("ii", $guru_id, $guru_id);
        $stmt->execute();
        $result = $stmt->get_result();
    }
} else {
    // Untuk admin, tampilkan semua kamar
    $sql = "SELECT k.*, g.nama as nama_guru, g.no_hp as no_hp_guru 
            FROM kamar k 
            LEFT JOIN guru g ON k.guru_id = g.guru_id";
    $result = $conn->query($sql);
}

// PERBAIKAN: Handle jika query berhasil
if ($result !== null && $result !== false) {
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $kamar_list[] = $row;
        }
    }
} else {
    // Handle error query
    $message = "danger|Error mengambil data kamar: " . $conn->error;
}

// PERBAIKAN BARU: Hitung jumlah murid per kamar
$kamar_terisi = [];
if (!empty($kamar_list)) {
    $kamar_ids = array_column($kamar_list, 'kamar_id');
    $placeholders = str_repeat('?,', count($kamar_ids) - 1) . '?';
    
    $sql_count = "SELECT kamar_id, COUNT(*) as jumlah FROM murid WHERE kamar_id IN ($placeholders) GROUP BY kamar_id";
    $stmt_count = $conn->prepare($sql_count);
    $stmt_count->bind_param(str_repeat('i', count($kamar_ids)), ...$kamar_ids);
    $stmt_count->execute();
    $result_count = $stmt_count->get_result();
    
    while ($row_count = $result_count->fetch_assoc()) {
        $kamar_terisi[$row_count['kamar_id']] = $row_count['jumlah'];
    }
}

// PERBAIKAN: Ambil data guru untuk dropdown - Handle query error
$sql_guru = "SELECT guru_id, nama, no_hp FROM guru ORDER BY nama";
$result_guru = $conn->query($sql_guru);

// PERBAIKAN: Handle jika query guru gagal
if ($result_guru === false) {
    $message = "danger|Error mengambil data guru: " . $conn->error;
    $guru_list = [];
} else {
    $guru_list = [];
    if ($result_guru->num_rows > 0) {
        while ($row = $result_guru->fetch_assoc()) {
            $guru_list[] = $row;
        }
    }
}

// Handle message from redirect
if (isset($_GET['message'])) {
    $message = $_GET['message'];
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
    <title>Manajemen Kamar - Sistem Absensi Online</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.5/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    
    <style>
    /* Loading Animation Styles */
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

    /* Tambahan styling untuk mobile */
    .btn-action {
        min-width: 44px;
        min-height: 44px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        margin: 2px;
    }
    .table-responsive {
        overflow-x: auto;
    }
    /* Memastikan modal responsif di mobile */
    @media (max-width: 576px) {
        .modal-dialog {
            margin: 10px;
            width: auto;
        }
        .modal-content {
            padding: 15px;
        }
        .table td, .table th {
            padding: 0.5rem;
            font-size: 0.875rem;
        }
        
        /* Memperbaiki z-index untuk mobile */
        .modal-backdrop {
            z-index: 1040;
        }
        .modal {
            z-index: 1050;
        }
    }
    
    /* Memastikan form input mudah digunakan di mobile */
    .form-control, .form-select {
        font-size: 16px; /* Mencegah zoom pada iOS */
    }
    
    /* Memastikan tombol mudah diklik di mobile */
    .btn {
        padding: 0.5rem 1rem;
        font-size: 1rem;
    }
    
    /* PERBAIKAN UTAMA: Atur lebar kolom secara proporsional */
    #kamarTable th:nth-child(1),
    #kamarTable td:nth-child(1) {
        width: 5% !important; /* ID */
        min-width: 50px;
    }
    
    #kamarTable th:nth-child(2),
    #kamarTable td:nth-child(2) {
        width: 15% !important; /* Nama Kamar */
        min-width: 120px;
    }
    
    #kamarTable th:nth-child(3),
    #kamarTable td:nth-child(3) {
        width: 8% !important; /* Kapasitas */
        min-width: 80px;
    }
    
    #kamarTable th:nth-child(4),
    #kamarTable td:nth-child(4) {
        width: 8% !important; /* Terisi */
        min-width: 70px;
    }
    
    #kamarTable th:nth-child(5),
    #kamarTable td:nth-child(5) {
        width: 10% !important; /* Sisa */
        min-width: 90px;
    }
    
    #kamarTable th:nth-child(6),
    #kamarTable td:nth-child(6) {
        width: 15% !important; /* Keterangan */
        min-width: 130px;
    }
    
    #kamarTable th:nth-child(7),
    #kamarTable td:nth-child(7) {
        width: 15% !important; /* Pembina */
        min-width: 130px;
    }
    
    #kamarTable th:nth-child(8),
    #kamarTable td:nth-child(8) {
        width: 8% !important; /* Kontak WA */
        min-width: 70px;
        text-align: center;
    }
    
    #kamarTable th:nth-child(9),
    #kamarTable td:nth-child(9) {
        width: 12% !important; /* Aksi */
        min-width: 100px;
        text-align: center;
    }
    
    /* Styling untuk status kapasitas */
    .badge.bg-success {
        background-color: #198754 !important;
    }
    .badge.bg-danger {
        background-color: #dc3545 !important;
    }
    .badge.bg-warning {
        background-color: #ffc107 !important;
        color: #000 !important;
    }
    
    /* PERBAIKAN: Pastikan tabel responsif */
    @media (max-width: 768px) {
        #kamarTable th,
        #kamarTable td {
            font-size: 0.8rem;
            padding: 0.3rem;
        }
        
        .btn-action {
            min-width: 36px;
            min-height: 36px;
            padding: 0.25rem;
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
            <h2><i class="bi bi-door-closed me-2"></i> Manajemen Kamar</h2>
            
            <?php if ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'staff'): ?>
            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#kamarModal" id="btnTambahKamar">
                <i class="bi bi-plus-circle me-1"></i> Tambah Kamar
            </button>
            <?php endif; ?>
        </div>

        <div class="card shadow-sm">
            <div class="card-body">
                <div class="table-responsive">
                    <table id="kamarTable" class="table table-hover table-striped">
                        <!-- PERBAIKAN: Header tabel konsisten untuk semua role -->
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nama Kamar</th>
                                <th>Kapasitas</th>
                                <th>Terisi</th>
                                <th>Sisa</th>
                                <th>Keterangan</th>
                                <th>Pembina</th>
                                <th>WA</th>
                                <?php if (in_array($_SESSION['role'], ['admin', 'staff'])): ?>
                                <th width="120">Aksi</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        
                        <!-- PERBAIKAN: Body tabel konsisten untuk semua role -->
                        <tbody>
                            <?php if (!empty($kamar_list)): ?>
                                <?php foreach ($kamar_list as $kamar): ?>
                                    <?php
                                    // Hitung jumlah murid dan sisa kapasitas
                                    $kamar_id = $kamar['kamar_id'];
                                    $count = isset($kamar_terisi[$kamar_id]) ? $kamar_terisi[$kamar_id] : 0;
                                    $sisa = $kamar['kapasitas'] - $count;
                                    ?>
                                    <tr>
                                        <td><?= $kamar['kamar_id'] ?></td>
                                        <td><?= htmlspecialchars($kamar['nama_kamar']) ?></td>
                                        <td><?= $kamar['kapasitas'] ?></td>
                                        <td><?= $count ?></td>
                                        <td>
                                            <?php if ($sisa > 0): ?>
                                                <span class="badge bg-success"><?= $sisa ?> tersedia</span>
                                            <?php elseif ($sisa == 0): ?>
                                                <span class="badge bg-warning">Penuh</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger"><?= abs($sisa) ?> kelebihan</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($kamar['keterangan'])): ?>
                                                <span title="<?= htmlspecialchars($kamar['keterangan']) ?>">
                                                    <?= strlen($kamar['keterangan']) > 30 ? substr(htmlspecialchars($kamar['keterangan']), 0, 30) . '...' : htmlspecialchars($kamar['keterangan']) ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($kamar['nama_guru'] ?? 'Belum ada pembina') ?></td>
                                        <td>
                                            <?php if (!empty($kamar['no_hp_guru'])): ?>
                                            <a href="https://wa.me/62<?= substr(htmlspecialchars($kamar['no_hp_guru']), 1) ?>" 
                                               class="btn btn-sm btn-success btn-action" target="_blank" title="Hubungi via WhatsApp">
                                                <i class="bi bi-whatsapp"></i>
                                            </a>
                                            <?php else: ?>
                                            <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <?php if (in_array($_SESSION['role'], ['admin', 'staff'])): ?>
                                        <td>
                                            <div class="d-flex flex-nowrap">
                                                <a href="?edit=<?= $kamar['kamar_id'] ?>" class="btn btn-sm btn-primary btn-action" title="Edit">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <a href="?hapus=<?= $kamar['kamar_id'] ?>" class="btn btn-sm btn-danger btn-action" 
                                                   onclick="return confirm('Yakin ingin menghapus kamar <?= htmlspecialchars($kamar['nama_kamar']) ?>?')" title="Hapus">
                                                    <i class="bi bi-trash"></i>
                                                </a>
                                            </div>
                                        </td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="<?= in_array($_SESSION['role'], ['admin', 'staff']) ? '9' : '8' ?>" class="text-center text-muted">
                                        <?= ($_SESSION['role'] === 'guru') ? 'Tidak ada kamar yang terkait dengan murid-murid Anda' : 'Tidak ada data kamar' ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Tambah/Edit Kamar -->
    <div class="modal fade" id="kamarModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <?= $current_kamar ? 'Edit Kamar' : 'Tambah Kamar' ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <?php if ($current_kamar): ?>
                        <input type="hidden" name="id" value="<?= $current_kamar['kamar_id'] ?>">
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <label class="form-label">Nama Kamar <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="nama_kamar" 
                                value="<?= $current_kamar['nama_kamar'] ?? '' ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Kapasitas <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="kapasitas" min="1" 
                                value="<?= $current_kamar['kapasitas'] ?? 4 ?>" required>
                        </div>
                        
                        <!-- Dropdown Pembina Kamar -->
                        <div class="mb-3">
                            <label class="form-label">Pembina Kamar</label>
                            <select class="form-select" name="guru_id">
                                <option value="">-- Pilih Pembina --</option>
                                <?php foreach ($guru_list as $guru): ?>
                                <option value="<?= $guru['guru_id'] ?>" 
                                    <?= ($current_kamar['guru_id'] ?? '') == $guru['guru_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($guru['nama']) ?> 
                                    <?= !empty($guru['no_hp']) ? ' - ' . $guru['no_hp'] : '' ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Keterangan Lainnya</label>
                            <textarea class="form-control" rows="3" name="keterangan"><?= $current_kamar['keterangan'] ?? '' ?></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="<?= $current_kamar ? 'edit_kamar' : 'tambah_kamar' ?>" class="btn btn-primary">
                            <?= $current_kamar ? 'Simpan Perubahan' : 'Simpan' ?>
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
    <script>
        $(document).ready(function() {
            // PERBAIKAN UTAMA: Konfigurasi DataTable yang benar
            const isAdminOrStaff = <?= in_array($_SESSION['role'], ['admin', 'staff']) ? 'true' : 'false' ?>;
            
            // Konfigurasi kolom berdasarkan role
            let columnDefs = [
                { 
                    targets: 0, // Kolom ID
                    width: '5%'
                },
                { 
                    targets: 1, // Kolom Nama Kamar
                    width: '15%'
                },
                { 
                    targets: 2, // Kolom Kapasitas
                    width: '8%'
                },
                { 
                    targets: 3, // Kolom Terisi
                    width: '8%'
                },
                { 
                    targets: 4, // Kolom Sisa
                    width: '10%'
                },
                { 
                    targets: 5, // Kolom Keterangan
                    width: '15%'
                },
                { 
                    targets: 6, // Kolom Pembina
                    width: '15%'
                },
                { 
                    targets: 7, // Kolom WA
                    width: '8%',
                    orderable: false,
                    className: 'text-center'
                }
            ];

            // Tambahkan konfigurasi untuk kolom Aksi hanya untuk admin/staff
            if (isAdminOrStaff) {
                columnDefs.push({
                    targets: 8, // Kolom Aksi
                    width: '12%',
                    orderable: false,
                    className: 'text-center'
                });
            }

            // Inisialisasi DataTable
            var table = $('#kamarTable').DataTable({
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.5/i18n/id.json',
                    emptyTable: "Tidak ada data kamar"
                },
                columnDefs: columnDefs,
                responsive: true,
                autoWidth: false,
                pageLength: 25,
                scrollX: true,
                scrollCollapse: true,
                fixedHeader: {
                    header: true,
                    headerOffset: $('.navbar').outerHeight() || 0
                },
                dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>rt<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
                drawCallback: function(settings) {
                    var api = this.api();
                    var rows = api.rows({page:'current'}).nodes().length;
                    
                    // Sembunyikan pagination dan info jika tidak ada data
                    if (rows === 0) {
                        $('.dataTables_paginate, .dataTables_info').hide();
                    } else {
                        $('.dataTables_paginate, .dataTables_info').show();
                    }
                    
                    // Force reflow untuk menghindari rendering issues
                    setTimeout(function() {
                        api.columns.adjust().responsive.recalc();
                    }, 100);
                },
                initComplete: function(settings, json) {
                    var api = this.api();
                    if (api.data().count() === 0) {
                        $('.dataTables_empty').html('Tidak ada data kamar');
                    }
                    
                    // Pastikan header tetap sync
                    setTimeout(function() {
                        api.columns.adjust();
                    }, 100);
                }
            });
            
            // Handler untuk tombol tambah
            $('#btnTambahKamar').click(function() {
                // Reset form
                $('#kamarModal form')[0].reset();
                // Ubah judul modal
                $('#kamarModal .modal-title').text('Tambah Kamar');
                // Ubah teks tombol submit
                $('#kamarModal button[type="submit"]').text('Simpan').attr('name', 'tambah_kamar');
                // Hapus input hidden jika ada
                $('#kamarModal input[name="id"]').remove();
            });
            
            // Auto close modal setelah operasi sukses
            <?php if ($message && strpos($message, 'success') === 0): ?>
            $('#kamarModal').modal('hide');
            <?php endif; ?>
            
            // Tampilkan modal edit jika parameter edit ada
            <?php if ($current_kamar): ?>
            $('#kamarModal').modal('show');
            <?php endif; ?>
            
            // Bersihkan parameter URL saat modal ditutup
            $('#kamarModal').on('hidden.bs.modal', function () {
                // Hapus parameter edit dari URL
                if (window.location.search.includes('edit=')) {
                    const url = new URL(window.location);
                    url.searchParams.delete('edit');
                    window.history.replaceState({}, document.title, url.toString());
                }
            });

            // PERBAIKAN: Pastikan tabel di-refresh setelah modal ditutup
            $('#kamarModal').on('hidden.bs.modal', function () {
                setTimeout(function() {
                    table.columns.adjust().responsive.recalc();
                }, 300);
            });
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