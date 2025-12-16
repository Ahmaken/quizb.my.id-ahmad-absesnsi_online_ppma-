<?php
// TAMBAHKAN DI AWAL
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../includes/init.php';

if (!check_auth()) {
    header("Location: ../index.php");
    exit();
}

// GANTI bagian PROSES PENCARIAN MURID dengan kode ini:
if (isset($_GET['q']) && isset($_GET['page']) && isset($_GET['action']) && $_GET['action'] == 'search_murid') {
    header('Content-Type: application/json');
    
    $searchTerm = $_GET['q'];
    $page = intval($_GET['page']);
    $limit = 30;
    $offset = ($page - 1) * $limit;

    // Query untuk mencari murid berdasarkan nama atau kelas - PERBAIKI INI
    $sql = "SELECT m.murid_id, m.nama, k.nama_kelas
            FROM murid m 
            JOIN kelas_madin k ON m.kelas_madin_id = k.kelas_id 
            WHERE m.nama LIKE ? OR k.nama_kelas LIKE ?
            ORDER BY m.nama 
            LIMIT ? OFFSET ?";

    $stmt = $conn->prepare($sql);
    $searchPattern = '%' . $searchTerm . '%';
    $stmt->bind_param("ssii", $searchPattern, $searchPattern, $limit, $offset);
    $stmt->execute();
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

    // Query untuk total count - PERBAIKI INI (ganti kelas menjadi kelas_madin)
    $sql_count = "SELECT COUNT(*) as total 
                  FROM murid m 
                  JOIN kelas_madin k ON m.kelas_madin_id = k.kelas_id 
                  WHERE m.nama LIKE ? OR k.nama_kelas LIKE ?";
    $stmt_count = $conn->prepare($sql_count);
    $stmt_count->bind_param("ss", $searchPattern, $searchPattern);
    $stmt_count->execute();
    $result_count = $stmt_count->get_result();
    $total_count = $result_count->fetch_assoc()['total'];

    echo json_encode([
        'items' => $items,
        'total_count' => $total_count
    ]);
    exit();
}

// Ambil parameter filter
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');
$kelas_id = $_GET['kelas_id'] ?? null;
$murid_id = $_GET['murid_id'] ?? null;
$kelas_quran_id = $_GET['kelas_quran_id'] ?? null; // Tambahan untuk kelas Quran
$kegiatan_id = $_GET['kegiatan_id'] ?? null; // Tambahan untuk kegiatan kamar

// Jika ada murid_id yang dipilih, ambil data murid tersebut untuk ditampilkan di select2
$selected_murid = null;
if ($murid_id) {
    $sql_selected_murid = "SELECT m.*, k.nama_kelas FROM murid m JOIN kelas k ON m.kelas_id = k.kelas_id WHERE m.murid_id = ?";
    $stmt_selected = $conn->prepare($sql_selected_murid);
    $stmt_selected->bind_param("i", $murid_id);
    $stmt_selected->execute();
    $result_selected = $stmt_selected->get_result();
    if ($result_selected->num_rows > 0) {
        $selected_murid = $result_selected->fetch_assoc();
    }
}

// Query untuk rekap per siswa
$rekap_siswa = [];
if ($murid_id) {
    $sql = "SELECT a.*, m.nama, j.mata_pelajaran, k.nama_kelas 
            FROM absensi a
            JOIN murid m ON a.murid_id = m.murid_id
            JOIN jadwal_madin j ON a.jadwal_madin_id = j.jadwal_id
            JOIN kelas_madin k ON j.kelas_madin_id = k.kelas_id
            WHERE a.tanggal BETWEEN ? AND ?
            AND a.murid_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssi", $start_date, $end_date, $murid_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $rekap_siswa[] = $row;
    }
}

// Query untuk rekap per kelas madin
$rekap_kelas = [];
if ($kelas_id) {
    $sql = "SELECT m.nama, 
            COUNT(CASE WHEN a.status = 'Hadir' THEN 1 END) as hadir,
            COUNT(CASE WHEN a.status = 'Sakit' THEN 1 END) as sakit,
            COUNT(CASE WHEN a.status = 'Izin' THEN 1 END) as izin,
            COUNT(CASE WHEN a.status = 'Alpa' THEN 1 END) as alpa,
            COUNT(*) as total
            FROM absensi a
            JOIN murid m ON a.murid_id = m.murid_id
            JOIN jadwal_madin j ON a.jadwal_madin_id = j.jadwal_id
            WHERE a.tanggal BETWEEN ? AND ?
            AND j.kelas_madin_id = ?
            GROUP BY a.murid_id";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssi", $start_date, $end_date, $kelas_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $rekap_kelas[] = $row;
    }
}

// Hitung total per kelas madin
$total_kelas = [];
if ($kelas_id) {
    $sql = "SELECT 
                COUNT(CASE WHEN a.status = 'Hadir' THEN 1 END) as total_hadir,
                COUNT(CASE WHEN a.status = 'Sakit' THEN 1 END) as total_sakit,
                COUNT(CASE WHEN a.status = 'Izin' THEN 1 END) as total_izin,
                COUNT(CASE WHEN a.status = 'Alpa' THEN 1 END) as total_alpa,
                COUNT(*) as total_absensi
            FROM absensi a
            JOIN jadwal_madin j ON a.jadwal_madin_id = j.jadwal_id
            WHERE a.tanggal BETWEEN ? AND ?
            AND j.kelas_madin_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssi", $start_date, $end_date, $kelas_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $total_kelas = $result->fetch_assoc();
}

