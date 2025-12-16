<?php
// Aktifkan output buffering untuk menghindari header issues
ob_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);

// pages/dashboard.php
require_once '../includes/init.php';

// === PERBAIKAN: CEK AUTH DULU SEBELUM MENGAKSES SESSION ===
if (!check_auth()) {
    echo '<script>window.location.href = "../index.php";</script>';
    exit();
}

// === PERBAIKAN: SET DEFAULT VALUES SETELAH CHECK_AUTH ===
// Default values untuk avoid notice - SETELAH check_auth
$_SESSION['role'] = $_SESSION['role'] ?? 'guest';
$_SESSION['guru_id'] = $_SESSION['guru_id'] ?? null;
$_SESSION['user_id'] = $_SESSION['user_id'] ?? null;
$_SESSION['username'] = $_SESSION['username'] ?? 'User';

// Filter data untuk role guru
$guru_id = null;
if ($_SESSION['role'] === 'guru' && isset($_SESSION['guru_id'])) {
    $guru_id = $_SESSION['guru_id'];
}

// PERBAIKAN: HAPUS DUPLIKASI INISIALISASI $guru_id
// ===== FILTER DATA UNTUK ROLE GURU =====
if ($_SESSION['role'] === 'guru' && !$guru_id) {
    $sql_guru = "SELECT guru_id FROM guru WHERE user_id = ?";
    $stmt_guru = $conn->prepare($sql_guru);
    $stmt_guru->bind_param("i", $_SESSION['user_id']);
    $stmt_guru->execute();
    $result_guru = $stmt_guru->get_result();
    
    if ($result_guru->num_rows > 0) {
        $guru_data = $result_guru->fetch_assoc();
        $guru_id = $guru_data['guru_id'];
        $_SESSION['guru_id'] = $guru_id; // Simpan di session
        error_log("Guru ID ditemukan: " . $guru_id);
    } else {
        error_log("Guru ID tidak ditemukan untuk user_id: " . $_SESSION['user_id']);
    }
    $stmt_guru->close();
}

// ===== PERBAIKAN: DEFINSIKAN VARIABEL $today DI AWAL =====
$today = date('Y-m-d');

// ===== INISIALISASI VARIABEL DI AWAL =====
$jadwal_hari_ini = [];
$jadwal_quran_hari_ini = [];
$jadwal_kegiatan_hari_ini = [];
$notifikasi_jadwal_belum_isi = []; 
$pelanggaran_terbaru = [];
$perizinan_terbaru = [];

// Include fungsi hijriyah
require_once '../includes/hijri_functions.php';

// Ambil tanggal Hijriyah dengan error handling
try {
    $tanggal_hijriyah = get_hijri_date_kemenag($today);
    
    $_SESSION['hijri_date_nav'] = $tanggal_hijriyah;
    $_SESSION['hijri_date_cache'] = [
        'date' => $today,
        'hijri_date' => $tanggal_hijriyah
    ];
    
} catch (Exception $e) {
    error_log("Error getting hijri date: " . $e->getMessage());
    $tanggal_hijriyah = date('d M Y') . ' H';
}

$day_name = date('l');
$day_map = [
    'Monday' => 'Senin',
    'Tuesday' => 'Selasa',
    'Wednesday' => 'Rabu',
    'Thursday' => 'Kamis',
    'Friday' => 'Jumat',
    'Saturday' => 'Sabtu',
    'Sunday' => 'Ahad'
];
$hari_ini = $day_map[$day_name] ?? $day_name;

// Fungsi get_jadwal_hari_ini dengan filter guru
function get_jadwal_hari_ini($conn, $hari, $table, $join_table, $id_field, $name_field, $guru_id = null) {
    $jadwal = [];
    $sql = "SELECT j.*, k.$name_field 
            FROM $table j 
            JOIN $join_table k ON j.{$id_field} = k.{$id_field}
            WHERE j.hari = ?";
    
    if ($guru_id) {
        if ($table == 'jadwal_madin') {
            $sql .= " AND (j.guru_id = ? OR k.guru_id = ?)";
        } elseif ($table == 'jadwal_quran') {
            $sql .= " AND (j.guru_id = ? OR k.guru_id = ?)";
        } elseif ($table == 'jadwal_kegiatan') {
            $sql .= " AND (j.guru_id = ? OR k.guru_id = ?)";
        }
    }

    $sql .= " ORDER BY j.jam_mulai ASC";
    
    try {
        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            throw new Exception("Error preparing statement: " . $conn->error);
        }

        if ($guru_id) {
            $stmt->bind_param("sii", $hari, $guru_id, $guru_id);
        } else {
            $stmt->bind_param("s", $hari);
        }

        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }

        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $jadwal[] = $row;
            }
        }
        $stmt->close();
    } catch (Exception $e) {
        error_log($e->getMessage());
    }
    
    return $jadwal;
}

// Fungsi untuk mengambil statistik absensi dengan filter guru
function get_attendance_stats($conn, $table, $date, $guru_id = null) {
    $stats = ['Hadir' => 0, 'Sakit' => 0, 'Izin' => 0, 'Alpa' => 0];
    $total = 0;
    
    $sql = "SELECT a.status, COUNT(*) as total 
            FROM $table a";
    
    if ($guru_id && $table == 'absensi') {
        $sql .= " JOIN jadwal_madin jm ON a.jadwal_madin_id = jm.jadwal_id
                  JOIN kelas_madin km ON jm.kelas_madin_id = km.kelas_id
                  WHERE a.tanggal = ? AND (jm.guru_id = ? OR km.guru_id = ?)";
    } elseif ($guru_id && $table == 'absensi_quran') {
        $sql .= " JOIN jadwal_quran jq ON a.jadwal_quran_id = jq.id
                  JOIN kelas_quran kq ON jq.kelas_quran_id = kq.id
                  WHERE a.tanggal = ? AND (jq.guru_id = ? OR kq.guru_id = ?)";
    } elseif ($guru_id && $table == 'absensi_kegiatan') {
        $sql .= " JOIN jadwal_kegiatan jk ON a.kegiatan_id = jk.kegiatan_id
                  JOIN kamar k ON jk.kamar_id = k.kamar_id
                  WHERE a.tanggal = ? AND (jk.guru_id = ? OR k.guru_id = ?)";
    } else {
        $sql .= " WHERE a.tanggal = ?";
    }
    
    $sql .= " GROUP BY a.status";
    
    try {
        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            throw new Exception("Error preparing stats statement: " . $conn->error);
        }

        if ($guru_id) {
            $stmt->bind_param("sii", $date, $guru_id, $guru_id);
        } else {
            $stmt->bind_param("s", $date);
        }

        if ($stmt->execute()) {
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    $stats[$row['status']] = $row['total'];
                    $total += $row['total'];
                }
            }
        }
        $stmt->close();
    } catch (Exception $e) {
        error_log($e->getMessage());
    }
    
    return ['stats' => $stats, 'total' => $total];
}

// ===== DEBUG: CEK DATA TERFILTER =====
if ($_SESSION['role'] === 'guru') {
    error_log("=== DEBUG GURU FILTER ===");
    error_log("Guru ID: " . $guru_id);
    error_log("Jadwal Madin: " . count($jadwal_hari_ini));
    error_log("Jadwal Quran: " . count($jadwal_quran_hari_ini)); 
    error_log("Jadwal Kegiatan: " . count($jadwal_kegiatan_hari_ini));
    error_log("Pelanggaran: " . count($pelanggaran_terbaru));
    error_log("Perizinan: " . count($perizinan_terbaru));
    error_log("==========================");
}

// Ambil jadwal madin hari ini dengan filter guru
$sql_madin = "SELECT jm.*, km.nama_kelas 
              FROM jadwal_madin jm 
              JOIN kelas_madin km ON jm.kelas_madin_id = km.kelas_id
              WHERE jm.hari = ?";
              
if ($guru_id) {
    $sql_madin .= " AND (jm.guru_id = ? OR km.guru_id = ?)";
}
$sql_madin .= " ORDER BY jm.jam_mulai ASC";

$stmt_madin = $conn->prepare($sql_madin);
if ($stmt_madin) {
    if ($guru_id) {
        $stmt_madin->bind_param("sii", $hari_ini, $guru_id, $guru_id);
    } else {
        $stmt_madin->bind_param("s", $hari_ini);
    }
    $stmt_madin->execute();
    $result_madin = $stmt_madin->get_result();
    while ($row = $result_madin->fetch_assoc()) {
        $jadwal_hari_ini[] = $row;
    }
    $stmt_madin->close();
} else {
    error_log("Error preparing jadwal madin statement: " . $conn->error);
}

// Jadwal Quran dengan filter guru
$sql_quran = "SELECT jq.*, kq.nama_kelas 
              FROM jadwal_quran jq 
              JOIN kelas_quran kq ON jq.kelas_quran_id = kq.id
              WHERE jq.hari = ?";
              
if ($guru_id) {
    $sql_quran .= " AND (jq.guru_id = ? OR kq.guru_id = ?)";
}
$sql_quran .= " ORDER BY jq.jam_mulai ASC";

$stmt_quran = $conn->prepare($sql_quran);
if ($stmt_quran) {
    if ($guru_id) {
        $stmt_quran->bind_param("sii", $hari_ini, $guru_id, $guru_id);
    } else {
        $stmt_quran->bind_param("s", $hari_ini);
    }
    $stmt_quran->execute();
    $result_quran = $stmt_quran->get_result();
    while ($row = $result_quran->fetch_assoc()) {
        $jadwal_quran_hari_ini[] = $row;
    }
    $stmt_quran->close();
} else {
    error_log("Error preparing jadwal quran statement: " . $conn->error);
}

$jadwal_kegiatan_hari_ini = get_jadwal_hari_ini($conn, $hari_ini, 'jadwal_kegiatan', 'kamar', 'kamar_id', 'nama_kamar', $guru_id);

// Ambil statistik absensi dengan filter guru
$stats_madin = get_attendance_stats($conn, 'absensi', $today, $guru_id);
$stats_quran_data = get_attendance_stats($conn, 'absensi_quran', $today, $guru_id);
$stats_kegiatan_data = get_attendance_stats($conn, 'absensi_kegiatan', $today, $guru_id);

// Hitung persentase
function calculate_percentages($stats, $total) {
    if ($total > 0) {
        return [
            'Hadir' => ($stats['Hadir'] / $total) * 100,
            'Sakit' => ($stats['Sakit'] / $total) * 100,
            'Izin' => ($stats['Izin'] / $total) * 100,
            'Alpa' => ($stats['Alpa'] / $total) * 100
        ];
    }
    return ['Hadir' => 0, 'Sakit' => 0, 'Izin' => 0, 'Alpa' => 0];
}

