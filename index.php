<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
// index.php
require_once 'includes/config.php';
require_once 'includes/auth.php';

// Pastikan session sudah dimulai
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// PERBAIKAN: Include fungsi hijriyah untuk halaman login
require_once 'includes/hijri_functions.php';

if (check_auth()) {
    header("Location: pages/dashboard.php");
    exit();
}

$error = "";
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];
    $remember = isset($_POST['remember']);
    
    if (login($username, $password, $remember)) {
        header("Location: pages/dashboard.php");
        exit();
    } else {
        $error = "Username atau password salah!";
    }
}

// PERBAIKAN: Gunakan cookie untuk mode gelap di halaman login
$dark_mode = isset($_COOKIE['dark_mode_pref']) ? (int)$_COOKIE['dark_mode_pref'] : 0;

// PERBAIKAN: Ambil tanggal Hijriyah untuk halaman login
$today = date('Y-m-d');
try {
    $tanggal_hijriyah_login = get_hijri_date_kemenag($today);
} catch (Exception $e) {
    error_log("Error getting hijri date for login: " . $e->getMessage());
    $tanggal_hijriyah_login = date('d M Y') . ' H';
}

// Pastikan tidak ada undefined
if (empty($tanggal_hijriyah_login) || strpos($tanggal_hijriyah_login, 'undefined') !== false) {
    $tanggal_hijriyah_login = date('d M Y') . ' H';
}
?>
<!DOCTYPE html>
<html lang="id" data-bs-theme="<?= $dark_mode ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistem Absensi Online - Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Naskh+Arabic:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <!-- PERBAIKAN: Tambahkan Bootstrap Icons untuk ikon bulan -->
    <!--<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">-->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <!-- PWA Configuration -->
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#1b5e20">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="AbsensiPPMA">
    
    <!-- Apple Touch Icons -->
    <link rel="apple-touch-icon" href="assets/icons/icon-152x152.png">
    <link rel="apple-touch-icon" sizes="167x167" href="assets/icons/icon-167x167.png">
    <link rel="apple-touch-icon" sizes="180x180" href="assets/icons/icon-180x180.png">
    
    <!-- Splash Screens for iOS -->
    <link rel="apple-touch-startup-image" href="assets/splash/splash-640x1136.png" media="(device-width: 320px) and (device-height: 568px) and (-webkit-device-pixel-ratio: 2) and (orientation: portrait)">
    <link rel="apple-touch-startup-image" href="assets/splash/splash-750x1334.png" media="(device-width: 375px) and (device-height: 667px) and (-webkit-device-pixel-ratio: 2) and (orientation: portrait)">
    <link rel="apple-touch-startup-image" href="assets/splash/splash-1242x2208.png" media="(device-width: 414px) and (device-height: 736px) and (-webkit-device-pixel-ratio: 3) and (orientation: portrait)">
    
    <!-- Microsoft Tile -->
    <meta name="msapplication-TileColor" content="#1b5e20">
    <meta name="msapplication-TileImage" content="assets/icons/icon-144x144.png">
    <style>
        /* Tambahkan style baru untuk dua baris */
        .typing-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            overflow: visible;
        }

        #typing-text-line1, #typing-text-line2 {
            display: inline-block;
            position: relative;
            overflow: hidden;
            white-space: nowrap;
            letter-spacing: 0;
        }

        #typing-text-line1::after, #typing-text-line2::after {
            content: '|';
            display: inline-block;
            animation: blink 0.5s infinite;
        }

        @keyframes blink {
            0%, 100% { opacity: 1; }
            50% { opacity: 0; }
        }


        /* Animasi untuk logo */
        .login-logo {
            opacity: 0;
            animation: logoAppear 1.5s forwards;
            animation-delay: 0.5s;
        }
        
        /* Di dalam tag <style>*/
        @keyframes logoAppear {
            0% {
                opacity: 0;
                transform: translateY(-100px); /* Ubah dari atas */
            }
            100% {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Animasi untuk dua baris teks */
        .typing-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
        }

        .typing-line {
            display: inline-block;
            position: relative;
            overflow: hidden;
            white-space: nowrap;
            letter-spacing: 0;
            margin: 3px 0;
        }

        .typing-line::after {
            content: '|';
            display: inline-block;
            animation: blink 0.7s infinite;
        }

        .typing-line.typing::after {
            opacity: 1;
        }

        .typing-line.completed::after {
            display: none;
        }

        @keyframes blink {
            0%, 100% { opacity: 1; }
            50% { opacity: 0; }
        }

        .typing-container {
            display: inline-block;
            overflow: visible; /* Perubahan di sini */
            white-space: nowrap;
            margin: 0 auto;
            width: 0;
            animation: typing 2.5s steps(50, end) forwards;
        
        .welcome-message {
            min-height: 1.5em; /* Pastikan tinggi cukup saat karakter belum muncul */
        }

        /* ... animasi logo ... */
        @keyframes logoAppear {
            0% {
                opacity: 0;
                transform: translateY(-100px);
            }
            100% {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Animasi baru untuk judul sistem */
        .fade-in-down {
            opacity: 0;
            animation: fadeInDown 1s forwards;
            animation-delay: 1s; /* Mulai setelah logo muncul */
        }

        @keyframes fadeInDown {
            0% {
                opacity: 0;
                transform: translateY(-20px);
            }
            100% {
                opacity: 1;
                transform: translateY(0);
            }
        }


        /* Animasi typing untuk judul sistem */
        .typing-container span {
            display: inline-block;
            opacity: 0;
            transform: translateY(-10px);
            animation: appear 0.1s forwards;
            animation-delay: calc(0.05s * var(--i));
        }

        @keyframes appear {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Pastikan container tidak memotong teks */
        .typing-container {
            display: inline-block;
            overflow: visible;
            white-space: nowrap;
        }

        .typing-container {
            height: auto; /* Pastikan tinggi cukup */
            overflow: visible; /* Perbaikan overflow */
        }

        .typing-line {
            display: block;
            text-align: center;
            position: relative;
            overflow: hidden;
            white-space: nowrap;
            margin: 0 auto;
            width: 0;
            animation: expandWidth 0.1s forwards;
            animation-delay: 1.5s;
        }

        .typing-line.typing::after {
            content: '|';
            display: inline-block;
            animation: blink 0.5s infinite;
            margin-left: 2px;
        }

        .typing-line.completed::after {
            display: none;
        }

        @keyframes expandWidth {
            to {
                width: 100%;
            }
        }

        @keyframes blink {
            0%, 100% { opacity: 1; }
            50% { opacity: 0; }
        }

        /* Untuk judul dua baris */
        h2 {
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        /* ANIMASI UNTUK PESAN LOGIN - FIXED VERSION */
        .welcome-login-container {
            min-height: 24px;
            margin: 15px 0;
            overflow: visible;
        }

        #loginMessage {
            display: inline-block;
            font-size: 1.1rem;
            color: #6c757d;
        }

        #loginMessage span {
            display: inline-block;
            opacity: 0;
            filter: blur(5px);
            animation-duration: 0.8s;
            animation-fill-mode: forwards;
            animation-name: fadeInBlur;
        }

        #loginMessage .space {
            width: 0.3em;
        }

        @keyframes fadeInBlur {
            to {
                opacity: 1 !important;
                filter: blur(0) !important;
            }
        }

        #loginMessage span {
            animation: fadeInBlur 0.8s forwards !important;
        }

        /* index.php */
        [data-bs-theme="dark"] #loginMessage {
            color: #ced4da !important;
        }

        [data-bs-theme="dark"] .form-label {
            color: #f8f9fa !important;
        }

        /* PERBAIKAN: Style untuk tampilan tanggal Hijriyah dan Masehi di login */
        .real-time-display {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            width: 100%;
            text-align: center;
            margin-bottom: 1rem;
        }
        
        .hijri-date-display {
            font-size: 1.1rem;
            font-weight: 600;
            color: #ffffff;
            margin-bottom: 5px;
            text-align: center;
            width: 100%;
        }
        
        #datetime {
            text-align: center;
            width: 100%;
            font-weight: bold;
            color: white;
            font-size: 1.2rem;
        }
        
        /* Dalam tag <style> tambahkan: */
        .input-group .btn-outline-secondary {
            border-color: #ced4da;
            background-color: transparent;
        }
        
        .input-group .btn-outline-secondary:hover {
            background-color: #f8f9fa;
        }
        
        [data-bs-theme="dark"] .input-group .btn-outline-secondary {
            border-color: #495057;
            color: #dee2e6;
        }
        
        [data-bs-theme="dark"] .input-group .btn-outline-secondary:hover {
            background-color: #495057;
        }
        
        /* Style khusus untuk tombol toggle password */
        #togglePassword {
            min-width: 45px;
            border-left: 0;
        }
        
        #togglePassword:hover {
            background-color: #e9ecef;
        }
        
        [data-bs-theme="dark"] #togglePassword:hover {
            background-color: #495057;
        }
        
        .input-group:focus-within #togglePassword {
            border-color: #86b7fe;
            z-index: 3;
        }
        
        /* Pastikan input group terlihat baik */
        .input-group .form-control {
            border-right: 0;
        }
        
        .input-group .form-control:focus {
            border-right: 0;
            box-shadow: none;
        }
        
        .input-group .form-control:focus + #togglePassword {
            border-color: #86b7fe;
        }
        
    </style>
