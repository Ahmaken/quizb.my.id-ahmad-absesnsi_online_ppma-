<?php
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Simpan tab aktif dalam session
if (isset($_GET['active_tab'])) {
    $_SESSION['active_tab'] = $_GET['active_tab'];
}
$active_tab = $_SESSION['active_tab'] ?? 'quran';

require_once '../includes/init.php';

if (!check_auth()) {
    header("Location: ../index.php");
    exit();
}

// INISIALISASI VARIABEL DI SINI - SEBELUM BLOK POST
$message = '';
$jadwal_selected = null;
$kelas_selected = null;
$tanggal_selected = date('Y-m-d');

// Variabel untuk absensi kamar
$kamar_selected = null;
$kegiatan_selected = null;
$tanggal_kegiatan_selected = date('Y-m-d');

// Variabel untuk absensi Quran
$kelas_quran_selected = null;
$jadwal_quran_selected = null;
$tanggal_quran_selected = date('Y-m-d');
$absensi_quran_data = [];
$murid_quran_list = [];
$jadwal_quran_detail = null;

// Variabel untuk status tombol
$is_absensi_sudah_ada = false;
$is_absensi_quran_sudah_ada = false;
$is_absensi_kegiatan_sudah_ada = false;

// Ambil dari session atau sumber lainnya
$guru_id = isset($_SESSION['guru_id']) ? $_SESSION['guru_id'] : null;

// Tambahkan mapping hari setelah koneksi database
$day_map = [
    'Monday' => 'Senin',
    'Tuesday' => 'Selasa',
    'Wednesday' => 'Rabu',
    'Thursday' => 'Kamis',
    'Friday' => 'Jumat',
    'Saturday' => 'Sabtu',
    'Sunday' => 'Ahad'
];
$hari_ini = $day_map[date('l')];

// PROSES ABSENSI OTOMATIS GURU - VERSI DIPERBAIKI
if ($_SERVER['REQUEST_METHOD'] == 'POST' && (
    isset($_POST['simpan_absensi']) || 
    isset($_POST['perbarui_absensi']) ||
    isset($_POST['simpan_absensi_quran']) || 
    isset($_POST['perbarui_absensi_quran']) ||
    isset($_POST['simpan_absensi_kegiatan']) ||
    isset($_POST['perbarui_absensi_kegiatan'])
)) {
    $tanggal = '';
    $guru_id_otomatis = null;
    
    if (isset($_POST['simpan_absensi']) || isset($_POST['perbarui_absensi'])) {
        $tanggal = $_POST['tanggal'];
        $jadwal_id = $_POST['jadwal_id'] ?? null;
        
        // Ambil guru_id dari jadwal madin - VERSI DIPERBAIKI
        if ($jadwal_id) {
            $sql_guru = "SELECT guru_id FROM jadwal_madin WHERE jadwal_id = ?";
            $stmt_guru = $conn->prepare($sql_guru);
            $stmt_guru->bind_param("i", $jadwal_id);
            $stmt_guru->execute();
            $result_guru = $stmt_guru->get_result();
            if ($row_guru = $result_guru->fetch_assoc()) {
                $guru_id_otomatis = $row_guru['guru_id'];
                error_log("Guru ID ditemukan untuk jadwal madin: " . $guru_id_otomatis);
            } else {
                error_log("Guru ID tidak ditemukan untuk jadwal madin ID: " . $jadwal_id);
            }
        }
    } 
    elseif (isset($_POST['simpan_absensi_quran']) || isset($_POST['perbarui_absensi_quran'])) {
        $tanggal = $_POST['tanggal_quran'];
        $jadwal_quran_id = $_POST['jadwal_quran_id'] ?? null;
        
        // Ambil guru_id dari jadwal quran - VERSI DIPERBAIKI
        if ($jadwal_quran_id) {
            $sql_guru = "SELECT guru_id FROM jadwal_quran WHERE id = ?";
            $stmt_guru = $conn->prepare($sql_guru);
            $stmt_guru->bind_param("i", $jadwal_quran_id);
            $stmt_guru->execute();
            $result_guru = $stmt_guru->get_result();
            if ($row_guru = $result_guru->fetch_assoc()) {
                $guru_id_otomatis = $row_guru['guru_id'];
                error_log("Guru ID ditemukan untuk jadwal quran: " . $guru_id_otomatis);
            } else {
                error_log("Guru ID tidak ditemukan untuk jadwal quran ID: " . $jadwal_quran_id);
            }
        }
    } 
    elseif (isset($_POST['simpan_absensi_kegiatan']) || isset($_POST['perbarui_absensi_kegiatan'])) {
        $tanggal = $_POST['tanggal_kegiatan'];
        $kegiatan_id = $_POST['kegiatan_id'] ?? null;
        
        // Ambil guru_id dari kegiatan - VERSI DIPERBAIKI
        if ($kegiatan_id) {
            $sql_guru = "SELECT guru_id FROM jadwal_kegiatan WHERE kegiatan_id = ?";
            $stmt_guru = $conn->prepare($sql_guru);
            $stmt_guru->bind_param("i", $kegiatan_id);
            $stmt_guru->execute();
            $result_guru = $stmt_guru->get_result();
            if ($row_guru = $result_guru->fetch_assoc()) {
                $guru_id_otomatis = $row_guru['guru_id'];
                error_log("Guru ID ditemukan untuk kegiatan: " . $guru_id_otomatis);
            } else {
                error_log("Guru ID tidak ditemukan untuk kegiatan ID: " . $kegiatan_id);
            }
        }
    }
    
    // Catat kehadiran guru jika ada guru_id dan tanggal
    if ($guru_id_otomatis && $tanggal) {
        error_log("Mencatat kehadiran otomatis untuk Guru ID: $guru_id_otomatis, Tanggal: $tanggal");
        $success = catat_kehadiran_guru_otomatis($conn, $tanggal, $guru_id_otomatis);
        if ($success) {
            error_log("✅ Kehadiran guru berhasil dicatat");
        } else {
            error_log("❌ Gagal mencatat kehadiran guru");
        }
    } else {
        error_log("❌ Data tidak lengkap untuk catat kehadiran guru. Guru ID: $guru_id_otomatis, Tanggal: $tanggal");
    }
}

// Handle AJAX request for kegiatan data
if (isset($_GET['kamar_id']) && isset($_GET['ajax'])) {
    $kamar_id = intval($_GET['kamar_id']);
    
    // Query yang benar untuk mengambil kegiatan berdasarkan kamar dan hari ini
    $sql = "SELECT jk.*, k.nama_kamar 
            FROM jadwal_kegiatan jk 
            LEFT JOIN kamar k ON jk.kamar_id = k.kamar_id
            WHERE jk.kamar_id = ? 
            AND jk.hari = ?";
    $stmt = $conn->prepare($sql);
    
    if ($stmt) {
        $stmt->bind_param("is", $kamar_id, $hari_ini);
        $stmt->execute();
        $result = $stmt->get_result();
        
        echo '<option value="">-- Pilih Kegiatan --</option>';
        while ($row = $result->fetch_assoc()) {
            echo '<option value="' . $row['kegiatan_id'] . '" data-kamar="' . $row['kamar_id'] . '">';
            echo htmlspecialchars($row['nama_kegiatan']) . ' (' . htmlspecialchars($row['nama_kamar']) . ')';
            echo '</option>';
        }
    } else {
        echo '<option value="">Error memuat data</option>';
    }
    
    ob_end_flush();
    exit();
}

// Proses simpan absensi Madin
if ($_SERVER['REQUEST_METHOD'] == 'POST' && (isset($_POST['simpan_absensi']) || isset($_POST['perbarui_absensi']))) {
    $jadwal_id = $_POST['jadwal_id'];
    $tanggal = $_POST['tanggal'];
    
    $absensi_data_post = $_POST['absensi'];
    $is_update = isset($_POST['perbarui_absensi']);

    foreach ($absensi_data_post as $murid_id => $data) {
        $status = $data['status'];
        $keterangan = $data['keterangan'] ?? '';

        if ($is_update) {
            // Update data yang sudah ada
            $sql = "UPDATE absensi SET status = ?, keterangan = ? 
                    WHERE jadwal_madin_id = ? AND murid_id = ? AND tanggal = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssiis", $status, $keterangan, $jadwal_id, $murid_id, $tanggal);
        } else {
            // Insert baru
            $sql = "INSERT INTO absensi (jadwal_madin_id, murid_id, tanggal, status, keterangan) 
                    VALUES (?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iisss", $jadwal_id, $murid_id, $tanggal, $status, $keterangan);
        }

        if (!$stmt->execute()) {
            $message = "danger|Error: " . $stmt->error;
            break;
        }
        
        // Catat pelanggaran atau perizinan setelah berhasil menyimpan absensi
        if ($status == 'Alpa') {
            catat_pelanggaran_alpa($conn, $murid_id, $tanggal, $keterangan);
        } elseif ($status == 'Izin') {
            catat_perizinan($conn, $murid_id, $tanggal, $keterangan);
        }
    }

    if (!isset($message)) {
        $action_text = $is_update ? "diperbarui" : "disimpan";
        $message = "success|Absensi berhasil $action_text!";
        
        // Kirim notifikasi untuk yang alpa
        foreach ($absensi_data_post as $murid_id => $data) {
            if ($data['status'] == 'Alpa') {
                $api_data = [
                    'action' => 'notify_alpa',
                    'jadwal_type' => 'madin',
                    'jadwal_id' => $jadwal_selected,
                    'murid_id' => $murid_id,
                    'tanggal' => $tanggal
                ];
                
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/api.php");
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $api_data);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 5);
                curl_exec($ch);
                curl_close($ch);
            }
        }
        
        error_log("Absensi berhasil $action_text untuk " . count($absensi_data_post) . " murid");
    }
}

