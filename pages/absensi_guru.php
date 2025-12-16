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

// PROSES UPDATE ABSENSI GURU
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_absensi_guru'])) {
    $guru_id = $_POST['guru_id'];
    $tanggal = $_POST['tanggal'];
    $status = $_POST['status'];
    $keterangan = $_POST['keterangan'] ?? '';

    if ($guru_id === 'all') {
        // Jika memilih semua guru, proses untuk setiap guru
        $success_count = 0;
        $error_count = 0;
        
        foreach ($guru_list as $guru) {
            $current_guru_id = $guru['guru_id'];
            
            // Cek apakah sudah ada absensi
            $sql_check = "SELECT * FROM absensi_guru WHERE guru_id = ? AND tanggal = ?";
            $stmt_check = $conn->prepare($sql_check);
            $stmt_check->bind_param("is", $current_guru_id, $tanggal);
            $stmt_check->execute();
            $result_check = $stmt_check->get_result();

            if ($result_check->num_rows > 0) {
                // Update
                $sql = "UPDATE absensi_guru SET status = ?, keterangan = ? WHERE guru_id = ? AND tanggal = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssis", $status, $keterangan, $current_guru_id, $tanggal);
            } else {
                // Insert
                $sql = "INSERT INTO absensi_guru (guru_id, tanggal, status, keterangan) VALUES (?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("isss", $current_guru_id, $tanggal, $status, $keterangan);
            }

            if ($stmt->execute()) {
                $success_count++;
            } else {
                $error_count++;
            }
        }
        
        if ($error_count === 0) {
            $_SESSION['message'] = "success|Absensi berhasil diperbarui untuk semua guru ($success_count guru)!";
        } else {
            $_SESSION['message'] = "warning|Absensi berhasil untuk $success_count guru, gagal untuk $error_count guru!";
        }
        
    } else {
        // Proses untuk satu guru saja (kode yang sudah ada)
        $guru_id = (int)$guru_id;
        
        // Cek apakah sudah ada absensi
        $sql_check = "SELECT * FROM absensi_guru WHERE guru_id = ? AND tanggal = ?";
        $stmt_check = $conn->prepare($sql_check);
        $stmt_check->bind_param("is", $guru_id, $tanggal);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();

        if ($result_check->num_rows > 0) {
            // Update
            $sql = "UPDATE absensi_guru SET status = ?, keterangan = ? WHERE guru_id = ? AND tanggal = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssis", $status, $keterangan, $guru_id, $tanggal);
        } else {
            // Insert
            $sql = "INSERT INTO absensi_guru (guru_id, tanggal, status, keterangan) VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("isss", $guru_id, $tanggal, $status, $keterangan);
        }

        if ($stmt->execute()) {
            $_SESSION['message'] = "success|Absensi guru berhasil diperbarui!";
        } else {
            $_SESSION['message'] = "danger|Error: " . $stmt->error;
        }
    }
    
    header("Location: absensi_guru.php");
    exit();
}

// PROSES HAPUS ABSENSI GURU
if (isset($_GET['delete_absensi'])) {
    $guru_id = $_GET['guru_id'];
    $tanggal = $_GET['tanggal'];
    
    $sql = "DELETE FROM absensi_guru WHERE guru_id = ? AND tanggal = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("is", $guru_id, $tanggal);
    
    if ($stmt->execute()) {
        $_SESSION['message'] = "success|Absensi guru berhasil dihapus!";
    } else {
        $_SESSION['message'] = "danger|Error: " . $stmt->error;
    }
    
    header("Location: absensi_guru.php");
    exit();
}

// Ambil parameter filter
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');
$guru_id_filter = $_GET['guru_id'] ?? null;

// Query untuk absensi guru
$absensi_guru = [];
$sql = "SELECT 
            ag.*,
            g.nama as nama_guru
        FROM absensi_guru ag
        JOIN guru g ON ag.guru_id = g.guru_id
        WHERE ag.tanggal BETWEEN ? AND ?";
        
$params = [];
$types = "ss";
$params[] = $start_date;
$params[] = $end_date;