// TAMBAHAN: Query untuk rekap per kelas Quran
$rekap_kelas_quran = [];
if ($kelas_quran_id) {
    $sql = "SELECT m.nama, 
                COUNT(CASE WHEN a.status = 'Hadir' THEN 1 END) as hadir,
                COUNT(CASE WHEN a.status = 'Sakit' THEN 1 END) as sakit,
                COUNT(CASE WHEN a.status = 'Izin' THEN 1 END) as izin,
                COUNT(CASE WHEN a.status = 'Alpa' THEN 1 END) as alpa,
                COUNT(*) as total
            FROM absensi_quran a
            JOIN murid m ON a.murid_id = m.murid_id
            JOIN jadwal_quran j ON a.jadwal_quran_id = j.id
            WHERE a.tanggal BETWEEN ? AND ?
            AND j.kelas_quran_id = ?
            GROUP BY a.murid_id";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssi", $start_date, $end_date, $kelas_quran_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $rekap_kelas_quran[] = $row;
    }
}

// TAMBAHAN: Hitung total per kelas Quran
$total_kelas_quran = [];
if ($kelas_quran_id) {
    $sql = "SELECT 
                COUNT(CASE WHEN a.status = 'Hadir' THEN 1 END) as total_hadir,
                COUNT(CASE WHEN a.status = 'Sakit' THEN 1 END) as total_sakit,
                COUNT(CASE WHEN a.status = 'Izin' THEN 1 END) as total_izin,
                COUNT(CASE WHEN a.status = 'Alpa' THEN 1 END) as total_alpa,
                COUNT(*) as total_absensi
            FROM absensi_quran a
            JOIN jadwal_quran j ON a.jadwal_quran_id = j.id
            WHERE a.tanggal BETWEEN ? AND ?
            AND j.kelas_quran_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssi", $start_date, $end_date, $kelas_quran_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $total_kelas_quran = $result->fetch_assoc();
}

// TAMBAHAN: Query untuk rekap per kegiatan kamar
$rekap_kegiatan = [];
if ($kegiatan_id) {
    $sql = "SELECT m.nama, 
                COUNT(CASE WHEN a.status = 'Hadir' THEN 1 END) as hadir,
                COUNT(CASE WHEN a.status = 'Sakit' THEN 1 END) as sakit,
                COUNT(CASE WHEN a.status = 'Izin' THEN 1 END) as izin,
                COUNT(CASE WHEN a.status = 'Alpa' THEN 1 END) as alpa,
                COUNT(*) as total
            FROM absensi_kegiatan a
            JOIN murid m ON a.murid_id = m.murid_id
            JOIN jadwal_kegiatan j ON a.kegiatan_id = j.kegiatan_id
            WHERE a.tanggal BETWEEN ? AND ?
            AND j.kegiatan_id = ?
            GROUP BY a.murid_id";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssi", $start_date, $end_date, $kegiatan_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $rekap_kegiatan[] = $row;
    }
}

// TAMBAHAN: Hitung total per kegiatan
$total_kegiatan = [];
if ($kegiatan_id) {
    $sql = "SELECT 
                COUNT(CASE WHEN a.status = 'Hadir' THEN 1 END) as total_hadir,
                COUNT(CASE WHEN a.status = 'Sakit' THEN 1 END) as total_sakit,
                COUNT(CASE WHEN a.status = 'Izin' THEN 1 END) as total_izin,
                COUNT(CASE WHEN a.status = 'Alpa' THEN 1 END) as total_alpa,
                COUNT(*) as total_absensi
            FROM absensi_kegiatan a
            JOIN jadwal_kegiatan j ON a.kegiatan_id = j.kegiatan_id
            WHERE a.tanggal BETWEEN ? AND ?
            AND j.kegiatan_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssi", $start_date, $end_date, $kegiatan_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $total_kegiatan = $result->fetch_assoc();
}

// Ambil semua kelas untuk dropdown
$sql_kelas = "SELECT * FROM kelas_madin";
$result_kelas = $conn->query($sql_kelas);
$kelas_list = [];
if ($result_kelas->num_rows > 0) {
    while ($row = $result_kelas->fetch_assoc()) {
        $kelas_list[] = $row;
    }
}

// Ambil semua murid untuk dropdown
$sql_murid = "SELECT * FROM murid";
$result_murid = $conn->query($sql_murid);
$murid_list = [];
if ($result_murid->num_rows > 0) {
    while ($row = $result_murid->fetch_assoc()) {
        $murid_list[] = $row;
    }
}

// TAMBAHAN: Ambil semua kelas quran untuk dropdown
$sql_kelas_quran = "SELECT * FROM kelas_quran";
$result_kelas_quran = $conn->query($sql_kelas_quran);
$kelas_quran_list = [];
if ($result_kelas_quran->num_rows > 0) {
    while ($row = $result_kelas_quran->fetch_assoc()) {
        $kelas_quran_list[] = $row;
    }
}

