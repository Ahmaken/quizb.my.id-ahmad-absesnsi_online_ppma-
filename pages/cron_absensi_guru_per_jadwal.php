<?php
/**
 * CRON JOB UNTUK ABSENSI OTOMATIS GURU PER JADWAL - VERSI DIPERBAIKI
 */

// AKTIFKAN ERROR REPORTING
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/cron_error_log.txt');

// LOG AWAL
error_log("=== CRON ABSENSI GURU PER JADWAL DIMULAI ===");
error_log("Directory: " . __DIR__);

// TENTUKAN PATH YANG BENAR
$base_dir = __DIR__; // /home/quic1934/public_html/ahmad/absesnsi_online_ppma/pages
$init_path = $base_dir . '/../includes/init.php'; // Menuju ke folder includes di atas pages

error_log("Mencoba load: " . $init_path);

try {
    if (!file_exists($init_path)) {
        // Coba alternatif path
        $init_path_alt = $base_dir . '/includes/init.php';
        if (!file_exists($init_path_alt)) {
            throw new Exception("File init.php tidak ditemukan. Tried:\n- $init_path\n- $init_path_alt");
        }
        $init_path = $init_path_alt;
    }
    
    require_once $init_path;
    error_log("✅ File init.php berhasil di-load dari: " . $init_path);
    
} catch (Exception $e) {
    error_log("❌ ERROR: " . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => $e->getMessage(), 'debug_path' => $init_path]);
    exit;
}

