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

$id = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT,
    ["options" => ["min_range" => 1]]
);

if($id === false || $id === null){

    header("Location: users.php");
    exit;
}

$user_stmt = mysqli_prepare(
    $conn,
    "SELECT id, nama, username, role FROM users WHERE id = ?"
);

mysqli_stmt_bind_param($user_stmt, "i", $id);
mysqli_stmt_execute($user_stmt);

$user_result = mysqli_stmt_get_result($user_stmt);
$user = mysqli_fetch_assoc($user_result);

mysqli_stmt_close($user_stmt);

if(!$user){

    header("Location: users.php");
    exit;
}

$error = '';

if(isset($_POST['simpan'])){

    $nama = trim($_POST['nama'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $role = $_POST['role'] ?? '';
    $password = $_POST['password'] ?? '';
    $konfirmasi_password = $_POST['konfirmasi_password'] ?? '';

    $user['nama'] = $nama;
    $user['username'] = $username;
    $user['role'] = $role;

    if($nama === '' || $username === ''){

        $error = 'Nama dan username wajib diisi.';

    }elseif(strlen($nama) > 100){

        $error = 'Nama maksimal terdiri dari 100 karakter.';

    }elseif(!preg_match('/^[A-Za-z0-9._-]{3,50}$/', $username)){

        $error = 'Username harus 3–50 karakter dan hanya boleh berisi huruf, angka, titik, garis bawah, atau tanda hubung.';

    }elseif(!in_array($role, ['admin', 'guru'], true)){

        $error = 'Role user tidak valid.';

    }elseif(($password === '') !== ($konfirmasi_password === '')){

        $error = 'Password baru dan konfirmasi password harus diisi bersamaan.';

    }elseif($password !== '' && strlen($password) < 6){

        $error = 'Password baru minimal terdiri dari 6 karakter.';

    }elseif($password !== $konfirmasi_password){

        $error = 'Konfirmasi password tidak sama dengan password baru.';
    }

    if($error === ''){

        if($user['role'] === 'admin' && $role !== 'admin'){
            $admin_result = mysqli_query($conn,"
                SELECT COUNT(*) AS total FROM users WHERE role = 'admin'
            ");
            $admin_count = (int) mysqli_fetch_assoc($admin_result)['total'];
            if($admin_count <= 1){
                $error = 'Admin terakhir tidak dapat diubah menjadi guru.';
            }
        }
    }

    if($error === ''){

        $cek_stmt = mysqli_prepare(
            $conn,
            "SELECT id FROM users WHERE username = ? AND id != ? LIMIT 1"
        );

        mysqli_stmt_bind_param($cek_stmt, "si", $username, $id);
        mysqli_stmt_execute($cek_stmt);
        mysqli_stmt_store_result($cek_stmt);

        if(mysqli_stmt_num_rows($cek_stmt) > 0){
            $error = 'Username sudah digunakan oleh user lain.';
        }

        mysqli_stmt_close($cek_stmt);
    }

    if($error === ''){

        if($password !== ''){

            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $update_stmt = mysqli_prepare($conn,"
                UPDATE users
                SET nama = ?, username = ?, role = ?, password = ?
                WHERE id = ?
            ");

            mysqli_stmt_bind_param(
                $update_stmt,
                "ssssi",
                $nama,
                $username,
                $role,
                $password_hash,
                $id
            );

        }else{

            $update_stmt = mysqli_prepare($conn,"
                UPDATE users
                SET nama = ?, username = ?, role = ?
                WHERE id = ?
            ");

            mysqli_stmt_bind_param(
                $update_stmt,
                "sssi",
                $nama,
                $username,
                $role,
                $id
            );
        }

        mysqli_stmt_execute($update_stmt);
        mysqli_stmt_close($update_stmt);

        if((int) ($_SESSION['user_id'] ?? 0) === (int) $id){
            $_SESSION['nama'] = $nama;
            $_SESSION['role'] = $role;
        }

        catat_aktivitas(
            $conn, 'ubah', 'user', (int) $id,
            'Memperbarui user '.$username.' dengan role '.$role
        );

        header(
            "Location: users.php?toast=".
            rawurlencode('Data user berhasil diperbarui')
        );
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

<title>Edit User</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

<link rel="stylesheet"href="../assets/user.css">

</head>

<body>

<?php include '../includes/sidebar.php'; ?>

<div class="content">

<div class="topbar">

<h3>Edit User</h3>

</div>

<div class="card-box">

<form method="POST">

<?= csrf_field(); ?>

<?php if($error !== ''){ ?>

<div class="alert alert-danger" role="alert">
<?= htmlspecialchars($error); ?>
</div>

<?php } ?>

<div class="mb-3">

<label>Nama</label>

<input
type="text"
name="nama"
class="form-control"
value="<?= htmlspecialchars($user['nama']); ?>"
required>

</div>

<div class="mb-3">

<label>Username</label>

<input
type="text"
name="username"
class="form-control"
value="<?= htmlspecialchars($user['username']); ?>"
required>

</div>

<div class="mb-3">

<label>Role</label>

<select
name="role"
class="form-select">

<option value="admin"
<?= $user['role']=='admin' ? 'selected' : ''; ?>>

Admin

</option>

<option value="guru"
<?= $user['role']=='guru' ? 'selected' : ''; ?>>

Guru

</option>

</select>

</div>

<div class="mb-3">

<label>Password Baru</label>

<input
type="password"
name="password"
class="form-control"
placeholder="Kosongkan jika password tidak diubah"
autocomplete="new-password">

<small class="text-muted">
Minimal 6 karakter. Kosongkan untuk mempertahankan password lama.
</small>

</div>

<div class="mb-3">

<label>Konfirmasi Password Baru</label>

<input
type="password"
name="konfirmasi_password"
class="form-control"
placeholder="Ulangi password baru"
autocomplete="new-password">

</div>

<div class="d-flex gap-2">

<button
type="submit"
name="simpan"
class="btn btn-primary">

Simpan

</button>

<a href="users.php"
class="btn btn-secondary">

Kembali

</a>

</div>

</form>

</div>

</div>

</body>
</html>