// Proses simpan absensi Quran
if ($_SERVER['REQUEST_METHOD'] == 'POST' && (isset($_POST['simpan_absensi_quran']) || isset($_POST['perbarui_absensi_quran']))) {
    $jadwal_quran_id = $_POST['jadwal_quran_id'];
    $tanggal_quran = $_POST['tanggal_quran'];
    
    $absensi_quran_post = $_POST['absensi_quran'];
    $is_update = isset($_POST['perbarui_absensi_quran']);

    foreach ($absensi_quran_post as $murid_id => $data) {
        $status = $data['status'];
        $keterangan = $data['keterangan'] ?? '';

        if ($is_update) {
            // Update data yang sudah ada
            $sql = "UPDATE absensi_quran SET status = ?, keterangan = ? 
                    WHERE jadwal_quran_id = ? AND murid_id = ? AND tanggal = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssiis", $status, $keterangan, $jadwal_quran_id, $murid_id, $tanggal_quran);
        } else {
            // Insert baru
            $sql = "INSERT INTO absensi_quran (jadwal_quran_id, murid_id, tanggal, status, keterangan) 
                    VALUES (?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iisss", $jadwal_quran_id, $murid_id, $tanggal_quran, $status, $keterangan);
        }

        if (!$stmt->execute()) {
            $message = "danger|Error: " . $stmt->error;
            break;
        }
        
        // Catat pelanggaran atau perizinan setelah berhasil menyimpan absensi
        if ($status == 'Alpa') {
            catat_pelanggaran_alpa($conn, $murid_id, $tanggal_quran, $keterangan);
        } elseif ($status == 'Izin') {
            catat_perizinan($conn, $murid_id, $tanggal_quran, $keterangan);
        }
    }

    if (!isset($message)) {
        $action_text = $is_update ? "diperbarui" : "disimpan";
        $message = "success|Absensi Quran berhasil $action_text!";
        
        // Kirim notifikasi untuk yang alpa
        foreach ($absensi_quran_post as $murid_id => $data) {
            if ($data['status'] == 'Alpa') {
                $api_data = [
                    'action' => 'notify_alpa',
                    'jadwal_type' => 'quran',
                    'jadwal_id' => $jadwal_quran_selected,
                    'murid_id' => $murid_id,
                    'tanggal' => $tanggal_quran
                ];
                
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/api.php");
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $api_data);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 5);
                $result = curl_exec($ch);
                curl_close($ch);
                
                error_log("Notifikasi alpa Quran - Murid ID: $murid_id, Result: " . $result);
            }
        }
        
        error_log("Absensi Quran berhasil $action_text untuk " . count($absensi_quran_post) . " murid");
    }
}

// Proses simpan absensi kegiatan kamar
if ($_SERVER['REQUEST_METHOD'] == 'POST' && (isset($_POST['simpan_absensi_kegiatan']) || isset($_POST['perbarui_absensi_kegiatan']))) {
    $kegiatan_id = $_POST['kegiatan_id'];
    $tanggal_kegiatan = $_POST['tanggal_kegiatan'];
    
    $absensi_kegiatan_post = $_POST['absensi_kegiatan'];
    $is_update = isset($_POST['perbarui_absensi_kegiatan']);

    foreach ($absensi_kegiatan_post as $murid_id => $data) {
        $status = $data['status'];
        $keterangan = $data['keterangan'] ?? '';

        if ($is_update) {
            // Update data yang sudah ada
            $sql = "UPDATE absensi_kegiatan SET status = ?, keterangan = ? 
                    WHERE kegiatan_id = ? AND murid_id = ? AND tanggal = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssiis", $status, $keterangan, $kegiatan_id, $murid_id, $tanggal_kegiatan);
        } else {
            // Insert baru
            $sql = "INSERT INTO absensi_kegiatan (kegiatan_id, murid_id, tanggal, status, keterangan) 
                    VALUES (?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iisss", $kegiatan_id, $murid_id, $tanggal_kegiatan, $status, $keterangan);
        }

        if (!$stmt->execute()) {
            $message = "danger|Error: " . $stmt->error;
            break;
        }
        
        // Catat pelanggaran atau perizinan setelah berhasil menyimpan absensi
        if ($status == 'Alpa') {
            catat_pelanggaran_alpa($conn, $murid_id, $tanggal_kegiatan, $keterangan);
        } elseif ($status == 'Izin') {
            catat_perizinan($conn, $murid_id, $tanggal_kegiatan, $keterangan);
        }
    }

    if (!isset($message)) {
        $action_text = $is_update ? "diperbarui" : "disimpan";
        $message = "success|Absensi kegiatan berhasil $action_text!";
        
        // Kirim notifikasi untuk yang alpa
        foreach ($absensi_kegiatan_post as $murid_id => $data) {
            if ($data['status'] == 'Alpa') {
                $api_data = [
                    'action' => 'notify_alpa',
                    'jadwal_type' => 'kegiatan',
                    'jadwal_id' => $kegiatan_selected,
                    'murid_id' => $murid_id,
                    'tanggal' => $tanggal_kegiatan
                ];
                
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/api.php");
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $api_data);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 5);
                curl_exec($ch);
                curl_close($ch);
            }
        }
        
        error_log("Absensi kegiatan berhasil $action_text untuk " . count($absensi_kegiatan_post) . " murid");
    }
}

// Fungsi untuk mencatat pelanggaran otomatis
function catat_pelanggaran_alpa($conn, $murid_id, $tanggal, $keterangan = '') {
    $jenis = "Tidak Hadir (Alpa)";
    $deskripsi = "Tidak hadir tanpa keterangan" . ($keterangan ? ". Keterangan: " . $keterangan : '');
    
    // Cek apakah sudah ada pelanggaran untuk murid ini pada tanggal yang sama
    $sql_check = "SELECT * FROM pelanggaran 
                  WHERE murid_id = ? 
                  AND tanggal = ? 
                  AND jenis = ?";
    $stmt_check = $conn->prepare($sql_check);
    $stmt_check->bind_param("iss", $murid_id, $tanggal, $jenis);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    
    if ($result_check->num_rows === 0) {
        // Jika belum ada, tambahkan pelanggaran
        $sql = "INSERT INTO pelanggaran (murid_id, jenis, tanggal, deskripsi) 
                VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("isss", $murid_id, $jenis, $tanggal, $deskripsi);
        
        if ($stmt->execute()) {
            error_log("Pelanggaran alpa berhasil dicatat untuk murid ID: $murid_id, tanggal: $tanggal");
        } else {
            error_log("Error mencatat pelanggaran: " . $stmt->error);
        }
    }
    
    // Di dalam fungsi catat_pelanggaran_alpa, tambahkan:
    error_log("Mencatat $jenis untuk murid ID: $murid_id, tanggal: $tanggal");
    
}

