<?php

include '../auth/cek_login.php';
include '../koneksi.php';
include '../includes/filter_session.php';

reset_persistent_filters_if_requested(
    ['filter_hasil_cluster_kelas', 'filter_hasil_cluster_sort'],
    'hasil_cluster.php'
);

$active = 'hasil';
$is_admin = ($_SESSION['role'] ?? '') === 'admin';

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
    ORDER BY kelas ASC
");
$kelas_valid = [];
while($item = mysqli_fetch_assoc($kelas_data)){
    $kelas_valid[] = $item['kelas'];
}
mysqli_data_seek($kelas_data, 0);

$kelas = persistent_filter_value(
    'filter_hasil_cluster_kelas', 'kelas', $kelas_valid
);
$sort = persistent_filter_value(
    'filter_hasil_cluster_sort',
    'sort',
    ['cluster_asc', 'nilai_desc', 'nilai_asc', 'nama_asc', 'nama_desc'],
    'cluster_asc'
);
if($kelas !== ''){
    ensure_persistent_filter_url(
        'hasil_cluster.php',
        ['kelas' => $kelas, 'sort' => $sort],
        ['toast', 'export_error']
    );
}

$where = [
    "hasil_cluster.mapel_id IS NULL"
];

function elbow_slug($value){

    $value =
    strtolower($value);

    $value =
    preg_replace('/[^a-z0-9]+/', '_', $value);

    $value =
    trim($value, '_');

    return $value != '' ? $value : 'kelas';
}

if($kelas != ''){

    $kelas =
    mysqli_real_escape_string(
        $conn,
        $kelas
    );

    $where[] =
    "siswa.kelas='$kelas'";
}

$where_sql = "";

if(count($where) > 0){

    $where_sql =
    "WHERE ".implode(" AND ", $where);
}
$order_by =
"ORDER BY
    hasil_cluster.cluster ASC,
    siswa.nama_siswa ASC";


if($sort == 'nilai_desc'){

    $order_by =
    "ORDER BY rata_rata DESC";
}

elseif($sort == 'nilai_asc'){

    $order_by =
    "ORDER BY rata_rata ASC";
}


elseif($sort == 'nama_asc'){

    $order_by =
    "ORDER BY siswa.nama_siswa ASC";
}

elseif($sort == 'nama_desc'){

    $order_by =
    "ORDER BY siswa.nama_siswa DESC";
}

