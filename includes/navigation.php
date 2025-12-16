<?php
// Di bagian atas file navigation.php - GANTI dengan ini:
require_once '../includes/functions.php';

// Include fungsi hijriyah
require_once '../includes/hijri_functions.php';

$current_uri = $_SERVER['REQUEST_URI'];
$path = parse_url($current_uri, PHP_URL_PATH);
$current_page = basename($path);

// PERBAIKAN: Gunakan satu sumber untuk tanggal Hijriyah
$today_nav = date('Y-m-d');
try {
    $tanggal_hijriyah_nav = get_hijri_date_kemenag($today_nav);
    // Simpan ke session untuk penggunaan berikutnya
    $_SESSION['hijri_date_nav'] = $tanggal_hijriyah_nav;
} catch (Exception $e) {
    error_log("Error getting hijri date for nav: " . $e->getMessage());
    $tanggal_hijriyah_nav = $_SESSION['hijri_date_nav'] ?? date('d M Y') . ' H';
}

// Pastikan tidak ada undefined
if (empty($tanggal_hijriyah_nav) || strpos($tanggal_hijriyah_nav, 'undefined') !== false) {
    $tanggal_hijriyah_nav = date('d M Y') . ' H';
}

$dark_mode = $_SESSION['dark_mode'] ?? 0;
?>

<!DOCTYPE html>
<html lang="id" data-bs-theme="<?= $dark_mode ? 'dark' : 'light' ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.5/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <!-- Tambahkan font elegan -->
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;500;600&display=swap" rel="stylesheet">

