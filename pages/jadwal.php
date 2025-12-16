<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Mulai session dan include koneksi database
session_start();
require_once '../includes/config.php';

// Filter data untuk role guru
$guru_id = null;
if (isset($_SESSION['role']) && $_SESSION['role'] === 'guru' && isset($_SESSION['guru_id'])) {
    $guru_id = $_SESSION['guru_id'];
}

// Fungsi check_auth sederhana
function check_auth() {
    return isset($_SESSION['user_id']) || isset($_SESSION['guru_id']);
}

if (!check_auth()) {
    header("Location: ../index.php");
    exit();
}

// Fungsi untuk mengurutkan hari
function urutkanHari($a, $b) {
    $urutan = array('Senin' => 1, 'Selasa' => 2, 'Rabu' => 3, 'Kamis' => 4, 'Jumat' => 5, 'Sabtu' => 6, 'Ahad' => 7);
    $a_hari = $a['hari'];
    $b_hari = $b['hari'];
    
    if (!isset($urutan[$a_hari])) return 1;
    if (!isset($urutan[$b_hari])) return -1;
    
    return $urutan[$a_hari] - $urutan[$b_hari];
}

$message = '';
$current_jadwal = null;
$current_kegiatan = null;
$current_jadwal_quran = null;

// INISIALISASI VARIABEL UNTUK DATA TABEL - PERBAIKAN
$jadwal_list = [];
$jadwal_quran_list = [];
$kegiatan_list = [];
$kelas_list = [];
$kelas_quran_list = [];
$kamar_list = [];

// AMBIL DATA GURU UNTUK DROPDOWN DENGAN FILTER - DENGAN PENGECEKAN ERROR
$sql_guru_dropdown = "SELECT guru_id, nama, no_hp FROM guru WHERE 1=1";
if ($guru_id) {
    $sql_guru_dropdown .= " AND guru_id = ?";
} else {
    // Pastikan hanya guru yang aktif/valid yang ditampilkan
    $sql_guru_dropdown .= " AND guru_id IS NOT NULL";
}
$sql_guru_dropdown .= " ORDER BY nama";

$stmt_guru_dropdown = $conn->prepare($sql_guru_dropdown);
if ($stmt_guru_dropdown) {
    if ($guru_id) {
        $stmt_guru_dropdown->bind_param("i", $guru_id);
    }
    $stmt_guru_dropdown->execute();
    $result_guru_dropdown = $stmt_guru_dropdown->get_result();
    $guru_dropdown_list = [];
    if ($result_guru_dropdown && $result_guru_dropdown->num_rows > 0) {
        while ($row = $result_guru_dropdown->fetch_assoc()) {
            $guru_dropdown_list[] = $row;
        }
    }
    $stmt_guru_dropdown->close();
} else {
    error_log("Error preparing guru dropdown query: " . $conn->error);
    $message = 'danger|Terjadi kesalahan dalam mengambil data guru';
}

// AMBIL DATA JADWAL MADIN - DENGAN PENGECEKAN ERROR
$sql_jadwal = "SELECT j.*, g.nama as nama_guru, g.no_hp as no_hp_guru, k.nama_kelas 
               FROM jadwal_madin j 
               LEFT JOIN guru g ON j.guru_id = g.guru_id 
               LEFT JOIN kelas_madin k ON j.kelas_madin_id = k.kelas_id 
               WHERE 1=1";

if ($guru_id) {
    $sql_jadwal .= " AND j.guru_id = ?";
}

$sql_jadwal .= " ORDER BY FIELD(j.hari, 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Ahad'), j.jam_mulai";

$stmt_jadwal = $conn->prepare($sql_jadwal);
if ($stmt_jadwal) {
    if ($guru_id) {
        $stmt_jadwal->bind_param("i", $guru_id);
    }
    $stmt_jadwal->execute();
    $result_jadwal = $stmt_jadwal->get_result();

    if ($result_jadwal && $result_jadwal->num_rows > 0) {
        while ($row = $result_jadwal->fetch_assoc()) {
            $jadwal_list[] = $row;
        }
    }
    $stmt_jadwal->close();
} else {
    error_log("Error preparing jadwal query: " . $conn->error);
    $message = 'danger|Terjadi kesalahan dalam mengambil data jadwal';
}

// AMBIL DATA KEGIATAN - DENGAN PENGECEKAN ERROR
$sql_kegiatan = "SELECT kg.*, g.nama as nama_guru, g.no_hp as no_hp_guru, k.nama_kamar 
                 FROM jadwal_kegiatan kg 
                 LEFT JOIN guru g ON kg.guru_id = g.guru_id 
                 LEFT JOIN kamar k ON kg.kamar_id = k.kamar_id 
                 WHERE 1=1";

if ($guru_id) {
    $sql_kegiatan .= " AND kg.guru_id = ?";
}

$sql_kegiatan .= " ORDER BY FIELD(kg.hari, 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Ahad'), kg.jam_mulai";

$stmt_kegiatan = $conn->prepare($sql_kegiatan);
if ($stmt_kegiatan) {
    if ($guru_id) {
        $stmt_kegiatan->bind_param("i", $guru_id);
    }
    $stmt_kegiatan->execute();
    $result_kegiatan = $stmt_kegiatan->get_result();

    if ($result_kegiatan && $result_kegiatan->num_rows > 0) {
        while ($row = $result_kegiatan->fetch_assoc()) {
            $kegiatan_list[] = $row;
        }
    }
    $stmt_kegiatan->close();
} else {
    error_log("Error preparing kegiatan query: " . $conn->error);
    $message = 'danger|Terjadi kesalahan dalam mengambil data kegiatan';
}

// AMBIL DATA KELAS MADIN UNTUK DROPDOWN
$sql_kelas = "SELECT * FROM kelas_madin ORDER BY nama_kelas";
$stmt_kelas = $conn->prepare($sql_kelas);
if ($stmt_kelas) {
    $stmt_kelas->execute();
    $result_kelas = $stmt_kelas->get_result();
    if ($result_kelas && $result_kelas->num_rows > 0) {
        while ($row = $result_kelas->fetch_assoc()) {
            $kelas_list[] = $row;
        }
    }
    $stmt_kelas->close();
} else {
    error_log("Error preparing kelas query: " . $conn->error);
}

// AMBIL DATA KELAS QURAN UNTUK DROPDOWN
$sql_kelas_quran = "SELECT * FROM kelas_quran ORDER BY nama_kelas";
$stmt_kelas_quran = $conn->prepare($sql_kelas_quran);
if ($stmt_kelas_quran) {
    $stmt_kelas_quran->execute();
    $result_kelas_quran = $stmt_kelas_quran->get_result();
    if ($result_kelas_quran && $result_kelas_quran->num_rows > 0) {
        while ($row = $result_kelas_quran->fetch_assoc()) {
            $kelas_quran_list[] = $row;
        }
    }
    $stmt_kelas_quran->close();
} else {
    error_log("Error preparing kelas quran query: " . $conn->error);
}

// AMBIL DATA KAMAR UNTUK DROPDOWN
$sql_kamar = "SELECT * FROM kamar ORDER BY nama_kamar";
$stmt_kamar = $conn->prepare($sql_kamar);
if ($stmt_kamar) {
    $stmt_kamar->execute();
    $result_kamar = $stmt_kamar->get_result();
    if ($result_kamar && $result_kamar->num_rows > 0) {
        while ($row = $result_kamar->fetch_assoc()) {
            $kamar_list[] = $row;
        }
    }
    $stmt_kamar->close();
} else {
    error_log("Error preparing kamar query: " . $conn->error);
}

