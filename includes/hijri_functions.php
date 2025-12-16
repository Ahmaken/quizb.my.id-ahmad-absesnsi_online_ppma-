<?php
// includes/hijri_functions.php

function get_hijri_date_kemenag($date) {
    $today = date('Y-m-d');
    
    // PERBAIKAN: Gunakan cache yang sama untuk semua halaman
    if (isset($_SESSION['hijri_date_cache']) && 
        $_SESSION['hijri_date_cache']['date'] === $today) {
        $cached_hijri = $_SESSION['hijri_date_cache']['hijri_date'];
        // Validasi cache
        if (strpos($cached_hijri, 'undefined') === false) {
            return $cached_hijri;
        }
    }
    
    // Cek cache di session untuk menghindari request berulang
    if (isset($_SESSION['hijri_date_cache']) && 
        $_SESSION['hijri_date_cache']['date'] === $today) {
        return $_SESSION['hijri_date_cache']['hijri_date'];
    }
    
    // PERBAIKAN: Gunakan API yang lebih reliable dengan multiple endpoints
    $formatted_date = date('d-m-Y', strtotime($date));
    
    // Multiple API endpoints sebagai fallback
    $api_endpoints = [
        "https://api.aladhan.com/v1/gToH?date=" . $formatted_date,
        "https://api.islamic.systems/v1/gToH?date=" . $formatted_date,
        "https://www.islamicfinder.org/index.php/api/gToH?date=" . $formatted_date
    ];
    
    foreach ($api_endpoints as $api_url) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $api_url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT => 'Sistem Absensi PP Matholiul Anwar',
            CURLOPT_FOLLOWLOCATION => true
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code === 200 && $response) {
            $data = json_decode($response, true);
            
            // Handle different API response formats
            $hijri = null;
            if (isset($data['data']['hijri'])) {
                $hijri = $data['data']['hijri'];
            } elseif (isset($data['hijri'])) {
                $hijri = $data['hijri'];
            }
            
            // PERBAIKAN: Pastikan format konsisten
            if ($hijri && isset($hijri['day']) && isset($hijri['month']) && isset($hijri['year'])) {
                $day = $hijri['day'];
                $month = is_array($hijri['month']) ? $hijri['month']['en'] : $hijri['month'];
                $year = $hijri['year'];
                
                // Validasi tahun Hijriyah harus reasonable (1400-1500)
                if ($year >= 1400 && $year <= 1500) {
                    // PERBAIKAN: Format yang lebih ringkas untuk navbar
                    $hijri_date = "$day $month $year H";
                    
                    // Simpan di cache session
                    $_SESSION['hijri_date_cache'] = [
                        'date' => $today,
                        'hijri_date' => $hijri_date
                    ];
                    
                    return $hijri_date;
                }
            }
        }
    }
    
    // PERBAIKAN: Fallback ke perhitungan yang lebih akurat
    return get_accurate_hijri_date($date);
}

function get_accurate_hijri_date($date) {
    // PERBAIKAN: Gunakan perhitungan yang lebih akurat berdasarkan referensi terkini
    $current_date = strtotime($date);
    
    // Referensi: 20 November 2024 = 18 Rabiul Akhir 1446 H
    $reference_gregorian = strtotime('2024-11-20');
    $reference_hijri = [18, 4, 1446]; // [day, month, year]
    
    // Hitung selisih hari dari referensi
    $diff_seconds = $current_date - $reference_gregorian;
    $diff_days = round($diff_seconds / (60 * 60 * 24));
    
    // Konversi ke hari Hijriyah (1 tahun = 354.367056 hari)
    $hijri_days = $reference_hijri[0] + $diff_days;
    
    $h_year = $reference_hijri[2];
    $h_month = $reference_hijri[1];
    $h_day = $hijri_days;
    
    // Array bulan Hijriyah
    $hijri_months = [
        "Muharram", "Safar", "Rabiul Awal", "Rabiul Akhir", 
        "Jumadil Awal", "Jumadil Akhir", "Rajab", "Sya'ban", 
        "Ramadhan", "Syawal", "Dzulqa'dah", "Dzulhijjah"
    ];
    
    // Normalisasi tanggal Hijriyah
    $days_in_month = [30, 29, 30, 29, 30, 29, 30, 29, 30, 29, 30, 29]; // Default, Dzulhijjah bisa 30 di tahun kabisat
    
    while ($h_day > $days_in_month[$h_month - 1]) {
        $h_day -= $days_in_month[$h_month - 1];
        $h_month++;
        
        if ($h_month > 12) {
            $h_month = 1;
            $h_year++;
            // Update Dzulhijjah untuk tahun kabisat (tahun 2,5,7,10,13,16,18,21,24,26,29 dalam siklus 30 tahun)
            $kabisat_years = [2,5,7,10,13,16,18,21,24,26,29];
            $cycle_year = ($h_year - 1) % 30 + 1;
            $days_in_month[11] = in_array($cycle_year, $kabisat_years) ? 30 : 29;
        }
    }
    
    while ($h_day < 1) {
        $h_month--;
        if ($h_month < 1) {
            $h_month = 12;
            $h_year--;
            // Update Dzulhijjah untuk tahun sebelumnya
            $kabisat_years = [2,5,7,10,13,16,18,21,24,26,29];
            $cycle_year = ($h_year - 1) % 30 + 1;
            $days_in_month[11] = in_array($cycle_year, $kabisat_years) ? 30 : 29;
        }
        $h_day += $days_in_month[$h_month - 1];
    }
    
    // Pastikan bulan valid
    if ($h_month < 1 || $h_month > 12) {
        $h_month = 1;
    }
    
    $month_name = $hijri_months[$h_month - 1];
    
    return $h_day . ' ' . $month_name . ' ' . $h_year . ' H';
}

function get_hijri_date_kemenag_nav($date) {
    // Gunakan cache session yang sama dengan fungsi utama untuk konsistensi
    if (isset($_SESSION['hijri_date_cache']) && 
        $_SESSION['hijri_date_cache']['date'] === date('Y-m-d')) {
        return $_SESSION['hijri_date_cache']['hijri_date'];
    }
    
    // Jika tidak ada cache, gunakan fungsi utama
    return get_hijri_date_kemenag($date);
}

// Fungsi untuk memaksa clear cache Hijriyah
function clear_hijri_cache() {
    unset($_SESSION['hijri_date_cache']);
    unset($_SESSION['hijri_date_cache_nav']);
}
?>