<?php

if(session_status() !== PHP_SESSION_ACTIVE){
    session_start();
}

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');

if(
    !isset($_SESSION['login']) ||
    $_SESSION['login'] !== true
){
    header("Location: /skripsi-kmeans/auth/login.php");
    exit;
}

require_once __DIR__.'/validate_session.php';
validate_current_session_user();

if(($_SESSION['role'] ?? '') !== 'guru'){
    header("Location: ../dashboard.php");
    exit;
}

require_once __DIR__.'/../includes/csrf.php';
csrf_protect_post_request();
?>
