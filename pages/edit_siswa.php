<?php
include '../auth/cek_admin.php';
include '../koneksi.php';
require_once '../includes/audit_log.php';

if(!isset($_SESSION['edit_siswa_id'])){

    header("Location: data_siswa.php");
    exit;
}

$kelas_filter_kembali =
trim($_SESSION['edit_siswa_kelas_kembali'] ?? '');

$id = filter_var(
    $_SESSION['edit_siswa_id'],
    FILTER_VALIDATE_INT,
    ["options" => ["min_range" => 1]]
);

if($id === false){

    unset($_SESSION['edit_siswa_id']);
    unset($_SESSION['edit_siswa_kelas_kembali']);
    header("Location: data_siswa.php");
    exit;
}

if($_SERVER['REQUEST_METHOD'] === 'GET' &&
   !empty($_SERVER['QUERY_STRING'])){

    header("Location: edit_siswa.php");
    exit;
}

$siswa_stmt = mysqli_prepare(
    $conn,
    "SELECT * FROM siswa WHERE id = ?"
);

mysqli_stmt_bind_param($siswa_stmt, "i", $id);
mysqli_stmt_execute($siswa_stmt);

$siswa_result = mysqli_stmt_get_result($siswa_stmt);
$siswa = mysqli_fetch_assoc($siswa_result);

mysqli_stmt_close($siswa_stmt);

if(!$siswa){

    unset($_SESSION['edit_siswa_id']);
    unset($_SESSION['edit_siswa_kelas_kembali']);
    header("Location: data_siswa.php");
    exit;
}

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

$error = '';

if(isset($_POST['simpan'])){

    $kelas_lama = (string) $siswa['kelas'];
    $jurusan_lama = (string) $siswa['jurusan'];

    $nis = trim($_POST['nis'] ?? '');

    $nama = trim($_POST['nama_siswa'] ?? '');

    $kelas = trim($_POST['kelas'] ?? '');

    $siswa['nis'] = $nis;
    $siswa['nama_siswa'] = $nama;
    $siswa['kelas'] = $kelas;

    if(!preg_match('/^[0-9]{10}$/', $nis)){
        $error = 'NIS wajib terdiri dari tepat 10 angka.';
    }elseif($nama === ''){
        $error = 'Nama siswa wajib diisi.';
    }elseif(strlen($nama) > 100){
        $error = 'Nama siswa maksimal terdiri dari 100 karakter.';
    }elseif(!in_array($kelas, $kelas_valid, true)){
        $error = 'Kelas yang dipilih tidak valid.';
    }

    if($error === ''){
        $cek_nis_stmt = mysqli_prepare($conn,"
            SELECT id FROM siswa
            WHERE nis = ? AND id != ?
            LIMIT 1
        ");
        mysqli_stmt_bind_param($cek_nis_stmt, 'si', $nis, $id);
        mysqli_stmt_execute($cek_nis_stmt);
        mysqli_stmt_store_result($cek_nis_stmt);

        if(mysqli_stmt_num_rows($cek_nis_stmt) > 0){
            $error = 'NIS sudah digunakan oleh siswa lain.';
        }

        mysqli_stmt_close($cek_nis_stmt);
    }

    $jurusan = '';
    if($error === ''){
        $jurusan_stmt = mysqli_prepare($conn,"
            SELECT jurusan, COUNT(*) AS total
            FROM siswa
            WHERE kelas = ? AND id != ?
              AND jurusan IS NOT NULL AND jurusan != ''
            GROUP BY jurusan
            ORDER BY total DESC
            LIMIT 1
        ");
        mysqli_stmt_bind_param($jurusan_stmt, 'si', $kelas, $id);
        mysqli_stmt_execute($jurusan_stmt);
        $jurusan_data = mysqli_fetch_assoc(mysqli_stmt_get_result($jurusan_stmt));
        mysqli_stmt_close($jurusan_stmt);

        if($jurusan_data){
            $jurusan = (string) $jurusan_data['jurusan'];
        }elseif($kelas === $kelas_lama){
            $jurusan = $jurusan_lama;
        }else{
            $error = 'Jurusan untuk kelas yang dipilih tidak ditemukan.';
        }
    }

    if($error === ''){
        $update_stmt = mysqli_prepare($conn,"
        UPDATE siswa
        SET
            nis = ?,
            nama_siswa = ?,
            kelas = ?,
            jurusan = ?
        WHERE id = ?
    ");

        mysqli_stmt_bind_param(
            $update_stmt,
            "ssssi",
            $nis,
            $nama,
            $kelas,
            $jurusan,
            $id
        );

        mysqli_stmt_execute($update_stmt);
        mysqli_stmt_close($update_stmt);

        catat_aktivitas(
            $conn, 'ubah', 'siswa', (int) $id,
            'Memperbarui siswa NIS '.$nis.' pada kelas '.$kelas
        );

        unset($_SESSION['edit_siswa_id']);
        unset($_SESSION['edit_siswa_kelas_kembali']);

        $redirect_query = http_build_query([
            'kelas' => $kelas,
            'toast' => 'Data siswa berhasil diperbarui'
        ]);

        header("Location: data_siswa.php?".$redirect_query);
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>

    <meta charset="UTF-8">
    <meta name="viewport"
    content="width=device-width, initial-scale=1.0">

    <title>Edit Siswa</title>

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

<i class="bi bi-pencil-square"></i>

Student Academic Form

</div>

</div>

<div class="icon-box">

<i class="bi bi-person-gear"></i>

</div>

<h2 class="page-title">
Edit Data Siswa
</h2>

<div class="page-subtitle">

Perbarui data siswa yang sudah tersimpan
di dalam sistem akademik sekolah.

</div>

<form method="POST">

<?= csrf_field(); ?>

<?php if($error !== ''){ ?>
<div class="alert alert-danger" role="alert">
    <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
</div>
<?php } ?>

<div class="mb-4">

<label class="form-label">

Nomor Induk Siswa (NIS)

</label>

<input type="text"
       name="nis"
       class="form-control"
       placeholder="Masukkan 10 angka NIS siswa"
       value="<?= htmlspecialchars($siswa['nis'], ENT_QUOTES, 'UTF-8'); ?>"
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
       placeholder="Masukkan nama siswa"
       value="<?= htmlspecialchars($siswa['nama_siswa']); ?>"
       required>

</div>

<div class="mb-4">

<label class="form-label">

Kelas

</label>

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
<?= $siswa['kelas'] == $k['kelas'] ? 'selected' : ''; ?>>

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

Simpan Perubahan

</button>

<a href="data_siswa.php<?= $kelas_filter_kembali !== ''
    ? '?'.http_build_query(['kelas' => $kelas_filter_kembali])
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
Perubahan NIS, nama, dan kelas akan langsung tersimpan
</li>

<li>
Jurusan akan disesuaikan otomatis berdasarkan kelas
</li>

<li>
Data siswa tetap digunakan pada input nilai dan proses clustering
</li>

</ul>

</div>

</div>

</div>

</body>
</html>