// AMBIL DATA JADWAL QURAN - TAMBAHKAN QUERY INI
$sql_jadwal_quran = "SELECT jq.*, g.nama as nama_guru, g.no_hp as no_hp_guru, kq.nama_kelas 
                     FROM jadwal_quran jq 
                     LEFT JOIN guru g ON jq.guru_id = g.guru_id 
                     LEFT JOIN kelas_quran kq ON jq.kelas_quran_id = kq.id 
                     WHERE 1=1";

if ($guru_id) {
    $sql_jadwal_quran .= " AND jq.guru_id = ?";
}

$sql_jadwal_quran .= " ORDER BY FIELD(jq.hari, 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Ahad'), jq.jam_mulai";

$stmt_jadwal_quran = $conn->prepare($sql_jadwal_quran);
if ($stmt_jadwal_quran) {
    if ($guru_id) {
        $stmt_jadwal_quran->bind_param("i", $guru_id);
    }
    $stmt_jadwal_quran->execute();
    $result_jadwal_quran = $stmt_jadwal_quran->get_result();

    if ($result_jadwal_quran && $result_jadwal_quran->num_rows > 0) {
        while ($row = $result_jadwal_quran->fetch_assoc()) {
            $jadwal_quran_list[] = $row;
        }
    }
    $stmt_jadwal_quran->close();
} else {
    error_log("Error preparing jadwal quran query: " . $conn->error);
    $message = 'danger|Terjadi kesalahan dalam mengambil data jadwal Quran';
}

// PROSES CRUD UNTUK JADWAL QURAN - TAMBAHKAN BAGIAN INI
if (isset($_POST['tambah_jadwal_quran'])) {
    $guru_id_post = $guru_id ?: $_POST['guru_id'];
    $mata_pelajaran = $_POST['mata_pelajaran'];
    $kelas_quran_id = $_POST['kelas_quran_id'];
    $jam_mulai = $_POST['jam_mulai'];
    $jam_selesai = $_POST['jam_selesai'];
    
    if (isset($_POST['hari']) && is_array($_POST['hari'])) {
        foreach ($_POST['hari'] as $hari) {
            $sql = "INSERT INTO jadwal_quran (guru_id, hari, mata_pelajaran, kelas_quran_id, jam_mulai, jam_selesai) 
                    VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param("ississ", $guru_id_post, $hari, $mata_pelajaran, $kelas_quran_id, $jam_mulai, $jam_selesai);
                if (!$stmt->execute()) {
                    $message = 'danger|Gagal menambahkan jadwal Quran: ' . $stmt->error;
                }
                $stmt->close();
            }
        }
        if (!isset($message)) {
            $message = 'success|Jadwal Quran berhasil ditambahkan';
            header("Location: jadwal.php");
            exit();
        }
    } else {
        $message = 'danger|Pilih minimal satu hari';
    }
}

// EDIT JADWAL QURAN
if (isset($_GET['edit_jadwal_quran'])) {
    $id = $_GET['edit_jadwal_quran'];
    $sql = "SELECT * FROM jadwal_quran WHERE id = ?";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $current_jadwal_quran = $result->fetch_assoc();
        $stmt->close();
    }
}

// UPDATE JADWAL QURAN
if (isset($_POST['edit_jadwal_quran'])) {
    $id = $_POST['id'];
    $guru_id_post = $guru_id ?: $_POST['guru_id'];
    $hari = $_POST['hari'];
    $mata_pelajaran = $_POST['mata_pelajaran'];
    $kelas_quran_id = $_POST['kelas_quran_id'];
    $jam_mulai = $_POST['jam_mulai'];
    $jam_selesai = $_POST['jam_selesai'];
    
    $sql = "UPDATE jadwal_quran SET guru_id = ?, hari = ?, mata_pelajaran = ?, kelas_quran_id = ?, jam_mulai = ?, jam_selesai = ? 
            WHERE id = ?";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("ississi", $guru_id_post, $hari, $mata_pelajaran, $kelas_quran_id, $jam_mulai, $jam_selesai, $id);
        if ($stmt->execute()) {
            $message = 'success|Jadwal Quran berhasil diupdate';
            header("Location: jadwal.php");
            exit();
        } else {
            $message = 'danger|Gagal mengupdate jadwal Quran: ' . $stmt->error;
        }
        $stmt->close();
    }
}

// HAPUS JADWAL QURAN
if (isset($_GET['hapus_jadwal_quran'])) {
    $id = $_GET['hapus_jadwal_quran'];
    $sql = "DELETE FROM jadwal_quran WHERE id = ?";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $message = 'success|Jadwal Quran berhasil dihapus';
        } else {
            $message = 'danger|Gagal menghapus jadwal Quran: ' . $stmt->error;
        }
        $stmt->close();
        header("Location: jadwal.php");
        exit();
    }
}

// PROSES CRUD UNTUK JADWAL MADIN - DENGAN PENGECEKAN ERROR
if (isset($_POST['tambah_jadwal'])) {
    $guru_id_post = $guru_id ?: $_POST['guru_id'];
    $mata_pelajaran = $_POST['mata_pelajaran'];
    $kelas_id = $_POST['kelas_id'];
    $jam_mulai = $_POST['jam_mulai'];
    $jam_selesai = $_POST['jam_selesai'];
    
    if (isset($_POST['hari']) && is_array($_POST['hari'])) {
        $success_count = 0;
        foreach ($_POST['hari'] as $hari) {
            $sql = "INSERT INTO jadwal_madin (guru_id, hari, mata_pelajaran, kelas_madin_id, jam_mulai, jam_selesai) 
                    VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param("ississ", $guru_id_post, $hari, $mata_pelajaran, $kelas_id, $jam_mulai, $jam_selesai);
                if ($stmt->execute()) {
                    $success_count++;
                } else {
                    error_log("Error executing jadwal insert: " . $stmt->error);
                }
                $stmt->close();
            } else {
                error_log("Error preparing jadwal insert: " . $conn->error);
            }
        }
        if ($success_count > 0) {
            $message = 'success|Jadwal berhasil ditambahkan (' . $success_count . ' hari)';
            header("Location: jadwal.php");
            exit();
        } else {
            $message = 'danger|Gagal menambahkan jadwal';
        }
    } else {
        $message = 'danger|Pilih minimal satu hari';
    }
}

// EDIT JADWAL MADIN
if (isset($_GET['edit'])) {
    $id = $_GET['edit'];
    $sql = "SELECT * FROM jadwal_madin WHERE jadwal_id = ?";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $current_jadwal = $result->fetch_assoc();
        $stmt->close();
    } else {
        error_log("Error preparing jadwal edit query: " . $conn->error);
        $message = 'danger|Terjadi kesalahan dalam mengambil data jadwal';
    }
}

// UPDATE JADWAL MADIN
if (isset($_POST['edit_jadwal'])) {
    error_log("DEBUG - Data yang dikirim:");
    error_log("guru_id_post: " . ($guru_id ?: $_POST['guru_id']));
    error_log("hari: " . $_POST['hari']);
    error_log("mata_pelajaran: " . $_POST['mata_pelajaran']);
    
    $id = $_POST['id'];
    $guru_id_post = $guru_id ?: $_POST['guru_id'];
    $hari = $_POST['hari'];
    $mata_pelajaran = $_POST['mata_pelajaran'];
    $kelas_id = $_POST['kelas_id'];
    $jam_mulai = $_POST['jam_mulai'];
    $jam_selesai = $_POST['jam_selesai'];
    
    // VALIDASI: Pastikan guru_id ada di tabel guru
    if ($guru_id_post) {
        $check_guru = "SELECT guru_id FROM guru WHERE guru_id = ?";
        $stmt_check = $conn->prepare($check_guru);
        $stmt_check->bind_param("i", $guru_id_post);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();
        
        if ($result_check->num_rows === 0) {
            $message = 'danger|Guru tidak valid. Silakan pilih guru yang tersedia.';
            header("Location: jadwal.php");
            exit();
        }
        $stmt_check->close();
    }
    
    $sql = "UPDATE jadwal_madin SET guru_id = ?, hari = ?, mata_pelajaran = ?, kelas_madin_id = ?, jam_mulai = ?, jam_selesai = ? 
            WHERE jadwal_id = ?";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        // Jika guru_id_post NULL, gunakan NULL
        if (empty($guru_id_post)) {
            $stmt->bind_param("sssissi", $guru_id_post, $hari, $mata_pelajaran, $kelas_id, $jam_mulai, $jam_selesai, $id);
        } else {
            $stmt->bind_param("ississi", $guru_id_post, $hari, $mata_pelajaran, $kelas_id, $jam_mulai, $jam_selesai, $id);
        }
        
        if ($stmt->execute()) {
            $message = 'success|Jadwal berhasil diupdate';
            header("Location: jadwal.php");
            exit();
        } else {
            error_log("Error executing jadwal update: " . $stmt->error);
            $message = 'danger|Gagal mengupdate jadwal: ' . $stmt->error;
        }
        $stmt->close();
    } else {
        error_log("Error preparing jadwal update: " . $conn->error);
        $message = 'danger|Terjadi kesalahan dalam mengupdate jadwal';
    }
}

