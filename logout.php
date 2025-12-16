<?php
// logout.php

// Mulai output buffering untuk menghindari header issues
ob_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'includes/auth.php';

// Pastikan session sudah dimulai sebelum logout
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

logout();

// Bersihkan output buffer dan redirect
ob_end_clean();
header("Location: index.php");
exit();
?>