<!-- NAVBAR - Perbaikan struktur dan alignment -->
<nav class="navbar navbar-expand-lg navbar-dark fixed-top" style="background-color: #1b5e20 !important;">
    <div class="container h-100"> 
        <!-- Logo di kiri (mobile) -->
        <a href="dashboard.php" class="d-lg-none d-flex align-items-center mobile-logo" style="height: 100%;">
            <img src="../assets/img/Logo_PP_Matholi'ul_Anwar.png" alt="Logo Lembaga" width="31" height="36">
        </a>
        
        <!-- Brand untuk Desktop - PERBAIKAN: Format tanggal lebih ringkas -->
        <div class="d-none d-lg-flex flex-column justify-content-center me-auto"> 
            <a class="navbar-brand d-flex align-items-center py-0" href="dashboard.php" style="max-width: calc(100vw - 120px);">
                <img src="../assets/img/Logo_PP_Matholi'ul_Anwar.png" alt="Logo Lembaga" class="me-2" width="43" height="49">
                <div class="d-flex flex-column text-truncate" style="line-height: 1.2;"> 
                    <span class="text-truncate" style="font-size: 0.95rem; font-weight: 700;">Sistem Absensi Mawar</span>
                    
                    <!-- PERBAIKAN: Ukuran tanggal diperkecil lagi -->
                    <small id="navbar-datetime" class="fw-bold text-white text-truncate navbar-datetime" style="line-height: 1; margin-top: -2px;">
                        <span class="navbar-text date-highlight">
                            <?= htmlspecialchars($tanggal_hijriyah_nav) ?>
                        </span>
                    </small>
                </div>
            </a>
        </div>
        
        <!-- Konten Tengah untuk Mobile - PERBAIKAN: Posisi absolut dengan breakpoint minimal -->
        <a href="dashboard.php" class="navbar-center-mobile d-lg-none text-center" style="text-decoration: none; height: 100%; display: flex; flex-direction: column; justify-content: center; position: absolute; left: 50%; transform: translateX(-50%); min-width: 200px; width: auto; z-index: 1;">
            <div class="text-white text-truncate" style="font-size: 1.15rem; font-weight:900;">Sistem Absensi Mawar</div>
            <!-- PERBAIKAN: Format tanggal lebih kompak dan warna diperjelas -->
            <small id="navbar-datetime-mobile" class="fw-bold text-white navbar-datetime" style="font-size: 0.35rem; font-weight: 900;">
                <span class="navbar-text date-highlight">
                    <?= date('d M Y') ?> M |
                    <?= htmlspecialchars($tanggal_hijriyah_nav) ?>
                </span>
            </small>
        </a>
        
        <!-- Menu Desktop -->
        <div class="collapse navbar-collapse justify-content-lg-center" id="navbarContent">
            <ul class="navbar-nav">
                <li class="nav-item">
                    <a class="nav-link <?= ($current_page == 'dashboard.php') ? 'active' : '' ?>" href="dashboard.php">
                        <i class="bi bi-house-door me-2"></i>
                        <span class="desktop-menu-label">Dashboard</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= ($current_page == 'database.php') ? 'active' : '' ?>" href="database.php">
                        <i class="bi bi-people me-2"></i>
                        <span class="desktop-menu-label">Personalia</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= ($current_page == 'jadwal.php') ? 'active' : '' ?>" href="jadwal.php">
                        <i class="bi bi-calendar-week me-2"></i>
                        <span class="desktop-menu-label">Jadwal</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= ($current_page == 'absensi.php') ? 'active' : '' ?>" href="absensi.php">
                        <i class="bi bi-clipboard-check me-2"></i>
                        <span class="desktop-menu-label">Absensi</span>
                        <?php if (isset($_SESSION['notifikasi_jadwal_belum_isi']) && count($_SESSION['notifikasi_jadwal_belum_isi']) > 0): ?>
                        <span class="badge bg-danger ms-1"><?= count($_SESSION['notifikasi_jadwal_belum_isi']) ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= ($current_page == 'pelanggaran.php') ? 'active' : '' ?>" href="pelanggaran.php">
                        <i class="bi bi-shield-check me-2"></i>
                        <span class="desktop-menu-label">Ketertiban</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= ($current_page == 'kelas.php') ? 'active' : '' ?>" href="kelas.php">
                        <i class="bi bi-journal-bookmark me-2"></i>
                        <span class="desktop-menu-label">Kelas</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= ($current_page == 'kamar.php') ? 'active' : '' ?>" href="kamar.php">
                        <i class="bi bi-door-closed me-2"></i>
                        <span class="desktop-menu-label">Kamar</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= ($current_page == 'rekapitulasi.php') ? 'active' : '' ?>" href="rekapitulasi.php">
                        <i class="bi bi-bar-chart-line me-2"></i>
                        <span class="desktop-menu-label">Rekapitulasi</span>
                    </a>
                </li>
                
                <!-- TAMBAHKAN MENU JURNAL DI SINI -->
                <li class="nav-item">
                    <a class="nav-link <?= ($current_page == 'jurnal.php') ? 'active' : '' ?>" href="jurnal.php">
                        <i class="bi bi-clipboard-data me-2"></i>
                        <span class="desktop-menu-label">Jurnal</span>
                    </a>
                </li>
                
                <li class="nav-item">
                    <a class="nav-link <?= ($current_page == 'alumni.php') ? 'active' : '' ?>" href="alumni.php">
                        <i class="bi bi-mortarboard me-2"></i>
                        <span class="desktop-menu-label">Alumni</span>
                    </a>
                </li>
            </ul>
        </div>
            
        <!-- Dropdown User Desktop -->
        <div class="dropdown d-none d-lg-block">
            <button class="btn btn-outline-light dropdown-toggle" type="button" id="userDropdown" data-bs-toggle="dropdown">
                <?php if (!empty($_SESSION['foto_profil'])): ?>
                    <img src="../uploads/profil/<?= $_SESSION['foto_profil'] ?>" class="rounded-circle me-2" width="30" height="30" alt="Profil">
                <?php else: ?>
                    <i class="bi bi-person-circle me-1"></i>
                <?php endif; ?>
                <?= htmlspecialchars($_SESSION['username']) ?>
            </button>
            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                <?php if ($_SESSION['role'] == 'admin'): ?>
                    <li><a class="dropdown-item" href="users.php"><i class="bi bi-person-gear me-2"></i> Manajemen Pengguna</a></li>
                    <li><hr class="dropdown-divider"></li>
                <?php endif; ?>
                
                <?php if ($_SESSION['role'] == 'admin' || $_SESSION['role'] == 'staff'): ?>
                    <li><a class="dropdown-item" href="pengaturan_notifikasi.php"><i class="bi bi-sliders me-2"></i> Pengaturan Notifikasi</a></li>
                    <li><hr class="dropdown-divider"></li>
                <?php endif; ?>
                
                <?php if ($_SESSION['role'] == 'admin' || $_SESSION['role'] == 'staff'): ?>
                    <li><a class="dropdown-item" href="absensi_guru.php"><i class="bi bi-person-check me-2"></i> Absensi Guru</a></li>
                    <li><hr class="dropdown-divider"></li>
                <?php endif; ?>
                
                <li><a class="dropdown-item" href="?clear_hijri_cache=1"><i class="bi bi-arrow-clockwise me-2"></i>Refresh Hijriyah</a></li>
                <li><hr class="dropdown-divider"></li>
                
                <li><a class="dropdown-item" href="settings.php"><i class="bi bi-gear me-2"></i> Pengaturan</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="https://wa.me/62895617553311" target="_blank">
                    <i class="bi bi-whatsapp me-2"></i> Layanan Pengaduan
                </a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="../logout.php"><i class="bi bi-box-arrow-right me-2"></i> Logout</a></li>
            </ul>
        </div>
    
        
        <!-- Tombol Toggle Mobile -->
        <div class="d-flex align-items-center h-100 ms-1">
            <button 
                class="navbar-toggler custom-toggler d-lg-none p-0 d-flex justify-content-center align-items-center"
                type="button"
                id="mobileMenuToggle"
                style="height: 32px; width: 32px;"
            >
                <span class="custom-toggler-icon">
                    <i class="bi bi-list" style="font-size: 1.4rem; color: white;"></i>
                </span>
            </button>
        </div>
        
    </div>
