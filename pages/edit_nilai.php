<?php
include '../auth/cek_guru.php';
include '../koneksi.php';
include '../includes/nilai_validation.php';
require_once '../includes/audit_log.php';

$active = 'nilai';

$siswa_id = filter_var(
    $_GET['siswa_id'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

if($siswa_id === false || $siswa_id === null){

    header("Location: input_nilai.php");
    exit;
}

$siswa_stmt = mysqli_prepare($conn,"
    SELECT * FROM siswa WHERE id = ? LIMIT 1
");
mysqli_stmt_bind_param($siswa_stmt, 'i', $siswa_id);
mysqli_stmt_execute($siswa_stmt);
$siswa = mysqli_fetch_assoc(mysqli_stmt_get_result($siswa_stmt));
mysqli_stmt_close($siswa_stmt);

if(!$siswa){

    header("Location: input_nilai.php");
    exit;
}

$kelas_back = (string) $siswa['kelas'];
$jurusan = (string) $siswa['jurusan'];

$mapel_stmt = mysqli_prepare($conn,"
    SELECT * FROM mata_pelajaran
    WHERE jurusan = ? OR jurusan = 'Umum'
    ORDER BY nama_mapel ASC
");
mysqli_stmt_bind_param($mapel_stmt, 's', $jurusan);
mysqli_stmt_execute($mapel_stmt);
$mapel = mysqli_stmt_get_result($mapel_stmt);

$mapel_rows = [];
$mapel_by_id = [];
$agama_mapel_ids = [];

while($m = mysqli_fetch_assoc($mapel)){
    $mapel_rows[] = $m;
    $mapel_id = (int) $m['id'];
    $mapel_by_id[$mapel_id] = $m;

    $nama_lower = function_exists('mb_strtolower')
        ? mb_strtolower($m['nama_mapel'], 'UTF-8')
        : strtolower($m['nama_mapel']);

    if(strpos($nama_lower, 'agama') !== false){
        $agama_mapel_ids[] = $mapel_id;
    }
}
mysqli_stmt_close($mapel_stmt);

$form_error = '';
$nilai_input = [];
$nilai_data = [];
$nilai_tersimpan_ids = [];

$nilai_stmt = mysqli_prepare($conn,"
    SELECT mapel_id, nilai
    FROM nilai_detail
    WHERE siswa_id = ?
");
mysqli_stmt_bind_param($nilai_stmt, "i", $siswa_id);
mysqli_stmt_execute($nilai_stmt);
$nilai_result = mysqli_stmt_get_result($nilai_stmt);

while($n = mysqli_fetch_assoc($nilai_result)){
    $nilai_data[(int) $n['mapel_id']] = $n['nilai'];
    $nilai_tersimpan_ids[(int) $n['mapel_id']] = true;
}
mysqli_stmt_close($nilai_stmt);

if(isset($_POST['simpan'])){

    $nilai_input =
    $_POST['nilai'] ?? [];

    $hapus_ids = [];

    if(!is_array($nilai_input)){
        $form_error = 'Format data nilai tidak valid.';
        $nilai_input = [];
    }elseif(count($nilai_input) > count($mapel_by_id)){
        $form_error = 'Jumlah data nilai yang dikirim tidak valid.';
    }

    foreach($nilai_input as $mapel_id => $nilai){

        if($form_error !== ''){
            break;
        }

        $mapel_id = (int) $mapel_id;
        if(!is_scalar($nilai) && $nilai !== null){
            $form_error = 'Format salah satu nilai tidak valid.';
            break;
        }
        $nilai = trim((string) $nilai);

        if(!isset($mapel_by_id[$mapel_id])){
            $form_error = 'Mata pelajaran yang dikirim tidak valid.';
            break;
        }

        if(!nilai_akademik_valid($nilai)){

            $form_error = 'Nilai hanya boleh berupa angka 0 sampai 100, dengan maksimal dua angka desimal.';
            break;
        }

        if($nilai === '' && isset($nilai_tersimpan_ids[$mapel_id])){
            $hapus_ids[$mapel_id] = true;
        }
    }

    $agama_final = [];
    if($form_error === ''){
        foreach($agama_mapel_ids as $agama_id){
            if(isset($nilai_tersimpan_ids[$agama_id]) &&
               !isset($hapus_ids[$agama_id])){
                $agama_final[$agama_id] = true;
            }

            $nilai_baru = trim((string) ($nilai_input[$agama_id] ?? ''));
            if($nilai_baru !== '' && !isset($hapus_ids[$agama_id])){
                $agama_final[$agama_id] = true;
            }
        }
    }

    if($form_error === '' && count($agama_final) > 1){

        $form_error =
        'Satu siswa hanya boleh memiliki satu nilai mata pelajaran agama. '.
        'Kosongkan nilai agama lama sebelum memilih agama lain.';
    }

    if($form_error === ''){

        mysqli_begin_transaction($conn);

        try{
            $delete_stmt = mysqli_prepare($conn,"
                DELETE FROM nilai_detail
                WHERE siswa_id = ? AND mapel_id = ?
            ");

            $upsert_stmt = mysqli_prepare($conn,"
                INSERT INTO nilai_detail (siswa_id, mapel_id, nilai)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE nilai = VALUES(nilai)
            ");

            foreach(array_keys($hapus_ids) as $mapel_id_hapus){
                mysqli_stmt_bind_param(
                    $delete_stmt,
                    "ii",
                    $siswa_id,
                    $mapel_id_hapus
                );
                mysqli_stmt_execute($delete_stmt);
            }

            foreach($nilai_input as $mapel_id => $nilai){

                $mapel_id = (int) $mapel_id;
                $nilai = trim((string) $nilai);

                if($nilai === '' || isset($hapus_ids[$mapel_id])){
                    continue;
                }

                $nilai_angka = (float) $nilai;

                mysqli_stmt_bind_param(
                    $upsert_stmt,
                    "iid",
                    $siswa_id,
                    $mapel_id,
                    $nilai_angka
                );
                mysqli_stmt_execute($upsert_stmt);
            }

            mysqli_stmt_close($delete_stmt);
            mysqli_stmt_close($upsert_stmt);
            mysqli_commit($conn);

            catat_perubahan_nilai(
                $conn,
                (int) $siswa_id,
                $siswa,
                $mapel_by_id,
                $nilai_data,
                $nilai_input
            );

            $redirect_params = [
                'toast' => 'Nilai berhasil diperbarui'
            ];

            if($kelas_back !== ''){
                $redirect_params['kelas'] = $kelas_back;
            }

            $redirect_query = http_build_query($redirect_params);

            header("Location: input_nilai.php?".$redirect_query);
            exit;

        }catch(mysqli_sql_exception $exception){
            mysqli_rollback($conn);
            $form_error = 'Perubahan nilai gagal disimpan. Silakan coba kembali.';
        }
    }
}

if($form_error !== ''){
    foreach($nilai_input as $mapel_id => $nilai){
        if(isset($mapel_by_id[(int) $mapel_id])){
            $nilai_data[(int) $mapel_id] = trim((string) $nilai);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>

<meta charset="UTF-8">
<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Edit Nilai</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/tambah_nilai.css?v=2">

</head>
<body>

<?php include '../includes/loading_screen.php'; ?>

<div class="wrapper">

<div class="form-card">

<div class="icon-box">
    <i class="bi bi-pencil-square"></i>
</div>

<h2 class="page-title">
    Edit Nilai
</h2>

<div class="page-subtitle">
    Perbarui nilai akademik siswa untuk semua mata pelajaran.
</div>

<div class="alert alert-primary">
    <strong><?= htmlspecialchars($siswa['nama_siswa'], ENT_QUOTES, 'UTF-8'); ?></strong>
    <br>
    <?= htmlspecialchars($siswa['kelas'], ENT_QUOTES, 'UTF-8'); ?> -
    <?= htmlspecialchars($siswa['jurusan'], ENT_QUOTES, 'UTF-8'); ?>
</div>

<form method="POST">

<?= csrf_field(); ?>

<?php if($form_error !== ''){ ?>

<div class="alert alert-warning" role="alert">
    <h6 class="fw-bold mb-1">Nilai tidak dapat disimpan</h6>
    <div><?= htmlspecialchars($form_error); ?></div>
</div>

<?php } ?>

<?php foreach($mapel_rows as $m){ ?>

<?php $mapel_id_form = (int) $m['id']; ?>

<div class="mb-4">

    <label class="form-label">
        <?= htmlspecialchars($m['nama_mapel'], ENT_QUOTES, 'UTF-8'); ?>
    </label>

    <input
        type="number"
        name="nilai[<?= $mapel_id_form; ?>]"
        class="form-control"
        data-nilai-akademik
        inputmode="decimal"
        min="0"
        max="100"
        step="0.01"
        value="<?= htmlspecialchars($nilai_data[$mapel_id_form] ?? ''); ?>"
        placeholder="Kosongkan untuk menghapus nilai">

</div>

<?php } ?>

<div class="d-grid gap-3">

    <button
        type="submit"
        name="simpan"
        class="btn-save">

        <i class="bi bi-save"></i>

        Simpan Perubahan

    </button>

    <a
        href="input_nilai.php<?= $kelas_back !== ''
            ? '?'.http_build_query(['kelas' => $kelas_back])
            : ''; ?>"
        class="btn btn-back">

        <i class="bi bi-arrow-left"></i>

        Kembali

    </a>

</div>

</form>

<div class="info-box">

    <h6>Informasi Edit</h6>

    <ul>
        <li>Nilai dapat diisi dari 0 sampai 100</li>
        <li>Kosongkan kolom untuk menghapus nilai dari database</li>
        <li>Perubahan nilai akan digunakan pada proses clustering berikutnya</li>
    </ul>

</div>

</div>

</div>

<script src="../assets/nilai_validation.js?v=3"></script>

</body>
</html>
