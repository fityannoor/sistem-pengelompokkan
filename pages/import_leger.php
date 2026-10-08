<?php

include '../auth/cek_guru.php';
include '../koneksi.php';
require_once '../includes/audit_log.php';

require '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$error = '';
$preview = null;
$tahun_mulai_default = (int) date('Y') - ((int) date('n') < 7 ? 1 : 0);
$tahun_ajaran_options = [];
for($offset = -2; $offset <= 2; $offset++){
    $tahun_mulai = $tahun_mulai_default + $offset;
    $tahun_ajaran_options[] = $tahun_mulai.'/'.($tahun_mulai + 1);
}
$tahun_ajaran_import = $tahun_mulai_default.'/'.($tahun_mulai_default + 1);

$mapel_umum = [
    'agama', 'pancasila', 'bahasa indonesia', 'bahasa inggris',
    'matematika', 'jasmani', 'olahraga', 'pjok', 'sejarah',
    'informatika', 'ipas', 'seni musik', 'seni budaya',
    'muatan lokal', 'bahasa daerah', 'kewirausahaan', 'pkwu'
];

$cakupan_mapel = static function(string $nama, string $jurusan) use ($mapel_umum): string {
    foreach($mapel_umum as $kata){
        if(stripos($nama, $kata) !== false){
            return 'Umum';
        }
    }
    return $jurusan;
};

if(isset($_POST['batal_preview'])){
    unset($_SESSION['import_leger_plan']);
    header('Location: import_leger.php');
    exit;
}

