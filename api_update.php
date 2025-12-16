<?php
// api_update.php - API untuk update data dari Google Sheets ke MySQL
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// =============================================
// KONFIGURASI ERROR HANDLING YANG AMAN
// =============================================
error_reporting(E_ALL);

// UNTUK PRODUCTION: Nonaktifkan display error, tapi log tetap aktif
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Buffer output untuk tangkap error yang tidak tertangani
ob_start();

// =============================================
// INCLUDE CONFIG DAN HANDLE DATABASE CONNECTION
// =============================================
try {
    require_once 'includes/config.php';
    
    // Validasi koneksi database
    if (!$conn || $conn->connect_error) {
        throw new Exception("Koneksi database gagal: " . ($conn->connect_error ?? 'Unknown error'));
    }
    
    // Test koneksi
    if (!$conn->ping()) {
        throw new Exception("Database connection is not active");
    }
    
} catch (Exception $e) {
    // Clean output buffer
    ob_end_clean();
    
    error_log("Database connection error: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database connection failed',
        'message' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
    exit;
}

// =============================================
// HANDLE PREFLIGHT REQUEST
// =============================================
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    ob_end_clean();
    exit(0);
}

// =============================================
// FUNGSI UTILITY
// =============================================

/**
 * Clean output buffer dan pastikan response JSON
 */
function cleanOutputAndSend($data) {
    // Clean semua output buffer
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    header('Content-Type: application/json');
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Log request untuk debugging
 */
function logRequest($input) {
    $logData = [
        'timestamp' => date('Y-m-d H:i:s'),
        'method' => $_SERVER['REQUEST_METHOD'],
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
        'input' => $input
    ];
    error_log("API Update Request: " . json_encode($logData));
}

// =============================================
// MAIN EXECUTION - DENGAN ERROR HANDLING
// =============================================
try {
    // Baca input
    $input = file_get_contents('php://input');
    
    // Log request (sebelum parse JSON untuk hindari error)
    logRequest($input ? substr($input, 0, 500) : 'EMPTY_INPUT');
    
    $data = json_decode($input, true);
    
    // Validasi JSON
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON input: ' . json_last_error_msg());
    }
    
    // Validasi input required
    if (!$data) {
        throw new Exception('No data received');
    }
    
    $table = $data['table'] ?? '';
    $action = $data['action'] ?? '';
    $updateData = $data['data'] ?? [];
    $where = $data['where'] ?? [];

    // Validasi field required
    if (empty($table) || empty($action)) {
        throw new Exception('Table dan action harus diisi');
    }

    // Validasi table name untuk prevent SQL injection
    $allowedTables = ['murid', 'guru', 'users', 'absensi', 'pelanggaran', 'perizinan', 'alumni'];
    if (!in_array($table, $allowedTables)) {
        throw new Exception('Table tidak diizinkan: ' . $table);
    }

    // Eksekusi berdasarkan action
    switch($action) {
        case 'update':
            if (empty($updateData) || empty($where)) {
                throw new Exception('Data dan where condition harus diisi untuk update');
            }
            $result = updateData($conn, $table, $updateData, $where);
            break;
            
        case 'insert':
            if (empty($updateData)) {
                throw new Exception('Data harus diisi untuk insert');
            }
            $result = insertData($conn, $table, $updateData);
            break;
            
        case 'delete':
            if (empty($where)) {
                throw new Exception('Where condition harus diisi untuk delete');
            }
            $result = deleteData($conn, $table, $where);
            break;
            
        default:
            throw new Exception('Action tidak valid: ' . $action);
    }

    // Success response
    $response = [
        'success' => true,
        'message' => "Operation {$action} completed successfully",
        'data' => $result
    ];
    
    cleanOutputAndSend($response);

} catch(Exception $e) {
    error_log("Error in api_update.php: " . $e->getMessage());
    
    $response = [
        'success' => false,
        'error' => $e->getMessage(),
        'debug' => [
            'table' => $table ?? '',
            'action' => $action ?? '',
            'timestamp' => date('Y-m-d H:i:s')
        ]
    ];
    
    http_response_code(400);
    cleanOutputAndSend($response);
}

// =============================================
// DATABASE OPERATIONS - DENGAN ERROR HANDLING
// =============================================

/**
 * Update data dengan prepared statements
 */
