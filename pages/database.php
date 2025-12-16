<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Simpan tab aktif dalam session
if (isset($_GET['active_tab'])) {
    $_SESSION['active_tab'] = $_GET['active_tab'];
}
$active_tab = $_SESSION['active_tab'] ?? 'dataGuru&Pembina';

// pages/database.php
require_once '../includes/init.php';

// Filter data untuk role guru
$guru_id = null;
if ($_SESSION['role'] === 'guru' && isset($_SESSION['guru_id'])) {
    $guru_id = $_SESSION['guru_id'];
}

if (!check_auth()) {
    header("Location: ../index.php");
    exit();
}

$message = '';
$current_murid = null;
$current_guru = null;

// KONVERSI GURU KE USER (One-time script) - PERUBAHAN: nama sebagai username, id sebagai password
if (isset($_GET['konversi_guru']) && isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    // Ambil data guru yang belum dikonversi
    $query_guru = "SELECT * FROM guru WHERE nip IS NOT NULL AND nip != ''";
    $result_guru = mysqli_query($conn, $query_guru);
    
    $success_count = 0;
    $error_count = 0;
    $duplicate_count = 0;
    
    while ($guru = mysqli_fetch_assoc($result_guru)) {
        // PERUBAHAN: Gunakan nama sebagai username, bukan NIP
        $username = $guru['nama'];
        
        // Cek apakah username sudah ada
        $sql_cek = "SELECT id FROM users WHERE username = ?";
        $stmt_cek = $conn->prepare($sql_cek);
        $stmt_cek->bind_param("s", $username);
        $stmt_cek->execute();
        $result_cek = $stmt_cek->get_result();
        
        if ($result_cek->num_rows > 0) {
            $duplicate_count++;
            continue;
        }
        
        // PERUBAHAN: Password = guru_id (bukan NIP)
        $password = (string)$guru['guru_id'];
        
        // Insert data guru sebagai user dengan role 'guru'
        $sql_insert = "INSERT INTO users (username, password, role, created_at, updated_at) 
                      VALUES (?, ?, 'guru', NOW(), NOW())";
        $stmt_insert = $conn->prepare($sql_insert);
        
        if ($stmt_insert) {
            $stmt_insert->bind_param("ss", $username, $password); // Password = guru_id (plain text)
            
            if ($stmt_insert->execute()) {
                $success_count++;
            } else {
                $error_count++;
                error_log("Error konversi guru: " . $stmt_insert->error);
            }
            $stmt_insert->close();
        } else {
            $error_count++;
            error_log("Error prepare statement: " . $conn->error);
        }
        $stmt_cek->close();
    }
    
    if ($error_count == 0) {
        $msg = "Berhasil mengkonversi $success_count data guru menjadi user!";
        if ($duplicate_count > 0) $msg .= " ($duplicate_count data duplikat dilewati)";
        $message = "success|$msg";
    } else {
        $message = "warning|Berhasil mengkonversi $success_count data guru, gagal $error_count data, dan $duplicate_count data duplikat.";
    }
    
    // Redirect untuk menghindari resubmission
    header("Location: database.php?message=" . urlencode($message));
    exit();
}

// BUAT FOLDER TEMPLATE DAN UPLOADS JIKA BELUM ADA
$template_dir = "../templates/";
$upload_dir = "../uploads/";

if (!file_exists($template_dir)) mkdir($template_dir, 0777, true);
if (!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);

$template_file = $template_dir . "template_import_murid.xlsx";
$template_url = "templates/template_import_murid.xlsx"; // Path relatif untuk download

// ========== PROSES CRUD GURU/PEMBINA ==========

// Proses Tambah Guru - PERBAIKI dengan menambahkan handling foto
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['tambah_guru'])) {
    $nama = $_POST['nama'];
    $nip = $_POST['nip'] ?? '';
    // Tambahkan field nik
    $nik = $_POST['nik'] ?? '';
    $jenis_kelamin = $_POST['jenis_kelamin'] ?? '';
    $no_hp = $_POST['no_hp'] ?? '';
    $alamat = $_POST['alamat'] ?? '';
    $jabatan = $_POST['jabatan'] ?? '';
    
    // Validasi NIP duplikat (jika NIP diisi)
    if (!empty($nip)) {
        $sql_cek = "SELECT * FROM guru WHERE nip = ?";
        $stmt_cek = $conn->prepare($sql_cek);
        $stmt_cek->bind_param("s", $nip);
        $stmt_cek->execute();
        $result_cek = $stmt_cek->get_result();

        if ($result_cek->num_rows > 0) {
            $message = "danger|NIP sudah digunakan oleh guru lain!";
        } else {
            // Proses upload foto
            $foto = '';
            if (isset($_FILES['foto']) && $_FILES['foto']['error'] == UPLOAD_ERR_OK) {
                $target_dir = "../uploads/";
                if (!file_exists($target_dir)) {
                    mkdir($target_dir, 0777, true);
                }
                $file_ext = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
                $new_filename = uniqid() . '.' . $file_ext;
                $target_file = $target_dir . $new_filename;
                
                if (move_uploaded_file($_FILES['foto']['tmp_name'], $target_file)) {
                    $foto = $new_filename;
                }
            }
            
            // Update query INSERT
            $sql = "INSERT INTO guru (nama, nip, nik, jenis_kelamin, no_hp, alamat, jabatan, foto) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssssssss", $nama, $nip, $nik, $jenis_kelamin, $no_hp, $alamat, $jabatan, $foto);
            
            if ($stmt->execute()) {
                $message = "success|Data guru berhasil ditambahkan!";
            } else {
                $message = "danger|Error: " . $stmt->error;
            }
        }
    } else {
        // Jika NIP kosong, langsung insert tanpa validasi duplikat
        // Proses upload foto
        $foto = '';
        if (isset($_FILES['foto']) && $_FILES['foto']['error'] == UPLOAD_ERR_OK) {
            $target_dir = "../uploads/";
            if (!file_exists($target_dir)) {
                mkdir($target_dir, 0777, true);
            }
            $file_ext = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
            $new_filename = uniqid() . '.' . $file_ext;
            $target_file = $target_dir . $new_filename;
            
            if (move_uploaded_file($_FILES['foto']['tmp_name'], $target_file)) {
                $foto = $new_filename;
            }
        }
        
        // Update query INSERT
        $sql = "INSERT INTO guru (nama, nip, nik, jenis_kelamin, no_hp, alamat, jabatan, foto) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssssss", $nama, $nip, $nik, $jenis_kelamin, $no_hp, $alamat, $jabatan, $foto);
        
        if ($stmt->execute()) {
            $message = "success|Data guru berhasil ditambahkan!";
        } else {
            $message = "danger|Error: " . $stmt->error;
        }
    }
}

// Proses Edit Guru - PERBAIKAN LENGKAP
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_guru'])) {
    $id = $_POST['id'];
    $nama = $_POST['nama'];
    $nip = $_POST['nip'] ?? '';
    $jenis_kelamin = $_POST['jenis_kelamin'] ?? '';
    $no_hp = $_POST['no_hp'] ?? '';
    $alamat = $_POST['alamat'] ?? '';
    $jabatan = $_POST['jabatan'] ?? '';

    // Validasi NIP duplikat (kecuali data saat ini)
    if (!empty($nip)) {
        $sql_cek = "SELECT * FROM guru WHERE nip = ? AND guru_id <> ?";
        $stmt_cek = $conn->prepare($sql_cek);
        if ($stmt_cek) {
            $stmt_cek->bind_param("si", $nip, $id);
            $stmt_cek->execute();
            $result_cek = $stmt_cek->get_result();

            if ($result_cek->num_rows > 0) {
                $message = "danger|NIP sudah digunakan oleh guru lain!";
            } else {
                processGuruUpdate($conn, $id, $nama, $nip, $jenis_kelamin, $no_hp, $alamat, $jabatan);
            }
            $stmt_cek->close();
        } else {
            $message = "danger|Error dalam validasi NIP: " . $conn->error;
        }
    } else {
        processGuruUpdate($conn, $id, $nama, $nip, $jenis_kelamin, $no_hp, $alamat, $jabatan);
    }
}

// Fungsi untuk memproses update guru
function processGuruUpdate($conn, $id, $nama, $nip, $jenis_kelamin, $no_hp, $alamat, $jabatan) {
    // Dapatkan foto lama
    $sql_old = "SELECT foto FROM guru WHERE guru_id = ?";
    $stmt_old = $conn->prepare($sql_old);
    if (!$stmt_old) {
        $GLOBALS['message'] = "danger|Error mendapatkan foto lama: " . $conn->error;
        return;
    }
    
    $stmt_old->bind_param("i", $id);
    $stmt_old->execute();
    $result_old = $stmt_old->get_result();
    $old_photo = $result_old->fetch_assoc()['foto'] ?? '';
    $stmt_old->close();
    
    $foto = $old_photo;

    // Proses upload foto hanya jika ada file baru
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] == UPLOAD_ERR_OK) {
        $target_dir = "../uploads/";
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        
        $file_ext = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
        $new_filename = uniqid() . '.' . $file_ext;
        $target_file = $target_dir . $new_filename;
        
        if (move_uploaded_file($_FILES['foto']['tmp_name'], $target_file)) {
            $foto = $new_filename;
            
            // Hapus foto lama jika ada dan berbeda dengan yang baru
            if (!empty($old_photo) && file_exists($target_dir . $old_photo)) {
                unlink($target_dir . $old_photo);
            }
        }
    }
    
    // Query UPDATE yang sesuai dengan struktur tabel
    $sql = "UPDATE guru SET 
            nama = ?, 
            nip = ?, 
            nik = ?,
            jenis_kelamin = ?, 
            no_hp = ?, 
            alamat = ?, 
            jabatan = ?,
            foto = ?
            WHERE guru_id = ?";
    
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        // Perbaikan: tambahkan variabel $nik yang belum didefinisikan
        $nik = $_POST['nik'] ?? '';
        $stmt->bind_param("ssssssssi", $nama, $nip, $nik, $jenis_kelamin, $no_hp, $alamat, $jabatan, $foto, $id);
        
        if ($stmt->execute()) {
            $GLOBALS['message'] = "success|Data guru berhasil diperbarui!";
        } else {
            $GLOBALS['message'] = "danger|Error: " . $stmt->error;
        }
        $stmt->close();
    } else {
        $GLOBALS['message'] = "danger|Error dalam persiapan query: " . $conn->error;
    }
}