$silhouette = mysqli_query($conn,"
    SELECT
        hasil_cluster.silhouette_score

    FROM hasil_cluster

    JOIN siswa
    ON hasil_cluster.siswa_id =
       siswa.id

    $where_sql

    ORDER BY hasil_cluster.id DESC

    LIMIT 1
");

$last_cluster =
mysqli_fetch_assoc($silhouette);
$show_data = false;

if($kelas != ''){

    $show_data = true;
}
$query = null;
$total_data = 0;
$evaluasi_query = null;
$total_evaluasi = 0;
$best_evaluasi = null;
$jenis_evaluasi = 'keseluruhan';

if($show_data){

    $query = mysqli_query($conn,"
        SELECT
            hasil_cluster.*,
            siswa.nama_siswa,
            siswa.kelas,
            mata_pelajaran.nama_mapel,

            CASE

            WHEN hasil_cluster.mapel_id IS NULL
            THEN AVG(nilai_detail.nilai)

            ELSE MAX(
                CASE
                WHEN nilai_detail.mapel_id =
                    hasil_cluster.mapel_id
                THEN nilai_detail.nilai
                END
            )

            END as rata_rata

        FROM hasil_cluster

        JOIN siswa
        ON hasil_cluster.siswa_id =
           siswa.id

        LEFT JOIN mata_pelajaran
        ON hasil_cluster.mapel_id =
           mata_pelajaran.id

        JOIN nilai_detail
        ON siswa.id =
           nilai_detail.siswa_id

        $where_sql

        GROUP BY hasil_cluster.id

        $order_by
    ");

    if($query){

        $total_data =
        mysqli_num_rows($query);
    }
}

if($is_admin && $kelas != ''){

    $evaluasi_query =
    mysqli_query($conn,"
        SELECT
            k,
            inertia,
            silhouette_score

        FROM evaluasi_cluster

        WHERE kelas='$kelas'
        AND jenis='$jenis_evaluasi'

        ORDER BY k ASC
    ");

    if($evaluasi_query){

        $total_evaluasi =
        mysqli_num_rows($evaluasi_query);
    }

    $best_evaluasi_query =
    mysqli_query($conn,"
        SELECT
            k,
            inertia,
            silhouette_score

        FROM evaluasi_cluster

        WHERE kelas='$kelas'
        AND jenis='$jenis_evaluasi'

        ORDER BY silhouette_score DESC

        LIMIT 1
    ");

    if($best_evaluasi_query){

        $best_evaluasi =
        mysqli_fetch_assoc($best_evaluasi_query);
    }
}

$analisis_cluster = [];
$profil_cluster_mapel = [];
$profil_cluster_data = [];
$ringkasan_perhitungan = null;
$detail_perhitungan = [];
$perhitungan_fitur = [];
$minimum_fitur = [];
$maksimum_fitur = [];
$centroid_normal = [];
$centroid_asli = [];

if($kelas != ''){

    $query_analisis = mysqli_query($conn,"
        SELECT *
        FROM analisis_cluster
        WHERE kelas='$kelas'
        AND jenis='keseluruhan'
        ORDER BY
            CAST(REPLACE(cluster, 'Cluster ', '') AS UNSIGNED) ASC
    ");

    if($query_analisis){

        while($row = mysqli_fetch_assoc($query_analisis)){

            $analisis_cluster[] = $row;
        }
    }

    $query_profil_cluster = mysqli_query($conn,"
        SELECT
            hasil_cluster.kategori AS cluster_label,
            mata_pelajaran.nama_mapel,
            AVG(nilai_detail.nilai) AS rata_rata

        FROM hasil_cluster

        JOIN siswa
        ON hasil_cluster.siswa_id =
           siswa.id

        JOIN nilai_detail
        ON siswa.id =
           nilai_detail.siswa_id

        JOIN mata_pelajaran
        ON nilai_detail.mapel_id =
           mata_pelajaran.id

        WHERE siswa.kelas='$kelas'
        AND hasil_cluster.mapel_id IS NULL

        GROUP BY
            hasil_cluster.kategori,
            mata_pelajaran.id,
            mata_pelajaran.nama_mapel

        ORDER BY
            CAST(REPLACE(hasil_cluster.kategori, 'Cluster ', '') AS UNSIGNED) ASC,
            mata_pelajaran.nama_mapel ASC
    ");

    if($query_profil_cluster){

        while($row =
        mysqli_fetch_assoc($query_profil_cluster)){

            $cluster_label =
            $row['cluster_label'];

            $nama_mapel =
            $row['nama_mapel'];

            if(!in_array($nama_mapel, $profil_cluster_mapel)){

                $profil_cluster_mapel[] =
                $nama_mapel;
            }

            if(!isset($profil_cluster_data[$cluster_label])){

                $profil_cluster_data[$cluster_label] =
                [];
            }

            $profil_cluster_data[$cluster_label][$nama_mapel] =
            $row['rata_rata'];
        }
    }

    $tabel_ringkasan_ada = mysqli_query(
        $conn,
        "SHOW TABLES LIKE 'ringkasan_perhitungan_kmeans'"
    );
    $tabel_detail_ada = mysqli_query(
        $conn,
        "SHOW TABLES LIKE 'detail_perhitungan_kmeans'"
    );

    if(
        $tabel_ringkasan_ada &&
        mysqli_num_rows($tabel_ringkasan_ada) > 0 &&
        $tabel_detail_ada &&
        mysqli_num_rows($tabel_detail_ada) > 0
    ){
        $query_ringkasan = mysqli_query($conn,"\n            SELECT *\n            FROM ringkasan_perhitungan_kmeans\n            WHERE kelas='$kelas'\n            AND jenis='keseluruhan'\n            LIMIT 1\n        ");

        if($query_ringkasan){
            $ringkasan_perhitungan =
            mysqli_fetch_assoc($query_ringkasan);
        }

        if($ringkasan_perhitungan){
            $perhitungan_fitur = json_decode(
                $ringkasan_perhitungan['fitur_json'],
                true
            ) ?: [];
            $minimum_fitur = json_decode(
                $ringkasan_perhitungan['minimum_json'],
                true
            ) ?: [];
            $maksimum_fitur = json_decode(
                $ringkasan_perhitungan['maksimum_json'],
                true
            ) ?: [];
            $centroid_normal = json_decode(
                $ringkasan_perhitungan['centroid_normal_json'],
                true
            ) ?: [];
            $centroid_asli = json_decode(
                $ringkasan_perhitungan['centroid_asli_json'],
                true
            ) ?: [];

            $query_detail_perhitungan = mysqli_query($conn,"\n                SELECT\n                    detail_perhitungan_kmeans.*,\n                    siswa.nama_siswa\n                FROM detail_perhitungan_kmeans\n                JOIN siswa\n                ON detail_perhitungan_kmeans.siswa_id = siswa.id\n                WHERE detail_perhitungan_kmeans.kelas='$kelas'\n                AND detail_perhitungan_kmeans.jenis='keseluruhan'\n                ORDER BY\n                    CAST(REPLACE(\n                        detail_perhitungan_kmeans.cluster_terpilih,\n                        'Cluster ',\n                        ''\n                    ) AS UNSIGNED) ASC,\n                    siswa.nama_siswa ASC\n            ");

            if($query_detail_perhitungan){
                while($detail = mysqli_fetch_assoc(
                    $query_detail_perhitungan
                )){
                    $detail['nilai_asli'] = json_decode(
                        $detail['nilai_asli_json'],
                        true
                    ) ?: [];
                    $detail['nilai_normalisasi'] = json_decode(
                        $detail['nilai_normalisasi_json'],
                        true
                    ) ?: [];
                    $detail['jarak_centroid'] = json_decode(
                        $detail['jarak_centroid_json'],
                        true
                    ) ?: [];
                    $detail_perhitungan[] = $detail;
                }
            }
        }
    }
}
$elbow_src = '';
$elbow_path = '';

if($is_admin && $kelas != ''){

    $kelas_slug =
    elbow_slug($kelas);

    $elbow_file =
    'elbow_method_keseluruhan_' .
    $kelas_slug . '.svg';

    $elbow_path =
    __DIR__ . '/../assets/img/' .
    $elbow_file;

    if(file_exists($elbow_path) && $total_evaluasi > 0 && $total_data > 0){

        $elbow_src =
        '../assets/img/' .
        $elbow_file .
        '?' . time();
    }
}


?>

<!DOCTYPE html>
<html lang="id">
<head>

    <meta charset="UTF-8">

    <meta name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>Hasil Clustering</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">

    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="../assets/hasil_cluster.css?v=15">


</head>
<body>


        <?php include '../includes/sidebar.php'; ?>



    <div class="content">

        <div class="topbar">
            <a href="<?= $show_data
                ? 'export_pdf.php?kelas='.rawurlencode($kelas)
                : '#'; ?>"
                class="btn btn-danger"
                id="exportPdfButton"
                download="laporan_clustering.pdf"
                data-filter-active="<?= $show_data ? '1' : '0'; ?>">

                <i class="bi bi-file-earmark-pdf"></i>

                Export PDF

            </a>

            <div class="topbar-title">

                <h3 class="page-title mb-0">
                    Hasil Clustering K-Means
                </h3>

                <small class="text-muted">
                    Analisis Pengelompokan Siswa Berdasarkan Pola Nilai Akademik
                </small>

            </div>

            

        </div>
        
        <div class="card-box mb-4">

            <form method="GET"
                  data-filter-required="kelas"
                  data-filter-message="Silakan pilih kelas terlebih dahulu sebelum melakukan filter.">

                <div class="row g-3 align-items-end">
                    <div class="col-md-3">

                        <label class="form-label">

                        Urutkan Data

                        </label>

                        <select name="sort"
                                class="form-select">

                        <option value="cluster_asc"
                        <?= $sort === 'cluster_asc' ? 'selected' : ''; ?>>
                        Cluster (1&ndash;Terakhir)
                        </option>

                        <option value="nilai_desc"

                        <?php
                        if($sort ==
                        'nilai_desc'){

                            echo "selected";
                        }
                        ?>

                        >

                        Nilai Tertinggi

                        </option>

                        <option value="nilai_asc"

                        <?php
                        if($sort ==
                        'nilai_asc'){

                            echo "selected";
                        }
                        ?>

                        >

                        Nilai Terendah

                        </option>

                        <option value="nama_asc"

                        <?php
                        if($sort ==
                        'nama_asc'){

                            echo "selected";
                        }
                        ?>

                        >

                        Nama A - Z

                        </option>

                        <option value="nama_desc"

                        <?php
                        if($sort ==
                        'nama_desc'){

                            echo "selected";
                        }
                        ?>

                        >

                        Nama Z - A

                        </option>

                        </select>

                    </div>
                    <div class="col-md-4">

                        <label class="form-label fw-semibold">

                            Filter Kelas

                        </label>

                        <select name="kelas"
                                class="form-select">

                                <option value="">
                                    Semua Kelas
                                </option>

                                <?php
                                    while($k =
                                    mysqli_fetch_assoc($kelas_data)){
                                ?>

                                    <option value="<?= htmlspecialchars($k['kelas'], ENT_QUOTES, 'UTF-8'); ?>"

                                <?php
                                    if($kelas == $k['kelas']){

                                        echo "selected";
                                    }
                                ?>

                                >

                                <?= htmlspecialchars($k['kelas'], ENT_QUOTES, 'UTF-8'); ?>

                                    </option>

                                <?php } ?>

                        </select>


                    </div>
                    <div class="col-md-2 d-flex">

                        <button
                            type="submit"
                            class="btn btn-primary px-4">

                        <i class="bi bi-funnel"></i>

                        Filter

                        </button>

                    </div>

                </div>

            </form>

        </div>

        <?php if($is_admin){ ?>

            <div class="card mt-3 mb-4">

                <div class="card-header">
                    Grafik Elbow Method
                </div>

                <div class="card-body text-center">

                    <?php if($elbow_src != ''){ ?>

                    <img
                        src="<?= $elbow_src; ?>"
                        class="img-fluid"
                    >

                    <?php }else{ ?>

                    <div class="text-muted py-4">
                        Grafik elbow belum tersedia untuk filter ini.
                        Silakan proses clustering sesuai kelas yang dipilih.
                    </div>

                    <?php } ?>

                </div>

            </div>

        <?php if($kelas != ''){ ?>

        <div class="card-box mb-4">

            <div class="d-flex justify-content-between align-items-center mb-3">

                <div>

                    <h5 class="mb-1">
                        Evaluasi Jumlah Cluster
                    </h5>

                    <small class="text-muted">
                    Kandidat jumlah cluster diperoleh dari Elbow Method, 
                    kemudian dievaluasi menggunakan Silhouette Score 
                    untuk menentukan jumlah cluster optimal.          
                    </small>

                </div>

                <span class="badge bg-primary p-3">
                    K Terbaik :
                    <?= $best_evaluasi ? $best_evaluasi['k'] : '-'; ?>
                </span>

            </div>

            <?php if($total_evaluasi > 0){ ?>

            <div class="table-responsive">

                <table class="table table-hover align-middle">

                    <thead class="table-primary">

                        <tr>
                            <th>K</th>
                            <th>Inertia</th>
                            <th>Silhouette Score</th>
                            <th>Status</th>
                        </tr>

                    </thead>

                    <tbody>

                        <?php while($eval =
                        mysqli_fetch_assoc($evaluasi_query)){ ?>

                        <tr>
                            <td>
                                <?= $eval['k']; ?>
                            </td>

                            <td>
                                <?= number_format($eval['inertia'], 6); ?>
                            </td>

                            <td>
                                <?= number_format($eval['silhouette_score'], 6); ?>
                            </td>

                            <td>
                                <?php if($best_evaluasi &&
                                $eval['k'] == $best_evaluasi['k']){ ?>

                                <span class="badge bg-success px-3 py-2">
                                    K Terbaik
                                </span>

                                <?php }else{ ?>

                                <span class="badge bg-secondary px-3 py-2">
                                    Pembanding
                                </span>

                                <?php } ?>
                            </td>
                        </tr>

                        <?php } ?>

                    </tbody>

                </table>

            </div>

            <?php }else{ ?>

            <div class="text-muted py-3">
                Data evaluasi cluster belum tersedia untuk kelas ini.
                Silakan proses clustering sesuai kelas yang dipilih terlebih dahulu.
            </div>

            <?php } ?>

        </div>

        <?php } ?>
        <?php } ?>
        <?php if(!empty($profil_cluster_data)){ ?>

        <div class="card-box mb-4">
            <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3 mb-3">

                <div>

                <h5 class="mb-1">
                    Profil Cluster
                </h5>

                <small class="text-muted">
                    Rata-rata nilai tiap mata pelajaran berdasarkan cluster
                </small>

                </div>

                <span class="badge bg-primary p-3">

                Silhouette Score :
                <?php

                if($show_data &&
                $last_cluster){

                    echo number_format(
                        $last_cluster['silhouette_score'],
                        3
                    );

                }else{

                    echo "-";
                }

                ?>

                </span>

            </div>

            <div class="table-responsive profile-table-wrapper">

                <table class="table table-hover align-middle profile-cluster-table">

                    <thead class="table-primary">

                        <tr>
                            <th>Cluster</th>

                            <?php foreach($profil_cluster_mapel as $nama_mapel){ ?>

                            <th>
                                <?= htmlspecialchars($nama_mapel); ?>
                            </th>

                            <?php } ?>

                        </tr>

                    </thead>

                    <tbody>

                        <?php foreach($profil_cluster_data as $cluster_label => $nilai_mapel){ ?>

                        <tr>
                            <td>
                                <span class="cluster-pill">
                                    <i class="bi bi-diagram-3"></i>
                                    <?= htmlspecialchars($cluster_label); ?>
                                </span>
                            </td>

                            <?php foreach($profil_cluster_mapel as $nama_mapel){ ?>

                            <td>
                                <?php if(isset($nilai_mapel[$nama_mapel])){ ?>

                                <span class="score-pill">
                                    <?= number_format($nilai_mapel[$nama_mapel], 2); ?>
                                </span>

                                <?php }else{ ?>

                                <span class="text-muted">-</span>

                                <?php } ?>
                            </td>

                            <?php } ?>
                        </tr>

                        <?php } ?>

                    </tbody>

                </table>

            </div>

        </div>

        <?php } ?>
        <?php if(!empty($analisis_cluster)){ ?>

            <div class="card-box mb-4">

                <div class="mb-3">
                    <h5 class="mb-1">
                        Interpretasi Cluster
                    </h5>

                    <small class="text-muted">
                        Ringkasan pola nilai akademik berdasarkan hasil pengelompokan siswa
                    </small>
                </div>

                <div class="table-responsive">

                    <table class="table table-hover align-middle">

                        <thead class="table-primary">

                            <tr>
                                <th>Cluster</th>
                                <th>Interpretasi</th>
                            </tr>

                        </thead>

                        <tbody>

                            <?php foreach($analisis_cluster as $data){ ?>

                            <tr>
                                <td>
                                    <strong>
                                        <?= htmlspecialchars($data['cluster']); ?>
                                    </strong>
                                </td>

                                <td>
                                    <?= htmlspecialchars($data['interpretasi']); ?>
                                </td>
                            </tr>

                            <?php } ?>

                        </tbody>

                    </table>

                </div>

            </div>

        <?php } ?>

        <?php if($ringkasan_perhitungan && !empty($detail_perhitungan)){ ?>

        <div class="card-box mb-4 calculation-card">
            <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-3">
                <div>
                    <h5 class="mb-1">
                        Detail Perhitungan K-Means
                    </h5>
                    <small class="text-muted">
                        Jejak angka dari model final untuk membuktikan proses normalisasi,
                        pembentukan centroid, dan pemilihan cluster terdekat.
                    </small>
                </div>

                <div class="calculation-meta">
                    <span class="badge bg-primary">
                        K = <?= count($centroid_normal); ?>
                    </span>
                    <span class="badge bg-secondary">
                        <?= (int) $ringkasan_perhitungan['jumlah_iterasi']; ?> Iterasi
                    </span>
                </div>
            </div>

            <div class="formula-grid mb-4">
                <div class="formula-box">
                    <strong>1. Normalisasi Min-Max</strong>
                    <code>x' = (x - min) / (max - min)</code>
                </div>
                <div class="formula-box">
                    <strong>2. Jarak Euclidean</strong>
                    <code>d(x,c) = √Σ(x<sub>j</sub> - c<sub>j</sub>)²</code>
                </div>
                <div class="formula-box">
                    <strong>3. Pemilihan Cluster</strong>
                    <code>cluster(x) = arg min d(x,c<sub>k</sub>)</code>
                </div>
            </div>

            <details class="calculation-section" open>
                <summary>
                    <span>
                        <i class="bi bi-bullseye"></i>
                        Centroid Akhir
                    </span>
                    <small>nilai normalisasi dan nilai asli</small>
                </summary>

                <div class="table-responsive calculation-table-wrapper">
                    <table class="table table-hover align-middle calculation-table centroid-table">
                        <thead class="table-primary">
                            <tr>
                                <th>Cluster</th>
                                <?php foreach($perhitungan_fitur as $fitur_nama){ ?>
                                <th><?= htmlspecialchars($fitur_nama, ENT_QUOTES, 'UTF-8'); ?></th>
                                <?php } ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($centroid_normal as $index => $centroid){ ?>
                            <tr>
                                <td>
                                    <span class="cluster-pill">
                                        Cluster <?= $index + 1; ?>
                                    </span>
                                </td>
                                <?php foreach($perhitungan_fitur as $fitur_nama){ ?>
                                <td>
                                    <strong class="calculation-value">
                                        <?= number_format((float) ($centroid[$fitur_nama] ?? 0), 4); ?>
                                    </strong>
                                    <small class="original-value">
                                        Nilai asli:
                                        <?= number_format((float) ($centroid_asli[$index][$fitur_nama] ?? 0), 2); ?>
                                    </small>
                                </td>
                                <?php } ?>
                            </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </details>

            <details class="calculation-section">
                <summary>
                    <span>
                        <i class="bi bi-sliders"></i>
                        Data Normalisasi
                    </span>
                    <small>nilai asli diubah ke rentang 0–1</small>
                </summary>

                <div class="normalization-range">
                    <?php foreach($perhitungan_fitur as $fitur_nama){ ?>
                    <div>
                        <strong><?= htmlspecialchars($fitur_nama, ENT_QUOTES, 'UTF-8'); ?></strong>
                        <span>
                            min <?= number_format((float) ($minimum_fitur[$fitur_nama] ?? 0), 2); ?>
                            &middot;
                            max <?= number_format((float) ($maksimum_fitur[$fitur_nama] ?? 0), 2); ?>
                        </span>
                    </div>
                    <?php } ?>
                </div>

                <p class="normalization-note">
                    Jika nilai minimum sama dengan maksimum, seluruh nilai pada
                    fitur tersebut menjadi 0 karena tidak memiliki variasi.
                </p>

                <div class="table-responsive calculation-table-wrapper">
                    <table class="table table-hover align-middle calculation-table normalized-table">
                        <thead class="table-primary">
                            <tr>
                                <th>Nama Siswa</th>
                                <?php foreach($perhitungan_fitur as $fitur_nama){ ?>
                                <th><?= htmlspecialchars($fitur_nama, ENT_QUOTES, 'UTF-8'); ?></th>
                                <?php } ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($detail_perhitungan as $detail){ ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($detail['nama_siswa'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                </td>
                                <?php foreach($perhitungan_fitur as $fitur_nama){ ?>
                                <td>
                                    <strong class="calculation-value">
                                        <?= number_format((float) ($detail['nilai_normalisasi'][$fitur_nama] ?? 0), 4); ?>
                                    </strong>
                                    <small class="original-value">
                                        Dari <?= number_format((float) ($detail['nilai_asli'][$fitur_nama] ?? 0), 2); ?>
                                    </small>
                                </td>
                                <?php } ?>
                            </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </details>

            <details class="calculation-section" open>
                <summary>
                    <span>
                        <i class="bi bi-rulers"></i>
                        Jarak Siswa ke Centroid
                    </span>
                    <small>jarak paling kecil menentukan cluster</small>
                </summary>

                <div class="table-responsive calculation-table-wrapper">
                    <table class="table table-hover align-middle calculation-table distance-table">
                        <thead class="table-primary">
                            <tr>
                                <th>Nama Siswa</th>
                                <?php for($cluster_no = 1; $cluster_no <= count($centroid_normal); $cluster_no++){ ?>
                                <th>Jarak C<?= $cluster_no; ?></th>
                                <?php } ?>
                                <th>Jarak Minimum</th>
                                <th>Cluster Terpilih</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($detail_perhitungan as $detail){ ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($detail['nama_siswa'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                </td>
                                <?php for($cluster_no = 1; $cluster_no <= count($centroid_normal); $cluster_no++){
                                    $cluster_label = 'Cluster '.$cluster_no;
                                    $is_selected = $detail['cluster_terpilih'] === $cluster_label;
                                ?>
                                <td class="<?= $is_selected ? 'distance-selected' : ''; ?>">
                                    <?= number_format((float) ($detail['jarak_centroid'][$cluster_label] ?? 0), 6); ?>
                                </td>
                                <?php } ?>
                                <td>
                                    <strong class="minimum-distance">
                                        <?= number_format((float) $detail['jarak_terdekat'], 6); ?>
                                    </strong>
                                </td>
                                <td>
                                    <span class="cluster-pill">
                                        <i class="bi bi-check-circle"></i>
                                        <?= htmlspecialchars($detail['cluster_terpilih'], ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </details>
        </div>

        <?php } ?>
        <?php if(
            !$ringkasan_perhitungan &&
            $show_data &&
            $total_data > 0
        ){ ?>

        <div class="card-box mb-4 calculation-empty">
            <i class="bi bi-calculator"></i>
            <div>
                <strong>Detail perhitungan belum tersedia</strong>
                <span>
                    Jalankan ulang proses clustering kelas ini untuk membuat data
                    centroid, normalisasi, dan jarak Euclidean.
                </span>
            </div>
        </div>

        <?php } ?>

        <div class="card-box">
            
            
        <div class="table-responsive result-scroll-wrapper"
             id="resultScrollWrapper">

        <table class="table table-hover align-middle result-table">

        <thead class="table-primary">

        <tr>

        <th class="text-center">No</th>
        <th>Nama Siswa</th>
        <th>Kelas</th>
        <th>Tahun Ajaran</th>
        <th>Jenis Nilai</th>
        <th>Cluster</th>
        <th>Nilai Rata-rata</th>
        <th>Iterasi</th>
        </tr>

        </thead>

        <tbody>
        <?php if($show_data && $total_data > 0){ ?>
        <?php $no = 1;

        while($row = mysqli_fetch_assoc($query)){

            $kategori = $row['kategori'];
        ?>

        <tr>

        <td class="text-center">
            <span class="row-number">
                <?= $no++; ?>
            </span>
        </td>

        <td>

            <form method="POST"
                  action="pilih_detail_siswa.php"
                  class="d-inline">

                <?= csrf_field(); ?>

                <input type="hidden"
                       name="siswa_id"
                       value="<?= (int) $row['siswa_id']; ?>">

                <input type="hidden"
                       name="kelas"
                       value="<?= htmlspecialchars($kelas); ?>">

                <input type="hidden"
                       name="sort"
                       value="<?= htmlspecialchars($sort); ?>">

                <button type="submit" class="student-link">
                    <?= htmlspecialchars($row['nama_siswa']); ?>
                </button>

            </form>

        </td>

        <td>

            <span class="class-pill">

                <?= htmlspecialchars($row['kelas'], ENT_QUOTES, 'UTF-8'); ?>

            </span>

        </td>
        <td>
            <?= trim((string) ($row['tahun_ajaran'] ?? '')) !== ''
                ? htmlspecialchars($row['tahun_ajaran'], ENT_QUOTES, 'UTF-8')
                : '-'; ?>
        </td>
        <td>

            Keseluruhan Nilai

        </td>
        <?php

            $kategori = $row['kategori'];


        ?>

        <td>

            <span class="cluster-pill">
                <i class="bi bi-diagram-3"></i>
                <?= htmlspecialchars($kategori, ENT_QUOTES, 'UTF-8'); ?>
            </span>

        </td>
        <td>

            <span class="score-pill">
                <?= number_format(
                $row['rata_rata'],
                2
                ); ?>
            </span>

        </td>
        

        <td>

            <div class="iterasi-box">

                    <i class="bi bi-arrow-repeat"></i>

                    <?= $row['iterasi']; ?>

                    Iterasi

                </div>

        </td>
        </tr>

        <?php } ?>
        <?php }elseif($show_data &&
               $total_data == 0){ ?>

        <tr>

        <td colspan="8"
        class="empty-animate"
        style="
        text-align:center;
        padding:30px;
        font-weight:600;
        color:#64748b;
        ">

        Silakan lakukan proses clustering
        terlebih dahulu

        </td>

        </tr>
        <?php }else{ ?>
        <tr>

        <td colspan="8"
        class="empty-animate"
        style="text-align:center;
        padding:30px;">

        Silakan pilih filter kelas terlebih dahulu

        </td>

        </tr>

        <?php } ?>
        

        </tbody>

        </table>

        </div>

        </div>

    </div>

    <script>
        <?php if(($_GET['export_error'] ?? '') === 'filter_required'){ ?>
        alert(
            'Silakan pilih dan terapkan filter kelas terlebih dahulu sebelum export PDF.'
        );
        <?php } ?>

        const exportPdfButton =
        document.getElementById('exportPdfButton');

        if(exportPdfButton){
            exportPdfButton.addEventListener(
                'click',
                function(event){
                    if(this.dataset.filterActive !== '1'){
                        event.preventDefault();
                        alert(
                            'Silakan pilih dan terapkan filter kelas terlebih dahulu sebelum export PDF.'
                        );
                    }
                }
            );
        }

        (function(){
            const wrapper =
            document.getElementById(
                'resultScrollWrapper'
            );

            if(!wrapper){
                return;
            }

            const table =
            wrapper.querySelector(
                '.result-table'
            );

            if(!table){
                return;
            }

            const floating =
            document.createElement('div');

            floating.className =
            'floating-x-scroll';

            const inner =
            document.createElement('div');

            inner.className =
            'floating-x-scroll-inner';

            floating.appendChild(inner);
            document.body.appendChild(floating);

            let syncing = false;

            function updateFloatingScroll(){
                const rect =
                wrapper.getBoundingClientRect();

                const hasOverflow =
                wrapper.scrollWidth >
                wrapper.clientWidth + 2;

                const isVisible =
                rect.top < window.innerHeight - 80 &&
                rect.bottom > 120 &&
                hasOverflow &&
                rect.bottom > window.innerHeight - 24;

                floating.classList.toggle(
                    'is-visible',
                    isVisible
                );

                if(!isVisible){
                    return;
                }

                const left =
                Math.max(rect.left, 16);

                const width =
                Math.min(
                    rect.width,
                    window.innerWidth - left - 16
                );

                floating.style.left =
                left + 'px';

                floating.style.width =
                width + 'px';

                inner.style.width =
                wrapper.scrollWidth + 'px';

                if(!syncing){
                    floating.scrollLeft =
                    wrapper.scrollLeft;
                }
            }

            wrapper.addEventListener(
                'scroll',
                function(){
                    if(syncing){
                        return;
                    }

                    syncing = true;
                    floating.scrollLeft =
                    wrapper.scrollLeft;
                    syncing = false;
                }
            );

            floating.addEventListener(
                'scroll',
                function(){
                    if(syncing){
                        return;
                    }

                    syncing = true;
                    wrapper.scrollLeft =
                    floating.scrollLeft;
                    syncing = false;
                }
            );

            window.addEventListener(
                'scroll',
                updateFloatingScroll
            );

            window.addEventListener(
                'resize',
                updateFloatingScroll
            );

            updateFloatingScroll();
        })();
    </script>

</body>
</html>