// TAMBAHAN: Ambil semua kegiatan untuk dropdown
$sql_kegiatan = "SELECT jk.*, k.nama_kamar 
                FROM jadwal_kegiatan jk
                JOIN kamar k ON jk.kamar_id = k.kamar_id";
$result_kegiatan = $conn->query($sql_kegiatan);
$kegiatan_list = [];
if ($result_kegiatan->num_rows > 0) {
    while ($row = $result_kegiatan->fetch_assoc()) {
        $kegiatan_list[] = $row;
    }
}

// PERBAIKI QUERY REKAP PELANGGARAN
$rekap_pelanggaran = [];
$sql_pelanggaran = "SELECT p.*, m.nama, km.nama_kelas, p.jenis AS jenis_pelanggaran
                FROM pelanggaran p
                JOIN murid m ON p.murid_id = m.murid_id
                JOIN kelas_madin km ON m.kelas_madin_id = km.kelas_id
                WHERE p.tanggal BETWEEN ? AND ?";
                
// PERBAIKI LOGIKA BIND PARAMETER
$types = "ss";
$params = [$start_date, $end_date];

if ($murid_id) {
    $sql_pelanggaran .= " AND p.murid_id = ?";
    $types .= "i";
    $params[] = $murid_id;
}
if ($kelas_id) {
    $sql_pelanggaran .= " AND m.kelas_madin_id = ?";
    $types .= "i";
    $params[] = $kelas_id;
}

$stmt = $conn->prepare($sql_pelanggaran);
if ($stmt) {
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $rekap_pelanggaran[] = $row;
    }
}

// API PENCARIAN UNTUK FILTER DROPDOWN - tambahkan sebelum require_once navigation
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
        
        // Dalam bagian API PENCARIAN UNTUK FILTER DROPDOWN, tambahkan case 'guru':
        case 'guru':
            $sql = "SELECT guru_id, nama FROM guru";
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
                    'id' => $row['guru_id'],
                    'text' => $row['nama']
                ];
            }
            break;
    }

    echo json_encode(['items' => $items]);
    exit();
}

// Tambahkan setelah variabel filter lainnya
$guru_id_filter = $_GET['guru_id'] ?? null;

// ===== REKAPITULASI ABSENSI GURU =====
$rekap_guru = [];
$total_guru = [];

if (in_array($_SESSION['role'], ['admin', 'staff'])) {
    // Query untuk rekap per guru
    if ($guru_id_filter) {
        $sql = "SELECT 
                    ag.tanggal,
                    g.nama as nama_guru,
                    ag.status,
                    ag.keterangan
                FROM absensi_guru ag
                JOIN guru g ON ag.guru_id = g.guru_id
                WHERE ag.tanggal BETWEEN ? AND ?
                AND ag.guru_id = ?
                ORDER BY ag.tanggal DESC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssi", $start_date, $end_date, $guru_id_filter);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $rekap_guru[] = $row;
        }
    }

    // Query untuk rekap semua guru (summary)
    $sql = "SELECT 
                g.guru_id,
                g.nama as nama_guru,
                COUNT(CASE WHEN ag.status = 'Hadir' THEN 1 END) as hadir,
                COUNT(CASE WHEN ag.status = 'Sakit' THEN 1 END) as sakit,
                COUNT(CASE WHEN ag.status = 'Izin' THEN 1 END) as izin,
                COUNT(CASE WHEN ag.status = 'Alpa' THEN 1 END) as alpa,
                COUNT(ag.absensi_id) as total
            FROM guru g
            LEFT JOIN absensi_guru ag ON g.guru_id = ag.guru_id AND ag.tanggal BETWEEN ? AND ?
            GROUP BY g.guru_id
            ORDER BY g.nama";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    $rekap_all_guru = [];
    while ($row = $result->fetch_assoc()) {
        $rekap_all_guru[] = $row;
    }

    // Hitung total semua guru
    $sql_total = "SELECT 
                    COUNT(CASE WHEN ag.status = 'Hadir' THEN 1 END) as total_hadir,
                    COUNT(CASE WHEN ag.status = 'Sakit' THEN 1 END) as total_sakit,
                    COUNT(CASE WHEN ag.status = 'Izin' THEN 1 END) as total_izin,
                    COUNT(CASE WHEN ag.status = 'Alpa' THEN 1 END) as total_alpa,
                    COUNT(ag.absensi_id) as total_absensi
                FROM absensi_guru ag
                WHERE ag.tanggal BETWEEN ? AND ?";
    $stmt_total = $conn->prepare($sql_total);
    $stmt_total->bind_param("ss", $start_date, $end_date);
    $stmt_total->execute();
    $result_total = $stmt_total->get_result();
    $total_guru = $result_total->fetch_assoc();
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

// PROSES UPDATE ABSENSI GURU
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_absensi_guru']) && in_array($_SESSION['role'], ['admin', 'staff'])) {
    $guru_id = $_POST['guru_id'];
    $tanggal = $_POST['tanggal'];
    $status = $_POST['status'];
    $keterangan = $_POST['keterangan'] ?? '';

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
        $message = "success|Absensi guru berhasil diperbarui!";
    } else {
        $message = "danger|Error: " . $stmt->error;
    }
}

