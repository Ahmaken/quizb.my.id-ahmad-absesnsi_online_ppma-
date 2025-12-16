<?php
// Mulai session jika belum dimulai
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include file config.php terlebih dahulu untuk mendefinisikan $conn
require_once __DIR__ . '/config.php';

// Dapatkan guru_id jika user adalah guru
$guru_id = null;
if (isset($_SESSION['role']) && $_SESSION['role'] === 'guru' && isset($_SESSION['user_id'])) {
    // Pastikan koneksi database tersedia
    if ($conn) {
        $sql_guru = "SELECT guru_id FROM guru WHERE user_id = ?";
        $stmt_guru = $conn->prepare($sql_guru);
        if ($stmt_guru) {
            $stmt_guru->bind_param("i", $_SESSION['user_id']);
            $stmt_guru->execute();
            $result_guru = $stmt_guru->get_result();
            
            if ($result_guru->num_rows > 0) {
                $guru_data = $result_guru->fetch_assoc();
                $guru_id = $guru_data['guru_id'];
                $_SESSION['guru_id'] = $guru_id; // Simpan di session untuk akses mudah
            }
            $stmt_guru->close();
        } else {
            // Log error atau handle error prepare statement
            error_log("Error preparing statement for guru_id: " . $conn->error);
        }
    } else {
        // Handle error koneksi database
        error_log("Database connection is not available in init.php");
    }
}

// PERBAIKAN: Clear cache hijriyah jika diperlukan
if (isset($_GET['clear_hijri_cache'])) {
    unset($_SESSION['hijri_date_cache']);
    unset($_SESSION['hijri_date_cache_nav']);
    // PERBAIKAN: Gunakan JavaScript redirect untuk menghindari header issues
    echo '<script>window.location.href = "' . str_replace('?clear_hijri_cache=1', '', $_SERVER['REQUEST_URI']) . '";</script>';
    exit;
}

// Inisialisasi cache hijriyah
if (!isset($_SESSION['hijri_date_cache'])) {
    $_SESSION['hijri_date_cache'] = [
        'date' => '',
        'hijri_date' => ''
    ];
}

if (!isset($_SESSION['hijri_date_cache_nav'])) {
    $_SESSION['hijri_date_cache_nav'] = [
        'date' => '',
        'hijri_date' => ''
    ];
}

// Autoloader untuk classes
spl_autoload_register(function($class) {
    $classFile = __DIR__ . '/../pages/' . $class . '.php';
    if (file_exists($classFile)) {
        require_once $classFile;
    }
});

// Include file-file essential yang bukan class
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';

// Navigation.php akan di-include secara manual di setiap halaman 
// karena memerlukan variabel yang mungkin di-set di halaman tertentu