if(isset($_POST['konfirmasi_sinkronisasi'])){
    $plan = $_SESSION['import_leger_plan'] ?? null;
    $plan_id = (string) ($_POST['plan_id'] ?? '');

    $plan_kedaluwarsa = !isset($plan['created_at']) ||
        (time() - (int) $plan['created_at']) > 900;
    $plan_tahun_invalid = !isset($plan['tahun_ajaran']) ||
        !in_array($plan['tahun_ajaran'], $tahun_ajaran_options, true);

    if(!is_array($plan) || $plan_kedaluwarsa || $plan_tahun_invalid ||
       !hash_equals((string) ($plan['id'] ?? ''), $plan_id)){
        $error = 'Pratinjau import sudah tidak berlaku. Silakan unggah file kembali.';
    }else{
        mysqli_begin_transaction($conn);
        try{
            $mapel_ids = [];
            $mapel_stmt = mysqli_prepare($conn,"
                INSERT INTO mata_pelajaran (nama_mapel, jurusan)
                VALUES (?, ?)
                ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)
            ");
            foreach($plan['mapel'] as $kolom => $mapel){
                $nama_mapel = (string) $mapel['nama'];
                $mapel_jurusan = (string) $mapel['jurusan'];
                mysqli_stmt_bind_param($mapel_stmt, 'ss', $nama_mapel, $mapel_jurusan);
                mysqli_stmt_execute($mapel_stmt);
                $mapel_ids[(int) $kolom] = mysqli_insert_id($conn);
            }
            mysqli_stmt_close($mapel_stmt);

            $cari_siswa = mysqli_prepare($conn, 'SELECT id FROM siswa WHERE nis = ? LIMIT 1');
            $tambah_siswa = mysqli_prepare($conn,"
                INSERT INTO siswa (nis, nama_siswa, kelas, jurusan)
                VALUES (?, ?, ?, ?)
            ");
            $ubah_siswa = mysqli_prepare($conn,"
                UPDATE siswa SET nama_siswa = ?, kelas = ?, jurusan = ? WHERE id = ?
            ");
            $upsert_nilai = mysqli_prepare($conn,"
                INSERT INTO nilai_detail (siswa_id, mapel_id, nilai)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE nilai = VALUES(nilai)
            ");

            foreach($plan['siswa'] as $data){
                $nis = (string) $data['nis'];
                $nama = (string) $data['nama'];
                $kelas_plan = (string) $plan['kelas'];
                $jurusan_plan = (string) $plan['jurusan'];
                mysqli_stmt_bind_param($cari_siswa, 's', $nis);
                mysqli_stmt_execute($cari_siswa);
                $tersimpan = mysqli_fetch_assoc(mysqli_stmt_get_result($cari_siswa));

                if($tersimpan){
                    $siswa_id = (int) $tersimpan['id'];
                    mysqli_stmt_bind_param(
                        $ubah_siswa, 'sssi', $nama,
                        $kelas_plan, $jurusan_plan, $siswa_id
                    );
                    mysqli_stmt_execute($ubah_siswa);
                }else{
                    mysqli_stmt_bind_param(
                        $tambah_siswa, 'ssss', $nis, $nama,
                        $kelas_plan, $jurusan_plan
                    );
                    mysqli_stmt_execute($tambah_siswa);
                    $siswa_id = mysqli_insert_id($conn);
                }

                foreach($data['nilai'] as $kolom => $nilai){
                    $mapel_id = $mapel_ids[(int) $kolom];
                    mysqli_stmt_bind_param($upsert_nilai, 'iid', $siswa_id, $mapel_id, $nilai);
                    mysqli_stmt_execute($upsert_nilai);
                }
            }

            mysqli_stmt_close($cari_siswa);
            mysqli_stmt_close($tambah_siswa);
            mysqli_stmt_close($ubah_siswa);
            mysqli_stmt_close($upsert_nilai);

            $periode_stmt = mysqli_prepare($conn,"
                INSERT INTO periode_nilai_kelas
                    (kelas, tahun_ajaran, updated_by)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    tahun_ajaran = VALUES(tahun_ajaran),
                    updated_by = VALUES(updated_by)
            ");
            $updated_by = (int) ($_SESSION['user_id'] ?? 0);
            $periode_kelas = (string) $plan['kelas'];
            $periode_tahun = (string) $plan['tahun_ajaran'];
            mysqli_stmt_bind_param(
                $periode_stmt,
                'ssi',
                $periode_kelas,
                $periode_tahun,
                $updated_by
            );
            mysqli_stmt_execute($periode_stmt);
            mysqli_stmt_close($periode_stmt);

            mysqli_commit($conn);

            catat_aktivitas(
                $conn,
                'sinkronisasi',
                'leger',
                null,
                'Sinkronisasi leger kelas '.$plan['kelas'].
                ' tahun ajaran '.$plan['tahun_ajaran'].'; '.
                count($plan['siswa']).' siswa diproses'
            );
            unset($_SESSION['import_leger_plan']);

            header('Location: input_nilai.php?'.http_build_query([
                'kelas' => $plan['kelas'],
                'toast' => 'Sinkronisasi leger berhasil'
            ]));
            exit;
        }catch(Throwable $exception){
            mysqli_rollback($conn);
            $error = 'Sinkronisasi gagal. Tidak ada perubahan yang disimpan.';
        }
    }
}

if(isset($_POST['import'])){
    unset($_SESSION['import_leger_plan']);
    $upload = $_FILES['file_excel'] ?? null;
    $tahun_ajaran_import = trim((string) ($_POST['tahun_ajaran'] ?? ''));

    if(!in_array($tahun_ajaran_import, $tahun_ajaran_options, true)){
        $error = 'Tahun ajaran leger tidak valid.';
    }elseif(!$upload || ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK){
        $error = 'Pilih file Excel yang valid terlebih dahulu.';
    }elseif(($upload['size'] ?? 0) > 10 * 1024 * 1024){
        $error = 'Ukuran file Excel maksimal 10 MB.';
    }else{
        try{
            $jenis = IOFactory::identify($upload['tmp_name']);
            if(!in_array($jenis, ['Xlsx', 'Xls'], true)){
                throw new RuntimeException('Format file tidak didukung.');
            }
            $sheet = IOFactory::load($upload['tmp_name'])->getActiveSheet()->toArray();

            if(!isset($sheet[2][1], $sheet[6]) || count($sheet) < 8){
                throw new RuntimeException('Struktur leger tidak sesuai template.');
            }

            $tahun_dalam_file = [];
            for($baris_header = 0; $baris_header <= 6; $baris_header++){
                foreach(($sheet[$baris_header] ?? []) as $sel_header){
                    if(preg_match(
                        '/\b(20\d{2})\s*[-\/]\s*(20\d{2})\b/',
                        (string) $sel_header,
                        $tahun_match
                    )){
                        $awal = (int) $tahun_match[1];
                        $akhir = (int) $tahun_match[2];
                        if($akhir === $awal + 1){
                            $tahun_dalam_file[$awal.'/'.$akhir] = true;
                        }
                    }
                }
            }

            if(count($tahun_dalam_file) > 1){
                throw new RuntimeException(
                    'Leger memuat lebih dari satu tahun ajaran pada bagian header.'
                );
            }
            if($tahun_dalam_file){
                $tahun_file = (string) array_key_first($tahun_dalam_file);
                if($tahun_file !== $tahun_ajaran_import){
                    throw new RuntimeException(
                        'Tahun ajaran yang dipilih tidak sesuai dengan leger. '.
                        'File memuat tahun ajaran '.$tahun_file.'.'
                    );
                }
                $tahun_ajaran_import = $tahun_file;
            }

            $kelas = preg_replace('/\s+/u', ' ', trim((string) $sheet[2][1]));
            $jurusan = preg_replace('/^(X|XI|XII)\s+/i', '', $kelas);
            if($kelas === '' || $jurusan === ''){
                throw new RuntimeException('Nama kelas pada leger tidak ditemukan.');
            }
            if(strlen($kelas) > 100 || strlen($jurusan) > 100){
                throw new RuntimeException('Nama kelas atau jurusan melebihi 100 karakter.');
            }

            $jurusan_stmt = mysqli_prepare($conn,"
                SELECT jurusan FROM siswa
                WHERE kelas = ? AND jurusan IS NOT NULL AND jurusan != ''
                LIMIT 1
            ");
            mysqli_stmt_bind_param($jurusan_stmt, 's', $kelas);
            mysqli_stmt_execute($jurusan_stmt);
            $jurusan_lama = mysqli_fetch_assoc(mysqli_stmt_get_result($jurusan_stmt));
            mysqli_stmt_close($jurusan_stmt);
            if($jurusan_lama){
                $jurusan = (string) $jurusan_lama['jurusan'];
            }

            $ignore = ['A', 'I', 'S', 'NO', 'NAMA', 'NIS', ''];
            $mapel = [];
            foreach($sheet[6] as $kolom => $judul){
                if($kolom < 3){ continue; }
                $nama = preg_replace('/\s+/u', ' ', trim((string) $judul));
                if(strlen($nama) <= 1 || in_array(strtoupper($nama), $ignore, true)){ continue; }
                if(strlen($nama) > 100){
                    throw new RuntimeException('Nama mata pelajaran melebihi 100 karakter.');
                }
                $mapel[$kolom] = [
                    'nama' => $nama,
                    'jurusan' => $cakupan_mapel($nama, $jurusan)
                ];
            }
            if(!$mapel){
                throw new RuntimeException('Header mata pelajaran tidak ditemukan.');
            }

            $siswa = [];
            $nis_ditemukan = [];
            $kesalahan = [];
            for($baris = 7; $baris < count($sheet); $baris++){
                $row = $sheet[$baris];
                $nis = trim((string) ($row[2] ?? ''));
                $nama = preg_replace('/\s+/u', ' ', trim((string) ($row[1] ?? '')));
                if($nis === '' && $nama === ''){ continue; }
                $nomor_baris = $baris + 1;

                if(!preg_match('/^[0-9]{10}$/', $nis)){
                    $kesalahan[] = "Baris {$nomor_baris}: NIS wajib tepat 10 angka.";
                    continue;
                }
                if($nama === ''){
                    $kesalahan[] = "Baris {$nomor_baris}: nama siswa kosong.";
                    continue;
                }
                if(strlen($nama) > 100){
                    $kesalahan[] = "Baris {$nomor_baris}: nama siswa melebihi 100 karakter.";
                    continue;
                }
                if(isset($nis_ditemukan[$nis])){
                    $kesalahan[] = "Baris {$nomor_baris}: NIS {$nis} duplikat di dalam file.";
                    continue;
                }
                $nis_ditemukan[$nis] = true;

                $nilai_siswa = [];
                $jumlah_nilai_agama = 0;
                foreach($mapel as $kolom => $data_mapel){
                    $nilai_raw = trim((string) ($row[$kolom] ?? ''));
                    if($nilai_raw === '' || $nilai_raw === '-'){ continue; }
                    if(!is_numeric($nilai_raw) || (float) $nilai_raw < 0 || (float) $nilai_raw > 100){
                        $kesalahan[] = "Baris {$nomor_baris}: nilai {$data_mapel['nama']} harus 0 sampai 100.";
                        continue 2;
                    }
                    if(stripos($data_mapel['nama'], 'agama') !== false){
                        $jumlah_nilai_agama++;
                        if($jumlah_nilai_agama > 1){
                            $kesalahan[] = "Baris {$nomor_baris}: satu siswa hanya boleh memiliki satu nilai mata pelajaran agama.";
                            continue 2;
                        }
                    }
                    $nilai_siswa[$kolom] = (float) $nilai_raw;
                }
                $siswa[] = ['nis' => $nis, 'nama' => $nama, 'nilai' => $nilai_siswa];
            }

            if($kesalahan){
                throw new RuntimeException(implode(' ', array_slice($kesalahan, 0, 8)));
            }
            if(!$siswa){
                throw new RuntimeException('Tidak ada data siswa yang valid untuk diimport.');
            }

            $ringkasan = [
                'siswa_baru' => 0, 'siswa_berubah' => 0,
                'nilai_baru' => 0, 'nilai_berubah' => 0, 'nilai_sama' => 0
            ];
            $perubahan = [];
            $cari_siswa = mysqli_prepare($conn,"
                SELECT id, nama_siswa, kelas FROM siswa WHERE nis = ? LIMIT 1
            ");
            $cari_mapel = mysqli_prepare($conn,"
                SELECT id FROM mata_pelajaran WHERE nama_mapel = ? AND jurusan = ? LIMIT 1
            ");
            $cari_nilai = mysqli_prepare($conn,"
                SELECT nilai FROM nilai_detail WHERE siswa_id = ? AND mapel_id = ? LIMIT 1
            ");

            foreach($siswa as $data){
                $nis_preview = (string) $data['nis'];
                mysqli_stmt_bind_param($cari_siswa, 's', $nis_preview);
                mysqli_stmt_execute($cari_siswa);
                $lama = mysqli_fetch_assoc(mysqli_stmt_get_result($cari_siswa));
                $status = 'Tidak berubah';
                if(!$lama){
                    $ringkasan['siswa_baru']++;
                    $status = 'Siswa baru';
                }elseif($lama['nama_siswa'] !== $data['nama'] || $lama['kelas'] !== $kelas){
                    $ringkasan['siswa_berubah']++;
                    $status = 'Identitas diperbarui';
                }

                foreach($data['nilai'] as $kolom => $nilai){
                    if(!$lama){ $ringkasan['nilai_baru']++; continue; }
                    $dm = $mapel[$kolom];
                    $nama_preview = (string) $dm['nama'];
                    $jurusan_preview = (string) $dm['jurusan'];
                    mysqli_stmt_bind_param($cari_mapel, 'ss', $nama_preview, $jurusan_preview);
                    mysqli_stmt_execute($cari_mapel);
                    $m_lama = mysqli_fetch_assoc(mysqli_stmt_get_result($cari_mapel));
                    if(!$m_lama){ $ringkasan['nilai_baru']++; continue; }
                    $sid = (int) $lama['id']; $mid = (int) $m_lama['id'];
                    mysqli_stmt_bind_param($cari_nilai, 'ii', $sid, $mid);
                    mysqli_stmt_execute($cari_nilai);
                    $n_lama = mysqli_fetch_assoc(mysqli_stmt_get_result($cari_nilai));
                    if(!$n_lama){ $ringkasan['nilai_baru']++; }
                    elseif(abs((float) $n_lama['nilai'] - $nilai) > 0.0001){ $ringkasan['nilai_berubah']++; }
                    else{ $ringkasan['nilai_sama']++; }
                }
                $perubahan[] = ['nis' => $data['nis'], 'nama' => $data['nama'], 'status' => $status];
            }
            mysqli_stmt_close($cari_siswa);
            mysqli_stmt_close($cari_mapel);
            mysqli_stmt_close($cari_nilai);

            $plan = [
                'id' => bin2hex(random_bytes(16)), 'kelas' => $kelas,
                'jurusan' => $jurusan, 'mapel' => $mapel, 'siswa' => $siswa,
                'ringkasan' => $ringkasan, 'perubahan' => $perubahan,
                'created_at' => time(), 'tahun_ajaran' => $tahun_ajaran_import
            ];
            $_SESSION['import_leger_plan'] = $plan;
            $preview = $plan;
        }catch(Throwable $exception){
            $error = $exception->getMessage();
        }
    }
}

if($preview === null && isset($_SESSION['import_leger_plan'])){
    $preview = $_SESSION['import_leger_plan'];
}

?>

<!DOCTYPE html>
<html lang='id'>
<head>

<meta charset='UTF-8'>

<meta name='viewport'
      content='width=device-width, initial-scale=1.0'>

<title>Import Leger</title>

<link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css' rel='stylesheet'>
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
<link rel="preconnect" href="https://fonts.googleapis.com">

<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/import_leger.css">



</head>
<body>

    <?php include '../includes/loading_screen.php'; ?>

    <div class="wrapper">

        <div class="import-card">

            <div class="text-center">

                <div class="badge-import">

                    <i class="bi bi-file-earmark-excel"></i>

                    Excel Import System

                </div>

            </div>

            <div class="icon-box">

                <i class="bi bi-cloud-arrow-up"></i>

            </div>

            <h2 class="page-title">
             Import Leger Nilai
            </h2>

             <div class="page-subtitle">

                Upload file Excel leger untuk memasukkan
                data nilai siswa secara otomatis.

            </div>

            <?php if($error !== ''){ ?>
            <div class="alert alert-danger" role="alert">
                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
            </div>
            <?php } ?>

            <?php if($preview){ ?>
            <div class="alert alert-warning" role="alert">
                <strong>Pratinjau sinkronisasi kelas
                    <?= htmlspecialchars($preview['kelas'], ENT_QUOTES, 'UTF-8'); ?></strong><br>
                Tahun ajaran:
                <strong><?= htmlspecialchars($preview['tahun_ajaran'], ENT_QUOTES, 'UTF-8'); ?></strong>.
                Periksa ringkasan berikut. Data yang tidak terdapat dalam leger tidak akan dihapus.
            </div>

            <div class="row g-2 mb-3 text-center">
                <div class="col-6"><div class="border rounded p-2">
                    <strong><?= (int) $preview['ringkasan']['siswa_baru']; ?></strong><br>
                    <small>Siswa baru</small>
                </div></div>
                <div class="col-6"><div class="border rounded p-2">
                    <strong><?= (int) $preview['ringkasan']['siswa_berubah']; ?></strong><br>
                    <small>Identitas diperbarui</small>
                </div></div>
                <div class="col-4"><div class="border rounded p-2">
                    <strong><?= (int) $preview['ringkasan']['nilai_baru']; ?></strong><br>
                    <small>Nilai baru</small>
                </div></div>
                <div class="col-4"><div class="border rounded p-2">
                    <strong><?= (int) $preview['ringkasan']['nilai_berubah']; ?></strong><br>
                    <small>Nilai berubah</small>
                </div></div>
                <div class="col-4"><div class="border rounded p-2">
                    <strong><?= (int) $preview['ringkasan']['nilai_sama']; ?></strong><br>
                    <small>Nilai sama</small>
                </div></div>
            </div>

            <div class="table-responsive mb-3" style="max-height:260px;">
                <table class="table table-sm table-bordered align-middle">
                    <thead><tr><th>NIS</th><th>Nama</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php foreach($preview['perubahan'] as $item){ ?>
                    <tr>
                        <td><?= htmlspecialchars($item['nis'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?= htmlspecialchars($item['nama'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?= htmlspecialchars($item['status'], ENT_QUOTES, 'UTF-8'); ?></td>
                    </tr>
                    <?php } ?>
                    </tbody>
                </table>
            </div>

            <form method="POST" class="d-grid gap-2 mb-3">
                <?= csrf_field(); ?>
                <input type="hidden" name="plan_id"
                       value="<?= htmlspecialchars($preview['id'], ENT_QUOTES, 'UTF-8'); ?>">
                <button type="submit" name="konfirmasi_sinkronisasi"
                        class="btn btn-success">
                    <i class="bi bi-check-circle"></i> Konfirmasi Sinkronisasi
                </button>
                <button type="submit" name="batal_preview" class="btn btn-secondary">
                    Batal dan Pilih File Lain
                </button>
            </form>
            <?php }else{ ?>

            <form method="POST"
                enctype="multipart/form-data">

                <?= csrf_field(); ?>

                <div class="mb-3 text-start">
                    <label class="form-label fw-semibold">Tahun Ajaran Leger</label>
                    <select name="tahun_ajaran" class="form-select" required>
                        <?php foreach($tahun_ajaran_options as $tahun_option){ ?>
                        <option value="<?= htmlspecialchars($tahun_option, ENT_QUOTES, 'UTF-8'); ?>"
                            <?= $tahun_ajaran_import === $tahun_option ? 'selected' : ''; ?>>
                            <?= htmlspecialchars($tahun_option, ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                        <?php } ?>
                    </select>
                    <small class="text-muted">
                        Tahun ini akan menjadi periode resmi nilai kelas yang diimport.
                    </small>
                </div>

                <div class="upload-box"
                id="drop-area">

                    <p>
                        Drag & Drop File Excel
                        di sini
                    </p>

                    <span>
                        atau klik untuk memilih file
                    </span>

                    <input
                        type="file"
                        name="file_excel"
                        id="fileElem"
                        accept=".xls,.xlsx"
                    >
                    <div id="file-name"
                    style="
                    margin-top:15px;
                    font-weight:600;
                    color:#2563eb;
                    ">
                    </div>
                </div>
                <br>
                <div class="d-grid gap-3">

                    <button type="submit"
                        name="import"
                        class="btn-import">

                        <i class="bi bi-upload"></i>

                        Tampilkan Pratinjau Import

                    </button>

                    <a href="input_nilai.php"
                        class="btn btn-back">

                        <i class="bi bi-arrow-left"></i>
                        
                        Kembali

                    </a>

                </div>

            </form>

            <?php } ?>

            <div class="info-box">

                <h6>
                    Informasi Import
                </h6>

                <ul>

                    <li>
                        File harus berformat .xlsx atau .xls
                    </li>

                    <li>
                        Perubahan ditampilkan terlebih dahulu sebelum disimpan
                    </li>

                    <li>
                        Tahun ajaran pilihan harus sesuai dengan tahun pada header leger
                    </li>

                    <li>
                        NIS yang sama akan memperbarui nama, kelas, dan nilai
                    </li>

                    <li>
                        Data yang tidak ada di dalam leger tidak akan dihapus
                    </li>

                    <li>
                        Data hasil import digunakan untuk clustering K-Means
                    </li>

                </ul>

            </div>

        </div>

    </div>
    <script>

const dropArea =
document.getElementById(
"drop-area"
);

const fileInput =
document.getElementById(
"fileElem"
);

if(dropArea && fileInput){


['dragenter',
 'dragover',
 'dragleave',
 'drop']

.forEach(eventName => {

    document.addEventListener(
    eventName,

    (e)=>{

        e.preventDefault();
        e.stopPropagation();

    }, false);
});


dropArea.addEventListener(
"dragover",

()=>{

    dropArea.classList.add(
    "dragover"
    );
});


dropArea.addEventListener(
"dragleave",

()=>{

    dropArea.classList.remove(
    "dragover"
    );
});


dropArea.addEventListener(
"drop",

(e)=>{

    dropArea.classList.remove(
    "dragover"
    );

    fileInput.files =
    e.dataTransfer.files;

    document.getElementById(
    "file-name"
    ).textContent =

    "📄 " +

    fileInput.files[0].name;
});

fileInput.addEventListener(
"change",

()=>{

    if(fileInput.files.length > 0){

        document.getElementById(
        "file-name"
        ).textContent =

        "📄 " +

        fileInput.files[0].name;
    }
});
}
</script>
</body>
</html>
