<?php
// pages/alumni.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../includes/init.php';

if (!check_auth()) {
    header("Location: ../index.php");
    exit();
}

$current_alumni = null;

// Proses simpan alumni
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['tambah_alumni'])) {
        $nama = $_POST['nama'];
        $nis = $_POST['nis'];
        $nik = $_POST['nik'] ?? '';
        $no_hp = $_POST['no_hp'] ?? '';
        $alamat = $_POST['alamat'] ?? ''; // TAMBAHKAN INI
        $tahun_masuk = $_POST['tahun_masuk'];
        $tahun_keluar = $_POST['tahun_keluar'];
        $status_keluar = $_POST['status_keluar'];
        $pekerjaan = $_POST['pekerjaan'] ?? '';
        $pendidikan_lanjut = $_POST['pendidikan_lanjut'] ?? '';
        $keterangan = $_POST['keterangan'] ?? '';

        // Proses upload foto
        $foto = '';
        if (isset($_FILES['foto']) && $_FILES['foto']['error'] == UPLOAD_ERR_OK) {
            $target_dir = "../uploads/";
            if (!file_exists($target_dir)) {
                mkdir($target_dir, 0777, true);
            }
            $file_ext = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
            $new_filename = uniqid() . '.' . $file_ext;
            $target_file = $target_dir . $new_filename;
            
            if (move_uploaded_file($_FILES['foto']['tmp_name'], $target_file)) {
                $foto = $new_filename;
            }
        }

        // PERBAIKI URUTAN: prepare DULU, baru bind_param
        $sql = "INSERT INTO alumni (nama, nis, nik, no_hp, alamat, tahun_masuk, tahun_keluar, status_keluar, pekerjaan, pendidikan_lanjut, foto, keterangan) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            die("Error dalam prepared statement: " . $conn->error);
        }
        $stmt->bind_param("sssssiisssss", $nama, $nis, $nik, $no_hp, $alamat, $tahun_masuk, $tahun_keluar, $status_keluar, $pekerjaan, $pendidikan_lanjut, $foto, $keterangan);
        
        if ($stmt->execute()) {
            $_SESSION['message'] = "success|Data alumni berhasil ditambahkan!";
        } else {
            $_SESSION['message'] = "danger|Error: " . $stmt->error;
        }
        $stmt->close();
        header("Location: alumni.php");
        exit();
    } 
    elseif (isset($_POST['edit_alumni'])) {
        $alumni_id = $_POST['alumni_id'];
        $nama = $_POST['nama'];
        $nis = $_POST['nis'];
        $nik = $_POST['nik'] ?? '';
        $no_hp = $_POST['no_hp'] ?? '';
        $alamat = $_POST['alamat'] ?? ''; // TAMBAHKAN INI
        $tahun_masuk = $_POST['tahun_masuk'];
        $tahun_keluar = $_POST['tahun_keluar'];
        $status_keluar = $_POST['status_keluar'];
        $pekerjaan = $_POST['pekerjaan'] ?? '';
        $pendidikan_lanjut = $_POST['pendidikan_lanjut'] ?? '';
        $keterangan = $_POST['keterangan'] ?? '';

        // Dapatkan foto lama
        $sql_old = "SELECT foto FROM alumni WHERE alumni_id = ?";
        $stmt_old = $conn->prepare($sql_old);
        $stmt_old->bind_param("i", $alumni_id);
        $stmt_old->execute();
        $result_old = $stmt_old->get_result();
        $old_photo = $result_old->fetch_assoc()['foto'] ?? '';
        $foto = $old_photo;

        // Proses upload foto baru jika ada
        if (isset($_FILES['foto']) && $_FILES['foto']['error'] == UPLOAD_ERR_OK) {
            $target_dir = "../uploads/";
            if (!file_exists($target_dir)) {
                mkdir($target_dir, 0777, true);
            }
            $file_ext = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
            $new_filename = uniqid() . '.' . $file_ext;
            $target_file = $target_dir . $new_filename;
            
            if (move_uploaded_file($_FILES['foto']['tmp_name'], $target_file)) {
                $foto = $new_filename;
                // Hapus foto lama jika ada
                if (!empty($old_photo) && file_exists($target_dir . $old_photo)) {
                    unlink($target_dir . $old_photo);
                }
            }
        }

        // PERBAIKI URUTAN: prepare DULU, baru bind_param
        $sql = "UPDATE alumni SET 
                nama = ?, 
                nis = ?, 
                nik = ?,
                no_hp = ?,
                alamat = ?,
                tahun_masuk = ?, 
                tahun_keluar = ?, 
                status_keluar = ?, 
                pekerjaan = ?, 
                pendidikan_lanjut = ?, 
                foto = ?, 
                keterangan = ?
                WHERE alumni_id = ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            die("Error dalam prepared statement: " . $conn->error);
        }
        $stmt->bind_param("sssssiisssssi", $nama, $nis, $nik, $no_hp, $alamat, $tahun_masuk, $tahun_keluar, $status_keluar, $pekerjaan, $pendidikan_lanjut, $foto, $keterangan, $alumni_id);
        
        if ($stmt->execute()) {
            $_SESSION['message'] = "success|Data alumni berhasil diperbarui!";
        } else {
            $_SESSION['message'] = "danger|Error: " . $stmt->error;
        }
        $stmt->close();
        header("Location: alumni.php");
        exit();
    }
}