// Perbaikan: Handle filter untuk "Semua Guru"
if ($guru_id_filter && $guru_id_filter !== 'all') {
    $sql .= " AND ag.guru_id = ?";
    $types .= "i";
    $params[] = $guru_id_filter;
}

$sql .= " ORDER BY ag.tanggal DESC, g.nama";

$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $absensi_guru[] = $row;
    }
}

// Ambil semua guru untuk dropdown
$sql_guru_list = "SELECT * FROM guru ORDER BY nama";
$result_guru_list = $conn->query($sql_guru_list);
$guru_list = [];
if ($result_guru_list->num_rows > 0) {
    while ($row = $result_guru_list->fetch_assoc()) {
        $guru_list[] = $row;
    }
}

require_once '../includes/navigation.php';
?>

<!DOCTYPE html>
<html lang="id" data-bs-theme="<?= $dark_mode ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Absensi Guru - Sistem Absensi Online</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-person-check me-2"></i> Kelola Absensi Guru</h2>
        </div>
        
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center">
                <a href="monitor_absensi_otomatis.php" class="btn btn-info">
                    <i class="bi bi-clock-history me-1"></i> Monitor Absensi Otomatis
                </a>
                <a href="absensi_otomatis_setting.php" class="btn btn-warning">
                    <i class="bi bi-gear me-1"></i> Pengaturan Absensi Otomatis
                </a>
            </div>
        </div>

        <!-- Filter -->
        <div class="card mb-4">
            <div class="card-header bg-light">
                <h5 class="card-title mb-0"><i class="bi bi-funnel me-1"></i>Filter</h5>
                

            </div>
            <div class="card-body">
                <form method="GET" action="">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Periode Mulai</label>
                            <input type="date" class="form-control" name="start_date" value="<?= $start_date ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Periode Akhir</label>
                            <input type="date" class="form-control" name="end_date" value="<?= $end_date ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Guru</label>
                            <select class="form-select select2-guru" name="guru_id">
                                <option value="">-- Semua Guru --</option>
                                <option value="all" <?= $guru_id_filter == 'all' ? 'selected' : '' ?>>-- Tampilkan Semua Guru --</option>
                                <?php foreach ($guru_list as $guru): ?>
                                <option value="<?= $guru['guru_id'] ?>" <?= $guru_id_filter == $guru['guru_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($guru['nama']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">Tampilkan</button>
                    <a href="absensi_guru.php" class="btn btn-secondary">Reset</a>
                </form>
            </div>
        </div>
        

        <!-- Tabel Absensi Guru -->
        <div class="card shadow-sm">
            <div class="card-body">
                <!-- Tambahkan di card header atau di bawah judul -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="bi bi-person-check me-2"></i> Kelola Absensi Guru</h2>
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#tambahAbsensiGuruModal">
                            <i class="bi bi-plus-circle me-1"></i> Tambah Absensi
                        </button>
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
        
                <?php if ($absensi_guru): ?>
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <!-- Dalam bagian tabel absensi guru, tambahkan kolom Otomatis -->
                        <thead class="table-primary">
                            <tr>
                                <th>Tanggal</th>
                                <th>Nama Guru</th>
                                <th>Status</th>
                                <th>Keterangan</th>
                                <th>Otomatis</th>
                                <th width="120">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($absensi_guru as $absensi): ?>
                            <tr>
                                <td><?= $absensi['tanggal'] ?></td>
                                <td><?= htmlspecialchars($absensi['nama_guru']) ?></td>
                                <td>
                                    <span class="badge 
                                        <?= $absensi['status'] == 'Hadir' ? 'bg-success' : 
                                           ($absensi['status'] == 'Sakit' ? 'bg-warning' : 
                                           ($absensi['status'] == 'Izin' ? 'bg-info' : 'bg-danger')) ?>">
                                        <?= $absensi['status'] ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($absensi['keterangan']) ?></td>
                                <td>
                                    <?php if ($absensi['is_otomatis'] == 1): ?>
                                        <span class="badge bg-secondary">Otomatis</span>
                                    <?php else: ?>
                                        <span class="badge bg-primary">Manual</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary edit-absensi-guru"
                                            data-guru-id="<?= $absensi['guru_id'] ?>"
                                            data-tanggal="<?= $absensi['tanggal'] ?>"
                                            data-status="<?= $absensi['status'] ?>"
                                            data-keterangan="<?= htmlspecialchars($absensi['keterangan']) ?>">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <a href="absensi_guru.php?delete_absensi=1&guru_id=<?= $absensi['guru_id'] ?>&tanggal=<?= $absensi['tanggal'] ?>" 
                                       class="btn btn-sm btn-outline-danger"
                                       onclick="return confirm('Hapus absensi ini?')">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="alert alert-info">Tidak ada data absensi guru.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    

    <!-- Modal Tambah/Edit Absensi Guru -->
    <div class="modal fade" id="tambahAbsensiGuruModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="absensiGuruModalTitle">Tambah Absensi Guru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="guru_id" id="modalGuruId">
                        
                        <div class="mb-3">
                            <label class="form-label">Tanggal</label>
                            <input type="date" class="form-control" name="tanggal" id="modalTanggal" 
                                   value="<?= date('Y-m-d') ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Guru</label>
                            <select class="form-select select2-guru-modal" name="guru_id" id="modalGuruIdSelect" required>
                                <option value="">-- Pilih Guru --</option>
                                <option value="all">-- Semua Guru --</option>
                                <?php foreach ($guru_list as $guru): ?>
                                <option value="<?= $guru['guru_id'] ?>"><?= htmlspecialchars($guru['nama']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status" id="modalStatus" required>
                                <option value="Hadir">Hadir</option>
                                <option value="Sakit">Sakit</option>
                                <option value="Izin">Izin</option>
                                <option value="Alpa">Alpa</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Keterangan</label>
                            <textarea class="form-control" name="keterangan" id="modalKeterangan" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="update_absensi_guru" class="btn btn-primary">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function() {
            // Inisialisasi Select2
            $('.select2-guru').select2({
                theme: 'bootstrap-5',
                placeholder: "Pilih Guru",
                allowClear: true
            });
    
            $('.select2-guru-modal').select2({
                theme: 'bootstrap-5',
                placeholder: "Pilih Guru",
                dropdownParent: $('#tambahAbsensiGuruModal')
            });
    
            // Handle edit absensi guru
            const editButtons = document.querySelectorAll('.edit-absensi-guru');
            const modal = new bootstrap.Modal(document.getElementById('tambahAbsensiGuruModal'));
            const modalTitle = document.getElementById('absensiGuruModalTitle');
            const modalGuruId = document.getElementById('modalGuruId');
            const modalGuruIdSelect = document.getElementById('modalGuruIdSelect');
            const modalTanggal = document.getElementById('modalTanggal');
            const modalStatus = document.getElementById('modalStatus');
            const modalKeterangan = document.getElementById('modalKeterangan');
    
            editButtons.forEach(button => {
                button.addEventListener('click', function() {
                    modalTitle.textContent = 'Edit Absensi Guru';
                    modalGuruId.value = this.dataset.guruId;
                    // Set select guru
                    modalGuruIdSelect.value = this.dataset.guruId;
                    modalGuruIdSelect.dispatchEvent(new Event('change'));
                    modalTanggal.value = this.dataset.tanggal;
                    modalStatus.value = this.dataset.status;
                    modalKeterangan.value = this.dataset.keterangan;
                    
                    modal.show();
                });
            });
    
            // Reset modal ketika dibuka untuk tambah baru
            document.getElementById('tambahAbsensiGuruModal').addEventListener('show.bs.modal', function(e) {
                if (!e.relatedTarget) {
                    modalTitle.textContent = 'Tambah Absensi Guru';
                    modalGuruId.value = '';
                    modalGuruIdSelect.value = '';
                    modalGuruIdSelect.dispatchEvent(new Event('change'));
                    modalTanggal.value = '<?= date('Y-m-d') ?>';
                    modalStatus.value = 'Hadir';
                    modalKeterangan.value = '';
                }
            });
    
            // Handle ketika memilih "Semua Guru"
            $('#modalGuruIdSelect').on('change', function() {
                if ($(this).val() === 'all') {
                    // Nonaktifkan field lainnya atau beri pesan
                    console.log('Semua guru dipilih');
                }
            });
        });
    </script>
</body>
</html>