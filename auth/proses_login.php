<?php

if(session_status() !== PHP_SESSION_ACTIVE){
    session_start();
}

include '../koneksi.php';
require_once '../includes/audit_log.php';

if($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['login'])){
    header('Location: login.php');
    exit;
}

$username = trim((string) ($_POST['username'] ?? ''));
$password = (string) ($_POST['password'] ?? '');

$login_gagal = static function(): void {
    $_SESSION['login_error'] = 'Username atau password salah.';
    header('Location: login.php');
    exit;
};

if($username === '' || $password === ''){
    $login_gagal();
}

$user_stmt = mysqli_prepare($conn, "
    SELECT id, nama, username, password, role
    FROM users
    WHERE username = ?
    LIMIT 1
");

mysqli_stmt_bind_param($user_stmt, 's', $username);
mysqli_stmt_execute($user_stmt);
$user = mysqli_fetch_assoc(mysqli_stmt_get_result($user_stmt));
mysqli_stmt_close($user_stmt);

if(!$user || !in_array($user['role'], ['admin', 'guru'], true)){
    $login_gagal();
}

$hash_tersimpan = (string) $user['password'];
$hash_md5_lama = preg_match('/^[a-f0-9]{32}$/i', $hash_tersimpan) === 1;

if($hash_md5_lama){
    $password_valid = hash_equals(
        strtolower($hash_tersimpan),
        md5($password)
    );
}else{
    $password_valid = password_verify($password, $hash_tersimpan);
}

if(!$password_valid){
    $login_gagal();
}

if($hash_md5_lama || password_needs_rehash($hash_tersimpan, PASSWORD_DEFAULT)){
    $hash_baru = password_hash($password, PASSWORD_DEFAULT);
    $user_id = (int) $user['id'];
    $update_stmt = mysqli_prepare(
        $conn,
        'UPDATE users SET password = ? WHERE id = ?'
    );
    mysqli_stmt_bind_param($update_stmt, 'si', $hash_baru, $user_id);
    mysqli_stmt_execute($update_stmt);
    mysqli_stmt_close($update_stmt);
}

session_regenerate_id(true);

$_SESSION['login'] = true;
$_SESSION['user_id'] = (int) $user['id'];
$_SESSION['nama'] = $user['nama'];
$_SESSION['role'] = $user['role'];

unset($_SESSION['login_error']);

catat_aktivitas(
    $conn,
    'login',
    'autentikasi',
    (int) $user['id'],
    'Login berhasil'
);

header('Location: ../dashboard.php');
exit;