$percentages_madin = calculate_percentages($stats_madin['stats'], $stats_madin['total']);
$percentages_quran = calculate_percentages($stats_quran_data['stats'], $stats_quran_data['total']);
$percentages_kegiatan = calculate_percentages($stats_kegiatan_data['stats'], $stats_kegiatan_data['total']);

// ===== AMBIL STATISTIK ABSENSI GURU =====
$stats_guru = [
    'hari_ini' => [
        'hadir_hari_ini' => 0,
        'sakit_hari_ini' => 0,
        'izin_hari_ini' => 0,
        'alpa_hari_ini' => 0,
        'total_hari_ini' => 0
    ],
    'total_guru' => 0
];

if (in_array($_SESSION['role'], ['admin', 'staff'])) {
    // Hitung total guru
    $sql_total_guru = "SELECT COUNT(*) as total FROM guru WHERE status = 'aktif'";
    $result_total_guru = $conn->query($sql_total_guru);
    if ($result_total_guru && $result_total_guru->num_rows > 0) {
        $row_total = $result_total_guru->fetch_assoc();
        $stats_guru['total_guru'] = $row_total['total'];
    }

    // Hitung statistik absensi guru hari ini
    $sql_absensi_guru = "SELECT 
        COUNT(CASE WHEN status = 'Hadir' THEN 1 END) as hadir_hari_ini,
        COUNT(CASE WHEN status = 'Sakit' THEN 1 END) as sakit_hari_ini,
        COUNT(CASE WHEN status = 'Izin' THEN 1 END) as izin_hari_ini,
        COUNT(CASE WHEN status = 'Alpa' THEN 1 END) as alpa_hari_ini,
        COUNT(*) as total_hari_ini
        FROM absensi_guru 
        WHERE tanggal = ?";
    
    $stmt_absensi_guru = $conn->prepare($sql_absensi_guru);
    if ($stmt_absensi_guru) {
        $stmt_absensi_guru->bind_param("s", $today);
        $stmt_absensi_guru->execute();
        $result_absensi_guru = $stmt_absensi_guru->get_result();
        
        if ($result_absensi_guru && $result_absensi_guru->num_rows > 0) {
            $row_absensi = $result_absensi_guru->fetch_assoc();
            $stats_guru['hari_ini'] = [
                'hadir_hari_ini' => $row_absensi['hadir_hari_ini'] ?? 0,
                'sakit_hari_ini' => $row_absensi['sakit_hari_ini'] ?? 0,
                'izin_hari_ini' => $row_absensi['izin_hari_ini'] ?? 0,
                'alpa_hari_ini' => $row_absensi['alpa_hari_ini'] ?? 0,
                'total_hari_ini' => $row_absensi['total_hari_ini'] ?? 0
            ];
        }
        $stmt_absensi_guru->close();
    }
}

// Ambil data pelanggaran terbaru dengan filter guru
$pelanggaran_terbaru = [];
$sql_pelanggaran = "SELECT p.*, m.nama, km.nama_kelas 
                    FROM pelanggaran p
                    JOIN murid m ON p.murid_id = m.murid_id
                    LEFT JOIN kelas_madin km ON m.kelas_madin_id = km.kelas_id
                    WHERE 1=1";
                    
if ($guru_id) {
    $sql_pelanggaran .= " AND (km.guru_id = ? OR m.kelas_quran_id IN (
                            SELECT id FROM kelas_quran WHERE guru_id = ?
                         ) OR m.kamar_id IN (
                            SELECT kamar_id FROM kamar WHERE guru_id = ?
                         ))";
}
$sql_pelanggaran .= " ORDER BY p.tanggal DESC LIMIT 5";

$stmt_pelanggaran = $conn->prepare($sql_pelanggaran);
if ($stmt_pelanggaran) {
    if ($guru_id) {
        $stmt_pelanggaran->bind_param("iii", $guru_id, $guru_id, $guru_id);
    }
    $stmt_pelanggaran->execute();
    $result_pelanggaran = $stmt_pelanggaran->get_result();
    if ($result_pelanggaran && $result_pelanggaran->num_rows > 0) {
        while ($row = $result_pelanggaran->fetch_assoc()) {
            $pelanggaran_terbaru[] = $row;
        }
    }
    $stmt_pelanggaran->close();
} else {
    error_log("Error preparing pelanggaran statement: " . $conn->error);
}

// Ambil data perizinan terbaru dengan filter guru
$perizinan_terbaru = [];
$sql_perizinan = "SELECT p.*, m.nama, km.nama_kelas 
                  FROM perizinan p
                  JOIN murid m ON p.murid_id = m.murid_id
                  LEFT JOIN kelas_madin km ON m.kelas_madin_id = km.kelas_id
                  WHERE 1=1";
                  
if ($guru_id) {
    $sql_perizinan .= " AND (km.guru_id = ? OR m.kelas_quran_id IN (
                            SELECT id FROM kelas_quran WHERE guru_id = ?
                         ) OR m.kamar_id IN (
                            SELECT kamar_id FROM kamar WHERE guru_id = ?
                         ))";
}
$sql_perizinan .= " ORDER BY p.tanggal DESC LIMIT 5";

$stmt_perizinan = $conn->prepare($sql_perizinan);
if ($stmt_perizinan) {
    if ($guru_id) {
        $stmt_perizinan->bind_param("iii", $guru_id, $guru_id, $guru_id);
    }
    $stmt_perizinan->execute();
    $result_perizinan = $stmt_perizinan->get_result();
    if ($result_perizinan && $result_perizinan->num_rows > 0) {
        while ($row = $result_perizinan->fetch_assoc()) {
            $perizinan_terbaru[] = $row;
        }
    }
    $stmt_perizinan->close();
} else {
    error_log("Error preparing perizinan statement: " . $conn->error);
}

// Di bagian setelah session_start() di dashboard.php, tambahkan:
if (isset($_GET['clear_hijri_cache'])) {
    unset($_SESSION['hijri_date_cache']);
    unset($_SESSION['hijri_date_cache_nav']);
    echo '<script>window.location.href = "dashboard.php";</script>';
    exit();
}

// ===== FUNGSI UNTUK MENGAMBIL PENGATURAN NOTIFIKASI =====
function get_pengaturan_notifikasi($conn, $nama_pengaturan) {
    $sql = "SELECT nilai FROM pengaturan_notifikasi WHERE nama_pengaturan = ?";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("s", $nama_pengaturan);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            return $row['nilai'];
        }
    }
    return null;
}

// Ambil pengaturan notifikasi
$notifikasi_aktif = get_pengaturan_notifikasi($conn, 'notifikasi_aktif') ?? '1';
$waktu_tampil_jam = intval(get_pengaturan_notifikasi($conn, 'waktu_tampil_notifikasi') ?? '1');
$batas_waktu_jam = intval(get_pengaturan_notifikasi($conn, 'batas_waktu_notifikasi') ?? '24');
$refresh_otomatis_menit = intval(get_pengaturan_notifikasi($conn, 'refresh_otomatis') ?? '5');

// Konversi ke detik
$waktu_tampil_detik = $waktu_tampil_jam * 3600;
$batas_waktu_detik = $batas_waktu_jam * 3600;

// ===== FUNGSI UNTUK NOTIFIKASI =====
function cek_jadwal_sudah_diisi($conn, $tanggal, $jenis_jadwal, $id_jadwal) {
    $table_map = [
        'madin' => 'absensi',
        'quran' => 'absensi_quran',
        'kegiatan' => 'absensi_kegiatan'
    ];
    
    $id_field_map = [
        'madin' => 'jadwal_madin_id',
        'quran' => 'jadwal_quran_id',
        'kegiatan' => 'kegiatan_id'
    ];
    
    $table = $table_map[$jenis_jadwal] ?? '';
    $id_field = $id_field_map[$jenis_jadwal] ?? '';
    
    if (!$table || !$id_field) return false;
    
    $sql = "SELECT COUNT(*) as total FROM $table 
            WHERE tanggal = ? AND $id_field = ?";
    $stmt = $conn->prepare($sql);
    
    if ($stmt) {
        $stmt->bind_param("si", $tanggal, $id_jadwal);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        
        return $row['total'] > 0;
    }
    
    return false;
}

// PERBAIKAN: Tambahkan parameter $today ke fungsi
function perlu_tampilkan_notifikasi($jam_mulai, $waktu_tampil_detik, $batas_waktu_detik, $today) {
    $sekarang = time();
    $waktu_jadwal = strtotime($today . ' ' . $jam_mulai);
    $waktu_tampil = $waktu_jadwal + $waktu_tampil_detik;
    $waktu_batas = $waktu_jadwal + $batas_waktu_detik;
    
    return ($sekarang >= $waktu_tampil && $sekarang <= $waktu_batas);
}

// ===== FUNGSI FILTER JADWAL BERDASARKAN WAKTU =====
function filter_jadwal_berdasarkan_waktu($jadwal_array, $today, $waktu_tenggang_jam = 2) {
    $sekarang = time();
    $jadwal_terfilter = [];
    
    foreach ($jadwal_array as $jadwal) {
        $waktu_jadwal = strtotime($today . ' ' . $jadwal['jam_mulai']);
        
        // Hitung batas waktu (30 menit sebelum jadwal)
        $batas_awal = $waktu_jadwal - (30 * 60); // 30 menit sebelum
        
        // Hitung batas akhir (waktu tenggang setelah jadwal dimulai)
        $batas_akhir = $waktu_jadwal + ($waktu_tenggang_jam * 3600);
        
        // Tampilkan hanya jika:
        // - Waktu sekarang >= 30 menit sebelum jadwal
        // - Waktu sekarang <= waktu tenggang setelah jadwal dimulai
        if ($sekarang >= $batas_awal && $sekarang <= $batas_akhir) {
            $jadwal_terfilter[] = $jadwal;
        }
    }
    
    return $jadwal_terfilter;
}

// ===== AMBIL PENGATURAN WAKTU TENGGANG =====
$sql_waktu_tenggang = "SELECT nilai FROM pengaturan_absensi_otomatis WHERE nama_pengaturan = 'waktu_tenggang_absensi'";
$result_waktu_tenggang = $conn->query($sql_waktu_tenggang);
$waktu_tenggang_jam = 2; // default 2 jam

