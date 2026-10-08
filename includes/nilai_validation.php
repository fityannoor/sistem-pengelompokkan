<?php

function nilai_akademik_valid($value): bool {
    $value = trim((string) $value);

    if($value === ''){
        return true;
    }

    if(!preg_match('/^(?:100(?:\.0{1,2})?|\d{1,2}(?:\.\d{1,2})?)$/', $value)){
        return false;
    }

    $angka = (float) $value;
    return $angka >= 0 && $angka <= 100;
}
