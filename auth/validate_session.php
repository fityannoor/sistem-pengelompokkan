<?php

require_once __DIR__.'/../koneksi.php';

function invalidate_current_session(string $status = 'account_invalid'): void {
    $_SESSION = [];

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
    header('Pragma: no-cache');
    header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
    header(
        'Location: /skripsi-kmeans/auth/login.php?'.
        http_build_query(['status' => $status])
    );
    exit;
}

function validate_current_session_user(): void {
    $user_id = filter_var(
        $_SESSION['user_id'] ?? null,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    if($user_id === false || $user_id === null){
        invalidate_current_session();
    }

    global $conn;
    $user_stmt = mysqli_prepare($conn,"
        SELECT id, nama, role
        FROM users
        WHERE id = ?
        LIMIT 1
    ");
    mysqli_stmt_bind_param($user_stmt, 'i', $user_id);
    mysqli_stmt_execute($user_stmt);
    $user = mysqli_fetch_assoc(mysqli_stmt_get_result($user_stmt));
    mysqli_stmt_close($user_stmt);

    if(!$user || !in_array($user['role'], ['admin', 'guru'], true)){
        invalidate_current_session();
    }

    $_SESSION['nama'] = (string) $user['nama'];
    $_SESSION['role'] = (string) $user['role'];
}