</nav>

<!-- Sidebar Mini Fixed untuk Handphone -->
<div class="mobile-mini-sidebar d-lg-none">
    <div class="mini-sidebar-content">
        <a href="dashboard.php" class="mini-item active">
            <i class="bi bi-house-door"></i>
        </a>
        <a href="database.php" class="mini-item">
            <i class="bi bi-people"></i>
        </a>
        <a href="jadwal.php" class="mini-item">
            <i class="bi bi-calendar-week"></i>
        </a>
        <a href="absensi.php" class="mini-item">
            <i class="bi bi-clipboard-check"></i>
        </a>
        <a href="pelanggaran.php" class="mini-item">
            <i class="bi bi-shield-check"></i>
        </a>
        <a href="kelas.php" class="mini-item">
            <i class="bi bi-journal-bookmark"></i>
        </a>
        <a href="kamar.php" class="mini-item">
            <i class="bi bi-door-closed"></i>
        </a>
        <a href="rekapitulasi.php" class="mini-item">
            <i class="bi bi-bar-chart-line"></i>
        </a>
        
        <!-- TAMBAHKAN MENU JURNAL DI MINI SIDEBAR -->
        <a href="jurnal.php" class="mini-item">
            <i class="bi bi-clipboard-data"></i>
        </a>
        
        <a href="alumni.php" class="mini-item">
            <i class="bi bi-mortarboard"></i>
        </a>
        
    </div>
</div>