if ($result_waktu_tenggang && $result_waktu_tenggang->num_rows > 0) {
    $row = $result_waktu_tenggang->fetch_assoc();
    $waktu_tenggang_jam = intval($row['nilai']);
}

// ===== FILTER JADWAL BERDASARKAN WAKTU =====
$jadwal_hari_ini = filter_jadwal_berdasarkan_waktu($jadwal_hari_ini, $today, $waktu_tenggang_jam);
$jadwal_quran_hari_ini = filter_jadwal_berdasarkan_waktu($jadwal_quran_hari_ini, $today, $waktu_tenggang_jam);
$jadwal_kegiatan_hari_ini = filter_jadwal_berdasarkan_waktu($jadwal_kegiatan_hari_ini, $today, $waktu_tenggang_jam);

// ===== CEK JADWAL YANG BELUM DIISI =====
// Cek jadwal Madin yang belum diisi
foreach ($jadwal_hari_ini as $jadwal) {
    if (!cek_jadwal_sudah_diisi($conn, $today, 'madin', $jadwal['jadwal_id'])) {
        if ($notifikasi_aktif == '1' && perlu_tampilkan_notifikasi($jadwal['jam_mulai'], $waktu_tampil_detik, $batas_waktu_detik, $today)) {
            $notifikasi_jadwal_belum_isi[] = [
                'jenis' => 'Madin',
                'mata_pelajaran' => $jadwal['mata_pelajaran'],
                'kelas' => $jadwal['nama_kelas'],
                'waktu' => $jadwal['jam_mulai'] . ' - ' . $jadwal['jam_selesai'],
                'jam_mulai' => $jadwal['jam_mulai'],
                'link' => "absensi.php?filter&tanggal={$today}&jadwal_id={$jadwal['jadwal_id']}",
                'waktu_tampil' => date('H:i', strtotime($today . ' ' . $jadwal['jam_mulai']) + $waktu_tampil_detik),
                'waktu_batas' => date('H:i', strtotime($today . ' ' . $jadwal['jam_mulai']) + $batas_waktu_detik)
            ];
        }
    }
}

// Cek jadwal Quran yang belum diisi
foreach ($jadwal_quran_hari_ini as $jadwal) {
    if (!cek_jadwal_sudah_diisi($conn, $today, 'quran', $jadwal['id'])) {
        if ($notifikasi_aktif == '1' && perlu_tampilkan_notifikasi($jadwal['jam_mulai'], $waktu_tampil_detik, $batas_waktu_detik, $today)) {
            $notifikasi_jadwal_belum_isi[] = [
                'jenis' => 'Quran',
                'mata_pelajaran' => $jadwal['mata_pelajaran'],
                'kelas' => $jadwal['nama_kelas'],
                'waktu' => $jadwal['jam_mulai'] . ' - ' . $jadwal['jam_selesai'],
                'jam_mulai' => $jadwal['jam_mulai'],
                'link' => "absensi.php?filter_quran&tanggal_quran={$today}&jadwal_quran_id={$jadwal['id']}",
                'waktu_tampil' => date('H:i', strtotime($today . ' ' . $jadwal['jam_mulai']) + $waktu_tampil_detik),
                'waktu_batas' => date('H:i', strtotime($today . ' ' . $jadwal['jam_mulai']) + $batas_waktu_detik)
            ];
        }
    }
}

// Cek jadwal Kegiatan yang belum diisi
foreach ($jadwal_kegiatan_hari_ini as $jadwal) {
    if (!cek_jadwal_sudah_diisi($conn, $today, 'kegiatan', $jadwal['kegiatan_id'])) {
        if ($notifikasi_aktif == '1' && perlu_tampilkan_notifikasi($jadwal['jam_mulai'], $waktu_tampil_detik, $batas_waktu_detik, $today)) {
            $notifikasi_jadwal_belum_isi[] = [
                'jenis' => 'Kegiatan',
                'mata_pelajaran' => $jadwal['nama_kegiatan'],
                'kelas' => $jadwal['nama_kamar'],
                'waktu' => $jadwal['jam_mulai'] . ' - ' . $jadwal['jam_selesai'],
                'jam_mulai' => $jadwal['jam_mulai'],
                'link' => "absensi.php?filter&tanggal_kegiatan={$today}&kegiatan_id={$jadwal['kegiatan_id']}",
                'waktu_tampil' => date('H:i', strtotime($today . ' ' . $jadwal['jam_mulai']) + $waktu_tampil_detik),
                'waktu_batas' => date('H:i', strtotime($today . ' ' . $jadwal['jam_mulai']) + $batas_waktu_detik)
            ];
        }
    }
}

require_once '../includes/navigation.php';
$dark_mode = $_SESSION['dark_mode'] ?? 0;
?>

