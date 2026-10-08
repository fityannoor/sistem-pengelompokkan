<?php

include '../auth/cek_login.php';
include '../koneksi.php';

if(!isset($_SESSION['detail_siswa_id'])){
    header("Location: hasil_cluster.php");
    exit;
}

$id = filter_var(
    $_SESSION['detail_siswa_id'],
    FILTER_VALIDATE_INT,
    ["options" => ["min_range" => 1]]
);

if($id === false){
    unset(
        $_SESSION['detail_siswa_id'],
        $_SESSION['detail_siswa_kelas'],
        $_SESSION['detail_siswa_sort']
    );
    header("Location: hasil_cluster.php");
    exit;
}

if(!empty($_SERVER['QUERY_STRING'])){
    header("Location: detail_siswa.php");
    exit;
}

$kelas_kembali = trim($_SESSION['detail_siswa_kelas'] ?? '');
$sort_kembali = $_SESSION['detail_siswa_sort'] ?? '';

$parameter_kembali = [];

if($kelas_kembali !== ''){
    $parameter_kembali['kelas'] = $kelas_kembali;
}

if($sort_kembali !== ''){
    $parameter_kembali['sort'] = $sort_kembali;
}

$url_kembali = 'hasil_cluster.php'.(
    $parameter_kembali
        ? '?'.http_build_query($parameter_kembali)
        : ''
);

