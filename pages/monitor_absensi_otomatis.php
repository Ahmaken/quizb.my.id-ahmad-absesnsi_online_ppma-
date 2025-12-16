<?php
session_start();
require_once '../includes/init.php';

// Hanya admin dan staff yang bisa mengakses
if (!check_auth() || !in_array($_SESSION['role'], ['admin', 'staff'])) {
    header("Location: ../index.php");
    exit();
}

$tanggal = $_GET['tanggal'] ?? date('Y-m-d');
$hari_ini = date('l');
$hari_indo = getHariIndonesia($hari_ini);

// Fungsi untuk mendapatkan hari Indonesia
function getHariIndonesia($hariInggris) {
    $day_map = [
        'Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu',
        'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu', 
        'Sunday' => 'Ahad'
    ];
    return $day_map[$hariInggris] ?? $hariInggris;
}

// Fungsi untuk menghitung deadline (1 jam setelah jam selesai)
function hitungDeadline($jam_selesai, $tanggal) {
    $datetime_selesai = DateTime::createFromFormat('Y-m-d H:i:s', $tanggal . ' ' . $jam_selesai);
    $datetime_selesai->modify('+1 hour');
    return $datetime_selesai->format('Y-m-d H:i:s');
}

// Fungsi untuk menghitung keterlambatan
function hitungKeterlambatan($waktu_absensi, $deadline) {
    if (!$waktu_absensi || !$deadline) return null;
    
    $waktu_absensi_dt = DateTime::createFromFormat('Y-m-d H:i:s', $waktu_absensi);
    $deadline_dt = DateTime::createFromFormat('Y-m-d H:i:s', $deadline);
    
    if ($waktu_absensi_dt > $deadline_dt) {
        $interval = $waktu_absensi_dt->diff($deadline_dt);
        return $interval->format('%H:%I:%S');
    }
    return null;
}

// QUERY ABSENSI HARIAN
$sql_harian = "SELECT 
            ag.*,
            g.nama as nama_guru,
            g.no_hp,
            TIMEDIFF(ag.waktu_absensi, ag.deadline_absensi) as selisih_waktu,
            CASE 
                WHEN ag.status = 'Hadir' AND ag.waktu_absensi <= ag.deadline_absensi THEN 'Tepat Waktu'
                WHEN ag.status = 'Hadir' AND ag.waktu_absensi > ag.deadline_absensi THEN 'Terlambat'
                WHEN ag.status = 'Alpa' THEN 'Alpa'
                ELSE 'Belum Absen'
            END as status_kehadiran
        FROM absensi_guru ag
        JOIN guru g ON ag.guru_id = g.guru_id
        WHERE ag.tanggal = ?
        ORDER BY g.nama";

$stmt_harian = $conn->prepare($sql_harian);
$stmt_harian->bind_param("s", $tanggal);
$stmt_harian->execute();
$absensi_guru_harian = $stmt_harian->get_result()->fetch_all(MYSQLI_ASSOC);

// QUERY ABSENSI PER JADWAL - PERBAIKI KONDISI JOIN

// Query untuk mendapatkan semua jadwal Madin hari ini - VERSI DIPERBAIKI
$sql_madin = "SELECT 
                jm.jadwal_id,
                jm.mata_pelajaran,
                jm.jam_mulai,
                jm.jam_selesai,
                km.nama_kelas,
                g.guru_id,
                g.nama as nama_guru,
                ag.status,
                ag.waktu_absensi,
                ag.deadline_absensi,
                ag.notifikasi_terkirim,
                ag.keterangan,
                ag.is_otomatis
            FROM jadwal_madin jm
            JOIN kelas_madin km ON jm.kelas_madin_id = km.kelas_id
            JOIN guru g ON (jm.guru_id = g.guru_id OR km.guru_id = g.guru_id)
            LEFT JOIN absensi_guru ag ON (
                ag.guru_id = g.guru_id 
                AND ag.tanggal = ? 
                AND (ag.jadwal_madin_id = jm.jadwal_id OR ag.jadwal_madin_id IS NULL)
            )
            WHERE jm.hari = ?
            ORDER BY jm.jam_mulai, g.nama";

