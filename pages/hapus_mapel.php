<?php

include '../auth/cek_admin.php';
include '../koneksi.php';
require_once '../includes/audit_log.php';

if($_SERVER['REQUEST_METHOD'] !== 'POST'){
    header('Location: mata_pelajaran.php');
    exit;
}

$id = filter_input(
    INPUT_POST,
    'id',
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

if($id !== false && $id !== null){
    $delete_stmt = mysqli_prepare(
        $conn,
        'DELETE FROM mata_pelajaran WHERE id = ?'
    );
    mysqli_stmt_bind_param($delete_stmt, 'i', $id);
    mysqli_stmt_execute($delete_stmt);
    $terhapus = mysqli_stmt_affected_rows($delete_stmt) > 0;
    mysqli_stmt_close($delete_stmt);
    if($terhapus){
        catat_aktivitas(
            $conn, 'hapus', 'mata_pelajaran', (int) $id,
            'Menghapus mata pelajaran dan nilai terkait'
        );
    }
}

header('Location: mata_pelajaran.php');
exit;