// Proses Hapus Guru
if (isset($_GET['hapus_guru'])) {
    $id = intval($_GET['hapus_guru']);
    
    // Cek apakah guru terkait dengan data lain (misalnya sebagai wali kelas)
    $sql_check = "SELECT COUNT(*) as jumlah FROM kelas_madin WHERE guru_id = ?";
    $stmt_check = $conn->prepare($sql_check);
    $stmt_check->bind_param("i", $id);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    $row_check = $result_check->fetch_assoc();
    
    if ($row_check['jumlah'] > 0) {
        $message = "danger|Guru tidak dapat dihapus karena masih menjadi wali kelas!";
    } else {
        $sql = "DELETE FROM guru WHERE guru_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            $message = "success|Data guru berhasil dihapus!";
        } else {
            $message = "danger|Error: " . $stmt->error;
        }
    }
}

// Ambil data untuk edit Guru
if (isset($_GET['edit_guru'])) {
    $id = intval($_GET['edit_guru']);
    
    $sql = "SELECT * FROM guru WHERE guru_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $current_guru = $result->fetch_assoc();
    }
}

// PROSES IMPOR DATA MURID DARI EXCEL
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['import_murid'])) {
    // Perbaikan path library
    $lib1 = '../spreadsheet-reader/php-excel-reader/excel_reader2.php';
    $lib2 = '../spreadsheet-reader/SpreadsheetReader.php';
    
    if (!file_exists($lib1) || !file_exists($lib2)) {
        $message = "danger|Library SpreadsheetReader tidak ditemukan!";
    } else {
        require $lib1;
        require $lib2;

        $target_dir = "../uploads/";
        $target_file = $target_dir . basename($_FILES['file_murid']['name']);

        // Pindahkan file yang diunggah
        if (move_uploaded_file($_FILES['file_murid']['tmp_name'], $target_file)) {
            try {
                $Reader = new SpreadsheetReader($target_file);
                
                $successCount = 0;
                $errorCount = 0;
                $duplicateCount = 0;
                
                foreach ($Reader as $Key => $Row) {
                    if ($Key < 1) continue; // Lewati header
                    
                    // Pastikan ada minimal 10 kolom
                    if (count($Row) < 10) {
                        $errorCount++;
                        continue;
                    }
                    
                    // Ambil data dari baris
                    $nama = $Row[0] ?? '';
                    $nis = $Row[1] ?? '';
                    // Tambahkan field nik
                    $nik = $Row[10] ?? ''; // Perbaikan: ambil dari kolom yang sesuai

                    $kelas_id = $Row[2] ?? 0;
                    $kamar = $Row[3] ?? '';
                    $no_hp = $Row[4] ?? '';
                    $alamat = $Row[5] ?? '';
                    $nama_wali = $Row[6] ?? '';
                    $no_wali = $Row[7] ?? '';
                    $nilai = $Row[8] ?? 0;
                    $foto = $Row[9] ?? ''; // Nama file foto (opsional)

                    // Skip jika NIS kosong
                    if (empty($nis)) {
                        $errorCount++;
                        continue;
                    }
                    
                    // Validasi NIS duplikat
                    $sql_cek = "SELECT * FROM murid WHERE nis = ?";
                    $stmt_cek = $conn->prepare($sql_cek);
                    $stmt_cek->bind_param("s", $nis);
                    $stmt_cek->execute();
                    $result_cek = $stmt_cek->get_result();

                    if ($result_cek->num_rows > 0) {
                        $duplicateCount++;
                        continue;
                    }
                    
                    // Update query INSERT murid - Perbaikan struktur parameter
                    $kelas_quran_id = null;
                    $kamar_id = null;
                    
                    $sql = "INSERT INTO murid (nama, nis, nik, kelas_madin_id, kelas_quran_id, kamar_id, no_hp, alamat, nama_wali, no_wali, nilai, foto) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("sssiisssssds", 
                        $nama, $nis, $nik, $kelas_id, $kelas_quran_id, $kamar_id, 
                        $no_hp, $alamat, $nama_wali, $no_wali, $nilai, $foto);
                    
                    if ($stmt->execute()) {
                        $successCount++;
                    } else {
                        $errorCount++;
                    }
                    $stmt->close();
                }
                
                // Hapus file setelah diimpor
                unlink($target_file);
                
                $msg = "Berhasil mengimpor $successCount data murid!";
                if ($duplicateCount > 0) $msg .= " ($duplicateCount data duplikat dilewati)";
                if ($errorCount > 0) $msg .= " ($errorCount data gagal)";
                
                $message = "success|$msg";
                
            } catch (Exception $e) {
                $message = "danger|Error: " . $e->getMessage();
            }
        } else {
            $message = "danger|Gagal mengunggah file!";
        }
    }
}

// BUAT FILE TEMPLATE JIKA BELUM ADA (MENGGUNAKAN SpreadsheetWriter)
if (!file_exists($template_file)) {
    // Header untuk file Excel
    $header = "Nama\tNIS\tID Kelas\tKamar\tNo HP\tAlamat\tNama Wali\tNo Wali\tNilai\tFoto\n";
    $data1 = "Ahmad Sutisna\t20230001\t1\tA1\t081234567890\tJl. Merdeka No. 123\tBambang Sutisna\t081234567891\t85\tahmad.jpg\n";
    $data2 = "Siti Rahayu\t20230002\t2\tB2\t081234567891\tJl. Sudirman No. 45\tWahyu Rahayu\t081234567892\t90\tsiti.jpg";
    
    // Gabungkan semua data
    $excelContent = $header . $data1 . $data2;
    
    // Simpan ke file
    file_put_contents($template_file, $excelContent);
}

// Proses Tambah Murid - PERBAIKAN
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['tambah_murid'])) {
    $nama = $_POST['nama'];
    $nis = $_POST['nis'];
    $kelas_id = $_POST['kelas_id'];
    $no_hp = $_POST['no_hp'] ?? '';
    $alamat = $_POST['alamat'] ?? '';
    $nama_wali = $_POST['nama_wali'] ?? '';
    $no_wali = $_POST['no_wali'] ?? '';
    $nilai = $_POST['nilai'] ?? 0;
    
    $kelas_quran_id = $_POST['kelas_quran_id'] ?? null;
    $kamar_id = $_POST['kamar_id'] ?? null;
    
    // Konversi string kosong menjadi null
    if ($kelas_quran_id === '') $kelas_quran_id = null;
    if ($kamar_id === '') $kamar_id = null;
    
    // Validasi NIS duplikat
    $sql_cek = "SELECT * FROM murid WHERE nis = ?";
    $stmt_cek = $conn->prepare($sql_cek);
    $stmt_cek->bind_param("s", $nis);
    $stmt_cek->execute();
    $result_cek = $stmt_cek->get_result();

    if ($result_cek->num_rows > 0) {
        $message = "danger|NIS sudah digunakan oleh murid lain!";
    } else {
        // Proses upload foto
        $foto = '';
        if (isset($_FILES['foto']) && $_FILES['foto']['error'] == UPLOAD_ERR_OK) {
            $target_dir = "../uploads/";
            if (!file_exists($target_dir)) {
                mkdir($target_dir, 0777, true);
            }
            $file_ext = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
            $new_filename = uniqid() . '.' . $file_ext;
            $target_file = $target_dir . $new_filename;
            
            if (move_uploaded_file($_FILES['foto']['tmp_name'], $target_file)) {
                $foto = $new_filename;
            }
        }
        
        // PERBAIKAN: Query INSERT yang sesuai
        $sql = "INSERT INTO murid (nama, nis, kelas_madin_id, kelas_quran_id, kamar_id, no_hp, alamat, nama_wali, no_wali, nilai, foto) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("ssiisssssds", 
                $nama, $nis, $kelas_id, $kelas_quran_id, $kamar_id, 
                $no_hp, $alamat, $nama_wali, $no_wali, $nilai, $foto);

            if ($stmt->execute()) {
                $message = "success|Data murid berhasil ditambahkan!";
            } else {
                $message = "danger|Error: " . $stmt->error;
            }
        } else {
            $message = "danger|Error dalam persiapan query: " . $conn->error;
        }
    }
}

