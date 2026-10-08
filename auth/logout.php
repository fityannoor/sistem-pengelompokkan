<?php

if(session_status() !== PHP_SESSION_ACTIVE){
    session_start();
}

if(($_SESSION['login'] ?? false) === true){
    require_once '../koneksi.php';
    require_once '../includes/audit_log.php';
    catat_aktivitas(
        $conn,
        'logout',
        'autentikasi',
        isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null,
        'Logout pengguna'
    );
}

$_SESSION = [];
session_unset();

if(ini_get('session.use_cookies')){

    $params = session_get_cookie_params();

    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

session_destroy();

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
header('Clear-Site-Data: "cache"');

header("Location: login.php");
exit;

?>
