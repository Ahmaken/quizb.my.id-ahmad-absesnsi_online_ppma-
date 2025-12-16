<?php
// api_sync.php - API untuk sinkronisasi data dengan Google Sheets dan Absensi_online_PPMA
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
// Di bagian atas api_sync.php, tambahkan:
header('X-Auto-Sync: enabled');
ini_set('max_execution_time', 300); // 5 menit untuk sinkronisasi besar

// Include config database
require_once 'includes/config.php';

// Konfigurasi error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0); // Nonaktifkan display error, tapi log tetap aktif

$action = $_GET['action'] ?? 'test';
$response = [];

// =============================================
// FUNGSI UTILITAS - DIPERBAIKI
// =============================================

/**
 * Mengambil data dari API PPMA dengan error handling yang lebih baik
 */
function getDataFromPPMA($endpoint) {
    try {
        $url = 'https://quizb.my.id/ahmad/absesnsi_online_ppma/api.php?action=' . $endpoint;
        
        $options = [
            'http' => [
                'method' => 'GET',
                'timeout' => 30,
                'ignore_errors' => true
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false
            ]
        ];
        
        $context = stream_context_create($options);
        $response = @file_get_contents($url, false, $context);
        
        if ($response === FALSE) {
            error_log("Gagal mengakses API PPMA: $endpoint");
            return ['success' => false, 'error' => 'Tidak dapat terhubung ke API PPMA'];
        }
        
        $data = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("Response JSON invalid dari API PPMA: " . json_last_error_msg());
            return ['success' => false, 'error' => 'Format response tidak valid'];
        }
        
        return $data;
        
    } catch (Exception $e) {
        error_log("Exception di getDataFromPPMA: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Fungsi generik untuk sinkronisasi data tabel - DIPERBAIKI
 */
function syncTableData($conn, $tableName, $data, $idField, $fields) {
    if (!$data || !is_array($data) || empty($data)) {
        return ['success' => true, 'message' => "Tidak ada data untuk sinkronisasi $tableName", 'count' => 0];
    }

    $count = 0;
    $successCount = 0;
    $errors = [];
    
    foreach ($data as $row) {
        try {
            if (!isset($row[$idField])) {
                $errors[] = "ID field '$idField' tidak ditemukan";
                continue;
            }
            
            // Cek apakah data sudah ada
            $checkStmt = $conn->prepare("SELECT $idField FROM $tableName WHERE $idField = ?");
            $checkStmt->bind_param('i', $row[$idField]);
            $checkStmt->execute();
            $existing = $checkStmt->get_result()->fetch_assoc();
            $checkStmt->close();

            if ($existing) {
                // UPDATE data existing
                $setParts = [];
                $values = [];
                $types = '';
                
                foreach ($fields as $field) {
                    if (isset($row[$field]) && $field !== $idField) {
                        $setParts[] = "$field = ?";
                        $values[] = $row[$field];
                        
                        // Tentukan tipe data
                        if (is_int($row[$field])) $types .= 'i';
                        elseif (is_float($row[$field])) $types .= 'd';
                        else $types .= 's';
                    }
                }
                
                if (!empty($setParts)) {
                    $values[] = $row[$idField];
                    $types .= 'i'; // untuk WHERE clause
                    
                    $sql = "UPDATE $tableName SET " . implode(', ', $setParts) . " WHERE $idField = ?";
                    $updateStmt = $conn->prepare($sql);
                    $updateStmt->bind_param($types, ...$values);
                    
                    if ($updateStmt->execute()) {
                        $successCount++;
                    } else {
                        $errors[] = "Gagal update: " . $updateStmt->error;
                    }
                    $updateStmt->close();
                }
            } else {
                // INSERT data baru
                $columns = [];
                $placeholders = [];
                $values = [];
                $types = '';
                
                // Sertakan ID field
                $columns[] = $idField;
                $placeholders[] = '?';
                $values[] = $row[$idField];
                $types .= 'i';
                
                foreach ($fields as $field) {
                    if (isset($row[$field]) && $field !== $idField) {
                        $columns[] = $field;
                        $placeholders[] = '?';
                        $values[] = $row[$field];
                        
                        if (is_int($row[$field])) $types .= 'i';
                        elseif (is_float($row[$field])) $types .= 'd';
                        else $types .= 's';
                    }
                }
                
                $sql = "INSERT INTO $tableName (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
                $insertStmt = $conn->prepare($sql);
                $insertStmt->bind_param($types, ...$values);
                
                if ($insertStmt->execute()) {
                    $successCount++;
                } else {
                    $errors[] = "Gagal insert: " . $insertStmt->error;
                }
                $insertStmt->close();
            }
            
            $count++;
            
        } catch (Exception $e) {
            $errors[] = "Error pada data {$row[$idField]}: " . $e->getMessage();
        }
    }
    
    $message = "Sinkronisasi $tableName: {$successCount}/{$count} berhasil";
    if (!empty($errors)) {
        $message .= ". Error: " . implode(', ', array_slice($errors, 0, 3));
    }
    
    return [
        'success' => $successCount > 0,
        'message' => $message,
        'count' => $successCount,
        'total' => $count,
        'errors' => $errors
    ];
}

// =============================================
// FUNGSI SINKRONISASI SPESIFIK
// =============================================

function syncKelasMadin($conn) {
    echo "🔄 Sync data kelas_madin via API...\n";
    $data = getDataFromPPMA('/kelas-madin');
    return syncTableData($conn, 'kelas_madin', $data, 'kelas_id', ['nama_kelas', 'guru_id'], 'si');
}

function syncKamar($conn) {
    echo "🔄 Sync data kamar via API...\n";
    $data = getDataFromPPMA('/kamar');
    return syncTableData($conn, 'kamar', $data, 'kamar_id', ['nama_kamar', 'kapasitas', 'keterangan', 'guru_id'], 'siss');
}

function syncKelasQuran($conn) {
    echo "🔄 Sync data kelas_quran via API...\n";
    $data = getDataFromPPMA('/kelas-quran');
    return syncTableData($conn, 'kelas_quran', $data, 'id', ['nama_kelas', 'guru_id'], 'si');
}

function syncUsers($conn) {
    echo "🔄 Sync data users via API...\n";
    $data = getDataFromPPMA('/users');
    
    if (!$data) {
        return ['success' => false, 'message' => 'Gagal ambil data users dari PPMA'];
    }

    $count = 0;
    $successCount = 0;
    
    foreach ($data as $user) {
        try {
            // Handle password field khusus
            if (!isset($user['password']) || empty($user['password'])) {
                $user['password'] = password_hash('default123', PASSWORD_DEFAULT);
            }
            
            $checkStmt = $conn->prepare('SELECT id FROM users WHERE id = ?');
            $checkStmt->bind_param('i', $user['id']);
            $checkStmt->execute();
            $existing = $checkStmt->get_result()->fetch_assoc();
            $checkStmt->close();

            if ($existing) {
                // Update tanpa mengubah password jika tidak disediakan
                $updateFields = [];
                $updateValues = [];
                $types = '';
                
                foreach ($user as $key => $value) {
                    if ($key !== 'id' && $key !== 'password') {
                        $updateFields[] = "$key = ?";
                        $updateValues[] = $value;
                        $types .= 's';
                    }
                }
                
                if (!empty($updateFields)) {
                    $updateValues[] = $user['id'];
                    $types .= 'i';
                    $sql = "UPDATE users SET " . implode(', ', $updateFields) . " WHERE id = ?";
                    $updateStmt = $conn->prepare($sql);
                    $updateStmt->bind_param($types, ...$updateValues);
                    $updateStmt->execute();
                    $updateStmt->close();
                }
            } else {
                // Insert baru
                $columns = implode(', ', array_keys($user));
                $placeholders = implode(', ', array_fill(0, count($user), '?'));
                $types = str_repeat('s', count($user));
                
                $insertStmt = $conn->prepare("INSERT INTO users ($columns) VALUES ($placeholders)");
                $insertStmt->bind_param($types, ...array_values($user));
                $insertStmt->execute();
                $insertStmt->close();
            }
            
            $successCount++;
        } catch (Exception $e) {
            error_log("Error sync users ID {$user['id']}: " . $e->getMessage());
        }
        $count++;
    }
    
    return [
        'success' => true, 
        'message' => "Berhasil sinkron {$successCount}/{$count} data users", 
        'count' => $successCount
    ];
}

function syncPengaturanNotifikasi($conn) {
    echo "🔄 Sync data pengaturan_notifikasi via API...\n";
    $data = getDataFromPPMA('/pengaturan-notifikasi');
    return syncTableData($conn, 'pengaturan_notifikasi', $data, 'id', ['nama_pengaturan', 'nilai', 'deskripsi'], 'sss');
}

function syncAbsensiGuru($conn) {
    echo "🔄 Sync data absensi_guru via API...\n";
    $data = getDataFromPPMA('/absensi-guru');
    return syncTableData($conn, 'absensi_guru', $data, 'absensi_id', [
        'guru_id', 'jadwal_madin_id', 'jadwal_quran_id', 'kegiatan_id', 
        'tanggal', 'waktu_absensi', 'deadline_absensi', 'status', 
        'keterangan', 'is_otomatis', 'notifikasi_terkirim', 'bisa_diubah'
    ], 'iiissssssiii');
}

function syncPengaturanAbsensiOtomatis($conn) {
    echo "🔄 Sync data pengaturan_absensi_otomatis via API...\n";
    $data = getDataFromPPMA('/pengaturan-absensi-otomatis');
    return syncTableData($conn, 'pengaturan_absensi_otomatis', $data, 'id', ['nama_pengaturan', 'nilai', 'deskripsi'], 'sss');
}

function syncJadwalMadin($conn) {
    echo "🔄 Sync data jadwal_madin via API...\n";
    $data = getDataFromPPMA('/jadwal-madin');
    return syncTableData($conn, 'jadwal_madin', $data, 'jadwal_id', [
        'hari', 'jam_mulai', 'jam_selesai', 'mata_pelajaran', 'kelas_madin_id', 'guru_id'
    ], 'ssssii');
}

function syncJadwalQuran($conn) {
    echo "🔄 Sync data jadwal_quran via API...\n";
    $data = getDataFromPPMA('/jadwal-quran');
    return syncTableData($conn, 'jadwal_quran', $data, 'jadwal_id', [
        'hari', 'jam_mulai', 'jam_selesai', 'mata_pelajaran', 'kelas_quran_id', 'guru_id'
    ], 'ssssii');
}

function syncJadwalKegiatan($conn) {
    echo "🔄 Sync data jadwal_kegiatan via API...\n";
    $data = getDataFromPPMA('/jadwal-kegiatan');
    return syncTableData($conn, 'jadwal_kegiatan', $data, 'kegiatan_id', [
        'nama_kegiatan', 'hari', 'jam_mulai', 'jam_selesai', 'deskripsi', 'penanggung_jawab'
    ], 'ssssss');
}

function syncLoginAttempts($conn) {
    echo "🔄 Sync data login_attempts via API...\n";
    $data = getDataFromPPMA('/login-attempts');
    return syncTableData($conn, 'login_attempts', $data, 'attempt_id', [
        'user_id', 'username', 'attempt_time', 'ip_address', 'user_agent', 'success'
    ], 'issisi');
}

// =============================================
// FUNGSI GET DATA UNTUK GOOGLE SHEETS - DIPERBAIKI
// =============================================

/**
 * Fungsi generik untuk mengambil data dari tabel
 */
function getTableData($conn, $query, $params = []) {
    try {
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            throw new Exception("Query preparation failed: " . $conn->error);
        }
        
        if (!empty($params)) {
            $types = '';
            $boundParams = [];
            
            foreach ($params as $param) {
                if (is_int($param)) {
                    $types .= 'i';
                } elseif (is_float($param)) {
                    $types .= 'd';
                } else {
                    $types .= 's';
                }
                $boundParams[] = $param;
            }
            
            $stmt->bind_param($types, ...$boundParams);
        }
        
        if (!$stmt->execute()) {
            throw new Exception("Query execution failed: " . $stmt->error);
        }
        
        $result = $stmt->get_result();
        $data = [];
        
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        
        $stmt->close();
        return $data;
        
    } catch (Exception $e) {
        error_log("Error in getTableData: " . $e->getMessage() . " - Query: " . $query);
        return [];
    }
}

// =============================================
// FUNGSI GET DATA SPESIFIK
// =============================================

function getMuridData($conn, $limit = 100000) {
    $query = "
        SELECT 
            murid_id, nama, nis, nik,
            no_hp, alamat, nama_wali, no_wali, nilai, foto,
            kelas_madin_id, kelas_quran_id, kamar_id,
            created_at, updated_at
        FROM murid 
        ORDER BY nama 
        LIMIT ?
    ";
    return getTableData($conn, $query, [$limit]);
}

function getGuruData($conn) {
    $query = "
        SELECT guru_id, nama, nip, nik, jenis_kelamin, 
               no_hp, alamat, jabatan, foto
        FROM guru 
        ORDER BY nama
    ";
    return getTableData($conn, $query);
}

function getAbsensiData($conn, $limit = 100000) {
    $query = "
        SELECT 
            a.absensi_id, m.nama, m.nis,
            jm.mata_pelajaran, jm.hari,
            a.tanggal, a.status, a.keterangan,
            k.nama_kelas,
            a.created_at
        FROM absensi a
        JOIN murid m ON a.murid_id = m.murid_id
        JOIN jadwal_madin jm ON a.jadwal_madin_id = jm.jadwal_id
        LEFT JOIN kelas_madin k ON m.kelas_madin_id = k.kelas_id
        ORDER BY a.tanggal DESC 
        LIMIT ?
    ";
    return getTableData($conn, $query, [$limit]);
}

// Tambahkan fungsi lainnya dengan pattern yang sama...

function getPelanggaranData($conn, $limit = 100000) {
    $query = "
        SELECT 
            p.pelanggaran_id, m.nama, m.nis,
            p.jenis, p.tanggal, p.deskripsi,
            p.created_at
        FROM pelanggaran p
        JOIN murid m ON p.murid_id = m.murid_id
        ORDER BY p.tanggal DESC 
        LIMIT ?
    ";
    return getTableData($conn, $query, [$limit]);
}

function getPerizinanData($conn, $limit = 100000) {
    $query = "
        SELECT 
            p.perizinan_id, m.nama, m.nis,
            p.jenis, p.tanggal, p.deskripsi,
            p.status_izin, p.created_at
        FROM perizinan p
        JOIN murid m ON p.murid_id = m.murid_id
        ORDER BY p.tanggal DESC 
        LIMIT ?
    ";
    return getTableData($conn, $query, [$limit]);
}

function getAlumniData($conn, $limit = 100000) {
    $query = "
        SELECT 
            alumni_id, nama, nis, nik,
            no_hp, alamat, tahun_masuk,
            tahun_keluar, status_keluar, keterangan,
            pekerjaan, pendidikan_lanjut, foto
        FROM alumni 
        ORDER BY tahun_keluar DESC
        LIMIT ?
    ";
    return getTableData($conn, $query, [$limit]);
}

// Di bagian "FUNGSI GET DATA SPESIFIK" (sekitar baris 250-300)
function getJadwalKegiatanData($conn, $limit = 100000) {
    $query = "
        SELECT 
            kegiatan_id, nama_kegiatan, hari, 
            jam_mulai, jam_selesai, deskripsi, 
            penanggung_jawab, created_at, updated_at
        FROM jadwal_kegiatan 
        ORDER BY hari, jam_mulai 
        LIMIT ?
    ";
    return getTableData($conn, $query, [$limit]);
}

function getJadwalQuranData($conn, $limit = 100000) {
    $query = "
        SELECT 
            jq.id as jadwal_id, kq.nama_kelas,
            jq.hari, jq.jam_mulai, jq.jam_selesai,
            jq.mata_pelajaran, g.nama as nama_guru,
            jq.created_at, jq.updated_at
        FROM jadwal_quran jq
        LEFT JOIN kelas_quran kq ON jq.kelas_quran_id = kq.id
        LEFT JOIN guru g ON jq.guru_id = g.guru_id
        ORDER BY jq.hari, jq.jam_mulai 
        LIMIT ?
    ";
    return getTableData($conn, $query, [$limit]);
}

function getJadwalMadinData($conn, $limit = 100000) {
    $query = "
        SELECT 
            jm.jadwal_id, km.nama_kelas,
            jm.hari, jm.jam_mulai, jm.jam_selesai,
            jm.mata_pelajaran, g.nama as nama_guru,
            jm.created_at, jm.updated_at
        FROM jadwal_madin jm
        LEFT JOIN kelas_madin km ON jm.kelas_madin_id = km.kelas_id
        LEFT JOIN guru g ON jm.guru_id = g.guru_id
        ORDER BY jm.hari, jm.jam_mulai 
        LIMIT ?
    ";
    return getTableData($conn, $query, [$limit]);
}

// =============================================
// LOGIC UTAMA DENGAN ERROR HANDLING YANG LEBIH BAIK
// =============================================

try {
    // Validasi koneksi database
    if (!$conn || $conn->connect_error) {
        throw new Exception("Koneksi database gagal: " . ($conn->connect_error ?? 'Unknown error'));
    }

    switch($action) {
        case 'test':
            // Test koneksi database yang lebih sederhana
            $response = [
                'success' => true, 
                'message' => 'Koneksi database berhasil',
                'timestamp' => date('Y-m-d H:i:s')
            ];
            break;
            
            // ================= SINKRONISASI DARI PPMA =================
            case 'sync_kelas_madin':
                $response = syncKelasMadin($conn);
                break;
                
            case 'sync_kamar':
                $response = syncKamar($conn);
                break;
                
            case 'sync_kelas_quran':
                $response = syncKelasQuran($conn);
                break;
                
            case 'sync_users':
                $response = syncUsers($conn);
                break;
                
            case 'sync_pengaturan_notifikasi':
                $response = syncPengaturanNotifikasi($conn);
                break;
                
            case 'sync_jadwal_kegiatan':
                $response = syncJadwalKegiatan($conn);
                break;
                
            case 'sync_jadwal_quran':
                $response = syncJadwalQuran($conn);
                break;
                
            case 'sync_jadwal_madin':
                $response = syncJadwalMadin($conn);
                break;
                
            case 'sync_absensi_guru':
                $response = syncAbsensiGuru($conn);
                break;
                
            case 'sync_pengaturan_absensi_otomatis':
                $response = syncPengaturanAbsensiOtomatis($conn);
                break;
                
            case 'sync_login_attempts':
                $response = syncLoginAttempts($conn);
                break;
                
            case 'sync_all_ppma':
                $results = [];
                $results[] = syncKelasMadin($conn);
                $results[] = syncKamar($conn);
                $results[] = syncKelasQuran($conn);
                $results[] = syncUsers($conn);
                $results[] = syncJadwalKegiatan($conn);  // TAMBAHKAN
                $results[] = syncJadwalQuran($conn);     // TAMBAHKAN
                $results[] = syncJadwalMadin($conn);     // TAMBAHKAN
                $results[] = syncAbsensiGuru($conn);     // TAMBAHKAN
                
                $response = [
                    'success' => true,
                    'message' => 'Sinkronisasi semua data dari PPMA selesai',
                    'results' => $results,
                    'timestamp' => date('Y-m-d H:i:s')
                ];
                break;
                
            case 'sync_all_complete':
                // Sinkronisasi SEMUA data termasuk yang baru
                $results = [];
                $results[] = syncKelasMadin($conn);
                $results[] = syncKamar($conn);
                $results[] = syncKelasQuran($conn);
                $results[] = syncUsers($conn);
                $results[] = syncPengaturanNotifikasi($conn);
                $results[] = syncAbsensiGuru($conn);
                $results[] = syncPengaturanAbsensiOtomatis($conn);
                $results[] = syncJadwalMadin($conn);
                $results[] = syncJadwalQuran($conn);
                $results[] = syncJadwalKegiatan($conn);
                $results[] = syncLoginAttempts($conn);
                
                $response = [
                    'success' => true,
                    'message' => 'Sinkronisasi SEMUA data dari PPMA selesai',
                    'results' => $results,
                    'timestamp' => date('Y-m-d H:i:s')
                ];
                break;
            
            // Tambahkan di api_sync.php di bagian switch case
            case 'get_ref_kelas_madin':
                $data = getTableData($conn, "SELECT kelas_id, nama_kelas FROM kelas_madin ORDER BY nama_kelas");
                $response = [
                    'success' => true, 
                    'data' => $data, 
                    'total' => count($data)
                ];
                break;
            
            case 'get_ref_kamar':
                $data = getTableData($conn, "SELECT kamar_id, nama_kamar FROM kamar ORDER BY nama_kamar");
                $response = [
                    'success' => true, 
                    'data' => $data, 
                    'total' => count($data)
                ];
                break;
            
            case 'get_ref_kelas_quran':
                $data = getTableData($conn, "SELECT id, nama_kelas FROM kelas_quran ORDER BY nama_kelas");
                $response = [
                    'success' => true, 
                    'data' => $data, 
                    'total' => count($data)
                ];
                break;
            
        // ================= GET DATA UNTUK GOOGLE SHEETS =================
        case 'get_murid':
            $limit = intval($_GET['limit'] ?? 100000);
            $data = getMuridData($conn, $limit);
            $response = [
                'success' => true, 
                'data' => $data, 
                'total' => count($data),
                'message' => 'Data murid berhasil diambil'
            ];
            break;
            
        case 'get_guru':
            $data = getGuruData($conn);
            $response = [
                'success' => true, 
                'data' => $data, 
                'total' => count($data),
                'message' => 'Data guru berhasil diambil'
            ];
            break;
            
        case 'get_users':
            $data = getTableData($conn, 
                "SELECT id, username, role, kelas_id, murid_id, dark_mode, foto_profil, 
                        email, is_active, last_login, nama, nip, created_at, updated_at 
                 FROM users ORDER BY id"
            );
            $response = [
                'success' => true, 
                'data' => $data, 
                'total' => count($data),
                'message' => 'Data users berhasil diambil'
            ];
            break;
            
        // Tambahkan case lainnya dengan format yang sama...
        
        case 'get_absensi':
            $limit = intval($_GET['limit'] ?? 100000);
            $data = getAbsensiData($conn, $limit);
            $response = [
                'success' => true, 
                'data' => $data, 
                'total' => count($data),
                'message' => 'Data absensi berhasil diambil'
            ];
            break;

        // Tambahkan case lainnya...

            
        case 'get_pelanggaran':
            $limit = intval($_GET['limit'] ?? 100000);
            $data = getPelanggaranData($conn, $limit);
            $response = [
                'success' => true, 
                'data' => $data, 
                'total' => count($data),
                'message' => 'Data pelanggaran berhasil diambil'
            ];
            break;
            
        case 'get_perizinan':
            $limit = intval($_GET['limit'] ?? 100000);
            $data = getPerizinanData($conn, $limit);
            $response = [
                'success' => true, 
                'data' => $data, 
                'total' => count($data),
                'message' => 'Data perizinan berhasil diambil'
            ];
            break;
            
        case 'get_alumni':
            $limit = intval($_GET['limit'] ?? 100000);
            $data = getAlumniData($conn, $limit);
            $response = [
                'success' => true, 
                'data' => $data, 
                'total' => count($data),
                'message' => 'Data alumni berhasil diambil'
            ];
            break;
            
        case 'get_pengaturan_notifikasi':
            $data = getTableData($conn, "SELECT * FROM pengaturan_notifikasi ORDER BY id");
            $response = [
                'success' => true, 
                'data' => $data, 
                'total' => count($data),
                'message' => 'Data pengaturan notifikasi berhasil diambil'
            ];
            break;
            
        case 'get_absensi_guru':
            $limit = intval($_GET['limit'] ?? 100000);
            $query = "SELECT ag.*, g.nama as nama_guru 
                      FROM absensi_guru ag 
                      LEFT JOIN guru g ON ag.guru_id = g.guru_id 
                      ORDER BY ag.tanggal DESC 
                      LIMIT ?";
            $data = getTableData($conn, $query, [$limit]);
            $response = [
                'success' => true, 
                'data' => $data, 
                'total' => count($data),
                'message' => 'Data absensi guru berhasil diambil'
            ];
            break;
            
        case 'get_pengaturan_absensi_otomatis':
            $data = getTableData($conn, "SELECT * FROM pengaturan_absensi_otomatis ORDER BY id");
            $response = [
                'success' => true, 
                'data' => $data, 
                'total' => count($data),
                'message' => 'Data pengaturan absensi otomatis berhasil diambil'
            ];
            break;
            
        case 'get_jadwal_madin':
            $query = "SELECT jm.*, km.nama_kelas, g.nama as nama_guru 
                      FROM jadwal_madin jm 
                      LEFT JOIN kelas_madin km ON jm.kelas_madin_id = km.kelas_id 
                      LEFT JOIN guru g ON jm.guru_id = g.guru_id 
                      ORDER BY jm.hari, jm.jam_mulai";
            $data = getTableData($conn, $query);
            $response = [
                'success' => true, 
                'data' => $data, 
                'total' => count($data),
                'message' => 'Data jadwal madin berhasil diambil'
            ];
            break;
            
        case 'get_jadwal_quran':
            $query = "SELECT jq.*, kq.nama_kelas, g.nama as nama_guru 
                      FROM jadwal_quran jq 
                      LEFT JOIN kelas_quran kq ON jq.kelas_quran_id = kq.id 
                      LEFT JOIN guru g ON jq.guru_id = g.guru_id 
                      ORDER BY jq.hari, jq.jam_mulai";
            $data = getTableData($conn, $query);
            $response = [
                'success' => true, 
                'data' => $data, 
                'total' => count($data),
                'message' => 'Data jadwal quran berhasil diambil'
            ];
            break;
            
        case 'get_jadwal_kegiatan':
            $data = getTableData($conn, "SELECT * FROM jadwal_kegiatan ORDER BY hari, jam_mulai");
            $response = [
                'success' => true, 
                'data' => $data, 
                'total' => count($data),
                'message' => 'Data jadwal kegiatan berhasil diambil'
            ];
            break;
            
        case 'get_login_attempts':
            $limit = intval($_GET['limit'] ?? 100000);
            $data = getTableData($conn, "SELECT * FROM login_attempts ORDER BY attempt_time DESC LIMIT ?", [$limit]);
            $response = [
                'success' => true, 
                'data' => $data, 
                'total' => count($data),
                'message' => 'Data login attempts berhasil diambil'
            ];
            break;
        
        case 'get_tables_list':
            $tables = [
                'jadwal_madin',
                'jadwal_quran', 
                'jadwal_kegiatan',
                'absensi_guru',
                'pengaturan_absensi_otomatis',
                'login_attempts',
                'pengaturan_notifikasi'
            ];
            
            echo json_encode([
                'success' => true,
                'tables' => $tables,
                'message' => 'Daftar tabel tersedia'
            ]);
            break;

        default:
            $response = [
                'success' => false, 
                'error' => 'Action tidak valid: ' . $action,
                'available_actions' => [
                    'test', 
                    'get_murid', 'get_guru', 'get_absensi', 'get_pelanggaran', 'get_perizinan', 
                    'get_alumni', 'get_users', 'get_pengaturan_notifikasi', 'get_absensi_guru', 
                    'get_pengaturan_absensi_otomatis', 'get_jadwal_madin', 'get_jadwal_quran', 
                    'get_jadwal_kegiatan', 'get_login_attempts'
                ]
            ];
    }
} catch(Exception $e) {
    $response = [
        'success' => false, 
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ];
}

// Pastikan response selalu dalam format JSON
header('Content-Type: application/json');
echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

// Tutup koneksi database
if (isset($conn) && $conn) {
    $conn->close();
}
?>