$stmt_madin = $conn->prepare($sql_madin);
$stmt_madin->bind_param("ss", $tanggal, $hari_indo);
$stmt_madin->execute();
$absensi_madin = $stmt_madin->get_result()->fetch_all(MYSQLI_ASSOC);

// Hitung deadline baru dan status kehadiran untuk Madin
foreach ($absensi_madin as &$absensi) {
    $deadline_baru = hitungDeadline($absensi['jam_selesai'], $tanggal);
    $absensi['deadline_absensi_baru'] = $deadline_baru;
    
    // Gunakan deadline baru jika tidak ada deadline di database, atau gunakan yang ada
    $deadline_aktif = $absensi['deadline_absensi'] ?: $deadline_baru;
    
    if ($absensi['status'] === 'Hadir' && $absensi['waktu_absensi']) {
        $keterlambatan = hitungKeterlambatan($absensi['waktu_absensi'], $deadline_aktif);
        if ($keterlambatan) {
            $absensi['status_kehadiran'] = 'Terlambat';
            $absensi['selisih_waktu'] = $keterlambatan;
        } else {
            $absensi['status_kehadiran'] = 'Tepat Waktu';
            $absensi['selisih_waktu'] = null;
        }
    } elseif ($absensi['status'] === 'Alpa') {
        $absensi['status_kehadiran'] = 'Alpa';
        $absensi['selisih_waktu'] = null;
    } else {
        $absensi['status_kehadiran'] = 'Belum Absen';
        $absensi['selisih_waktu'] = null;
    }
}
unset($absensi); // Hapus reference

// Query untuk mendapatkan semua jadwal Quran hari ini
$sql_quran = "SELECT 
                jq.id as jadwal_id,
                jq.mata_pelajaran,
                jq.jam_mulai,
                jq.jam_selesai,
                kq.nama_kelas,
                g.guru_id,
                g.nama as nama_guru,
                ag.status,
                ag.waktu_absensi,
                ag.deadline_absensi,
                ag.notifikasi_terkirim,
                ag.keterangan,
                ag.is_otomatis
            FROM jadwal_quran jq
            JOIN kelas_quran kq ON jq.kelas_quran_id = kq.id
            JOIN guru g ON (jq.guru_id = g.guru_id OR kq.guru_id = g.guru_id)
            LEFT JOIN absensi_guru ag ON (ag.jadwal_quran_id = jq.id AND ag.tanggal = ?)
            WHERE jq.hari = ?
            ORDER BY jq.jam_mulai, g.nama";

$stmt_quran = $conn->prepare($sql_quran);
$stmt_quran->bind_param("ss", $tanggal, $hari_indo);
$stmt_quran->execute();
$absensi_quran = $stmt_quran->get_result()->fetch_all(MYSQLI_ASSOC);

// Hitung deadline baru dan status kehadiran untuk Quran
foreach ($absensi_quran as &$absensi) {
    $deadline_baru = hitungDeadline($absensi['jam_selesai'], $tanggal);
    $absensi['deadline_absensi_baru'] = $deadline_baru;
    
    // Gunakan deadline baru jika tidak ada deadline di database, atau gunakan yang ada
    $deadline_aktif = $absensi['deadline_absensi'] ?: $deadline_baru;
    
    if ($absensi['status'] === 'Hadir' && $absensi['waktu_absensi']) {
        $keterlambatan = hitungKeterlambatan($absensi['waktu_absensi'], $deadline_aktif);
        if ($keterlambatan) {
            $absensi['status_kehadiran'] = 'Terlambat';
            $absensi['selisih_waktu'] = $keterlambatan;
        } else {
            $absensi['status_kehadiran'] = 'Tepat Waktu';
            $absensi['selisih_waktu'] = null;
        }
    } elseif ($absensi['status'] === 'Alpa') {
        $absensi['status_kehadiran'] = 'Alpa';
        $absensi['selisih_waktu'] = null;
    } else {
        $absensi['status_kehadiran'] = 'Belum Absen';
        $absensi['selisih_waktu'] = null;
    }
}
unset($absensi); // Hapus reference

