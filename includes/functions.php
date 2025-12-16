<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once 'config.php';

// Fungsi untuk mendapatkan ucapan selamat datang
if (!function_exists('get_greeting')) {
    function get_greeting() {
        $hour = date('H');
        
        if ($hour < 12) {
            return "Selamat Pagi";
        } elseif ($hour < 15) {
            return "Selamat Siang";
        } elseif ($hour < 18) {
            return "Selamat Sore";
        } elseif ($hour < 21) {
            return "Selamat Malam";
        } elseif ($hour < 24) {
            return "Selamat Beristirahat";
        } elseif ($hour < 3) {
            return "Selamat Berjuang";
        } 
    }
}

// Fungsi untuk mendapatkan data murid
if (!function_exists('get_murid')) {
    function get_murid() {
        global $conn;
        $sql = "SELECT * FROM murid";
        $result = $conn->query($sql);
        return $result;
    }
}

// includes/functions.php
if (!function_exists('get_alumni')) {
    function get_alumni() {
        global $conn;
        $sql = "SELECT * FROM alumni";
        $result = $conn->query($sql);
        return $result;
    }
}

// Fungsi untuk mendapatkan preferensi mode gelap
if (!function_exists('get_dark_mode')) {
    function get_dark_mode() {
        // PERBAIKAN: Gunakan session langsung
        return $_SESSION['dark_mode'] ?? 0;
    }
}
?>