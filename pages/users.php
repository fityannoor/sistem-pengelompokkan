
<?php

include '../auth/cek_admin.php';
include '../koneksi.php';

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
$logo_path = '../assets/img/logo_smk_4.png';
$total_admin = mysqli_fetch_assoc(
    mysqli_query($conn,"
        SELECT COUNT(*) as total
        FROM users
        WHERE role='admin'
    ")
);

$total_guru = mysqli_fetch_assoc(
    mysqli_query($conn,"
        SELECT COUNT(*) as total
        FROM users
        WHERE role='guru'
    ")
);

$users = mysqli_query($conn,"
    SELECT *
    FROM users
    ORDER BY id DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/user.css">

</head>
<body>
    <?php include '../includes/sidebar.php'; ?>

    <div class="content">

        <div class="topbar">

            <div>

                <h3 class="mb-0">
                Kelola User
                </h3>

                <small class="text-muted">
                Manajemen akun admin dan guru
                </small>

            </div>

            <a href="tambah_user.php"
                class="btn btn-primary rounded-pill px-4">

                <i class="bi bi-plus-circle"></i>

                Tambah User

            </a>

        </div>
        <div class="row g-3 mb-4">

            <div class="col-md-6">

                <div class="card-box text-center">

                    <h6 class="text-muted">
                        Total Admin
                    </h6>

                    <h1 class="fw-bold text-primary">

                        <?= $total_admin['total']; ?>

                    </h1>

                </div>

            </div>

            <div class="col-md-6">

                <div class="card-box text-center">

                    <h6 class="text-muted">
                        Total Guru
                    </h6>

                    <h1 class="fw-bold text-success">

                        <?= $total_guru['total']; ?>

                    </h1>

                </div>

            </div>

        </div>
        <div class="card-box">

            <table class="table table-hover">

                <thead>

                    <tr>

                    <th>No</th>
                    <th>Nama</th>
                    <th>Username</th>
                    <th>Role</th>
                    <th width="250">
                    Aksi
                    </th>

                    </tr>

                </thead>

                <tbody>

                    <?php

                    $no = 1;

                    while($row =
                    mysqli_fetch_assoc($users)){

                    ?>

                    <tr>

                    <td><?= $no++; ?></td>

                    <td><?= htmlspecialchars($row['nama'], ENT_QUOTES, 'UTF-8'); ?></td>

                    <td><?= htmlspecialchars($row['username'], ENT_QUOTES, 'UTF-8'); ?></td>

                    <td>

                    <?php if($row['role']=='admin'){ ?>

                    <span class="badge bg-danger">

                    Admin

                    </span>

                    <?php } else { ?>

                    <span class="badge bg-success">

                    Guru

                    </span>

                    <?php } ?>

                    </td>

                    <td>

                    <a href="edit_user.php?id=<?= $row['id']; ?>"
                    class="btn btn-warning btn-sm">

                    Edit

                    </a>

                    <?php if($row['role']!='admin'){ ?>

                    <form method="POST" action="hapus_user.php"
                          class="d-inline"
                          onsubmit="return confirm('Hapus user ini?')">
                        <?= csrf_field(); ?>
                        <input type="hidden" name="id" value="<?= (int) $row['id']; ?>">
                        <button type="submit" class="btn btn-danger btn-sm">
                            Hapus
                        </button>
                    </form>

                    <?php } ?>

                    </td>

                    </tr>

                    <?php } ?>

                </tbody>

            </table>

        </div>

    </div>
</body>
</html>