$siswa_stmt = mysqli_prepare($conn,"
    SELECT * FROM siswa WHERE id = ? LIMIT 1
");
mysqli_stmt_bind_param($siswa_stmt, "i", $id);
mysqli_stmt_execute($siswa_stmt);
$siswa_result = mysqli_stmt_get_result($siswa_stmt);
$siswa = mysqli_fetch_assoc($siswa_result);
mysqli_stmt_close($siswa_stmt);

if(!$siswa){
    unset(
        $_SESSION['detail_siswa_id'],
        $_SESSION['detail_siswa_kelas'],
        $_SESSION['detail_siswa_sort']
    );
    header("Location: ".$url_kembali);
    exit;
}

$cluster_stmt = mysqli_prepare($conn,"
    SELECT * FROM hasil_cluster
    WHERE siswa_id = ? AND mapel_id IS NULL
    ORDER BY id DESC
    LIMIT 1
");
mysqli_stmt_bind_param($cluster_stmt, 'i', $id);
mysqli_stmt_execute($cluster_stmt);
$cluster = mysqli_fetch_assoc(mysqli_stmt_get_result($cluster_stmt));
mysqli_stmt_close($cluster_stmt);
if(!$cluster){

    $cluster = [
        'kategori' => 'Belum Clustering',
        'catatan' => 'Silakan lakukan proses clustering terlebih dahulu',
        'iterasi' => '-',
        'silhouette_score' => '-',
        'tahun_ajaran' => '-'
    ];
}

$nilai_stmt = mysqli_prepare($conn,"
    SELECT
        mata_pelajaran.nama_mapel,
        nilai_detail.nilai

    FROM nilai_detail

    JOIN mata_pelajaran
    ON nilai_detail.mapel_id =
       mata_pelajaran.id

    WHERE nilai_detail.siswa_id = ?

    ORDER BY mata_pelajaran.nama_mapel ASC
");
mysqli_stmt_bind_param($nilai_stmt, 'i', $id);
mysqli_stmt_execute($nilai_stmt);
$nilai_result = mysqli_stmt_get_result($nilai_stmt);
$nilai_rows = [];
while($nilai_row = mysqli_fetch_assoc($nilai_result)){
    $nilai_rows[] = $nilai_row;
}
mysqli_stmt_close($nilai_stmt);

$catatan_individu = '';
if($nilai_rows){
    $nilai_urut = $nilai_rows;
    usort($nilai_urut, static function(array $a, array $b): int {
        return (float) $b['nilai'] <=> (float) $a['nilai'];
    });

    $nilai_tertinggi = array_slice($nilai_urut, 0, 2);
    $nilai_terendah = array_slice(array_reverse($nilai_urut), 0, 2);
    $format_mapel = static function(array $item): string {
        return $item['nama_mapel'].' ('.number_format((float) $item['nilai'], 2).')';
    };

    $catatan_individu =
        'Keunggulan berdasarkan nilai tertinggi terdapat pada '.
        implode(', ', array_map($format_mapel, $nilai_tertinggi)).
        '. Mata pelajaran yang paling perlu ditingkatkan adalah '.
        implode(', ', array_map($format_mapel, $nilai_terendah)).'.';
}
?>

<!DOCTYPE html>
<html lang="id">
<head>

<meta charset="UTF-8">

<meta name="viewport"
content="width=device-width, initial-scale=1.0">

<title>Detail Siswa</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
<link rel="stylesheet" href="../assets/detail_siswa.css">


</head>

<body>

<?php include '../includes/loading_screen.php'; ?>

<div class="container-custom">

    <div class="card-box mb-4">

        <h3><?= htmlspecialchars($siswa['nama_siswa'], ENT_QUOTES, 'UTF-8'); ?></h3>

        <hr>

        <div class="row">

            <div class="col-md-4">

                <b>NIS</b><br>

                <?= htmlspecialchars($siswa['nis'], ENT_QUOTES, 'UTF-8'); ?>

            </div>

            <div class="col-md-4">

                <b>Kelas</b><br>

                <?= htmlspecialchars($siswa['kelas'], ENT_QUOTES, 'UTF-8'); ?>

            </div>

            <div class="col-md-4">

                <b>Jurusan</b><br>

                <?= htmlspecialchars($siswa['jurusan'], ENT_QUOTES, 'UTF-8'); ?>

            </div>

        </div>

    </div>
    <div class="card-box mb-4">

        <h4>Pola Nilai Akademik</h4>

        <hr>

        <table class="table">

            <tr>

                <td width="250">

                    Cluster

                </td>

                <td>

                    <b>

                        <?php

                            $badge = "secondary";

                            if($cluster['kategori']=="Sangat Baik"){

                                $badge="success";

                            }elseif($cluster['kategori']=="Baik"){

                                $badge="primary";

                            }elseif($cluster['kategori']=="Cukup"){

                                $badge="warning";

                            }elseif($cluster['kategori']=="Perlu Pendampingan"){

                                $badge="danger";
                            }

                        ?>

                        <span class="badge bg-<?= $badge; ?> px-3 py-2">

                            <?= htmlspecialchars($cluster['kategori'], ENT_QUOTES, 'UTF-8'); ?>

                        </span>

                    </b>

                </td>

            </tr>

            <tr>

                <td>

                    Catatan

                </td>

                <td>

                    <?= $catatan_individu !== ''
                        ? htmlspecialchars($catatan_individu, ENT_QUOTES, 'UTF-8')
                        : 'Nilai mata pelajaran belum tersedia.'; ?>

                </td>

            </tr>

            <?php if($_SESSION['role'] == 'admin'){ ?>

                <tr>

                    <td>
                        Iterasi K-Means
                    </td>

                    <td>
                        <?= $cluster['iterasi']; ?>
                    </td>

                </tr>

                <tr>

                    <td>
                        Silhouette Score
                    </td>

                    <td>
                        <?= $cluster['silhouette_score']; ?>
                    </td>

                </tr>

            <?php } ?>

            <tr>

                <td>

                    Tahun Ajaran

                </td>

                <td>

                    <?= trim((string) ($cluster['tahun_ajaran'] ?? '')) !== ''
                        ? htmlspecialchars($cluster['tahun_ajaran'], ENT_QUOTES, 'UTF-8')
                        : '-'; ?>

                </td>

            </tr>

        </table>

    </div>
    <div class="card-box mb-4">

        <h4>Nilai Mata Pelajaran</h4>

        <hr>

    <table class="table table-hover">

        <thead>

            <tr>

                <th>No</th>

                <th>Mata Pelajaran</th>

                <th>Nilai</th>

            </tr>

        </thead>

        <tbody>

            <?php

            $no = 1;

            foreach($nilai_rows as $row){

            ?>

            <tr>

            <td>

            <?= $no++; ?>

            </td>

            <td>

            <?= htmlspecialchars($row['nama_mapel'], ENT_QUOTES, 'UTF-8'); ?>

            </td>

            <td>

            <?= number_format($row['nilai'],2); ?>

            </td>

            </tr>

            <?php } ?>

        </tbody>

    </table>

    </div>
        <a href="<?= htmlspecialchars($url_kembali); ?>"
        class="btn btn-primary rounded-pill px-4">

            <i class="bi bi-arrow-left"></i>

            Kembali

        </a>

    </div>

</body>
</html>