<!DOCTYPE html>
<html lang="id" data-bs-theme="<?= $dark_mode ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Sistem Absensi Online</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Naskh+Arabic:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <script>
    // Deteksi unload listeners
    document.addEventListener('DOMContentLoaded', function() {
        // Cek jika ada unload listeners di window
        const events = getEventListeners(window);
        if (events.unload && events.unload.length > 0) {
            console.warn('Unload listeners ditemukan:', events.unload);
            events.unload.forEach((listener, index) => {
                console.warn(`Unload listener ${index}:`, {
                    listener: listener.listener,
                    source: listener.source,
                    type: listener.type
                });
            });
        }
    });
    </script>
    <style>
    /* ===== ANIMASI TEKS ===== */
    .arabic-font {
        font-family: 'Noto Naskh Arabic', serif !important;
        font-size: clamp(1.4rem, 4vw, 1.8rem) !important; /* Responsive font size */
        font-weight: 600 !important;
        line-height: 1.8 !important;
        text-align: center !important;
        margin: 0 auto !important;
        display: block !important;
        width: 100% !important;
    }
    
    /* Container khusus untuk teks Arab */
    .arabic-container {
        display: flex !important;
        justify-content: center !important;
        align-items: center !important;
        width: 100% !important;
        margin: 0 auto 2rem auto !important;
        padding: 0 10px !important;
        min-height: 80px !important;
    }
    
    .arabic-text .word, .welcome-heading span {
        display: inline-block;
        opacity: 0;
        animation: fadeIn 0.8s forwards;
    }
    
    .arabic-text .space {
        display: inline-block !important;
        width: 0.3em !important;
        text-align: center !important;
    }
    
    .welcome-heading span {
        transform: translateY(30px);
    }
    
    /* Perbaikan untuk kata-kata Arab individual */
    .arabic-text .word {
        display: inline-block !important;
        margin: 0 1px !important;
        text-align: center !important;
        vertical-align: middle !important;
        line-height: 1.6 !important;
        transform: translateY(-30px);
    }
    
    .welcome-message span {
        display: inline-block;
        opacity: 0;
        filter: blur(8px);
        animation: fadeInBlur 1s forwards;
    }
    
    @keyframes fadeIn {
        to { opacity: 1; transform: translateY(0); }
    }
    
    @keyframes fadeInBlur {
        to { opacity: 1; filter: blur(0); }
    }
    
    .arabic-text .word { animation-delay: calc(0.2s * var(--word-index)); }
    .welcome-heading span { animation-delay: calc(0.1s * var(--i)); }
    .welcome-message span { animation-delay: calc(0.03s * var(--i)); }
    
    .space { display: inline-block; width: 0.5em; }
    
    .welcome-dashboard-message {
        min-height: 24px;
        margin: 15px 0;
    }
    
    .welcome-dashboard-message span {
        display: inline-block;
        opacity: 0;
        filter: blur(5px);
        animation: fadeInBlur 2.5s forwards;
    }
    
    .welcome-dashboard-message .space { width: 0.3em; }
    
    /* ===== LAYOUT UTAMA ===== */
    .main-content {
        display: flex;
        gap: 20px;
        position: relative;
    }
    
    .jadwal-container {
        flex: 1;
    }
    
    .quick-access-container {
        width: 300px;
        position: sticky;
        top: 80px;
        height: fit-content;
        align-self: flex-start;
        z-index: 1000;
    }
    
    .chart-container {
        height: 200px;
        position: relative;
        margin-bottom: 20px;
    }
    
    /* ===== PERBAIKAN TAMPILAN TANGGAL ===== */
    .hijri-date-container {
        min-height: 60px;
        display: flex;
        flex-direction: column;
        justify-content: center;
        width: 100%;
        margin: 0 auto;
        align-items: center;
        animation: fadeInDown 1s ease-out 0.5s both;
    }
    
    .hijri-date-official {
        margin-bottom: 8px;
    }
    
    .hijri-date-container .badge {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border: none;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        transition: all 0.3s ease;
    }
    
    .hijri-date-container .badge:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
    }
    
    @keyframes fadeInDown {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    /* ===== STYLE BADGE TANGGAL RESPONSIF ===== */
    .custom-date-badge {
        white-space: normal;
        word-wrap: break-word;
        max-width: 95%;
        margin: 0 auto;
        display: inline-flex;
        flex-wrap: wrap;
        justify-content: center;
        align-items: center;
        line-height: 1.3;
        font-size: 0.875rem;
        padding: 0.5rem 1rem !important;
    }
    
    /* ===== DARK MODE SUPPORT ===== */
    [data-bs-theme="dark"] .arabic-font,
    [data-bs-theme="dark"] .welcome-heading span,
    [data-bs-theme="dark"] .welcome-dashboard-message span {
        color: #e0e0e0 !important;
    }
    
    [data-bs-theme="dark"] .progress {
        background-color: #333 !important;
    }
    
    [data-bs-theme="dark"] .hijri-date-container .badge {
        background: linear-gradient(135deg, #4a5568 0%, #2d3748 100%) !important;
        color: #e2e8f0 !important;
    }
    
    [data-bs-theme="dark"] .hijri-date-container .text-muted {
        color: #a0aec0 !important;
    }
    
    /* ===== PERBAIKAN STYLE NOTIFIKASI ===== */
    .notification-minimized {
        position: fixed;
        top: 100px;
        right: 20px;
        z-index: 1060;
        background: rgba(255, 193, 7, 0.95);
        border-radius: 50%;
        width: 60px;
        height: 60px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.3s ease;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
        border: 3px solid #ffc107;
    }
    
    .notification-minimized:hover {
        transform: scale(1.1);
        background: rgba(255, 193, 7, 1);
        box-shadow: 0 6px 20px rgba(0, 0, 0, 0.4);
    }
    
    .notification-bell {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 100%;
        height: 100%;
        border-radius: 50%;
    }
    
    /* ===== PERBAIKAN ANIMASI NOTIFIKASI ===== */
    .notification-panel-card {
        position: fixed;
        top: 100px;
        right: 20px;
        z-index: 9999;
        width: 450px;
        max-width: 90vw;
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
        border: 1px solid #ffc107;
        transition: all 0.3s ease-in-out;
        transform: translateX(0);
        opacity: 1;
    }
    
    .notification-panel-card.mobile {
        right: 10px;
        left: 10px;
        width: auto;
        max-width: calc(100vw - 20px);
    }
    
    /* Pastikan notifikasi selalu di atas elemen lain */
    .notification-minimized,
    .notification-panel-card {
        z-index: 9999 !important;
    }
    
    /* PERBAIKAN: Sembunyikan notifikasi jika tidak ada notifikasi */
    .notification-minimized.hidden,
    .notification-panel-card.hidden {
        display: none !important;
    }
    
    /* Pastikan panel notifikasi hidden by default dengan d-none */
    .notification-panel-card.d-none {
        display: none !important;
        opacity: 0;
        transform: translateX(100%);
    }
    
    /* Pastikan notification minimized visible ketika ada notifikasi */
    .notification-minimized:not(.d-none) {
        display: flex !important;
        opacity: 1 !important;
        visibility: visible !important;
    }
    
    /* Z-index yang lebih tinggi untuk memastikan di atas elemen lain */
    .notification-minimized {
        z-index: 9998 !important;
    }
    
    .notification-panel-card {
        z-index: 9999 !important;
    }
    
    /* Style untuk header notifikasi */
    .toast-header-warning {
        background: linear-gradient(45deg, #ffc107, #ffb300);
        color: #000;
        border-bottom: 1px solid #ffb300;
    }
    
    [data-bs-theme="dark"] .toast-header-warning {
        background: linear-gradient(45deg, #665800, #856d00);
        color: #fff;
        border-bottom: 1px solid #856d00;
    }
    
    .toast-body-scroll {
        max-height: 400px;
        overflow-y: auto;
    }
    
    .notification-item {
        border-left: 4px solid #ffc107;
        padding-left: 12px;
        margin-bottom: 12px;
        background: rgba(255, 193, 7, 0.05);
        padding: 10px;
        border-radius: 4px;
    }
    
    [data-bs-theme="dark"] .notification-item {
        border-left-color: #856d00;
        background: rgba(133, 109, 0, 0.1);
    }
    
    [data-bs-theme="dark"] .notification-item {
        border-left-color: #856d00;
    }
    
    /* Pastikan notifikasi minimized selalu terlihat jika ada notifikasi */
    .notification-minimized:not(.d-none) {
        display: flex !important;
        opacity: 1 !important;
        visibility: visible !important;
    }
    
    .notification-panel-card:not(.d-none) {
        display: block !important;
        opacity: 1 !important;
        visibility: visible !important;
    }
    
    /* Animasi untuk notifikasi */
    @keyframes slideInRight {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    @keyframes pulse {
        0% { transform: scale(1); }
        50% { transform: scale(1.1); }
        100% { transform: scale(1); }
    }
    
    .notification-bell .badge {
        animation: pulse 2s infinite;
        font-size: 0.7rem;
        min-width: 20px;
        height: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .notification-panel-card:not(.d-none) {
        display: block !important;
        animation: slideInRight 0.3s ease-out;
    }
    
    /* Responsive design untuk notifikasi */
    @media (max-width: 768px) {
        .notification-minimized {
            top: 80px;
            right: 15px;
            width: 50px;
            height: 50px;
        }
        
        .notification-panel-card {
            top: 80px;
            right: 15px;
            width: calc(100vw - 30px);
        }
        
        .notification-bell i {
            font-size: 1.3rem !important;
        }
    }
    
    @media (max-width: 576px) {
        .notification-minimized {
            top: 70px;
            right: 10px;
            width: 45px;
            height: 45px;
        }
        
        .notification-panel-card {
            top: 70px;
            right: 10px;
            left: 10px;
            width: calc(100vw - 20px);
        }
        
        .notification-bell i {
            font-size: 1.2rem !important;
        }
    }
    
    @media (max-width: 768px) {
        .display-4 { font-size: 1.8rem; }
        .arabic-font { font-size: 1.2rem; }
        .lead { font-size: 1rem; }
        
        /* Notifikasi mobile */
        .notification-minimized {
            top: 80px;
            right: 15px;
            width: 45px;
            height: 45px;
            display: flex !important;
            opacity: 1 !important;
            visibility: visible !important;
        }
        
        .notification-panel-card {
            top: 80px;
            right: 15px;
        }
        
        .notification-bell i {
            font-size: 1.3rem !important;
        }
    }
    
    /* ===== PERBAIKAN RESPONSIVE UNTUK MOBILE ZOOM ===== */
    @media (max-width: 576px) {
        html {
            -webkit-text-size-adjust: 100%;
            text-size-adjust: 100%;
        }
        
        body {
            min-width: 320px;
            overflow-x: hidden;
        }
        
        .container {
            padding-left: 10px;
            padding-right: 10px;
            max-width: 100%;
            overflow-x: hidden;
        }
        
        /* Perbaikan untuk tulisan Arab */
        .arabic-container {
            min-height: 60px !important;
            margin: 0 auto 1.5rem auto !important;
            padding: 0 5px !important;
        }
        
        .arabic-font {
            font-size: clamp(1.2rem, 5vw, 1.6rem) !important;
            line-height: 1.6 !important;
            padding: 0 5px;
        }
        
        .arabic-text h3 {
            line-height: 1.5 !important;
            padding: 0 !important;
            margin-bottom: 1rem !important;
        }
        
        /* Perbaikan untuk greeting */
        .display-4.welcome-heading {
            font-size: 1.8rem !important;
            line-height: 1.3 !important;
            margin-bottom: 0.5rem !important;
        }
        
        .display-7.welcome-heading {
            font-size: 1.4rem !important;
            line-height: 1.3 !important;
            margin-bottom: 1rem !important;
        }
        
        .welcome-dashboard-message {
            font-size: 0.9rem !important;
            line-height: 1.4 !important;
            padding: 0 8px;
            display: block !important;
        }
        
        .welcome-dashboard-message .break-mobile {
            display: block;
            width: 100%;
            height: 5px;
        }
        
        /* Perbaikan untuk badge tanggal */
        .custom-date-badge {
            font-size: 0.74rem !important;
            padding: 0.5rem 0.8rem !important;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 95%;
            margin: 0 auto;
        }
        
        .custom-date-badge .bi {
            font-size: 0.9em !important;
        }
        
        /* Perbaikan untuk alert PWA */
        #pwa-promotion {
            margin: 0 5px 20px 5px;
            border-radius: 8px;
        }
        
        #pwa-promotion .d-flex {
            flex-direction: column;
            text-align: center;
        }
        
        #pwa-promotion .btn {
            margin-top: 8px;
            width: 100%;
        }
        
        /* Perbaikan layout cards */
        .card {
            margin-left: 2px;
            margin-right: 2px;
        }
        
        .card-body {
            padding: 12px;
        }
        
        /* Notifikasi mobile kecil */
        .notification-minimized {
            top: 70px;
            right: 10px;
            width: 40px;
            height: 40px;
            display: flex !important;
            opacity: 1 !important;
            visibility: visible !important;
        }
        
        .notification-bell i {
            font-size: 1.2rem !important;
        }
        
        .notification-panel-card {
            top: 70px;
            right: 10px;
            left: 10px;
            width: auto;
        }
        
        .arabic-text .word {
            margin: 0 0.5px !important;
            line-height: 1.5 !important;
        }
        
        .arabic-text .space {
            width: 0.2em !important;
        }
        
        /* Perbaikan animasi untuk mobile */
        .welcome-heading span,
        .welcome-dashboard-message span,
        .arabic-text .word {
            animation-duration: 0.5s;
        }
        
        /* Perbaikan responsif untuk tanggal */
        .custom-date-badge {
            font-size: 0.7rem !important;
            padding: 0.4rem 0.6rem !important;
            line-height: 1.2;
        }
        
        .hijri-date-official {
            font-size: 0.85rem;
        }
        
        .hijri-date-container {
            padding: 0 5px;
        }
        
        /* Pastikan tanggal tidak terpotong */
        .custom-date-badge br {
            display: none;
        }
        
        /* Perbaikan untuk mencegah texts overflow */
        .welcome-dashboard-message {
            font-size: 0.85rem !important;
            line-height: 1.3 !important;
            padding: 0 8px;
        }
        
        .welcome-dashboard-message span {
            margin: 0 -0.3px;
            display: inline-block;
        }
        
        /* Container untuk greeting */
        .greeting-container {
            padding: 0 5px;
        }
        
        .display-4.welcome-heading {
            font-size: 1.6rem !important;
            line-height: 1.2 !important;
            margin-bottom: 0.3rem !important;
            word-spacing: -0.5px;
        }
        
        .display-7.welcome-heading {
            font-size: 1.2rem !important;
            line-height: 1.2 !important;
            margin-bottom: 0.5rem !important;
        }
        
        /* Perbaikan untuk animasi per huruf di mobile */
        .welcome-heading span {
            display: inline-block;
            margin: 0 -0.5px;
        }
    }
    
    /* ===== PERBAIKAN UNTUK TABLET ===== */
    @media (min-width: 577px) and (max-width: 768px) {
        .container {
            padding-left: 15px;
            padding-right: 15px;
        }
        
        .arabic-font {
            font-size: clamp(1.5rem, 4vw, 1.7rem) !important;
        }
        
        .display-4.welcome-heading {
            font-size: 2rem;
        }
        
        .custom-date-badge {
            font-size: 0.85rem !important;
            padding: 0.5rem 0.8rem !important;
        }
    }
    
    /* Untuk desktop - ukuran lebih besar */
    @media (min-width: 992px) {
        .custom-date-badge {
            font-size: 1.1rem !important;
            padding: 0.75rem 1.5rem !important;
        }
    }
    
    /* Untuk tablet - ukuran medium */
    @media (min-width: 768px) and (max-width: 991px) {
        .custom-date-badge {
            font-size: 1rem !important;
            padding: 0.6rem 1.2rem !important;
        }
    }
    
    /* Untuk mencegah layout break saat zoom */
    @media (max-width: 400px) {
        .arabic-font {
            font-size: 1.3rem !important;
            line-height: 1.5 !important;
        }
        
        .arabic-text h3 {
            line-height: 1.4 !important;
        }
    }
    
    /* ===== PERBAIKAN SPESIFIK UNTUK PWA PROMOTION ===== */
    #pwa-promotion {
        border-left: 4px solid #0dcaf0;
    }
    
    #pwa-promotion .d-flex {
        align-items: center;
    }
    
    @media (max-width: 576px) {
        #pwa-promotion .d-flex {
            align-items: stretch;
        }
        
        #pwa-promotion .bi-phone {
            margin-bottom: 10px;
        }
    }
    
    /* ===== PERBAIKAN UNTUK MENCEGAH TEXTS OVERFLOW ===== */
    .welcome-heading {
        word-wrap: break-word;
        overflow-wrap: break-word;
    }
    
    .welcome-dashboard-message {
        line-height: 1.4;
        max-width: 100%;
        margin: 10px auto;
        padding: 0 10px;
        text-align: center;
        word-wrap: break-word;
        overflow-wrap: break-word;
        hyphens: auto;
    }
    
    /* ===== PERBAIKAN KHUSUS UNTUK TULISAN ARAB ===== */
    .arabic-text {
        line-height: 1.8 !important;
        padding: 0 10px !important;
        text-align: center !important;
        margin: 0 auto !important;
        max-width: 100% !important;
        display: block !important;
        word-wrap: break-word;
        overflow-wrap: break-word;
    }
    
    .arabic-text h3 {
        margin: 0 auto !important;
        max-width: 100% !important;
        display: block !important;
        text-align: center !important;
        line-height: 1.6 !important;
    }
    
    /* Zoom protection */
    .arabic-text h3 span[dir="rtl"] {
        transform: translateZ(0); /* Force hardware acceleration */
        backface-visibility: hidden;
        perspective: 1000;
        display: inline-block !important;
        text-align: center !important;
        margin: 0 auto !important;
        padding: 5px 0 !important;
        line-height: 1.8 !important;
        max-width: 100% !important;
        word-wrap: break-word !important;
        overflow-wrap: break-word !important;
    }
    
    /* Pastikan container parent tidak mempengaruhi layout Arab */
    .text-center.mb-8 {
        width: 100% !important;
        max-width: 100% !important;
        overflow: hidden !important;
        margin: 0 auto !important;
        padding: 0 5px !important;
    }
    
    .d-flex.flex-column.align-items-center {
        width: 100%;
        max-width: 100%;
    }
    
    /* Perbaikan untuk mencegah overflow */
    .text-center {
        max-width: 100%;
        overflow: hidden;
    }
    
    /* Dark mode support untuk notifikasi */
    [data-bs-theme="dark"] .notification-minimized {
        background: rgba(133, 109, 0, 0.95);
        border-color: #856d00;
    }
    
    [data-bs-theme="dark"] .notification-minimized:hover {
        background: rgba(133, 109, 0, 1);
    }
    </style>
