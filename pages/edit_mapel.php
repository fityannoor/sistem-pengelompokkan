<?php

include '../auth/cek_admin.php';
include '../koneksi.php';
require_once '../includes/audit_log.php';

$id = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT,
    ["options" => ["min_range" => 1]]
);

$jurusan_asal = trim($_GET['jurusan_asal'] ?? '');

if($id === false || $id === null){
    header("Location: mata_pelajaran.php");
    exit;
}

$mapel_stmt = mysqli_prepare($conn,"
    SELECT id, nama_mapel, jurusan
    FROM mata_pelajaran
    WHERE id = ?
    LIMIT 1
");

mysqli_stmt_bind_param($mapel_stmt, "i", $id);
mysqli_stmt_execute($mapel_stmt);

$mapel_result = mysqli_stmt_get_result($mapel_stmt);
$mapel_lama = mysqli_fetch_assoc($mapel_result);

mysqli_stmt_close($mapel_stmt);

if(!$mapel_lama){
    header("Location: mata_pelajaran.php");
    exit;
}

$jurusan_options = [];
$jurusan_query = mysqli_query($conn,"
    SELECT DISTINCT jurusan
    FROM siswa
    WHERE jurusan IS NOT NULL
      AND jurusan != ''
      AND jurusan != 'Umum'
    ORDER BY jurusan ASC
");

while($row = mysqli_fetch_assoc($jurusan_query)){
    $jurusan_options[] = $row['jurusan'];
}

if($mapel_lama['jurusan'] !== 'Umum' &&
   !in_array($mapel_lama['jurusan'], $jurusan_options, true)){
    $jurusan_options[] = $mapel_lama['jurusan'];
    sort($jurusan_options);
}

$nama = $mapel_lama['nama_mapel'];
$jurusan = $mapel_lama['jurusan'];
$error = '';
$warning_digunakan = false;
$jumlah_nilai = 0;

if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $nama = preg_replace(
        '/\s+/u',
        ' ',
        trim($_POST['nama_mapel'] ?? '')
    );
    $jurusan = trim($_POST['jurusan'] ?? '');
    $buat_baru = isset($_POST['buat_baru']);

    if($nama === '' || $jurusan === ''){
        $error = 'Nama mata pelajaran dan cakupan wajib diisi.';
    }elseif($jurusan !== 'Umum' &&
           !in_array($jurusan, $jurusan_options, true)){
        $error = 'Program keahlian tujuan tidak valid.';
    }

    $jumlah_stmt = mysqli_prepare($conn,"
        SELECT COUNT(*) AS total
        FROM nilai_detail
        WHERE mapel_id = ?
    ");
    mysqli_stmt_bind_param($jumlah_stmt, "i", $id);
    mysqli_stmt_execute($jumlah_stmt);
    $jumlah_result = mysqli_stmt_get_result($jumlah_stmt);
    $jumlah_nilai = (int) mysqli_fetch_assoc($jumlah_result)['total'];
    mysqli_stmt_close($jumlah_stmt);

    if($error === ''){
        $cek_sql = $buat_baru
            ? "SELECT id FROM mata_pelajaran
               WHERE LOWER(TRIM(nama_mapel)) = LOWER(?)
                 AND jurusan = ? LIMIT 1"
            : "SELECT id FROM mata_pelajaran
               WHERE LOWER(TRIM(nama_mapel)) = LOWER(?)
                 AND jurusan = ? AND id != ? LIMIT 1";

        $cek_stmt = mysqli_prepare($conn, $cek_sql);

        if($buat_baru){
            mysqli_stmt_bind_param($cek_stmt, "ss", $nama, $jurusan);
        }else{
            mysqli_stmt_bind_param($cek_stmt, "ssi", $nama, $jurusan, $id);
        }

        mysqli_stmt_execute($cek_stmt);
        mysqli_stmt_store_result($cek_stmt);

        if(mysqli_stmt_num_rows($cek_stmt) > 0){
            $error = 'Mata pelajaran tersebut sudah tersedia untuk cakupan tujuan.';
        }

        mysqli_stmt_close($cek_stmt);
    }

    $cakupan_berubah = $jurusan !== $mapel_lama['jurusan'];

    if($error === '' && $buat_baru){
        if(!$cakupan_berubah || $jumlah_nilai === 0){
            $error = 'Pembuatan mapel baru hanya tersedia saat perubahan cakupan ditolak karena mapel sudah digunakan.';
        }else{
            try{
                $insert_stmt = mysqli_prepare($conn,"
                    INSERT INTO mata_pelajaran (nama_mapel, jurusan)
                    VALUES (?, ?)
                ");
                mysqli_stmt_bind_param($insert_stmt, "ss", $nama, $jurusan);
                mysqli_stmt_execute($insert_stmt);
                $mapel_baru_id = mysqli_insert_id($conn);
                mysqli_stmt_close($insert_stmt);

                catat_aktivitas(
                    $conn, 'tambah', 'mata_pelajaran', $mapel_baru_id,
                    'Membuat mapel baru '.$nama.' untuk '.$jurusan
                );

                header(
                    "Location: mata_pelajaran.php?".
                    http_build_query([
                        'jurusan' => $jurusan,
                        'toast' => 'Mata pelajaran baru berhasil dibuat'
                    ])
                );
                exit;
            }catch(mysqli_sql_exception $exception){
                $error = (int) $exception->getCode() === 1062
                    ? 'Mata pelajaran tersebut sudah tersedia untuk cakupan tujuan.'
                    : 'Mata pelajaran baru gagal dibuat.';
            }
        }
    }

    if($error === '' && !$buat_baru){
        if($cakupan_berubah && $jumlah_nilai > 0){
            $warning_digunakan = true;
        }else{
            try{
                $update_stmt = mysqli_prepare($conn,"
                    UPDATE mata_pelajaran
                    SET nama_mapel = ?, jurusan = ?
                    WHERE id = ?
                ");
                mysqli_stmt_bind_param(
                    $update_stmt,
                    "ssi",
                    $nama,
                    $jurusan,
                    $id
                );
                mysqli_stmt_execute($update_stmt);
                mysqli_stmt_close($update_stmt);

                catat_aktivitas(
                    $conn, 'ubah', 'mata_pelajaran', (int) $id,
                    'Memperbarui mapel menjadi '.$nama.' untuk '.$jurusan
                );

                header(
                    "Location: mata_pelajaran.php?".
                    http_build_query([
                        'jurusan' => $jurusan,
                        'toast' => 'Mata pelajaran berhasil diperbarui'
                    ])
                );
                exit;
            }catch(mysqli_sql_exception $exception){
                $error = (int) $exception->getCode() === 1062
                    ? 'Mata pelajaran tersebut sudah tersedia untuk cakupan tujuan.'
                    : 'Mata pelajaran gagal diperbarui.';
            }
        }
    }
}

$url_kembali = 'mata_pelajaran.php'.(
    $jurusan_asal !== ''
        ? '?'.http_build_query(['jurusan' => $jurusan_asal])
        : ''
);

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Mata Pelajaran</title>
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
            <i class="bi bi-pencil-square"></i>
            Academic Subject Form
        </div>
    </div>

    <div class="icon-box">
        <i class="bi bi-journal-check"></i>
    </div>

    <h2 class="page-title">Edit Mata Pelajaran</h2>
    <div class="page-subtitle">
        Perbarui nama atau cakupan mata pelajaran.
    </div>

    <form method="POST">
        <?= csrf_field(); ?>
        <?php if($error !== ''){ ?>
        <div class="alert alert-danger" role="alert">
            <?= htmlspecialchars($error); ?>
        </div>
        <?php } ?>

        <?php if($warning_digunakan){ ?>
        <div class="alert alert-warning" role="alert">
            <h6 class="fw-bold">Perubahan cakupan dihentikan</h6>
            <p>
                Mata pelajaran ini sudah digunakan pada
                <?= $jumlah_nilai; ?> data nilai siswa. Mengubah bagian
                “Berlaku Untuk” dapat menyebabkan data nilai menjadi tidak sesuai.
            </p>
            <div class="d-flex flex-wrap gap-2">
                <a href="<?= htmlspecialchars($url_kembali); ?>"
                   class="btn btn-secondary">
                    Batal
                </a>
                <button type="submit"
                        name="buat_baru"
                        class="btn btn-warning">
                    Buat Mata Pelajaran Baru
                </button>
            </div>
        </div>
        <?php } ?>

        <div class="mb-4">
            <label class="form-label">Nama Mata Pelajaran</label>
            <input type="text"
                   name="nama_mapel"
                   class="form-control"
                   value="<?= htmlspecialchars($nama); ?>"
                   required>
        </div>

        <div class="mb-4">
            <label class="form-label">Berlaku Untuk</label>
            <select name="jurusan" class="form-select" required>
                <option value="">-- Pilih Cakupan Mata Pelajaran --</option>
                <option value="Umum" <?= $jurusan === 'Umum' ? 'selected' : ''; ?>>
                    Semua Jurusan
                </option>
                <?php foreach($jurusan_options as $option){ ?>
                <option value="<?= htmlspecialchars($option); ?>"
                    <?= $jurusan === $option ? 'selected' : ''; ?>>
                    <?= htmlspecialchars($option); ?>
                </option>
                <?php } ?>
            </select>
        </div>

        <?php if(!$warning_digunakan){ ?>
        <div class="d-grid gap-3">
            <button type="submit" name="simpan" class="btn-save">
                <i class="bi bi-save"></i>
                Simpan Perubahan
            </button>
            <a href="<?= htmlspecialchars($url_kembali); ?>"
               class="btn btn-back">
                <i class="bi bi-arrow-left"></i>
                Kembali
            </a>
        </div>
        <?php } ?>
    </form>
</div>
</div>
</body>
</html>