</head>
<body class="login-page">
    <div class="welcome-login-container">
                
        <!-- Tempatkan info waktu di sini, setelah judul -->
        <div class="real-time-display fw-bold text-white text-center mb-3">
            <!-- PERBAIKAN: Tambahkan tanggal Hijriyah di atas tanggal Masehi -->
            <div id="hijri-date" class="hijri-date-display ">
                <i class="bi bi-moon-stars-fill me-1"></i>
                <?= htmlspecialchars($tanggal_hijriyah_login) ?>
            </div>
            
            <div id="datetime" class="fw-bold text-white"></div>
        </div>
        
        <!-- Sisanya tetap sama -->
        <div class="login-card">
            <div class="text-center mb-4">
                <img src="assets/img/Logo_PP_Matholi'ul_Anwar.png" alt="Logo Sekolah" class="login-logo">
                <h2 class="mt-3">
                    <!-- Ubah menjadi dua baris terpisah -->
                    <div id="typing-line1" class="typing-line"></div>
                    <div id="typing-line2" class="typing-line"></div>
                </h2>
                
                <p class="text-muted" id="loginMessage">
                    <?php
                    $welcomeMsg = "Masuk untuk mengakses sistem";
                    $welcomeChars = preg_split('//u', $welcomeMsg, -1, PREG_SPLIT_NO_EMPTY);
                    foreach ($welcomeChars as $char) {
                        $delay = rand(0, 1500) / 1000; // Delay acak 0-1.5 detik
                        if ($char === ' ') {
                            echo '<span class="space" style="animation-delay: '.$delay.'s;">&nbsp;</span>';
                        } else {
                            echo '<span style="animation-delay: '.$delay.'s;">'.$char.'</span>';
                        }
                    }
                    ?>
                </p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="mb-3">
                    <label for="username" class="form-label">Username</label>
                    <input type="text" class="form-control" id="username" name="username" required>
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <div class="input-group">
                        <input type="password" class="form-control" id="password" name="password" required>
                        <button class="btn btn-outline-secondary" type="button" id="togglePassword" aria-label="Lihat password">
                            <i class="bi bi-lightbulb-fill"></i>
                        </button>
                    </div>
                    <div class="form-text mt-1">
                        Tekan Shift+Enter pada field password untuk melihat
                    </div>
                </div>
                <div class="mb-3 form-check">
                    <input type="checkbox" class="form-check-input" id="remember" name="remember">
                    <label class="form-check-label" for="remember">Ingat saya</label>
                </div>
                <button type="submit" class="btn btn-primary w-100">Masuk</button> 
            </form>

            <div class="mt-3 text-center">
                <p class="mb-1">Login sebagai:</p>                
                <div class="d-flex justify-content-center gap-2">
                    <button class="btn btn-sm btn-outline-secondary demo-btn" data-user="admin">Admin</button>
                    <button class="btn btn-sm btn-outline-secondary demo-btn" data-user="walikelas">Wali Kelas</button>
                    <button class="btn btn-sm btn-outline-secondary demo-btn" data-user="walimurid">Wali Murid</button>
                </div>
            </div>
        </div>

        <div id="login-footer" class="text-white">
            <p>© <?php echo date('Y'); ?> Pondok Pesantren Matholi'ul Anwar. All rights reserved.</p>
        </div>

    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>

    // Fallback untuk browser yang menolak animasi pertama kali
    let animationAttempts = 0;
    const startAnimations = () => {
        const spans = document.querySelectorAll('#loginMessage span');
        
        spans.forEach(span => {
            span.style.animation = 'none';
            void span.offsetWidth;
            span.style.animation = '';
        });
        
        // Coba lagi setelah 300ms jika belum berjalan
        if (animationAttempts < 3) {
            animationAttempts++;
            setTimeout(startAnimations, 300);
        }
    };    

    document.addEventListener('DOMContentLoaded', function() {
        const line1 = "Sistem Absensi Online";
        const line2 = "PPMA";
        const element1 = document.getElementById('typing-line1');
        const element2 = document.getElementById('typing-line2');
        let i = 0;
        const speed = 40; // kecepatan typing (ms per karakter)

        // Mulai dengan baris pertama
        element1.classList.add('typing');
        
        function typeWriterLine1() {
            if (i < line1.length) {
                element1.innerHTML += line1.charAt(i);
                i++;
                setTimeout(typeWriterLine1, speed);
            } else {
                i = 0;
                element1.classList.remove('typing');
                element1.classList.add('completed');
                
                // Mulai baris kedua setelah jeda
                setTimeout(() => {
                    element2.classList.add('typing');
                    typeWriterLine2();
                }, 500);
            }
        }

        function typeWriterLine2() {
            if (i < line2.length) {
                element2.innerHTML += line2.charAt(i);
                i++;
                setTimeout(typeWriterLine2, speed);
            } else {
                element2.classList.remove('typing');
                element2.classList.add('completed');
            }
        }

        // Animasi untuk "Masuk untuk mengakses sistem"
            const welcomeSpans = document.querySelectorAll('.welcome-message span');
            welcomeSpans.forEach(span => {
                // Set delay acak antara 0-2 detik
                const randomDelay = Math.random() * 2;
                span.style.animationDelay = `${randomDelay}s`;
                span.style.animationPlayState = 'running';
            });
            
            // Mulai animasi setelah delay kecil
            setTimeout(() => {
                welcomeSpans.forEach(span => {
                    span.style.animationPlayState = 'running';
                });
            }, 500);

        // Mulai animasi setelah logo muncul
        setTimeout(typeWriterLine1, 700);
        
        // Hapus konten sebelumnya
        container.innerHTML = '';
        
        // Bangun karakter per karakter
        for (let i = 0; i < message.length; i++) {
            const char = message[i];
            const span = document.createElement('span');
            
            if (char === ' ') {
                span.className = 'space';
                span.innerHTML = '&nbsp;';
            } else {
                span.textContent = char;
            }
            
            // Set delay acak 0-1.5 detik
            const delay = Math.random() * 1.5;
            span.style.animationDelay = `${delay}s`;
            
            container.appendChild(span);
        }
        
        // Paksa render ulang untuk memicu animasi
        setTimeout(() => {
            const spans = container.querySelectorAll('span');
            spans.forEach(span => {
                // Trik untuk memaksa animasi restart
                span.style.animation = 'none';
                void span.offsetWidth; // Trigger reflow
                span.style.animation = '';
            });
        }, 100);  
    
         setTimeout(startAnimations, 200);

        // Mulai animasi setelah logo muncul
        setTimeout(typeWriter, 1500);
    });

        $(document).ready(function() {
            $('.demo-btn').click(function() {
                const user = $(this).data('user');
                let username = '', password = '';
                
                switch(user) {
                    case 'admin':
                        username = 'admin';
                        password = 'admin123';
                        break;
                    case 'walikelas':
                        username = 'wali_kelas';
                        password = 'wali123';
                        break;
                    case 'walimurid':
                        username = 'wali_murid';
                        password = 'murid123';
                        break;
                }
                
                $('#username').val(username);
                $('#password').val(password);
            });
            // ===== TOGGLE PASSWORD VISIBILITY =====
            const togglePassword = $('#togglePassword');
            const passwordInput = $('#password');
            
            if (togglePassword.length && passwordInput.length) {
                // Fungsi untuk toggle password
                const togglePasswordVisibility = function() {
                    const isPassword = passwordInput.attr('type') === 'password';
                    
                    if (isPassword) {
                        passwordInput.attr('type', 'text');
                        togglePassword.find('i')
                            .removeClass('bi-lightbulb-fill')
                            .addClass('bi bi-lightbulb-off');
                        togglePassword.attr('aria-label', 'Sembunyikan password');
                    } else {
                        passwordInput.attr('type', 'password');
                        togglePassword.find('i')
                            .removeClass('bi bi-lightbulb-off')
                            .addClass('bi-lightbulb-fill');
                        togglePassword.attr('aria-label', 'Lihat password');
                    }
                };
                
                // Event listener untuk tombol toggle
                togglePassword.on('click', togglePasswordVisibility);
                
                // Event listener untuk Shift+Enter pada password field
                passwordInput.on('keydown', function(e) {
                    if (e.key === 'Enter' && e.shiftKey) {
                        e.preventDefault();
                        togglePasswordVisibility();
                    }
                });
                
                // Inisialisasi tooltip Bootstrap
                const tooltip = new bootstrap.Tooltip(togglePassword[0], {
                    title: 'Klik untuk melihat/sembunyikan password',
                    placement: 'top'
                });
                
                console.log('Password toggle initialized successfully');
            } else {
                console.error('Password toggle elements not found');
            }
        });

        // Ganti fungsi updateRealTime dengan yang baru
        function updateRealTime() {
            const now = new Date();
            const days = ['Ahad', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
            const months = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
            
            const day = days[now.getDay()];
            const date = now.getDate();
            const month = months[now.getMonth()];
            const year = now.getFullYear();
            
            const hours = String(now.getHours()).padStart(2, '0');
            const minutes = String(now.getMinutes()).padStart(2, '0');
            const seconds = String(now.getSeconds()).padStart(2, '0');
            
            // Format dalam satu baris: Hari, Tanggal Bulan Tahun | Jam:Menit:Detik
            document.getElementById('datetime').textContent = `${day}, ${date} ${month} ${year} M | ${hours}:${minutes}:${seconds}`;
        }
        
        // Update setiap detik
        setInterval(updateRealTime, 1000);
        updateRealTime(); // Panggil pertama kali
    
    // PWA Installation
    class PWAHelper {
        constructor() {
            this.deferredPrompt = null;
            this.init();
        }
    
        init() {
            // Register Service Worker
            if ('serviceWorker' in navigator) {
                window.addEventListener('load', () => {
                    navigator.serviceWorker.register('/sw.js')
                        .then(registration => {
                            console.log('SW registered: ', registration);
                        })
                        .catch(registrationError => {
                            console.log('SW registration failed: ', registrationError);
                        });
                });
            }
    
            // Listen for install prompt
            window.addEventListener('beforeinstallprompt', (e) => {
                e.preventDefault();
                this.deferredPrompt = e;
                this.showInstallPromotion();
            });
    
            // Track app installed event
            window.addEventListener('appinstalled', (evt) => {
                console.log('PWA was installed');
                this.hideInstallPromotion();
            });
        }
    
        showInstallPromotion() {
            // Create install button if not exists
            if (!document.getElementById('pwa-install-btn')) {
                const installBtn = document.createElement('button');
                installBtn.id = 'pwa-install-btn';
                installBtn.className = 'btn btn-success btn-sm position-fixed';
                installBtn.style.cssText = 'bottom: 20px; right: 20px; z-index: 9999; box-shadow: 0 4px 12px rgba(0,0,0,0.3);';
                installBtn.innerHTML = '<i class="bi bi-download me-1"></i> Install App';
                installBtn.onclick = () => this.installApp();
                
                document.body.appendChild(installBtn);
            }
        }
    
        hideInstallPromotion() {
            const installBtn = document.getElementById('pwa-install-btn');
            if (installBtn) {
                installBtn.remove();
            }
        }
    
        async installApp() {
            if (this.deferredPrompt) {
                this.deferredPrompt.prompt();
                const { outcome } = await this.deferredPrompt.userChoice;
                
                if (outcome === 'accepted') {
                    console.log('User accepted the install prompt');
                } else {
                    console.log('User dismissed the install prompt');
                }
                
                this.deferredPrompt = null;
                this.hideInstallPromotion();
            }
        }
    }
    
    // Initialize PWA when DOM is loaded
    document.addEventListener('DOMContentLoaded', function() {
        new PWAHelper();
    });
    
    // Check if app is running in standalone mode
    if (window.matchMedia('(display-mode: standalone)').matches || 
        window.navigator.standalone === true) {
        document.documentElement.classList.add('pwa-standalone');
    }
    
    // PWA Testing and Analytics
    function checkPWACompatibility() {
        const compatibility = {
            serviceWorker: 'serviceWorker' in navigator,
            fetch: 'fetch' in window,
            promise: 'Promise' in window,
            cache: 'caches' in window,
            installPrompt: 'BeforeInstallPromptEvent' in window
        };
        
        console.log('PWA Compatibility Check:', compatibility);
        return compatibility;
    }
    
    // Track PWA installation
    function trackPWAInstall() {
        window.addEventListener('appinstalled', (evt) => {
            // Send to analytics
            if (typeof gtag !== 'undefined') {
                gtag('event', 'pwa_installed');
            }
            console.log('PWA installed successfully');
        });
    }
    
    // Initialize when DOM is ready
    document.addEventListener('DOMContentLoaded', function() {
        checkPWACompatibility();
        trackPWAInstall();
    });
    
    <!--// Di bagian akhir index.php-->
    
    // PWA Analytics
    function trackPWAUsage() {
        const displayMode = window.matchMedia('(display-mode: standalone)').matches ? 
            'standalone' : 
            (window.navigator.standalone ? 'standalone' : 'browser');
        
        console.log('App running in:', displayMode + ' mode');
    }
    </script>
    
</body>
</html>