<!-- Sidebar Mobile (Full) -->
<div class="mobile-full-sidebar">
    <div class="sidebar-header">
        <h5 class="sidebar-title">Menu</h5>
        <button type="button" class="sidebar-close-btn" id="sidebarCloseBtn">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>
    <div class="sidebar-content">
        <a href="dashboard.php" class="sidebar-item">
            <i class="bi bi-house-door me-3"></i> Dashboard
        </a>
        <a href="database.php" class="sidebar-item">
            <i class="bi bi-people me-3"></i> Personalia
        </a>
        <a href="jadwal.php" class="sidebar-item">
            <i class="bi bi-calendar-week me-3"></i> Jadwal
        </a>
        <a href="absensi.php" class="sidebar-item">
            <i class="bi bi-clipboard-check me-3"></i> Absensi
        </a>
        <a href="pelanggaran.php" class="sidebar-item">
            <i class="bi bi-shield-check me-3"></i> Ketertiban
        </a>
        <a href="kelas.php" class="sidebar-item">
            <i class="bi bi-journal-bookmark me-3"></i> Kelas
        </a>
        <a href="kamar.php" class="sidebar-item">
            <i class="bi bi-door-closed me-3"></i> Kamar
        </a>
        <a href="rekapitulasi.php" class="sidebar-item">
            <i class="bi bi-bar-chart-line me-3"></i> Rekapitulasi
        </a>
        
         <!-- TAMBAHKAN MENU JURNAL DI FULL SIDEBAR -->
        <a href="jurnal.php" class="sidebar-item">
            <i class="bi bi-clipboard-data me-3"></i> Jurnal
        </a>
        
        <a href="alumni.php" class="sidebar-item">
            <i class="bi bi-mortarboard me-3"></i> Alumni
        </a>
        
        <a href="?clear_hijri_cache=1" class="sidebar-item">
            <i class="bi bi-arrow-clockwise me-3"></i> Refresh Hijriyah
        </a>
        
        <?php if ($_SESSION['role'] == 'admin' || $_SESSION['role'] == 'staff'): ?>
        <div class="user-access-admin">
            <a href="users.php" class="sidebar-item">
                <i class="bi bi-person-gear me-2"></i>Manajemen Pengguna
            </a>
            
            <a href="pengaturan_notifikasi.php" class="sidebar-item">
                <i class="bi bi-sliders me-2"></i> Pengaturan Notifikasi
            </a>
            <a href="absensi_guru.php" class="sidebar-item">
                <i class="bi bi-person-check me-2"></i> Absensi Guru
            </a>
            
        </div>    
        <?php endif; ?>
            
        <div class="user-info-mobile">    
            <div class="user-avatar">
                <?php if (!empty($_SESSION['foto_profil'])): ?>
                    <img src="../uploads/profil/<?= $_SESSION['foto_profil'] ?>" class="rounded-circle" width="80" height="80" alt="Profil">
                <?php else: ?>
                    <i class="bi bi-person-circle"></i>
                <?php endif; ?>
            </div>
            
            <!-- TAMBAHKAN NAMA USER DI SINI -->
            <div class="user-name-mobile text-white text-center mt-2 mb-3">
                <strong><?= htmlspecialchars($_SESSION['username']) ?></strong>
            </div>
            
            <div class="user-actions">
                <a href="settings.php" class="btn btn-sm btn-outline-light mb-2">
                    <i class="bi bi-gear me-1"></i> Pengaturan
                </a>
                <!-- TAMBAHKAN TOMBOL BANTUAN DI SINI -->
                <a href="https://wa.me/62895617553311" target="_blank" class="btn btn-sm btn-outline-light mb-2">
                    <i class="bi bi-whatsapp me-2"></i>Layanan Pengaduan
                </a>
                <a href="../logout.php" class="btn btn-sm btn-outline-light w-100">
                    <i class="bi bi-box-arrow-right me-1"></i> Logout
                </a>
            </div>
        </div>
        
    </div>
</div>

<!-- Modal untuk Foto Profil Pengguna (Mobile) -->
<div class="modal fade" id="userPhotoModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body text-center p-0">
                <img id="modalUserPhoto" src="" class="img-fluid" alt="Foto Profil">
            </div>
            <div class="modal-footer justify-content-center">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<style>
/* ========== GENERAL STYLES ========== */
body {
    padding-top: 50px;
}

.navbar {
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    min-height: 50px;
    max-height: 50px;
    padding: 0;
    background-color: #1b5e20 !important;
}

.navbar > .container {
    align-items: center;
    flex-wrap: nowrap;
}

.navbar-brand {
    padding-top: 0;
    padding-bottom: 0;
    display: flex;
    align-items: center;
}

.text-truncate {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

/* Date Styling */
#navbar-datetime,
#navbar-datetime-mobile {
    color: #ffffff !important;
    font-weight: 900 !important;
}

[data-bs-theme="dark"] .navbar-brand,
[data-bs-theme="dark"] .navbar-brand small {
    color: #fff !important;
}

