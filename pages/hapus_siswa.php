<?php
include '../auth/cek_admin.php';
include '../koneksi.php';
require_once '../includes/audit_log.php';

if($_SERVER['REQUEST_METHOD'] !== 'POST'){

    header("Location: data_siswa.php");
    exit;
}

$id = filter_input(
    INPUT_POST,
    'id',
    FILTER_VALIDATE_INT,
    ["options" => ["min_range" => 1]]
);

$kelas_aktif = trim($_POST['kelas'] ?? '');

$redirect_params = [];

if($kelas_aktif !== ''){
    $redirect_params['kelas'] = $kelas_aktif;
}

if($id !== false && $id !== null){

    $delete_stmt = mysqli_prepare(
        $conn,
        "DELETE FROM siswa WHERE id = ?"
    );

    mysqli_stmt_bind_param($delete_stmt, "i", $id);
    mysqli_stmt_execute($delete_stmt);

    if(mysqli_stmt_affected_rows($delete_stmt) > 0){
        $redirect_params['toast'] = 'Data siswa berhasil dihapus';
        catat_aktivitas(
            $conn, 'hapus', 'siswa', (int) $id,
            'Menghapus siswa dan data terkait'
        );
    }

    mysqli_stmt_close($delete_stmt);
}

$redirect_query = http_build_query($redirect_params);

header(
    "Location: data_siswa.php".
    ($redirect_query !== '' ? '?'.$redirect_query : '')
);
exit;

?>