// HAPUS JADWAL MADIN
if (isset($_GET['hapus'])) {
    $id = $_GET['hapus'];
    $sql = "DELETE FROM jadwal_madin WHERE jadwal_id = ?";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $message = 'success|Jadwal berhasil dihapus';
        } else {
            error_log("Error executing jadwal delete: " . $stmt->error);
            $message = 'danger|Gagal menghapus jadwal: ' . $stmt->error;
        }
        $stmt->close();
    } else {
        error_log("Error preparing jadwal delete: " . $conn->error);
        $message = 'danger|Terjadi kesalahan dalam menghapus jadwal';
    }
    header("Location: jadwal.php");
    exit();
}

// PROSES CRUD UNTUK KEGIATAN - DENGAN PENGECEKAN ERROR
if (isset($_POST['tambah_kegiatan'])) {
    $guru_id_post = $guru_id ?: $_POST['guru_id'];
    $nama_kegiatan = $_POST['nama_kegiatan'];
    $kamar_id = $_POST['kamar_id'];
    $jam_mulai = $_POST['jam_mulai'];
    $jam_selesai = $_POST['jam_selesai'];
    
    if (isset($_POST['hari']) && is_array($_POST['hari'])) {
        $success_count = 0;
        foreach ($_POST['hari'] as $hari) {
            $sql = "INSERT INTO jadwal_kegiatan (guru_id, hari, nama_kegiatan, kamar_id, jam_mulai, jam_selesai) 
                    VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param("ississ", $guru_id_post, $hari, $nama_kegiatan, $kamar_id, $jam_mulai, $jam_selesai);
                if ($stmt->execute()) {
                    $success_count++;
                } else {
                    error_log("Error executing kegiatan insert: " . $stmt->error);
                }
                $stmt->close();
            } else {
                error_log("Error preparing kegiatan insert: " . $conn->error);
            }
        }
        if ($success_count > 0) {
            $message = 'success|Kegiatan berhasil ditambahkan (' . $success_count . ' hari)';
            header("Location: jadwal.php");
            exit();
        } else {
            $message = 'danger|Gagal menambahkan kegiatan';
        }
    } else {
        $message = 'danger|Pilih minimal satu hari';
    }
}

// EDIT KEGIATAN
if (isset($_GET['edit_kegiatan'])) {
    $id = $_GET['edit_kegiatan'];
    $sql = "SELECT * FROM jadwal_kegiatan WHERE kegiatan_id = ?";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $current_kegiatan = $result->fetch_assoc();
        $stmt->close();
    } else {
        error_log("Error preparing kegiatan edit query: " . $conn->error);
        $message = 'danger|Terjadi kesalahan dalam mengambil data kegiatan';
    }
}

// UPDATE KEGIATAN
if (isset($_POST['edit_kegiatan'])) {
    $id = $_POST['id'];
    $guru_id_post = $guru_id ?: $_POST['guru_id'];
    $hari = $_POST['hari'];
    $nama_kegiatan = $_POST['nama_kegiatan'];
    $kamar_id = $_POST['kamar_id'];
    $jam_mulai = $_POST['jam_mulai'];
    $jam_selesai = $_POST['jam_selesai'];
    
    $sql = "UPDATE jadwal_kegiatan SET guru_id = ?, hari = ?, nama_kegiatan = ?, kamar_id = ?, jam_mulai = ?, jam_selesai = ? 
            WHERE kegiatan_id = ?";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("ississi", $guru_id_post, $hari, $nama_kegiatan, $kamar_id, $jam_mulai, $jam_selesai, $id);
        if ($stmt->execute()) {
            $message = 'success|Kegiatan berhasil diupdate';
            header("Location: jadwal.php");
            exit();
        } else {
            error_log("Error executing kegiatan update: " . $stmt->error);
            $message = 'danger|Gagal mengupdate kegiatan: ' . $stmt->error;
        }
        $stmt->close();
    } else {
        error_log("Error preparing kegiatan update: " . $conn->error);
        $message = 'danger|Terjadi kesalahan dalam mengupdate kegiatan';
    }
}

