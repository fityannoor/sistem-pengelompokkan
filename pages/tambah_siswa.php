<?php
include '../auth/cek_admin.php';
include '../koneksi.php';
require_once '../includes/audit_log.php';
$kelas_asal = trim($_GET['kelas_asal'] ?? '');
?>
<?php
include '../koneksi.php';

$kelas_data = mysqli_query($conn,"
    SELECT DISTINCT kelas
    FROM siswa

    WHERE kelas IS NOT NULL
    AND kelas != ''

    ORDER BY kelas ASC
");
$kelas_valid = [];
while($kelas_item = mysqli_fetch_assoc($kelas_data)){
    $kelas_valid[] = (string) $kelas_item['kelas'];
}
mysqli_data_seek($kelas_data, 0);

$nis_duplikat = false;
$pesan_error = '';

if(isset($_POST['simpan'])){

    $nis = trim($_POST['nis'] ?? '');
    $nama = trim($_POST['nama_siswa'] ?? '');
    $kelas = trim($_POST['kelas'] ?? '');

    if(!preg_match('/^[0-9]{10}$/', $nis)){
        $pesan_error = 'NIS wajib terdiri dari tepat 10 angka.';
    }elseif($nama === ''){
        $pesan_error = 'Nama siswa wajib diisi.';
    }elseif(strlen($nama) > 100){
        $pesan_error = 'Nama siswa maksimal terdiri dari 100 karakter.';
    }elseif(!in_array($kelas, $kelas_valid, true)){
        $pesan_error = 'Kelas yang dipilih tidak valid.';
    }

    $siswa_terdaftar = null;
    if($pesan_error === ''){
        $cek_nis_stmt = mysqli_prepare(
            $conn,
            "SELECT id, kelas FROM siswa WHERE nis = ? LIMIT 1"
        );
        mysqli_stmt_bind_param($cek_nis_stmt, 's', $nis);
        mysqli_stmt_execute($cek_nis_stmt);
        $cek_nis_result = mysqli_stmt_get_result($cek_nis_stmt);
        $siswa_terdaftar = mysqli_fetch_assoc($cek_nis_result);
        mysqli_stmt_close($cek_nis_stmt);
    }

    if($siswa_terdaftar){
        $nis_duplikat = true;
        $pesan_error = 'NIS '.$nis.' sudah digunakan oleh siswa yang terdaftar.';
        $_SESSION['edit_siswa_id'] = (int) $siswa_terdaftar['id'];
        $_SESSION['edit_siswa_kelas_kembali'] =
            trim((string) $siswa_terdaftar['kelas']);
    }

    $jurusan = '';
    if($pesan_error === ''){
        $jurusan_stmt = mysqli_prepare($conn,"
            SELECT jurusan, COUNT(*) AS total
            FROM siswa
            WHERE kelas = ? AND jurusan IS NOT NULL AND jurusan != ''
            GROUP BY jurusan
            ORDER BY total DESC
            LIMIT 1
        ");
        mysqli_stmt_bind_param($jurusan_stmt, 's', $kelas);
        mysqli_stmt_execute($jurusan_stmt);
        $jurusan_data = mysqli_fetch_assoc(mysqli_stmt_get_result($jurusan_stmt));
        mysqli_stmt_close($jurusan_stmt);

        if(!$jurusan_data){
            $pesan_error = 'Jurusan untuk kelas yang dipilih tidak ditemukan.';
        }else{
            $jurusan = (string) $jurusan_data['jurusan'];
        }
    }

    if(!$nis_duplikat && $pesan_error === ''){
        try{
            $insert_stmt = mysqli_prepare(
                $conn,
                "INSERT INTO siswa (nis, nama_siswa, kelas, jurusan)
                 VALUES (?, ?, ?, ?)"
            );
            mysqli_stmt_bind_param(
                $insert_stmt,
                'ssss',
                $nis,
                $nama,
                $kelas,
                $jurusan
            );
            mysqli_stmt_execute($insert_stmt);
            $siswa_baru_id = mysqli_insert_id($conn);
            mysqli_stmt_close($insert_stmt);
            catat_aktivitas(
                $conn, 'tambah', 'siswa', $siswa_baru_id,
                'Menambahkan siswa NIS '.$nis.' ke kelas '.$kelas
            );
        }catch(mysqli_sql_exception $exception){
            if((int) $exception->getCode() !== 1062){
                throw $exception;
            }

            $nis_duplikat = true;
            $pesan_error = 'NIS '.$nis.' sudah digunakan oleh siswa yang terdaftar.';

            $cek_ulang_stmt = mysqli_prepare(
                $conn,
                "SELECT id, kelas FROM siswa WHERE nis = ? LIMIT 1"
            );
            mysqli_stmt_bind_param($cek_ulang_stmt, 's', $nis);
            mysqli_stmt_execute($cek_ulang_stmt);
            $cek_ulang = mysqli_fetch_assoc(
                mysqli_stmt_get_result($cek_ulang_stmt)
            );
            mysqli_stmt_close($cek_ulang_stmt);

            if($cek_ulang){
                $_SESSION['edit_siswa_id'] = (int) $cek_ulang['id'];
                $_SESSION['edit_siswa_kelas_kembali'] =
                    trim((string) $cek_ulang['kelas']);
            }
        }
    }

    if(!$nis_duplikat && $pesan_error === ''){
        $redirect_query = http_build_query([
            'kelas' => $kelas,
            'toast' => 'Data siswa berhasil ditambahkan'
        ]);

        header("Location: data_siswa.php?".$redirect_query);
        exit;
    }
}
?>

<!DOCTYPE html>
<html>
<head>

    <title>Tambah Siswa</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">

<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/tambah_siswa.css">

</head>
<body>

<?php include '../includes/loading_screen.php'; ?>


<div class="wrapper">

<div class="form-card">

<div class="text-center">

<div class="badge-form">

<i class="bi bi-people"></i>

Student Academic Form

</div>

</div>

<div class="icon-box">

<i class="bi bi-person-plus"></i>

</div>

<h2 class="page-title">
Tambah Data Siswa
</h2>

<div class="page-subtitle">

Tambahkan data siswa baru
ke dalam sistem akademik sekolah.

</div>

<?php if($pesan_error !== '' && !$nis_duplikat){ ?>
<div class="alert alert-danger text-start mt-4" role="alert">
    <?= htmlspecialchars($pesan_error, ENT_QUOTES, 'UTF-8'); ?>
</div>
<?php } ?>

<?php if($nis_duplikat){ ?>
<div class="alert alert-warning text-start mt-4" role="alert">
    <div class="fw-semibold mb-2">
        <i class="bi bi-exclamation-triangle me-1"></i>
        NIS sudah digunakan
    </div>

    <div class="mb-3">
        <?= htmlspecialchars($pesan_error); ?>
        Data baru tidak disimpan.
    </div>

    <div class="d-flex flex-wrap gap-2">
        <?php if(isset($_SESSION['edit_siswa_id'])){ ?>
        <a href="edit_siswa.php" class="btn btn-warning">
            <i class="bi bi-pencil-square me-1"></i>
            Edit Data Siswa
        </a>
        <?php } ?>

        <a href="data_siswa.php<?= !empty($_SESSION['edit_siswa_kelas_kembali'])
            ? '?'.http_build_query([
                'kelas' => $_SESSION['edit_siswa_kelas_kembali']
            ])
            : ''; ?>"
           class="btn btn-secondary">
            <i class="bi bi-arrow-left me-1"></i>
            Kembali ke Data Siswa
        </a>
    </div>
</div>
<?php } ?>

<form method="POST">

<?= csrf_field(); ?>

<div class="mb-4">

<label class="form-label">

Nomor Induk Siswa (NIS)

</label>

<input type="text"
           name="nis"
           class="form-control"
           value="<?= htmlspecialchars($_POST['nis'] ?? ''); ?>"
           placeholder="Masukkan 10 angka NIS siswa"
           inputmode="numeric"
           maxlength="10"
           pattern="[0-9]{10}"
           oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 10)"
           required>

</div>

<div class="mb-4">

<label class="form-label">

Nama Siswa

</label>

<input type="text"
       name="nama_siswa"
       class="form-control"
       value="<?= htmlspecialchars($_POST['nama_siswa'] ?? ''); ?>"
       placeholder="Masukkan nama siswa"
       required>

</div>

<div class="mb-3">

<label>Kelas</label>

<select name="kelas"
        class="form-select"
        required>

<option value="">
-- Pilih Kelas --
</option>

<?php
while($k =
mysqli_fetch_assoc($kelas_data)){
?>

<option value="<?= htmlspecialchars($k['kelas']); ?>"
        <?= ($_POST['kelas'] ?? '') === $k['kelas'] ? 'selected' : ''; ?>>

<?= htmlspecialchars($k['kelas']); ?>

</option>

<?php } ?>

</select>

</div>

<div class="d-grid gap-3">

<button type="submit"
        name="simpan"
        class="btn-save">

<i class="bi bi-save"></i>

Simpan Data Siswa

</button>

<a href="data_siswa.php<?= $kelas_asal !== ''
    ? '?'.http_build_query(['kelas' => $kelas_asal])
    : ''; ?>"
   class="btn btn-back">

<i class="bi bi-arrow-left"></i>

Kembali

</a>

</div>

</form>

<div class="info-box">

<h6>
Informasi Data
</h6>

<ul>

<li>
Data siswa akan tersimpan otomatis ke database
</li>

<li>
Siswa dapat digunakan untuk input nilai akademik
</li>

<li>
Data siswa akan digunakan pada proses clustering K-Means
</li>

</ul>

</div>

</div>

</div>

</body>
</html>