// Fungsi untuk mencatat perizinan otomatis
function catat_perizinan($conn, $murid_id, $tanggal, $keterangan = '') {
    $jenis = "Izin dari Absensi";
    $deskripsi = "Izin: " . $keterangan;
    
    // Cek apakah sudah ada perizinan untuk murid ini pada tanggal yang sama
    $sql_check = "SELECT * FROM perizinan 
                  WHERE murid_id = ? 
                  AND tanggal = ? 
                  AND jenis = ?";
    $stmt_check = $conn->prepare($sql_check);
    $stmt_check->bind_param("iss", $murid_id, $tanggal, $jenis);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    
    if ($result_check->num_rows === 0) {
        // Jika belum ada, tambahkan perizinan dengan status Disetujui
        $sql = "INSERT INTO perizinan (murid_id, jenis, tanggal, deskripsi, status_izin) 
                VALUES (?, ?, ?, ?, 'Disetujui')";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("isss", $murid_id, $jenis, $tanggal, $deskripsi);
        
        if ($stmt->execute()) {
            error_log("Perizinan berhasil dicatat untuk murid ID: $murid_id, tanggal: $tanggal");
        } else {
            error_log("Error mencatat perizinan: " . $stmt->error);
        }
    }
    
    // Di dalam fungsi catat_perizinan, tambahkan:
    error_log("Mencatat $jenis untuk murid ID: $murid_id, tanggal: $tanggal");
    
}

// Fungsi untuk mencatat kehadiran guru otomatis - VERSI DIPERBAIKI
// Fungsi untuk mencatat kehadiran guru otomatis - VERSI DIPERBAIKI
function catat_kehadiran_guru_otomatis($conn, $tanggal, $guru_id, $jadwal_madin_id = null, $jadwal_quran_id = null, $kegiatan_id = null, $keterangan = 'Hadir mengajar - absensi otomatis') {
    if (!$guru_id) {
        error_log("❌ Guru ID kosong, tidak dapat mencatat kehadiran");
        return false;
    }
    
    try {
        // Cek apakah sudah ada absensi untuk guru di tanggal dan jadwal tersebut
        $sql_check = "SELECT * FROM absensi_guru WHERE guru_id = ? AND tanggal = ?";
        
        // Tambahkan kondisi untuk jadwal spesifik jika ada
        if ($jadwal_madin_id) {
            $sql_check .= " AND jadwal_madin_id = ?";
        } elseif ($jadwal_quran_id) {
            $sql_check .= " AND jadwal_quran_id = ?";
        } elseif ($kegiatan_id) {
            $sql_check .= " AND kegiatan_id = ?";
        }
        
        $stmt_check = $conn->prepare($sql_check);
        
        if ($jadwal_madin_id) {
            $stmt_check->bind_param("isi", $guru_id, $tanggal, $jadwal_madin_id);
        } elseif ($jadwal_quran_id) {
            $stmt_check->bind_param("isi", $guru_id, $tanggal, $jadwal_quran_id);
        } elseif ($kegiatan_id) {
            $stmt_check->bind_param("isi", $guru_id, $tanggal, $kegiatan_id);
        } else {
            $stmt_check->bind_param("is", $guru_id, $tanggal);
        }
        
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();
        
        $waktu_absensi = date('Y-m-d H:i:s');
        
        // Hitung deadline (1 jam setelah jam selesai)
        $deadline_absensi = null;
        if ($jadwal_madin_id) {
            // Ambil jam selesai dari jadwal madin
            $sql_jam = "SELECT jam_selesai FROM jadwal_madin WHERE jadwal_id = ?";
            $stmt_jam = $conn->prepare($sql_jam);
            $stmt_jam->bind_param("i", $jadwal_madin_id);
            $stmt_jam->execute();
            $result_jam = $stmt_jam->get_result();
            if ($row_jam = $result_jam->fetch_assoc()) {
                $deadline_absensi = date('Y-m-d H:i:s', strtotime($tanggal . ' ' . $row_jam['jam_selesai'] . ' +1 hour'));
            }
        } elseif ($jadwal_quran_id) {
            // Ambil jam selesai dari jadwal quran
            $sql_jam = "SELECT jam_selesai FROM jadwal_quran WHERE id = ?";
            $stmt_jam = $conn->prepare($sql_jam);
            $stmt_jam->bind_param("i", $jadwal_quran_id);
            $stmt_jam->execute();
            $result_jam = $stmt_jam->get_result();
            if ($row_jam = $result_jam->fetch_assoc()) {
                $deadline_absensi = date('Y-m-d H:i:s', strtotime($tanggal . ' ' . $row_jam['jam_selesai'] . ' +1 hour'));
            }
        } elseif ($kegiatan_id) {
            // Ambil jam selesai dari kegiatan
            $sql_jam = "SELECT jam_selesai FROM jadwal_kegiatan WHERE kegiatan_id = ?";
            $stmt_jam = $conn->prepare($sql_jam);
            $stmt_jam->bind_param("i", $kegiatan_id);
            $stmt_jam->execute();
            $result_jam = $stmt_jam->get_result();
            if ($row_jam = $result_jam->fetch_assoc()) {
                $deadline_absensi = date('Y-m-d H:i:s', strtotime($tanggal . ' ' . $row_jam['jam_selesai'] . ' +1 hour'));
            }
        }
        
        if ($result_check->num_rows === 0) {
            // Jika belum ada, tambahkan absensi dengan status Hadir
            $sql = "INSERT INTO absensi_guru (guru_id, tanggal, status, keterangan, is_otomatis, waktu_absensi, deadline_absensi, jadwal_madin_id, jadwal_quran_id, kegiatan_id) 
                    VALUES (?, ?, 'Hadir', ?, 1, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("issssiii", $guru_id, $tanggal, $keterangan, $waktu_absensi, $deadline_absensi, $jadwal_madin_id, $jadwal_quran_id, $kegiatan_id);
            
            if ($stmt->execute()) {
                error_log("✅ Absensi otomatis guru BERHASIL: Guru ID $guru_id, Tanggal $tanggal, Status: Hadir, Jadwal Madin: $jadwal_madin_id, Jadwal Quran: $jadwal_quran_id, Kegiatan: $kegiatan_id");
                return true;
            } else {
                error_log("❌ Error absensi otomatis guru: " . $stmt->error);
                return false;
            }
        } else {
            // Jika sudah ada, update status menjadi Hadir dan catat waktu absensi
            $row = $result_check->fetch_assoc();
            $sql_update = "UPDATE absensi_guru SET status = 'Hadir', keterangan = ?, waktu_absensi = ?, deadline_absensi = ?, is_otomatis = 1 
                          WHERE absensi_id = ?";
            $stmt_update = $conn->prepare($sql_update);
            $stmt_update->bind_param("sssi", $keterangan, $waktu_absensi, $deadline_absensi, $row['absensi_id']);
            
            if ($stmt_update->execute()) {
                error_log("✅ Absensi guru diupdate: Guru ID $guru_id, Tanggal $tanggal, Status: Hadir");
                return true;
            } else {
                error_log("❌ Error update absensi guru: " . $stmt_update->error);
                return false;
            }
        }
    } catch (Exception $e) {
        error_log("❌ Exception dalam catat_kehadiran_guru_otomatis: " . $e->getMessage());
        return false;
    }
}

// Ambil data filter
if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    if (isset($_GET['filter'])) {
        $jadwal_selected = $_GET['jadwal_id'] ?? null;
        $kelas_selected = $_GET['kelas_id'] ?? null;
        $tanggal_selected = $_GET['tanggal'] ?? date('Y-m-d');
        
        // Filter untuk kegiatan kamar
        $kamar_selected = $_GET['kamar_id'] ?? null;
        $kegiatan_selected = $_GET['kegiatan_id'] ?? null;
        $tanggal_kegiatan_selected = $_GET['tanggal_kegiatan'] ?? date('Y-m-d');
    }
    
    if (isset($_GET['filter_quran'])) {
        $kelas_quran_selected = $_GET['kelas_quran_id'] ?? null;
        $jadwal_quran_selected = $_GET['jadwal_quran_id'] ?? null;
        $tanggal_quran_selected = $_GET['tanggal_quran'] ?? date('Y-m-d');
    }
}

// PERBAIKI QUERY DENGAN PREPARED STATEMENT
function getJadwalMadin($conn, $guru_id, $hari_ini) {
    $sql_jadwal = "SELECT j.*, km.nama_kelas 
                   FROM jadwal_madin j 
                   LEFT JOIN kelas_madin km ON j.kelas_madin_id = km.kelas_id
                   WHERE j.hari = ?";
    
    $params = [$hari_ini];
    $types = "s";
    
    if ($guru_id) {
        $sql_jadwal .= " AND km.guru_id = ?";
        $types .= "i";
        $params[] = $guru_id;
    }
    
    $stmt = $conn->prepare($sql_jadwal);
    if ($stmt) {
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        return $stmt->get_result();
    }
    return false;
}