// HAPUS KEGIATAN
if (isset($_GET['hapus_kegiatan'])) {
    $id = $_GET['hapus_kegiatan'];
    $sql = "DELETE FROM jadwal_kegiatan WHERE kegiatan_id = ?";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $message = 'success|Kegiatan berhasil dihapus';
        } else {
            error_log("Error executing kegiatan delete: " . $stmt->error);
            $message = 'danger|Gagal menghapus kegiatan: ' . $stmt->error;
        }
        $stmt->close();
    } else {
        error_log("Error preparing kegiatan delete: " . $conn->error);
        $message = 'danger|Terjadi kesalahan dalam menghapus kegiatan';
    }
    header("Location: jadwal.php");
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
    <title>Jadwal - Sistem Absensi Online</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.5/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/fixedheader/3.3.2/css/fixedHeader.bootstrap5.min.css">
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
        
        .card {
            margin-bottom: 2rem;
        }
        
        /* PERBAIKAN UTAMA: Atur lebar tabel dan kolom dengan lebih baik */
        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        
        /* GANTI aturan lebar kolom dengan yang lebih fleksibel */
        #jadwalQuranTable th,
        #jadwalQuranTable td,
        #jadwalTable th,
        #jadwalTable td,
        #kegiatanTable th,
        #kegiatanTable td {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            min-width: 100px; /* Berikan minimum width yang reasonable */
        }
        
        /* Atur lebar yang lebih proporsional */
        #jadwalQuranTable th:nth-child(1),
        #jadwalQuranTable td:nth-child(1),
        #jadwalTable th:nth-child(1),
        #jadwalTable td:nth-child(1),
        #kegiatanTable th:nth-child(1),
        #kegiatanTable td:nth-child(1) {
            min-width: 80px; /* Hari */
            max-width: 100px;
        }
        
        #jadwalQuranTable th:nth-child(2),
        #jadwalQuranTable td:nth-child(2),
        #jadwalTable th:nth-child(2),
        #jadwalTable td:nth-child(2),
        #kegiatanTable th:nth-child(2),
        #kegiatanTable td:nth-child(2) {
            min-width: 120px; /* Tingkatan/Nama Kegiatan */
            max-width: 200px;
        }
        
        #jadwalQuranTable th:nth-child(3),
        #jadwalQuranTable td:nth-child(3),
        #jadwalTable th:nth-child(3),
        #jadwalTable td:nth-child(3),
        #kegiatanTable th:nth-child(3),
        #kegiatanTable td:nth-child(3) {
            min-width: 120px; /* Kelas/Kamar */
            max-width: 180px;
        }
        
        #jadwalQuranTable th:nth-child(4),
        #jadwalQuranTable td:nth-child(4),
        #jadwalTable th:nth-child(4),
        #jadwalTable td:nth-child(4),
        #kegiatanTable th:nth-child(4),
        #kegiatanTable td:nth-child(4),
        #jadwalQuranTable th:nth-child(5),
        #jadwalQuranTable td:nth-child(5),
        #jadwalTable th:nth-child(5),
        #jadwalTable td:nth-child(5),
        #kegiatanTable th:nth-child(5),
        #kegiatanTable td:nth-child(5) {
            min-width: 90px; /* Jam Mulai & Selesai */
            max-width: 110px;
        }
        
        #jadwalQuranTable th:nth-child(6),
        #jadwalQuranTable td:nth-child(6),
        #jadwalTable th:nth-child(6),
        #jadwalTable td:nth-child(6),
        #kegiatanTable th:nth-child(6),
        #kegiatanTable td:nth-child(6) {
            min-width: 150px; /* Pengajar/Pembina */
            max-width: 250px;
        }
        
        #jadwalQuranTable th:nth-child(7),
        #jadwalQuranTable td:nth-child(7),
        #jadwalTable th:nth-child(7),
        #jadwalTable td:nth-child(7),
        #kegiatanTable th:nth-child(7),
        #kegiatanTable td:nth-child(7) {
            min-width: 80px; /* Kontak WA */
            max-width: 100px;
            text-align: center;
        }
        
        #jadwalQuranTable th:nth-child(8),
        #jadwalQuranTable td:nth-child(8),
        #jadwalTable th:nth-child(8),
        #jadwalTable td:nth-child(8),
        #kegiatanTable th:nth-child(8),
        #kegiatanTable td:nth-child(8) {
            min-width: 100px; /* Aksi */
            max-width: 120px;
            text-align: center;
        }
        
        /* PERBAIKAN UTAMA: Untuk mobile - pastikan header tetap sync dengan konten */
        @media (max-width: 768px) {
            .table-responsive {
                border: 1px solid #dee2e6;
                border-radius: 0.375rem;
            }
            
            /* PERBAIKAN: Gunakan container yang lebih lebar untuk mobile */
            #jadwalQuranTable,
            #jadwalTable,
            #kegiatanTable {
                min-width: 1000px;
                table-layout: fixed;
            }
            
            /* PERBAIKAN: Pastikan header dan body tetap sync saat scroll */
            .dataTables_wrapper .dataTables_scroll {
                position: relative;
            }
            
            .dataTables_wrapper .dataTables_scrollHead {
                position: relative;
                overflow: hidden !important;
            }
            
            .dataTables_wrapper .dataTables_scrollBody {
                -webkit-overflow-scrolling: touch;
                max-height: none !important;
            }
            
            /* PERBAIKAN UTAMA: Pastikan header tetap terlihat saat scroll horizontal */
            .dataTables_scrollHeadInner {
                width: auto !important;
            }
            
            .dataTables_scrollHeadInner table {
                margin-bottom: 0 !important;
                width: 100% !important;
            }
            
            /* PERBAIKAN: Hilangkan border-bottom yang tidak perlu */
            .dataTables_scrollHead {
                border-bottom: none;
            }
            
            /* PERBAIKAN: Atur tinggi header untuk konsistensi */
            .dataTables_scrollHead thead th {
                height: 45px;
                vertical-align: middle;
            }
        }
    
        /* PERBAIKAN: Untuk desktop - pastikan tabel memanfaatkan lebar penuh */
        @media (min-width: 769px) {
            #jadwalQuranTable,
            #jadwalTable,
            #kegiatanTable {
                min-width: auto;
                /* HAPUS BARIS INI: table-layout: fixed; */
                table-layout: auto; /* GUNAKAN INI */
                width: 100% !important;
            }
        }
    
        /* PERBAIKAN UTAMA: Tambahan untuk DataTables scroll */
        .dataTables_scroll {
            width: 100%;
        }
        
        .dataTables_scrollHead {
            width: 100% !important;
            overflow: hidden !important;
        }
        
        .dataTables_scrollBody {
            width: 100% !important;
        }
        
        /* PERBAIKAN UTAMA: Pastikan header dan body sejajar */
        .dataTables_scrollHead table,
        .dataTables_scrollBody table {
            width: 100% !important;
            margin: 0 !important;
            table-layout: fixed;
        }
        
        /* PERBAIKAN: Atur tampilan header yang fixed */
        .dataTables_scrollHead {
            background-color: #f8f9fa;
        }
        
        body.dark-mode .dataTables_scrollHead {
            background-color: #212529;
        }
        
        .btn-action {
            margin-right: 5px;
        }
        .modal-content {
            border-radius: 10px;
        }
        .multi-hari {
            max-height: 200px;
            overflow-y: auto;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 10px;
        }
        
        @media (max-width: 768px) {
            .d-flex.justify-content-between {
                flex-direction: column;
            }
            .btn {
                margin-bottom: 10px;
            }
        }
        
        /* Tambahan untuk improve UX */
        .table th {
            border-top: none;
            font-weight: 600;
        }
        
        .btn-action {
            margin-right: 5px;
        }
        
        /* Loading improvement */
        #loading-overlay {
            background: rgba(255, 255, 255, 0.95);
        }
        
        body.dark-mode #loading-overlay {
            background: rgba(0, 0, 0, 0.95);
        }
        
        /* PERBAIKAN: Handle text panjang dengan lebih baik */
        .dt-body-nowrap {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        /* Berikan ruang lebih untuk konten panjang */
        .table td {
            position: relative;
        }
        
        /* Tooltip untuk konten yang terpotong */
        .table td:hover::after {
            content: attr(data-title);
            position: absolute;
            left: 0;
            top: 100%;
            background: rgba(0,0,0,0.8);
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            z-index: 1000;
            white-space: normal;
            max-width: 300px;
        }
        
        /* Untuk mobile - pastikan scroll smooth */
        @media (max-width: 768px) {
            .table-responsive {
                border: 1px solid #dee2e6;
                border-radius: 0.375rem;
            }
            
            #jadwalQuranTable,
            #jadwalTable,
            #kegiatanTable {
                min-width: 800px; /* Kurangi dari 1000px ke 800px */
                table-layout: auto;
            }
            
            /* Pastikan konten terlihat */
            .table td, .table th {
                padding: 8px 4px;
                font-size: 0.875rem;
            }
        }
        
        /* Sembunyikan kolom aksi berdasarkan role */
        <?php if (!in_array($_SESSION['role'], ['admin', 'staff'])): ?>
        .hide-for-non-admin {
            display: none !important;
        }
        <?php endif; ?>
        
        /* Sembunyikan kolom aksi berdasarkan role - PERBAIKI INI */
        <?php if (!in_array($_SESSION['role'], ['admin', 'staff'])): ?>
        /* Untuk non-admin/staff, sembunyikan kolom aksi dan sesuaikan lebar kolom lainnya */
        #jadwalQuranTable th:nth-child(8),
        #jadwalQuranTable td:nth-child(8),
        #jadwalTable th:nth-child(8),
        #jadwalTable td:nth-child(8),
        #kegiatanTable th:nth-child(8),
        #kegiatanTable td:nth-child(8) {
            display: none;
        }
        
        /* Ketika kolom aksi dihapus, berikan lebih banyak ruang untuk kolom lain */
        #jadwalQuranTable th:nth-child(2),
        #jadwalQuranTable td:nth-child(2),
        #jadwalTable th:nth-child(2),
        #jadwalTable td:nth-child(2),
        #kegiatanTable th:nth-child(2),
        #kegiatanTable td:nth-child(2) {
            min-width: 150px;
        }
        
        #jadwalQuranTable th:nth-child(3),
        #jadwalQuranTable td:nth-child(3),
        #jadwalTable th:nth-child(3),
        #jadwalTable td:nth-child(3),
        #kegiatanTable th:nth-child(3),
        #kegiatanTable td:nth-child(3) {
            min-width: 150px;
        }
        
        #jadwalQuranTable th:nth-child(6),
        #jadwalQuranTable td:nth-child(6),
        #jadwalTable th:nth-child(6),
        #jadwalTable td:nth-child(6),
        #kegiatanTable th:nth-child(6),
        #kegiatanTable td:nth-child(6) {
            min-width: 150px;
        }
        <?php endif; ?>
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
            <h2 class="mb-4"><i class="bi bi-journal-bookmark me-2"></i> Manajemen Jadwal</h2>
        </div>
        
        <!-- Tab Navigasi -->
        <ul class="nav nav-tabs mb-3" id="myTab" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="jadwalKelasQuran-tab" data-bs-toggle="tab" data-bs-target="#jadwalKelasQuran" type="button" role="tab">Kelas Qur'an</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="jadwalKelasMadin-tab" data-bs-toggle="tab" data-bs-target="#jadwalKelasMadin" type="button" role="tab">Kelas Madin</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="jadwalKegiatan-tab" data-bs-toggle="tab" data-bs-target="#jadwalKegiatan" type="button" role="tab">Kegiatan</button>
            </li>
        </ul>
        
        <div class="tab-content" id="myTabContent">
        
            <!-- Card Jadwal Qur'an -->
            <div class="tab-pane fade show active" id="jadwalKelasQuran" role="tabpanel">
                <div class="card shadow-sm">
                    <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
                        <h3 class="card-title mb-0"><i class="bi bi-book me-2"></i> Jadwal Kelas Qur'an</h3>
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
                            <button class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#jadwalQuranModal" id="btnTambahJadwalQuran">
                                <i class="bi bi-plus-circle me-1"></i> Tambah
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="jadwalQuranTable" class="table table-hover table-striped">
                                <thead class="table-light">
                                    <tr>
                                        <th>Hari</th>
                                        <th>Tingkatan</th>
                                        <th>Kelas Qur'an</th>
                                        <th>Jam Mulai</th>
                                        <th>Jam Selesai</th>
                                        <th>Pengajar</th>
                                        <th>Kontak WA</th>
                                        <th width="100" class="hide-for-non-admin">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Dalam loop foreach untuk setiap tabel, tambahkan data-title -->
                                    <?php foreach ($jadwal_quran_list as $jadwal): ?>
                                    <tr>
                                        <td data-title="Hari"><?= htmlspecialchars($jadwal['hari']) ?></td>
                                        <td data-title="Tingkatan"><?= htmlspecialchars($jadwal['mata_pelajaran']) ?></td>
                                        <td data-title="Kelas Qur'an"><?= htmlspecialchars($jadwal['nama_kelas']) ?></td>
                                        <td data-title="Jam Mulai"><?= htmlspecialchars($jadwal['jam_mulai']) ?></td>
                                        <td data-title="Jam Selesai"><?= htmlspecialchars($jadwal['jam_selesai']) ?></td>
                                        <td data-title="Pengajar"><?= htmlspecialchars($jadwal['nama_guru'] ?? 'Belum ada pengajar') ?></td>
                                        <td>
                                            <?php if (!empty($jadwal['no_hp_guru'])): ?>
                                            <a href="https://wa.me/<?= htmlspecialchars($jadwal['no_hp_guru']) ?>" 
                                               class="btn btn-sm btn-success" target="_blank" title="Hubungi via WhatsApp">
                                                <i class="bi bi-whatsapp"></i>
                                            </a>
                                            <?php else: ?>
                                            <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="hide-for-non-admin">
                                            <a href="?edit_jadwal_quran=<?= $jadwal['id'] ?>" class="btn btn-sm btn-primary btn-action">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <a href="?hapus_jadwal_quran=<?= $jadwal['id'] ?>" class="btn btn-sm btn-danger" 
                                               onclick="return confirm('Yakin ingin menghapus jadwal ini?')">
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
            
            <!-- Card Jadwal Madin -->
            <div class="tab-pane fade" id="jadwalKelasMadin" role="tabpanel">
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <h3 class="card-title mb-0"><i class="bi bi-calendar me-2"></i> Jadwal Pelajaran Madin</h3>
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
                            <button class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#jadwalModal" id="btnTambah">
                                <i class="bi bi-plus-circle me-1"></i> Tambah
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="jadwalTable" class="table table-hover table-striped">
                                <thead class="table-light">
                                    <tr>
                                        <th>Hari</th>
                                        <th>Tingkatan</th>
                                        <th>Kelas Madin</th>
                                        <th>Jam Mulai</th>
                                        <th>Jam Selesai</th>
                                        <th>Pengajar</th>
                                        <th>Kontak WA</th>
                                        <th width="100" class="hide-for-non-admin">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($jadwal_list as $jadwal): ?>
                                    <tr>
                                        <td data-title="Hari"><?= htmlspecialchars($jadwal['hari']) ?></td>
                                        <td data-title="Tingkatan"><?= htmlspecialchars($jadwal['mata_pelajaran']) ?></td>
                                        <td data-title="Kelas Qur'an"><?= htmlspecialchars($jadwal['nama_kelas']) ?></td>
                                        <td data-title="Jam Mulai"><?= htmlspecialchars($jadwal['jam_mulai']) ?></td>
                                        <td data-title="Jam Selesai"><?= htmlspecialchars($jadwal['jam_selesai']) ?></td>
                                        <td data-title="Pengajar"><?= htmlspecialchars($jadwal['nama_guru'] ?? 'Belum ada pengajar') ?></td>
                                        <td>
                                            <?php if (!empty($jadwal['no_hp_guru'])): ?>
                                            <a href="https://wa.me/<?= htmlspecialchars($jadwal['no_hp_guru']) ?>" 
                                               class="btn btn-sm btn-success" target="_blank" title="Hubungi via WhatsApp">
                                                <i class="bi bi-whatsapp"></i>
                                            </a>
                                            <?php else: ?>
                                            <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="hide-for-non-admin">
                                            <a href="?edit=<?= $jadwal['jadwal_id'] ?>" class="btn btn-sm btn-primary btn-action">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <a href="?hapus=<?= $jadwal['jadwal_id'] ?>" class="btn btn-sm btn-danger" 
                                               onclick="return confirm('Yakin ingin menghapus jadwal ini?')">
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
            
            <!-- Card Jadwal Kegiatan -->
            <div class="tab-pane fade" id="jadwalKegiatan" role="tabpanel">
                <div class="card shadow-sm">
                    <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                        <h3 class="card-title mb-0"><i class="bi bi-clock me-2"></i> Jadwal Kegiatan Kamar</h3>
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
                            <button class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#kegiatanModal" id="btnTambahKegiatan">
                                <i class="bi bi-plus-circle me-1"></i> Tambah
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="kegiatanTable" class="table table-hover table-striped">
                                <thead class="table-light">
                                    <tr>
                                        <th>Hari</th>
                                        <th>Nama Kegiatan</th>
                                        <th>Kamar</th>
                                        <th>Jam Mulai</th>
                                        <th>Jam Selesai</th>
                                        <th>Pembina</th>
                                        <th>Kontak WA</th>
                                        <th width="100" class="hide-for-non-admin">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($kegiatan_list as $kegiatan): ?>
                                    <tr>
                                        <td data-title="Hari"><?= htmlspecialchars($jadwal['hari']) ?></td>
                                        <td data-title="Tingkatan"><?= htmlspecialchars($jadwal['mata_pelajaran']) ?></td>
                                        <td data-title="Kelas Qur'an"><?= htmlspecialchars($jadwal['nama_kelas']) ?></td>
                                        <td data-title="Jam Mulai"><?= htmlspecialchars($jadwal['jam_mulai']) ?></td>
                                        <td data-title="Jam Selesai"><?= htmlspecialchars($jadwal['jam_selesai']) ?></td>
                                        <td data-title="Pembina"><?= htmlspecialchars($jadwal['nama_guru'] ?? 'Belum ada pembina') ?></td>
                                        <td>
                                            <?php if (!empty($kegiatan['no_hp_guru'])): ?>
                                            <a href="https://wa.me/<?= htmlspecialchars($kegiatan['no_hp_guru']) ?>" 
                                               class="btn btn-sm btn-success" target="_blank" title="Hubungi via WhatsApp">
                                                <i class="bi bi-whatsapp"></i>
                                            </a>
                                            <?php else: ?>
                                            <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="hide-for-non-admin">
                                            <a href="?edit_kegiatan=<?= $kegiatan['kegiatan_id'] ?>" class="btn btn-sm btn-primary btn-action">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <a href="?hapus_kegiatan=<?= $kegiatan['kegiatan_id'] ?>" class="btn btn-sm btn-danger" 
                                               onclick="return confirm('Yakin ingin menghapus kegiatan ini?')">
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
    
    <!-- Modal Tambah/Edit Jadwal Quran -->
    <div class="modal fade" id="jadwalQuranModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <?= $current_jadwal_quran ? 'Edit Jadwal Quran' : 'Tambah Jadwal Quran' ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <?php if ($current_jadwal_quran): ?>
                        <input type="hidden" name="id" value="<?= $current_jadwal_quran['id'] ?>">
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <label class="form-label">Guru Pengajar</label>
                            <select class="form-select" name="guru_id" <?= $guru_id ? 'disabled' : '' ?>>
                                <option value="">-- Pilih Guru Pengajar --</option>
                                <?php foreach ($guru_dropdown_list as $guru): ?>
                                    <option value="<?= $guru['guru_id'] ?>" 
                                        <?= ($current_jadwal_quran['guru_id'] ?? '') == $guru['guru_id'] ? 'selected' : '' ?>
                                        <?= $guru_id && $guru['guru_id'] == $guru_id ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($guru['nama']) ?> 
                                        <?= !empty($guru['no_hp']) ? ' - ' . $guru['no_hp'] : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($guru_id): ?>
                            <input type="hidden" name="guru_id" value="<?= $guru_id ?>">
                            <small class="text-muted">Anda otomatis ditetapkan sebagai guru pengajar</small>
                            <?php endif; ?>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Hari <span class="text-danger">*</span></label>
                            <?php if ($current_jadwal_quran): ?>
                                <div class="single-hari">
                                    <select class="form-select" name="hari" required>
                                        <option value="Senin" <?= ($current_jadwal_quran['hari'] ?? '') === 'Senin' ? 'selected' : '' ?>>Senin</option>
                                        <option value="Selasa" <?= ($current_jadwal_quran['hari'] ?? '') === 'Selasa' ? 'selected' : '' ?>>Selasa</option>
                                        <option value="Rabu" <?= ($current_jadwal_quran['hari'] ?? '') === 'Rabu' ? 'selected' : '' ?>>Rabu</option>
                                        <option value="Kamis" <?= ($current_jadwal_quran['hari'] ?? '') === 'Kamis' ? 'selected' : '' ?>>Kamis</option>
                                        <option value="Jumat" <?= ($current_jadwal_quran['hari'] ?? '') === 'Jumat' ? 'selected' : '' ?>>Jumat</option>
                                        <option value="Sabtu" <?= ($current_jadwal_quran['hari'] ?? '') === 'Sabtu' ? 'selected' : '' ?>>Sabtu</option>
                                        <option value="Ahad" <?= ($current_jadwal_quran['hari'] ?? '') === 'Ahad' ? 'selected' : '' ?>>Ahad</option>
                                    </select>
                                </div>
                            <?php else: ?>
                                <div class="multi-hari">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="hari[]" value="Senin" id="quran_senin">
                                        <label class="form-check-label" for="quran_senin">Senin</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="hari[]" value="Selasa" id="quran_selasa">
                                        <label class="form-check-label" for="quran_selasa">Selasa</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="hari[]" value="Rabu" id="quran_rabu">
                                        <label class="form-check-label" for="quran_rabu">Rabu</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="hari[]" value="Kamis" id="quran_kamis">
                                        <label class="form-check-label" for="quran_kamis">Kamis</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="hari[]" value="Jumat" id="quran_jumat">
                                        <label class="form-check-label" for="quran_jumat">Jum'at</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="hari[]" value="Sabtu" id="quran_sabtu">
                                        <label class="form-check-label" for="quran_sabtu">Sabtu</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="hari[]" value="Ahad" id="quran_ahad">
                                        <label class="form-check-label" for="quran_ahad">Ahad</label>
                                    </div>
                                </div>
                                <small class="text-muted">Pilih satu atau lebih hari</small>
                            <?php endif; ?>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Mata Pelajaran <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="mata_pelajaran" 
                                value="<?= $current_jadwal_quran['mata_pelajaran'] ?? '' ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Kelas Qur'an <span class="text-danger">*</span></label>
                            <select class="form-select" name="kelas_quran_id" required>
                                <option value="">-- Pilih Kelas Qur'an --</option>
                                <?php foreach ($kelas_quran_list as $kelas): ?>
                                <option value="<?= $kelas['id'] ?>" 
                                    <?= ($current_jadwal_quran['kelas_quran_id'] ?? '') == $kelas['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($kelas['nama_kelas']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Jam Mulai <span class="text-danger">*</span></label>
                                <input type="time" class="form-control" name="jam_mulai" 
                                    value="<?= $current_jadwal_quran['jam_mulai'] ?? '' ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Jam Selesai <span class="text-danger">*</span></label>
                                <input type="time" class="form-control" name="jam_selesai" 
                                    value="<?= $current_jadwal_quran['jam_selesai'] ?? '' ?>" required>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="<?= $current_jadwal_quran ? 'edit_jadwal_quran' : 'tambah_jadwal_quran' ?>" class="btn btn-primary">
                            <?= $current_jadwal_quran ? 'Simpan Perubahan' : 'Simpan' ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Tambah/Edit Jadwal Madin -->
    <div class="modal fade" id="jadwalModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <?= $current_jadwal ? 'Edit Jadwal' : 'Tambah Jadwal' ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <?php if ($current_jadwal): ?>
                        <input type="hidden" name="id" value="<?= $current_jadwal['jadwal_id'] ?>">
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <label class="form-label">Guru Pengajar</label>
                            <select class="form-select" name="guru_id" <?= $guru_id ? 'disabled' : '' ?> required>
                                <option value="">-- Pilih Guru Pengajar --</option>
                                <?php foreach ($guru_dropdown_list as $guru): ?>
                                    <option value="<?= $guru['guru_id'] ?>" 
                                        <?= ($current_jadwal['guru_id'] ?? '') == $guru['guru_id'] ? 'selected' : '' ?>
                                        <?= $guru_id && $guru['guru_id'] == $guru_id ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($guru['nama']) ?> 
                                        <?= !empty($guru['no_hp']) ? ' - ' . $guru['no_hp'] : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($guru_id): ?>
                            <input type="hidden" name="guru_id" value="<?= $guru_id ?>">
                            <small class="text-muted">Anda otomatis ditetapkan sebagai guru pengajar</small>
                            <?php endif; ?>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Hari <span class="text-danger">*</span></label>
                            <?php if ($current_jadwal): ?>
                                <div class="single-hari">
                                    <select class="form-select" name="hari" required>
                                        <option value="Sabtu" <?= ($current_jadwal['hari'] ?? '') === 'Sabtu' ? 'selected' : '' ?>>Sabtu</option>
                                        <option value="Ahad" <?= ($current_jadwal['hari'] ?? '') === 'Ahad' ? 'selected' : '' ?>>Ahad</option>
                                        <option value="Senin" <?= ($current_jadwal['hari'] ?? '') === 'Senin' ? 'selected' : '' ?>>Senin</option>
                                        <option value="Selasa" <?= ($current_jadwal['hari'] ?? '') === 'Selasa' ? 'selected' : '' ?>>Selasa</option>
                                        <option value="Rabu" <?= ($current_jadwal['hari'] ?? '') === 'Rabu' ? 'selected' : '' ?>>Rabu</option>
                                        <option value="Kamis" <?= ($current_jadwal['hari'] ?? '') === 'Kamis' ? 'selected' : '' ?>>Kamis</option>
                                        <option value="Jumat" <?= ($current_jadwal['hari'] ?? '') === 'Jumat' ? 'selected' : '' ?>>Jum'at</option>
                                    </select>
                                </div>
                            <?php else: ?>
                                <div class="multi-hari">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="hari[]" value="Sabtu" id="hari_sabtu">
                                        <label class="form-check-label" for="hari_sabtu">Sabtu</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="hari[]" value="Ahad" id="hari_ahad">
                                        <label class="form-check-label" for="hari_ahad">Ahad</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="hari[]" value="Senin" id="hari_senin">
                                        <label class="form-check-label" for="hari_senin">Senin</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="hari[]" value="Selasa" id="hari_selasa">
                                        <label class="form-check-label" for="hari_selasa">Selasa</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="hari[]" value="Rabu" id="hari_rabu">
                                        <label class="form-check-label" for="hari_rabu">Rabu</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="hari[]" value="Kamis" id="hari_kamis">
                                        <label class="form-check-label" for="hari_kamis">Kamis</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="hari[]" value="Jumat" id="hari_jumat">
                                        <label class="form-check-label" for="hari_jumat">Jum'at</label>
                                    </div>
                                </div>
                                <small class="text-muted">Pilih satu atau lebih hari</small>
                            <?php endif; ?>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Mata Pelajaran <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="mata_pelajaran" 
                                value="<?= $current_jadwal['mata_pelajaran'] ?? '' ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Kelas <span class="text-danger">*</span></label>
                            <select class="form-select" name="kelas_id" required>
                                <option value="">-- Pilih Kelas Madin --</option>
                                <?php foreach ($kelas_list as $kelas): ?>
                                <option value="<?= $kelas['kelas_id'] ?>" 
                                    <?= ($current_jadwal['kelas_madin_id'] ?? '') == $kelas['kelas_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($kelas['nama_kelas']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Jam Mulai <span class="text-danger">*</span></label>
                                <input type="time" class="form-control" name="jam_mulai" 
                                    value="<?= $current_jadwal['jam_mulai'] ?? '' ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Jam Selesai <span class="text-danger">*</span></label>
                                <input type="time" class="form-control" name="jam_selesai" 
                                    value="<?= $current_jadwal['jam_selesai'] ?? '' ?>" required>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="<?= $current_jadwal ? 'edit_jadwal' : 'tambah_jadwal' ?>" class="btn btn-primary">
                            <?= $current_jadwal ? 'Simpan Perubahan' : 'Simpan' ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
        
    <!-- Modal Tambah/Edit Kegiatan -->
    <div class="modal fade" id="kegiatanModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <?= $current_kegiatan ? 'Edit Kegiatan' : 'Tambah Kegiatan' ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <?php if ($current_kegiatan): ?>
                        <input type="hidden" name="id" value="<?= $current_kegiatan['kegiatan_id'] ?>">
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <label class="form-label">Pembina Kamar</label>
                            <select class="form-select" name="guru_id" <?= $guru_id ? 'disabled' : '' ?>>
                                <option value="">-- Pilih Pembina --</option>
                                <?php foreach ($guru_dropdown_list as $guru): ?>
                                    <option value="<?= $guru['guru_id'] ?>" 
                                        <?= ($current_kegiatan['guru_id'] ?? '') == $guru['guru_id'] ? 'selected' : '' ?>
                                        <?= $guru_id && $guru['guru_id'] == $guru_id ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($guru['nama']) ?> 
                                        <?= !empty($guru['no_hp']) ? ' - ' . $guru['no_hp'] : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($guru_id): ?>
                            <input type="hidden" name="guru_id" value="<?= $guru_id ?>">
                            <small class="text-muted">Anda otomatis ditetapkan sebagai pembina</small>
                            <?php endif; ?>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Hari <span class="text-danger">*</span></label>
                            <?php if ($current_kegiatan): ?>
                                <div class="single-hari">
                                    <select class="form-select" name="hari" required>
                                        <option value="Sabtu" <?= ($current_kegiatan['hari'] ?? '') === 'Sabtu' ? 'selected' : '' ?>>Sabtu</option>
                                        <option value="Ahad" <?= ($current_kegiatan['hari'] ?? '') === 'Ahad' ? 'selected' : '' ?>>Ahad</option>
                                        <option value="Senin" <?= ($current_kegiatan['hari'] ?? '') === 'Senin' ? 'selected' : '' ?>>Senin</option>
                                        <option value="Selasa" <?= ($current_kegiatan['hari'] ?? '') === 'Selasa' ? 'selected' : '' ?>>Selasa</option>
                                        <option value="Rabu" <?= ($current_kegiatan['hari'] ?? '') === 'Rabu' ? 'selected' : '' ?>>Rabu</option>
                                        <option value="Kamis" <?= ($current_kegiatan['hari'] ?? '') === 'Kamis' ? 'selected' : '' ?>>Kamis</option>
                                        <option value="Jumat" <?= ($current_kegiatan['hari'] ?? '') === 'Jumat' ? 'selected' : '' ?>>Jum'at</option>
                                    </select>
                                </div>
                            <?php else: ?>
                                <div class="multi-hari">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="hari[]" value="Sabtu" id="kegiatan_sabtu">
                                        <label class="form-check-label" for="kegiatan_sabtu">Sabtu</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="hari[]" value="Ahad" id="kegiatan_ahad">
                                        <label class="form-check-label" for="kegiatan_ahad">Ahad</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="hari[]" value="Senin" id="kegiatan_senin">
                                        <label class="form-check-label" for="kegiatan_senin">Senin</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="hari[]" value="Selasa" id="kegiatan_selasa">
                                        <label class="form-check-label" for="kegiatan_selasa">Selasa</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="hari[]" value="Rabu" id="kegiatan_rabu">
                                        <label class="form-check-label" for="kegiatan_rabu">Rabu</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="hari[]" value="Kamis" id="kegiatan_kamis">
                                        <label class="form-check-label" for="kegiatan_kamis">Kamis</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="hari[]" value="Jumat" id="kegiatan_jumat">
                                        <label class="form-check-label" for="kegiatan_jumat">Jum'at</label>
                                    </div>
                                </div>
                                <small class="text-muted">Pilih satu atau lebih hari</small>
                            <?php endif; ?>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Nama Kegiatan <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="nama_kegiatan" 
                                value="<?= $current_kegiatan['nama_kegiatan'] ?? '' ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Kamar <span class="text-danger">*</span></label>
                            <select class="form-select" name="kamar_id" required>
                                <option value="">-- Pilih Kamar --</option>
                                <?php foreach ($kamar_list as $kamar): ?>
                                <option value="<?= $kamar['kamar_id'] ?>" 
                                    <?= ($current_kegiatan['kamar_id'] ?? '') == $kamar['kamar_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($kamar['nama_kamar']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Jam Mulai <span class="text-danger">*</span></label>
                                <input type="time" class="form-control" name="jam_mulai" 
                                    value="<?= $current_kegiatan['jam_mulai'] ?? '' ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Jam Selesai <span class="text-danger">*</span></label>
                                <input type="time" class="form-control" name="jam_selesai" 
                                    value="<?= $current_kegiatan['jam_selesai'] ?? '' ?>" required>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="<?= $current_kegiatan ? 'edit_kegiatan' : 'tambah_kegiatan' ?>" class="btn btn-primary">
                            <?= $current_kegiatan ? 'Simpan Perubahan' : 'Simpan' ?>
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
    <script src="https://cdn.datatables.net/fixedheader/3.3.2/js/dataTables.fixedHeader.min.js"></script>
    <script>
        $(document).ready(function() {
            // Fungsi untuk setup DataTable dengan pengaturan yang konsisten
            function setupDataTable(tableId, orderColumns) {
                const isAdminOrStaff = <?= in_array($_SESSION['role'], ['admin', 'staff']) ? 'true' : 'false' ?>;
                
                // Konfigurasi kolom default
                const columnDefs = [
                    { orderable: false, targets: [6] }, // Kolom Kontak WA non-orderable
                    // Hapus aturan width fixed dan gunakan responsive
                    { targets: '_all', className: 'dt-body-nowrap' }
                ];
                
                // Atur kolom aksi berdasarkan role
                if (!isAdminOrStaff) {
                    columnDefs.push({ 
                        targets: [7], 
                        orderable: false,
                        visible: false,
                        searchable: false
                    });
                } else {
                    columnDefs.push({ 
                        targets: [7], 
                        orderable: false 
                    });
                }
                
                return $(tableId).DataTable({
                    language: {
                        url: '//cdn.datatables.net/plug-ins/1.13.5/i18n/id.json'
                    },
                    order: orderColumns,
                    columnDefs: columnDefs,
                    pageLength: 25,
                    scrollX: true,
                    scrollCollapse: true,
                    autoWidth: true, // Biarkan DataTables menghitung lebar otomatis
                    fixedHeader: {
                        header: true,
                        headerOffset: $('.navbar').outerHeight() || 0
                    },
                    dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>rt<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
                    initComplete: function() {
                        // Biarkan kolom menyesuaikan konten
                        this.api().columns.adjust();
                        
                        setTimeout(() => {
                            this.api().columns.adjust();
                            $(window).trigger('resize');
                        }, 100);
                    },
                    drawCallback: function() {
                        this.api().columns.adjust();
                    }
                });
            }
            
            // Inisialisasi semua tabel
            const table1 = setupDataTable('#jadwalQuranTable', [[0, 'asc'], [3, 'asc']]);
            const table2 = setupDataTable('#jadwalTable', [[0, 'asc'], [3, 'asc']]); 
            const table3 = setupDataTable('#kegiatanTable', [[0, 'asc'], [3, 'asc']]);
            
            // PERBAIKAN UTAMA: Pastikan tabel di-resize saat window berubah ukuran
            let resizeTimer;
            $(window).on('resize', function() {
                clearTimeout(resizeTimer);
                resizeTimer = setTimeout(function() {
                    table1.columns.adjust();
                    table2.columns.adjust();
                    table3.columns.adjust();
                    
                    // Force redraw untuk memastikan sync
                    $('.dataTables_scrollHeadInner').css('width', '100%');
                    $('.dataTables_scrollHeadInner table').css('width', '100%');
                }, 250);
            });
            
            // PERBAIKAN UTAMA: Saat tab di-click, adjust tabel dengan delay untuk memastikan render selesai
            $('button[data-bs-toggle="tab"]').on('shown.bs.tab', function(e) {
                const target = $(e.target).attr('data-bs-target');
                
                setTimeout(function() {
                    switch(target) {
                        case '#jadwalKelasQuran':
                            table1.columns.adjust();
                            break;
                        case '#jadwalKelasMadin':
                            table2.columns.adjust();
                            break;
                        case '#jadwalKegiatan':
                            table3.columns.adjust();
                            break;
                    }
                    
                    // Force sync header dan body
                    $('.dataTables_scrollHeadInner').css('width', '100%');
                    $('.dataTables_scrollHeadInner table').css('width', '100%');
                    
                    // Trigger resize event
                    $(window).trigger('resize');
                }, 300);
            });
            
            // PERBAIKAN TAMBAHAN: Handle orientation change untuk mobile
            window.addEventListener('orientationchange', function() {
                setTimeout(function() {
                    table1.columns.adjust();
                    table2.columns.adjust();
                    table3.columns.adjust();
                    $(window).trigger('resize');
                }, 500);
            });
            
            // Tampilkan modal jika ada parameter edit
            <?php if ($current_jadwal_quran): ?>
            $('#jadwalQuranModal').modal('show');
            <?php endif; ?>
            
            <?php if ($current_jadwal): ?>
            $('#jadwalModal').modal('show');
            <?php endif; ?>
            
            <?php if ($current_kegiatan): ?>
            $('#kegiatanModal').modal('show');
            <?php endif; ?>
            
            // Fungsi reset modal
            function resetModal(modalId, title, submitName, submitText) {
                $(modalId + ' form')[0].reset();
                $(modalId + ' .modal-title').text(title);
                $(modalId + ' button[type="submit"]').text(submitText).attr('name', submitName);
                $(modalId + ' input[name="id"]').remove();
                $(modalId + ' input[name="hari[]"]').prop('checked', false);
                $(modalId + ' .multi-hari').show();
                $(modalId + ' .single-hari').hide();
            }
            
            // Tombol Tambah Jadwal Quran
            $('#btnTambahJadwalQuran').click(function() {
                resetModal('#jadwalQuranModal', 'Tambah Jadwal Quran', 'tambah_jadwal_quran', 'Simpan');
            });
            
            // Tombol Tambah Jadwal Madin
            $('#btnTambah').click(function() {
                resetModal('#jadwalModal', 'Tambah Jadwal', 'tambah_jadwal', 'Simpan');
            });
            
            // Tombol Tambah Kegiatan
            $('#btnTambahKegiatan').click(function() {
                resetModal('#kegiatanModal', 'Tambah Kegiatan', 'tambah_kegiatan', 'Simpan');
            });
            
            // Auto close modal setelah submit berhasil
            <?php if ($message): ?>
            setTimeout(() => {
                $('#jadwalModal, #kegiatanModal, #jadwalQuranModal').modal('hide');
                
                // Hapus parameter edit dari URL
                const url = new URL(window.location);
                url.searchParams.delete('edit');
                url.searchParams.delete('edit_kegiatan');
                url.searchParams.delete('edit_jadwal_quran');
                window.history.replaceState({}, document.title, url.toString());
            }, 1500);
            <?php endif; ?>
            
            // Validasi waktu - jam selesai harus setelah jam mulai
            $('input[type="time"]').on('change', function() {
                const jamMulai = $(this).closest('.row').find('input[name="jam_mulai"]').val();
                const jamSelesai = $(this).closest('.row').find('input[name="jam_selesai"]').val();
                
                if (jamMulai && jamSelesai && jamSelesai <= jamMulai) {
                    alert('Jam selesai harus setelah jam mulai');
                    $(this).val('');
                }
            });
        });
        
        // PERBAIKAN: Optimasi resize handler
        let resizeTimeout;
        $(window).on('resize', function() {
            clearTimeout(resizeTimeout);
            resizeTimeout = setTimeout(function() {
                if (typeof table1 !== 'undefined') table1.columns.adjust();
                if (typeof table2 !== 'undefined') table2.columns.adjust();
                if (typeof table3 !== 'undefined') table3.columns.adjust();
                
                // Force reflow untuk memastikan tampilan konsisten
                $('.dataTables_scrollHeadInner').css('width', 'auto');
                $('.dataTables_scrollHeadInner table').css('width', 'auto');
            }, 150);
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
    </script>
</body>
</html>