// Query untuk mendapatkan semua jadwal Kegiatan hari ini
$sql_kegiatan = "SELECT 
                    jk.kegiatan_id,
                    jk.nama_kegiatan,
                    jk.jam_mulai,
                    jk.jam_selesai,
                    k.nama_kamar,
                    g.guru_id,
                    g.nama as nama_guru,
                    ag.status,
                    ag.waktu_absensi,
                    ag.deadline_absensi,
                    ag.notifikasi_terkirim,
                    ag.keterangan,
                    ag.is_otomatis
                FROM jadwal_kegiatan jk
                JOIN kamar k ON jk.kamar_id = k.kamar_id
                JOIN guru g ON (jk.guru_id = g.guru_id OR k.guru_id = g.guru_id)
                LEFT JOIN absensi_guru ag ON (ag.kegiatan_id = jk.kegiatan_id AND ag.tanggal = ?)
                WHERE jk.hari = ?
                ORDER BY jk.jam_mulai, g.nama";

$stmt_kegiatan = $conn->prepare($sql_kegiatan);
$stmt_kegiatan->bind_param("ss", $tanggal, $hari_indo);
$stmt_kegiatan->execute();
$absensi_kegiatan = $stmt_kegiatan->get_result()->fetch_all(MYSQLI_ASSOC);

// Hitung deadline baru dan status kehadiran untuk Kegiatan
foreach ($absensi_kegiatan as &$absensi) {
    $deadline_baru = hitungDeadline($absensi['jam_selesai'], $tanggal);
    $absensi['deadline_absensi_baru'] = $deadline_baru;
    
    // Gunakan deadline baru jika tidak ada deadline di database, atau gunakan yang ada
    $deadline_aktif = $absensi['deadline_absensi'] ?: $deadline_baru;
    
    if ($absensi['status'] === 'Hadir' && $absensi['waktu_absensi']) {
        $keterlambatan = hitungKeterlambatan($absensi['waktu_absensi'], $deadline_aktif);
        if ($keterlambatan) {
            $absensi['status_kehadiran'] = 'Terlambat';
            $absensi['selisih_waktu'] = $keterlambatan;
        } else {
            $absensi['status_kehadiran'] = 'Tepat Waktu';
            $absensi['selisih_waktu'] = null;
        }
    } elseif ($absensi['status'] === 'Alpa') {
        $absensi['status_kehadiran'] = 'Alpa';
        $absensi['selisih_waktu'] = null;
    } else {
        $absensi['status_kehadiran'] = 'Belum Absen';
        $absensi['selisih_waktu'] = null;
    }
}
unset($absensi); // Hapus reference

// Gabungkan semua data untuk statistik
$absensi_guru = array_merge($absensi_madin, $absensi_quran, $absensi_kegiatan);

require_once '../includes/navigation.php';
?>