function updateData($conn, $table, $data, $where) {
    $setParts = [];
    $whereParts = [];
    $types = '';
    $values = [];
    
    // Build SET clause
    foreach ($data as $key => $value) {
        // Escape field names untuk security
        $escapedKey = escapeFieldName($key);
        $setParts[] = "{$escapedKey} = ?";
        $values[] = $value;
        $types .= getTypeChar($value);
    }
    
    // Build WHERE clause
    foreach ($where as $key => $value) {
        $escapedKey = escapeFieldName($key);
        $whereParts[] = "{$escapedKey} = ?";
        $values[] = $value;
        $types .= getTypeChar($value);
    }
    
    $sql = "UPDATE {$table} SET " . implode(', ', $setParts) . 
           " WHERE " . implode(' AND ', $whereParts);
    
    error_log("Update SQL: " . $sql);
    error_log("Update Values: " . json_encode($values));
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }
    
    if (!$stmt->bind_param($types, ...$values)) {
        throw new Exception('Bind param failed: ' . $stmt->error);
    }
    
    if (!$stmt->execute()) {
        throw new Exception('Execute failed: ' . $stmt->error);
    }
    
    $affectedRows = $stmt->affected_rows;
    $stmt->close();
    
    return [
        'affected_rows' => $affectedRows,
        'table' => $table,
        'where' => $where
    ];
}

/**
 * Insert data dengan prepared statements
 */
function insertData($conn, $table, $data) {
    $columns = [];
    $placeholders = [];
    $values = [];
    $types = '';
    
    foreach ($data as $key => $value) {
        $escapedKey = escapeFieldName($key);
        $columns[] = $escapedKey;
        $placeholders[] = '?';
        $values[] = $value;
        $types .= getTypeChar($value);
    }
    
    $sql = "INSERT INTO {$table} (" . implode(', ', $columns) . 
           ") VALUES (" . implode(', ', $placeholders) . ")";
    
    error_log("Insert SQL: " . $sql);
    error_log("Insert Values: " . json_encode($values));
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }
    
    if (!$stmt->bind_param($types, ...$values)) {
        throw new Exception('Bind param failed: ' . $stmt->error);
    }
    
    if (!$stmt->execute()) {
        throw new Exception('Execute failed: ' . $stmt->error);
    }
    
    $insertId = $stmt->insert_id;
    $affectedRows = $stmt->affected_rows;
    $stmt->close();
    
    return [
        'insert_id' => $insertId,
        'affected_rows' => $affectedRows,
        'table' => $table
    ];
}

/**
 * Delete data dengan prepared statements
 */
function deleteData($conn, $table, $where) {
    $whereParts = [];
    $types = '';
    $values = [];
    
    foreach ($where as $key => $value) {
        $escapedKey = escapeFieldName($key);
        $whereParts[] = "{$escapedKey} = ?";
        $values[] = $value;
        $types .= getTypeChar($value);
    }
    
    $sql = "DELETE FROM {$table} WHERE " . implode(' AND ', $whereParts);
    
    error_log("Delete SQL: " . $sql);
    error_log("Delete Values: " . json_encode($values));
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }
    
    if (!$stmt->bind_param($types, ...$values)) {
        throw new Exception('Bind param failed: ' . $stmt->error);
    }
    
    if (!$stmt->execute()) {
        throw new Exception('Execute failed: ' . $stmt->error);
    }
    
    $affectedRows = $stmt->affected_rows;
    $stmt->close();
    
    return [
        'affected_rows' => $affectedRows,
        'table' => $table,
        'where' => $where
    ];
}

/**
 * Escape field names untuk security tambahan
 */
function escapeFieldName($field) {
    // Hanya allow alphanumeric dan underscore
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $field)) {
        throw new Exception('Invalid field name: ' . $field);
    }
    return "`{$field}`";
}

/**
 * Fungsi bantu untuk mendapatkan tipe data
 */
function getTypeChar($value) {
    if (is_int($value)) return 'i';
    if (is_float($value) || is_double($value)) return 'd';
    return 's';
}

// =============================================
// CLEANUP DAN EXIT
// =============================================

// Clean output buffer terakhir
ob_end_clean();

// Tutup koneksi database
if (isset($conn) && $conn) {
    $conn->close();
}
?>