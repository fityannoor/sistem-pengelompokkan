<?php

include '../auth/cek_admin.php';
include '../koneksi.php';
require_once '../includes/audit_log.php';

$active = 'backup';
$dashboard_link = '../dashboard.php';
$data_siswa_link = 'data_siswa.php';
$mapel_link = 'mata_pelajaran.php';
$nilai_link = 'input_nilai.php';
$cluster_link = 'clustering.php';
$hasil_link = 'hasil_cluster.php';
$user_link = 'users.php';
$audit_link = 'aktivitas_log.php';
$backup_link = 'backup_database.php';
$logout_link = '../auth/logout.php';
$logo_path = '../assets/img/logo_smk_4.png';

$error = '';

if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['backup'])){
    $mysqldump = realpath(__DIR__.'/../../../mysql/bin/mysqldump.exe');

    if($mysqldump === false){
        $error = 'Program backup database tidak ditemukan pada instalasi XAMPP.';
    }else{
        $config = @parse_ini_file(
            __DIR__.'/../config/database.ini',
            true,
            INI_SCANNER_RAW
        );
        $backup_config = is_array($config)
            ? ($config['backup'] ?? null)
            : null;

        if(!is_array($backup_config)){
            $error = 'Konfigurasi akun backup database tidak tersedia.';
        }
    }

    if($error === ''){
        $nama_file = 'skripsi_kmeans_'.date('Y-m-d_H-i-s').'.sql';
        $file_temp = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR).
            DIRECTORY_SEPARATOR.'skripsi_kmeans_'.bin2hex(random_bytes(8)).'.sql';
        $defaults_file = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR).
            DIRECTORY_SEPARATOR.'skripsi_backup_'.bin2hex(random_bytes(8)).'.cnf';

        $defaults_content = "[client]\n".
            'host='.(string) ($backup_config['host'] ?? 'localhost')."\n".
            'user='.(string) ($backup_config['username'] ?? '')."\n".
            'password='.(string) ($backup_config['password'] ?? '')."\n".
            "default-character-set=utf8mb4\n";
        file_put_contents($defaults_file, $defaults_content, LOCK_EX);

        $command = escapeshellarg($mysqldump).
            ' --defaults-extra-file='.escapeshellarg($defaults_file).
            ' --single-transaction'.
            ' --triggers'.
            ' '.escapeshellarg((string) ($backup_config['database'] ?? 'skripsi_kmeans')).
            ' --result-file='.escapeshellarg($file_temp);

        $output = [];
        $return_code = 0;
        exec($command.' 2>&1', $output, $return_code);
        if(is_file($defaults_file)){
            unlink($defaults_file);
        }

        if($return_code !== 0 || !is_file($file_temp) || filesize($file_temp) === 0){
            if(is_file($file_temp)){
                unlink($file_temp);
            }
            error_log('Backup database gagal: '.implode(PHP_EOL, $output));
            $error = 'Backup database gagal dibuat. Periksa layanan MySQL dan coba kembali.';
        }else{
            catat_aktivitas(
                $conn,
                'backup',
                'database',
                null,
                'Membuat backup database '.$nama_file
            );

            header('Content-Type: application/sql; charset=utf-8');
            header('Content-Disposition: attachment; filename="'.$nama_file.'"');
            header('Content-Length: '.filesize($file_temp));
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');

            readfile($file_temp);
            unlink($file_temp);
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Backup Database</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/user.css">
</head>
<body>
<?php include '../includes/sidebar.php'; ?>

<div class="content">
    <div class="topbar">
        <div>
            <h3 class="mb-0">Backup Database</h3>
            <small class="text-muted">Unduh salinan seluruh data sistem</small>
        </div>
    </div>

    <div class="card-box">
        <?php if($error !== ''){ ?>
        <div class="alert alert-danger" role="alert">
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
        </div>
        <?php } ?>

        <div class="text-center py-4">
            <i class="bi bi-database-down text-primary" style="font-size:64px;"></i>
            <h4 class="mt-3">Unduh Backup Database</h4>
            <p class="text-muted mx-auto" style="max-width:650px;">
                File SQL berisi struktur tabel, akun pengguna, siswa, mata pelajaran,
                nilai, hasil clustering, dan log aktivitas.
            </p>
        </div>

        <div class="alert alert-warning">
            <strong>Perhatian:</strong> simpan file backup di lokasi aman dan jangan
            membagikannya karena berisi data akademik serta akun pengguna.
        </div>

        <form method="POST" class="d-grid gap-2" data-skip-loader="true">
            <?= csrf_field(); ?>
            <button type="submit" name="backup" class="btn btn-primary btn-lg"
                    onclick="return confirm('Buat dan unduh backup database sekarang?')">
                <i class="bi bi-download me-1"></i>
                Buat dan Unduh Backup
            </button>
            <a href="../dashboard.php" class="btn btn-secondary">Kembali</a>
        </form>

        <div class="mt-4 text-muted small">
            <ul class="mb-0">
                <li>Buat backup sebelum import besar, migrasi, atau penghapusan data.</li>
                <li>Simpan salinan pada flashdisk atau perangkat lain.</li>
                <li>Pemulihan tetap dilakukan melalui phpMyAdmin oleh administrator.</li>
            </ul>
        </div>
    </div>
</div>
</body>
</html>