// TEST KONEKSI DATABASE
try {
    if (!isset($conn) || !$conn) {
        throw new Exception("Koneksi database NULL");
    }
    
    // Test query sederhana
    $test_query = $conn->query("SELECT 1 as test");
    if (!$test_query) {
        throw new Exception("Test query failed: " . $conn->error);
    }
    
    error_log("✅ Koneksi database berhasil di-test");
    
} catch (Exception $e) {
    error_log("❌ ERROR Database: " . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Database connection failed', 'message' => $e->getMessage()]);
    exit;
}

class AbsensiGuruPerJadwal {
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
        error_log("✅ Class AbsensiGuruPerJadwal initialized");
    }
    
    public function prosesAbsensiOtomatisPerJadwal() {
        error_log("🚀 Memulai proses absensi otomatis per jadwal");
        
        $tanggal = date('Y-m-d');
        $waktu_sekarang = date('Y-m-d H:i:s');
        $hari_indo = $this->getHariIndonesia(date('l'));
        
        error_log("📅 Tanggal: $tanggal, Hari: $hari_indo");
        
        $result = [
            'status' => 'berjalan',
            'tanggal' => $tanggal,
            'waktu' => $waktu_sekarang,
            'hari' => $hari_indo,
            'total_jadwal_diperiksa' => 0,
            'alpa_dicatat' => 0,
            'notifikasi_dikirim' => 0,
            'errors' => []
        ];
        
        try {
            // 1. PROSES JADWAL MADIN
            error_log("📚 Memproses jadwal Madin...");
            $jadwal_madin = $this->getJadwalMadinHariIni($hari_indo);
            error_log("📋 Ditemukan " . count($jadwal_madin) . " jadwal Madin");
            
            foreach ($jadwal_madin as $jadwal) {
                $result['total_jadwal_diperiksa']++;
                
                // Hitung deadline (jadwal + 3 jam)
                $deadline = $this->hitungDeadlineAbsensi($tanggal, $jadwal['jam_mulai'], 3);
                
                error_log("⏰ Jadwal: {$jadwal['mata_pelajaran']} - {$jadwal['nama_kelas']}");
                error_log("   Jam: {$jadwal['jam_mulai']}, Deadline: $deadline, Sekarang: $waktu_sekarang");
                
                // Jika sudah lewat deadline, proses absensi otomatis
                if ($waktu_sekarang > $deadline) {
                    error_log("❌ Deadline terlewati, mencatat alpa...");
                    
                    $alpa_dicatat = $this->prosesAbsensiAlpa(
                        $jadwal['guru_id'], 
                        $tanggal, 
                        $jadwal['jadwal_id'],
                        'madin',
                        "Tidak hadir mengajar {$jadwal['mata_pelajaran']} - {$jadwal['nama_kelas']}"
                    );
                    
                    if ($alpa_dicatat) {
                        $result['alpa_dicatat']++;
                        
                        // Kirim notifikasi
                        if ($this->kirimNotifikasiAlpa($jadwal['guru_id'], $tanggal, $jadwal)) {
                            $result['notifikasi_dikirim']++;
                        }
                    }
                } else {
                    error_log("✅ Masih dalam batas waktu");
                }
            }
            
            // 2. PROSES JADWAL QURAN
            error_log("📖 Memproses jadwal Quran...");
            $jadwal_quran = $this->getJadwalQuranHariIni($hari_indo);
            error_log("📋 Ditemukan " . count($jadwal_quran) . " jadwal Quran");
            
            foreach ($jadwal_quran as $jadwal) {
                $result['total_jadwal_diperiksa']++;
                
                $deadline = $this->hitungDeadlineAbsensi($tanggal, $jadwal['jam_mulai'], 3);
                
                if ($waktu_sekarang > $deadline) {
                    $alpa_dicatat = $this->prosesAbsensiAlpa(
                        $jadwal['guru_id'], 
                        $tanggal, 
                        $jadwal['id'],
                        'quran',
                        "Tidak hadir mengajar Quran {$jadwal['mata_pelajaran']} - {$jadwal['nama_kelas']}"
                    );
                    
                    if ($alpa_dicatat) {
                        $result['alpa_dicatat']++;
                        
                        if ($this->kirimNotifikasiAlpa($jadwal['guru_id'], $tanggal, $jadwal)) {
                            $result['notifikasi_dikirim']++;
                        }
                    }
                }
            }
            
            // 3. PROSES JADWAL KEGIATAN
            error_log("🏠 Memproses jadwal Kegiatan...");
            $jadwal_kegiatan = $this->getJadwalKegiatanHariIni($hari_indo);
            error_log("📋 Ditemukan " . count($jadwal_kegiatan) . " jadwal Kegiatan");
            
            foreach ($jadwal_kegiatan as $jadwal) {
                $result['total_jadwal_diperiksa']++;
                
                $deadline = $this->hitungDeadlineAbsensi($tanggal, $jadwal['jam_mulai'], 3);
                
                if ($waktu_sekarang > $deadline) {
                    $alpa_dicatat = $this->prosesAbsensiAlpa(
                        $jadwal['guru_id'], 
                        $tanggal, 
                        $jadwal['kegiatan_id'],
                        'kegiatan',
                        "Tidak hadir membina kegiatan {$jadwal['nama_kegiatan']} - {$jadwal['nama_kamar']}"
                    );
                    
                    if ($alpa_dicatat) {
                        $result['alpa_dicatat']++;
                        
                        if ($this->kirimNotifikasiAlpa($jadwal['guru_id'], $tanggal, $jadwal)) {
                            $result['notifikasi_dikirim']++;
                        }
                    }
                }
            }
            
            error_log("✅ Proses selesai dengan sukses");
            
        } catch (Exception $e) {
            error_log("❌ ERROR dalam proses: " . $e->getMessage());
            $result['status'] = 'error';
            $result['errors'][] = $e->getMessage();
        }
        
        return $result;
    }
    
    private function hitungDeadlineAbsensi($tanggal, $jam_mulai, $tenggang_jam = 3) {
        return date('Y-m-d H:i:s', strtotime("$tanggal $jam_mulai +$tenggang_jam hours"));
    }
    
    private function prosesAbsensiAlpa($guru_id, $tanggal, $jadwal_id, $jenis_jadwal, $keterangan) {
        try {
            error_log("📝 Memproses absensi alpa untuk Guru ID: $guru_id, Tanggal: $tanggal, Jadwal: $jadwal_id, Jenis: $jenis_jadwal");
            
            // Set kolom berdasarkan jenis jadwal
            $jadwal_madin_id = null;
            $jadwal_quran_id = null;
            $kegiatan_id = null;
            
            switch($jenis_jadwal) {
                case 'madin':
                    $jadwal_madin_id = $jadwal_id;
                    break;
                case 'quran':
                    $jadwal_quran_id = $jadwal_id;
                    break;
                case 'kegiatan':
                    $kegiatan_id = $jadwal_id;
                    break;
            }
            
            // Cek apakah sudah ada absensi untuk guru di tanggal dan jadwal ini
            $sql_check = "SELECT * FROM absensi_guru WHERE guru_id = ? AND tanggal = ? 
                          AND ((jadwal_madin_id = ? AND ? IS NOT NULL) 
                               OR (jadwal_quran_id = ? AND ? IS NOT NULL) 
                               OR (kegiatan_id = ? AND ? IS NOT NULL))";
            $stmt_check = $this->conn->prepare($sql_check);
            
            if (!$stmt_check) {
                throw new Exception("Error prepare check: " . $this->conn->error);
            }
            
            $stmt_check->bind_param("isiiiiii", $guru_id, $tanggal, 
                                   $jadwal_madin_id, $jadwal_madin_id,
                                   $jadwal_quran_id, $jadwal_quran_id,
                                   $kegiatan_id, $kegiatan_id);
            $stmt_check->execute();
            $result_check = $stmt_check->get_result();
            
            if ($result_check->num_rows === 0) {
                // Jika belum ada, insert absensi alpa dengan informasi jadwal
                $sql_insert = "INSERT INTO absensi_guru 
                              (guru_id, tanggal, jadwal_madin_id, jadwal_quran_id, kegiatan_id, 
                               status, keterangan, is_otomatis, deadline_absensi, notifikasi_terkirim) 
                              VALUES (?, ?, ?, ?, ?, 'Alpa', ?, 1, NOW(), 0)";
                $stmt_insert = $this->conn->prepare($sql_insert);
                
                if (!$stmt_insert) {
                    throw new Exception("Error prepare insert: " . $this->conn->error);
                }
                
                $stmt_insert->bind_param("isiiis", $guru_id, $tanggal, 
                                        $jadwal_madin_id, $jadwal_quran_id, $kegiatan_id, 
                                        $keterangan);
                
                if ($stmt_insert->execute()) {
                    error_log("✅ Absensi alpa dicatat: Guru ID $guru_id, Tanggal $tanggal, Jadwal $jadwal_id ($jenis_jadwal)");
                    return true;
                } else {
                    throw new Exception("Error execute insert: " . $stmt_insert->error);
                }
            } else {
                // Jika sudah ada, update menjadi alpa jika status masih Hadir
                $sql_update = "UPDATE absensi_guru SET status = 'Alpa', keterangan = ? 
                              WHERE guru_id = ? AND tanggal = ? 
                              AND ((jadwal_madin_id = ? AND ? IS NOT NULL) 
                                   OR (jadwal_quran_id = ? AND ? IS NOT NULL) 
                                   OR (kegiatan_id = ? AND ? IS NOT NULL))
                              AND status = 'Hadir'";
                $stmt_update = $this->conn->prepare($sql_update);
                
                if (!$stmt_update) {
                    throw new Exception("Error prepare update: " . $this->conn->error);
                }
                
                $stmt_update->bind_param("siiiiiiii", $keterangan, $guru_id, $tanggal,
                                        $jadwal_madin_id, $jadwal_madin_id,
                                        $jadwal_quran_id, $jadwal_quran_id,
                                        $kegiatan_id, $kegiatan_id);
                $stmt_update->execute();
                
                error_log("✅ Absensi diupdate ke alpa: Guru ID $guru_id, Tanggal $tanggal, Jadwal $jadwal_id ($jenis_jadwal)");
                return true;
            }
            
        } catch (Exception $e) {
            error_log("❌ ERROR prosesAbsensiAlpa: " . $e->getMessage());
            return false;
        }
    }
    
    private function kirimNotifikasiAlpa($guru_id, $tanggal, $jadwal) {
        try {
            error_log("📱 Mengirim notifikasi untuk Guru ID: $guru_id");
            
            // Ambil data guru
            $sql_guru = "SELECT * FROM guru WHERE guru_id = ?";
            $stmt_guru = $this->conn->prepare($sql_guru);
            
            if (!$stmt_guru) {
                throw new Exception("Error prepare guru query: " . $this->conn->error);
            }
            
            $stmt_guru->bind_param("i", $guru_id);
            $stmt_guru->execute();
            $guru = $stmt_guru->get_result()->fetch_assoc();
            
            if ($guru && !empty($guru['no_hp'])) {
                // Format pesan notifikasi
                $pesan = "PEMBERITAHUAN ABSENSI ALPA\n";
                $pesan .= "Yth. {$guru['nama']}\n";
                $pesan .= "Anda tercatat ALPA pada:\n";
                $pesan .= "Tanggal: $tanggal\n";
                
                if (isset($jadwal['mata_pelajaran'])) {
                    $pesan .= "Mata Pelajaran: {$jadwal['mata_pelajaran']}\n";
                    if (isset($jadwal['nama_kelas'])) {
                        $pesan .= "Kelas: {$jadwal['nama_kelas']}\n";
                    }
                } elseif (isset($jadwal['nama_kegiatan'])) {
                    $pesan .= "Kegiatan: {$jadwal['nama_kegiatan']}\n";
                    if (isset($jadwal['nama_kamar'])) {
                        $pesan .= "Kamar: {$jadwal['nama_kamar']}\n";
                    }
                }
                
                $pesan .= "\nSilakan hubungi admin jika ada ketidaksesuaian.";
                
                error_log("💬 Pesan notifikasi: " . str_replace("\n", " ", $pesan));
                
                // Untuk testing, kita log saja dulu (non-aktifkan pengiriman sebenarnya)
                $terkirim = $this->kirimWhatsAppTest($guru['no_hp'], $pesan);
                
                if ($terkirim) {
                    // Update status notifikasi
                    $sql_update = "UPDATE absensi_guru SET notifikasi_terkirim = 1 
                                  WHERE guru_id = ? AND tanggal = ?";
                    $stmt_update = $this->conn->prepare($sql_update);
                    
                    if (!$stmt_update) {
                        throw new Exception("Error prepare update notifikasi: " . $this->conn->error);
                    }
                    
                    $stmt_update->bind_param("is", $guru_id, $tanggal);
                    $stmt_update->execute();
                    
                    error_log("✅ Notifikasi berhasil dikirim ke: {$guru['no_hp']}");
                    return true;
                }
            } else {
                error_log("⚠️ Guru tidak ditemukan atau no HP kosong");
            }
            
            return false;
            
        } catch (Exception $e) {
            error_log("❌ ERROR kirimNotifikasiAlpa: " . $e->getMessage());
            return false;
        }
    }
    
    private function kirimWhatsAppTest($no_hp, $pesan) {
        // UNTUK TESTING: Hanya log, tidak benar-benar kirim
        error_log("📤 TEST WhatsApp ke $no_hp: " . substr($pesan, 0, 100) . "...");
        
        // Simulasi pengiriman berhasil untuk testing
        return true;
        
        /*
        // KODE ASLI UNTUK PRODUKSI:
        $api_url = "https://api.whatsapp.com/send"; // Ganti dengan API yang sesuai
        $data = [
            'phone' => $no_hp,
            'message' => $pesan
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $api_url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return ($http_code == 200);
        */
    }
    
    private function getHariIndonesia($hariInggris) {
        $day_map = [
            'Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu',
            'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu', 
            'Sunday' => 'Ahad'
        ];
        return $day_map[$hariInggris] ?? $hariInggris;
    }
    
    private function getJadwalMadinHariIni($hari) {
        try {
            $sql = "SELECT jm.*, km.nama_kelas 
                    FROM jadwal_madin jm 
                    JOIN kelas_madin km ON jm.kelas_madin_id = km.kelas_id 
                    WHERE jm.hari = ? AND jm.guru_id IS NOT NULL";
            $stmt = $this->conn->prepare($sql);
            
            if (!$stmt) {
                throw new Exception("Error prepare jadwal madin: " . $this->conn->error);
            }
            
            $stmt->bind_param("s", $hari);
            $stmt->execute();
            return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            
        } catch (Exception $e) {
            error_log("❌ ERROR getJadwalMadinHariIni: " . $e->getMessage());
            return [];
        }
    }
    
    private function getJadwalQuranHariIni($hari) {
        try {
            $sql = "SELECT jq.*, kq.nama_kelas 
                    FROM jadwal_quran jq 
                    JOIN kelas_quran kq ON jq.kelas_quran_id = kq.id 
                    WHERE jq.hari = ? AND jq.guru_id IS NOT NULL";
            $stmt = $this->conn->prepare($sql);
            
            if (!$stmt) {
                throw new Exception("Error prepare jadwal quran: " . $this->conn->error);
            }
            
            $stmt->bind_param("s", $hari);
            $stmt->execute();
            return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            
        } catch (Exception $e) {
            error_log("❌ ERROR getJadwalQuranHariIni: " . $e->getMessage());
            return [];
        }
    }
    
    private function getJadwalKegiatanHariIni($hari) {
        try {
            $sql = "SELECT jk.*, k.nama_kamar 
                    FROM jadwal_kegiatan jk 
                    JOIN kamar k ON jk.kamar_id = k.kamar_id 
                    WHERE jk.hari = ? AND jk.guru_id IS NOT NULL";
            $stmt = $this->conn->prepare($sql);
            
            if (!$stmt) {
                throw new Exception("Error prepare jadwal kegiatan: " . $this->conn->error);
            }
            
            $stmt->bind_param("s", $hari);
            $stmt->execute();
            return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            
        } catch (Exception $e) {
            error_log("❌ ERROR getJadwalKegiatanHariIni: " . $e->getMessage());
            return [];
        }
    }
}

