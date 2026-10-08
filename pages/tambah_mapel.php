<?php
include '../auth/cek_admin.php';
include '../koneksi.php';
require_once '../includes/audit_log.php';

$error = '';
$nama = '';
$jurusan = '';
$mapel_duplikat = null;
$jurusan_asal = trim($_GET['jurusan_asal'] ?? '');

$normalisasi_nama_mapel = static function(string $value): string {
    $value = preg_replace('/\s+/u', ' ', trim($value));
    return function_exists('mb_strtolower')
        ? mb_strtolower($value, 'UTF-8')
        : strtolower($value);
};


$jurusan_data = mysqli_query($conn,"
    SELECT DISTINCT jurusan
    FROM siswa

    WHERE jurusan IS NOT NULL
    AND jurusan != ''
    AND jurusan != 'Umum'

    ORDER BY jurusan ASC
");

if(isset($_POST['simpan'])){

    $nama = preg_replace(
        '/\s+/u',
        ' ',
        trim($_POST['nama_mapel'] ?? '')
    );

    $jurusan = trim($_POST['jurusan'] ?? '');

    if($nama === '' || $jurusan === ''){

        $error = 'Nama mata pelajaran dan cakupan wajib dipilih.';
    }

    if($error === '' && $jurusan !== 'Umum'){

        $jurusan_stmt = mysqli_prepare($conn,"
            SELECT id FROM siswa WHERE jurusan = ? LIMIT 1
        ");

        mysqli_stmt_bind_param($jurusan_stmt, "s", $jurusan);
        mysqli_stmt_execute($jurusan_stmt);
        mysqli_stmt_store_result($jurusan_stmt);

        if(mysqli_stmt_num_rows($jurusan_stmt) === 0){
            $error = 'Program keahlian yang dipilih tidak valid.';
        }

        mysqli_stmt_close($jurusan_stmt);
    }

    if($error === ''){

        $nama_normal = $normalisasi_nama_mapel($nama);
        $cek_mapel = mysqli_query($conn,"
            SELECT id, nama_mapel, jurusan
            FROM mata_pelajaran
            ORDER BY id ASC
        ");

        while($mapel_tersimpan = mysqli_fetch_assoc($cek_mapel)){
            if($normalisasi_nama_mapel($mapel_tersimpan['nama_mapel']) ===
               $nama_normal){
                $mapel_duplikat = $mapel_tersimpan;
                break;
            }
        }

        if($mapel_duplikat){
            $cakupan_tersimpan = $mapel_duplikat['jurusan'] === 'Umum'
                ? 'Semua Jurusan'
                : $mapel_duplikat['jurusan'];
            $error = 'Mata pelajaran "'.
                $mapel_duplikat['nama_mapel'].
                '" sudah tersedia untuk '.$cakupan_tersimpan.'.';
        }
    }

    if($error === ''){

        try{
            $insert_stmt = mysqli_prepare($conn,"
                INSERT INTO mata_pelajaran (nama_mapel, jurusan)
                VALUES (?, ?)
            ");

            mysqli_stmt_bind_param(
                $insert_stmt,
                "ss",
                $nama,
                $jurusan
            );

            mysqli_stmt_execute($insert_stmt);
            $mapel_baru_id = mysqli_insert_id($conn);
            mysqli_stmt_close($insert_stmt);

            catat_aktivitas(
                $conn, 'tambah', 'mata_pelajaran', $mapel_baru_id,
                'Menambahkan '.$nama.' untuk '.$jurusan
            );

            header(
                "Location: mata_pelajaran.php?toast=".
                rawurlencode('Mata pelajaran berhasil ditambahkan')
            );
            exit;

        }catch(mysqli_sql_exception $exception){

            if((int) $exception->getCode() === 1062){
                $error = 'Mata pelajaran tersebut sudah tersedia dan tidak dapat ditambahkan kembali.';
            }else{
                $error = 'Mata pelajaran gagal disimpan. Silakan coba kembali.';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>

    <title>Tambah Mapel</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">

    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/tambah_mapel.css">

</head>
<body>

<?php include '../includes/loading_screen.php'; ?>



<div class="wrapper">

<div class="form-card">

<div class="text-center">

<div class="badge-form">

<i class="bi bi-book"></i>

Academic Subject Form

</div>

</div>

<div class="icon-box">

<i class="bi bi-journal-plus"></i>

</div>

<h2 class="page-title">
Tambah Mata Pelajaran
</h2>

<div class="page-subtitle">

Tambahkan mata pelajaran baru
ke dalam sistem akademik.

</div>

<form method="POST">

<?= csrf_field(); ?>

<?php if($error !== ''){ ?>

<div class="alert alert-warning" role="alert">
    <h6 class="fw-bold mb-1">Mata pelajaran tidak dapat ditambahkan</h6>
    <div><?= htmlspecialchars($error); ?></div>

    <?php if($mapel_duplikat){ ?>
    <div class="d-flex flex-wrap gap-2 mt-3">
        <a href="edit_mapel.php?<?= http_build_query([
            'id' => (int) $mapel_duplikat['id'],
            'jurusan_asal' => $jurusan_asal
        ]); ?>"
           class="btn btn-warning">
            <i class="bi bi-pencil-square me-1"></i>
            Edit Mata Pelajaran
        </a>

        <a href="mata_pelajaran.php<?= $jurusan_asal !== ''
            ? '?'.http_build_query(['jurusan' => $jurusan_asal])
            : ''; ?>"
           class="btn btn-secondary">
            <i class="bi bi-x-circle me-1"></i>
            Batal
        </a>
    </div>
    <?php } ?>
</div>

<?php } ?>

<div class="mb-4">

<label class="form-label">

Nama Mata Pelajaran

</label>

<input type="text"
       name="nama_mapel"
       class="form-control"
       placeholder="Masukkan nama mata pelajaran"
       value="<?= htmlspecialchars($nama); ?>"
       required>

</div>
<div class="mb-4">

<label class="form-label">

Berlaku Untuk

</label>

<select name="jurusan"
        class="form-select"
        required>

<option value="">
-- Pilih Cakupan Mata Pelajaran --
</option>

<option value="Umum"
<?= $jurusan === 'Umum' ? 'selected' : ''; ?>>
Semua Jurusan
</option>

<?php
while($j =
mysqli_fetch_assoc($jurusan_data)){
?>

<option value="<?= htmlspecialchars($j['jurusan']); ?>"
<?= $jurusan === $j['jurusan'] ? 'selected' : ''; ?>>

<?= htmlspecialchars($j['jurusan']); ?>

</option>

<?php } ?>

</select>

<small class="text-muted">
Pilih Semua Jurusan untuk mata pelajaran umum.
</small>

</div>

<div class="d-grid gap-3">

<button type="submit"
        name="simpan"
        class="btn-save">

<i class="bi bi-save"></i>

Simpan Mata Pelajaran

</button>

<a href="mata_pelajaran.php<?= $jurusan_asal !== ''
    ? '?'.http_build_query(['jurusan' => $jurusan_asal])
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
Mata pelajaran akan masuk ke database otomatis
</li>

<li>
Data mapel digunakan untuk input nilai siswa
</li>

<li>
Mata pelajaran dapat digunakan pada proses clustering
</li>

</ul>

</div>

</div>

</div>

</body>
</html>
