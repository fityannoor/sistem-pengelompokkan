<?php
include 'auth/cek_login.php';
include 'koneksi.php';
include 'includes/filter_session.php';

reset_persistent_filters_if_requested(
    ['filter_dashboard_kelas'],
    'dashboard.php'
);

$active = 'dashboard';

$dashboard_link = 'dashboard.php';
$data_siswa_link = 'pages/data_siswa.php';
$mapel_link = 'pages/mata_pelajaran.php';
$nilai_link = 'pages/input_nilai.php';
$cluster_link = 'pages/clustering.php';
$hasil_link = 'pages/hasil_cluster.php';
$user_link = 'pages/users.php';

$logout_link = 'auth/logout.php';
$logo_path = 'assets/img/logo_smk_4.png';

$kelas_valid_query = mysqli_query($conn,"
    SELECT DISTINCT kelas FROM siswa
    WHERE kelas IS NOT NULL AND kelas != ''
    ORDER BY kelas ASC
");
$kelas_valid = [];
while($item = mysqli_fetch_assoc($kelas_valid_query)){
    $kelas_valid[] = $item['kelas'];
}

$kelas_dashboard = persistent_filter_value(
    'filter_dashboard_kelas', 'kelas', $kelas_valid
);
ensure_persistent_filter_url(
    'dashboard.php',
    ['kelas' => $kelas_dashboard],
    ['toast']
);
$kelas_dashboard = mysqli_real_escape_string($conn, $kelas_dashboard);

$show_dashboard_data =
$kelas_dashboard != '';
?>
<?php



$total_siswa = mysqli_num_rows(
    mysqli_query($conn,
        "SELECT * FROM siswa")
);


$total_mapel = mysqli_num_rows(
    mysqli_query($conn,
        "SELECT * FROM mata_pelajaran")
);



$last_cluster = null;

if($show_dashboard_data){

    $cluster_terakhir = mysqli_query($conn,"
        SELECT
            hasil_cluster.*,
            mata_pelajaran.nama_mapel,
            siswa.kelas

        FROM hasil_cluster

        LEFT JOIN mata_pelajaran
        ON hasil_cluster.mapel_id =
        mata_pelajaran.id

        JOIN siswa
        ON hasil_cluster.siswa_id =
           siswa.id

        WHERE hasil_cluster.mapel_id IS NULL
        AND siswa.kelas='$kelas_dashboard'

        ORDER BY hasil_cluster.id DESC

        LIMIT 1
    ");

    $last_cluster =
    mysqli_fetch_assoc(
        $cluster_terakhir
    );
}

$tanggal = '-';

if(isset($last_cluster['created_at'])){

    $tanggal = date(
        'd F Y H:i',
        strtotime(
            $last_cluster['created_at']
        )
    );
}



$kelas_last = '';

if($show_dashboard_data){

    $kelas_last =
    $kelas_dashboard;
}

$kelas_last_sql =
mysqli_real_escape_string(
    $conn,
    $kelas_last
);

$cluster_counts = [];
$cluster_labels = [];
$cluster_values = [];
$total_cluster_aktif = 0;

if($show_dashboard_data &&
$kelas_last_sql != ''){

    $q_cluster_counts =
    mysqli_query($conn,"
        SELECT
            hasil_cluster.kategori,
            hasil_cluster.cluster,
            COUNT(*) AS total

        FROM hasil_cluster

        JOIN siswa
        ON hasil_cluster.siswa_id =
           siswa.id

        WHERE hasil_cluster.mapel_id IS NULL
        AND siswa.kelas =
        '$kelas_last_sql'

        GROUP BY
            hasil_cluster.kategori,
            hasil_cluster.cluster

        ORDER BY hasil_cluster.kategori ASC
    ");

    while($cluster_row =
    mysqli_fetch_assoc($q_cluster_counts)){

        $cluster_label =
        $cluster_row['kategori'] != ''
        ? $cluster_row['kategori']
        : $cluster_row['cluster'];

        $cluster_counts[] = [
            'label' => $cluster_label,
            'total' => (int) $cluster_row['total']
        ];

        $cluster_labels[] =
        $cluster_label;

        $cluster_values[] =
        (int) $cluster_row['total'];

        $total_cluster_aktif +=
        (int) $cluster_row['total'];
    }
}

$total_cluster_jenis =
count($cluster_counts);




$total_kelas = mysqli_num_rows(
    mysqli_query($conn,"
        SELECT DISTINCT kelas
        FROM siswa
    ")
);



$data_kelas = mysqli_query($conn,"
    SELECT DISTINCT kelas
    FROM siswa

    WHERE kelas IS NOT NULL
    AND kelas != ''

    ORDER BY kelas ASC
");


$kelas_overview = mysqli_query($conn,"
    SELECT
        siswa.kelas,
        siswa.jurusan,
        COUNT(DISTINCT siswa.id) AS total_siswa,
        (
            SELECT COUNT(*)
            FROM mata_pelajaran
            WHERE
            mata_pelajaran.jurusan = siswa.jurusan
            OR mata_pelajaran.jurusan = 'Umum'
        ) AS total_mapel,
        COUNT(DISTINCT hasil_cluster.siswa_id) AS total_clustered,
        MAX(hasil_cluster.created_at) AS last_processed

    FROM siswa

    LEFT JOIN hasil_cluster
    ON hasil_cluster.siswa_id = siswa.id
    AND hasil_cluster.mapel_id IS NULL

    WHERE siswa.kelas IS NOT NULL
    AND siswa.kelas != ''

    GROUP BY
        siswa.kelas,
        siswa.jurusan

    ORDER BY siswa.kelas ASC
");

$total_siswa_kelas = 0;
$total_mapel_kelas = 0;

if($show_dashboard_data){


    $total_siswa_kelas =
    mysqli_num_rows(
        mysqli_query($conn,"
            SELECT *
            FROM siswa

            WHERE kelas='$kelas_dashboard'
        ")
    );


    $q_jurusan = mysqli_query($conn,"
        SELECT jurusan
        FROM siswa

        WHERE kelas='$kelas_dashboard'

        LIMIT 1
    ");

    $d_jurusan =
    mysqli_fetch_assoc($q_jurusan);

    $jurusan =
    $d_jurusan['jurusan'];


    $q_total_mapel_stmt = mysqli_prepare($conn,"
        SELECT COUNT(*) AS total FROM mata_pelajaran
        WHERE jurusan = ? OR jurusan = 'Umum'
    ");
    mysqli_stmt_bind_param($q_total_mapel_stmt, 's', $jurusan);
    mysqli_stmt_execute($q_total_mapel_stmt);
    $d_total_mapel = mysqli_fetch_assoc(
        mysqli_stmt_get_result($q_total_mapel_stmt)
    );
    mysqli_stmt_close($q_total_mapel_stmt);
    $total_mapel_kelas = (int) ($d_total_mapel['total'] ?? 0);
}


$ranking = null;

if($show_dashboard_data){

    $ranking = mysqli_query($conn,"
        SELECT
            siswa.nama_siswa,
            siswa.kelas,
            AVG(nilai_detail.nilai) as rata_rata

        FROM hasil_cluster

        JOIN siswa
        ON hasil_cluster.siswa_id =
           siswa.id

        JOIN nilai_detail
        ON siswa.id =
           nilai_detail.siswa_id

        WHERE
        hasil_cluster.mapel_id IS NULL

        AND siswa.kelas =
        '$kelas_last'

        AND nilai_detail.nilai IS NOT NULL

        GROUP BY siswa.id

        ORDER BY rata_rata DESC

        LIMIT 5
    ");
}



?>


<!DOCTYPE html>
<html lang="id">
<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Dashboard</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<link rel="preconnect" href="https://fonts.googleapis.com">

<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet"href="assets/dashboard.css?v=3">  


</head>
<body>


    <?php include 'includes/sidebar.php'; ?>


    <div class="content">

        <div class="topbar">

            <div>

                <h3 class="mb-0">
                Dashboard
                </h3>

                <small class="text-muted">
                Sistem Analisis Pola Nilai Akademik Siswa
                </small>

            </div>

            <div class="d-flex align-items-center gap-2">

                <span class="badge
                    <?= $_SESSION['role']=='admin'
                        ? 'bg-danger'
                        : 'bg-success'; ?>
                        fs-6 px-3 py-2">

                    <?= strtoupper($_SESSION['role']); ?>

                </span>

                <a href="dashboard.php<?= $kelas_dashboard != '' ? '?kelas=' . urlencode($kelas_dashboard) : ''; ?>"
                   class="btn btn-primary rounded-pill px-4">

                    <i class="bi bi-arrow-clockwise"></i>

                    Refresh

                </a>

            </div>

        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-4">

                <div class="card-box">

                    <small>Total Kelas</small>

                    <h3>
                        <?= $total_kelas; ?>
                    </h3>

                </div>

            </div>
            <div class="col-md-4">

                <div class="card-box">

                    <div class="stat-title">
                    Total Siswa
                    </div>

                    <h3>
                        <?= $total_siswa; ?>
                    </h3>


                </div>

            </div>

            <div class="col-md-4">

                <div class="card-box">

                    <div class="stat-title">
                    Total Mapel
                    </div>

                    <h3>
                    <?= $total_mapel; ?>
                    </h3>

                </div>

            </div>

        </div>
        <div class="card-box mb-4">

            <form method="GET"
                  data-filter-required="kelas"
                  data-filter-message="Silakan pilih kelas terlebih dahulu sebelum melakukan filter.">

                <div class="row align-items-end">

                    <div class="col-md-4">

                        <label class="form-label fw-semibold">

                        Filter Dashboard Kelas

                        </label>

                        <select name="kelas"
                                class="form-select">

                            <option value="">
                            -- Pilih Kelas --
                            </option>

                            <?php
                            while($k =
                            mysqli_fetch_assoc($data_kelas)){
                            ?>

                            <option value="<?= htmlspecialchars($k['kelas'], ENT_QUOTES, 'UTF-8'); ?>"

                            <?php
                            if($kelas_dashboard ==
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

                    <div class="col-md-2">

                        <button type="submit"
                                class="btn btn-primary w-100">

                        <i class="bi bi-funnel"></i>

                        Filter

                        </button>

                    </div>

                </div>

            </form>

        </div>
        <?php if(!$show_dashboard_data){ ?>

        <div class="card-box mb-4">

            <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">

                <div>

                    <h5 class="fw-bold mb-1">
                        <i class="bi bi-list-check text-primary"></i>
                        Daftar Kelas Tersedia
                    </h5>

                    <small class="text-muted">
                        Pilih salah satu kelas untuk menampilkan data clustering, ranking siswa, dan grafik distribusi.
                    </small>

                </div>

                <span class="badge bg-primary px-3 py-2">
                    <?= $total_kelas; ?> Kelas
                </span>

            </div>

            <div class="table-responsive">

                <table class="table table-hover align-middle class-overview-table">

                    <thead class="table-primary">

                        <tr>
                            <th>Kelas</th>
                            <th>Jurusan</th>
                            <th>Jumlah Siswa</th>
                            <th>Jumlah Mapel</th>
                            <th>Status Clustering</th>
                            <th>Terakhir Diproses</th>
                            <th>Aksi</th>
                        </tr>

                    </thead>

                    <tbody>

                    <?php while($kelas_row =
                    mysqli_fetch_assoc($kelas_overview)){ ?>

                        <?php
                            $kelas_row_name =
                            $kelas_row['kelas'];

                            $is_processed =
                            (int) $kelas_row['total_clustered'] > 0;

                            $last_processed =
                            $kelas_row['last_processed'] != ''
                            ? date(
                                'd F Y H:i',
                                strtotime($kelas_row['last_processed'])
                            )
                            : '-';
                        ?>

                        <tr>
                            <td>
                                <strong>
                                    <?= htmlspecialchars($kelas_row_name); ?>
                                </strong>
                            </td>

                            <td>
                                <?= htmlspecialchars($kelas_row['jurusan']); ?>
                            </td>

                            <td>
                                <?= $kelas_row['total_siswa']; ?> siswa
                            </td>

                            <td>
                                <?= $kelas_row['total_mapel']; ?> mapel
                            </td>

                            <td>
                                <?php if($is_processed){ ?>

                                <span class="status-badge status-done">
                                    <i class="bi bi-check-circle"></i>
                                    Sudah diproses
                                </span>

                                <?php }else{ ?>

                                <span class="status-badge status-empty">
                                    <i class="bi bi-exclamation-circle"></i>
                                    Belum diproses
                                </span>

                                <?php } ?>
                            </td>

                            <td>
                                <?= $last_processed; ?>
                            </td>

                            <td>
                                <a href="dashboard.php?kelas=<?= urlencode($kelas_row_name); ?>"
                                   class="btn btn-sm btn-primary">
                                    Pilih
                                </a>
                            </td>
                        </tr>

                    <?php } ?>

                    </tbody>

                </table>

            </div>

        </div>

        <?php }else{ ?>

        <div class="card-box mb-4">

            <h5 class="fw-bold mb-3">

                <i class="bi bi-mortarboard-fill text-primary"></i>

                Informasi Kelas

            </h5>

            <hr>

            <div class="row">

                <div class="col-md-4">

                    <small class="text-muted">

                        Kelas Aktif

                    </small>

                    <h5 class="fw-bold">

<?= htmlspecialchars($kelas_dashboard, ENT_QUOTES, 'UTF-8'); ?>

                    </h5>

                </div>

                <div class="col-md-4">

                    <small class="text-muted">

                    Jumlah Siswa

                    </small>

                    <h5 class="fw-bold text-success">

                        <?= $total_siswa_kelas; ?>

                    </h5>

                </div>

                <div class="col-md-4">

                    <small class="text-muted">

                        Jumlah Mapel

                    </small>

                    <h5 class="fw-bold text-primary">

                        <?= $total_mapel_kelas; ?>

                    </h5>

                </div>

            </div>

        </div>

        <div class="card-box mb-4">

            <div class="d-flex justify-content-between align-items-center flex-wrap">

                <div>

                    <h5 class="fw-bold mb-1">

                    <i class="bi bi-cpu"></i>

                    Clustering Aktif

                    </h5>

                    <div class="text-muted">
                        <?php

                            if($total_cluster_aktif > 0){

                                echo $total_cluster_aktif .
                                " data siswa diproses";

                            }else{

                                echo "Belum ada data clustering";
                            }

                        ?>
                        <br>

                        Jenis Nilai:

                        <b class="text-primary">
                            Keseluruhan Nilai

                        </b>
                    </div>

                    <small>
                        Kelas:
                        <b>
<?= htmlspecialchars($kelas_dashboard, ENT_QUOTES, 'UTF-8'); ?>
                    </small>
                    <br>
                    <small class="text-muted">

                        Diproses pada:


                    </b>
                    <?= $tanggal; ?>

                    <br><br>

                        Sistem sedang menampilkan hasil clustering
                        berdasarkan keseluruhan nilai siswa.

                    </small>

                </div>



            </div>

        </div>

        <div class="row g-3 mb-4">

            <?php if(count($cluster_counts) > 0){ ?>

                <?php foreach($cluster_counts as $index => $cluster_item){ ?>

                <div class="col-md-3">

                    <div class="card-box cluster-summary-card cluster-tone-<?= ($index % 6) + 1; ?>">

<h5><?= htmlspecialchars($cluster_item['label'], ENT_QUOTES, 'UTF-8'); ?></h5>

                        <h1><?= $cluster_item['total']; ?></h1>

                        <small>
                            Jumlah siswa dalam pola nilai akademik cluster ini
                        </small>

                    </div>

                </div>

                <?php } ?>

            <?php }else{ ?>

            <div class="col-12">

                <div class="card-box text-muted">
                    Belum ada distribusi cluster untuk kelas yang dipilih.
                </div>

            </div>

            <?php } ?>

        </div>

        <div class="card-box mb-4">

            <div class="d-flex justify-content-between align-items-center mb-4">

                <div>

                    <h4 class="fw-bold">

                        <i class="bi bi-trophy"></i>

                        Ranking Siswa Terbaik

                    </h4>

                    <small class="text-muted">

                        Berdasarkan Keseluruhan Nilai

                    </small>

                </div>

                <div class="badge bg-warning text-dark px-4 py-3"
                    style="border-radius:14px;">

                Top 5 Student

                </div>

            </div>

            <?php

                $rank = 1;

                while($r = mysqli_fetch_assoc($ranking)){

            ?>

            <div class="ranking-item">

                <div class="d-flex align-items-center gap-3">

                    <div class="rank-badge">

                        <?= $rank++; ?>

                    </div>

                    <div class="rank-name">

                        <?= htmlspecialchars($r['nama_siswa'], ENT_QUOTES, 'UTF-8'); ?>

                    </div>

                </div>

                <div class="rank-score">

                    <?= number_format($r['rata_rata'],2); ?>

                </div>

            </div>

                <?php } ?>

                <?php if($rank == 1){ ?>

                <div class="text-muted">
                    Belum ada data ranking untuk kelas ini.
                </div>

                <?php } ?>

            </div>



            <div class="card-box mb-4">

            <h5 class="mb-3">

                <i class="bi bi-clock-history"></i>

                Clustering Terakhir

            </h5>

            <hr>

            <?php if($last_cluster){ ?>

            <div class="row g-4">

                <div class="col-md-4">

                    <small class="text-muted">

                        Jenis Nilai

                    </small>

                    <h6 class="fw-bold">

                            Keseluruhan Nilai

                    </h6>

                </div>

                <div class="col-md-4">

                    <small class="text-muted">

                        Kelas

                    </small>

                    <h6 class="fw-bold">

                        <?= $last_cluster['kelas'] ?? '-'; ?>

                    </h6>

                </div>

                <div class="col-md-2">

                    <small class="text-muted">

                        Total Cluster

                    </small>

                    <h6 class="fw-bold text-primary">

                        <?= $total_cluster_jenis; ?>

                    </h6>

                </div>

                <div class="col-md-2">

                    <small class="text-muted">

                        Silhouette

                    </small>

                    <h6 class="fw-bold text-success">

                        <?= round(
                            $last_cluster['silhouette_score'],
                            3
                        ); ?>

                    </h6>

                </div>

                <div class="col-md-2">

                    <small class="text-muted">

                        Tanggal

                    </small>

                    <h6 class="fw-bold">

                        <?= $tanggal; ?>

                    </h6>

                </div>

            </div>

            <?php }else{ ?>

            <div class="alert alert-warning mb-0">

                Belum ada proses clustering dilakukan.

            </div>

            <?php } ?>

        </div>

        <div class="card-box chart-card">

            <h5 class="fw-bold">

                <i class="bi bi-pie-chart-fill"></i>

                Distribusi Cluster

            </h5>

            <hr>

            <div class="chart-container">

                <canvas id="clusterChart"></canvas>

            </div>

        </div>

    <script>

    const ctx =
    document.getElementById('clusterChart');

    new Chart(ctx, {

        type: 'doughnut',

        data: {

            labels:
            <?= json_encode($cluster_labels); ?>,

            datasets: [{

                label: 'Jumlah Siswa',

                data:
                <?= json_encode($cluster_values); ?>,
                backgroundColor: [
                    '#22c55e',
                    '#3b82f6',
                    '#f59e0b',
                    '#ef4444',
                    '#8b5cf6',
                    '#14b8a6',

                ],

                borderWidth:0,

                borderWidth: 1

            }]
        },

        options: {

            responsive: true,

            maintainAspectRatio: false,

            animation: {
                duration: 1200,
                easing: 'easeOutQuart'
            }

        }

    });

    </script>

    <?php } ?>

    </div>

</body>
</html>