</head>
<body>
    
    <!-- PERBAIKAN: Notifikasi dengan fungsi buka/tutup yang benar -->
    <?php if ($notifikasi_aktif == '1' && count($notifikasi_jadwal_belum_isi) > 0): ?>
        <!-- Ikon lonceng minimized - hanya tampil jika ada notifikasi -->
        <div class="notification-minimized" id="notificationMinimized">
            <div class="notification-bell position-relative" id="notificationBell" style="cursor: pointer;">
                <i class="bi bi-bell-fill" style="font-size: 1.5rem; color: <?= $dark_mode ? '#fff' : '#000' ?>;"></i>
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" id="notificationCount">
                    <?= count($notifikasi_jadwal_belum_isi) ?>
                </span>
            </div>
        </div>
        
        <!-- Panel Notifikasi - dimulai dengan status d-none -->
        <div class="notification-panel-card card shadow-sm d-none" id="notificationPanel">
            <div class="card-header toast-header-warning modal-header justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-bell-fill me-2"></i> Notifikasi Jadwal</h5>
                <button type="button" class="btn-close btn-sm" id="minimizeNotification"></button>
            </div>
            <div class="card-body toast-body-scroll" style="max-height: 400px; overflow-y: auto;">
                <p class="mb-3"><strong>Ada <?= count($notifikasi_jadwal_belum_isi) ?> jadwal yang belum diisi!</strong></p>
                
                <?php foreach ($notifikasi_jadwal_belum_isi as $index => $notif): ?>
                <div class="notification-item">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div class="flex-grow-1">
                            <small class="text-muted d-block"><?= $notif['jenis'] ?></small>
                            <strong class="d-block"><?= htmlspecialchars($notif['mata_pelajaran']) ?></strong>
                            <small class="text-muted d-block">
                                <?= htmlspecialchars($notif['kelas']) ?>
                            </small>
                            <small class="text-muted">
                                <i class="bi bi-clock me-1"></i><?= $notif['waktu'] ?>
                            </small>
                        </div>
                        <a href="<?= $notif['link'] ?>" class="btn btn-sm btn-warning ms-2">
                            <i class="bi bi-pencil-square"></i> Isi
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <div class="mt-3 pt-2 border-top">
                    <small class="text-muted">
                        <i class="bi bi-info-circle me-1"></i>
                        Notifikasi muncul <?= $waktu_tampil_jam ?> jam setelah jadwal dimulai
                    </small>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="container mt-4">
        <div class="text-center mb-8">
            <div class="d-flex flex-column align-items-center">
                <!-- Tulisan Arab dengan animasi per kata -->
                <div class="arabic-container">
                    <div class="arabic-font arabic-text">
                        <h3 class="display-6">
                            <span dir="rtl" style="display: inline-block; text-align: center; margin: 0 auto;">
                            <?php
                            $arabicText = "« السلام عليكم ورحمة الله »";
                            $arabicWords = explode(' ', $arabicText);
                            foreach ($arabicWords as $wordIndex => $word) {
                                echo '<span class="word" style="--word-index: '.$wordIndex.';">'.$word.'</span>';
                                echo '<span class="space" style="--word-index: '.$wordIndex.';"> </span>';
                            }
                            ?>
                            </span>
                        </h3>
                    </div>
                </div>
                
                <!-- Greeting dengan animasi per huruf -->
                <div class="text-center">
                    <!-- Baris pertama: Salam -->
                    <h1 class="display-4 welcome-heading mb-4">
                        <?php
                        $greeting = get_greeting() . ' !';
                        $greetingChars = preg_split('//u', $greeting, -1, PREG_SPLIT_NO_EMPTY);
                        foreach ($greetingChars as $i => $char) {
                            echo $char === ' ' ? 
                                '<span class="space" style="--i: '.$i.';">&nbsp;</span>' : 
                                '<span style="--i: '.$i.';">'.$char.'</span>';
                        }
                        ?>
                    </h1>
                    
                    <!-- Baris kedua: Nama pengguna -->
                    <h2 class="display-7 welcome-heading mb-4">
                        <?php
                        $username = (isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : "Pengguna") . '🌹';
                        $usernameChars = preg_split('//u', $username, -1, PREG_SPLIT_NO_EMPTY);
                        foreach ($usernameChars as $i => $char) {
                            echo $char === ' ' ? 
                                '<span class="space" style="--i: '.$i.';">&nbsp;</span>' : 
                                '<span style="--i: '.$i.';">'.$char.'</span>';
                        }
                        ?>
                        <div class="rose-icon">
                            <i class="fas fa-spa"></i>
                        </div>
                    </h2>
                </div>
            </div>
            
            <!-- Tampilkan Tanggal Masehi dan Hijriyah -->
            <div class="hijri-date-container mb-3">
                
                <!-- Tampilkan Tanggal Hijriyah Resmi -->
                <div class="text-center mb-1">
                    <div class="hijri-date-official">
                        <small class="ms-1 opacity-75 text-body">
                            <i class="bi bi-moon-stars-fill me-1"></i>
                            <span id="hijri-date-text"><?= htmlspecialchars($tanggal_hijriyah) ?></span>
                            <i class="bi bi-calendar2 me-1"></i>Versi Resmi Kemenag
                        </small>
                    </div>
                </div>
                
                <div class="badge bg-primary p-2 shadow-sm border-0 custom-date-badge" style="background: #1b5e20 !important;">    
                    <?= date('l, d F Y') ?> M | 
                    <span id="hijri-date-text" class="fw-bold">
                        <?= htmlspecialchars($tanggal_hijriyah) ?>
                    </span>
                </div>
                
            </div>
            
            <!-- Pesan selamat datang dengan animasi per huruf acak -->
            <p class="lead mt-3 welcome-dashboard-message" id="dashboardMessage">
                <?php
                // Baris pertama
                $welcomeMsg1 = "Selamat datang di Sistem Absensi Online";
                $welcomeChars1 = preg_split('//u', $welcomeMsg1, -1, PREG_SPLIT_NO_EMPTY);
                foreach ($welcomeChars1 as $char) {
                    echo $char === ' ' ? 
                        '<span class="space">&nbsp;</span>' : 
                        '<span>'.$char.'</span>';
                }
                ?>
                
                <!-- Break untuk mobile -->
                <span class="break-mobile"></span>
                
                <?php
                // Baris kedua
                $welcomeMsg2 = "PP. Matholi'ul Anwar";
                $welcomeChars2 = preg_split('//u', $welcomeMsg2, -1, PREG_SPLIT_NO_EMPTY);
                foreach ($welcomeChars2 as $char) {
                    echo $char === ' ' ? 
                        '<span class="space">&nbsp;</span>' : 
                        '<span>'.$char.'</span>';
                }
                ?>
            </p>
        </div>
        
        <!-- Tambahkan di dashboard.php setelah welcome message -->
        <?php if (!isset($_SESSION['pwa_promotion_dismissed'])): ?>
        <div class="alert alert-info p-2 p-md-3 alert-dismissible fade show mx-2 mx-md-0" id="pwa-promotion">
            <div class="d-flex align-items-center flex-column flex-md-row">
                <div class="d-flex align-items-center mb-2 mb-md-0 me-md-3">
                    <i class="bi bi-phone me-2 me-md-3" style="font-size: 1.5rem;"></i>
                    <div class="flex-grow-1 text-center text-md-start">
                        <p class="mb-1" style="font-size: 0.875rem;">
                            Dapatkan pengalaman terbaik dengan menginstall aplikasi ke perangkat Anda.
                        </p>
                    </div>
                </div>
                <div class="d-flex flex-column flex-sm-row gap-2 w-100 w-md-auto">
                    <button type="button" class="btn btn-outline-primary btn-sm flex-fill" 
                            style="font-size: 0.8rem; padding: 0.4rem 0.8rem;" 
                            onclick="window.pwaInstaller.installApp()">
                        <i class="bi bi-download me-1"></i> Install Aplikasi!
                    </button>
                    <button type="button" class="btn-close align-self-center" 
                            data-bs-dismiss="alert" aria-label="Close" 
                            onclick="dismissPWAPromotion()"
                            style="margin-left: 10px;">
                    </button>
                </div>
            </div>
        </div>
        
        <script>
        function dismissPWAPromotion() {
            fetch('?dismiss_pwa_promo=1');
        }
        </script>
        <?php endif; ?>
        
        <div class="card bg-warning  border-warning p-2 p-md-3 justify-content-between align-items-center mx-2 mx-md-0" style="background: orange !important;">
            <div class="text-center mb-8">
                <i class="bi bi-info-circle"></i>
                Jadwal akan ditampilkan 30 menit sebelum 
                
                <!-- Break untuk mobile -->
                <span class="break-mobile"></span>
                
                dan <?= $waktu_tenggang_jam ?> jam setelah mulai
            </div>
        </div>
        
        <div class="row mt-4">
            <!-- Card Akses Cepat untuk mobile -->
            <div class="col-12 mb-4 d-lg-none">
                <div class="card shadow-sm">
                    <div class="card-header bg-success text-white">
                        <h5 class="card-title mb-0"><i class="bi bi-speedometer2 me-2"></i> Akses Cepat</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-3">
                            <a href="absensi.php" class="btn btn-lg btn-outline-primary text-start">
                                <i class="bi bi-clipboard-check me-2"></i> Input Absensi
                            </a>
                            <a href="jadwal.php" class="btn btn-lg btn-outline-success text-start">
                                <i class="bi bi-calendar-week me-2"></i> Lihat Jadwal
                            </a>
                            <a href="database.php" class="btn btn-lg btn-outline-secondary text-start">
                                <i class="bi bi-people me-2"></i> Data Guru & Murid
                            </a>
                            <a href="rekapitulasi.php" class="btn btn-lg btn-outline-info text-start">
                                <i class="bi bi-bar-chart-line me-2"></i> Rekapitulasi
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Jadwal Kegiatan -->
            <div class="col-lg-9 col-12 mb-4">
                <div class="card shadow-sm">
                    <!-- Di bagian header setiap card jadwal, tambahkan informasi filter -->
                    <div class="card-header bg-primary text-white">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-calendar-event me-2"></i> 
                            Jadwal Kegiatan Hari Ini (<?= $hari_ini ?>)
                            <small class="float-end" style="font-size: 0.7rem;"><?= date('d F Y') ?></small>
                        </h5>
                    </div>
                    <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                    <?php if (count($jadwal_kegiatan_hari_ini) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Kegiatan</th>
                                    <th>Kamar</th>
                                    <th>Waktu</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($jadwal_kegiatan_hari_ini as $jadwal): ?>
                                <tr>
                                    <td><?= htmlspecialchars($jadwal['nama_kegiatan']) ?></td>
                                    <td><?= htmlspecialchars($jadwal['nama_kamar']) ?></td>
                                    <td><?= $jadwal['jam_mulai'] ?> - <?= $jadwal['jam_selesai'] ?></td>
                                    <td>
                                        <a href="absensi.php?filter&tanggal_kegiatan=<?= $today ?>&kegiatan_id=<?= $jadwal['kegiatan_id'] ?>&active_tab=kegiatan#kegiatan" 
                                           class="btn btn-sm btn-secondary">Absen</a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="alert alert-info">Belum ada jadwal kegiatan saat ini.</div>
                    <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Card Akses Cepat untuk desktop -->
            <div class="quick-access-container d-none d-lg-block">
                <div class="card shadow-sm">
                    <div class="card-header bg-success text-white">
                        <h5 class="card-title mb-0"><i class="bi bi-speedometer2 me-2"></i> Akses Cepat</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-3">
                            <a href="absensi.php" class="btn btn-lg btn-outline-primary text-start">
                                <i class="bi bi-clipboard-check me-2"></i> Input Absensi
                            </a>
                            <a href="jadwal.php" class="btn btn-lg btn-outline-success text-start">
                                <i class="bi bi-calendar-week me-2"></i> Lihat Jadwal
                            </a>
                            <a href="database.php" class="btn btn-lg btn-outline-secondary text-start">
                                <i class="bi bi-people me-2"></i> Data Murid
                            </a>
                            <a href="rekapitulasi.php" class="btn btn-lg btn-outline-info text-start">
                                <i class="bi bi-bar-chart-line me-2"></i> Rekapitulasi
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Jadwal Quran -->
            <div class="col-12 mb-4">
                <div class="card shadow-sm">
                    <!-- Di bagian header setiap card jadwal, tambahkan informasi filter -->
                    <div class="card-header bg-primary text-white">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-calendar-event me-2"></i> 
                            Jadwal Qur'an Hari Ini (<?= $hari_ini ?>)
                            <small class="float-end" style="font-size: 0.7rem;"><?= date('d F Y') ?></small>
                        </h5>
                    </div>
                    <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                    <?php if (count($jadwal_quran_hari_ini) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Mata Pelajaran</th>
                                    <th>Kelas</th>
                                    <th>Waktu</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($jadwal_quran_hari_ini as $jadwal): ?>
                                <tr>
                                    <td><?= htmlspecialchars($jadwal['mata_pelajaran']) ?></td>
                                    <td><?= htmlspecialchars($jadwal['nama_kelas']) ?></td>
                                    <td><?= $jadwal['jam_mulai'] ?> - <?= $jadwal['jam_selesai'] ?></td>
                                    <td>
                                        <a href="absensi.php?filter_quran&tanggal_quran=<?= $today ?>&jadwal_quran_id=<?= $jadwal['id'] ?>&active_tab=quran#quran" 
                                           class="btn btn-sm btn-secondary">Absen</a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="alert alert-info">Belum ada jadwal mengaji saat ini.</div>
                    <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Jadwal Mengajar (Madin) -->
            <div class="col-12 mb-4">
                <div class="card shadow-sm">
                    <!-- Di bagian header setiap card jadwal, tambahkan informasi filter -->
                    <div class="card-header bg-primary text-white">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-calendar-event me-2"></i> 
                            Jadwal Madin Hari Ini (<?= $hari_ini ?>)
                            <small class="float-end" style="font-size: 0.7rem;"><?= date('d F Y') ?></small>
                        </h5>
                    </div>
                    <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                    <?php if (count($jadwal_hari_ini) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Mata Pelajaran</th>
                                    <th>Kelas</th>
                                    <th>Waktu</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($jadwal_hari_ini as $jadwal): ?>
                                <tr>
                                    <td><?= htmlspecialchars($jadwal['mata_pelajaran']) ?></td>
                                    <td><?= htmlspecialchars($jadwal['nama_kelas']) ?></td>
                                    <td><?= $jadwal['jam_mulai'] ?> - <?= $jadwal['jam_selesai'] ?></td>
                                    <td>
                                        <a href="absensi.php?filter&tanggal=<?= $today ?>&jadwal_id=<?= $jadwal['jadwal_id'] ?>&active_tab=pelajaran#pelajaran" 
                                           class="btn btn-sm btn-secondary">Absen</a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="alert alert-info">Belum ada jadwal mengajar saat ini.</div>
                    <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- CARD STATISTIK ABSENSI GURU UNTUK DASHBOARD -->
        <?php if (in_array($_SESSION['role'], ['admin', 'staff'])): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="card bg-warning bg-opacity-10 border-warning">
                    <div class="card-body py-3">
                        <div class="card-header bg-info text-white">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="card-title text-white mb-0">
                                    <i class="bi bi-people-fill me-2"></i>Statistik Absensi Guru
                                </h5>
                                <small class="float-end" style="font-size: 0.7rem;"><?= date('d F Y') ?></small>
                            </div>
                        </div>
                        
                        <?php
                        // Hitung presentase untuk semua status
                        $total_hari_ini = $stats_guru['hari_ini']['total_hari_ini'];
                        $presentase_hadir = 0;
                        $presentase_sakit = 0;
                        $presentase_izin = 0;
                        $presentase_alpa = 0;
                        
                        if ($total_hari_ini > 0) {
                            $presentase_hadir = round(($stats_guru['hari_ini']['hadir_hari_ini'] / $total_hari_ini) * 100, 1);
                            $presentase_sakit = round(($stats_guru['hari_ini']['sakit_hari_ini'] / $total_hari_ini) * 100, 1);
                            $presentase_izin = round(($stats_guru['hari_ini']['izin_hari_ini'] / $total_hari_ini) * 100, 1);
                            $presentase_alpa = round(($stats_guru['hari_ini']['alpa_hari_ini'] / $total_hari_ini) * 100, 1);
                        }
                        
                        // Hitung presentase kehadiran keseluruhan
                        $presentase_kehadiran = 0;
                        if ($stats_guru['total_guru'] > 0) {
                            $presentase_kehadiran = round(($stats_guru['hari_ini']['hadir_hari_ini'] / $stats_guru['total_guru']) * 100, 1);
                        }
                        ?>
                        
                        <!-- Desktop View (6 kolom) -->
                        <div class="d-none d-md-block">
                            <div class="row mt-3 text-center">
                                
                                <div class="col-3">
                                    <div class="border rounded p-2 bg-success bg-opacity-10">
                                        <div class="h5 mb-1 text-success"><?= $stats_guru['hari_ini']['hadir_hari_ini'] ?></div>
                                        <small class="text-muted">Hadir</small>
                                        <div class="small text-success fw-bold mt-1"><?= $presentase_hadir ?>%</div>
                                    </div>
                                </div>
                                <div class="col-3">
                                    <div class="border rounded p-2 bg-warning bg-opacity-10">
                                        <div class="h5 mb-1 text-warning"><?= $stats_guru['hari_ini']['sakit_hari_ini'] ?></div>
                                        <small class="text-muted">Sakit</small>
                                        <div class="small text-warning fw-bold mt-1"><?= $presentase_sakit ?>%</div>
                                    </div>
                                </div>
                                <div class="col-3">
                                    <div class="border rounded p-2 bg-info bg-opacity-10">
                                        <div class="h5 mb-1 text-info"><?= $stats_guru['hari_ini']['izin_hari_ini'] ?></div>
                                        <small class="text-muted">Izin</small>
                                        <div class="small text-info fw-bold mt-1"><?= $presentase_izin ?>%</div>
                                    </div>
                                </div>
                                <div class="col-3">
                                    <div class="border rounded p-2 bg-danger bg-opacity-10">
                                        <div class="h5 mb-1 text-danger"><?= $stats_guru['hari_ini']['alpa_hari_ini'] ?></div>
                                        <small class="text-muted">Alpa</small>
                                        <div class="small text-danger fw-bold mt-1"><?= $presentase_alpa ?>%</div>
                                    </div>
                                </div>
                                <!-- Ringkasan Desktop -->
                                <div class="col-12 mt-3">
                                    <div class="border rounded p-2 bg-secondary bg-opacity-10">
                                        <small class="fw-bold">Total Absensi guru Hari Ini :</small>
                                        <div class="fw-bold mt-1">
                                            <small class="fw-bold"><?= $total_hari_ini ?> guru</small>
                                        </div>
                                        <div class="progress mt-1" style="height: 8px;">
                                            <div class="progress-bar bg-success" style="width: <?= $presentase_hadir ?>%"></div>
                                            <div class="progress-bar bg-warning" style="width: <?= $presentase_sakit ?>%"></div>
                                            <div class="progress-bar bg-info" style="width: <?= $presentase_izin ?>%"></div>
                                            <div class="progress-bar bg-danger" style="width: <?= $presentase_alpa ?>%"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        
                        <!-- Mobile View (bertumpuk) -->
                        <div class="d-block d-md-none">
                            <div class="row mt-3">
                                <!-- Hadir -->
                                <div class="col-12 mb-3">
                                    <div class="border rounded p-3 bg-success bg-opacity-10 d-flex justify-content-between align-items-center">
                                        <div>
                                            <div class="h4 mb-1 text-success"><?= $stats_guru['hari_ini']['hadir_hari_ini'] ?></div>
                                            <small class="text-muted">Hadir</small>
                                        </div>
                                        <div class="text-end">
                                            <div class="h5 text-success"><?= $presentase_hadir ?>%</div>
                                            <small class="text-muted">dari <?= $total_hari_ini ?> absen</small>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Sakit -->
                                <div class="col-12 mb-3">
                                    <div class="border rounded p-3 bg-warning bg-opacity-10 d-flex justify-content-between align-items-center">
                                        <div>
                                            <div class="h4 mb-1 text-warning"><?= $stats_guru['hari_ini']['sakit_hari_ini'] ?></div>
                                            <small class="text-muted">Sakit</small>
                                        </div>
                                        <div class="text-end">
                                            <div class="h5 text-warning"><?= $presentase_sakit ?>%</div>
                                            <small class="text-muted">dari <?= $total_hari_ini ?> absen</small>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Izin -->
                                <div class="col-12 mb-3">
                                    <div class="border rounded p-3 bg-info bg-opacity-10 d-flex justify-content-between align-items-center">
                                        <div>
                                            <div class="h4 mb-1 text-info"><?= $stats_guru['hari_ini']['izin_hari_ini'] ?></div>
                                            <small class="text-muted">Izin</small>
                                        </div>
                                        <div class="text-end">
                                            <div class="h5 text-info"><?= $presentase_izin ?>%</div>
                                            <small class="text-muted">dari <?= $total_hari_ini ?> absen</small>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Alpa -->
                                <div class="col-12 mb-3">
                                    <div class="border rounded p-3 bg-danger bg-opacity-10 d-flex justify-content-between align-items-center">
                                        <div>
                                            <div class="h4 mb-1 text-danger"><?= $stats_guru['hari_ini']['alpa_hari_ini'] ?></div>
                                            <small class="text-muted">Alpa</small>
                                        </div>
                                        <div class="text-end">
                                            <div class="h5 text-danger"><?= $presentase_alpa ?>%</div>
                                            <small class="text-muted">dari <?= $total_hari_ini ?> absen</small>
                                        </div>
                                    </div>
                                </div>
                                
                            </div>
                            
                            <!-- Ringkasan Mobile -->
                            <div class="row mt-3">
                                <div class="col-12">
                                    <div class="alert alert-info py-2">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <small class="fw-bold">Total Absensi guru Hari Ini:</small>
                                            <small class="fw-bold"><?= $total_hari_ini ?> guru</small>
                                        </div>
                                        <div class="progress mt-1" style="height: 8px;">
                                            <div class="progress-bar bg-success" style="width: <?= $presentase_hadir ?>%"></div>
                                            <div class="progress-bar bg-warning" style="width: <?= $presentase_sakit ?>%"></div>
                                            <div class="progress-bar bg-info" style="width: <?= $presentase_izin ?>%"></div>
                                            <div class="progress-bar bg-danger" style="width: <?= $presentase_alpa ?>%"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Statistik Absensi Murid-->
        <div class="row mt-4">
            <!-- Statistik Absensi Quran -->
            <div class="col-md-4 mb-4 stat-card">
                <div class="card shadow-sm">
                    <div class="card-header bg-info text-white">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0"><i class="bi bi-clipboard-data me-2"></i> Statistik Absensi Qur'an</h5>
                            <small class="float-end" style="font-size: 0.7rem;"><?= date('d F Y') ?></small>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="attendanceChartQuran"></canvas>
                        </div>
                        
                        <!-- Progress bars untuk Quran -->
                        <div class="mt-4">
                            <?php foreach (['Hadir', 'Sakit', 'Izin', 'Alpa'] as $status): 
                                $color = [
                                    'Hadir' => 'success',
                                    'Sakit' => 'warning',
                                    'Izin' => 'info',
                                    'Alpa' => 'danger'
                                ][$status];
                            ?>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between mb-1">
                                    <span><?= $status ?>: <?= $stats_quran_data['stats'][$status] ?> (<?= round($percentages_quran[$status], 1) ?>%)</span>
                                    <span><?= round($percentages_quran[$status], 1) ?>%</span>
                                </div>
                                <div class="progress" style="height: 20px;">
                                    <div class="progress-bar bg-<?= $color ?>" style="width: <?= $percentages_quran[$status] ?>%"></div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Statistik Absensi Madin -->
            <div class="col-md-4 mb-4 stat-card">
                <div class="card shadow-sm">
                    <div class="card-header bg-warning text-dark">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0"><i class="bi bi-clipboard-data me-2"></i> Statistik Absensi Madin</h5>
                            <small class="float-end" style="font-size: 0.7rem;"><?= date('d F Y') ?></small>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="attendanceChart"></canvas>
                        </div>
                        
                        <!-- Progress bars untuk Madin -->
                        <div class="mt-4">
                            <?php foreach (['Hadir', 'Sakit', 'Izin', 'Alpa'] as $status): 
                                $color = [
                                    'Hadir' => 'success',
                                    'Sakit' => 'warning',
                                    'Izin' => 'info',
                                    'Alpa' => 'danger'
                                ][$status];
                            ?>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between mb-1">
                                    <span><?= $status ?>: <?= $stats_madin['stats'][$status] ?> (<?= round($percentages_madin[$status], 1) ?>%)</span>
                                    <span><?= round($percentages_madin[$status], 1) ?>%</span>
                                </div>
                                <div class="progress" style="height: 20px;">
                                    <div class="progress-bar bg-<?= $color ?>" style="width: <?= $percentages_madin[$status] ?>%"></div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Statistik Absensi Kegiatan -->
            <div class="col-md-4 mb-4 stat-card">
                <div class="card shadow-sm">
                    <div class="card-header bg-success text-white">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0"><i class="bi bi-clipboard-data me-2"></i> Statistik Absensi Kegiatan</h5>
                            <small class="float-end" style="font-size: 0.7rem;"><?= date('d F Y') ?></small>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="attendanceChartKegiatan"></canvas>
                        </div>
                        <!-- Progress bars untuk Kegiatan -->
                        <div class="mt-4">
                            <?php foreach (['Hadir', 'Sakit', 'Izin', 'Alpa'] as $status): 
                                $color = [
                                    'Hadir' => 'success',
                                    'Sakit' => 'warning',
                                    'Izin' => 'info',
                                    'Alpa' => 'danger'
                                ][$status];
                            ?>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between mb-1">
                                    <span><?= $status ?>: <?= $stats_kegiatan_data['stats'][$status] ?> (<?= round($percentages_kegiatan[$status], 1) ?>%)</span>
                                    <span><?= round($percentages_kegiatan[$status], 1) ?>%</span>
                                </div>
                                <div class="progress" style="height: 20px;">
                                    <div class="progress-bar bg-<?= $color ?>" style="width: <?= $percentages_kegiatan[$status] ?>%"></div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Di bagian Statistik Pelanggaran dan Perizinan -->
        <div class="row mt-4">
            
            <!-- Card Baru: Perizinan Terbaru -->
            <div class="col-md-6 mb-4">
                <div class="card shadow-sm">
                    <div class="card-header bg-info text-white">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0"><i class="bi bi-clipboard-check me-2"></i> Perizinan Terbaru</h5>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="list-group">
                            <?php if (isset($perizinan_terbaru) && count($perizinan_terbaru) > 0): ?>
                                <?php foreach ($perizinan_terbaru as $row): ?>
                                <a href="pelanggaran.php" class="list-group-item list-group-item-action">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1"><?= htmlspecialchars($row['nama']) ?></h6>
                                        <small><?= $row['tanggal'] ?></small>
                                    </div>
                                    <p class="mb-1"><strong><?= htmlspecialchars($row['jenis']) ?>:</strong> 
                                    <?= htmlspecialchars($row['deskripsi']) ?></p>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <small class="text-muted">Kelas: <?= htmlspecialchars($row['nama_kelas']) ?></small>
                                        <span class="badge bg-<?= 
                                            $row['status_izin'] == 'Disetujui' ? 'success' : 
                                            ($row['status_izin'] == 'Ditolak' ? 'danger' : 'warning') 
                                        ?>">
                                            <?= $row['status_izin'] ?>
                                        </span>
                                    </div>
                                </a>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="alert alert-info mb-0">Tidak ada perizinan terbaru.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Card Pelanggaran Terbaru -->
            <div class="col-md-6 mb-4">
                <div class="card shadow-sm">
                    <div class="card-header bg-danger text-white">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0"><i class="bi bi-exclamation-triangle me-2"></i> Pelanggaran Terbaru</h5>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="list-group">
                            <?php if (isset($pelanggaran_terbaru) && count($pelanggaran_terbaru) > 0): ?>
                                <?php foreach ($pelanggaran_terbaru as $row): ?>
                                <a href="pelanggaran.php" class="list-group-item list-group-item-action">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1"><?= htmlspecialchars($row['nama']) ?></h6>
                                        <small><?= $row['tanggal'] ?></small>
                                    </div>
                                    <p class="mb-1"><strong><?= htmlspecialchars($row['jenis']) ?>:</strong> 
                                    <?= htmlspecialchars($row['deskripsi']) ?></p>
                                    <small class="text-muted">Kelas: <?= htmlspecialchars($row['nama_kelas']) ?></small>
                                </a>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="alert alert-info mb-0">Tidak ada pelanggaran terbaru.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
    // ===== PERBAIKAN: KONTROL NOTIFIKASI YANG DIPERBAIKI =====
    function initializeNotificationSystem() {
        const notificationBell = document.getElementById('notificationBell');
        const notificationPanel = document.getElementById('notificationPanel');
        const minimizeBtn = document.getElementById('minimizeNotification');
        const notificationMinimized = document.getElementById('notificationMinimized');
    
        console.log('🔔 Initializing notification system...', {
            bell: !!notificationBell,
            panel: !!notificationPanel,
            minimizeBtn: !!minimizeBtn,
            minimized: !!notificationMinimized
        });
    
        // Jika elemen tidak ada, keluar
        if (!notificationBell || !notificationPanel || !minimizeBtn || !notificationMinimized) {
            console.error('❌ Notification elements not found');
            return;
        }
    
        let isNotificationOpen = false;
    
        // Fungsi untuk membuka notifikasi
        function openNotification() {
            console.log('📖 Opening notification panel');
            notificationPanel.classList.remove('d-none');
            isNotificationOpen = true;
            
            // Tambahkan class mobile jika perlu
            if (window.innerWidth < 768) {
                notificationPanel.classList.add('mobile');
            } else {
                notificationPanel.classList.remove('mobile');
            }
        }
        
        // Fungsi untuk menutup notifikasi
        function closeNotification() {
            console.log('📕 Closing notification panel');
            notificationPanel.classList.add('d-none');
            isNotificationOpen = false;
        }
    
        // Toggle panel notifikasi ketika ikon lonceng diklik
        notificationBell.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            console.log('🔔 Bell clicked, current state:', isNotificationOpen);
            
            if (isNotificationOpen) {
                closeNotification();
            } else {
                openNotification();
            }
        });
    
        // Minimize panel notifikasi ketika tombol close diklik
        minimizeBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            console.log('❌ Close button clicked');
            closeNotification();
        });
    
        // Tutup panel ketika klik di luar area notifikasi
        document.addEventListener('click', function(e) {
            if (isNotificationOpen && 
                !notificationPanel.contains(e.target) && 
                !notificationMinimized.contains(e.target)) {
                console.log('🌍 Click outside, closing notification');
                closeNotification();
            }
        });
    
        // Handle escape key untuk menutup notifikasi
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && isNotificationOpen) {
                console.log('⌨️ Escape key pressed');
                closeNotification();
            }
        });
    
        // Handle resize untuk responsive
        window.addEventListener('resize', function() {
            if (isNotificationOpen) {
                if (window.innerWidth < 768) {
                    notificationPanel.classList.add('mobile');
                } else {
                    notificationPanel.classList.remove('mobile');
                }
            }
        });
    
        console.log('✅ Notification system initialized successfully');
    }
    
    // Panggil fungsi inisialisasi setelah DOM loaded
    document.addEventListener('DOMContentLoaded', function() {
        // Animasi untuk pesan dashboard
        const dashboardSpans = document.querySelectorAll('.welcome-dashboard-message span');
        dashboardSpans.forEach(span => {
            const randomDelay = Math.random() * 2;
            span.style.animationDelay = `${randomDelay}s`;
        });
        
        // Fungsi untuk membuat pie chart
        const createPieChart = (elementId, data, colors) => {
            const ctx = document.getElementById(elementId).getContext('2d');
            return new Chart(ctx, {
                type: 'pie',
                data: {
                    labels: ['Hadir', 'Sakit', 'Izin', 'Alpa'],
                    datasets: [{
                        data: data,
                        backgroundColor: colors
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        };
    
        // Statistik absensi utama
        createPieChart('attendanceChart', [
            <?= $stats_madin['stats']['Hadir'] ?>, 
            <?= $stats_madin['stats']['Sakit'] ?>, 
            <?= $stats_madin['stats']['Izin'] ?>, 
            <?= $stats_madin['stats']['Alpa'] ?>
        ], ['#4caf50', '#ff9800', '#2196f3', '#f44336']);
    
        // Statistik absensi Quran
        createPieChart('attendanceChartQuran', [
            <?= $stats_quran_data['stats']['Hadir'] ?>, 
            <?= $stats_quran_data['stats']['Sakit'] ?>, 
            <?= $stats_quran_data['stats']['Izin'] ?>, 
            <?= $stats_quran_data['stats']['Alpa'] ?>
        ], ['#4caf50', '#ff9800', '#2196f3', '#f44336']);
    
        // Statistik absensi Kegiatan
        createPieChart('attendanceChartKegiatan', [
            <?= $stats_kegiatan_data['stats']['Hadir'] ?>, 
            <?= $stats_kegiatan_data['stats']['Sakit'] ?>, 
            <?= $stats_kegiatan_data['stats']['Izin'] ?>, 
            <?= $stats_kegiatan_data['stats']['Alpa'] ?>
        ], ['#4caf50', '#ff9800', '#2196f3', '#f44336']);
    
        // Inisialisasi sistem notifikasi
        initializeNotificationSystem();
    
        // Auto-refresh notifikasi berdasarkan pengaturan
        const refreshInterval = <?= $refresh_otomatis_menit ?> * 60 * 1000;
        
        if (refreshInterval > 0 && <?= count($notifikasi_jadwal_belum_isi) > 0 ? 'true' : 'false' ?>) {
            setInterval(function() {
                fetch('?check_notifications=1')
                    .then(response => response.json())
                    .then(data => {
                        if (data.has_unfilled) {
                            console.log('🔄 Auto-refreshing notifications...');
                            location.reload();
                        }
                    })
                    .catch(error => console.error('Error checking notifications:', error));
            }, refreshInterval);
        }
    });
    
    // Fungsi untuk menyesuaikan layout Arab saat zoom
    function adjustArabicLayout() {
        const arabicContainer = document.querySelector('.arabic-container');
        const arabicText = document.querySelector('.arabic-text h3 span[dir="rtl"]');
        
        if (arabicContainer && arabicText) {
            const containerWidth = arabicContainer.offsetWidth;
            const textWidth = arabicText.scrollWidth;
            
            // Jika teks lebih lebar dari container, sesuaikan font size
            if (textWidth > containerWidth * 0.9) {
                const scaleFactor = (containerWidth * 0.9) / textWidth;
                const currentFontSize = parseFloat(getComputedStyle(arabicText).fontSize);
                arabicText.style.fontSize = (currentFontSize * scaleFactor) + 'px';
            } else {
                arabicText.style.fontSize = ''; // Reset ke default
            }
        }
    }
    
    // Debug comprehensive untuk notifikasi
    function debugNotificationSystem() {
        const elements = {
            bell: document.getElementById('notificationBell'),
            panel: document.getElementById('notificationPanel'),
            minimizeBtn: document.getElementById('minimizeNotification'),
            minimized: document.getElementById('notificationMinimized')
        };
        
        console.group('🔔 Debug Notification System');
        console.log('Elements status:', elements);
        
        if (elements.panel) {
            console.log('Panel classes:', elements.panel.classList.toString());
            console.log('Panel display style:', window.getComputedStyle(elements.panel).display);
            console.log('Panel opacity:', window.getComputedStyle(elements.panel).opacity);
            console.log('Panel visibility:', window.getComputedStyle(elements.panel).visibility);
        }
        
        console.groupEnd();
    }
    
    // Panggil debug setelah DOM loaded
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(debugNotificationSystem, 1000);
    });
    
    // Panggil saat load dan resize
    document.addEventListener('DOMContentLoaded', function() {
        adjustArabicLayout();
        
        // Debounce untuk resize
        let resizeTimer;
        window.addEventListener('resize', function() {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(adjustArabicLayout, 250);
        });
        
        // Juga panggil setelah animasi selesai
        setTimeout(adjustArabicLayout, 1000);
    });
    
    // Deteksi perubahan zoom
    let currentZoomLevel = window.devicePixelRatio;
    window.addEventListener('resize', function() {
        const newZoom = window.devicePixelRatio;
        
        if (Math.abs(newZoom - currentZoomLevel) > 0.1) {
            console.log('🔍 Zoom level changed, adjusting Arabic layout...');
            currentZoomLevel = newZoom;
            setTimeout(adjustArabicLayout, 100);
        }
    });
    
    // Deteksi perubahan ukuran viewport (termasuk zoom)
    window.addEventListener('resize', function() {
        const newZoom = window.devicePixelRatio;
        
        if (Math.abs(newZoom - currentZoomLevel) > 0.1) {
            console.log('🔍 Zoom level changed, adjusting layout...');
            currentZoomLevel = newZoom;
            
            // Force reflow untuk elemen tertentu
            const elements = document.querySelectorAll('.welcome-heading, .arabic-text, .hijri-date-container');
            elements.forEach(el => {
                el.style.display = 'none';
                void el.offsetHeight; // Trigger reflow
                el.style.display = '';
            });
        }
    });
    
    // Inisialisasi saat load
    document.addEventListener('DOMContentLoaded', function() {
        // Pastikan konten tetap dalam bounds
        const container = document.querySelector('.container');
        if (container) {
            container.style.minWidth = '320px';
            container.style.maxWidth = '100%';
        }
    });
    
    // Test function untuk notifikasi (opsional, hapus di production)
    function testNotification() {
        console.log('🧪 Testing notification system...');
        const bell = document.getElementById('notificationBell');
        if (bell) {
            bell.click();
            setTimeout(() => {
                const panel = document.getElementById('notificationPanel');
                console.log('Panel visible:', panel && !panel.classList.contains('d-none'));
            }, 500);
        }
    }
    
    // PERBAIKAN: Endpoint untuk check notifikasi via AJAX
    <?php if (isset($_GET['check_notifications'])): ?>
    <?php
        header('Content-Type: application/json');
        echo json_encode([
            'has_unfilled' => count($notifikasi_jadwal_belum_isi) > 0,
            'notifikasi_aktif' => $notifikasi_aktif == '1',
            'count' => count($notifikasi_jadwal_belum_isi)
        ]);
        exit;
    ?>
    <?php endif; ?>
    </script>
</body>
<?php
// Flush output buffer
ob_end_flush();
?>
</html>