// Proses Edit Murid - PERBAIKAN
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_murid'])) {
    $id = $_POST['id'];
    $nama = $_POST['nama'];
    $nis = $_POST['nis'];
    $kelas_id = $_POST['kelas_id'];
    $no_hp = $_POST['no_hp'] ?? '';
    $alamat = $_POST['alamat'] ?? '';
    $nama_wali = $_POST['nama_wali'] ?? '';
    $no_wali = $_POST['no_wali'] ?? '';
    $nilai = $_POST['nilai'] ?? 0;
    
    // Ambil data tambahan
    $kelas_quran_id = $_POST['kelas_quran_id'] ?? null;
    $kamar_id = $_POST['kamar_id'] ?? null;
    
    // Konversi string kosong menjadi null
    if ($kelas_quran_id === '') $kelas_quran_id = null;
    if ($kamar_id === '') $kamar_id = null;

    // Validasi NIS duplikat (kecuali data saat ini)
    $sql_cek = "SELECT * FROM murid WHERE nis = ? AND murid_id <> ?";
    $stmt_cek = $conn->prepare($sql_cek);
    $stmt_cek->bind_param("si", $nis, $id);
    $stmt_cek->execute();
    $result_cek = $stmt_cek->get_result();

    if ($result_cek->num_rows > 0) {
        $message = "danger|NIS sudah digunakan oleh murid lain!";
    } else {
        // Dapatkan foto lama
        $sql_old = "SELECT foto FROM murid WHERE murid_id = ?";
        $stmt_old = $conn->prepare($sql_old);
        $stmt_old->bind_param("i", $id);
        $stmt_old->execute();
        $result_old = $stmt_old->get_result();
        $old_photo = $result_old->fetch_assoc()['foto'] ?? '';
        
        $foto = $old_photo;

        // Proses upload foto hanya jika ada file baru
        if (isset($_FILES['foto']) && $_FILES['foto']['error'] == UPLOAD_ERR_OK) {
            $target_dir = "../uploads/";
            if (!file_exists($target_dir)) {
                mkdir($target_dir, 0777, true);
            }
            
            $file_ext = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
            $new_filename = uniqid() . '.' . $file_ext;
            $target_file = $target_dir . $new_filename;
            
            if (move_uploaded_file($_FILES['foto']['tmp_name'], $target_file)) {
                $foto = $new_filename;
                
                // Hapus foto lama jika ada dan berbeda dengan yang baru
                if (!empty($old_photo) && file_exists($target_dir . $old_photo)) {
                    unlink($target_dir . $old_photo);
                }
            }
        }
        
        // PERBAIKAN: Query update data dengan parameter yang sesuai
        $sql = "UPDATE murid SET 
                nama = ?, 
                nis = ?, 
                nik = ?,
                kelas_madin_id = ?, 
                kelas_quran_id = ?, 
                kamar_id = ?, 
                no_hp = ?, 
                alamat = ?, 
                nama_wali = ?, 
                no_wali = ?, 
                nilai = ?,
                foto = ?
                WHERE murid_id = ?";
        
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            // PERBAIKAN: Tambahkan variabel $nik
            $nik = $_POST['nik'] ?? '';
            $stmt->bind_param("sssiisssssdsi", 
                $nama, $nis, $nik, $kelas_id, $kelas_quran_id, $kamar_id, 
                $no_hp, $alamat, $nama_wali, $no_wali, $nilai, $foto, $id);
            
            if ($stmt->execute()) {
                $message = "success|Data murid berhasil diperbarui!";
            } else {
                $message = "danger|Error: " . $stmt->error;
            }
        } else {
            $message = "danger|Error dalam persiapan query: " . $conn->error;
        }
    }
}

// Proses Hapus Murid (Pindahkan ke Alumni)
if (isset($_GET['hapus'])) {
    $id = intval($_GET['hapus']);
    // PERBAIKAN: Pastikan status dikirim dengan benar
    $status_keluar = $_GET['status'] ?? 'Lulus'; // Default Lulus
    $keterangan = $_GET['keterangan'] ?? '';

    // Ambil data murid
    $sql = "SELECT * FROM murid WHERE murid_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $murid = $result->fetch_assoc();
    
    if ($murid) {
        // Gunakan tahun aktual
        $tahun_masuk = date('Y', strtotime($murid['created_at'] ?? 'now -3 years'));
        $tahun_keluar = date('Y');
        
        // PERBAIKAN: Simpan nilai default ke variabel terlebih dahulu
        $pekerjaan = $murid['pekerjaan'] ?? '';
        $pendidikan_lanjut = $murid['pendidikan_lanjut'] ?? '';
        $foto = $murid['foto'] ?? '';
        
        // Di bagian Proses Hapus Murid (Pindahkan ke Alumni)
        // GANTI kode SQL INSERT alumni dengan ini:
        $sql_insert = "INSERT INTO alumni (
            nama, 
            nis, 
            nik,
            no_hp,
            alamat,
            tahun_masuk, 
            tahun_keluar, 
            status_keluar, 
            keterangan,
            pekerjaan, 
            pendidikan_lanjut, 
            foto  
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt_insert = $conn->prepare($sql_insert);
        $stmt_insert->bind_param(
            "sssssiisssss", 
            $murid['nama'],
            $murid['nis'],
            $murid['nik'] ?? '', // Tambahkan NIK
            $murid['no_hp'], // Pastikan kolom no_hp ada di tabel murid
            $murid['alamat'], // Tambahkan alamat
            $tahun_masuk,
            $tahun_keluar,
            $status_keluar,
            $keterangan,
            $pekerjaan,
            $pendidikan_lanjut,
            $foto
        );
        
        if ($stmt_insert->execute()) {
            // Hapus dari tabel murid
            $sql_delete = "DELETE FROM murid WHERE murid_id = ?";
            $stmt_delete = $conn->prepare($sql_delete);
            $stmt_delete->bind_param("i", $id);
            
            if ($stmt_delete->execute()) {
                $message = "success|Data murid berhasil dipindahkan ke alumni!";
            } else {
                $message = "danger|Gagal menghapus data murid: " . $stmt_delete->error;
            }
            $stmt_delete->close();
        } else {
            $message = "danger|Gagal memindahkan ke alumni: " . $stmt_insert->error;
        }
        $stmt_insert->close();
    } else {
        $message = "danger|Data murid tidak ditemukan!";
    }
}

// Proses Pindah ke Alumni
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['pindah_alumni'])) {
    $murid_ids = $_POST['murid_ids'] ?? [];
    $status_keluar = $_POST['status_keluar'] ?? 'Lulus';
    $tahun_keluar = $_POST['tahun_keluar'] ?? date('Y');
    $keterangan = $_POST['keterangan'] ?? '';

    if (!empty($murid_ids)) {
        $success_count = 0;
        $error_count = 0;
        
        foreach ($murid_ids as $murid_id) {
            // Ambil data murid
            $sql_select = "SELECT * FROM murid WHERE murid_id = ?";
            $stmt_select = $conn->prepare($sql_select);
            $stmt_select->bind_param("i", $murid_id);
            $stmt_select->execute();
            $result_murid = $stmt_select->get_result();
            
            if ($result_murid->num_rows > 0) {
                $murid = $result_murid->fetch_assoc();
                
                // Insert ke tabel alumni - PERBAIKAN: tambahkan field yang diperlukan
                $sql_insert = "INSERT INTO alumni (
                    nama, 
                    nis, 
                    nik,
                    no_hp,
                    alamat,
                    tahun_masuk, 
                    tahun_keluar, 
                    status_keluar, 
                    keterangan, 
                    pekerjaan,
                    pendidikan_lanjut,
                    foto
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                
                // Tentukan tahun masuk (gunakan tahun sekarang jika created_at tidak ada)
                $tahun_masuk = date('Y');
                if (!empty($murid['created_at'])) {
                    $tahun_masuk = date('Y', strtotime($murid['created_at']));
                }
                
                // Set nilai default untuk field yang tidak ada di tabel murid
                $pekerjaan = $murid['pekerjaan'] ?? '';
                $pendidikan_lanjut = $murid['pendidikan_lanjut'] ?? '';
                
                $stmt_insert->bind_param("sssssiisssss", 
                    $murid['nama'], 
                    $murid['nis'],
                    $murid['nik'] ?? '', // Tambahkan NIK
                    $murid['no_hp'], // Tambahkan no_hp
                    $murid['alamat'], // Tambahkan alamat
                    $tahun_masuk, 
                    $tahun_keluar, 
                    $status_keluar, 
                    $keterangan,
                    $pekerjaan,
                    $pendidikan_lanjut,
                    $murid['foto']
                );
                
                if ($stmt_insert->execute()) {
                    // Hapus dari tabel murid
                    $sql_delete = "DELETE FROM murid WHERE murid_id = ?";
                    $stmt_delete = $conn->prepare($sql_delete);
                    $stmt_delete->bind_param("i", $murid_id);
                    
                    if ($stmt_delete->execute()) {
                        $success_count++;
                    } else {
                        $error_count++;
                    }
                    $stmt_delete->close();
                } else {
                    $error_count++;
                }
                $stmt_insert->close();
            } else {
                $error_count++;
            }
            $stmt_select->close();
        }
        
        if ($error_count == 0) {
            $message = "success|Berhasil memindahkan " . $success_count . " murid ke alumni!";
        } else {
            $message = "warning|Berhasil memindahkan " . $success_count . " murid, dan gagal memindahkan $error_count murid.";
        }
    } else {
        $message = "danger|Tidak ada murid yang dipilih!";
    }
}

// Di bagian PROSES EDIT MASSAL - PERBAIKAN LENGKAP
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_massal'])) {
    $murid_ids = $_POST['murid_ids'] ?? [];
    $kelas_id = $_POST['kelas_id'] ?? null;
    $kelas_quran_id = $_POST['kelas_quran_id'] ?? null;
    $kamar_id = $_POST['kamar_id'] ?? null;

    if (!empty($murid_ids)) {
        // Konversi ke integer dan validasi
        $murid_ids = array_filter(array_map('intval', $murid_ids));
        
        if (empty($murid_ids)) {
            $message = "danger|Tidak ada murid yang valid dipilih!";
        } else {
            // Hitung jumlah field yang diisi
            $filled_fields = 0;
            $updates = [];
            $params = [];
            $types = '';
            
            if (!empty($kelas_id) && $kelas_id !== '') {
                $updates[] = "kelas_madin_id = ?";
                $params[] = $kelas_id;
                $types .= 'i';
                $filled_fields++;
            }
            
            if (!empty($kelas_quran_id) && $kelas_quran_id !== '') {
                $updates[] = "kelas_quran_id = ?";
                $params[] = $kelas_quran_id;
                $types .= 'i';
                $filled_fields++;
            }
            
            if (!empty($kamar_id) && $kamar_id !== '') {
                $updates[] = "kamar_id = ?";
                $params[] = $kamar_id;
                $types .= 'i';
                $filled_fields++;
            }
            
            // Validasi: hanya satu field yang boleh diisi
            if ($filled_fields === 1) {
                // Tambahkan murid_ids ke parameter
                $placeholders = implode(',', array_fill(0, count($murid_ids), '?'));
                $types .= str_repeat('i', count($murid_ids));
                $params = array_merge($params, $murid_ids);
                
                $sql = "UPDATE murid SET " . implode(', ', $updates) . 
                       " WHERE murid_id IN ($placeholders)";
                
                $stmt = $conn->prepare($sql);
                if ($stmt) {
                    $stmt->bind_param($types, ...$params);
                    if ($stmt->execute()) {
                        $affected_rows = $stmt->affected_rows;
                        $message = "success|Berhasil memperbarui " . $affected_rows . " data murid!";
                    } else {
                        $message = "danger|Error: " . $stmt->error;
                    }
                    $stmt->close();
                } else {
                    $message = "danger|Error dalam persiapan pernyataan: " . $conn->error;
                }
            } else if ($filled_fields > 1) {
                $message = "danger|Hanya boleh memilih satu field untuk diupdate!";
            } else {
                $message = "warning|Pilih satu field yang ingin diupdate!";
            }
        }
    } else {
        $message = "danger|Tidak ada murid yang dipilih!";
    }
}

