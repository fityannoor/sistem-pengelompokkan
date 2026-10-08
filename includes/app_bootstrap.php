<?php

if(defined('SKRIPSI_APP_BOOTSTRAPPED')){
    return;
}
define('SKRIPSI_APP_BOOTSTRAPPED', true);

date_default_timezone_set('Asia/Makassar');

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');
ini_set(
    'error_log',
    rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR).
    DIRECTORY_SEPARATOR.'skripsi-kmeans-php-error.log'
);

ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');

if(!headers_sent() && PHP_SAPI !== 'cli'){
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
}

set_exception_handler(static function(Throwable $exception): void {
    error_log(
        'Uncaught '.get_class($exception).': '.$exception->getMessage().
        ' in '.$exception->getFile().':'.$exception->getLine().PHP_EOL.
        $exception->getTraceAsString()
    );

    if(PHP_SAPI === 'cli'){
        fwrite(STDERR, "Terjadi kesalahan internal aplikasi.\n");
        return;
    }

    http_response_code(500);
    if(!headers_sent()){
        header('Content-Type: text/html; charset=UTF-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    }

    echo '<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8">'.
        '<meta name="viewport" content="width=device-width, initial-scale=1.0">'.
        '<title>Kesalahan Sistem</title></head>'.
        '<body style="font-family:Arial,sans-serif;background:#f4f7fb;'.
        'display:flex;align-items:center;justify-content:center;min-height:100vh;'.
        'margin:0"><main style="background:#fff;padding:32px;border-radius:16px;'.
        'max-width:560px;box-shadow:0 12px 35px rgba(0,0,0,.08)">'.
        '<h2>Terjadi kesalahan pada sistem</h2>'.
        '<p>Permintaan tidak dapat diproses. Silakan coba kembali atau hubungi administrator.</p>'.
        '<a href="/skripsi-kmeans/dashboard.php">Kembali ke Dashboard</a>'.
        '</main></body></html>';
});
