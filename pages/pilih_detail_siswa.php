<?php

include '../auth/cek_login.php';
include '../koneksi.php';

if($_SERVER['REQUEST_METHOD'] !== 'POST'){
    header("Location: hasil_cluster.php");
    exit;
}

$id = filter_input(
    INPUT_POST,
    'siswa_id',
    FILTER_VALIDATE_INT,
    ["options" => ["min_range" => 1]]
);

if($id === false || $id === null){
    header("Location: hasil_cluster.php");
    exit;
}

$siswa_stmt = mysqli_prepare($conn,"
    SELECT kelas FROM siswa WHERE id = ? LIMIT 1
");
mysqli_stmt_bind_param($siswa_stmt, "i", $id);
mysqli_stmt_execute($siswa_stmt);
$siswa_result = mysqli_stmt_get_result($siswa_stmt);
$siswa = mysqli_fetch_assoc($siswa_result);
mysqli_stmt_close($siswa_stmt);

if(!$siswa){
    header("Location: hasil_cluster.php");
    exit;
}

$kelas = trim($_POST['kelas'] ?? '');

if($kelas === '' || $kelas !== $siswa['kelas']){
    $kelas = $siswa['kelas'];
}

$sort = $_POST['sort'] ?? '';
$sort_diizinkan = [
    'nilai_desc',
    'nilai_asc',
    'nama_asc',
    'nama_desc'
];

if(!in_array($sort, $sort_diizinkan, true)){
    $sort = '';
}

$_SESSION['detail_siswa_id'] = $id;
$_SESSION['detail_siswa_kelas'] = $kelas;
$_SESSION['detail_siswa_sort'] = $sort;

header("Location: detail_siswa.php");
exit;

