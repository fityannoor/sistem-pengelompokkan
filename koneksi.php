<?php

$database_config_path = __DIR__.'/config/database.ini';
$database_config = @parse_ini_file(
    $database_config_path,
    true,
    INI_SCANNER_RAW
);

if(!is_array($database_config) || !isset($database_config['application'])){
    error_log('Konfigurasi database aplikasi tidak ditemukan atau tidak valid.');
    http_response_code(500);
    exit('Konfigurasi server belum siap. Hubungi administrator.');
}

$app_database = $database_config['application'];

try{
    $conn = mysqli_connect(
        (string) ($app_database['host'] ?? 'localhost'),
        (string) ($app_database['username'] ?? ''),
        (string) ($app_database['password'] ?? ''),
        (string) ($app_database['database'] ?? '')
    );

    if(!$conn){
        throw new RuntimeException(mysqli_connect_error());
    }

    mysqli_set_charset($conn, 'utf8mb4');
}catch(Throwable $exception){
    error_log('Koneksi database gagal: '.$exception->getMessage());
    http_response_code(500);
    exit('Layanan database tidak dapat diakses. Hubungi administrator.');
}