// Fungsi untuk mengecek apakah absensi sudah pernah disimpan
function cek_absensi_sudah_ada($conn, $type, $jadwal_id, $tanggal) {
    $table_map = [
        'madin' => 'absensi',
        'quran' => 'absensi_quran', 
        'kegiatan' => 'absensi_kegiatan'
    ];
    
    $id_column_map = [
        'madin' => 'jadwal_madin_id',
        'quran' => 'jadwal_quran_id',
        'kegiatan' => 'kegiatan_id'
    ];
    
    if (!isset($table_map[$type]) || !isset($id_column_map[$type])) {
        return false;
    }
    
    $table = $table_map[$type];
    $id_column = $id_column_map[$type];
    
    $sql = "SELECT COUNT(*) as total FROM $table WHERE $id_column = ? AND tanggal = ?";
    $stmt = $conn->prepare($sql);
    
    if ($stmt) {
        $stmt->bind_param("is", $jadwal_id, $tanggal);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        return $row['total'] > 0;
    }
    
    return false;
}

// Ambil semua jadwal madin
$sql_jadwal = "SELECT j.*, km.nama_kelas 
               FROM jadwal_madin j 
               LEFT JOIN kelas_madin km ON j.kelas_madin_id = km.kelas_id
               WHERE j.hari = '$hari_ini'"; // Hanya jadwal hari ini

// Tambahkan filter guru jika $guru_id tersedia dan valid
if (isset($guru_id) && !empty($guru_id)) {
    $sql_jadwal .= " AND km.guru_id = $guru_id";
}

// Eksekusi query
$result_jadwal = $conn->query($sql_jadwal);
$jadwal_list = [];

if ($result_jadwal && $result_jadwal->num_rows > 0) {
    while ($row = $result_jadwal->fetch_assoc()) {
        $jadwal_list[] = $row;
    }
}               
               
// GUNAKAN FUNGSI YANG SUDAH DIPERBAIKI
$result_jadwal = getJadwalMadin($conn, $guru_id, $hari_ini);
$jadwal_list = [];
if ($result_jadwal && $result_jadwal->num_rows > 0) {
    while ($row = $result_jadwal->fetch_assoc()) {
        $jadwal_list[] = $row;
    }
}

// Ambil semua kelas madin dengan filter guru
$sql_kelas = "SELECT * FROM kelas_madin";
if ($guru_id) {
    $sql_kelas .= " WHERE guru_id = $guru_id";
}
$result_kelas = $conn->query($sql_kelas);
$kelas_list = [];
if ($result_kelas && $result_kelas->num_rows > 0) {
    while ($row = $result_kelas->fetch_assoc()) {
        $kelas_list[] = $row;
    }
}

// Ambil semua kamar dengan filter guru
$sql_kamar = "SELECT * FROM kamar";
if ($guru_id) {
    $sql_kamar .= " WHERE guru_id = $guru_id";
}
$result_kamar = $conn->query($sql_kamar);
$kamar_list = [];
if ($result_kamar && $result_kamar->num_rows > 0) {
    while ($row = $result_kamar->fetch_assoc()) {
        $kamar_list[] = $row;
    }
}

// Ambil semua kelas Quran dengan filter guru
$sql_kelas_quran = "SELECT * FROM kelas_quran";
if ($guru_id) {
    $sql_kelas_quran .= " WHERE guru_id = $guru_id";
}
$result_kelas_quran = $conn->query($sql_kelas_quran);
$kelas_quran_list = [];
if ($result_kelas_quran && $result_kelas_quran->num_rows > 0) {
    while ($row = $result_kelas_quran->fetch_assoc()) {
        $kelas_quran_list[] = $row;
    }
}

// Ambil semua jadwal Quran dengan filter guru
$sql_jadwal_quran = "SELECT jq.*, kq.nama_kelas 
                     FROM jadwal_quran jq 
                     LEFT JOIN kelas_quran kq ON jq.kelas_quran_id = kq.id
                     WHERE jq.hari = '$hari_ini'"; // Hanya jadwal hari ini
if ($guru_id) {
    $sql_jadwal_quran .= " AND kq.guru_id = $guru_id";
}                     
$result_jadwal_quran = $conn->query($sql_jadwal_quran);
$jadwal_quran_all = [];
if ($result_jadwal_quran && $result_jadwal_quran->num_rows > 0) {
    while ($row = $result_jadwal_quran->fetch_assoc()) {
        $jadwal_quran_all[] = $row;
    }
}

// Ambil jadwal Quran berdasarkan kelas yang dipilih
$jadwal_quran_list = [];
if ($kelas_quran_selected) {
    $sql = "SELECT jq.*, kq.nama_kelas 
            FROM jadwal_quran jq 
            LEFT JOIN kelas_quran kq ON jq.kelas_quran_id = kq.id
            WHERE jq.kelas_quran_id = ?";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("i", $kelas_quran_selected);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $jadwal_quran_list[] = $row;
        }
    }
} else {
    // Jika tidak ada kelas yang dipilih, tampilkan semua jadwal
    $jadwal_quran_list = $jadwal_quran_all;
}

// Ambil kegiatan berdasarkan kamar
$kegiatan_list = [];
// Ambil kegiatan berdasarkan kamar dengan filter guru
if ($kamar_selected) {
    $sql = "SELECT jk.*, k.nama_kamar 
            FROM jadwal_kegiatan jk 
            LEFT JOIN kamar k ON jk.kamar_id = k.kamar_id
            WHERE jk.kamar_id = ? 
            AND jk.hari = ?";
            
    if ($guru_id) {
        $sql .= " AND k.guru_id = ?";
    }
    
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        if ($guru_id) {
            $stmt->bind_param("isi", $kamar_selected, $hari_ini, $guru_id);
        } else {
            $stmt->bind_param("is", $kamar_selected, $hari_ini);
        }
        $stmt->bind_param("is", $kamar_selected, $hari_ini);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $kegiatan_list[] = $row;
        }
    }
} else {
    // Jika tidak ada kamar yang dipilih, ambil semua kegiatan hari ini dengan filter guru
    $sql = "SELECT jk.*, k.nama_kamar 
            FROM jadwal_kegiatan jk 
            LEFT JOIN kamar k ON jk.kamar_id = k.kamar_id
            WHERE jk.hari = ?";
            
    if ($guru_id) {
        $sql .= " AND k.guru_id = ?";
    }
    
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        if ($guru_id) {
            $stmt->bind_param("si", $hari_ini, $guru_id);
        } else {
            $stmt->bind_param("s", $hari_ini);
        }
        $stmt->bind_param("s", $hari_ini);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $kegiatan_list[] = $row;
        }
    }
}

// Ambil data absensi jika ada filter
$absensi_data = [];
$murid_list = [];
$jadwal_detail = null;

if ($jadwal_selected) {
    // Ambil detail jadwal madin
    $sql = "SELECT j.*, km.nama_kelas 
            FROM jadwal_madin j 
            LEFT JOIN kelas_madin km ON j.kelas_madin_id = km.kelas_id
            WHERE j.jadwal_id = ?";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("i", $jadwal_selected);
        $stmt->execute();
        $result = $stmt->get_result();
        $jadwal_detail = $result->fetch_assoc();
        
        // Ambil murid berdasarkan kelas di jadwal
        if ($jadwal_detail && isset($jadwal_detail['kelas_madin_id'])) {
            $sql = "SELECT * FROM murid 
                    WHERE kelas_madin_id = ?";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param("i", $jadwal_detail['kelas_madin_id']);
                $stmt->execute();
                $result = $stmt->get_result();
                
                while ($row = $result->fetch_assoc()) {
                    $murid_list[] = $row;
                }
            }
        }
        
        // Ambil data absensi yang sudah ada
        $sql = "SELECT * FROM absensi 
                WHERE jadwal_madin_id = ? 
                AND tanggal = ?";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("is", $jadwal_selected, $tanggal_selected);
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                $absensi_data[$row['murid_id']] = $row;
            }
            
            // Cek apakah sudah ada absensi untuk jadwal ini
            $is_absensi_sudah_ada = (count($absensi_data) > 0);
        }
    }
}

// Data untuk absensi Quran
if ($jadwal_quran_selected) {
    // Ambil detail jadwal Quran
    $sql = "SELECT jq.*, kq.nama_kelas 
            FROM jadwal_quran jq 
            LEFT JOIN kelas_quran kq ON jq.kelas_quran_id = kq.id
            WHERE jq.id = ?";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("i", $jadwal_quran_selected);
        $stmt->execute();
        $result = $stmt->get_result();
        $jadwal_quran_detail = $result->fetch_assoc();
        
        // Ambil murid berdasarkan kelas Quran
        if ($jadwal_quran_detail && isset($jadwal_quran_detail['kelas_quran_id'])) {
            $sql = "SELECT * FROM murid 
                    WHERE kelas_quran_id = ?";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param("i", $jadwal_quran_detail['kelas_quran_id']);
                $stmt->execute();
                $result = $stmt->get_result();
                
                while ($row = $result->fetch_assoc()) {
                    $murid_quran_list[] = $row;
                }
            }
        }
        
        // Ambil data absensi yang sudah ada
        $sql = "SELECT * FROM absensi_quran 
                WHERE jadwal_quran_id = ? 
                AND tanggal = ?";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("is", $jadwal_quran_selected, $tanggal_quran_selected);
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                $absensi_quran_data[$row['murid_id']] = $row;
            }
            
            // Cek apakah sudah ada absensi untuk jadwal Quran ini
            $is_absensi_quran_sudah_ada = (count($absensi_quran_data) > 0);
        }
    }
}

