<?php
// includes/guru_functions.php

function get_kelas_terkait_guru($guru_id) {
    global $conn;
    
    $kelas_list = [];
    
    // Kelas Madin
    $sql_km = "SELECT * FROM kelas_madin WHERE guru_id = ?";
    $stmt_km = $conn->prepare($sql_km);
    $stmt_km->bind_param("i", $guru_id);
    $stmt_km->execute();
    $result_km = $stmt_km->get_result();
    
    while ($row = $result_km->fetch_assoc()) {
        $kelas_list['madin'][] = $row;
    }
    
    // Kelas Quran
    $sql_kq = "SELECT * FROM kelas_quran WHERE guru_id = ?";
    $stmt_kq = $conn->prepare($sql_kq);
    $stmt_kq->bind_param("i", $guru_id);
    $stmt_kq->execute();
    $result_kq = $stmt_kq->get_result();
    
    while ($row = $result_kq->fetch_assoc()) {
        $kelas_list['quran'][] = $row;
    }
    
    return $kelas_list;
}

function get_kamar_terkait_guru($guru_id) {
    global $conn;
    
    $sql = "SELECT * FROM kamar WHERE guru_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $guru_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $kamar_list = [];
    while ($row = $result->fetch_assoc()) {
        $kamar_list[] = $row;
    }
    
    return $kamar_list;
}
?>