/* ========== DESKTOP STYLES ========== */
@media (min-width: 992px) {
    .navbar {
        min-height: 60px;
        max-height: 60px;
    }
    
    .navbar > .container {
        height: 60px;
    }
    
    /* Desktop Menu Animations */
    .nav-link {
        display: flex;
        align-items: center;
        position: relative;
        overflow: hidden;
        transition: 
            padding 0.6s cubic-bezier(0.22, 0.61, 0.36, 1),
            min-width 0.6s cubic-bezier(0.22, 0.61, 0.36, 1),
            background-color 0.3s ease;
        min-width: 50px;
        height: 50px;
        padding: 0.5rem 0.75rem !important;
    }

    .desktop-menu-label {
        font-size: 1.07rem;
        position: absolute;
        left: 100%;
        opacity: 0;
        white-space: nowrap;
        transition: 
            left 0.6s cubic-bezier(0.22, 0.61, 0.36, 1),
            opacity 0.6s cubic-bezier(0.22, 0.61, 0.36, 1);
        padding-top: 7.9px;
    }

    .nav-link:hover,
    .nav-link.active {
        min-width: 150px;
        padding: 0.5rem 1.25rem !important;
    }

    .nav-link:hover .desktop-menu-label,
    .nav-link.active .desktop-menu-label {
        left: 57px;
        opacity: 1;
    }

    .nav-link i {
        font-size: 1.7rem;
        transition: transform 0.4s ease;
        position: relative;
        z-index: 2;
        margin-right: 1px;
    }
    
    .nav-link:hover i {
        transform: scale(1.15);
    }

    /* Active State Indicator */
    .nav-link.active {
        font-weight: 600;
        color: white !important;
        position: relative;
    }

    .nav-link.active::after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        height: 3px;
        background-color: white;
        border-radius: 3px;
        transition: bottom 0.3s ease;
    }

    /* Dropdown Styles */
    .dropdown .btn {
        padding: 6px 12px;
        margin-top: 0;
        font-size: 0.9rem;
        margin-right: 15px;
        transition: 
            background-color 0.4s ease-in-out,
            color 0.4s ease-in-out,
            border-color 0.4s ease-in-out,
            transform 0.3s ease-in-out;
    }

    .dropdown .btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }

    .dropdown .btn img {
        width: 25px;
        height: 25px;
    }

    /* Brand and Date Styles */
    .navbar-brand span {
        font-size: 1.15rem !important;
        line-height: 1.3;
    }
    
    .navbar-brand > div {
        line-height: 1.2;
        padding-top: 1px;
    }
    
    .navbar-brand .navbar-text.date-highlight {
        color: #ffffff !important;
        font-weight: 500;
        opacity: 1 !important;
        font-size: 0.4rem !important;
        line-height: 1.1;
        margin-top: -2px;
    }

    /* Hide Mobile Elements on Desktop */
    .mobile-mini-sidebar,
    .mobile-full-sidebar,
    .sidebar-overlay {
        display: none;
    }
}