// Ambil data untuk edit
if (isset($_GET['edit'])) {
    $id = intval($_GET['edit']);
    
    $sql = "SELECT m.*, k.guru_id as guru_madin, kq.guru_id as guru_quran 
            FROM murid m 
            LEFT JOIN kelas_madin k ON m.kelas_madin_id = k.kelas_id
            LEFT JOIN kelas_quran kq ON m.kelas_quran_id = kq.id
            WHERE m.murid_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $current_murid = $result->fetch_assoc();
        // Jika role guru, cek apakah murid ini terkait dengan guru yang login
        if (isset($_SESSION['role']) && $_SESSION['role'] === 'guru' && $guru_id) {
            if ($current_murid['guru_madin'] != $guru_id && $current_murid['guru_quran'] != $guru_id) {
                // Tidak berhak mengakses
                header("Location: database.php?message=danger|Anda tidak berhak mengakses data murid ini!");
                exit();
            }
        }
    } else {
        header("Location: database.php?message=danger|Data murid tidak ditemukan!");
        exit();
    }
}

// ========== AMBIL DATA UNTUK DITAMPILKAN ==========

// Ambil semua data guru dengan filter jika guru
$sql_guru = "SELECT * FROM guru WHERE 1=1";
if ($guru_id) {
    $sql_guru .= " AND guru_id = ?";
}
$sql_guru .= " ORDER BY nama";

$stmt_guru = $conn->prepare($sql_guru);
if ($guru_id) {
    $stmt_guru->bind_param("i", $guru_id);
}
$stmt_guru->execute();
$result_guru = $stmt_guru->get_result();
$guru_list = [];
if ($result_guru->num_rows > 0) {
    while ($row = $result_guru->fetch_assoc()) {
        $guru_list[] = $row;
    }
}
$stmt_guru->close();

// OPTIMASI QUERY: Menggunakan JOIN yang lebih efisien (untuk murid)
// AMBIL SEMUA MURID DENGAN FILTER JIKA GURU
$sql = "SELECT 
            m.murid_id, 
            m.nama, 
            m.nis, 
            m.alamat,  
            m.foto, 
            m.no_hp, 
            k.nama_kelas, 
            kq.nama_kelas as nama_kelas_quran, 
            km.nama_kamar 
        FROM murid m 
        LEFT JOIN kelas_madin k ON m.kelas_madin_id = k.kelas_id
        LEFT JOIN kelas_quran kq ON m.kelas_quran_id = kq.id
        LEFT JOIN kamar km ON m.kamar_id = km.kamar_id
        WHERE 1=1";
        
// Jika role guru, batasi data murid yang ditampilkan
if ($guru_id) {
    $sql .= " AND (k.guru_id = ? OR kq.guru_id = ? OR km.guru_id = ?)";
}

$sql .= " ORDER BY m.nama";

$stmt = $conn->prepare($sql);
if ($guru_id) {
    $stmt->bind_param("iii", $guru_id, $guru_id, $guru_id);
}
$stmt->execute();
$result = $stmt->get_result();
$murid_list = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $murid_list[] = $row;
    }
}
$stmt->close();

// Di bagian "AMBIL DATA UNTUK DITAMPILKAN", tambahkan:

// Ambil semua kelas_quran untuk dropdown filter dengan filter jika guru
$sql_kelas_quran = "SELECT * FROM kelas_quran WHERE 1=1";
if ($guru_id) {
    $sql_kelas_quran .= " AND guru_id = ?";
}
$sql_kelas_quran .= " ORDER BY nama_kelas";

$stmt_kelas_quran = $conn->prepare($sql_kelas_quran);
if ($guru_id) {
    $stmt_kelas_quran->bind_param("i", $guru_id);
}
$stmt_kelas_quran->execute();
$result_kelas_quran = $stmt_kelas_quran->get_result();
$kelas_quran_list = [];
if ($result_kelas_quran->num_rows > 0) {
    while ($row = $result_kelas_quran->fetch_assoc()) {
        $kelas_quran_list[] = $row;
    }
}
$stmt_kelas_quran->close();

// Ambil semua kelas_madin dengan filter jika guru
$sql_kelas = "SELECT kelas_id, nama_kelas FROM kelas_madin WHERE 1=1";
if ($guru_id) {
    $sql_kelas .= " AND guru_id = ?";
}
$sql_kelas .= " ORDER BY nama_kelas";

$stmt_kelas = $conn->prepare($sql_kelas);
if ($guru_id) {
    $stmt_kelas->bind_param("i", $guru_id);
}
$stmt_kelas->execute();
$result_kelas = $stmt_kelas->get_result();
$kelas_list = [];
if ($result_kelas->num_rows > 0) {
    while ($row = $result_kelas->fetch_assoc()) {
        $kelas_list[] = $row;
    }
}
$stmt_kelas->close();

// Ambil semua kamar untuk dropdown
$sql_kamar = "SELECT kamar_id, nama_kamar, kapasitas FROM kamar";
$result_kamar = $conn->query($sql_kamar);
$kamar_list = [];
if ($result_kamar->num_rows > 0) {
    while ($row = $result_kamar->fetch_assoc()) {
        $kamar_list[] = $row;
    }
}