// Data untuk absensi kegiatan
$absensi_kegiatan_data = [];
$murid_kamar_list = [];
$kegiatan_detail = null;

if ($kegiatan_selected) {
    // Ambil detail kegiatan dengan kondisi hari ini
    $sql = "SELECT jk.*, k.nama_kamar 
            FROM jadwal_kegiatan jk 
            LEFT JOIN kamar k ON jk.kamar_id = k.kamar_id
            WHERE jk.kegiatan_id = ? 
            AND jk.hari = ?";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("is", $kegiatan_selected, $hari_ini);
        $stmt->execute();
        $result = $stmt->get_result();
        $kegiatan_detail = $result->fetch_assoc();
        
        // Ambil murid berdasarkan kamar di kegiatan
        if ($kegiatan_detail && isset($kegiatan_detail['kamar_id'])) {
            $sql = "SELECT * FROM murid 
                    WHERE kamar_id = ?";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param("i", $kegiatan_detail['kamar_id']);
                $stmt->execute();
                $result = $stmt->get_result();
                
                while ($row = $result->fetch_assoc()) {
                    $murid_kamar_list[] = $row;
                }
            }
        }
        
        // Ambil data absensi kegiatan yang sudah ada
        $sql = "SELECT * FROM absensi_kegiatan 
                WHERE kegiatan_id = ? 
                AND tanggal = ?";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("is", $kegiatan_selected, $tanggal_kegiatan_selected);
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                $absensi_kegiatan_data[$row['murid_id']] = $row;
            }
            
            // Cek apakah sudah ada absensi untuk kegiatan ini
            $is_absensi_kegiatan_sudah_ada = (count($absensi_kegiatan_data) > 0);
        }
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

