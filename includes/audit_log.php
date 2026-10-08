<?php

function catat_aktivitas(
    mysqli $conn,
    string $aksi,
    string $entitas,
    ?int $entitas_id = null,
    string $keterangan = ''
): void {
    $user_id = isset($_SESSION['user_id'])
        ? (int) $_SESSION['user_id']
        : null;
    $nama = trim((string) ($_SESSION['nama'] ?? 'Sistem'));
    $role = trim((string) ($_SESSION['role'] ?? ''));
    $ip = substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
    $user_agent = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
    $aksi = substr(trim($aksi), 0, 50);
    $entitas = substr(trim($entitas), 0, 50);
    $keterangan = substr(trim($keterangan), 0, 500);

    try{
        $stmt = mysqli_prepare($conn,"
            INSERT INTO aktivitas_log
                (user_id, nama_pengguna, role_pengguna, aksi, entitas,
                 entitas_id, keterangan, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        mysqli_stmt_bind_param(
            $stmt,
            'issssisss',
            $user_id,
            $nama,
            $role,
            $aksi,
            $entitas,
            $entitas_id,
            $keterangan,
            $ip,
            $user_agent
        );
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }catch(Throwable $exception){
        error_log('Audit log gagal: '.$exception->getMessage());
    }
}

function format_nilai_audit($nilai): string {
    $hasil = number_format((float) $nilai, 2, '.', '');
    return rtrim(rtrim($hasil, '0'), '.');
}

function catat_perubahan_nilai(
    mysqli $conn,
    int $siswa_id,
    array $siswa,
    array $mapel_by_id,
    array $nilai_lama,
    array $nilai_baru
): void {
    $nama_siswa = trim((string) ($siswa['nama_siswa'] ?? ''));
    $nis = trim((string) ($siswa['nis'] ?? ''));
    $kelas = trim((string) ($siswa['kelas'] ?? ''));

    foreach($nilai_baru as $mapel_id => $nilai){
        $mapel_id = (int) $mapel_id;
        if(!isset($mapel_by_id[$mapel_id]) ||
           (!is_scalar($nilai) && $nilai !== null)){
            continue;
        }

        $nama_mapel = trim((string) $mapel_by_id[$mapel_id]['nama_mapel']);
        $nilai_input = trim((string) $nilai);
        $lama_tersedia = array_key_exists($mapel_id, $nilai_lama);
        $nilai_sebelumnya = $lama_tersedia
            ? (float) $nilai_lama[$mapel_id]
            : null;

        if($nilai_input === ''){
            if($lama_tersedia){
                catat_aktivitas(
                    $conn,
                    'hapus',
                    'nilai',
                    $siswa_id,
                    'Menghapus nilai '.$nama_mapel.' siswa '.$nama_siswa.
                    ' (NIS '.$nis.'), kelas '.$kelas.', sebelumnya '.
                    format_nilai_audit($nilai_sebelumnya)
                );
            }
            continue;
        }

        $nilai_setelahnya = (float) $nilai_input;
        if(!$lama_tersedia){
            catat_aktivitas(
                $conn,
                'tambah',
                'nilai',
                $siswa_id,
                'Menambahkan nilai '.$nama_mapel.' siswa '.$nama_siswa.
                ' (NIS '.$nis.'), kelas '.$kelas.', sebesar '.
                format_nilai_audit($nilai_setelahnya)
            );
        }elseif(abs($nilai_sebelumnya - $nilai_setelahnya) > 0.0001){
            catat_aktivitas(
                $conn,
                'ubah',
                'nilai',
                $siswa_id,
                'Mengubah nilai '.$nama_mapel.' siswa '.$nama_siswa.
                ' (NIS '.$nis.'), kelas '.$kelas.', dari '.
                format_nilai_audit($nilai_sebelumnya).' menjadi '.
                format_nilai_audit($nilai_setelahnya)
            );
        }
    }
}
