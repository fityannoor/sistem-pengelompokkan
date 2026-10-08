<?php

include '../auth/cek_admin.php';
include '../koneksi.php';

if($_SERVER['REQUEST_METHOD'] !== 'POST'){

    header("Location: data_siswa.php");
    exit;
}

$id = filter_input(
    INPUT_POST,
    'siswa_id',
    FILTER_VALIDATE_INT,
    ["options" => ["min_range" => 1]]
);

if($id === false || $id === null){

    unset($_SESSION['edit_siswa_id']);
    header("Location: data_siswa.php");
    exit;
}

$siswa_stmt = mysqli_prepare(
    $conn,
    "SELECT id FROM siswa WHERE id = ?"
);

mysqli_stmt_bind_param($siswa_stmt, "i", $id);
mysqli_stmt_execute($siswa_stmt);
mysqli_stmt_store_result($siswa_stmt);

$siswa_tersedia = mysqli_stmt_num_rows($siswa_stmt) === 1;

mysqli_stmt_close($siswa_stmt);

if(!$siswa_tersedia){

    unset($_SESSION['edit_siswa_id']);
    header("Location: data_siswa.php");
    exit;
}

$_SESSION['edit_siswa_id'] = $id;

$kelas_kembali = trim($_POST['kelas_kembali'] ?? '');

if($kelas_kembali !== ''){
    $_SESSION['edit_siswa_kelas_kembali'] = $kelas_kembali;
}else{
    unset($_SESSION['edit_siswa_kelas_kembali']);
}

header("Location: edit_siswa.php");
exit;