// Hitung jumlah murid per kamar (untuk optimasi)
$sql_count = "SELECT kamar_id, COUNT(*) as jumlah FROM murid GROUP BY kamar_id";
$result_count = $conn->query($sql_count);
$count_per_kamar = [];
if ($result_count->num_rows > 0) {
    while ($row = $result_count->fetch_assoc()) {
        $count_per_kamar[$row['kamar_id']] = $row['jumlah'];
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
    <title>Data Guru & Murid - Sistem Absensi Online</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.5/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    
    <!-- Preload font untuk performa lebih baik -->
    <link rel="preload" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/fonts/bootstrap-icons.woff2" as="font" type="font/woff2" crossorigin>
    <!-- Tambahkan di bagian head -->
    <style>
    /* ===== STYLE UTAMA ===== */
    
    /* Loading Overlay */
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
    
    /* Tab styling */
    .nav-tabs .nav-link.active {
        background-color: #0d6efd;
        color: white;
    }
    
    /* Judul Section */
    .card h4 {
        font-size: 1.4rem;
        margin-bottom: 1rem;
    }
    
    /* Tabel */
    .table th {
        font-size: 0.95rem;
        padding: 12px 8px;
    }
    
    .table td {
        font-size: 0.9rem;
        padding: 10px 8px;
        vertical-align: middle;
    }
    
    /* Ikon dalam Tab */
    .nav-tabs .nav-link i.bi {
        font-size: 0.95rem;
        margin-right: 5px;
    }
    
    /* Style untuk dropdown filter */
    .d-inline-block {
        vertical-align: middle;
    }
    
    .form-select {
        max-width: 200px;
    }
    
    .massal-field {
        transition: all 0.3s ease;
    }
    
    /* Style untuk badge notifikasi */
    #selectedCountBadge {
        font-size: 0.7rem;
        min-width: 20px;
        height: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    /* Animasi untuk badge */
    .badge {
        transition: all 0.3s ease;
    }
    
    /* Style untuk modal count */
    #modalSelectedCount {
        font-weight: bold;
        color: #0d6efd;
    }
    
    /* Pastikan modal backdrop dihapus dengan benar */
    .modal-backdrop {
        z-index: 1040;
    }
    
    .modal {
        z-index: 1050;
    }
    
    /* Pastikan body tidak tetap terkunci setelah modal ditutup */
    body.modal-open {
        overflow: auto;
        padding-right: 0 !important;
    }
    
    /* Style untuk memastikan interaksi normal setelah modal ditutup */
    .dataTables_wrapper {
        position: relative;
        z-index: 1;
    }
    
    /* Pastikan tab tetap berfungsi */
    .nav-tabs .nav-link {
        cursor: pointer;
    }
    
    /* Loading state untuk operasi */
    .operation-loading {
        pointer-events: none;
        opacity: 0.7;
    }
    
    </style>
</head>
<body>
    <!-- Tambahkan di awal body (setelah tag <body>) -->
    <div id="loading-overlay">
        <div class="loading-content">
            <img src="../assets/img/logo_ppma_loading.gif" class="loading-spinner" alt="Loading...">
        </div>
    </div>
    
    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-people me-2"></i> Data Personalia</h2>
        </div>
        
        <!-- Tab Navigasi -->
        <ul class="nav nav-tabs mb-3" id="myTab" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="dataGuru&Pembina-tab" data-bs-toggle="tab" data-bs-target="#dataGuru&Pembina" type="button" role="tab">Data Guru & Pembina</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="dataMurid-tab" data-bs-toggle="tab" data-bs-target="#dataMurid" type="button" role="tab">Data Murid</button>
            </li>
        </ul>
        
        <div class="tab-content" id="dataTabsContent">
            
            <!-- Tab Guru/Pembina -->
            <div class="tab-pane fade <?= $active_tab == 'dataGuru&Pembina' ? 'show active' : '' ?>" id="dataGuru&Pembina" role="tabpanel">
                <div class="d-flex justify-content-between align-items-center mb-3 mt-3">
                    <h4><i class="bi bi-person-badge me-2"></i>Data Guru & Pembina</h4>
                    <!-- Di bagian tombol Guru/Pembina -->
                    <div class="d-flex flex-wrap gap-2 align-items-center">
                        <!-- Tombol Konversi (hanya tampil untuk admin) -->
                        <?php if ($_SESSION['role'] === 'admin'): ?>
                        <a href="?konversi_guru=1" class="btn btn-warning" onclick="return confirm('Yakin ingin mengkonversi semua guru menjadi user?')">
                            <i class="bi bi-arrow-repeat me-1"></i> Konversi ke User
                        </a>
                        <?php endif; ?>
                        
                        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#guruModal" onclick="setModalTambahGuru()">
                            <i class="bi bi-plus-circle me-1"></i> Tambah Guru / Pembina
                        </button>
                    </div>
                </div>

                <div class="card shadow-sm">
                    <div class="card-body">
                        <!-- Tabel Guru - Ubah tampilan -->
                        <div class="table-responsive">
                            <table id="guruTable" class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Foto</th>
                                        <th>Nama</th>
                                        <th>NIP</th>
                                        <th>Jenis Kelamin</th>
                                        <th>Jabatan</th>
                                        <th>Alamat</th> <!-- KOLOM BARU: Alamat -->
                                        <th>WhatsApp</th>
                                        <?php if ($_SESSION['role'] == 'admin' || $_SESSION['role'] == 'staff'): ?>
                                        <th>Aksi</th>
                                        <?php endif; ?>
                                        
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($guru_list as $guru): ?>
                                    <tr>
                                        <td>
                                            <?php if (!empty($guru['foto'])): ?>
                                            <img src="../uploads/<?= htmlspecialchars($guru['foto']) ?>" 
                                                 class="rounded-circle" 
                                                 width="40" 
                                                 height="40" 
                                                 alt="Foto"
                                                 loading="lazy"
                                                 style="cursor:pointer"
                                                 onclick="showPhoto('../uploads/<?= htmlspecialchars($guru['foto']) ?>')">
                                            <?php else: ?>
                                            <img src="../assets/img/default-avatar.png" 
                                                 class="rounded-circle" 
                                                 width="40" 
                                                 height="40" 
                                                 alt="Foto"
                                                 loading="lazy"
                                                 style="cursor:pointer"
                                                 onclick="showPhoto('../assets/img/default-avatar.png')">
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($guru['nama'] ?? '') ?></td>
                                        <td><?= htmlspecialchars($guru['nip'] ?? '') ?></td>
                                        <td><?= htmlspecialchars($guru['jenis_kelamin'] ?? '') ?></td>
                                        <td><?= htmlspecialchars($guru['jabatan'] ?? '') ?></td>
                                        <td><?= htmlspecialchars($guru['alamat'] ?? '') ?></td> <!-- DATA ALAMAT -->
                                        <td>
                                            <?php if (!empty($guru['no_hp'])): ?>
                                            <a href="https://wa.me/<?= htmlspecialchars($guru['no_hp']) ?>" class="btn btn-sm btn-success" target="_blank">
                                                <i class="bi bi-whatsapp"></i>
                                            </a>
                                            <?php endif; ?>
                                        </td>
                                        <?php if ($_SESSION['role'] == 'admin' || $_SESSION['role'] == 'staff'): ?>
                                        <td>
                                            <a href="?edit_guru=<?= $guru['guru_id'] ?>" class="btn btn-sm btn-warning">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <a href="?hapus_guru=<?= $guru['guru_id'] ?>" class="btn btn-sm btn-danger" 
                                               onclick="return confirm('Yakin ingin menghapus data guru ini?')">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        </td>
                                        <?php endif; ?>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab Murid (Data yang sudah ada) -->
            <div class="tab-pane fade <?= $active_tab == 'dataMurid' ? 'show active' : '' ?>" id="dataMurid" role="tabpanel">
                <div class="d-flex justify-content-between align-items-center mb-3 mt-3">
                    <h4><i class="bi bi-person me-2"></i>Data Murid</h4>
                    <div class="d-flex justify-content-between align-items-center mb-3 mt-3 flex-wrap">
                        
                        <div class="d-flex flex-wrap gap-2 align-items-center w-100 w-md-auto justify-content-end">
                            
                            <!-- Di bagian tombol Data Murid -->
                            <div class="d-flex flex-wrap gap-2 align-items-center">
                                
                                
                                <?php if ($_SESSION['role'] == 'admin' || $_SESSION['role'] == 'staff'): ?>
                                <button class="btn btn-primary p-0">
                                    <a href="https://docs.google.com/spreadsheets/d/1o8Q5i4Wk2x2o_kT9Hfaud6DYhbrSznrqX9EVQySfRr0/edit#gid=1913039132" 
                                       target="_blank" 
                                       class="btn btn-primary p-2">
                                        <i class="bi bi-file-earmark-excel me-0"></i> Google Sheet
                                    </a>
                                </button>
                                <?php endif; ?>
                                
                                <button class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#importModal">
                                    <i class="bi bi-upload me-1"></i> Import Excel
                                </button>
                                
                                
                                <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#muridModal" onclick="setModalTambah()">
                                    <i class="bi bi-plus-circle me-1"></i> Tambah Murid
                                </button>
                                
                                <?php if ($_SESSION['role'] == 'admin' || $_SESSION['role'] == 'staff'): ?>
                                <button id="toggleCheckbox" class="btn btn-outline-secondary btn-sm" title="Sembunyikan/Tampilkan pilihan">
                                    <i class="bi bi-list-check"></i> Pilih
                                    <span id="selectedCountBadge" class="badge bg-primary ms-1" style="display: none;">0</span>
                                </button>
                                
                                <!-- Tombol Edit Massal -->
                                <button id="btnEditTerpilih" class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#massalModal" style="display: none;">
                                    <i class="bi bi-pencil-square me-1"></i> Edit Terpilih
                                </button>
                            
                                <!-- Tombol Pindah ke Alumni -->
                                <button type="button" id="btnPindahAlumni" class="btn btn-danger btn-sm" style="display: none;" onclick="openPindahAlumniModal()">
                                    <i class="bi bi-mortarboard me-1"></i> Pindah ke Alumni
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                    </div>
                </div>
            
            
            
                <div class="card shadow-sm">
                    <div class="card-body">
                        <div class="table-responsive">
                            <form id="formMassal" method="POST">
                                <table id="dataTable" class="table table-hover">
                                    <!-- Di bagian thead tabel murid -->
                                    <thead>
                                        <tr>
                                            <?php if ($_SESSION['role'] == 'admin' || $_SESSION['role'] == 'staff'): ?>
                                            <th width="30"><input type="checkbox" id="checkAll"></th>
                                            <?php endif; ?>
                                            <th>Foto</th>
                                            <th>Nama</th>
                                            <th>NIS</th>
                                            <th>Alamat</th>
                                            <th>Madin</th>
                                            <th>Qur'an</th>
                                            <th>Kamar</th>
                                            <th>WA</th>
                                            <?php if ($_SESSION['role'] == 'admin' || $_SESSION['role'] == 'staff'): ?>
                                            <th>Detail</th>
                                            <?php endif; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($murid_list as $murid): ?>
                                        <tr>
                                            <?php if ($_SESSION['role'] == 'admin' || $_SESSION['role'] == 'staff'): ?>
                                            <td>
                                                <input type="checkbox" class="checkbox-murid" name="murid_ids[]" value="<?= $murid['murid_id'] ?>">
                                            </td>
                                            <?php endif; ?>
                                            <td>
                                                <?php if (!empty($murid['foto'])): ?>
                                                <img src="../uploads/<?= htmlspecialchars($murid['foto']) ?>" 
                                                     class="rounded-circle" 
                                                     width="40" 
                                                     height="40" 
                                                     alt="Foto"
                                                     loading="lazy"
                                                     style="cursor:pointer"
                                                     onclick="showPhoto('../uploads/<?= htmlspecialchars($murid['foto']) ?>')">
                                                <?php else: ?>
                                                <img src="../assets/img/default-avatar.png" 
                                                     class="rounded-circle" 
                                                     width="40" 
                                                     height="40" 
                                                     alt="Foto"
                                                     loading="lazy"
                                                     style="cursor:pointer"
                                                     onclick="showPhoto('../assets/img/default-avatar.png')">
                                                <?php endif; ?>
                                            </td>
                                            <td><?= htmlspecialchars($murid['nama'] ?? '') ?></td>
                                            <td><?= htmlspecialchars($murid['nis'] ?? '') ?></td>
                                            <td><?= htmlspecialchars($murid['alamat'] ?? '') ?></td> <!-- DATA ALAMAT -->
                                            <td><?= htmlspecialchars($murid['nama_kelas'] ?? 'Belum ada kelas') ?></td>
                                            <td><?= htmlspecialchars($murid['nama_kelas_quran'] ?? '') ?></td>
                                            <td><?= htmlspecialchars($murid['nama_kamar'] ?? '') ?></td>
                                            <td>
                                                <?php if (!empty($murid['no_hp'])): ?>
                                                <a href="https://wa.me/<?= htmlspecialchars($murid['no_hp']) ?>" class="btn btn-sm btn-success">
                                                    <i class="bi bi-whatsapp"></i>
                                                </a>
                                                <?php endif; ?>
                                            </td>
                                            
                                            <?php if ($_SESSION['role'] == 'admin' || $_SESSION['role'] == 'staff'): ?>
                                            <td>
                                                <a href="?edit=<?= $murid['murid_id'] ?>" class="btn btn-sm btn-warning">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                            </td>
                                            <?php endif; ?>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            
        </div>
    </div>
    
    <!-- Modal Guru/Pembina - PERBAIKAN: Struktur konsisten dengan modal murid -->
    <div class="modal fade" id="guruModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <!-- PERBAIKAN: Gunakan modal-header seperti pada modal murid -->
                <div class="modal-header">
                    <h5 class="modal-title" id="modalGuruTitle">Tambah Data Guru / Pembina</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="" id="formGuru" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" name="id" id="inputGuruId">
                        
                        <!-- PERBAIKAN: Gunakan layout grid seperti modal murid -->
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="nama" id="inputGuruNama" required>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">NIP</label>
                                <input type="text" class="form-control" name="nip" id="inputGuruNip">
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">NIK</label>
                                <input type="text" class="form-control" name="nik" id="inputGuruNik">
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Jenis Kelamin</label>
                                <select class="form-select" name="jenis_kelamin" id="selectGuruJenisKelamin">
                                    <option value="">-- Pilih --</option>
                                    <option value="Laki-laki">Laki-laki</option>
                                    <option value="Perempuan">Perempuan</option>
                                </select>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Jabatan</label>
                                <input type="text" class="form-control" name="jabatan" id="inputGuruJabatan">
                            </div>
                            
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Nomor HP</label>
                                <input type="tel" class="form-control" name="no_hp" id="inputGuruNoHp">
                            </div>
                            
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Alamat</label>
                                <textarea class="form-control" rows="3" name="alamat" id="textareaGuruAlamat"></textarea>
                            </div>
                            
                            <!-- Input Foto untuk Guru -->
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Foto</label>
                                <input type="file" class="form-control" name="foto" id="fotoGuruInput" accept="image/*">
                                <div class="mt-2">
                                    <img src="../assets/img/default-avatar.png" id="fotoGuruPreview" class="img-thumbnail" width="150" loading="lazy">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="tambah_guru" class="btn btn-primary" id="btnSubmitGuruForm">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Modal Import Data Murid -->
    <div class="modal fade" id="importModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Impor Data Murid dari Excel</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="" enctype="multipart/form-data">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Pilih File Excel</label>
                            <input type="file" class="form-control" name="file_murid" accept=".xlsx,.xls" required>
                        </div>
                        
                        <div class="alert alert-info">
                            <strong>Format file Excel:</strong>
                            <ul class="mb-0">
                                <li>Baris pertama: Header</li>
                                <li>Kolom berurutan: 
                                    <ol>
                                        <li>Nama</li>
                                        <li>NIS</li>
                                        <li>ID Kelas Madin</li>
                                        <li>Kamar</li>
                                        <li>No HP</li>
                                        <li>Alamat</li>
                                        <li>Nama Wali</li>
                                        <li>No Wali</li>
                                        <li>Nilai</li>
                                        <li>Foto (opsional)</li>
                                    </ol>
                                </li>
                            </ul>
                            <a href="<?= htmlspecialchars($template_url) ?>" class="btn btn-sm btn-success mt-2" download>
                                <i class="bi bi-download me-1"></i> Download Template
                            </a>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="import_murid" class="btn btn-primary">Import Data</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Tambah/Edit Murid -->
    <div class="modal fade" id="muridModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalMuridTitle">Tambah Data Murid</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="" enctype="multipart/form-data" id="formMurid">
                    <div class="modal-body">
                        <input type="hidden" name="id" id="inputMuridId">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="nama" id="inputNama" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">NIS <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="nis" id="inputNis" required>
                            </div>
                            <!-- Di dalam form muridModal, tambahkan field NIK setelah field NIS -->
                            <div class="col-md-6 mb-3">
                                <label class="form-label">NIK</label>
                                <input type="text" class="form-control" name="nik" id="inputNik">
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Nomor WhatsApp</label>
                                <input type="tel" class="form-control" name="no_hp" id="inputNoHp">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Kelas Qur'an</label>
                                <select class="form-select" name="kelas_quran_id" id="selectKelasQuran">
                                    <option value="">-- Pilih Kelas Qur'an --</option>
                                    <?php 
                                    $sql_kelas_quran = "SELECT * FROM kelas_quran";
                                    $result_kelas_quran = $conn->query($sql_kelas_quran);
                                    while ($row_quran = $result_kelas_quran->fetch_assoc()): 
                                    ?>
                                        <option value="<?= $row_quran['id'] ?>">
                                            <?= htmlspecialchars($row_quran['nama_kelas']) ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Kelas Madin <span class="text-danger">*</span></label>
                                <select class="form-select" name="kelas_id" id="selectKelas" required>
                                    <option value="">-- Pilih Kelas Madin --</option>
                                    <?php foreach ($kelas_list as $kelas): ?>
                                        <option value="<?= $kelas['kelas_id'] ?>">
                                            <?= htmlspecialchars($kelas['nama_kelas']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Kamar</label>
                                <select class="form-select" name="kamar_id" id="selectKamar">
                                    <option value="">-- Pilih Kamar --</option>
                                    <?php foreach ($kamar_list as $kamar): 
                                        $kamar_id = $kamar['kamar_id'];
                                        $count = $count_per_kamar[$kamar_id] ?? 0;
                                        $sisa = $kamar['kapasitas'] - $count;
                                        $disabled = ($sisa <= 0) ? 'disabled' : '';
                                    ?>
                                    <option value="<?= $kamar_id ?>" <?= $disabled ?>>
                                        <?= htmlspecialchars($kamar['nama_kamar']) ?>
                                        (<?= $count ?>/<?= $kamar['kapasitas'] ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="form-text text-muted">Kamar yang penuh akan dinonaktifkan</small>
                            </div>
                            
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Nama Wali</label>
                                <input type="text" class="form-control" name="nama_wali" id="inputNamaWali">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Nomor Wali</label>
                                <input type="tel" class="form-control" name="no_wali" id="inputNoWali">
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Nilai Santri</label>
                                <input type="number" class="form-control" name="nilai" min="0" max="100" id="inputNilai" value="0">
                            </div>
                            
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Alamat</label>
                                <textarea class="form-control" rows="3" name="alamat" id="textareaAlamat"></textarea>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Foto</label>
                                <input type="file" class="form-control" name="foto" id="fotoInput">
                                <div class="mt-2">
                                    <img src="../assets/img/default-avatar.png" id="fotoPreview" class="img-thumbnail" width="150" loading="lazy">
                                </div>
                            </div>
                            
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="tambah_murid" class="btn btn-primary" id="btnSubmitForm">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Modal Foto Besar -->
    <div class="modal fade" id="photoModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-body text-center p-0">
                    <img id="modalPhoto" src="" class="img-fluid" alt="Foto Murid">
                </div>
                <div class="modal-footer justify-content-center">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal Konfirmasi Pindah ke Alumni -->
    <div class="modal fade" id="confirmAlumniModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Pindahkan ke Alumni</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="GET" action="">
                    <input type="hidden" name="hapus" id="hapusMuridId">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Status Keluar</label>
                            <select name="status" class="form-select" required>
                                <option value="Lulus">Lulus</option>
                                <option value="Berhenti">Berhenti</option>
                                <option value="Dikeluarkan">Dikeluarkan</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Keterangan</label>
                            <textarea name="keterangan" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-danger">Pindahkan ke Alumni</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Di bagian tombol Data Murid -->
    <div class="d-flex flex-wrap gap-2 align-items-center">
        
        <!-- Tombol Edit Massal -->
        <button id="btnEditTerpilih" class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#massalModal" style="display: none;">
            <i class="bi bi-pencil-square me-1"></i> Edit Terpilih
        </button>
    
        <!-- Tombol Pindah ke Alumni -->
        <button type="button" id="btnPindahAlumni" class="btn btn-danger btn-sm" style="display: none;" onclick="openPindahAlumniModal()">
            <i class="bi bi-mortarboard me-1"></i> Pindah ke Alumni
        </button>
    </div>
    
    <!-- Modal Pindah ke Alumni -->
    <div class="modal fade" id="pindahAlumniModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Pindahkan ke Alumni</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <div id="pindah-murid-ids"></div>
                        
                        <div class="mb-3">
                            <label class="form-label">Status Keluar</label>
                            <select class="form-select" name="status_keluar" required>
                                <option value="Lulus">Lulus</option>
                                <option value="Berhenti">Berhenti</option>
                                <option value="Dikeluarkan">Dikeluarkan</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Tahun Keluar</label>
                            <input type="number" class="form-control" name="tahun_keluar" value="<?= date('Y') ?>" min="2000" max="2099" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Keterangan</label>
                            <textarea class="form-control" name="keterangan"></textarea>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i> Data murid yang dipilih akan dipindahkan ke data alumni dan dihapus dari data murid.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="pindah_alumni" class="btn btn-danger">Pindahkan ke Alumni</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Modal Edit Massal -->
    <div class="modal fade" id="massalModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Data Massal</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="" id="formMassalModal">
                    <div class="modal-body">
                        <div id="massal-murid-ids"></div>
                        
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i> 
                            Anda akan mengedit data untuk <span id="modalSelectedCount" class="fw-bold">0</span> murid.
                            <br><small>Pilih hanya SATU field yang ingin diupdate.</small>
                        </div>
                        
                        <div class="mb-3 massal-field">
                            <label class="form-label">Kelas Madin</label>
                            <select class="form-select" name="kelas_id" id="massalKelas">
                                <option value="">-- Pilih Kelas Madin --</option>
                                <?php foreach ($kelas_list as $kelas): ?>
                                    <option value="<?= $kelas['kelas_id'] ?>">
                                        <?= htmlspecialchars($kelas['nama_kelas']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3 massal-field">
                            <label class="form-label">Kelas Qur'an</label>
                            <select class="form-select" name="kelas_quran_id" id="massalKelasQuran">
                                <option value="">-- Pilih Kelas Qur'an --</option>
                                <?php foreach ($kelas_quran_list as $kelas): ?>
                                    <option value="<?= $kelas['id'] ?>">
                                        <?= htmlspecialchars($kelas['nama_kelas']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3 massal-field">
                            <label class="form-label">Kamar</label>
                            <select class="form-select" name="kamar_id" id="massalKamar">
                                <option value="">-- Pilih Kamar --</option>
                                <?php foreach ($kamar_list as $kamar): 
                                    $kamar_id = $kamar['kamar_id'];
                                    $count = $count_per_kamar[$kamar_id] ?? 0;
                                    $sisa = $kamar['kapasitas'] - $count;
                                    $disabled = ($sisa <= 0) ? 'disabled' : '';
                                ?>
                                    <option value="<?= $kamar_id ?>" <?= $disabled ?>>
                                        <?= htmlspecialchars($kamar['nama_kamar']) ?> 
                                        (<?= $count ?>/<?= $kamar['kapasitas'] ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="update_massal" class="btn btn-primary">Simpan Perubahan</button>
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
    // ===== VARIABEL GLOBAL =====
    let dataTable;
    
    // ===== FUNGSI UTAMA =====
    function hideLoading() {
        const overlay = document.getElementById('loading-overlay');
        if (overlay) {
            overlay.style.opacity = '0';
            setTimeout(() => overlay.remove(), 300);
        }
    }
    
    function showPhoto(photoUrl) {
        document.getElementById('modalPhoto').src = photoUrl;
        new bootstrap.Modal(document.getElementById('photoModal')).show();
    }
    
    function clearEditParam() {
        const url = new URL(window.location);
        url.searchParams.delete('edit');
        url.searchParams.delete('edit_guru');
        window.history.replaceState({}, document.title, url.toString());
    }
    
    // ===== FUNGSI MODAL GURU =====
    function setModalTambahGuru() {
        document.getElementById('modalGuruTitle').textContent = 'Tambah Data Guru';
        document.getElementById('formGuru').reset();
        document.getElementById('fotoGuruPreview').src = '../assets/img/default-avatar.png';
        document.getElementById('btnSubmitGuruForm').textContent = 'Simpan';
        document.getElementById('btnSubmitGuruForm').name = 'tambah_guru';
        document.getElementById('inputGuruId').value = '';
    }
    
    function setModalEditGuru(id, nama, nip, nik, jenis_kelamin, jabatan, no_hp, alamat, foto = '') {
        document.getElementById('modalGuruTitle').textContent = 'Edit Data Guru';
        document.getElementById('inputGuruId').value = id;
        document.getElementById('inputGuruNama').value = nama;
        document.getElementById('inputGuruNip').value = nip;
        document.getElementById('inputGuruNik').value = nik;
        document.getElementById('selectGuruJenisKelamin').value = jenis_kelamin;
        document.getElementById('inputGuruJabatan').value = jabatan;
        document.getElementById('inputGuruNoHp').value = no_hp;
        document.getElementById('textareaGuruAlamat').value = alamat;
        
        const preview = document.getElementById('fotoGuruPreview');
        preview.src = foto ? '../uploads/' + foto : '../assets/img/default-avatar.png';
        
        const submitBtn = document.getElementById('btnSubmitGuruForm');
        submitBtn.textContent = 'Simpan Perubahan';
        submitBtn.name = 'edit_guru';
    }
    
    // ===== FUNGSI MODAL MURID =====
    function setModalTambah() {
        document.getElementById('modalMuridTitle').textContent = 'Tambah Data Murid';
        document.getElementById('formMurid').reset();
        document.getElementById('fotoPreview').src = '../assets/img/default-avatar.png';
        document.getElementById('btnSubmitForm').textContent = 'Simpan';
        document.getElementById('btnSubmitForm').name = 'tambah_murid';
        document.getElementById('inputMuridId').value = '';
    }
    
    function setModalEdit(id, nama, nis, nik, kelas_id, kelas_quran_id, kamar_id, no_hp, nama_wali, no_wali, alamat, nilai, foto = '') {
        document.getElementById('modalMuridTitle').textContent = 'Edit Data Murid';
        document.getElementById('inputMuridId').value = id;
        document.getElementById('inputNama').value = nama;
        document.getElementById('inputNis').value = nis;
        document.getElementById('inputNik').value = nik;
        document.getElementById('selectKelas').value = kelas_id;
        document.getElementById('selectKelasQuran').value = kelas_quran_id || '';
        document.getElementById('selectKamar').value = kamar_id || '';
        document.getElementById('inputNoHp').value = no_hp;
        document.getElementById('inputNamaWali').value = nama_wali;
        document.getElementById('inputNoWali').value = no_wali;
        document.getElementById('textareaAlamat').value = alamat;
        document.getElementById('inputNilai').value = nilai;
        
        const preview = document.getElementById('fotoPreview');
        preview.src = foto ? '../uploads/' + foto : '../assets/img/default-avatar.png';
        
        const submitBtn = document.getElementById('btnSubmitForm');
        submitBtn.textContent = 'Simpan Perubahan';
        submitBtn.name = 'edit_murid';
    }
    
    // ===== FUNGSI NOTIFIKASI JUMLAH DATA TERPILIH =====
    function updateSelectedCount() {
        const userRole = '<?php echo $_SESSION['role']; ?>';
        
        // Hanya update selected count untuk admin/staff
        if (userRole !== 'admin' && userRole !== 'staff') {
            return;
        }
        
        const checkedCount = document.querySelectorAll('.checkbox-murid:checked').length;
        const btnEdit = document.getElementById('btnEditTerpilih');
        const btnAlumni = document.getElementById('btnPindahAlumni');
        const selectedCountBadge = document.getElementById('selectedCountBadge');
        
        // Update badge count
        if (selectedCountBadge) {
            selectedCountBadge.textContent = checkedCount;
            if (checkedCount > 0) {
                selectedCountBadge.style.display = 'inline-block';
            } else {
                selectedCountBadge.style.display = 'none';
            }
        }
        
        // Update tombol
        if (checkedCount > 0) {
            btnEdit.style.display = 'block';
            btnAlumni.style.display = 'block';
            
            // Update teks tombol dengan jumlah
            btnEdit.innerHTML = `<i class="bi bi-pencil-square me-1"></i> Edit Terpilih (${checkedCount})`;
            btnAlumni.innerHTML = `<i class="bi bi-mortarboard me-1"></i> Pindah ke Alumni (${checkedCount})`;
        } else {
            btnEdit.style.display = 'none';
            btnAlumni.style.display = 'none';
            btnEdit.innerHTML = `<i class="bi bi-pencil-square me-1"></i> Edit Terpilih`;
            btnAlumni.innerHTML = `<i class="bi bi-mortarboard me-1"></i> Pindah ke Alumni`;
        }
    }
    
    // ===== FUNGSI EDIT MASSAL =====
    function openEditMassalModal() {
        const checkboxes = document.querySelectorAll('.checkbox-murid:checked');
        if (checkboxes.length === 0) {
            alert("Pilih setidaknya satu murid untuk diedit.");
            return;
        }
        
        let html = "";
        checkboxes.forEach(checkbox => {
            html += `<input type="hidden" name="murid_ids[]" value="${checkbox.value}">`;
        });
        document.getElementById("massal-murid-ids").innerHTML = html;
        
        // Reset dan tampilkan semua field
        resetMassalFields();
        
        // Setup event listeners untuk field massal
        setupMassalFieldListeners();
        
        new bootstrap.Modal(document.getElementById("massalModal")).show();
    }
    
    function resetMassalFields() {
        const fields = ['massalKelas', 'massalKelasQuran', 'massalKamar'];
        
        fields.forEach(fieldId => {
            const field = document.getElementById(fieldId);
            if (field) {
                field.value = '';
            }
        });
        
        // Tampilkan semua field
        document.querySelectorAll('.massal-field').forEach(field => {
            field.style.display = 'block';
        });
    }
    
    function setupMassalFieldListeners() {
        const fields = ['massalKelas', 'massalKelasQuran', 'massalKamar'];
        
        fields.forEach(fieldId => {
            const field = document.getElementById(fieldId);
            if (field) {
                // Hapus event listener lama untuk menghindari duplikasi
                field.removeEventListener('change', handleMassalFieldChange);
                // Tambahkan event listener baru
                field.addEventListener('change', handleMassalFieldChange);
            }
        });
    }
    
    function handleMassalFieldChange(e) {
        const selectedField = e.target;
        const selectedValue = selectedField.value;
        
        // Jika field dipilih (tidak kosong), sembunyikan field lainnya
        if (selectedValue !== '') {
            document.querySelectorAll('.massal-field').forEach(field => {
                if (field.querySelector('select') !== selectedField) {
                    field.style.display = 'none';
                    // Reset value field yang disembunyikan
                    field.querySelector('select').value = '';
                }
            });
        } else {
            // Jika field dikosongkan, tampilkan semua field
            document.querySelectorAll('.massal-field').forEach(field => {
                field.style.display = 'block';
            });
        }
    }
    
    function openPindahAlumniModal() {
        const checkboxes = document.querySelectorAll('.checkbox-murid:checked');
        if (checkboxes.length === 0) {
            alert("Pilih setidaknya satu murid untuk dipindahkan ke alumni.");
            return;
        }
        
        let html = "";
        checkboxes.forEach(checkbox => {
            html += `<input type="hidden" name="murid_ids[]" value="${checkbox.value}">`;
        });
        document.getElementById("pindah-murid-ids").innerHTML = html;
        new bootstrap.Modal(document.getElementById("pindahAlumniModal")).show();
    }
    
    // ===== KONFIGURASI DATATABLES BERDASARKAN ROLE =====
    function initializeDataTables() {
        const userRole = '<?php echo $_SESSION['role']; ?>';
        
        // Konfigurasi untuk Admin/Staff (dengan checkbox dan aksi)
        const adminColumnDefs = [
            { 
                orderable: false, 
                targets: [0, 1, 8, 9] // checkbox, foto, WA, aksi
            },
            { 
                searchable: false, 
                targets: [0, 1, 8, 9] 
            },
            { width: "30px", targets: 0 },
            { width: "50px", targets: 1 },
            { width: "20%", targets: 2 },
            { width: "15%", targets: 3 },
            { width: "25%", targets: 4 },
            { width: "15%", targets: 5 },
            { width: "15%", targets: 6 },
            { width: "15%", targets: 7 },
            { width: "50px", targets: 8 },
            { width: "80px", targets: 9 }
        ];
        
        // Konfigurasi untuk Guru (tanpa checkbox dan aksi)
        const guruColumnDefs = [
            { 
                orderable: false, 
                targets: [0, 7] // foto, WA
            },
            { 
                searchable: false, 
                targets: [0, 7] 
            },
            { width: "50px", targets: 0 },
            { width: "20%", targets: 1 },
            { width: "15%", targets: 2 },
            { width: "25%", targets: 3 },
            { width: "15%", targets: 4 },
            { width: "15%", targets: 5 },
            { width: "15%", targets: 6 },
            { width: "50px", targets: 7 }
        ];
        
        // Pilih konfigurasi berdasarkan role
        const columnDefs = (userRole === 'admin' || userRole === 'staff') ? adminColumnDefs : guruColumnDefs;
        
        // Inisialisasi DataTables
        return $('#dataTable').DataTable({
            language: { 
                url: '//cdn.datatables.net/plug-ins/1.13.5/i18n/id.json',
                search: "Cari:",
                searchPlaceholder: "Ketik nama, NIS, atau lainnya..."
            },
            deferRender: true,
            scrollCollapse: true,
            scroller: true,
            stateSave: false,
            columnDefs: columnDefs,
            search: {
                return: true,
                regex: false,
                smart: true
            },
            autoWidth: false,
            dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
                 '<"row"<"col-sm-12"tr>>' +
                 '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>'
        });
    }
    
    // ===== FUNGSI UNTUK MEMASTIKAN DATATABLES BERJALAN NORMAL =====
    function ensureDataTablesNormal() {
        if (dataTable) {
            // Redraw table
            dataTable.draw();
            
            const userRole = '<?php echo $_SESSION['role']; ?>';
            
            // Hanya apply event listeners untuk checkbox jika admin/staff
            if (userRole === 'admin' || userRole === 'staff') {
                setTimeout(() => {
                    const checkAll = document.getElementById('checkAll');
                    if (checkAll) {
                        checkAll.addEventListener('click', function() {
                            const checkboxes = document.querySelectorAll('.checkbox-murid');
                            checkboxes.forEach(cb => cb.checked = this.checked);
                            updateSelectedCount();
                        });
                    }
                }, 200);
            }
        }
    }
    
    // ===== INISIALISASI DOKUMEN =====
    document.addEventListener('DOMContentLoaded', function() {
        // Sembunyikan loading setelah halaman siap
        setTimeout(hideLoading, 500);
        
        // Inisialisasi DataTables
        if (typeof $.fn.DataTable !== 'undefined') {
            // DataTables untuk tabel guru
            $('#guruTable').DataTable({
                language: { 
                    url: '//cdn.datatables.net/plug-ins/1.13.5/i18n/id.json',
                    search: "Cari:",
                    searchPlaceholder: "Ketik untuk mencari..."
                },
                dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
                     '<"row"<"col-sm-12"tr>>' +
                     '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
                stateSave: false
            });
            
            // DataTables untuk tabel murid dengan konfigurasi dinamis
            dataTable = initializeDataTables();
            
            // Event untuk memastikan DataTables normal setelah operasi
            if (dataTable) {
                dataTable.on('draw', function() {
                    ensureDataTablesNormal();
                });
            }
        } else {
            console.error('DataTables tidak tersedia');
        }
    
        // ===== EVENT HANDLERS =====
        
        // Preview foto guru
        document.getElementById('fotoGuruInput')?.addEventListener('change', function(e) {
            if (e.target.files[0]) {
                const reader = new FileReader();
                reader.onload = function() {
                    document.getElementById('fotoGuruPreview').src = reader.result;
                };
                reader.readAsDataURL(e.target.files[0]);
            }
        });
    
        // Preview foto murid
        document.getElementById('fotoInput')?.addEventListener('change', function(e) {
            if (e.target.files[0]) {
                const reader = new FileReader();
                reader.onload = function() {
                    document.getElementById('fotoPreview').src = reader.result;
                };
                reader.readAsDataURL(e.target.files[0]);
            }
        });
    
        // Event handler untuk checkbox (hanya untuk admin/staff)
        const userRole = '<?php echo $_SESSION['role']; ?>';
        if (userRole === 'admin' || userRole === 'staff') {
            // Check All functionality
            document.getElementById('checkAll')?.addEventListener('click', function() {
                const checkboxes = document.querySelectorAll('.checkbox-murid');
                checkboxes.forEach(cb => cb.checked = this.checked);
                updateSelectedCount();
            });
    
            // Individual checkbox change
            document.addEventListener('change', function(e) {
                if (e.target.classList.contains('checkbox-murid')) {
                    if (!e.target.checked) {
                        document.getElementById('checkAll').checked = false;
                    }
                    updateSelectedCount();
                }
            });
    
            // Toggle checkbox column visibility
            let checkboxVisible = true;
            document.getElementById('toggleCheckbox')?.addEventListener('click', function() {
                checkboxVisible = !checkboxVisible;
                if (dataTable) {
                    dataTable.column(0).visible(checkboxVisible);
                    
                    if (!checkboxVisible) {
                        document.querySelectorAll('.checkbox-murid').forEach(cb => cb.checked = false);
                        document.getElementById('checkAll').checked = false;
                        updateSelectedCount();
                    }
                    
                    this.classList.toggle('btn-secondary', !checkboxVisible);
                    this.classList.toggle('btn-outline-secondary', checkboxVisible);
                }
            });
            
            // Tombol Edit Terpilih
            document.getElementById('btnEditTerpilih')?.addEventListener('click', function(e) {
                e.preventDefault();
                openEditMassalModal();
            });
        }
    
        // Setup field listeners untuk modal massal
        document.getElementById('massalModal')?.addEventListener('show.bs.modal', function() {
            const checkedCount = document.querySelectorAll('.checkbox-murid:checked').length;
            document.getElementById('modalSelectedCount').textContent = checkedCount;
            setupMassalFieldListeners();
        });
        
        // Event handler untuk form edit massal
        document.getElementById('formMassalModal')?.addEventListener('submit', function(e) {
            const kelasValue = document.getElementById('massalKelas').value;
            const kelasQuranValue = document.getElementById('massalKelasQuran').value;
            const kamarValue = document.getElementById('massalKamar').value;
            
            // Hitung berapa field yang memiliki nilai
            const filledFields = [kelasValue, kelasQuranValue, kamarValue].filter(value => value !== '').length;
            
            if (filledFields === 0) {
                e.preventDefault();
                alert('Pilih satu field untuk diupdate!');
                return false;
            } else if (filledFields > 1) {
                e.preventDefault();
                alert('Hanya boleh memilih satu field untuk diupdate!');
                return false;
            }
            
            return true;
        });
    
        // Auto-open modals for edit
        <?php if ($current_guru): ?>
        setTimeout(() => {
            setModalEditGuru(
                <?= $current_guru['guru_id'] ?>,
                '<?= addslashes($current_guru['nama']) ?>',
                '<?= addslashes($current_guru['nip'] ?? '') ?>',
                '<?= addslashes($current_guru['nik'] ?? '') ?>',
                '<?= addslashes($current_guru['jenis_kelamin'] ?? '') ?>',
                '<?= addslashes($current_guru['jabatan'] ?? '') ?>',
                '<?= addslashes($current_guru['no_hp'] ?? '') ?>',
                '<?= addslashes($current_guru['alamat'] ?? '') ?>',
                '<?= addslashes($current_guru['foto'] ?? '') ?>'
            );
            new bootstrap.Modal(document.getElementById('guruModal')).show();
        }, 100);
        <?php endif; ?>
        
        <?php if ($current_murid): ?>
        setTimeout(() => {
            setModalEdit(
                <?= $current_murid['murid_id'] ?>,
                '<?= addslashes($current_murid['nama']) ?>',
                '<?= addslashes($current_murid['nis']) ?>',
                '<?= addslashes($current_murid['nik'] ?? '') ?>',
                '<?= $current_murid['kelas_madin_id'] ?>',
                '<?= $current_murid['kelas_quran_id'] ?? '' ?>',
                '<?= $current_murid['kamar_id'] ?? '' ?>',
                '<?= addslashes($current_murid['no_hp'] ?? '') ?>',
                '<?= addslashes($current_murid['nama_wali'] ?? '') ?>',
                '<?= addslashes($current_murid['no_wali'] ?? '') ?>',
                '<?= addslashes($current_murid['alamat'] ?? '') ?>',
                '<?= $current_murid['nilai'] ?? 0 ?>',
                '<?= $current_murid['foto'] ?? '' ?>'
            );
            new bootstrap.Modal(document.getElementById('muridModal')).show();
        }, 100);
        <?php endif; ?>
    
        // Inisialisasi jumlah terpilih
        updateSelectedCount();
    });
    
    // Event handler untuk form submission
    document.addEventListener('submit', function(e) {
        const form = e.target;
        
        // Tambahkan loading state sementara
        form.classList.add('operation-loading');
        
        // Hapus loading state setelah timeout (fallback)
        setTimeout(() => {
            form.classList.remove('operation-loading');
        }, 3000);
    });
    </script>
</body>
</html>