// ===== STATISTIK ABSENSI GURU =====
$stats_guru = [
    'total_guru' => 0,
    'hari_ini' => [
        'hadir_hari_ini' => 0,
        'sakit_hari_ini' => 0,
        'izin_hari_ini' => 0,
        'alpa_hari_ini' => 0,
        'total_hari_ini' => 0
    ],
    'bulan_ini' => [
        'hadir_bulan_ini' => 0,
        'sakit_bulan_ini' => 0,
        'izin_bulan_ini' => 0,
        'alpa_bulan_ini' => 0,
        'total_bulan_ini' => 0
    ]
];

if (in_array($_SESSION['role'], ['admin', 'staff'])) {
    // Hitung total guru
    $sql_total_guru = "SELECT COUNT(*) as total FROM guru";
    $result_total_guru = $conn->query($sql_total_guru);
    if ($result_total_guru) {
        $stats_guru['total_guru'] = $result_total_guru->fetch_assoc()['total'];
    }

    // Statistik hari ini
    $today = date('Y-m-d');
    $sql_hari_ini = "SELECT 
                        COUNT(CASE WHEN status = 'Hadir' THEN 1 END) as hadir,
                        COUNT(CASE WHEN status = 'Sakit' THEN 1 END) as sakit,
                        COUNT(CASE WHEN status = 'Izin' THEN 1 END) as izin,
                        COUNT(CASE WHEN status = 'Alpa' THEN 1 END) as alpa,
                        COUNT(*) as total
                    FROM absensi_guru 
                    WHERE tanggal = ?";
    
    $stmt_hari_ini = $conn->prepare($sql_hari_ini);
    if ($stmt_hari_ini) {
        $stmt_hari_ini->bind_param("s", $today);
        $stmt_hari_ini->execute();
        $result_hari_ini = $stmt_hari_ini->get_result();
        if ($result_hari_ini && $row = $result_hari_ini->fetch_assoc()) {
            $stats_guru['hari_ini'] = [
                'hadir_hari_ini' => $row['hadir'],
                'sakit_hari_ini' => $row['sakit'],
                'izin_hari_ini' => $row['izin'],
                'alpa_hari_ini' => $row['alpa'],
                'total_hari_ini' => $row['total']
            ];
        }
    }

    // Statistik bulan ini
    $first_day_month = date('Y-m-01');
    $last_day_month = date('Y-m-t');
    $sql_bulan_ini = "SELECT 
                        COUNT(CASE WHEN status = 'Hadir' THEN 1 END) as hadir,
                        COUNT(CASE WHEN status = 'Sakit' THEN 1 END) as sakit,
                        COUNT(CASE WHEN status = 'Izin' THEN 1 END) as izin,
                        COUNT(CASE WHEN status = 'Alpa' THEN 1 END) as alpa,
                        COUNT(*) as total
                    FROM absensi_guru 
                    WHERE tanggal BETWEEN ? AND ?";
    
    $stmt_bulan_ini = $conn->prepare($sql_bulan_ini);
    if ($stmt_bulan_ini) {
        $stmt_bulan_ini->bind_param("ss", $first_day_month, $last_day_month);
        $stmt_bulan_ini->execute();
        $result_bulan_ini = $stmt_bulan_ini->get_result();
        if ($result_bulan_ini && $row = $result_bulan_ini->fetch_assoc()) {
            $stats_guru['bulan_ini'] = [
                'hadir_bulan_ini' => $row['hadir'],
                'sakit_bulan_ini' => $row['sakit'],
                'izin_bulan_ini' => $row['izin'],
                'alpa_bulan_ini' => $row['alpa'],
                'total_bulan_ini' => $row['total']
            ];
        }
    }
}

require_once '../includes/navigation.php';
?>