// Jika ada parameter edit, ambil data alumni
if (isset($_GET['edit_alumni'])) {
    $id = intval($_GET['edit_alumni']);
    $sql = "SELECT * FROM alumni WHERE alumni_id = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        die("Error dalam prepared statement: " . $conn->error);
    }
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $current_alumni = $result->fetch_assoc();
    }
    $stmt->close();
} else {
    $current_alumni = null;
}

// Query untuk mengambil data alumni
$sql = "SELECT alumni_id, nama, nis, nik, no_hp, alamat, tahun_masuk, tahun_keluar, status_keluar, pekerjaan, pendidikan_lanjut, foto, keterangan FROM alumni";
$result = $conn->query($sql);

if (!$result) {
    die("Query error: " . $conn->error);
}

$alumni_list = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $alumni_list[] = $row;
    }
}

// Proses Hapus Alumni
if (isset($_GET['hapus_alumni'])) {
    $id = intval($_GET['hapus_alumni']);
    
    // Hapus foto jika ada
    $sql_foto = "SELECT foto FROM alumni WHERE alumni_id = ?";
    $stmt_foto = $conn->prepare($sql_foto);
    $stmt_foto->bind_param("i", $id);
    $stmt_foto->execute();
    $result_foto = $stmt_foto->get_result();
    $alumni = $result_foto->fetch_assoc();
    
    if ($alumni && !empty($alumni['foto'])) {
        $file_path = "../uploads/" . $alumni['foto'];
        if (file_exists($file_path)) {
            unlink($file_path);
        }
    }
    
    // Hapus data alumni
    $sql = "DELETE FROM alumni WHERE alumni_id = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        die("Error dalam prepared statement: " . $conn->error);
    }
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        $_SESSION['message'] = "success|Data alumni berhasil dihapus!";
    } else {
        $_SESSION['message'] = "danger|Error: " . $stmt->error;
    }
    $stmt->close();
    header("Location: alumni.php");
    exit();
}

// Tampilkan pesan jika ada
if (isset($_SESSION['message'])) {
    list($type, $text) = explode('|', $_SESSION['message'], 2);
    echo '<div class="alert alert-'.$type.' alert-dismissible fade show" role="alert">';
    echo $text;
    echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
    echo '</div>';
    unset($_SESSION['message']);
}

require_once '../includes/navigation.php';
?>

