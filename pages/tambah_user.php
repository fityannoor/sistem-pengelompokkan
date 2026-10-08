<?php

include '../auth/cek_admin.php';
include '../koneksi.php';
require_once '../includes/audit_log.php';
$active = 'users';

$dashboard_link = '../dashboard.php';
$data_siswa_link = 'data_siswa.php';
$mapel_link = 'mata_pelajaran.php';
$nilai_link = 'input_nilai.php';
$cluster_link = 'clustering.php';
$hasil_link = 'hasil_cluster.php';
$user_link = 'users.php';
$import_link = 'import_leger.php';

$logout_link = '../auth/logout.php';
$error = '';
$nama = '';
$username = '';
$role = 'guru';

if(isset($_POST['simpan'])){
    $nama = trim((string) ($_POST['nama'] ?? ''));
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $role = (string) ($_POST['role'] ?? '');

    if($nama === '' || $username === '' || $password === ''){
        $error = 'Nama, username, dan password wajib diisi.';
    }elseif(strlen($nama) > 100){
        $error = 'Nama maksimal terdiri dari 100 karakter.';
    }elseif(!preg_match('/^[A-Za-z0-9._-]{3,50}$/', $username)){
        $error = 'Username harus 3–50 karakter dan hanya boleh berisi huruf, angka, titik, garis bawah, atau tanda hubung.';
    }elseif(strlen($password) < 6){
        $error = 'Password minimal terdiri dari 6 karakter.';
    }elseif(!in_array($role, ['admin', 'guru'], true)){
        $error = 'Role user tidak valid.';
    }

    if($error === ''){
        $cek_stmt = mysqli_prepare(
            $conn,
            'SELECT id FROM users WHERE username = ? LIMIT 1'
        );
        mysqli_stmt_bind_param($cek_stmt, 's', $username);
        mysqli_stmt_execute($cek_stmt);
        mysqli_stmt_store_result($cek_stmt);
        if(mysqli_stmt_num_rows($cek_stmt) > 0){
            $error = 'Username sudah digunakan.';
        }
        mysqli_stmt_close($cek_stmt);
    }

    if($error === ''){
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        $insert_stmt = mysqli_prepare($conn,"
            INSERT INTO users (nama, username, password, role)
            VALUES (?, ?, ?, ?)
        ");
        mysqli_stmt_bind_param(
            $insert_stmt,
            'ssss',
            $nama,
            $username,
            $password_hash,
            $role
        );
        mysqli_stmt_execute($insert_stmt);
        $user_baru_id = mysqli_insert_id($conn);
        mysqli_stmt_close($insert_stmt);

        catat_aktivitas(
            $conn, 'tambah', 'user', $user_baru_id,
            'Menambahkan user '.$username.' dengan role '.$role
        );

        header('Location: users.php?toast='.
            rawurlencode('User berhasil ditambahkan'));
        exit;
    }
}
?>

<!DOCTYPE html>

<html lang="id">
<head>

<meta charset="UTF-8">

<meta name="viewport"
content="width=device-width, initial-scale=1.0">

<title>Tambah User</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

<link rel="stylesheet"
href="../assets/user.css">

</head>

<body>

    <?php include '../includes/sidebar.php'; ?>

    <div class="content">

        <div class="topbar">

            <div>

                <h3 class="mb-0">
                    Tambah User
                </h3>

                <small class="text-muted">
                    Tambahkan akun admin atau guru
                </small>

            </div>

        </div>

        <div class="card-box">

            <form method="POST">

                <?= csrf_field(); ?>

                <?php if($error !== ''){ ?>
                <div class="alert alert-danger" role="alert">
                    <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
                </div>
                <?php } ?>

                <div class="mb-3">

                    <label class="form-label">

                        Nama User

                    </label>

                    <input
                    type="text"
                    name="nama"
                    class="form-control"
                    value="<?= htmlspecialchars($nama, ENT_QUOTES, 'UTF-8'); ?>"
                    placeholder="Masukkan nama user"
                    required>

                </div>

                <div class="mb-3">

                    <label class="form-label">

                        Username

                    </label>

                    <input
                    type="text"
                    name="username"
                    class="form-control"
                    value="<?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?>"
                    placeholder="Masukkan username"
                    required>

                </div>

                <div class="mb-3">

                    <label class="form-label">

                        Password

                    </label>

                    <input
                    type="password"
                    name="password"
                    class="form-control"
                    placeholder="Masukkan password"
                    required>

                </div>

                <div class="mb-4">

                    <label class="form-label">

                        Role

                    </label>

                    <select
                    name="role"
                    class="form-select">

                        <option value="admin" <?= $role === 'admin' ? 'selected' : ''; ?>>

                            Admin

                        </option>

                        <option value="guru" <?= $role === 'guru' ? 'selected' : ''; ?>>

                            Guru

                        </option>

                    </select>

                </div>

                <div class="d-flex gap-2">

                    <button
                    type="submit"
                    name="simpan"
                    class="btn btn-primary">

                        <i class="bi bi-save"></i>

                        Simpan

                    </button>

                    <a href="users.php"
                    class="btn btn-secondary">

                        <i class="bi bi-arrow-left"></i>

                        Kembali

                    </a>

                </div>

            </form>

        </div>



    </div>

</body>
</html>
