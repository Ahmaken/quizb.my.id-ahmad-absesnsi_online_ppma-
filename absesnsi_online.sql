-- =============================================
-- DATABASE SISTEM ABSENSI ONLINE
-- Dibuat untuk mengelola data santri, kelas, kehadiran, dan aktivitas di lingkungan pesantren
-- =============================================

-- BUAT DAN GUNAKAN DATABASE
-- =============================================
CREATE DATABASE IF NOT EXISTS absensi_online;
USE absensi_online;

-- BERSIHKAN DATA EXISTING DAN RESET AUTO INCREMENT
-- =============================================
SET FOREIGN_KEY_CHECKS = 0;

-- Hapus semua data yang ada
DELETE FROM absensi;
DELETE FROM pelanggaran;
DELETE FROM perizinan;
DELETE FROM jadwal_madin;
DELETE FROM jadwal_kegiatan;
DELETE FROM jadwal_quran;
DELETE FROM murid;
DELETE FROM alumni;
DELETE FROM users;
DELETE FROM kelas_madin;
DELETE FROM kamar;
DELETE FROM kelas_quran;
DELETE FROM guru;

-- Reset auto increment
ALTER TABLE absensi AUTO_INCREMENT = 1;
ALTER TABLE pelanggaran AUTO_INCREMENT = 1;
ALTER TABLE perizinan AUTO_INCREMENT = 1;
ALTER TABLE jadwal_madin AUTO_INCREMENT = 1;
ALTER TABLE jadwal_kegiatan AUTO_INCREMENT = 1;
ALTER TABLE jadwal_quran AUTO_INCREMENT = 1;
ALTER TABLE murid AUTO_INCREMENT = 1;
ALTER TABLE alumni AUTO_INCREMENT = 1;
ALTER TABLE users AUTO_INCREMENT = 1;
ALTER TABLE kelas_madin AUTO_INCREMENT = 1;
ALTER TABLE kamar AUTO_INCREMENT = 1;
ALTER TABLE kelas_quran AUTO_INCREMENT = 1;
ALTER TABLE guru AUTO_INCREMENT = 1;

-- =============================================
-- TABEL USERS (PENGGUNA SISTEM)
-- =============================================
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'wali_kelas', 'wali_murid', 'guru', 'staff') NOT NULL,
    remember_token VARCHAR(100) DEFAULT NULL,
    dark_mode TINYINT(1) DEFAULT 0 COMMENT 'Mode gelap/tema terang',
    foto_profil VARCHAR(255) DEFAULT 'default-avatar.png',
    kelas_id INT DEFAULT NULL COMMENT 'Untuk wali_kelas: kelas yang diampu',
    murid_id INT DEFAULT NULL COMMENT 'Untuk wali_murid: murid yang menjadi tanggungan',
    email VARCHAR(100) NULL,
    is_active BOOLEAN DEFAULT TRUE COMMENT 'Status aktif/nonaktif user',
    last_login TIMESTAMP NULL COMMENT 'Waktu login terakhir',
    nama VARCHAR(100) NULL COMMENT 'Nama lengkap user',
    nip VARCHAR(20) NULL COMMENT 'Nomor Induk Pegawai (untuk guru/staff)',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (kelas_id) REFERENCES kelas_madin(kelas_id) ON DELETE SET NULL,
    FOREIGN KEY (murid_id) REFERENCES murid(murid_id) ON DELETE SET NULL
);

-- UPDATE FOREIGN KEY GURU KE USERS SETELAH TABEL USERS DIBUAT
ALTER TABLE guru ADD FOREIGN KEY (user_id) REFERENCES users(id);

/* CONTOH DATA USERS
INSERT INTO users (username, password, role, kelas_id, murid_id, dark_mode, foto_profil) VALUES 
('admin', 'admin123', 'admin', NULL, NULL, 0, 'default-avatar.png'),
('wali_kelas1', 'wali123', 'wali_kelas', 1, NULL, 0, 'default-avatar.png'),
('wali_murid1', 'walim123', 'wali_murid', NULL, 1, 0, 'default-avatar.png'),
('guru1', 'guru123', 'guru', NULL, NULL, 0, 'default-avatar.png'),
('staff1', 'staff123', 'staff', NULL, NULL, 0, 'default-avatar.png');
*/