<!DOCTYPE html>
<html lang="id" data-bs-theme="<?= $dark_mode ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Alumni - Sistem Absensi Online</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.5/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    
    <!-- Loading Animation Styles -->
    <style>
        #loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.9);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            transition: opacity 0.3s ease-out;
        }
        
        .loading-content {
            text-align: center;
        }
        
        .loading-spinner {
            width: 364px;
            height: 364px;
            margin-bottom: 15px;
        }
        
        .loading-text {
            font-size: 18px;
            color: #333;
        }
        
        body.dark-mode #loading-overlay {
            background: rgba(0, 0, 0, 0.9);
        }
        
        body.dark-mode .loading-text {
            color: #fff;
        }
        
        .whatsapp-btn {
            background-color: #25D366;
            color: white;
            border: none;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        
        .whatsapp-btn:hover {
            background-color: #128C7E;
            color: white;
        }
    </style>
</head>
<body>
    <!-- Loading Overlay -->
    <div id="loading-overlay">
        <div class="loading-content">
            <img src="../assets/img/logo_ppma_loading.gif" class="loading-spinner" alt="Loading...">
        </div>
    </div>
    
    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-mortarboard me-2"></i> Data Alumni</h2>
            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#alumniModal" id="tambahAlumniBtn">
                <i class="bi bi-plus-circle me-1"></i> Tambah Alumni
            </button>
        </div>

        <div class="card shadow-sm">
            <div class="card-body">
                <!-- Dalam file alumni.php - bagian tabel -->
                <div class="table-responsive">
                    <table id="alumniTable" class="table table-hover">
                        <thead>
                            <tr>
                                <th>Foto</th>
                                <th>Nama</th>
                                <th>NIS</th>
                                <th>NIK</th>
                                <th>WA</th>
                                <th>Alamat</th> <!-- TAMBAHKAN KOLOM INI -->
                                <th>Tahun Masuk</th>
                                <th>Tahun Keluar</th>
                                <th>Status</th>
                                <th>Keterangan</th>
                                <th>Pekerjaan</th>
                                <th>Pendidikan Lanjut</th>
                                <?php if (in_array($_SESSION['role'], ['admin', 'staff'])): ?>
                                <th>Edit</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($alumni_list as $alumni): ?>
                            <tr>
                                <td>
                                    <?php if (!empty($alumni['foto'])): ?>
                                        <img src="../uploads/<?= htmlspecialchars($alumni['foto']) ?>" 
                                             class="rounded-circle" 
                                             width="40" 
                                             height="40" 
                                             alt="Foto"
                                             loading="lazy"
                                             style="cursor:pointer"
                                             onclick="showPhoto('../uploads/<?= htmlspecialchars($alumni['foto']) ?>')">
                                    <?php else: ?>
                                        <img src="../assets/img/default-avatar.png" 
                                             class="rounded-circle" 
                                             width="40" 
                                             height="40" 
                                             alt="Foto"
                                             loading="lazy"
                                             style="cursor:pointer"
                                             onclick="showPhoto('../assets/img/default-avatar.png')">
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($alumni['nama'] ?? '') ?></td>
                                <td><?= htmlspecialchars($alumni['nis'] ?? '') ?></td>
                                <td><?= htmlspecialchars($alumni['nik'] ?? '') ?></td>
                                <td>
                                    <?php if (!empty($alumni['no_hp'])): ?>
                                    <a href="https://wa.me/<?= htmlspecialchars($alumni['no_hp']) ?>" 
                                       class="btn btn-sm btn-success" 
                                       target="_blank"
                                       title="Kirim pesan WhatsApp">
                                        <i class="bi bi-whatsapp"></i>
                                    </a>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span title="<?= htmlspecialchars($alumni['alamat'] ?? '') ?>">
                                        <?= strlen($alumni['alamat'] ?? '') > 50 ? substr(htmlspecialchars($alumni['alamat']), 0, 50) . '...' : htmlspecialchars($alumni['alamat'] ?? '') ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($alumni['tahun_masuk'] ?? '') ?></td>
                                <td><?= htmlspecialchars($alumni['tahun_keluar'] ?? '') ?></td>
                                <td><?= htmlspecialchars($alumni['status_keluar'] ?? '') ?></td>
                                <td><?= htmlspecialchars($alumni['keterangan'] ?? '') ?></td>
                                <td><?= htmlspecialchars($alumni['pekerjaan'] ?? '') ?></td>
                                <td><?= htmlspecialchars($alumni['pendidikan_lanjut'] ?? '') ?></td>
                                <?php if (in_array($_SESSION['role'], ['admin', 'staff'])): ?>
                                <td>
                                    <a href="alumni.php?edit_alumni=<?= $alumni['alumni_id'] ?>" 
                                       class="btn btn-sm btn-warning me-1" 
                                       title="Edit">
                                        <i class="bi bi-pencil-square"></i>
                                    </a>
                                    <a href="alumni.php?hapus_alumni=<?= $alumni['alumni_id'] ?>" 
                                       class="btn btn-sm btn-danger" 
                                       title="Hapus"
                                       onclick="return confirm('Apakah Anda yakin ingin menghapus alumni ini?');">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                </td>
                                <?php endif; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal tambah/edit alumni -->
    <div class="modal fade" id="alumniModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle"><?= $current_alumni ? 'Edit Alumni' : 'Tambah Alumni' ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" enctype="multipart/form-data" id="alumniForm">
                    <div class="modal-body">
                        <?php if ($current_alumni): ?>
                        <input type="hidden" name="alumni_id" value="<?= $current_alumni['alumni_id'] ?>">
                        <?php endif; ?>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Nama <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="nama" required 
                                    value="<?= htmlspecialchars($current_alumni['nama'] ?? '') ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">NIS <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="nis" required 
                                    value="<?= htmlspecialchars($current_alumni['nis'] ?? '') ?>">
                            </div>
                            <!-- Di dalam form alumniModal, tambahkan field NIK setelah field NIS -->
                            <div class="col-md-6 mb-3">
                                <label class="form-label">NIK</label>
                                <input type="text" class="form-control" name="nik" 
                                    value="<?= htmlspecialchars($current_alumni['nik'] ?? '') ?>">
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Nomor WhatsApp</label>
                                <input type="tel" class="form-control" name="no_hp" 
                                    value="<?= htmlspecialchars($current_alumni['no_hp'] ?? '') ?>">
                            </div>
                            <!-- Dalam modal form - pastikan field alamat ada -->
                            <div class="col-12 mb-3">
                                <label class="form-label">Alamat</label>
                                <textarea name="alamat" class="form-control" rows="3"><?= htmlspecialchars($current_alumni['alamat'] ?? '') ?></textarea>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Tahun Masuk <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" name="tahun_masuk" min="1900" max="2099" step="1" required 
                                    value="<?= htmlspecialchars($current_alumni['tahun_masuk'] ?? '') ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Tahun Keluar <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" name="tahun_keluar" min="1900" max="2099" step="1" required 
                                    value="<?= htmlspecialchars($current_alumni['tahun_keluar'] ?? '') ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Pekerjaan</label>
                                <input type="text" class="form-control" name="pekerjaan" 
                                    value="<?= htmlspecialchars($current_alumni['pekerjaan'] ?? '') ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Pendidikan Lanjut</label>
                                <input type="text" class="form-control" name="pendidikan_lanjut" 
                                    value="<?= htmlspecialchars($current_alumni['pendidikan_lanjut'] ?? '') ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Status Keluar <span class="text-danger">*</span></label>
                                <select name="status_keluar" class="form-select" required>
                                    <option value="Lulus" <?= ($current_alumni['status_keluar'] ?? '') == 'Lulus' ? 'selected' : '' ?>>Lulus</option>
                                    <option value="Berhenti" <?= ($current_alumni['status_keluar'] ?? '') == 'Berhenti' ? 'selected' : '' ?>>Berhenti</option>
                                    <option value="Dikeluarkan" <?= ($current_alumni['status_keluar'] ?? '') == 'Dikeluarkan' ? 'selected' : '' ?>>Dikeluarkan</option>
                                </select>
                            </div>
                            <div class="col-12 mb-3">
                                <label class="form-label">Keterangan</label>
                                <textarea name="keterangan" class="form-control"><?= htmlspecialchars($current_alumni['keterangan'] ?? '') ?></textarea>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Foto</label>
                                <input type="file" class="form-control" name="foto" id="fotoInput">
                                <div class="mt-2">
                                    <?php if ($current_alumni && !empty($current_alumni['foto'])): ?>
                                        <img src="../uploads/<?= htmlspecialchars($current_alumni['foto']) ?>" 
                                             id="fotoPreview" 
                                             class="img-thumbnail" 
                                             width="150" 
                                             loading="lazy"
                                             alt="Foto saat ini">
                                    <?php else: ?>
                                        <img src="../assets/img/default-avatar.png" 
                                             id="fotoPreview" 
                                             class="img-thumbnail" 
                                             width="150" 
                                             loading="lazy"
                                             alt="Foto default">
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="<?= $current_alumni ? 'edit_alumni' : 'tambah_alumni' ?>" class="btn btn-primary" id="submitBtn">
                            Simpan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Modal Foto Besar -->
    <div class="modal fade" id="photoModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-body text-center p-0">
                    <img id="modalPhoto" src="" class="img-fluid" alt="Foto Alumni">
                </div>
                <div class="modal-footer justify-content-center">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.5/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            // Inisialisasi DataTable
            $('#alumniTable').DataTable();
            
            // Tampilkan modal edit jika ada parameter edit
            <?php if (isset($_GET['edit_alumni'])): ?>
                $('#alumniModal').modal('show');
            <?php endif; ?>
            
            // Event listener untuk tombol Tambah Alumni
            $('#tambahAlumniBtn').on('click', function() {
                resetModalToAddMode();
            });
            
            // Event listener untuk ketika modal ditutup
            $('#alumniModal').on('hidden.bs.modal', function() {
                // Hapus parameter edit dari URL tanpa reload
                if (window.history.replaceState && typeof URL !== 'undefined') {
                    const url = new URL(window.location);
                    url.searchParams.delete('edit_alumni');
                    window.history.replaceState({}, '', url);
                }
                
                // Reset modal ke mode tambah
                resetModalToAddMode();
            });
            
            // Preview foto saat input file diubah
            $('#fotoInput').on('change', function(e) {
                if (this.files && this.files[0]) {
                    const reader = new FileReader();
                    
                    reader.onload = function(e) {
                        $('#fotoPreview').attr('src', e.target.result);
                    }
                    
                    reader.readAsDataURL(this.files[0]);
                }
            });
        });
        
        // Fungsi untuk mereset modal ke mode tambah
        function resetModalToAddMode() {
            // Reset form
            $('#alumniForm')[0].reset();
            
            // Reset preview foto ke default
            $('#fotoPreview').attr('src', '../assets/img/default-avatar.png');
            
            // Ubah judul modal
            $('#modalTitle').text('Tambah Alumni');
            
            // Ubah nama tombol submit
            $('#submitBtn').attr('name', 'tambah_alumni');
            $('#submitBtn').text('Simpan');
            
            // Hapus field alumni_id jika ada
            $('input[name="alumni_id"]').remove();
        }
        
        // Fungsi untuk menampilkan foto dalam modal besar
        function showPhoto(photoUrl) {
            $('#modalPhoto').attr('src', photoUrl);
            $('#photoModal').modal('show');
        }
        
        // Fungsi untuk menyembunyikan loading overlay
        function hideLoading() {
            $('#loading-overlay').fadeOut(300, function() {
                $(this).remove();
            });
        }
    
        // Tunggu sampai DOM siap dan semua asset termasuk gambar selesai dimuat
        $(window).on('load', function() {
            // Beri sedikit delay agar animasi terlihat (opsional)
            setTimeout(hideLoading, 500);
        });
    
        // Fallback: Sembunyikan loading setelah 5 detik maksimal
        setTimeout(hideLoading, 5000);
    </script>
</body>
</html>