// JALANKAN PROSES DENGAN ERROR HANDLING
try {
    error_log("🎯 Membuat instance AbsensiGuruPerJadwal...");
    $absensiOtomatis = new AbsensiGuruPerJadwal($conn);
    
    error_log("🔥 Menjalankan proses utama...");
    $result = $absensiOtomatis->prosesAbsensiOtomatisPerJadwal();
    
    // Log hasil
    $log_message = "[" . date('Y-m-d H:i:s') . "] CRON Absensi Guru Per Jadwal: " . json_encode($result);
    error_log($log_message);
    
    // Tulis ke file log
    file_put_contents(__DIR__ . '/cron_absensi_guru_per_jadwal_log.txt', $log_message . PHP_EOL, FILE_APPEND | LOCK_EX);
    
    // Output untuk browser
    if (php_sapi_name() === 'cli') {
        echo "CRON Absensi Guru Per Jadwal selesai:\n";
        print_r($result);
    } else {
        header('Content-Type: application/json');
        echo json_encode($result, JSON_PRETTY_PRINT);
    }
    
    error_log("✅ CRON berhasil dijalankan");
    
} catch (Exception $e) {
    $error_msg = "❌ ERROR utama: " . $e->getMessage();
    error_log($error_msg);
    
    if (php_sapi_name() === 'cli') {
        echo $error_msg . "\n";
    } else {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['error' => $error_msg], JSON_PRETTY_PRINT);
    }
}

error_log("=== CRON ABSENSI GURU PER JADWAL SELESAI ===\n");
?>