/* ========== MOBILE STYLES - PERBAIKAN ========== */
@media (max-width: 991.98px) {
    body {
        padding-top: 40px; /* Dikurangi dari 50px */
        padding-bottom: 90px !important;
    }

    .navbar {
        min-height: 40px !important; /* Dikurangi dari 50px */
        max-height: 40px !important; /* Dikurangi dari 50px */
        padding-top: 0 !important;
        padding-bottom: 0 !important;
    }

    .navbar > .container {
        padding: 0 8px;
        height: 40px !important; /* Disesuaikan dengan tinggi navbar baru */
        position: relative;
        min-height: 40px;
        align-items: 0.1px !important; /* Rapatkan ke atas */
    }

    /* Mobile Logo - Perbaikan posisi */
    .mobile-logo {
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 0;
        position: relative;
        z-index: 2;
        margin-top: -1px; /* Geser lebih ke atas */
    }
    
    /* Brand Mobile Center - Perbaikan posisi dan lebar */
    .navbar-center-mobile {
        display: flex;
        flex-direction: column;
        justify-content: flex-start; /* Ubah dari center ke flex-start */
        align-items: center;
        height: 100%;
        padding: 0;
        text-decoration: none;
        position: absolute !important;
        left: 50% !important;
        transform: translateX(-50%) !important;
        min-width: 240px !important; /* DIPERLEBAR dari 200px */
        max-width: 280px !important; /* Tambahkan batas maksimum */
        width: auto !important;
        z-index: 1 !important;
        text-align: center;
        margin-top: 0.5px; /* Geser lebih ke atas */
    }
    
    .navbar-center-mobile .text-white {
        font-size: 1.05rem; /* Sedikit diperkecil */
        line-height: 1;
        margin: 0;
        margin-top: 2.5px; /* Tambahkan sedikit margin atas */
    }
    
    /* Mobile Date Styling - PERBAIKAN BESAR: Format dan ukuran */
    #navbar-datetime-mobile {
        font-size: 0.79rem !important; /* DIPERBESAR dari 0.35rem */
        margin-top: 0px;
        line-height: 1.2 !important;
        white-space: nowrap; /* Pastikan tidak ada line break */
        overflow: hidden;
        text-overflow: ellipsis;
        width: 100%;
        padding: 0 5px;
        font-weight: 700 !important;
    }

    /* Mini Sidebar */
    .mobile-mini-sidebar {
        position: fixed;
        bottom: 0;
        left: 0;
        right: 0;
        height: 50px;
        background-color: #1b5e20;
        z-index: 1030;
        display: flex;
        border-top: 1px solid rgba(255,255,255,0.1);
        box-shadow: 0 -2px 10px rgba(0,0,0,0.1);
    }
    
    /* Format khusus untuk teks tanggal di mobile */
    .navbar-center-mobile .navbar-text.date-highlight {
        display: inline-block;
        max-width: 100%;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        font-weight: 700;
        color: #ffffff !important;
    }

    .mobile-mini-sidebar .mini-sidebar-content {
        display: flex;
        justify-content: space-around;
        align-items: center;
        width: 100%;
        padding: 0 5px;
    }

    .mobile-mini-sidebar .mini-item {
        transition: all 0.4s ease-in-out;
        display: flex;
        align-items: center;
        justify-content: center;
        width: 37px;
        height: 37px;
        font-size: 1.2rem;
        border-radius: 50%;
        color: rgba(255,255,255,0.7);
    }

    .mobile-mini-sidebar .mini-item.active,
    .mobile-mini-sidebar .mini-item:hover {
        color: white;
        background-color: rgba(255,255,255,0.1);
        transform: translateY(-1px);
    }

    /* Full Sidebar */
    .mobile-full-sidebar {
        position: fixed;
        top: 0;
        right: -300px;
        height: 100vh;
        width: 280px;
        background-color: #1b5e20;
        z-index: 1040;
        transition: right 0.3s ease-in-out;
        box-shadow: -2px 0 10px rgba(0,0,0,0.2);
        display: flex;
        flex-direction: column;
    }

    .mobile-full-sidebar.active {
        right: 0;
    }

    .sidebar-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 15px 20px;
        border-bottom: 1px solid rgba(255,255,255,0.1);
    }

    .sidebar-title {
        color: white;
        margin: 0;
        font-size: 1.2rem;
    }

    .sidebar-close-btn {
        background: none;
        border: none;
        color: white;
        font-size: 1.5rem;
        width: 40px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        transition: background-color 0.2s;
    }

    .sidebar-close-btn:hover {
        background-color: rgba(255,255,255,0.1);
    }

    .sidebar-content {
        padding: 20px;
        overflow-y: auto;
        flex: 1;
    }

    .sidebar-item {
        font-size: 1rem;
        display: block;
        padding: 12px 15px;
        margin-bottom: 8px;
        color: rgba(255,255,255,0.8);
        text-decoration: none;
        border-radius: 8px;
        transition: all 0.2s;
    }

    .sidebar-item:hover,
    .sidebar-item.active {
        background-color: rgba(255,255,255,0.1);
        color: white;
    }

    .sidebar-item i {
        width: 30px;
        text-align: center;
    }
    
    /* Mobile admin Access */
    .user-access-admin {
        padding: 10px 5px 5px;
        margin-top: 10px;
        border-top: 1px solid rgba(255,255,255,0.1);
    }

    /* Mobile User Info */
    .user-info-mobile {
        text-align: center;
        padding: 25px 15px 75px;
        margin-top: 20px;
        border-top: 1px solid rgba(255,255,255,0.1);
    }
    
    .user-avatar {
        font-size: 3rem;
        color: white;
        margin-bottom: 10px;
    }

    .user-actions {
        margin-top: 15px;
    }
    
    /* TAMBAHKAN STYLING UNTUK NAMA USER MOBILE */
    .user-name-mobile {
        font-size: 1.1rem;
        font-weight: 600;
        margin-bottom: 15px;
        word-break: break-word;
        line-height: 1.3;
        font-family: "Palatino Linotype", "Book Antiqua", Palatino, "Times New Roman", serif;
        letter-spacing: 0.3px;
        text-shadow: 0 1px 2px rgba(0,0,0,0.1);
        font-style: italic;
    }

    .sidebar-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0,0,0,0.5);
        z-index: 1039;
        display: none;
    }

    /* Custom Toggler - Perbaikan posisi */
    .custom-toggler {
        border: none !important;
        outline: none !important;
        box-shadow: none !important;
        background: transparent !important;
        margin: 0;
        padding: 0;
        position: relative;
        z-index: 2;
        margin-top: 0.3px; /* Geser lebih ke atas */
    }

    .custom-toggler:hover {
        background-color: rgba(255, 255, 255, 0.1) !important;
    }

    .custom-toggler-icon {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 100%;
        height: 100%;
    }

    /* Tombol Toggle - Perbaikan posisi */
    .navbar > .container .d-flex.align-items-center.ms-1 {
        margin-left: 4px !important;
        margin-top: 1px; /* Geser lebih ke atas */
    }
    
      /* Responsive adjustments untuk brand yang lebih kecil */
    @media (max-width: 400px) {
        .navbar-center-mobile {
            min-width: 220px !important;
            max-width: 240px !important;
        }
        
        .navbar-center-mobile .text-white {
            font-size: 1.1rem !important;
        }
        
        #navbar-datetime-mobile {
            font-size: 0.65rem !important;
        }
    }

    /* Responsive adjustments untuk brand yang lebih kecil */
    @media (max-width: 360px) {
        .navbar-center-mobile {
            min-width: 200px !important;
            max-width: 220px !important;
        }
        
        .navbar-center-mobile .text-white {
            font-size: 0.95rem !important;
        }
        
        #navbar-datetime-mobile {
            font-size: 0.6rem !important;
        }
    }
    
    @media (max-width: 320px) {
        .navbar-center-mobile {
            min-width: 180px !important;
            max-width: 200px !important;
        }
        
        .navbar-center-mobile .text-white {
            font-size: 0.9rem !important;
        }
        
        #navbar-datetime-mobile {
            font-size: 0.55rem !important;
        }
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const mobileMenuToggle = document.getElementById('mobileMenuToggle');
    const fullSidebar = document.querySelector('.mobile-full-sidebar');
    const sidebarCloseBtn = document.getElementById('sidebarCloseBtn');
    const sidebarOverlay = document.createElement('div');
    
    sidebarOverlay.className = 'sidebar-overlay';
    document.body.appendChild(sidebarOverlay);
    
    mobileMenuToggle.addEventListener('click', function() {
        fullSidebar.classList.add('active');
        sidebarOverlay.style.display = 'block';
    });
    
    sidebarOverlay.addEventListener('click', function() {
        fullSidebar.classList.remove('active');
        this.style.display = 'none';
    });
    
    sidebarCloseBtn.addEventListener('click', function() {
        fullSidebar.classList.remove('active');
        sidebarOverlay.style.display = 'none';
    });
    
    const currentPage = location.pathname.split('/').pop();
    const menuItems = document.querySelectorAll('.mini-item, .sidebar-item');
    
    menuItems.forEach(item => {
        if (item.getAttribute('href') === currentPage) {
            item.classList.add('active');
        }
    });
    
    // Fungsi untuk update tanggal Hijriyah setiap hari
    function updateHijriDate() {
        const now = new Date();
        const hours = now.getHours();
        const minutes = now.getMinutes();
        
        // Reset cache dan reload pada pukul 00:01
        if (hours === 0 && minutes === 1) {
            // Hapus cache session
            fetch('?clear_hijri_cache=1')
                .then(() => {
                    console.log('Hijri date cache cleared');
                    location.reload();
                })
                .catch(err => console.error('Error clearing cache:', err));
        }
    }
    
    // Update setiap menit untuk cek perubahan hari
    setInterval(updateHijriDate, 60000);
    
    // Handle error display
    const hijriElement = document.getElementById('hijri-date-text');
    if (hijriElement && hijriElement.textContent.includes('undefined')) {
        hijriElement.textContent = 'Loading tanggal Hijriyah...';
        
        // Retry after 3 seconds
        setTimeout(() => {
            location.reload();
        }, 3000);
    }
    
});

