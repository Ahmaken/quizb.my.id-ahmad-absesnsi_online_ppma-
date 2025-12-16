<?php
// api.php di sistem PPMA
require_once 'config/database.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'kelas-madin':
        $data = $pdo->query("SELECT * FROM kelas_madin")->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($data);
        break;
        
    case 'kamar':
        $data = $pdo->query("SELECT * FROM kamar")->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($data);
        break;
        
    case 'kelas-quran':
        $data = $pdo->query("SELECT * FROM kelas_quran")->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($data);
        break;
        
    case 'users':
        $data = $pdo->query("SELECT id, username, role, kelas_id, murid_id, dark_mode, foto_profil, email, is_active, last_login, nama, nip, created_at, updated_at FROM users")->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($data);
        break;

    // TABEL BARU YANG DITAMBAHKAN
    case 'pengaturan-notifikasi':
        $data = $pdo->query("SELECT * FROM pengaturan_notifikasi")->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($data);
        break;
        
    case 'absensi-guru':
        $data = $pdo->query("SELECT * FROM absensi_guru")->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($data);
        break;
        
    case 'pengaturan-absensi-otomatis':
        $data = $pdo->query("SELECT * FROM pengaturan_absensi_otomatis")->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($data);
        break;
        
    case 'jadwal-madin':
        $data = $pdo->query("SELECT * FROM jadwal_madin")->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($data);
        break;
        
    case 'jadwal-quran':
        $data = $pdo->query("SELECT * FROM jadwal_quran")->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($data);
        break;
        
    case 'jadwal-kegiatan':
        $data = $pdo->query("SELECT * FROM jadwal_kegiatan")->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($data);
        break;
        
    case 'login-attempts':
        $data = $pdo->query("SELECT * FROM login_attempts ORDER BY attempt_time DESC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($data);
        break;
        
    default:
        echo json_encode(['error' => 'Action tidak valid']);
        break;
}
?>