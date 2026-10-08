
<?php

include '../auth/cek_login.php';
include '../koneksi.php';

$kelas = trim((string) ($_GET['kelas'] ?? ''));

if($kelas === ''){
    header('Location: hasil_cluster.php?export_error=filter_required');
    exit;
}

$kelas_stmt = mysqli_prepare($conn,"
    SELECT 1 FROM siswa WHERE kelas = ? LIMIT 1
");
mysqli_stmt_bind_param($kelas_stmt, 's', $kelas);
mysqli_stmt_execute($kelas_stmt);
mysqli_stmt_store_result($kelas_stmt);
$kelas_valid = mysqli_stmt_num_rows($kelas_stmt) === 1;
mysqli_stmt_close($kelas_stmt);

if(!$kelas_valid){
    header('Location: hasil_cluster.php?export_error=filter_required');
    exit;
}

require '../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$nama_mapel = "Keseluruhan Nilai";


$query_stmt = mysqli_prepare($conn,"
    SELECT
        hasil_cluster.*,
        siswa.nama_siswa,
        siswa.kelas,
        mata_pelajaran.nama_mapel

    FROM hasil_cluster

    JOIN siswa
    ON hasil_cluster.siswa_id =
       siswa.id

    LEFT JOIN mata_pelajaran
    ON hasil_cluster.mapel_id =
       mata_pelajaran.id

    WHERE hasil_cluster.mapel_id IS NULL
      AND siswa.kelas = ?

    ORDER BY siswa.nama_siswa ASC
");
mysqli_stmt_bind_param($query_stmt, 's', $kelas);
mysqli_stmt_execute($query_stmt);
$query = mysqli_stmt_get_result($query_stmt);


$html = '

<style>

body{
    font-family:Arial, sans-serif;
    font-size:12px;
}

.title{
    text-align:center;
    margin-bottom:20px;
}

.title h2{
    margin-bottom:5px;
}

.info{
    margin-bottom:20px;
}

table{
    width:100%;
    border-collapse:collapse;
}

table th{
    background:#2563eb;
    color:white;
    padding:10px;
    border:1px solid #ddd;
}

table td{
    padding:8px;
    border:1px solid #ddd;
}

.badge-high{
    color:green;
    font-weight:bold;
}

.badge-medium{
    color:orange;
    font-weight:bold;
}

.badge-low{
    color:red;
    font-weight:bold;
}

.footer{
    margin-top:30px;
    text-align:right;
}

</style>

<div class="title">

<h2>
LAPORAN HASIL CLUSTERING K-MEANS
</h2>

<h4>
SMKN 4 TANAH GROGOT
</h4>

</div>

<div class="info">

<b>Mata Pelajaran :</b>
'.$nama_mapel.'

<br><br>

<b>Tanggal :</b>
'.date('d F Y').'

</div>

<table>

<tr>

<th>No</th>
<th>Nama Siswa</th>
<th>Kelas</th>
<th>Tahun Ajaran</th>
<th>Cluster</th>
<th>Karakteristik Cluster</th>
<th>Silhouette</th>

</tr>
';

$no = 1;

while($row = mysqli_fetch_assoc($query)){

    $kategori = $row['kategori'];

    if($kategori == "Cluster 1"){

        $warna = "badge-high";

    }elseif($kategori == "Cluster 2"){

        $warna = "badge-medium";

    }else{

        $warna = "badge-low";
    }

$html .= '

<tr>

<td>'.$no++.'</td>

<td>'.htmlspecialchars($row['nama_siswa'], ENT_QUOTES, 'UTF-8').'</td>

<td>'.htmlspecialchars($row['kelas'], ENT_QUOTES, 'UTF-8').'</td>

<td>'.(
    trim((string) ($row['tahun_ajaran'] ?? '')) !== ''
        ? htmlspecialchars($row['tahun_ajaran'], ENT_QUOTES, 'UTF-8')
        : '-'
).'</td>

<td>'.htmlspecialchars($row['cluster'], ENT_QUOTES, 'UTF-8').'</td>

<td class="'.$warna.'">
'.htmlspecialchars($kategori, ENT_QUOTES, 'UTF-8').'
</td>

<td>
'.number_format(
    $row['silhouette_score'],
    3
).'
</td>

</tr>

';
}
mysqli_stmt_close($query_stmt);

$html .= '

</table>

<div class="footer">

Sistem Clustering K-Means Akademik

</div>

';


$options = new Options();

$options->set('isRemoteEnabled', true);

$dompdf = new Dompdf($options);

$dompdf->loadHtml($html);

$dompdf->setPaper('A4', 'landscape');

$dompdf->render();

$dompdf->stream(
    "laporan_clustering.pdf",
    array("Attachment" => true)
);