-- =============================================
-- TABEL GURU/PEMBINA
-- =============================================
CREATE TABLE IF NOT EXISTS guru (
    guru_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL COMMENT 'ID user untuk login sistem',
    nama VARCHAR(100) NOT NULL,
    nip VARCHAR(20) UNIQUE COMMENT 'Nomor Induk Pegawai',
    nik VARCHAR(20) NULL COMMENT 'Nomor Induk Kependudukan',
    jenis_kelamin ENUM('Laki-laki', 'Perempuan') NULL,
    no_hp VARCHAR(15),
    alamat TEXT,
    jabatan VARCHAR(100) COMMENT 'Jabatan di pesantren (Wali Kelas, Guru Mata Pelajaran, dll)',
    foto VARCHAR(100) NULL COMMENT 'File foto guru',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

/* CONTOH DATA GURU
INSERT INTO guru (nama, nip, nik, jenis_kelamin, no_hp, alamat, jabatan, foto) VALUES 
('Ahmad S.Pd', '196510012000031001', 'Laki-laki', '081234567890', 'Jl. Pendidikan No. 1', 'Wali Kelas VII-A', NULL),
('Siti M.Pd', '197612102005042001', 'Perempuan', NULL, NULL, 'Guru Bahasa Arab', NULL),
('Budi Santoso', NULL, NULL, NULL, NULL, NULL, NULL);
*/

-- =============================================
-- TABEL KELAS MADIN
-- =============================================
CREATE TABLE IF NOT EXISTS kelas_madin (
    kelas_id INT AUTO_INCREMENT PRIMARY KEY,
    nama_kelas VARCHAR(20) NOT NULL UNIQUE COMMENT 'Contoh: VII-A, VIII-B',
    guru_id INT NULL COMMENT 'Wali kelas (boleh kosong)',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (guru_id) REFERENCES guru(guru_id) ON DELETE SET NULL
);

/* CONTOH DATA KELAS MADIN
INSERT INTO kelas_madin (nama_kelas, guru_id) VALUES 
('VII-A', 1),
('VII-B', 2),
('VIII-A', NULL);
*/

-- =============================================
-- TABEL KAMAR ASRAMA
-- =============================================
CREATE TABLE IF NOT EXISTS kamar (
    kamar_id INT AUTO_INCREMENT PRIMARY KEY,
    nama_kamar VARCHAR(50) NOT NULL UNIQUE COMMENT 'Nama kamar asrama',
    kapasitas INT DEFAULT 0 COMMENT 'Jumlah maksimal santri dalam kamar',
    keterangan TEXT COMMENT 'Informasi tambahan tentang kamar',
    guru_id INT NULL COMMENT 'Pembina kamar (boleh kosong)',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (guru_id) REFERENCES guru(guru_id) ON DELETE SET NULL
);

/* CONTOH DATA KAMAR
INSERT INTO kamar (nama_kamar, kapasitas, keterangan, guru_id)
SELECT 
    nama_kamar, 
    kapasitas, 
    keterangan, 
    CASE WHEN g.guru_id IS NOT NULL THEN k.guru_id ELSE NULL END as guru_id
FROM (
    SELECT 'A1' as nama_kamar, 70 as kapasitas, NULL as keterangan, 49 as guru_id
    UNION ALL SELECT 'A2', 70, NULL, 45
    UNION ALL SELECT 'A3', 70, NULL, 44
    UNION ALL SELECT 'A4', 70, NULL, 50
    UNION ALL SELECT 'A5', 70, NULL, NULL
) k
LEFT JOIN guru g ON k.guru_id = g.guru_id;
*/

-- =============================================
-- TABEL KELAS QURAN
-- =============================================
CREATE TABLE IF NOT EXISTS kelas_quran (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_kelas VARCHAR(50) NOT NULL COMMENT 'Nama kelas Quran (Tahfidz, Pemula, Menengah, Lanjut)',
    guru_id INT NULL COMMENT 'Guru pengampu kelas Quran',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (guru_id) REFERENCES guru(guru_id) ON DELETE SET NULL
);

-- =============================================
-- TABEL MURID/SANTRI
-- =============================================
CREATE TABLE IF NOT EXISTS murid (
    murid_id INT AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(100) NOT NULL,
    nis VARCHAR(20) NOT NULL UNIQUE COMMENT 'Nomor Induk Santri',
    nik VARCHAR(20) NULL COMMENT 'Nomor Induk Kependudukan',
    kelas_madin_id INT NULL COMMENT 'Kelas Madrasah Diniyah',
    kelas_quran_id INT NULL COMMENT 'Kelas Al-Quran',
    kamar_id INT NULL COMMENT 'Kamar asrama tempat tinggal',
    no_hp VARCHAR(15) COMMENT 'Nomor HP santri',
    alamat TEXT COMMENT 'Alamat asal santri',
    nama_wali VARCHAR(100) COMMENT 'Nama wali santri',
    no_wali VARCHAR(15) COMMENT 'Nomor HP wali santri',
    nilai DECIMAL(5,2) DEFAULT 0 COMMENT 'Nilai rata-rata santri',
    foto VARCHAR(100) COMMENT 'File foto santri',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (kelas_madin_id) REFERENCES kelas_madin(kelas_id) ON DELETE SET NULL,
    FOREIGN KEY (kamar_id) REFERENCES kamar(kamar_id) ON DELETE SET NULL,
    FOREIGN KEY (kelas_quran_id) REFERENCES kelas_quran(id) ON DELETE SET NULL
);

/* CONTOH DATA MURID
INSERT INTO murid (nama, nis, nik, kelas_madin_id, kelas_quran_id, kamar_id, no_hp, alamat, nama_wali, no_wali, nilai, foto) VALUES
('Ahmad Fauzi', 'NIS001', 1, 1, 1, '081234567890', 'Jl. Merdeka No. 123', 'Bambang Sutisna', '081234567891', 85, 'ahmad.jpg'),
('Dewi Anggraini', 'NIS004', 2, NULL, NULL, '085678901234', NULL, NULL, NULL, 0, NULL),
('Rizki Pratama', 'NIS005', 1, 2, 2, NULL, NULL, 'Suryadi', NULL, 78, NULL);
*/

-- =============================================
-- TABEL JADWAL MADIN
-- =============================================
CREATE TABLE IF NOT EXISTS jadwal_madin (
    jadwal_id INT AUTO_INCREMENT PRIMARY KEY,
    hari ENUM('Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Ahad') NOT NULL,
    jam_mulai TIME NOT NULL,
    jam_selesai TIME NOT NULL,
    mata_pelajaran VARCHAR(100) NOT NULL,
    kelas_madin_id INT NOT NULL,
    guru_id INT NULL COMMENT 'Guru pengajar (boleh kosong)',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (kelas_madin_id) REFERENCES kelas_madin(kelas_id) ON DELETE CASCADE,
    FOREIGN KEY (guru_id) REFERENCES guru(guru_id) ON DELETE SET NULL
);

/* CONTOH DATA JADWAL MADIN
INSERT INTO jadwal_madin (hari, jam_mulai, jam_selesai, mata_pelajaran, kelas_madin_id, guru_id) VALUES 
('Senin', '08:00:00', '09:30:00', 'Matematika', 1, 1),
('Rabu', '07:30:00', '09:00:00', 'Fiqih', 2, 2),
('Kamis', '10:00:00', '11:30:00', 'Bahasa Arab', 1, NULL);
*/

-- =============================================
-- TABEL JADWAL KEGIATAN KAMAR
-- =============================================
CREATE TABLE IF NOT EXISTS jadwal_kegiatan (
    kegiatan_id INT AUTO_INCREMENT PRIMARY KEY,
    hari ENUM('Sabtu', 'Ahad', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat') NOT NULL,
    jam_mulai TIME NOT NULL,
    jam_selesai TIME NOT NULL,
    nama_kegiatan VARCHAR(100) NOT NULL,
    kamar_id INT NOT NULL,
    guru_id INT NULL COMMENT 'Pembina kamar (boleh kosong)',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (kamar_id) REFERENCES kamar(kamar_id) ON DELETE CASCADE,
    FOREIGN KEY (guru_id) REFERENCES guru(guru_id) ON DELETE SET NULL
);

/* CONTOH DATA JADWAL KEGIATAN
INSERT INTO jadwal_kegiatan (hari, jam_mulai, jam_selesai, nama_kegiatan, kamar_id, guru_id) VALUES
('Sabtu', '15:00:00', '16:30:00', 'Kerja Bakti', 1, 1),
('Ahad', '08:00:00', '09:00:00', 'Olahraga', 2, NULL);
*/

-- =============================================
-- TABEL JADWAL QURAN
-- =============================================
CREATE TABLE IF NOT EXISTS jadwal_quran (
    id INT AUTO_INCREMENT PRIMARY KEY,
    kelas_quran_id INT NOT NULL,
    hari ENUM('Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Ahad') NOT NULL,
    jam_mulai TIME NOT NULL,
    jam_selesai TIME NOT NULL,
    mata_pelajaran VARCHAR(100) NOT NULL COMMENT 'Materi pembelajaran Quran',
    guru_id INT NULL COMMENT 'Guru pengajar Quran (boleh kosong)',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (kelas_quran_id) REFERENCES kelas_quran(id) ON DELETE CASCADE,
    FOREIGN KEY (guru_id) REFERENCES guru(guru_id) ON DELETE SET NULL
);

/* CONTOH DATA JADWAL QURAN
INSERT INTO jadwal_quran (kelas_quran_id, hari, jam_mulai, jam_selesai, mata_pelajaran, guru_id) VALUES
(1, 'Senin', '13:00:00', '14:30:00', 'Tajwid', 1),
(2, 'Selasa', '14:00:00', '15:30:00', 'Hafalan', NULL);
*/

-- =============================================
-- TABEL ABSENSI
-- =============================================
CREATE TABLE IF NOT EXISTS absensi (
    absensi_id INT AUTO_INCREMENT PRIMARY KEY,
    jadwal_madin_id INT NOT NULL COMMENT 'Jadwal yang dihadiri',
    murid_id INT NOT NULL,
    tanggal DATE NOT NULL,
    status ENUM('Hadir', 'Sakit', 'Izin', 'Alpa') NOT NULL,
    keterangan TEXT COMMENT 'Alasan jika tidak hadir',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (jadwal_madin_id) REFERENCES jadwal_madin(jadwal_id) ON DELETE CASCADE,
    FOREIGN KEY (murid_id) REFERENCES murid(murid_id) ON DELETE CASCADE
);

/* CONTOH DATA ABSENSI
INSERT INTO absensi (jadwal_madin_id, murid_id, tanggal, status, keterangan) VALUES 
(1, 1, '2023-10-10', 'Hadir', ''),
(1, 2, '2023-10-10', 'Alpa', NULL),
(2, 1, '2023-10-11', 'Sakit', 'Surat dokter terlampir');
*/

-- =============================================
-- TABEL PELANGGARAN
-- =============================================
CREATE TABLE IF NOT EXISTS pelanggaran (
    pelanggaran_id INT AUTO_INCREMENT PRIMARY KEY,
    murid_id INT NOT NULL,
    jenis VARCHAR(100) NOT NULL COMMENT 'Jenis pelanggaran',
    tanggal DATE NOT NULL,
    deskripsi TEXT COMMENT 'Detail pelanggaran',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (murid_id) REFERENCES murid(murid_id) ON DELETE CASCADE
);

/* CONTOH DATA PELANGGARAN
INSERT INTO pelanggaran (murid_id, jenis, tanggal, deskripsi) VALUES 
(1, 'Terlambat', '2023-10-10', 'Terlambat 10 menit'),
(2, 'Tidak memakai seragam', '2023-10-12', NULL),
(3, 'Tidak mengerjakan tugas', '2023-10-13', 'Tugas matematika tidak dikumpulkan');
*/

-- =============================================
-- TABEL PERIZINAN
-- =============================================
CREATE TABLE IF NOT EXISTS perizinan (
    perizinan_id INT AUTO_INCREMENT PRIMARY KEY,
    murid_id INT NOT NULL,
    jenis VARCHAR(100) NOT NULL COMMENT 'Jenis izin (sakit, keluar, dll)',
    tanggal DATE NOT NULL,
    deskripsi TEXT COMMENT 'Alasan izin',
    status_izin ENUM('Disetujui', 'Menunggu', 'Ditolak') DEFAULT 'Menunggu',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (murid_id) REFERENCES murid(murid_id) ON DELETE CASCADE
);

/* CONTOH DATA PERIZINAN
INSERT INTO perizinan (murid_id, jenis, tanggal, deskripsi, status_izin) VALUES
(1, 'Izin Sakit', '2023-10-15', 'Demam tinggi, perlu istirahat', 'Disetujui'),
(2, 'Izin Tidak Masuk', '2023-10-17', NULL, 'Menunggu'),
(3, 'Izin Keluar', '2023-10-18', 'Menjemput keluarga', 'Ditolak');
*/




-- =============================================
-- TABEL ALUMNI
-- =============================================
CREATE TABLE IF NOT EXISTS alumni (
    alumni_id INT AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(255) NOT NULL,
    nis VARCHAR(50) NOT NULL UNIQUE COMMENT 'Nomor Induk Santri saat masih aktif',
    nik VARCHAR(20) NULL COMMENT 'Nomor Induk Kependudukan',
    no_hp VARCHAR(20) NULL,
    tahun_masuk YEAR NOT NULL,
    tahun_keluar YEAR NOT NULL,
    status_keluar ENUM('Lulus', 'Berhenti', 'Dikeluarkan') NOT NULL DEFAULT 'Lulus',
    keterangan TEXT COMMENT 'Alasan keluar atau informasi tambahan',
    pekerjaan VARCHAR(255) COMMENT 'Pekerjaan saat ini',
    pendidikan_lanjut VARCHAR(255) COMMENT 'Jenjang pendidikan yang sedang/telah ditempuh',
    foto VARCHAR(255) COMMENT 'Foto terbaru alumni',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

ALTER TABLE alumni ADD COLUMN alamat TEXT AFTER no_hp;

/* CONTOH DATA ALUMNI
INSERT INTO alumni (nama, nis, no_hp, tahun_masuk, tahun_keluar, status_keluar, keterangan, pekerjaan, pendidikan_lanjut, foto) VALUES
('Muhammad Rizki', 'NIS2018001', '08123456789', 2018, 2021, 'Lulus', 'Lulus dengan nilai terbaik', 'Mahasiswa', 'Universitas Indonesia', 'default-avatar.png'),
('Sari Dewi', 'NIS2018002', '08123456790', 2018, 2021, 'Berhenti', 'Pindah ke kota lain', NULL, NULL, NULL),
('Andi Saputra', 'NIS2019001', NULL, 2019, 2022, 'Lulus', NULL, 'Karyawan Swasta', NULL, NULL);
*/


-- =============================================
-- TABEL ABSENSI GURU - VERSI BERSIH
-- =============================================
CREATE TABLE IF NOT EXISTS absensi_guru (
    -- Primary Key & Identifiers
    absensi_id INT PRIMARY KEY AUTO_INCREMENT,
    guru_id INT NOT NULL,
    -- Referensi Jadwal dan Kegiatan
    jadwal_madin_id INT NULL,
    jadwal_quran_id INT NULL,
    kegiatan_id INT NULL,
    -- Data Waktu
    tanggal DATE NOT NULL,
    waktu_absensi TIMESTAMP NULL,
    deadline_absensi TIMESTAMP NULL,
    -- Status dan Keterangan
    status ENUM('Hadir', 'Sakit', 'Izin', 'Alpa') DEFAULT 'Alpa',
    keterangan TEXT COMMENT 'Alasan jika tidak hadir',
    -- Flags Sistem
    is_otomatis TINYINT(1) DEFAULT 0 COMMENT 'Apakah absensi dibuat otomatis oleh sistem',
    notifikasi_terkirim TINYINT(1) DEFAULT 0,
    bisa_diubah TINYINT(1) DEFAULT 1 COMMENT 'Apakah absensi bisa diubah manual',
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    -- Foreign Keys
    FOREIGN KEY (guru_id) REFERENCES guru(guru_id) ON DELETE CASCADE,
    FOREIGN KEY (jadwal_madin_id) REFERENCES jadwal_madin(jadwal_id) ON DELETE SET NULL,
    FOREIGN KEY (jadwal_quran_id) REFERENCES jadwal_quran(id) ON DELETE SET NULL,
    FOREIGN KEY (kegiatan_id) REFERENCES jadwal_kegiatan(kegiatan_id) ON DELETE SET NULL,
    -- Unique Constraint
    UNIQUE KEY unique_guru_tanggal (guru_id, tanggal)
);

-- =============================================
-- TABEL PENGATURAN ABSENSI OTOMATIS
-- Menyimpan pengaturan untuk fitur absensi otomatis
-- =============================================
CREATE TABLE IF NOT EXISTS pengaturan_absensi_otomatis (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_pengaturan VARCHAR(100) NOT NULL UNIQUE,
    nilai VARCHAR(255) NOT NULL,
    deskripsi TEXT COMMENT 'Penjelasan tentang pengaturan',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- =============================================
-- TABEL LOGIN ATTEMPTS
-- Menyimpan data percobaan login untuk keamanan
-- =============================================
CREATE TABLE IF NOT EXISTS login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    attempt_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    success BOOLEAN DEFAULT FALSE COMMENT 'Apakah login berhasil',
    INDEX idx_username (username),
    INDEX idx_attempt_time (attempt_time)
);

-- =============================================
-- TABEL PENGATURAN NOTIFIKASI
-- =============================================
CREATE TABLE IF NOT EXISTS pengaturan_notifikasi (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_pengaturan VARCHAR(100) NOT NULL UNIQUE,
    nilai VARCHAR(255) NOT NULL,
    deskripsi TEXT COMMENT 'Penjelasan tentang pengaturan',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- =============================================
-- INSERT DATA PENGATURAN DEFAULT
-- =============================================
-- Data pengaturan notifikasi default
INSERT IGNORE INTO pengaturan_notifikasi (nama_pengaturan, nilai, deskripsi) VALUES
('notifikasi_aktif', '1', 'Aktifkan notifikasi jadwal belum diisi (0=nonaktif, 1=aktif)'),
('waktu_tampil_notifikasi', '1', 'Waktu notifikasi muncul setelah jadwal dimulai (dalam jam)'),
('batas_waktu_notifikasi', '24', 'Batas waktu notifikasi tetap muncul (dalam jam)'),
('refresh_otomatis', '5', 'Interval refresh notifikasi otomatis (dalam menit)');

-- Data pengaturan absensi otomatis default
INSERT IGNORE INTO pengaturan_absensi_otomatis (nama_pengaturan, nilai, deskripsi) VALUES
('absensi_otomatis_guru', '0', 'Aktifkan absensi otomatis untuk guru (0=nonaktif, 1=aktif)'),
('waktu_tenggang_absensi', '2', 'Waktu tenggang untuk absensi guru dalam jam');



-- =============================================
-- BUAT INDEX UNTUK PERFORMANCE
-- =============================================
-- Index untuk tabel murid
CREATE INDEX idx_murid_nis ON murid(nis);
CREATE INDEX idx_murid_kelas ON murid(kelas_madin_id);
CREATE INDEX idx_murid_kamar ON murid(kamar_id);

-- Index untuk tabel absensi
CREATE INDEX idx_absensi_tanggal ON absensi(tanggal);
CREATE INDEX idx_absensi_murid_tanggal ON absensi(murid_id, tanggal);
CREATE INDEX idx_absensi_status ON absensi(status);

-- Index untuk tabel jadwal
CREATE INDEX idx_jadwal_hari ON jadwal_madin(hari);
CREATE INDEX idx_jadwal_kelas ON jadwal_madin(kelas_madin_id);
CREATE INDEX idx_jadwal_kegiatan_hari ON jadwal_kegiatan(hari);

-- Index untuk tabel users
CREATE INDEX idx_users_username ON users(username);
CREATE INDEX idx_users_role ON users(role);

-- Index untuk tabel guru
CREATE INDEX idx_guru_nip ON guru(nip);

-- =============================================
-- CONSTRAINTS UNTUK VALIDASI DATA
-- =============================================
-- Constraint untuk memastikan nilai santri antara 0-100
ALTER TABLE murid ADD CONSTRAINT chk_nilai_range 
CHECK (nilai >= 0 AND nilai <= 100);

-- Constraint untuk memastikan jam selesai setelah jam mulai di jadwal
ALTER TABLE jadwal_madin ADD CONSTRAINT chk_jam_valid 
CHECK (jam_selesai > jam_mulai);

-- Constraint untuk format NIS (hanya huruf dan angka)
ALTER TABLE murid ADD CONSTRAINT chk_nis_format 
CHECK (nis REGEXP '^[A-Z0-9]+$');


-- =============================================
-- EVENT UNTUK PEMBERSIHAN DATA OTOMATIS
-- =============================================
DELIMITER //

-- Event untuk membersihkan data percobaan login lama
CREATE EVENT IF NOT EXISTS cleanup_old_login_attempts
ON SCHEDULE EVERY 1 DAY
DO BEGIN
    -- Hapus data percobaan login yang lebih dari 30 hari
    DELETE FROM login_attempts 
    WHERE attempt_time < DATE_SUB(NOW(), INTERVAL 30 DAY);
END//

DELIMITER ;

-- =============================================
-- KETERANGAN STRUKTUR RELASI DATABASE:
-- 1. Guru dapat menjadi: Wali Kelas, Pengajar Madin, Pengajar Quran, Pembina Kamar
-- 2. Santri terdaftar di: Kelas Madin, Kelas Quran, dan Kamar Asrama
-- 3. Absensi dikaitkan dengan Jadwal Madin dan Santri
-- 4. Setiap user sistem dapat memiliki role yang berbeda dengan akses berbeda
-- 5. Data alumni adalah history dari santri yang sudah keluar/lulus
-- =============================================

SET FOREIGN_KEY_CHECKS = 1;