// Pindahkan navigasi setelah penanganan output
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
    <title>Absensi - Sistem Absensi Online</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css"
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
        
        [data-bs-theme="dark"] .card-header.bg-light {
            background-color: #2d2d2d !important;
            color: #f8f9fa !important;
        }

        [data-bs-theme="dark"] .card-header.bg-light .card-title {
            color: #f8f9fa !important;
        }
        
        /* Tab styling */
        .nav-tabs .nav-link.active {
            background-color: #0d6efd;
            color: white;
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
            <h2><i class="bi bi-clipboard-check me-2"></i> Absensi Online</h2>
                <button class="btn btn-primary p-0">
                    <?php if ($_SESSION['role'] == 'admin' || $_SESSION['role'] == 'staff'): ?>
                    <a href="https://docs.google.com/spreadsheets/d/1o8Q5i4Wk2x2o_kT9Hfaud6DYhbrSznrqX9EVQySfRr0/edit#gid=1913039132" 
                       target="_blank" 
                       class="btn btn-primary p-2">
                        <i class="bi bi-file-earmark-excel me-0"></i> Google Sheet
                    </a>
                    <?php endif; ?>
                </button>
        </div>
        
        <!-- Tab Navigasi -->
        <ul class="nav nav-tabs mb-3" id="absensiTab" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link <?= $active_tab == 'quran' ? 'active' : '' ?>" id="quran-tab" data-bs-toggle="tab" data-bs-target="#quran" type="button" role="tab">Qur'an</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link <?= $active_tab == 'pelajaran' ? 'active' : '' ?>" id="pelajaran-tab" data-bs-toggle="tab" data-bs-target="#pelajaran" type="button" role="tab">Madin</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link <?= $active_tab == 'kegiatan' ? 'active' : '' ?>" id="kegiatan-tab" data-bs-toggle="tab" data-bs-target="#kegiatan" type="button" role="tab">Kegiatan Kamar</button>
            </li>
        </ul>
        
        <div class="tab-content" id="absensiTabContent">
            <!-- Tab Qur'an -->
            <div class="tab-pane fade <?= $active_tab == 'quran' ? 'show active' : '' ?>" id="quran" role="tabpanel">
                <!-- Filter Form for Quran -->
                <div class="card mb-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="card-title mb-0"><i class="bi bi-funnel me-1"></i> Filter Absensi Qur'an</h5>
                    </div>
                    <div class="card-body">
                        <form method="GET" action="">
                            <input type="hidden" name="active_tab" value="quran">
                            <input type="hidden" name="filter_quran" value="1">
                            <div class="row">
                                <?php if ($_SESSION['role'] == 'admin' || $_SESSION['role'] == 'staff'): ?>
                                <div class="col-md-12 mb-3">
                                    <label class="form-label">Tanggal</label>
                                    <input type="date" class="form-control" name="tanggal_quran" value="<?= $tanggal_quran_selected ?>" required>
                                </div>
                                <?php endif; ?>
                                
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Kelas Qur'an</label>
                                    <select class="form-select select2-kelas-quran" name="kelas_quran_id" id="kelasQuranFilter">
                                        <option value="">-- Pilih Kelas Qur'an --</option>
                                        <?php foreach ($kelas_quran_list as $kelas): ?>
                                        <option value="<?= $kelas['id'] ?>" <?= $kelas_quran_selected == $kelas['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($kelas['nama_kelas']) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Majlis Qur'an</label>
                                    <select class="form-select" name="jadwal_quran_id" id="jadwalQuranFilter" required>
                                        <option value="">-- Pilih Majlis --</option>
                                        <?php foreach ($jadwal_quran_all as $jadwal): ?>
                                            <option value="<?= $jadwal['id'] ?>" 
                                                data-kelas="<?= $jadwal['kelas_quran_id'] ?>"
                                                <?= $jadwal_quran_selected == $jadwal['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($jadwal['mata_pelajaran']) ?> (<?= htmlspecialchars($jadwal['hari']) ?>)
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
            
                <!-- Untuk Form Absensi Qur'an -->
                <?php if ($jadwal_quran_selected && $jadwal_quran_detail): ?>
                <?php 
                    // Format tanggal untuk display
                    $tanggal_display_quran = date('d F Y', strtotime($tanggal_quran_selected));
                ?>
                <div class="card shadow-sm">
                    <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-book me-1"></i> 
                            Absensi Qur'an: <?= htmlspecialchars($jadwal_quran_detail['mata_pelajaran']) ?> 
                            (<?= htmlspecialchars($jadwal_quran_detail['nama_kelas']) ?>)
                        </h5>
                        <small style="font-size: 0.7rem;"><?= $tanggal_display_quran ?></small>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <input type="hidden" name="jadwal_quran_id" value="<?= $jadwal_quran_detail['id'] ?>">
                            <input type="hidden" name="tanggal_quran" value="<?= $tanggal_quran_selected ?>">
                            
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Nama Siswa</th>
                                            <th>Status Kehadiran</th>
                                            <th>Keterangan</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($murid_quran_list as $murid): 
                                            $absensi = isset($absensi_quran_data[$murid['murid_id']]) ? $absensi_quran_data[$murid['murid_id']] : [];
                                            $current_status = $absensi['status'] ?? 'Hadir';
                                        ?>
                                        <tr>
                                            <td><?= htmlspecialchars($murid['nama']) ?></td>
                                            <td>
                                                <div class="btn-group btn-group-sm" role="group" aria-label="Status kehadiran">
                                                    <input type="radio" class="btn-check" name="absensi_quran[<?= $murid['murid_id'] ?>][status]" 
                                                           id="quran_hadir_<?= $murid['murid_id'] ?>" value="Hadir" 
                                                           <?= $current_status == 'Hadir' ? 'checked' : '' ?> required>
                                                    <label class="btn btn-outline-success" for="quran_hadir_<?= $murid['murid_id'] ?>">
                                                        <i class="bi bi-check-circle"></i> Hadir
                                                    </label>
                
                                                    <input type="radio" class="btn-check" name="absensi_quran[<?= $murid['murid_id'] ?>][status]" 
                                                           id="quran_sakit_<?= $murid['murid_id'] ?>" value="Sakit" 
                                                           <?= $current_status == 'Sakit' ? 'checked' : '' ?>>
                                                    <label class="btn btn-outline-warning" for="quran_sakit_<?= $murid['murid_id'] ?>">
                                                        <i class="bi bi-activity"></i> Sakit
                                                    </label>
                
                                                    <input type="radio" class="btn-check" name="absensi_quran[<?= $murid['murid_id'] ?>][status]" 
                                                           id="quran_izin_<?= $murid['murid_id'] ?>" value="Izin" 
                                                           <?= $current_status == 'Izin' ? 'checked' : '' ?>>
                                                    <label class="btn btn-outline-info" for="quran_izin_<?= $murid['murid_id'] ?>">
                                                        <i class="bi bi-envelope"></i> Izin
                                                    </label>
                
                                                    <input type="radio" class="btn-check" name="absensi_quran[<?= $murid['murid_id'] ?>][status]" 
                                                           id="quran_alpa_<?= $murid['murid_id'] ?>" value="Alpa" 
                                                           <?= $current_status == 'Alpa' ? 'checked' : '' ?>>
                                                    <label class="btn btn-outline-danger" for="quran_alpa_<?= $murid['murid_id'] ?>">
                                                        <i class="bi bi-x-circle"></i> Alpa
                                                    </label>
                                                </div>
                                            </td>
                                            <td>
                                                <input type="text" class="form-control form-control-sm" 
                                                    name="absensi_quran[<?= $murid['murid_id'] ?>][keterangan]" 
                                                    value="<?= htmlspecialchars($absensi['keterangan'] ?? '') ?>"
                                                    placeholder="Keterangan (opsional)">
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <!-- Ganti bagian tombol simpan absensi Quran -->
                            <div class="d-grid mt-3">
                                <?php if ($is_absensi_quran_sudah_ada): ?>
                                    <button type="submit" name="perbarui_absensi_quran" class="btn btn-warning">
                                        <i class="bi bi-arrow-clockwise me-1"></i> Perbarui Absensi
                                    </button>
                                    <small class="text-muted mt-1 d-block">Absensi Quran untuk hari ini sudah pernah disimpan. Klik untuk memperbarui data.</small>
                                <?php else: ?>
                                    <button type="submit" name="simpan_absensi_quran" class="btn btn-secondary">
                                        <i class="bi bi-check-circle me-1"></i> Simpan Absensi
                                    </button>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Tab Pelajaran Madin -->
            <div class="tab-pane fade <?= $active_tab == 'pelajaran' ? 'show active' : '' ?>" id="pelajaran" role="tabpanel">
                <!-- Filter Form -->
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="card-title mb-0"><i class="bi bi-funnel me-1"></i> Filter Absensi Madin</h5>
                    </div>
                    <div class="card-body">
                        <form method="GET" action="">
                            <input type="hidden" name="active_tab" value="pelajaran">
                            <input type="hidden" name="filter" value="1">
                            <div class="row">
                                <?php if ($_SESSION['role'] == 'admin' || $_SESSION['role'] == 'staff'): ?>
                                <div class="col-md-12 mb-3">
                                    <label class="form-label">Tanggal</label>
                                    <input type="date" class="form-control" name="tanggal" value="<?= $tanggal_selected ?>" required>
                                </div>
                                <?php endif; ?>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Kelas Madin</label>
                                    <select class="form-select select2-kelas-madin" name="kelas_id" id="kelasFilter">
                                        <option value="">-- Pilih Kelas Madin --</option>
                                        <?php foreach ($kelas_list as $kelas): ?>
                                        <option value="<?= $kelas['kelas_id'] ?>" <?= $kelas_selected == $kelas['kelas_id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($kelas['nama_kelas']) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Pelajaran Madin</label>
                                    <select class="form-select" name="jadwal_id" id="jadwalFilter" required>
                                        <option value="">-- Pilih Pelajaran --</option>
                                        <?php foreach ($jadwal_list as $jadwal): ?>
                                        <option value="<?= $jadwal['jadwal_id'] ?>" 
                                            data-kelas="<?= $jadwal['kelas_madin_id'] ?>"
                                            <?= $jadwal_selected == $jadwal['jadwal_id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($jadwal['mata_pelajaran']) ?> (<?= htmlspecialchars($jadwal['nama_kelas']) ?>)
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="d-grid">
                                <button type="submit" name="filter" class="btn btn-secondary">Tampilkan</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Untuk Form Absensi Madin -->
                <?php if ($jadwal_selected && $jadwal_detail): ?>
                <?php 
                    // Format tanggal untuk display
                    $tanggal_display_madin = date('d F Y', strtotime($tanggal_selected));
                ?>
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-calendar-event me-1"></i> 
                            Absensi Madin : <?= htmlspecialchars($jadwal_detail['mata_pelajaran']) ?> 
                            (<?= htmlspecialchars($jadwal_detail['nama_kelas']) ?>)
                        </h5>
                        <small style="font-size: 0.7rem;"><?= $tanggal_display_madin ?></small>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <input type="hidden" name="jadwal_id" value="<?= $jadwal_detail['jadwal_id'] ?>">
                            <input type="hidden" name="tanggal" value="<?= $tanggal_selected ?>">
                            
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Nama Siswa</th>
                                            <th>Status Kehadiran</th>
                                            <th>Keterangan</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($murid_list as $murid): 
                                            $absensi = isset($absensi_data[$murid['murid_id']]) ? $absensi_data[$murid['murid_id']] : [];
                                            $current_status = $absensi['status'] ?? 'Hadir';
                                        ?>
                                        <tr>
                                            <td><?= htmlspecialchars($murid['nama']) ?></td>
                                            <td>
                                                <div class="btn-group btn-group-sm" role="group" aria-label="Status kehadiran">
                                                    <input type="radio" class="btn-check" name="absensi[<?= $murid['murid_id'] ?>][status]" 
                                                           id="madin_hadir_<?= $murid['murid_id'] ?>" value="Hadir" 
                                                           <?= $current_status == 'Hadir' ? 'checked' : '' ?> required>
                                                    <label class="btn btn-outline-success" for="madin_hadir_<?= $murid['murid_id'] ?>">
                                                        <i class="bi bi-check-circle"></i> Hadir
                                                    </label>
                
                                                    <input type="radio" class="btn-check" name="absensi[<?= $murid['murid_id'] ?>][status]" 
                                                           id="madin_sakit_<?= $murid['murid_id'] ?>" value="Sakit" 
                                                           <?= $current_status == 'Sakit' ? 'checked' : '' ?>>
                                                    <label class="btn btn-outline-warning" for="madin_sakit_<?= $murid['murid_id'] ?>">
                                                        <i class="bi bi-activity"></i> Sakit
                                                    </label>
                
                                                    <input type="radio" class="btn-check" name="absensi[<?= $murid['murid_id'] ?>][status]" 
                                                           id="madin_izin_<?= $murid['murid_id'] ?>" value="Izin" 
                                                           <?= $current_status == 'Izin' ? 'checked' : '' ?>>
                                                    <label class="btn btn-outline-info" for="madin_izin_<?= $murid['murid_id'] ?>">
                                                        <i class="bi bi-envelope"></i> Izin
                                                    </label>
                
                                                    <input type="radio" class="btn-check" name="absensi[<?= $murid['murid_id'] ?>][status]" 
                                                           id="madin_alpa_<?= $murid['murid_id'] ?>" value="Alpa" 
                                                           <?= $current_status == 'Alpa' ? 'checked' : '' ?>>
                                                    <label class="btn btn-outline-danger" for="madin_alpa_<?= $murid['murid_id'] ?>">
                                                        <i class="bi bi-x-circle"></i> Alpa
                                                    </label>
                                                </div>
                                            </td>
                                            <td>
                                                <input type="text" class="form-control form-control-sm" 
                                                    name="absensi[<?= $murid['murid_id'] ?>][keterangan]" 
                                                    value="<?= htmlspecialchars($absensi['keterangan'] ?? '') ?>"
                                                    placeholder="Keterangan (opsional)">
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <!-- Ganti bagian tombol simpan absensi Madin -->
                            <div class="d-grid mt-3">
                                <?php if ($is_absensi_sudah_ada): ?>
                                    <button type="submit" name="perbarui_absensi" class="btn btn-warning">
                                        <i class="bi bi-arrow-clockwise me-1"></i> Perbarui Absensi
                                    </button>
                                    <small class="text-muted mt-1 d-block">Absensi untuk hari ini sudah pernah disimpan. Klik untuk memperbarui data.</small>
                                <?php else: ?>
                                    <button type="submit" name="simpan_absensi" class="btn btn-secondary">
                                        <i class="bi bi-check-circle me-1"></i> Simpan Absensi
                                    </button>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Tab Kegiatan Kamar -->
            <div class="tab-pane fade <?= $active_tab == 'kegiatan' ? 'show active' : '' ?>" id="kegiatan" role="tabpanel">
                <!-- Filter Form for Kamar Activities -->
                <div class="card mb-4">
                    <div class="card-header bg-success text-white">
                        <h5 class="card-title mb-0"><i class="bi bi-funnel me-1"></i> Filter Absensi Kegiatan Kamar</h5>
                    </div>
                    <div class="card-body">
                        <form method="GET" action="">
                            <input type="hidden" name="active_tab" value="kegiatan">
                            <input type="hidden" name="filter" value="1">
                            <div class="row">
                                <?php if ($_SESSION['role'] == 'admin' || $_SESSION['role'] == 'staff'): ?>
                                <div class="col-md-12 mb-3">
                                    <label class="form-label">Tanggal Kegiatan</label>
                                    <input type="date" class="form-control" name="tanggal_kegiatan" value="<?= $tanggal_kegiatan_selected ?>" required>
                                </div>
                                <?php endif; ?>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Kamar</label>
                                    <select class="form-select select2-kamar" name="kamar_id" id="kamarFilter">
                                        <option value="">-- Pilih Kamar --</option>
                                        <?php foreach ($kamar_list as $kamar): ?>
                                        <option value="<?= $kamar['kamar_id'] ?>" <?= $kamar_selected == $kamar['kamar_id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($kamar['nama_kamar']) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Kegiatan</label>
                                    <select class="form-select select2-kegiatan" name="kegiatan_id" id="kegiatanFilter" required>
                                        <option value="">-- Pilih Kegiatan --</option>
                                        <?php foreach ($kegiatan_list as $kegiatan): ?>
                                        <option value="<?= $kegiatan['kegiatan_id'] ?>" 
                                            data-kamar="<?= $kegiatan['kamar_id'] ?>"
                                            <?= $kegiatan_selected == $kegiatan['kegiatan_id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($kegiatan['nama_kegiatan']) ?> (<?= htmlspecialchars($kegiatan['nama_kamar'] ?? '') ?>)
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

                <!-- Untuk Form Absensi Kegiatan Kamar -->
                <?php if ($kegiatan_selected && $kegiatan_detail): ?>
                <?php 
                    // Format tanggal untuk display
                    $tanggal_display_kegiatan = date('d F Y', strtotime($tanggal_kegiatan_selected));
                ?>
                <div class="card shadow-sm">
                    <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-clock me-1"></i> 
                            Absensi Kegiatan: <?= htmlspecialchars($kegiatan_detail['nama_kegiatan']) ?> 
                            (<?= htmlspecialchars($kegiatan_detail['nama_kamar']) ?>)
                        </h5>
                        <small style="font-size: 0.7rem;"><?= $tanggal_display_kegiatan ?></small>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <input type="hidden" name="kegiatan_id" value="<?= $kegiatan_detail['kegiatan_id'] ?>">
                            <input type="hidden" name="tanggal_kegiatan" value="<?= $tanggal_kegiatan_selected ?>">
                            
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Nama Siswa</th>
                                            <th>Status Kehadiran</th>
                                            <th>Keterangan</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($murid_kamar_list as $murid): 
                                            $absensi = isset($absensi_kegiatan_data[$murid['murid_id']]) ? $absensi_kegiatan_data[$murid['murid_id']] : [];
                                            $current_status = $absensi['status'] ?? 'Hadir';
                                        ?>
                                        <tr>
                                            <td><?= htmlspecialchars($murid['nama']) ?></td>
                                            <td>
                                                <div class="btn-group btn-group-sm" role="group" aria-label="Status kehadiran">
                                                    <input type="radio" class="btn-check" name="absensi_kegiatan[<?= $murid['murid_id'] ?>][status]" 
                                                           id="kegiatan_hadir_<?= $murid['murid_id'] ?>" value="Hadir" 
                                                           <?= $current_status == 'Hadir' ? 'checked' : '' ?> required>
                                                    <label class="btn btn-outline-success" for="kegiatan_hadir_<?= $murid['murid_id'] ?>">
                                                        <i class="bi bi-check-circle"></i> Hadir
                                                    </label>
                
                                                    <input type="radio" class="btn-check" name="absensi_kegiatan[<?= $murid['murid_id'] ?>][status]" 
                                                           id="kegiatan_sakit_<?= $murid['murid_id'] ?>" value="Sakit" 
                                                           <?= $current_status == 'Sakit' ? 'checked' : '' ?>>
                                                    <label class="btn btn-outline-warning" for="kegiatan_sakit_<?= $murid['murid_id'] ?>">
                                                        <i class="bi bi-activity"></i> Sakit
                                                    </label>
                
                                                    <input type="radio" class="btn-check" name="absensi_kegiatan[<?= $murid['murid_id'] ?>][status]" 
                                                           id="kegiatan_izin_<?= $murid['murid_id'] ?>" value="Izin" 
                                                           <?= $current_status == 'Izin' ? 'checked' : '' ?>>
                                                    <label class="btn btn-outline-info" for="kegiatan_izin_<?= $murid['murid_id'] ?>">
                                                        <i class="bi bi-envelope"></i> Izin
                                                    </label>
                
                                                    <input type="radio" class="btn-check" name="absensi_kegiatan[<?= $murid['murid_id'] ?>][status]" 
                                                           id="kegiatan_alpa_<?= $murid['murid_id'] ?>" value="Alpa" 
                                                           <?= $current_status == 'Alpa' ? 'checked' : '' ?>>
                                                    <label class="btn btn-outline-danger" for="kegiatan_alpa_<?= $murid['murid_id'] ?>">
                                                        <i class="bi bi-x-circle"></i> Alpa
                                                    </label>
                                                </div>
                                            </td>
                                            <td>
                                                <input type="text" class="form-control form-control-sm" 
                                                    name="absensi_kegiatan[<?= $murid['murid_id'] ?>][keterangan]" 
                                                    value="<?= htmlspecialchars($absensi['keterangan'] ?? '') ?>"
                                                    placeholder="Keterangan (opsional)">
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <!-- Ganti bagian tombol simpan absensi Kegiatan -->
                            <div class="d-grid mt-3">
                                <?php if ($is_absensi_kegiatan_sudah_ada): ?>
                                    <button type="submit" name="perbarui_absensi_kegiatan" class="btn btn-warning">
                                        <i class="bi bi-arrow-clockwise me-1"></i> Perbarui Absensi
                                    </button>
                                    <small class="text-muted mt-1 d-block">Absensi kegiatan untuk hari ini sudah pernah disimpan. Klik untuk memperbarui data.</small>
                                <?php else: ?>
                                    <button type="submit" name="simpan_absensi_kegiatan" class="btn btn-secondary">
                                        <i class="bi bi-check-circle me-1"></i> Simpan Absensi
                                    </button>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Tambahkan JavaScript Select2 -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
    
        // Fungsi untuk memuat data default jika AJAX gagal
        function loadDefaultData(selector, data) {
            $(selector).empty();
            $(selector).append('<option value="">-- Pilih --</option>');
            data.forEach(function(item) {
                $(selector).append('<option value="' + item.id + '">' + item.text + '</option>');
            });
        }
        
        // Data default untuk fallback
        $(document).ready(function() {
            // Coba ambil data default dari options yang sudah ada
            var kelasMadinOptions = [];
            $('#kelasFilter option').each(function() {
                if ($(this).val()) {
                    kelasMadinOptions.push({
                        id: $(this).val(),
                        text: $(this).text()
                    });
                }
            });
            
            // Jika ada data, gunakan sebagai fallback
            if (kelasMadinOptions.length > 1) {
                $('.select2-kelas-madin').select2({
                    theme: 'bootstrap-5',
                    placeholder: 'Pilih Kelas Madin',
                    allowClear: true,
                    width: '100%',
                    data: kelasMadinOptions
                });
            } else {
                // Gunakan AJAX
                initSelect2Filter('.select2-kelas-madin', 'kelas_madin', 'Ketik untuk Cari Kelas Madin...');
            }
            
            // Coba ambil data default dari options yang sudah ada
            var kelasQuranOptions = [];
            $('#kelasFilter option').each(function() {
                if ($(this).val()) {
                    kelasQuranOptions.push({
                        id: $(this).val(),
                        text: $(this).text()
                    });
                }
            });
            
            // Jika ada data, gunakan sebagai fallback
            if (kelasQuranOptions.length > 1) {
                $('.select2-kelas-quran').select2({
                    theme: 'bootstrap-5',
                    placeholder: 'Pilih Kelas Quran',
                    allowClear: true,
                    width: '100%',
                    data: kelasQuranOptions
                });
            } else {
                // Gunakan AJAX
                initSelect2Filter('.select2-kelas-quran', 'kelas_quran', 'Ketik untuk Cari Kelas Quran...');
            }
            
            // Coba ambil data default dari options yang sudah ada
            var kamarOptions = [];
            $('#kamarFilter option').each(function() {
                if ($(this).val()) {
                    kamarOptions.push({
                        id: $(this).val(),
                        text: $(this).text()
                    });
                }
            });
            
            // Jika ada data, gunakan sebagai fallback
            if (kamarOptions.length > 1) {
                $('.select2-kamar').select2({
                    theme: 'bootstrap-5',
                    placeholder: 'Pilih Kamar',
                    allowClear: true,
                    width: '100%',
                    data: kamarOptions
                });
            } else {
                // Gunakan AJAX
                initSelect2Filter('.select2-kamar', 'kamar', 'Ketik untuk Cari Kelas Kamar...');
            }
            
        });
        
        // Inisialisasi Select2 untuk filter dropdown - VERSI DIPERBAIKI
        function initSelect2Filter(selector, type, placeholder) {
            try {
                if ($(selector).length === 0) {
                    console.error('Elemen ' + selector + ' tidak ditemukan');
                    return;
                }
                
                // Jika sudah ada data options, gunakan data lokal dulu
                var existingOptions = $(selector).html();
                if (existingOptions && existingOptions.length > 0) {
                    $(selector).select2({
                        theme: 'bootstrap-5',
                        placeholder: placeholder,
                        allowClear: true,
                        width: '100%',
                        dropdownCssClass: "select2-dropdown-wide"
                    });
                } else {
                    // Gunakan AJAX untuk mengambil data
                    $(selector).select2({
                        theme: 'bootstrap-5',
                        placeholder: placeholder,
                        allowClear: true,
                        width: '100%',
                        dropdownCssClass: "select2-dropdown-wide",
                        ajax: {
                            url: 'absensi.php',
                            dataType: 'json',
                            delay: 250,
                            data: function (params) {
                                return {
                                    q: params.term || '',
                                    type: type
                                };
                            },
                            processResults: function (data) {
                                if (!data || !data.items || data.items.length === 0) {
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
                    });
                }
                
                // Trigger pencarian saat dropdown dibuka
                $(selector).on('select2:open', function () {
                    setTimeout(function() {
                        var $search = $('.select2-search__field');
                        if ($search.length > 0 && $search.val() === '') {
                            $search.trigger('input');
                        }
                    }, 100);
                });
                
            } catch (error) {
                console.error('Error inisialisasi Select2 untuk ' + selector + ':', error);
            }
        }
        
        $(document).ready(function() {
            
            // Filter jadwal berdasarkan kelas
            $('#kelasFilter').change(function() {
                const kelasId = $(this).val();
                $('#jadwalFilter option').each(function() {
                    if (!kelasId || $(this).data('kelas') == kelasId) {
                        $(this).show();
                    } else {
                        $(this).hide();
                        if ($(this).prop('selected')) {
                            $(this).prop('selected', false);
                        }
                    }
                });
            });
            
            // Filter jadwal Quran berdasarkan kelas Quran
            $('#kelasQuranFilter').change(function() {
                const kelasId = $(this).val();
                $('#jadwalQuranFilter option').each(function() {
                    if (!kelasId || $(this).data('kelas') == kelasId) {
                        $(this).show();
                    } else {
                        $(this).hide();
                        if ($(this).prop('selected')) {
                            $(this).prop('selected', false);
                        }
                    }
                });
            });
            
            // Filter kegiatan berdasarkan kamar
            $('#kamarFilter').change(function() {
                const kamarId = $(this).val();
                $('#kegiatanFilter option').each(function() {
                    if (!kamarId || $(this).data('kamar') == kamarId) {
                        $(this).show();
                    } else {
                        $(this).hide();
                        if ($(this).prop('selected')) {
                            $(this).prop('selected', false);
                        }
                    }
                });
                
                // AJAX untuk memuat kegiatan berdasarkan kamar yang dipilih
                if (kamarId) {
                    $.ajax({
                        url: 'absensi.php',
                        type: 'GET',
                        data: {kamar_id: kamarId, ajax: 1},
                        success: function(response) {
                            $('#kegiatanFilter').html(response);
                            // Re-inisialisasi Select2 setelah update options
                            $('#kegiatanFilter').select2({
                                theme: 'bootstrap-5',
                                placeholder: 'Pilih Kegiatan',
                                allowClear: true,
                                width: '100%'
                            });
                        },
                        error: function() {
                            console.error('Gagal memuat data kegiatan');
                        }
                    });
                }
            });
            
            // Trigger change saat halaman dimuat
            $('#kelasFilter').trigger('change');
            $('#kelasQuranFilter').trigger('change');
            $('#kamarFilter').trigger('change');
            
        });
        
        // Script untuk mengontrol tampilan loading
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
        
        function confirmSimpanAbsensi(jenis) {
            const countAlpa = document.querySelectorAll('select[value="Alpa"]').length;
            if (countAlpa > 0) {
                return confirm(`Ada ${countAlpa} siswa yang alpa. Notifikasi WhatsApp akan dikirim ke wali murid dan guru. Lanjutkan?`);
            }
            return true;
        }
        
        // Fungsi untuk toggle absensi otomatis guru
        function toggleAbsensiOtomatis(checkbox) {
            const isActive = checkbox.checked ? 1 : 0;
            
            fetch('absensi_otomatis_guru.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=toggle_otomatis&status=' + isActive
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    alert('Pengaturan absensi otomatis berhasil diubah');
                } else {
                    alert('Gagal mengubah pengaturan');
                    checkbox.checked = !checkbox.checked;
                }
            });
        }
        
        // Nonaktifkan tombol simpan setelah berhasil menyimpan
        function disableSaveButton(button) {
            button.disabled = true;
            button.innerHTML = '<i class="bi bi-check-circle me-1"></i> Sudah Disimpan';
            button.classList.remove('btn-secondary');
            button.classList.add('btn-success');
        }
        
        // Cek status tombol saat halaman dimuat
        $(document).ready(function() {
            // Jika ada pesan success, nonaktifkan tombol simpan
            if ($('.alert-success').length > 0) {
                $('button[name^="simpan_absensi"]').each(function() {
                    disableSaveButton(this);
                });
            }
        });
        
        // Tambahkan fungsi untuk refresh status tombol setelah filter
        function refreshButtonStatus() {
            // Fungsi ini bisa dipanggil setelah filter berubah
            // Untuk menyesuaikan status tombol berdasarkan data yang sudah ada
            console.log("Refresh button status...");
        }
        
        // Panggil saat filter berubah
        $('#kelasFilter, #jadwalFilter, #tanggal').change(function() {
            setTimeout(refreshButtonStatus, 500);
        });
        
        $('#kelasQuranFilter, #jadwalQuranFilter, #tanggal_quran').change(function() {
            setTimeout(refreshButtonStatus, 500);
        });
        
        $('#kamarFilter, #kegiatanFilter, #tanggal_kegiatan').change(function() {
            setTimeout(refreshButtonStatus, 500);
        });
        
        // Fungsi untuk membuka tab berdasarkan hash URL
        function activateTabFromHash() {
            const hash = window.location.hash.substring(1); // Hapus #
            
            if (hash) {
                const tabMap = {
                    'pelajaran': 'pelajaran-tab',
                    'quran': 'quran-tab',
                    'kegiatan': 'kegiatan-tab'
                };
                
                const tabButtonId = tabMap[hash];
                if (tabButtonId) {
                    const tabButton = document.getElementById(tabButtonId);
                    if (tabButton) {
                        const tab = new bootstrap.Tab(tabButton);
                        tab.show();
                    }
                }
            }
        }
        
        // Jalankan saat DOM siap
        document.addEventListener('DOMContentLoaded', function() {
            // Aktifkan tab dari URL hash
            activateTabFromHash();
            
            // Auto-submit form jika ada parameter filter
            const urlParams = new URLSearchParams(window.location.search);
            
            if (urlParams.has('filter_quran') || urlParams.has('filter')) {
                setTimeout(() => {
                    // Temukan form aktif di tab yang sedang dibuka
                    const activeTab = document.querySelector('.tab-pane.active');
                    if (activeTab) {
                        const form = activeTab.querySelector('form');
                        if (form) {
                            // Cek apakah form sudah punya data (jika tidak, submit)
                            const hasData = activeTab.querySelector('table tbody tr');
                            if (!hasData) {
                                form.submit();
                            }
                        }
                    }
                }, 500);
            }
        });
        
    </script>
</body>
</html>
<?php ob_end_flush(); ?>