<?php

function csrf_token(): string {
    if(empty($_SESSION['csrf_token'])){
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="'.
        htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8').'">';
}

function csrf_protect_post_request(): void {
    if($_SERVER['REQUEST_METHOD'] !== 'POST'){
        return;
    }

    $token = (string) ($_POST['csrf_token'] ?? '');

    if($token === '' || !hash_equals(csrf_token(), $token)){
        http_response_code(403);
        exit('Permintaan tidak valid atau sesi telah berakhir. Silakan kembali dan coba lagi.');
    }
}