<!DOCTYPE html>
<html lang="id" data-bs-theme="<?= $dark_mode ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitor Absensi Guru - Sistem Absensi Online</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .badge-tipe-madin { background-color: #0d6efd; }
        .badge-tipe-quran { background-color: #198754; }
        .badge-tipe-kegiatan { background-color: #6f42c1; }
        .table th { background-color: #f8f9fa; }
        .jadwal-header {
            border-left: 4px solid;
            padding-left: 10px;
        }
        .jadwal-header-madin { border-color: #0d6efd; }
        .jadwal-header-quran { border-color: #198754; }
        .jadwal-header-kegiatan { border-color: #6f42c1; }
        @media (max-width: 768px) {
            .table-responsive { font-size: 0.875rem; }
        }
        .nav-tabs .nav-link.active {
            font-weight: bold;
            border-bottom: 3px solid #0d6efd;
        }
        .stat-card {
            transition: transform 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-2px);
        }
        
        /* Tambahkan CSS ini untuk memperbaiki alignment vertikal tombol tab */
        .nav-tabs .nav-link {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 48px; /* Memberikan tinggi minimum yang konsisten */
        }
        
        /* Untuk desktop saja - optional jika ingin lebih spesifik */
        @media (min-width: 768px) {
            .nav-tabs .nav-link {
                padding-top: 12px;
                padding-bottom: 12px;
            }
        }
        
    </style>
</head>
<body>
    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2><i class="bi bi-clock-history me-1"></i> Monitor Absensi Guru</h2>
        </div>
        
        
        
        <!-- Statistik Ringkas - Responsive untuk Mobile -->
        <div class="row align-items-center text-center mb-3 g-2 g-md-3">
            <?php if ($tanggal): ?>
            <div class="card mb-0">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0">
                        Status Absensi Guru - <?= $tanggal ?> (<?= $hari_indo ?>)
                    </h5>
                </div>
            </div>
            <?php endif; ?>
            <div class="col-4 md-3">
                <div class="border rounded p-2 bg-success bg-opacity-10 h-100 stat-card">
                    <div class="card-body p-2">
                        <h6 class="card-title mb-1">Hadir</h6>
                        <h4 class="card-text mb-0">
                            <?= count(array_filter($absensi_guru, function($item) { 
                                return isset($item['status']) && $item['status'] == 'Hadir'; 
                            })) ?>
                        </h4>
                    </div>
                </div>
            </div>
            <div class="col-4 md-3">
                <div class="border rounded p-2 bg-danger bg-opacity-10 h-100 stat-card">
                    <div class="card-body p-2">
                        <h6 class="card-title mb-1">Alpa</h6>
                        <h4 class="card-text mb-0">
                            <?= count(array_filter($absensi_guru, function($item) { 
                                return isset($item['status']) && $item['status'] == 'Alpa'; 
                            })) ?>
                        </h4>
                    </div>
                </div>
            </div>
            <div class="col-4 md-3">
                <div class="border rounded p-2 bg-warning bg-opacity-10 h-100 stat-card">
                    <div class="card-body p-2">
                        <h6 class="card-title mb-1">Terlambat</h6>
                        <h4 class="card-text mb-0">
                            <?= count(array_filter($absensi_guru, function($item) { 
                                return isset($item['status_kehadiran']) && $item['status_kehadiran'] == 'Terlambat'; 
                            })) ?>
                        </h4>
                    </div>
                </div>
            </div>
            <div class="col-12 md-3">
                <div class="border rounded p-2 bg-info bg-opacity-10 h-100 stat-card">
                    <div class="card-body p-2">
                        <h6 class="card-title mb-1">Total Jadwal</h6>
                        <h4 class="card-text mb-0"><?= count($absensi_guru) ?></h4>
                    </div>
                </div>
            </div>
        </div>

        
        <div class="card mb-3">
            <div class="card-header bg-info text-white">
                <h5 class="card-title mb-0">Filter Tanggal</h5>
            </div>
            <div class="card-body">
                <form method="GET" class="row">
                    <div class="col-md-7 mb-2">
                        <input type="date" class="form-control" name="tanggal" value="<?= $tanggal ?>">
                    </div>
                    <div class="col-md-2 mb-2">
                        <button type="submit" class="btn btn-primary">Tampilkan</button>
                        <a href="monitor_absensi_guru.php" class="btn btn-secondary">Hari Ini</a>
                    </div>
                    <div class="col-md-3 mb-2">
                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <a href="absensi_guru.php" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-left me-1"></i> Kembali ke Absensi Guru
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Legenda -->
        <div class="card mb-4">
            <div class="card-header bg-light">
                <h6 class="card-title mb-0">Legenda:</h6>
            </div>
            <div class="card-body">
                <div class="d-flex flex-wrap gap-3">
                    <div class="d-flex align-items-center">
                        <span class="badge bg-success me-2">Hadir</span>
                        <small>Guru hadir tepat waktu</small>
                    </div>
                    <div class="d-flex align-items-center">
                        <span class="badge bg-warning me-2">Belum Absen</span>
                        <small>Guru belum melakukan absensi</small>
                    </div>
                    <div class="d-flex align-items-center">
                        <span class="badge bg-danger me-2">Alpa</span>
                        <small>Guru tidak hadir tanpa keterangan</small>
                    </div>
                    <div class="d-flex align-items-center">
                        <span class="badge bg-secondary me-2">Auto</span>
                        <small>Absensi otomatis oleh sistem</small>
                    </div>
                </div>
            </div>
        </div>
        
        <?php if ($tanggal): ?>
        <!-- Tab Panel Utama -->
        <div class="card">
            <div class="card-body">
                <ul class="nav nav-tabs mb-4" id="mainTab" role="tablist">
                    <li class="nav-item d-flex align-items-center" role="presentation">
                        <button class="nav-link active d-flex align-items-center justify-content-center" 
                                id="per-jadwal-tab" data-bs-toggle="tab" data-bs-target="#per-jadwal-pane" 
                                type="button" role="tab" aria-controls="per-jadwal-pane" aria-selected="false">
                            <i class="bi bi-calendar-check me-1"></i> Per Jadwal
                        </button>
                    </li>
                    <li class="nav-item d-flex align-items-center" role="presentation">
                        <button class="nav-link d-flex align-items-center justify-content-center" 
                                id="harian-tab" data-bs-toggle="tab" data-bs-target="#harian-pane" 
                                type="button" role="tab" aria-controls="harian-pane" aria-selected="true">
                            <i class="bi bi-list-check me-1"></i> Per Guru
                        </button>
                    </li>
                </ul>

                <div class="tab-content" id="mainTabContent">
                    
                    <!-- Tab Absensi Per Jadwal -->
                    <div class="tab-pane fade show active" id="per-jadwal-pane" role="tabpanel" 
                         aria-labelledby="per-jadwal-tab" tabindex="0">
                        
                        <!-- Sub Tab untuk Jenis Jadwal -->
                        <ul class="nav nav-tabs mb-4" id="jadwalTab" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="kegiatan-tab" data-bs-toggle="tab" 
                                        data-bs-target="#kegiatan-pane" type="button" role="tab" 
                                        aria-controls="kegiatan-pane" aria-selected="true">
                                    Kegiatan
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="quran-tab" data-bs-toggle="tab" 
                                        data-bs-target="#quran-pane" type="button" role="tab" 
                                        aria-controls="quran-pane" aria-selected="false">
                                    Qur'an
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="madin-tab" data-bs-toggle="tab" 
                                        data-bs-target="#madin-pane" type="button" role="tab" 
                                        aria-controls="madin-pane" aria-selected="false">
                                    Madin
                                </button>
                            </li>
                        </ul>

                        <div class="tab-content" id="jadwalTabContent">
                            <!-- Tab Jadwal Kegiatan -->
                            <div class="tab-pane fade show active" id="kegiatan-pane" role="tabpanel" 
                                 aria-labelledby="kegiatan-tab" tabindex="0">
                                
                                <?php if (count($absensi_kegiatan) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover">
                                        <thead>
                                            <tr>
                                                <th>Kegiatan Kamar</th>
                                                <th>Nama Guru</th>
                                                <th>Status</th>
                                                <th>Waktu Absensi</th>
                                                <th>Deadline</th>
                                                <th>Keterlambatan</th>
                                                <th>Notifikasi</th>
                                                <th>Keterangan</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($absensi_kegiatan as $absensi): ?>
                                            <tr>
                                                <td>
                                                    <div>
                                                        <strong><?= htmlspecialchars($absensi['nama_kegiatan']) ?></strong>
                                                        <div class="text-muted small">
                                                            <i class="bi bi-geo-alt me-1"></i><?= htmlspecialchars($absensi['nama_kamar']) ?>
                                                        </div>
                                                        <div class="text-muted small">
                                                            <i class="bi bi-clock me-1"></i><?= $absensi['jam_mulai'] ?> - <?= $absensi['jam_selesai'] ?>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td><?= htmlspecialchars($absensi['nama_guru']) ?></td>
                                                <td>
                                                    <span class="badge bg-<?= 
                                                        (isset($absensi['status']) && $absensi['status'] == 'Hadir') ? 'success' : 
                                                        ((isset($absensi['status']) && $absensi['status'] == 'Alpa') ? 'danger' : 'warning')
                                                    ?>">
                                                        <?= isset($absensi['status']) ? $absensi['status'] : 'Belum Absen' ?>
                                                    </span>
                                                    <?php if (isset($absensi['is_otomatis']) && $absensi['is_otomatis']): ?>
                                                        <span class="badge bg-secondary" title="Absensi Otomatis">Auto</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?= isset($absensi['waktu_absensi']) ? date('H:i:s', strtotime($absensi['waktu_absensi'])) : '-' ?></td>
                                                <td>
                                                    <?= isset($absensi['deadline_absensi_baru']) ? date('H:i:s', strtotime($absensi['deadline_absensi_baru'])) : '-' ?>
                                                    <?php if (isset($absensi['deadline_absensi']) && $absensi['deadline_absensi']): ?>
                                                        <br><small class="text-muted">Asli: <?= date('H:i:s', strtotime($absensi['deadline_absensi'])) ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if (isset($absensi['selisih_waktu']) && $absensi['selisih_waktu'] && isset($absensi['status_kehadiran']) && $absensi['status_kehadiran'] == 'Terlambat'): ?>
                                                        <span class="text-danger">
                                                            <i class="bi bi-clock-fill me-1"></i><?= $absensi['selisih_waktu'] ?>
                                                        </span>
                                                    <?php elseif (isset($absensi['status_kehadiran']) && $absensi['status_kehadiran'] == 'Tepat Waktu'): ?>
                                                        <span class="text-success">
                                                            <i class="bi bi-check-circle-fill me-1"></i>Tepat Waktu
                                                        </span>
                                                    <?php else: ?>
                                                        -
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if (isset($absensi['notifikasi_terkirim']) && $absensi['notifikasi_terkirim']): ?>
                                                        <span class="badge bg-success">
                                                            <i class="bi bi-check-lg me-1"></i>Terkirim
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">
                                                            <i class="bi bi-clock me-1"></i>Belum
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if (!empty($absensi['keterangan'])): ?>
                                                        <span title="<?= htmlspecialchars($absensi['keterangan']) ?>">
                                                            <?= strlen($absensi['keterangan']) > 30 ? 
                                                                substr(htmlspecialchars($absensi['keterangan']), 0, 30) . '...' : 
                                                                htmlspecialchars($absensi['keterangan']) ?>
                                                        </span>
                                                    <?php else: ?>
                                                        -
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php else: ?>
                                <div class="alert alert-info text-center">
                                    <i class="bi bi-info-circle me-2"></i>
                                    Tidak ada jadwal kegiatan untuk hari ini.
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Tab Jadwal Quran -->
                            <div class="tab-pane fade" id="quran-pane" role="tabpanel" 
                                 aria-labelledby="quran-tab" tabindex="0">
                                
                                <?php if (count($absensi_quran) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover">
                                        <thead>
                                            <tr>
                                                <th>Mata Pelajaran & Kelas</th>
                                                <th>Nama Guru</th>
                                                <th>Status</th>
                                                <th>Waktu Absensi</th>
                                                <th>Deadline</th>
                                                <th>Keterlambatan</th>
                                                <th>Notifikasi</th>
                                                <th>Keterangan</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($absensi_quran as $absensi): ?>
                                            <tr>
                                                <td>
                                                    <div>
                                                        <strong><?= htmlspecialchars($absensi['mata_pelajaran']) ?></strong>
                                                        <div class="text-muted small">
                                                            <i class="bi bi-mortarboard me-1"></i><?= htmlspecialchars($absensi['nama_kelas']) ?>
                                                        </div>
                                                        <div class="text-muted small">
                                                            <i class="bi bi-clock me-1"></i><?= $absensi['jam_mulai'] ?> - <?= $absensi['jam_selesai'] ?>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td><?= htmlspecialchars($absensi['nama_guru']) ?></td>
                                                <td>
                                                    <span class="badge bg-<?= 
                                                        (isset($absensi['status']) && $absensi['status'] == 'Hadir') ? 'success' : 
                                                        ((isset($absensi['status']) && $absensi['status'] == 'Alpa') ? 'danger' : 'warning')
                                                    ?>">
                                                        <?= isset($absensi['status']) ? $absensi['status'] : 'Belum Absen' ?>
                                                    </span>
                                                    <?php if (isset($absensi['is_otomatis']) && $absensi['is_otomatis']): ?>
                                                        <span class="badge bg-secondary" title="Absensi Otomatis">Auto</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?= isset($absensi['waktu_absensi']) ? date('H:i:s', strtotime($absensi['waktu_absensi'])) : '-' ?></td>
                                                <td>
                                                    <?= isset($absensi['deadline_absensi_baru']) ? date('H:i:s', strtotime($absensi['deadline_absensi_baru'])) : '-' ?>
                                                    <?php if (isset($absensi['deadline_absensi']) && $absensi['deadline_absensi']): ?>
                                                        <br><small class="text-muted">Asli: <?= date('H:i:s', strtotime($absensi['deadline_absensi'])) ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if (isset($absensi['selisih_waktu']) && $absensi['selisih_waktu'] && isset($absensi['status_kehadiran']) && $absensi['status_kehadiran'] == 'Terlambat'): ?>
                                                        <span class="text-danger">
                                                            <i class="bi bi-clock-fill me-1"></i><?= $absensi['selisih_waktu'] ?>
                                                        </span>
                                                    <?php elseif (isset($absensi['status_kehadiran']) && $absensi['status_kehadiran'] == 'Tepat Waktu'): ?>
                                                        <span class="text-success">
                                                            <i class="bi bi-check-circle-fill me-1"></i>Tepat Waktu
                                                        </span>
                                                    <?php else: ?>
                                                        -
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if (isset($absensi['notifikasi_terkirim']) && $absensi['notifikasi_terkirim']): ?>
                                                        <span class="badge bg-success">
                                                            <i class="bi bi-check-lg me-1"></i>Terkirim
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">
                                                            <i class="bi bi-clock me-1"></i>Belum
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if (!empty($absensi['keterangan'])): ?>
                                                        <span title="<?= htmlspecialchars($absensi['keterangan']) ?>">
                                                            <?= strlen($absensi['keterangan']) > 30 ? 
                                                                substr(htmlspecialchars($absensi['keterangan']), 0, 30) . '...' : 
                                                                htmlspecialchars($absensi['keterangan']) ?>
                                                        </span>
                                                    <?php else: ?>
                                                        -
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php else: ?>
                                <div class="alert alert-info text-center">
                                    <i class="bi bi-info-circle me-2"></i>
                                    Tidak ada jadwal Quran untuk hari ini.
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Tab Jadwal Madin -->
                            <div class="tab-pane fade" id="madin-pane" role="tabpanel" 
                                 aria-labelledby="madin-tab" tabindex="0">
                                
                                <?php if (count($absensi_madin) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover">
                                        <thead>
                                            <tr>
                                                <th>Mata Pelajaran & Kelas</th>
                                                <th>Nama Guru</th>
                                                <th>Status</th>
                                                <th>Waktu Absensi</th>
                                                <th>Deadline</th>
                                                <th>Keterlambatan</th>
                                                <th>Notifikasi</th>
                                                <th>Keterangan</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($absensi_madin as $absensi): ?>
                                            <tr>
                                                <td>
                                                    <div>
                                                        <strong><?= htmlspecialchars($absensi['mata_pelajaran']) ?></strong>
                                                        <div class="text-muted small">
                                                            <i class="bi bi-mortarboard me-1"></i><?= htmlspecialchars($absensi['nama_kelas']) ?>
                                                        </div>
                                                        <div class="text-muted small">
                                                            <i class="bi bi-clock me-1"></i><?= $absensi['jam_mulai'] ?> - <?= $absensi['jam_selesai'] ?>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td><?= htmlspecialchars($absensi['nama_guru']) ?></td>
                                                <td>
                                                    <span class="badge bg-<?= 
                                                        (isset($absensi['status']) && $absensi['status'] == 'Hadir') ? 'success' : 
                                                        ((isset($absensi['status']) && $absensi['status'] == 'Alpa') ? 'danger' : 'warning')
                                                    ?>">
                                                        <?= isset($absensi['status']) ? $absensi['status'] : 'Belum Absen' ?>
                                                    </span>
                                                    <?php if (isset($absensi['is_otomatis']) && $absensi['is_otomatis']): ?>
                                                        <span class="badge bg-secondary" title="Absensi Otomatis">Auto</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?= isset($absensi['waktu_absensi']) ? date('H:i:s', strtotime($absensi['waktu_absensi'])) : '-' ?></td>
                                                <td>
                                                    <?= isset($absensi['deadline_absensi_baru']) ? date('H:i:s', strtotime($absensi['deadline_absensi_baru'])) : '-' ?>
                                                    <?php if (isset($absensi['deadline_absensi']) && $absensi['deadline_absensi']): ?>
                                                        <br><small class="text-muted">Asli: <?= date('H:i:s', strtotime($absensi['deadline_absensi'])) ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if (isset($absensi['selisih_waktu']) && $absensi['selisih_waktu'] && isset($absensi['status_kehadiran']) && $absensi['status_kehadiran'] == 'Terlambat'): ?>
                                                        <span class="text-danger">
                                                            <i class="bi bi-clock-fill me-1"></i><?= $absensi['selisih_waktu'] ?>
                                                        </span>
                                                    <?php elseif (isset($absensi['status_kehadiran']) && $absensi['status_kehadiran'] == 'Tepat Waktu'): ?>
                                                        <span class="text-success">
                                                            <i class="bi bi-check-circle-fill me-1"></i>Tepat Waktu
                                                        </span>
                                                    <?php else: ?>
                                                        -
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if (isset($absensi['notifikasi_terkirim']) && $absensi['notifikasi_terkirim']): ?>
                                                        <span class="badge bg-success">
                                                            <i class="bi bi-check-lg me-1"></i>Terkirim
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">
                                                            <i class="bi bi-clock me-1"></i>Belum
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if (!empty($absensi['keterangan'])): ?>
                                                        <span title="<?= htmlspecialchars($absensi['keterangan']) ?>">
                                                            <?= strlen($absensi['keterangan']) > 30 ? 
                                                                substr(htmlspecialchars($absensi['keterangan']), 0, 30) . '...' : 
                                                                htmlspecialchars($absensi['keterangan']) ?>
                                                        </span>
                                                    <?php else: ?>
                                                        -
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php else: ?>
                                <div class="alert alert-info text-center">
                                    <i class="bi bi-info-circle me-2"></i>
                                    Tidak ada jadwal Madin untuk hari ini.
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Tab Absensi Harian -->
                    <div class="tab-pane fade" id="harian-pane" role="tabpanel" 
                         aria-labelledby="harian-tab" tabindex="0">
                        
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Nama Guru</th>
                                        <th>Status</th>
                                        <th>Waktu Absensi</th>
                                        <th>Deadline</th>
                                        <th>Keterlambatan</th>
                                        <th>Notifikasi</th>
                                        <th>Keterangan</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($absensi_guru_harian as $absensi): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($absensi['nama_guru']) ?></td>
                                        <td>
                                            <span class="badge bg-<?= 
                                                $absensi['status'] == 'Hadir' ? 'success' : 
                                                ($absensi['status'] == 'Alpa' ? 'danger' : 'warning')
                                            ?>">
                                                <?= $absensi['status'] ?>
                                            </span>
                                        </td>
                                        <td><?= $absensi['waktu_absensi'] ?: '-' ?></td>
                                        <td><?= $absensi['deadline_absensi'] ?: '-' ?></td>
                                        <td>
                                            <?php if ($absensi['selisih_waktu'] && $absensi['status_kehadiran'] == 'Terlambat'): ?>
                                                <span class="text-danger"><?= $absensi['selisih_waktu'] ?></span>
                                            <?php elseif ($absensi['status_kehadiran'] == 'Tepat Waktu'): ?>
                                                <span class="text-success">Tepat Waktu</span>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($absensi['notifikasi_terkirim']): ?>
                                                <span class="badge bg-success">Terkirim</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Belum</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($absensi['keterangan']) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <?php if (empty($absensi_guru_harian)): ?>
                        <div class="alert alert-info">Tidak ada data absensi guru untuk tanggal ini.</div>
                        <?php endif; ?>
                    </div>
                    
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>