<!DOCTYPE html>
<html lang="id" data-bs-theme="<?= $dark_mode ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rekapitulasi - Sistem Absensi Online</title>
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
            <h2><i class="bi bi-bar-chart-line me-2"></i> Rekapitulasi</h2>
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
                <button class="btn btn-success"> <!-- Tetap hijau -->
                    <i class="bi bi-printer me-1"></i> Cetak Laporan
                </button>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header bg-light">
                <h5 class="card-title mb-0"><i class="bi bi-funnel me-1"></i> Filter Rekapitulasi</h5>
            </div>
            <div class="card-body">
                <form method="GET" action="">
                    <div class="row">
                        <div class="col-md-6 mb-6">
                            <label class="form-label">Periode Mulai</label>
                            <input type="date" class="form-control" name="start_date" value="<?= $start_date ?>">
                        </div>
                        <div class="col-md-6 mb-6">
                            <label class="form-label">Periode Akhir</label>
                            <input type="date" class="form-control" name="end_date" value="<?= $end_date ?>">
                        </div>
                        
                        <!-- Filter Guru -->
                        <?php if (in_array($_SESSION['role'], ['admin', 'staff'])): ?>
                        <div class="col-md-12 mb-6">
                            <label class="form-label">Guru / Pembina</label>
                            <select class="form-select select2-guru" name="guru_id">
                                <option value="">-- Semua Guru --</option>
                                <?php foreach ($guru_list as $guru): ?>
                                <option value="<?= $guru['guru_id'] ?>" <?= $guru_id_filter == $guru['guru_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($guru['nama']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Filter Murid -->
                        <div class="col-md-12 mb-6">
                            <label class="form-label">Murid</label>
                            <!-- Ganti dropdown dengan Select2 -->
                            <select class="form-select select2-murid" name="murid_id">
                                <option value="">-- Semua Murid --</option>
                                <?php if ($selected_murid): ?>
                                <option value="<?= $selected_murid['murid_id'] ?>" selected>
                                    <?= htmlspecialchars($selected_murid['nama']) ?> (<?= htmlspecialchars($selected_murid['nama_kelas']) ?>)
                                </option>
                                <?php endif; ?>
                            </select>
                        </div>
                        
                        <!-- TAMBAHAN: Filter Kelas Quran -->
                        <div class="col-md-4 mb-4">
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
                        
                        <!-- TAMBAHAN: Filter Kelas Madin -->
                        <div class="col-md-4 mb-4">
                            <label class="form-label">Kelas Madin</label>
                            <select class="form-select select2-kelas-madin" name="kelas_id">
                                <option value="">-- Semua Kelas --</option>
                                <?php foreach ($kelas_list as $kelas): ?>
                                <option value="<?= $kelas['kelas_id'] ?>" <?= $kelas_id == $kelas['kelas_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($kelas['nama_kelas']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- TAMBAHAN: Filter Kegiatan Kamar -->
                        <div class="col-md-4 mb-4">
                            <label class="form-label">Kegiatan Kamar</label>
                            <select class="form-select select2-kegiatan" name="kegiatan_id">
                                <option value="">-- Semua Kegiatan --</option>
                                <?php foreach ($kegiatan_list as $kegiatan): ?>
                                <option value="<?= $kegiatan['kegiatan_id'] ?>" <?= $kegiatan_id == $kegiatan['kegiatan_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($kegiatan['nama_kegiatan']) ?> (<?= htmlspecialchars($kegiatan['nama_kamar']) ?>)
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
                <ul class="nav nav-tabs mb-4" id="rekapTab" role="tablist">
                    <!--  Tab Siswa -->
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="per-siswa-tab" data-bs-toggle="tab" data-bs-target="#per-siswa" type="button">Murid</button>
                    </li>
                    
                    <!-- Tab Guru -->
                    <?php if (in_array($_SESSION['role'], ['admin', 'staff'])): ?>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="per-guru-tab" data-bs-toggle="tab" data-bs-target="#per-guru" type="button">Guru / Pembina</button>
                    </li>
                    <?php endif; ?>
                    
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="per-kelas-tab" data-bs-toggle="tab" data-bs-target="#per-kelas" type="button">Madin</button>
                    </li>
                    <!-- Tab Kelas Quran -->
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="per-kelas-quran-tab" data-bs-toggle="tab" data-bs-target="#per-kelas-quran" type="button">Qur'an</button>
                    </li>
                    <!-- Tab Kegiatan Kamar -->
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="per-kegiatan-tab" data-bs-toggle="tab" data-bs-target="#per-kegiatan" type="button">Kegiatan</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="pelanggaran-tab" data-bs-toggle="tab" data-bs-target="#pelanggaran" type="button">Pelanggaran</button>
                    </li>
                    
                </ul>
                
                <div class="tab-content" id="rekapTabContent">
                    <!-- Tab Rekapitulasi Siswa -->
                    <div class="tab-pane fade show active" id="per-siswa" role="tabpanel">
                        <h5 class="card-title mb-4">Rekap Per Murid</h5>
                        <?php if ($rekap_siswa): ?>
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead class="table-primary">
                                    <tr>
                                        <th>Tanggal</th>
                                        <th>Mata Pelajaran</th>
                                        <th>Kelas</th>
                                        <th>Status</th>
                                        <th>Keterangan</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($rekap_siswa as $absensi): ?>
                                    <tr>
                                        <td><?= $absensi['tanggal'] ?></td>
                                        <td><?= htmlspecialchars($absensi['mata_pelajaran']) ?></td>
                                        <td><?= htmlspecialchars($absensi['nama_kelas']) ?></td>
                                        <td><?= $absensi['status'] ?></td>
                                        <td><?= htmlspecialchars($absensi['keterangan']) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <div class="alert alert-info">Tidak ada data absensi untuk Murid ini.</div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Tab Per Kelas Madin -->
                    <div class="tab-pane fade" id="per-kelas" role="tabpanel">
                        <h5 class="card-title mb-4">Rekap Per Kelas Madin</h5>
                        <?php if ($rekap_kelas): ?>
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead class="table-primary">
                                    <tr>
                                        <th>Nama Siswa</th>
                                        <th>Hadir</th>
                                        <th>Sakit</th>
                                        <th>Izin</th>
                                        <th>Alpa</th>
                                        <th>Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($rekap_kelas as $rekap): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($rekap['nama']) ?></td>
                                        <td><?= $rekap['hadir'] ?></td>
                                        <td><?= $rekap['sakit'] ?></td>
                                        <td><?= $rekap['izin'] ?></td>
                                        <td><?= $rekap['alpa'] ?></td>
                                        <td><?= $rekap['total'] ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if ($total_kelas): ?>
                                    <tr class="table-info fw-bold">
                                        <td>Total Kelas</td>
                                        <td><?= $total_kelas['total_hadir'] ?></td>
                                        <td><?= $total_kelas['total_sakit'] ?></td>
                                        <td><?= $total_kelas['total_izin'] ?></td>
                                        <td><?= $total_kelas['total_alpa'] ?></td>
                                        <td><?= $total_kelas['total_absensi'] ?></td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <div class="alert alert-info">Tidak ada data absensi untuk kelas Madin ini.</div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Tab Per Kelas Quran -->
                    <div class="tab-pane fade" id="per-kelas-quran" role="tabpanel">
                        <h5 class="card-title mb-4">Rekap Per Kelas Qur'an</h5>
                        <?php if ($rekap_kelas_quran): ?>
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead class="table-primary">
                                    <tr>
                                        <th>Nama Siswa</th>
                                        <th>Hadir</th>
                                        <th>Sakit</th>
                                        <th>Izin</th>
                                        <th>Alpa</th>
                                        <th>Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($rekap_kelas_quran as $rekap): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($rekap['nama']) ?></td>
                                        <td><?= $rekap['hadir'] ?></td>
                                        <td><?= $rekap['sakit'] ?></td>
                                        <td><?= $rekap['izin'] ?></td>
                                        <td><?= $rekap['alpa'] ?></td>
                                        <td><?= $rekap['total'] ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if ($total_kelas_quran): ?>
                                    <tr class="table-info fw-bold">
                                        <td>Total Kelas</td>
                                        <td><?= $total_kelas_quran['total_hadir'] ?></td>
                                        <td><?= $total_kelas_quran['total_sakit'] ?></td>
                                        <td><?= $total_kelas_quran['total_izin'] ?></td>
                                        <td><?= $total_kelas_quran['total_alpa'] ?></td>
                                        <td><?= $total_kelas_quran['total_absensi'] ?></td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <div class="alert alert-info">Tidak ada data absensi untuk kelas Qur'an ini.</div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Tab Per Kegiatan Kamar -->
                    <div class="tab-pane fade" id="per-kegiatan" role="tabpanel">
                        <h5 class="card-title mb-4">Rekap Per Kegiatan Kamar</h5>
                        <?php if ($rekap_kegiatan): ?>
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead class="table-primary">
                                    <tr>
                                        <th>Nama Siswa</th>
                                        <th>Hadir</th>
                                        <th>Sakit</th>
                                        <th>Izin</th>
                                        <th>Alpa</th>
                                        <th>Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($rekap_kegiatan as $rekap): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($rekap['nama']) ?></td>
                                        <td><?= $rekap['hadir'] ?></td>
                                        <td><?= $rekap['sakit'] ?></td>
                                        <td><?= $rekap['izin'] ?></td>
                                        <td><?= $rekap['alpa'] ?></td>
                                        <td><?= $rekap['total'] ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if ($total_kegiatan): ?>
                                    <tr class="table-info fw-bold">
                                        <td>Total Kegiatan</td>
                                        <td><?= $total_kegiatan['total_hadir'] ?></td>
                                        <td><?= $total_kegiatan['total_sakit'] ?></td>
                                        <td><?= $total_kegiatan['total_izin'] ?></td>
                                        <td><?= $total_kegiatan['total_alpa'] ?></td>
                                        <td><?= $total_kegiatan['total_absensi'] ?></td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <div class="alert alert-info">Tidak ada data absensi untuk kegiatan kamar ini.</div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Konten Tab Pelanggaran -->
                    <div class="tab-pane fade" id="pelanggaran" role="tabpanel">
                        <h5 class="card-title mb-4">Rekap Pelanggaran</h5>
                        <?php if ($rekap_pelanggaran): ?>
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead class="table-primary">
                                    <tr>
                                        <th>Tanggal</th>
                                        <th>Nama Siswa</th>
                                        <th>Kelas</th>
                                        <th>Jenis Pelanggaran</th>
                                        <th>Deskripsi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($rekap_pelanggaran as $pel): ?>
                                    <tr>
                                        <td><?= $pel['tanggal'] ?></td>
                                        <td><?= htmlspecialchars($pel['nama']) ?></td>
                                        <td><?= htmlspecialchars($pel['nama_kelas']) ?></td>
                                        <td><?= htmlspecialchars($pel['jenis_pelanggaran'] ?? $pel['jenis'] ?? '') ?></td>
                                        <td><?= htmlspecialchars($pel['deskripsi']) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <div class="alert alert-info">Tidak ada data pelanggaran.</div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Tab Rekapitulasi Guru -->
                    <?php if (in_array($_SESSION['role'], ['admin', 'staff'])): ?>
                    <div class="tab-pane fade" id="per-guru" role="tabpanel">
                        <div class="justify-content-between align-items-center col-md-12 mb-6">
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <h5 class="card-title mb-0">Rekap Absensi Guru / Pembina</h5>
                            </div>
                            
                            
                        </div>
                    
                        <?php if ($guru_id_filter && $rekap_guru): ?>
                        <!-- Detail Absensi Guru Terpilih -->
                        <div class="card mb-4">
                            <div class="card-header bg-warning">
                                <h6 class="card-title mb-0">Detail Absensi</h6>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-bordered">
                                        <thead class="table-warning">
                                            <tr>
                                                <th>Tanggal</th>
                                                <th>Nama Guru</th>
                                                <th>Status</th>
                                                <th>Keterangan</th>
                                                <th width="100">Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($rekap_guru as $absensi): ?>
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
                                                    <button class="btn btn-sm btn-outline-primary edit-absensi-guru"
                                                            data-guru-id="<?= $guru_id_filter ?>"
                                                            data-tanggal="<?= $absensi['tanggal'] ?>"
                                                            data-status="<?= $absensi['status'] ?>"
                                                            data-keterangan="<?= htmlspecialchars($absensi['keterangan']) ?>">
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    
                        <!-- CARD STATISTIK ABSENSI GURU -->
                        <div class="row mb-4">
    <div class="col-12">
        <div class="card bg-warning bg-opacity-10 border-warning">
            <div class="card-body">
                <h6 class="card-title text-warning mb-3">
                    <i class="bi bi-graph-up me-2"></i>Statistik Absensi Guru
                </h6>
                
                <!-- Container untuk statistik yang akan bertumpuk di mobile -->
                <div class="row">
                    <!-- Statistik Hari Ini -->
                    <div class="col-12 col-lg-6 mb-4 mb-lg-0">
                        <h6 class="text-muted mb-3">Hari Ini (<?= date('d/m/Y') ?>)</h6>
                        <div class="row text-center g-2">
                            <div class="col-6 col-md-3">
                                <div class="border rounded p-2 bg-success bg-opacity-10 h-100">
                                    <div class="h5 mb-1 text-success"><?= $stats_guru['hari_ini']['hadir_hari_ini'] ?></div>
                                    <small class="text-muted">Hadir</small>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="border rounded p-2 bg-warning bg-opacity-10 h-100">
                                    <div class="h5 mb-1 text-warning"><?= $stats_guru['hari_ini']['sakit_hari_ini'] ?></div>
                                    <small class="text-muted">Sakit</small>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="border rounded p-2 bg-info bg-opacity-10 h-100">
                                    <div class="h5 mb-1 text-info"><?= $stats_guru['hari_ini']['izin_hari_ini'] ?></div>
                                    <small class="text-muted">Izin</small>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="border rounded p-2 bg-danger bg-opacity-10 h-100">
                                    <div class="h5 mb-1 text-danger"><?= $stats_guru['hari_ini']['alpa_hari_ini'] ?></div>
                                    <small class="text-muted">Alpa</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Statistik Bulan Ini -->
                    <div class="col-12 col-lg-6">
                        <h6 class="text-muted mb-3">Bulan Ini (<?= date('F Y') ?>)</h6>
                        <div class="row text-center g-2">
                            <div class="col-6 col-md-3">
                                <div class="border rounded p-2 bg-success bg-opacity-10 h-100">
                                    <div class="h5 mb-1 text-success"><?= $stats_guru['bulan_ini']['hadir_bulan_ini'] ?></div>
                                    <small class="text-muted">Hadir</small>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="border rounded p-2 bg-warning bg-opacity-10 h-100">
                                    <div class="h5 mb-1 text-warning"><?= $stats_guru['bulan_ini']['sakit_bulan_ini'] ?></div>
                                    <small class="text-muted">Sakit</small>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="border rounded p-2 bg-info bg-opacity-10 h-100">
                                    <div class="h5 mb-1 text-info"><?= $stats_guru['bulan_ini']['izin_bulan_ini'] ?></div>
                                    <small class="text-muted">Izin</small>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="border rounded p-2 bg-danger bg-opacity-10 h-100">
                                    <div class="h5 mb-1 text-danger"><?= $stats_guru['bulan_ini']['alpa_bulan_ini'] ?></div>
                                    <small class="text-muted">Alpa</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Summary - juga bertumpuk di mobile -->
                <div class="row mt-4">
                    <div class="col-12 col-md-4 mb-2 mb-md-0">
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">Total Guru:</span>
                            <strong><?= $stats_guru['total_guru'] ?> Guru</strong>
                        </div>
                    </div>
                    <div class="col-12 col-md-4 mb-2 mb-md-0">
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">Kehadiran Hari Ini:</span>
                            <strong>
                                <?= $stats_guru['hari_ini']['total_hari_ini'] > 0 ? 
                                    round(($stats_guru['hari_ini']['hadir_hari_ini'] / $stats_guru['hari_ini']['total_hari_ini']) * 100, 1) : 0 ?>%
                            </strong>
                        </div>
                    </div>
                    <div class="col-12 col-md-4">
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">Kehadiran Bulan Ini:</span>
                            <strong>
                                <?= $stats_guru['bulan_ini']['total_bulan_ini'] > 0 ? 
                                    round(($stats_guru['bulan_ini']['hadir_bulan_ini'] / $stats_guru['bulan_ini']['total_bulan_ini']) * 100, 1) : 0 ?>%
                            </strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

                        
                        <!-- Summary Semua Guru -->
                        <div class="card">
                            <div class="card-header bg-warning">
                                <h6 class="card-title mb-0">Ikhtisar Semua Guru</h6>
                            </div>
                            <div class="card-body">
                                <?php if ($rekap_all_guru): ?>
                                <div class="table-responsive">
                                    <table class="table table-bordered">
                                        <thead class="table-warning">
                                            <tr>
                                                <th>Nama Guru</th>
                                                <th>Hadir</th>
                                                <th>Sakit</th>
                                                <th>Izin</th>
                                                <th>Alpa</th>
                                                <th>Total</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($rekap_all_guru as $guru): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($guru['nama_guru']) ?></td>
                                                <td><?= $guru['hadir'] ?></td>
                                                <td><?= $guru['sakit'] ?></td>
                                                <td><?= $guru['izin'] ?></td>
                                                <td><?= $guru['alpa'] ?></td>
                                                <td><?= $guru['total'] ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                            <?php if ($total_guru): ?>
                                            <tr class="table-info fw-bold">
                                                <td>Total Semua Guru</td>
                                                <td><?= $total_guru['total_hadir'] ?></td>
                                                <td><?= $total_guru['total_sakit'] ?></td>
                                                <td><?= $total_guru['total_izin'] ?></td>
                                                <td><?= $total_guru['total_alpa'] ?></td>
                                                <td><?= $total_guru['total_absensi'] ?></td>
                                            </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php else: ?>
                                <div class="alert alert-info">Tidak ada data absensi guru.</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    
                        <?php if (in_array($_SESSION['role'], ['admin', 'staff'])): ?>
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
                                            <input type="hidden" name="guru_id" id="modalGuruId" value="<?= $guru_id_filter ?>">
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Tanggal</label>
                                                <input type="date" class="form-control" name="tanggal" id="modalTanggal" 
                                                       value="<?= date('Y-m-d') ?>" required>
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
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Tambahkan JavaScript Select2 -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <!-- Script untuk mengontrol tampilan loading -->
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
        
        // PERBAIKAN: Fungsi inisialisasi Select2 yang lebih robust
        function initSelect2Filter(selector, type, placeholder) {
            try {
                if ($(selector).length === 0) {
                    console.warn('Elemen ' + selector + ' tidak ditemukan');
                    return;
                }
                
                $(selector).select2({
                    theme: 'bootstrap-5',
                    placeholder: placeholder,
                    allowClear: true,
                    width: '100%',
                    dropdownParent: $(selector).parent(),
                    ajax: {
                        url: 'rekapitulasi.php',
                        dataType: 'json',
                        delay: 300,
                        data: function (params) {
                            return {
                                q: params.term || '',
                                type: type,
                                page: params.page || 1
                            };
                        },
                        processResults: function (data, params) {
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
        
        // Inisialisasi semua Select2 ketika dokumen ready
        $(document).ready(function() {
            // Inisialisasi Select2 untuk murid dengan Ajax
            $('.select2-murid').select2({
                theme: 'bootstrap-5',
                placeholder: "Cari nama murid...",
                allowClear: true,
                width: '100%',
                dropdownParent: $('.select2-murid').parent(),
                ajax: {
                    url: 'rekapitulasi.php',
                    dataType: 'json',
                    delay: 250,
                    data: function (params) {
                        return {
                            q: params.term,
                            page: params.page || 1,
                            action: 'search_murid'
                        };
                    },
                    processResults: function (data, params) {
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
                minimumInputLength: 1,
                templateResult: formatMurid,
                templateSelection: formatMuridSelection
            });
            
            function formatMurid(murid) {
                if (murid.loading) {
                    return murid.text;
                }
                
                var $container = $(
                    "<div class='select2-result-murid clearfix'>" +
                        "<div class='select2-result-murid__nama fw-bold'>" + (murid.nama || murid.text) + "</div>" +
                        "<div class='select2-result-murid__kelas text-muted small'>Kelas: " + (murid.nama_kelas || '') + "</div>" +
                    "</div>"
                );
                
                return $container;
            }
            
            function formatMuridSelection(murid) {
                if (!murid.id) {
                    return murid.text;
                }
                
                return murid.nama ? murid.nama + ' (' + murid.nama_kelas + ')' : murid.text;
            }
            
            // Inisialisasi filter Select2 lainnya
            initSelect2Filter('.select2-kelas-quran', 'kelas_quran', 'Ketik untuk Cari Kelas Qur\'an...');
            initSelect2Filter('.select2-kelas-madin', 'kelas_madin', 'Ketik untuk Cari Kelas Madin...');
            initSelect2Filter('.select2-kegiatan', 'kegiatan', 'Ketik untuk Cari Kegiatan...');
            initSelect2Filter('.select2-guru', 'guru', 'Ketik untuk Cari Guru...');
            
            // Handle edit absensi guru
            const editButtons = document.querySelectorAll('.edit-absensi-guru');
            const modal = new bootstrap.Modal(document.getElementById('tambahAbsensiGuruModal'));
            const modalTitle = document.getElementById('absensiGuruModalTitle');
            const modalGuruId = document.getElementById('modalGuruId');
            const modalTanggal = document.getElementById('modalTanggal');
            const modalStatus = document.getElementById('modalStatus');
            const modalKeterangan = document.getElementById('modalKeterangan');
        
            editButtons.forEach(button => {
                button.addEventListener('click', function() {
                    modalTitle.textContent = 'Edit Absensi Guru';
                    modalGuruId.value = this.dataset.guruId;
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
                    modalTanggal.value = '<?= date('Y-m-d') ?>';
                    modalStatus.value = 'Hadir';
                    modalKeterangan.value = '';
                }
            });
        });
    </script>
</body>
</html>