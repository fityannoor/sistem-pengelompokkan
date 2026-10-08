<?php
include '../auth/cek_guru.php';
?>
<?php
include '../koneksi.php';
include '../includes/nilai_validation.php';
require_once '../includes/audit_log.php';

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


$kelas_selected = trim((string) ($_GET['kelas'] ?? ''));
if(!in_array($kelas_selected, $kelas_valid, true)){
    $kelas_selected = '';
}

if($kelas_selected !== ''){
    $siswa_stmt = mysqli_prepare($conn,"
        SELECT * FROM siswa WHERE kelas = ? ORDER BY nama_siswa ASC
    ");
    mysqli_stmt_bind_param($siswa_stmt, 's', $kelas_selected);
    mysqli_stmt_execute($siswa_stmt);
    $siswa = mysqli_stmt_get_result($siswa_stmt);
    mysqli_stmt_close($siswa_stmt);
}else{
    $siswa = mysqli_query($conn,"
        SELECT * FROM siswa WHERE 1 = 0
    ");
}

$siswa_id_raw = trim((string) ($_GET['siswa_id'] ?? ''));
$siswa_id_valid = filter_var(
    $siswa_id_raw,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);
$siswa_id_selected = $siswa_id_valid === false ? '' : (string) $siswa_id_valid;

$jurusan = '';
$d_siswa = null;

if($siswa_id_selected !== '' && $kelas_selected !== ''){
    $siswa_terpilih_id = (int) $siswa_id_selected;
    $q_siswa_stmt = mysqli_prepare($conn,"
        SELECT * FROM siswa WHERE id = ? AND kelas = ? LIMIT 1
    ");
    mysqli_stmt_bind_param(
        $q_siswa_stmt,
        'is',
        $siswa_terpilih_id,
        $kelas_selected
    );
    mysqli_stmt_execute($q_siswa_stmt);
    $d_siswa = mysqli_fetch_assoc(mysqli_stmt_get_result($q_siswa_stmt));
    mysqli_stmt_close($q_siswa_stmt);

    if($d_siswa){
        $jurusan = (string) $d_siswa['jurusan'];
    }else{
        $siswa_id_selected = '';
    }
}
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
    $mapel_id = (int) $m['id'];
    $mapel_rows[] = $m;
    $mapel_by_id[$mapel_id] = $m;

    $nama_lower = function_exists('mb_strtolower')
        ? mb_strtolower($m['nama_mapel'], 'UTF-8')
        : strtolower($m['nama_mapel']);

    if(strpos($nama_lower, 'agama') !== false){

        $agama_mapel_ids[] = $mapel_id;
    }
}
mysqli_stmt_close($mapel_stmt);

$nilai_data = [];
$nilai_tersimpan_ids = [];
$form_error = '';
$nilai_input = [];
$agama_conflict = false;
$agama_aktif_id = null;
$agama_edit_id = null;

if($siswa_id_selected !== ''){
    $nilai_stmt = mysqli_prepare($conn,"
        SELECT mapel_id, nilai
        FROM nilai_detail
        WHERE siswa_id = ?
    ");

    $siswa_id_nilai = (int) $siswa_id_selected;
    mysqli_stmt_bind_param($nilai_stmt, "i", $siswa_id_nilai);
    mysqli_stmt_execute($nilai_stmt);

    $nilai_result = mysqli_stmt_get_result($nilai_stmt);

    while($n = mysqli_fetch_assoc($nilai_result)){
        $nilai_data[(int) $n['mapel_id']] = $n['nilai'];
        $nilai_tersimpan_ids[(int) $n['mapel_id']] = true;
    }

    mysqli_stmt_close($nilai_stmt);
}

foreach($agama_mapel_ids as $agama_id){
    if(array_key_exists($agama_id, $nilai_data)){
        $agama_aktif_id = $agama_id;
        break;
    }
}
$agama_edit_id = $agama_aktif_id;

$nilai_flash = $_SESSION['tambah_nilai_flash'] ?? null;
unset($_SESSION['tambah_nilai_flash']);

if(is_array($nilai_flash) &&
   (string) ($nilai_flash['siswa_id'] ?? '') === (string) $siswa_id_selected){
    $form_error = (string) ($nilai_flash['error'] ?? '');
    $agama_conflict = !empty($nilai_flash['agama_conflict']);
    $agama_edit_id = isset($nilai_flash['agama_edit_id'])
        ? (int) $nilai_flash['agama_edit_id']
        : $agama_edit_id;

    foreach(($nilai_flash['nilai'] ?? []) as $mapel_id => $nilai){
        $mapel_id = (int) $mapel_id;
        if(isset($mapel_by_id[$mapel_id])){
            $nilai_data[$mapel_id] = trim((string) $nilai);
        }
    }
}

if(isset($_POST['simpan'])){
    $siswa_id = filter_input(INPUT_POST, 'siswa_id', FILTER_VALIDATE_INT);
    $nilai_input = $_POST['nilai'] ?? [];
    $hapus_ids = [];

    if(!is_array($nilai_input)){
        $form_error = 'Format data nilai tidak valid.';
        $nilai_input = [];
    }elseif(count($nilai_input) > count($mapel_by_id)){
        $form_error = 'Jumlah data nilai yang dikirim tidak valid.';
    }

    if($form_error === '' &&
       (!$siswa_id || $siswa_id !== (int) $siswa_id_selected || !$d_siswa)){
        $form_error = 'Siswa yang dipilih tidak valid.';
    }

    if($form_error === ''){
        foreach($nilai_input as $mapel_id => $nilai){
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
    }

    $agama_final = [];

    if($form_error === ''){
        foreach($agama_mapel_ids as $agama_id){
            if(array_key_exists($agama_id, $nilai_data) &&
               !isset($hapus_ids[$agama_id])){
                $agama_final[$agama_id] = true;
            }

            $nilai_agama_baru = trim((string) ($nilai_input[$agama_id] ?? ''));
            if($nilai_agama_baru !== '' && !isset($hapus_ids[$agama_id])){
                $agama_final[$agama_id] = true;
            }
        }
    }

    if($form_error === '' && count($agama_final) > 1){
        $form_error = 'Satu siswa hanya boleh memiliki satu nilai mata pelajaran agama.';
        $agama_conflict = true;
        $agama_edit_id = $agama_aktif_id ?? array_key_first($agama_final);
    }

    if($form_error === ''){
        mysqli_begin_transaction($conn);

        try{
            $upsert_stmt = mysqli_prepare($conn,"
                INSERT INTO nilai_detail (siswa_id, mapel_id, nilai)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE nilai = VALUES(nilai)
            ");

            $hapus_stmt = mysqli_prepare($conn,"
                DELETE FROM nilai_detail
                WHERE siswa_id = ? AND mapel_id = ?
            ");

            foreach(array_keys($hapus_ids) as $mapel_id_hapus){
                mysqli_stmt_bind_param(
                    $hapus_stmt,
                    "ii",
                    $siswa_id,
                    $mapel_id_hapus
                );
                mysqli_stmt_execute($hapus_stmt);
            }

            mysqli_stmt_close($hapus_stmt);

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

            mysqli_stmt_close($upsert_stmt);
            mysqli_commit($conn);

            catat_perubahan_nilai(
                $conn,
                (int) $siswa_id,
                $d_siswa,
                $mapel_by_id,
                $nilai_data,
                $nilai_input
            );

            header(
                'Location: input_nilai.php?'.
                http_build_query([
                    'kelas' => $d_siswa['kelas'],
                    'toast' => 'Semua nilai berhasil disimpan'
                ])
            );
            exit;

        }catch(mysqli_sql_exception $exception){
            mysqli_rollback($conn);
            $form_error = 'Nilai gagal disimpan. Silakan coba kembali.';
        }
    }

    if($form_error !== ''){
        $nilai_aman = [];
        foreach($nilai_input as $mapel_id => $nilai){
            if(isset($mapel_by_id[(int) $mapel_id])){
                $nilai_aman[(int) $mapel_id] = trim((string) $nilai);
            }
        }

        $_SESSION['tambah_nilai_flash'] = [
            'siswa_id' => (string) $siswa_id_selected,
            'error' => $form_error,
            'nilai' => $nilai_aman,
            'agama_conflict' => $agama_conflict,
            'agama_edit_id' => $agama_edit_id
        ];

        header('Location: tambah_nilai.php?'.http_build_query([
            'kelas' => $kelas_selected,
            'siswa_id' => $siswa_id_selected
        ]));
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>

<title>Tambah Nilai</title>

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

<i class="bi bi-journal-check"></i>

</div>

<h2 class="page-title">
Tambah Nilai
</h2>

<div class="page-subtitle">

Input nilai akademik siswa berdasarkan
seluruh mata pelajaran yang berlaku.

</div>

<form method="POST">

<?= csrf_field(); ?>

<?php if($form_error !== '' && !$agama_conflict){ ?>

<div class="alert alert-danger" role="alert">
    <?= htmlspecialchars($form_error); ?>
</div>

<?php } ?>
<div class="mb-4">

<label class="form-label">

Kelas / Jurusan

</label>

<select name="kelas"
        class="form-select"
        onchange="window.location='?kelas='+this.value">

<option value="">
-- Pilih Kelas --
</option>

<?php
while($k =
mysqli_fetch_assoc($kelas_data)){
?>

<option value="<?= htmlspecialchars($k['kelas'], ENT_QUOTES, 'UTF-8'); ?>"

<?php
if($kelas_selected ==
   $k['kelas']){

    echo "selected";
}
?>

>

<?= htmlspecialchars($k['kelas'], ENT_QUOTES, 'UTF-8'); ?>

</option>

<?php } ?>

</select>

</div>

<div class="mb-4">

<label class="form-label">

Pilih Siswa

</label>

<select name="siswa_id"
        class="form-select"

    <?php
    if($kelas_selected == ''){
        echo "disabled";
    }
    ?>

    onchange="
    window.location=
    '?kelas=<?= rawurlencode($kelas_selected); ?>&siswa_id='
    +encodeURIComponent(this.value)
    "

    required>

    <option value="">

        <?php

        if($kelas_selected == ''){

            echo "Pilih kelas terlebih dahulu";

        }else{

            echo "-- Pilih Siswa --";
        }

        ?>

    </option>

    <?php while($s = mysqli_fetch_assoc($siswa)){ ?>

    <option value="<?= (int) $s['id']; ?>"

    <?php
    if($siswa_id_selected ==
    $s['id']){

        echo "selected";
    }
    ?>

    >

    <?= htmlspecialchars($s['nama_siswa'], ENT_QUOTES, 'UTF-8'); ?>

    </option>

    <?php } ?>

</select>

</div>

<?php if($d_siswa){ ?>

<div class="alert alert-primary">
    <strong><?= htmlspecialchars($d_siswa['nama_siswa']); ?></strong><br>
    <?= htmlspecialchars($d_siswa['kelas']); ?> -
    <?= htmlspecialchars($d_siswa['jurusan']); ?>
</div>

<?php if($agama_conflict){ ?>
<div class="alert alert-danger agama-warning"
     id="agamaWarning"
     role="alert"
     tabindex="-1">
    <h6 class="fw-bold mb-2">
        <i class="bi bi-exclamation-triangle me-1"></i>
        Nilai agama tidak dapat disimpan
    </h6>
    <div>
        <?= htmlspecialchars($form_error); ?>
        Kosongkan nilai agama lama terlebih dahulu sebelum memilih agama lain.
    </div>
    <div class="d-flex flex-wrap gap-2 mt-3">
        <a href="#mapel-<?= (int) ($agama_edit_id ?? 0); ?>"
           class="btn btn-warning">
            <i class="bi bi-pencil-square me-1"></i>
            Edit Nilai Agama
        </a>
        <a href="input_nilai.php?<?= http_build_query([
            'kelas' => $d_siswa['kelas'],
            'siswa_id' => (int) $d_siswa['id']
        ]); ?>"
           class="btn btn-secondary">
            <i class="bi bi-x-circle me-1"></i>
            Batal
        </a>
    </div>
</div>
<?php } ?>

<?php foreach($mapel_rows as $m){ ?>

<?php
$mapel_id_form = (int) $m['id'];
$mapel_agama = in_array($mapel_id_form, $agama_mapel_ids, true);
$agama_lain_dinonaktifkan = $mapel_agama &&
    $agama_aktif_id !== null &&
    $agama_aktif_id !== $mapel_id_form &&
    trim((string) ($nilai_data[$agama_aktif_id] ?? '')) !== '';
?>

<div class="mb-4 nilai-mapel-row<?= $mapel_agama ? ' agama-row' : ''; ?>"
     id="mapel-<?= $mapel_id_form; ?>"
     data-mapel-id="<?= $mapel_id_form; ?>"
     data-agama="<?= $mapel_agama ? '1' : '0'; ?>">
    <label class="form-label">
        <?= htmlspecialchars($m['nama_mapel']); ?>
    </label>

    <input type="number"
           name="nilai[<?= $mapel_id_form; ?>]"
           class="form-control"
           data-nilai-input
           data-nilai-akademik
           inputmode="decimal"
           min="0"
           max="100"
           step="0.01"
           value="<?= htmlspecialchars($nilai_data[$mapel_id_form] ?? ''); ?>"
           placeholder="Masukkan nilai jika tersedia"
           <?= $agama_lain_dinonaktifkan ? 'disabled' : ''; ?>>

    <?php if($mapel_agama){ ?>
    <small class="text-danger d-block mt-2 agama-disabled-note<?=
        $agama_lain_dinonaktifkan ? '' : ' d-none'; ?>">
        Dinonaktifkan karena nilai
        <span data-agama-active-name><?= $agama_aktif_id !== null
            ? htmlspecialchars($mapel_by_id[$agama_aktif_id]['nama_mapel'])
            : 'mata pelajaran agama lain'; ?></span>
        sudah diisi.
    </small>
    <?php } ?>

</div>

<?php } ?>

<?php }else{ ?>

<div class="alert alert-info">
    Pilih kelas dan siswa untuk menampilkan seluruh mata pelajaran.
</div>

<?php } ?>

<div class="d-grid gap-3">

<button type="submit"
        name="simpan"
        class="btn-save"
        <?= !$d_siswa ? 'disabled' : ''; ?>>

<i class="bi bi-save"></i>

Simpan Semua Nilai

</button>

<a href="input_nilai.php<?= $kelas_selected !== ''
    ? '?'.http_build_query(['kelas' => $kelas_selected])
    : ''; ?>"
    class="btn btn-back">

    <i class="bi bi-arrow-left"></i>

    Kembali

</a>

</div>

</form>

<div class="info-box">

<h6>
Informasi Input
</h6>

<ul>

<li>
Pastikan siswa dan mata pelajaran dipilih dengan benar
</li>

<li>
Nilai yang sudah tersedia akan diperbarui tanpa membuat data duplikat
</li>

<li>Nilai lama akan dihapus jika kolom nilainya dikosongkan</li>

<li>
Satu siswa hanya dapat memiliki satu nilai mata pelajaran agama
</li>

<li>
Nilai akan langsung tersimpan ke database
</li>

<li>
Data digunakan dalam proses clustering K-Means
</li>

</ul>

</div>

</div>

</div>

<script src="../assets/nilai_validation.js?v=3"></script>
<script>
document.addEventListener('DOMContentLoaded', function(){
    const rows = Array.from(document.querySelectorAll('.nilai-mapel-row'));
    const agamaRows = rows.filter(function(row){
        return row.dataset.agama === '1';
    });

    function updateNilaiRows(){
        const agamaAktifRow = agamaRows.find(function(row){
            const input = row.querySelector('[data-nilai-input]');
            return input && input.value.trim() !== '';
        }) || null;

        const agamaAktifNama = agamaAktifRow
            ? agamaAktifRow.querySelector('.form-label').textContent.trim()
            : '';

        rows.forEach(function(row){
            const input = row.querySelector('[data-nilai-input]');
            const isAgama = row.dataset.agama === '1';
            const note = row.querySelector('.agama-disabled-note');

            if(!input){
                return;
            }

            const agamaLainTerkunci = isAgama &&
                agamaAktifRow !== null && row !== agamaAktifRow;

            input.disabled = Boolean(agamaLainTerkunci);
            if(note){
                note.classList.toggle('d-none', !agamaLainTerkunci);
                const activeName = note.querySelector('[data-agama-active-name]');
                if(activeName && agamaAktifNama !== ''){
                    activeName.textContent = agamaAktifNama;
                }
            }
        });
    }

    agamaRows.forEach(function(row){
        const input = row.querySelector('[data-nilai-input]');
        if(input){
            input.addEventListener('input', updateNilaiRows);
            input.addEventListener('change', updateNilaiRows);
        }
    });

    updateNilaiRows();

    const warning = document.getElementById('agamaWarning');
    if(warning){
        requestAnimationFrame(function(){
            warning.scrollIntoView({behavior:'smooth', block:'center'});
            warning.focus({preventScroll:true});
        });
    }
});
</script>

</body>
</html>
