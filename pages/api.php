<?php
require_once '../includes/init.php';

if (!check_auth()) {
    header("HTTP/1.1 401 Unauthorized");
    exit(json_encode(['status' => 'error', 'message' => 'Unauthorized']));
}

class WhatsAppNotifier {
    private $conn;
    
    public function __construct($connection) {
        $this->conn = $connection;
    }
    
    // Fungsi untuk mengirim notifikasi WhatsApp
    public function sendNotification($phone, $message) {
        // Ganti dengan API WhatsApp yang Anda gunakan (contoh: Fonnte, Wablas, dll)
        $api_key = 'YOUR_WHATSAPP_API_KEY';
        $api_url = 'https://api.fonnte.com/send';
        
        $data = [
            'target' => $phone,
            'message' => $message,
            'countryCode' => '62', // Indonesia
        ];
        
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $api_url);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, [
            'Authorization: ' . $api_key
        ]);
        
        $response = curl_exec($curl);
        curl_close($curl);
        
        return $response;
    }
    
    // Kirim notifikasi untuk murid alpa
    public function notifyAlpa($jadwal_type, $jadwal_id, $murid_id, $tanggal) {
        $notifier = new WhatsAppNotifier($this->conn);
        $messages_sent = [];
        
        // Dapatkan data murid yang alpa
        $sql_murid = "SELECT m.*, km.nama_kelas, k.nama_kamar, kq.nama_kelas as kelas_quran,
                             m.nama_wali, m.no_wali 
                      FROM murid m 
                      LEFT JOIN kelas_madin km ON m.kelas_madin_id = km.kelas_id
                      LEFT JOIN kamar k ON m.kamar_id = k.kamar_id
                      LEFT JOIN kelas_quran kq ON m.kelas_quran_id = kq.id
                      WHERE m.murid_id = ?";
        $stmt_murid = $this->conn->prepare($sql_murid);
        $stmt_murid->bind_param("i", $murid_id);
        $stmt_murid->execute();
        $murid = $stmt_murid->get_result()->fetch_assoc();
        
        if (!$murid) return $messages_sent;
        
        // Dapatkan data jadwal
        $jadwal_info = $this->getJadwalInfo($jadwal_type, $jadwal_id);
        
        // Notifikasi untuk wali murid
        if (!empty($murid['no_wali']) && !empty($murid['nama_wali'])) {
            $message_wali = "Assalamu'alaikum Bapak/Ibu " . $murid['nama_wali'] . ",\n\n";
            $message_wali .= "Putra/i Anda *" . $murid['nama'] . "* tidak hadir (Alpa) pada:\n";
            $message_wali .= "📚 *" . $jadwal_info['jenis'] . "*\n";
            $message_wali .= "🗓️ Tanggal: " . $tanggal . "\n";
            $message_wali .= "⏰ " . $jadwal_info['waktu'] . "\n";
            $message_wali .= "📍 " . $jadwal_info['lokasi'] . "\n\n";
            $message_wali .= "Silakan hubungi bagian administrasi untuk informasi lebih lanjut.\n\n";
            $message_wali .= "Salam,\nPPMA UNIDA";
            
            $result = $notifier->sendNotification($murid['no_wali'], $message_wali);
            $messages_sent[] = ['target' => 'wali_murid', 'phone' => $murid['no_wali'], 'result' => $result];
        }
        
        // Notifikasi untuk guru terkait
        if (!empty($jadwal_info['guru_id'])) {
            $sql_guru = "SELECT g.*, u.username 
                         FROM guru g 
                         LEFT JOIN users u ON g.user_id = u.id 
                         WHERE g.guru_id = ?";
            $stmt_guru = $this->conn->prepare($sql_guru);
            $stmt_guru->bind_param("i", $jadwal_info['guru_id']);
            $stmt_guru->execute();
            $guru = $stmt_guru->get_result()->fetch_assoc();
            
            if ($guru && !empty($guru['no_hp'])) {
                $message_guru = "Assalamu'alaikum " . $guru['nama'] . ",\n\n";
                $message_guru .= "Murid *" . $murid['nama'] . "* tidak hadir (Alpa) pada jadwal Anda:\n";
                $message_guru .= "📚 *" . $jadwal_info['mata_pelajaran'] . "*\n";
                $message_guru .= "🗓️ Tanggal: " . $tanggal . "\n";
                $message_guru .= "⏰ " . $jadwal_info['waktu'] . "\n";
                $message_guru .= "📍 " . $jadwal_info['lokasi'] . "\n\n";
                $message_guru .= "Salam,\nSistem Absensi PPMA";
                
                $result = $notifier->sendNotification($guru['no_hp'], $message_guru);
                $messages_sent[] = ['target' => 'guru', 'phone' => $guru['no_hp'], 'result' => $result];
            }
        }
        
        return $messages_sent;
    }
    
    private function getJadwalInfo($type, $id) {
        $info = ['jenis' => '', 'waktu' => '', 'lokasi' => '', 'mata_pelajaran' => '', 'guru_id' => null];
        
        switch ($type) {
            case 'quran':
                $sql = "SELECT jq.*, kq.nama_kelas, g.guru_id 
                        FROM jadwal_quran jq 
                        LEFT JOIN kelas_quran kq ON jq.kelas_quran_id = kq.id 
                        LEFT JOIN guru g ON jq.guru_id = g.guru_id 
                        WHERE jq.id = ?";
                $stmt = $this->conn->prepare($sql);
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $data = $stmt->get_result()->fetch_assoc();
                
                if ($data) {
                    $info['jenis'] = "Kelas Quran: " . $data['mata_pelajaran'];
                    $info['waktu'] = $data['jam_mulai'] . " - " . $data['jam_selesai'] . " (" . $data['hari'] . ")";
                    $info['lokasi'] = $data['nama_kelas'];
                    $info['mata_pelajaran'] = $data['mata_pelajaran'];
                    $info['guru_id'] = $data['guru_id'];
                }
                break;
                
            case 'madin':
                $sql = "SELECT jm.*, km.nama_kelas, g.guru_id 
                        FROM jadwal_madin jm 
                        LEFT JOIN kelas_madin km ON jm.kelas_madin_id = km.kelas_id 
                        LEFT JOIN guru g ON jm.guru_id = g.guru_id 
                        WHERE jm.jadwal_id = ?";
                $stmt = $this->conn->prepare($sql);
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $data = $stmt->get_result()->fetch_assoc();
                
                if ($data) {
                    $info['jenis'] = "Kelas Madin: " . $data['mata_pelajaran'];
                    $info['waktu'] = $data['jam_mulai'] . " - " . $data['jam_selesai'] . " (" . $data['hari'] . ")";
                    $info['lokasi'] = $data['nama_kelas'];
                    $info['mata_pelajaran'] = $data['mata_pelajaran'];
                    $info['guru_id'] = $data['guru_id'];
                }
                break;
                
            case 'kegiatan':
                $sql = "SELECT jk.*, k.nama_kamar, g.guru_id 
                        FROM jadwal_kegiatan jk 
                        LEFT JOIN kamar k ON jk.kamar_id = k.kamar_id 
                        LEFT JOIN guru g ON jk.guru_id = g.guru_id 
                        WHERE jk.kegiatan_id = ?";
                $stmt = $this->conn->prepare($sql);
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $data = $stmt->get_result()->fetch_assoc();
                
                if ($data) {
                    $info['jenis'] = "Kegiatan: " . $data['nama_kegiatan'];
                    $info['waktu'] = $data['jam_mulai'] . " - " . $data['jam_selesai'] . " (" . $data['hari'] . ")";
                    $info['lokasi'] = $data['nama_kamar'];
                    $info['mata_pelajaran'] = $data['nama_kegiatan'];
                    $info['guru_id'] = $data['guru_id'];
                }
                break;
        }
        
        return $info;
    }
}

// Handle API requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $notifier = new WhatsAppNotifier($conn);
    $response = ['status' => 'success', 'messages' => []];
    
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'notify_alpa':
                $jadwal_type = $_POST['jadwal_type'] ?? '';
                $jadwal_id = intval($_POST['jadwal_id'] ?? 0);
                $murid_id = intval($_POST['murid_id'] ?? 0);
                $tanggal = $_POST['tanggal'] ?? date('Y-m-d');
                
                $messages = $notifier->notifyAlpa($jadwal_type, $jadwal_id, $murid_id, $tanggal);
                $response['messages'] = $messages;
                break;
                
            case 'test_notification':
                $phone = $_POST['phone'] ?? '';
                $message = $_POST['message'] ?? 'Test notification from PPMA Absensi System';
                
                if (!empty($phone)) {
                    $result = $notifier->sendNotification($phone, $message);
                    $response['test_result'] = $result;
                }
                break;
        }
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}
?>