// Endpoint untuk clear cache
<?php if (isset($_GET['clear_hijri_cache'])): ?>
<?php
    unset($_SESSION['hijri_date_cache']);
    echo 'Cache cleared';
    exit;
?>
<?php endif; ?>

// PERBAIKAN: Fungsi untuk memperbarui tanggal dan waktu di navbar
    function updateNavbarDateTime() {
        const now = new Date();
        const days = ['Ahad', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
        const months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Ags', 'Sep', 'Okt', 'Nov', 'Des'];
        
        const day = days[now.getDay()];
        const date = now.getDate();
        const month = months[now.getMonth()];
        const year = now.getFullYear();
        
        const hours = String(now.getHours()).padStart(2, '0');
        const minutes = String(now.getMinutes()).padStart(2, '0');
        
        // Format untuk navbar (lebih ringkas)
        const dateFormat = `${date} ${month} ${year}`;
        const timeFormat = `${hours}:${minutes}`;
        
        // Update kedua elemen datetime (desktop dan mobile)
        const desktopDatetime = document.getElementById('navbar-datetime');
        const mobileDatetime = document.getElementById('navbar-datetime-mobile');
        
        if (desktopDatetime) {
            // Untuk desktop, tampilkan dalam format yang sudah ada
            const existingHijri = desktopDatetime.querySelector('.navbar-text')?.innerHTML || '';
            desktopDatetime.innerHTML = `<span class="navbar-text">${existingHijri}</span>`;
        }
        
        if (mobileDatetime) {
            // Untuk mobile, tampilkan dalam format yang sudah ada  
            const existingHijri = mobileDatetime.querySelector('.navbar-text')?.innerHTML || '';
            mobileDatetime.innerHTML = `<span class="navbar-text">${existingHijri}</span>`;
        }
    }

    // Panggil fungsi update datetime setiap menit
    setInterval(updateNavbarDateTime, 60000);
    updateNavbarDateTime(); // Panggil pertama kali saat halaman dimuat

// Fungsi untuk menampilkan foto profil pengguna
function showUserPhoto(photoUrl) {
    $('#modalUserPhoto').attr('src', photoUrl);
    $('#userPhotoModal').modal('show');
}

// Event handler untuk foto profil di sidebar mobile - versi improved
document.addEventListener('DOMContentLoaded', function() {
    // Handler untuk sidebar mobile
    const userAvatarMobile = document.querySelector('.mobile-full-sidebar .user-avatar img, .mobile-full-sidebar .user-avatar i.bi-person-circle');
    if (userAvatarMobile) {
        if (userAvatarMobile.tagName === 'IMG') {
            userAvatarMobile.style.cursor = 'pointer';
            userAvatarMobile.addEventListener('click', function() {
                showUserPhoto(this.src);
            });
        } else if (userAvatarMobile.classList.contains('bi-person-circle')) {
            userAvatarMobile.style.cursor = 'pointer';
            userAvatarMobile.addEventListener('click', function() {
                showUserPhoto('../assets/img/default-avatar.png');
            });
        }
    }
    
    // Handler untuk dropdown desktop (jika ada)
    const userPhotoDropdown = document.querySelector('.dropdown .btn img');
    if (userPhotoDropdown) {
        userPhotoDropdown.style.cursor = 'pointer';
        userPhotoDropdown.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            showUserPhoto(this.src);
        });
    }
});

// Fungsi untuk menyesuaikan lebar brand mobile secara dinamis
function adjustMobileBrandWidth() {
    if (window.innerWidth <= 991) {
        const navbarContainer = document.querySelector('.navbar > .container');
        const mobileLogo = document.querySelector('.mobile-logo');
        const menuToggle = document.querySelector('.custom-toggler');
        
        if (navbarContainer && mobileLogo && menuToggle) {
            const containerWidth = navbarContainer.offsetWidth;
            const leftSpace = mobileLogo.offsetWidth + 20; // logo + margin
            const rightSpace = menuToggle.offsetWidth + 20; // toggle + margin
            const availableWidth = containerWidth - leftSpace - rightSpace;
            
            const brandMobile = document.querySelector('.navbar-center-mobile');
            if (brandMobile) {
                // Set minimal 220px, maksimal available width
                const newWidth = Math.max(220, Math.min(320, availableWidth - 20));
                brandMobile.style.minWidth = newWidth + 'px';
            }
        }
    }
}

// Panggil saat load dan resize
document.addEventListener('DOMContentLoaded', function() {
    adjustMobileBrandWidth();
    window.addEventListener('resize', adjustMobileBrandWidth);
});

</script>