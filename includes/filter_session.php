<?php

function reset_persistent_filters_if_requested(
    array $session_keys,
    string $redirect_path
): void {
    if(($_GET['reset_filter'] ?? '') !== '1'){
        return;
    }

    foreach($session_keys as $session_key){
        unset($_SESSION[$session_key]);
    }

    header('Location: '.$redirect_path);
    exit;
}

function persistent_filter_value(
    string $session_key,
    string $parameter,
    array $valid_values,
    string $default = ''
): string {
    $valid_values = array_map('strval', $valid_values);
    $stored = isset($_SESSION[$session_key])
        ? trim((string) $_SESSION[$session_key])
        : '';

    if(array_key_exists($parameter, $_GET)){
        $requested = trim((string) $_GET[$parameter]);

        if($requested !== '' && in_array($requested, $valid_values, true)){
            $_SESSION[$session_key] = $requested;
            return $requested;
        }
    }

    if($stored !== '' && in_array($stored, $valid_values, true)){
        return $stored;
    }

    unset($_SESSION[$session_key]);
    return $default;
}

function ensure_persistent_filter_url(
    string $path,
    array $filters,
    array $preserve_parameters = []
): void {
    $expected = [];

    foreach($filters as $name => $value){
        $value = trim((string) $value);
        if($value !== ''){
            $expected[$name] = $value;
        }
    }

    if(!$expected){
        return;
    }

    $matches = true;
    foreach($expected as $name => $value){
        if(!isset($_GET[$name]) || trim((string) $_GET[$name]) !== $value){
            $matches = false;
            break;
        }
    }

    if($matches){
        return;
    }

    $query = $expected;

    foreach($preserve_parameters as $name){
        if(isset($_GET[$name]) && trim((string) $_GET[$name]) !== ''){
            $query[$name] = trim((string) $_GET[$name]);
        }
    }

    header('Location: '.$path.'?'.http_build_query($query));
    exit;
}
