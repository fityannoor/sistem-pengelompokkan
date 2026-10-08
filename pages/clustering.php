<?php

include '../auth/cek_login.php';
include '../koneksi.php';
require_once '../includes/audit_log.php';
$active = 'cluster';

$dashboard_link = '../dashboard.php';
$data_siswa_link = 'data_siswa.php';
$mapel_link = 'mata_pelajaran.php';
$nilai_link = 'input_nilai.php';
$cluster_link = 'clustering.php';
$hasil_link = 'hasil_cluster.php';
$user_link = 'users.php';
$import_link = 'import_leger.php';

$logout_link = '../auth/logout.php';
$logo_path = '../assets/img/logo_smk_4.png';


$kelas_data = mysqli_query($conn,"
    SELECT DISTINCT kelas
    FROM siswa

    WHERE kelas IS NOT NULL
    AND kelas != ''

    ORDER BY kelas ASC
");
$kelas_valid_values = [];
while($kelas_item = mysqli_fetch_assoc($kelas_data)){
    $kelas_valid_values[] = (string) $kelas_item['kelas'];
}
mysqli_data_seek($kelas_data, 0);


$kelas_selected = trim((string) ($_GET['kelas'] ?? ''));
if(!in_array($kelas_selected, $kelas_valid_values, true)){
    $kelas_selected = '';
}

$jurusan = '';
$total_siswa_kelas = 0;
if($kelas_selected != ''){

    $q_kelas_stmt = mysqli_prepare($conn,"
        SELECT jurusan FROM siswa WHERE kelas = ? LIMIT 1
    ");
    mysqli_stmt_bind_param($q_kelas_stmt, 's', $kelas_selected);
    mysqli_stmt_execute($q_kelas_stmt);
    $d_kelas = mysqli_fetch_assoc(mysqli_stmt_get_result($q_kelas_stmt));
    mysqli_stmt_close($q_kelas_stmt);

    $jurusan =
    $d_kelas['jurusan'] ?? '';

    $q_total_stmt = mysqli_prepare($conn,"
        SELECT COUNT(*) AS total FROM siswa WHERE kelas = ?
    ");
    mysqli_stmt_bind_param($q_total_stmt, 's', $kelas_selected);
    mysqli_stmt_execute($q_total_stmt);
    $d_total_siswa = mysqli_fetch_assoc(mysqli_stmt_get_result($q_total_stmt));
    mysqli_stmt_close($q_total_stmt);

    $total_siswa_kelas =
    $d_total_siswa['total'] ?? 0;
}
$total_mapel = 0;

if($kelas_selected != ''){

    $q_mapel_stmt = mysqli_prepare($conn,"
        SELECT COUNT(*) AS total FROM mata_pelajaran
        WHERE jurusan = ? OR jurusan = 'Umum'
    ");
    mysqli_stmt_bind_param($q_mapel_stmt, 's', $jurusan);
    mysqli_stmt_execute($q_mapel_stmt);
    $d_total_mapel = mysqli_fetch_assoc(mysqli_stmt_get_result($q_mapel_stmt));
    mysqli_stmt_close($q_mapel_stmt);
    $total_mapel = (int) ($d_total_mapel['total'] ?? 0);
}

$process_error = '';
$tahun_ajaran_selected = '';

if($kelas_selected !== ''){
    $periode_stmt = mysqli_prepare($conn,"
        SELECT tahun_ajaran
        FROM periode_nilai_kelas
        WHERE kelas = ?
        LIMIT 1
    ");
    mysqli_stmt_bind_param($periode_stmt, 's', $kelas_selected);
    mysqli_stmt_execute($periode_stmt);
    $periode_data = mysqli_fetch_assoc(mysqli_stmt_get_result($periode_stmt));
    mysqli_stmt_close($periode_stmt);
    $tahun_ajaran_selected = (string) ($periode_data['tahun_ajaran'] ?? '');
}
$release_clustering_lock = static function(mysqli $conn): void {
    mysqli_query($conn, "SELECT RELEASE_LOCK('skripsi_kmeans_clustering')");
};

if(isset($_POST['proses'])){

    $python_bin = 'python';
    $log_file = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR).
        DIRECTORY_SEPARATOR.'skripsi-kmeans-clustering.log';

    $mode = 'keseluruhan';

    $kelas = trim($_POST['kelas'] ?? '');
    $tahun_ajaran = '';

    $kelas_stmt = mysqli_prepare(
        $conn,
        "SELECT id FROM siswa WHERE kelas = ? LIMIT 1"
    );

    mysqli_stmt_bind_param($kelas_stmt, "s", $kelas);
    mysqli_stmt_execute($kelas_stmt);
    mysqli_stmt_store_result($kelas_stmt);

    $kelas_valid =
    $kelas !== '' &&
    mysqli_stmt_num_rows($kelas_stmt) > 0;

    mysqli_stmt_close($kelas_stmt);

    if($kelas_valid){
        $periode_stmt = mysqli_prepare($conn,"
            SELECT tahun_ajaran
            FROM periode_nilai_kelas
            WHERE kelas = ?
            LIMIT 1
        ");
        mysqli_stmt_bind_param($periode_stmt, 's', $kelas);
        mysqli_stmt_execute($periode_stmt);
        $periode_data = mysqli_fetch_assoc(mysqli_stmt_get_result($periode_stmt));
        mysqli_stmt_close($periode_stmt);
        $tahun_ajaran = (string) ($periode_data['tahun_ajaran'] ?? '');
        $tahun_ajaran_selected = $tahun_ajaran;
    }

    if(!$kelas_valid){

        $process_error =
        'Kelas yang dipilih tidak valid. Silakan pilih kelas kembali.';

    }elseif($tahun_ajaran === ''){

        $process_error =
            'Kelas ini belum memiliki periode nilai. Import ulang leger dengan ' .
            'tahun ajaran yang benar sebelum menjalankan clustering.';

    }else{

        $lock_result = mysqli_query(
            $conn,
            "SELECT GET_LOCK('skripsi_kmeans_clustering', 0) AS acquired"
        );
        $lock_row = mysqli_fetch_assoc($lock_result);
        $lock_acquired = (int) ($lock_row['acquired'] ?? 0) === 1;

        if(!$lock_acquired){
            $process_error =
                'Proses clustering sedang dijalankan oleh pengguna lain. ' .
                'Tunggu hingga proses tersebut selesai, lalu coba kembali.';
        }else{

        $kelas_arg =
        escapeshellarg($kelas);

        $output = [];
        $return_code = 0;

        $script_path =
        realpath(__DIR__ . '/../python/kmeans_keseluruhan.py');

        if($script_path === false){

            $process_error =
            'File proses clustering tidak ditemukan.';

        }else{

            exec(
                $python_bin . ' '
                . escapeshellarg($script_path) . ' '
                . $kelas_arg
                . ' ' . escapeshellarg($tahun_ajaran)
                . ' 2>&1',
                $output,
                $return_code
            );

            $output_text = implode(PHP_EOL, $output);

            file_put_contents(
                $log_file,
                "Mode: " . $mode . PHP_EOL .
                "Kelas: " . $kelas . PHP_EOL .
                "Script: " . $script_path . PHP_EOL .
                "Return code: " . $return_code . PHP_EOL .
                "Output:" . PHP_EOL .
                $output_text . PHP_EOL .
                str_repeat('-', 60) . PHP_EOL,
                FILE_APPEND
            );

            $process_success =
            $return_code === 0 &&
            strpos(
                $output_text,
                'Data clustering keseluruhan berhasil disimpan'
            ) !== false;

            if($process_success){

                catat_aktivitas(
                    $conn,
                    'proses',
                    'clustering',
                    null,
                    'Menjalankan clustering kelas '.$kelas.
                    ' tahun ajaran '.$tahun_ajaran
                );

                $redirect_query = http_build_query([
                    'kelas' => $kelas,
                    'tahun_ajaran' => $tahun_ajaran,
                    'toast' => 'Clustering berhasil diproses'
                ]);

                $release_clustering_lock($conn);

                header(
                    'Location: hasil_cluster.php?'.$redirect_query
                );
                exit;
            }

            $process_error =
            'Proses clustering gagal atau data nilai kelas belum mencukupi.';
        }
        $release_clustering_lock($conn);
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

    <title>Clustering</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">

    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/clustering.css?v=4">


</head>
<body>

    <?php include '../includes/sidebar.php'; ?>
    <?php include '../includes/loading_screen.php'; ?>

    <div class="content">

        <div class="topbar">

            <div>

                <h3 class="page-title mb-0">
                    Proses Clustering
                </h3>

                <small class="text-muted">
                    Jalankan analisis K-Means berdasarkan seluruh nilai pada kelas
                </small>

            </div>

            <a href="<?= $kelas_selected !== ''
                ? 'hasil_cluster.php?'.http_build_query(['kelas' => $kelas_selected])
                : 'hasil_cluster.php'; ?>"
               class="btn btn-outline-primary px-4">

                <i class="bi bi-bar-chart"></i>

                Lihat Hasil

            </a>

        </div>

        <div class="summary-grid mb-4">

            <div class="summary-card">

                <div class="summary-icon">
                    <i class="bi bi-building"></i>
                </div>

                <div class="summary-content">
                    <small>Kelas Dipilih</small>
                    <h5><?= $kelas_selected !== ''
                        ? htmlspecialchars($kelas_selected, ENT_QUOTES, 'UTF-8')
                        : '-'; ?></h5>
                </div>

            </div>

            <div class="summary-card">

                <div class="summary-icon">
                    <i class="bi bi-mortarboard"></i>
                </div>

                <div class="summary-content">
                    <small>Jurusan</small>
                    <h5><?= $jurusan !== ''
                        ? htmlspecialchars($jurusan, ENT_QUOTES, 'UTF-8')
                        : '-'; ?></h5>
                </div>

            </div>

            <div class="summary-card">

                <div class="summary-icon">
                    <i class="bi bi-people"></i>
                </div>

                <div class="summary-content">
                    <small>Jumlah Siswa</small>
                    <h5><?= $kelas_selected != '' ? $total_siswa_kelas : '-'; ?></h5>
                </div>

            </div>

            <div class="summary-card">

                <div class="summary-icon">
                    <i class="bi bi-book"></i>
                </div>

                <div class="summary-content">
                    <small>Mapel Tersedia</small>
                    <h5><?= $kelas_selected != '' ? $total_mapel : '-'; ?></h5>
                </div>

            </div>

        </div>

        <div class="cluster-workspace"
             id="konfigurasi-clustering">

            <div class="card-box process-card">

                <div class="card-header-clean">

                    <div class="header-copy">

                        <span class="eyebrow">
                            K-Means Setup
                        </span>

                        <h4>
                            Konfigurasi Proses
                        </h4>

                        <p>
                            Pilih kelas yang ingin dianalisis sebelum menjalankan proses clustering.
                        </p>

                    </div>

                    <div class="header-icon">
                        <i class="bi bi-diagram-3"></i>
                    </div>

                </div>

                <?php if($process_error !== ''){ ?>

                <div class="alert alert-danger" role="alert">
                    <?= htmlspecialchars($process_error); ?>
                </div>

                <?php } ?>

                <form method="POST" data-progress="clustering">

                    <?= csrf_field(); ?>

                    <div class="form-grid process-form-grid">

                    <div class="field-group field-full">

                        <label class="form-label fw-semibold">
                            Kelas / Jurusan
                        </label>

                        <select name="kelas"
                                class="form-select"

                        onchange="
                        window.location='?kelas='
                        +encodeURIComponent(this.value)
                        +(this.value ? '#konfigurasi-clustering' : '')
                        "
                        required>

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

                    <div class="field-group field-full">

                        <label class="form-label fw-semibold">
                            Tahun Ajaran
                        </label>

                        <div class="form-control bg-light">
                            <?= $tahun_ajaran_selected !== ''
                                ? htmlspecialchars($tahun_ajaran_selected, ENT_QUOTES, 'UTF-8')
                                : 'Belum ditetapkan melalui import leger'; ?>
                        </div>
                        <small class="text-muted">
                            Tahun ajaran mengikuti dataset kelas dan tidak dapat diubah saat clustering.
                        </small>

                    </div>

                    </div>

                    <div class="process-preview-box">

                        <div class="preview-title">
                            <i class="bi bi-sliders"></i>
                            Ringkasan Proses
                        </div>

                        <div class="process-preview">

                        <div class="preview-item d-flex align-items-center">

                            <div class="preview-icon">
                                <i class="bi bi-calendar3"></i>
                            </div>

                            <div>
                                <small>Tahun Ajaran</small>
                                <strong><?= $tahun_ajaran_selected !== ''
                                    ? htmlspecialchars($tahun_ajaran_selected, ENT_QUOTES, 'UTF-8')
                                    : '-'; ?></strong>
                            </div>

                        </div>

                        <div class="preview-item d-flex align-items-center">

                            <div class="preview-icon">
                                <i class="bi bi-collection"></i>
                            </div>

                            <div>
                                <small>Metode Analisis</small>
                                <strong>Keseluruhan Nilai</strong>
                            </div>

                        </div>

                        <div class="preview-item d-flex align-items-center">

                            <div class="preview-icon">
                                <i class="bi bi-person-lines-fill"></i>
                            </div>

                            <div>
                                <small>Data Diproses</small>
                                <strong>
                                    <?= $kelas_selected != '' ? $total_siswa_kelas . ' siswa' : '-'; ?>
                                </strong>
                            </div>

                        </div>

                        <div class="preview-item d-flex align-items-center">

                            <div class="preview-icon">
                                <i class="bi bi-journal-text"></i>
                            </div>

                            <div>
                                <small>Sumber Nilai</small>
                                <strong>
                                    <?= $kelas_selected != '' ? $total_mapel . ' mata pelajaran' : '-'; ?>
                                </strong>
                            </div>

                        </div>

                        </div>

                    </div>

                    <?php if($kelas_selected == ''){ ?>

                    <div class="alert-soft">

                        <i class="bi bi-info-circle"></i>

                        <span>
                            Pilih kelas terlebih dahulu agar tombol proses aktif.
                        </span>

                    </div>

                    <?php } ?>

                    <?php if($kelas_selected !== '' && $tahun_ajaran_selected === ''){ ?>

                    <div class="alert-soft">
                        <i class="bi bi-exclamation-triangle"></i>
                        <span>
                            Periode nilai kelas belum tersedia. Import ulang leger
                            dengan tahun ajaran yang benar terlebih dahulu.
                        </span>
                    </div>

                    <?php } ?>

                    <button type="submit"
                            name="proses"
                            class="btn btn-primary btn-process w-100"

                        <?php
                        if($kelas_selected == '' || $tahun_ajaran_selected === ''){
                            echo "disabled";
                        }
                        ?>
                        >

                        <i class="bi bi-play-circle"></i>

                        Proses K-Means

                    </button>

                </form>

            </div>

            <div class="side-panel">

                <div class="card-box guide-card">

                    <div class="guide-icon">
                        <i class="bi bi-cpu"></i>
                    </div>

                    <h5 class="mb-2">
                        K-Means Clustering
                    </h5>

                    <p class="text-muted">
                        Sistem mengelompokkan siswa berdasarkan kemiripan nilai
                        pada setiap mata pelajaran. Jumlah cluster ditentukan
                        secara otomatis menggunakan Elbow Method dan
                        Silhouette Score.
                    </p>

                    <div class="category-list">

                        <span class="category-pill good">
                            Cluster Dinamis
                        </span>

                        <span class="category-pill ok">
                            Elbow Method
                        </span>

                        <span class="category-pill mid">
                            Silhouette Score
                        </span>

                    </div>

                </div>

                <div class="card-box step-card">

                    <h6>
                        Alur Proses
                    </h6>

                    <div class="step-item">

                        <span>1</span>
                        <p>Pilih kelas yang akan dianalisis.</p>

                    </div>

                    <div class="step-item">

                        <span>2</span>
                        <p>Sistem membaca seluruh nilai mata pelajaran siswa.</p>

                    </div>

                    <div class="step-item">

                        <span>3</span>
                        <p>Jalankan proses, lalu lihat hasil clustering.</p>

                    </div>

                </div>

            </div>

        </div>

    </div>

    <script>
        window.addEventListener('load', function(){
            if(window.location.hash !== '#konfigurasi-clustering'){
                return;
            }

            const konfigurasi =
            document.getElementById('konfigurasi-clustering');

            if(!konfigurasi){
                return;
            }

            setTimeout(function(){
                konfigurasi.scrollIntoView({
                    behavior:'auto',
                    block:'start'
                });
            }, 380);
        });
    </script>

</body>
</html>
