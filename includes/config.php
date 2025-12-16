<?php
// includes/config.php
$host = 'localhost';
$username = 'quic1934_Admin123';
$password = '.A7991h80d70.';
$database = 'quic1934_absensi_online';

$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
    // Jangan tampilkan error detail ke user
    die(json_encode(['success' => false, 'error' => 'Database connection failed']));
}

$conn->set_charset("utf8mb4");
?>