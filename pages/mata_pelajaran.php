<?php
include '../auth/cek_login.php';
include '../koneksi.php';
include '../includes/filter_session.php';

reset_persistent_filters_if_requested(
    ['filter_mata_pelajaran_jurusan'],
    'mata_pelajaran.php'
);
$active = 'mapel';

$dashboard_link = '../dashboard.php';
$data_siswa_link = 'data_siswa.php';
$mapel_link = 'mata_pelajaran.php';
$nilai_link = 'input_nilai.php';
$cluster_link = 'clustering.php';
$hasil_link = 'hasil_cluster.php';
$user_link = 'users.php';
$import_link = 'import_leger.php';

$logout_link = '../auth/logout.php';
$is_admin = ($_SESSION['role'] ?? '') === 'admin';
?>
<?php
include '../koneksi.php';


$jurusan_data = mysqli_query($conn,"
    SELECT DISTINCT jurusan
    FROM mata_pelajaran
    ORDER BY jurusan ASC
");
$jurusan_valid = [];
while($item = mysqli_fetch_assoc($jurusan_data)){
    $jurusan_valid[] = $item['jurusan'];
}
mysqli_data_seek($jurusan_data, 0);


$where = "";
$jurusan = persistent_filter_value(
    'filter_mata_pelajaran_jurusan', 'jurusan', $jurusan_valid
);
ensure_persistent_filter_url(
    'mata_pelajaran.php',
    ['jurusan' => $jurusan],
    ['toast']
);

if($jurusan !== ''){
    $jurusan_sql = mysqli_real_escape_string($conn, $jurusan);

    $where =
    "WHERE jurusan='$jurusan_sql'";
}

$data = mysqli_query($conn,"
    SELECT *
    FROM mata_pelajaran

    $where

    ORDER BY nama_mapel ASC
");
?>

<!DOCTYPE html>
<html lang="id">
<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Mata Pelajaran</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">

    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="../assets/mata_pelajaran.css">


</head>
<body>


    <?php include '../includes/sidebar.php'; ?>


<div class="content">

    <div class="topbar">

        <div>

        <h3 class="page-title mb-0">
            Mata Pelajaran
        </h3>

        <small class="text-muted">
            <?= $is_admin ? 'Kelola' : 'Lihat'; ?> data mata pelajaran
        </small>

        </div>

        <?php if($is_admin){ ?>
        <a href="tambah_mapel.php<?= $jurusan !== ''
            ? '?'.http_build_query(['jurusan_asal' => $jurusan])
            : ''; ?>"
            class="btn btn-primary px-4">
                <i class="bi bi-plus-circle"></i>
            Tambah Mapel
        </a>
        <?php } ?>

    </div>
    <div class="card-box mb-4">

        <form method="GET"
              data-filter-required="jurusan"
              data-filter-message="Silakan pilih program keahlian terlebih dahulu sebelum melakukan filter.">

        <div class="row align-items-end">

        <div class="col-md-4">

        <label class="form-label fw-semibold">

        Filter Program Keahlian

        </label>

        <select name="jurusan"
                class="form-select">

        <option value="">
        Semua Program Keahlian
        </option>

        <?php
        while($j =
        mysqli_fetch_assoc($jurusan_data)){
        ?>

        <option value="<?= htmlspecialchars($j['jurusan'], ENT_QUOTES, 'UTF-8'); ?>"

        <?php
        if($jurusan == $j['jurusan']){

            echo "selected";
        }
        ?>

        >

        <?= $j['jurusan'] === 'Umum'
            ? 'Semua Jurusan'
            : htmlspecialchars($j['jurusan']); ?>

        </option>

        <?php } ?>

        </select>

        </div>

        <div class="col-md-2">

        <button type="submit"
                class="btn btn-primary w-100">

        <i class="bi bi-funnel"></i>

        Filter

        </button>

        </div>

        </div>

        </form>

    </div>
    <div class="card-box">

        <div class="table-responsive">

            <table class="table table-hover align-middle">
                <thead class="table-primary">

                    <tr>
                        <th>No</th>
                        <th>Nama Mata Pelajaran</th>
                        <th>Berlaku Untuk</th>
                        <?php if($is_admin){ ?><th>Aksi</th><?php } ?>
                    </tr>

                </thead>

                <tbody>

                <?php
                $no = 1;

                while($row = mysqli_fetch_assoc($data)){
                ?>

                    <tr>

                        <td><?= $no++; ?></td>

                            <td>

                                <span class="badge bg-primary px-3 py-2">

                                    <?= htmlspecialchars($row['nama_mapel'], ENT_QUOTES, 'UTF-8'); ?>

                                </span>

                            </td>
                            <td>

                                <span class="badge bg-primary">

                                    <?= $row['jurusan'] === 'Umum'
                                        ? 'Semua Jurusan'
                                        : htmlspecialchars($row['jurusan']); ?>

                                </span>

                            </td>
                        <?php if($is_admin){ ?>
                        <td>

                            <a href="edit_mapel.php?<?= http_build_query([
                                'id' => (int) $row['id'],
                                'jurusan_asal' => $jurusan
                            ]); ?>"
                                class="btn btn-warning btn-sm">

                                <i class="bi bi-pencil"></i>

                            </a>

                            <form method="POST" action="hapus_mapel.php"
                                  class="d-inline"
                                  onsubmit="return confirm('Hapus mapel?')">
                                <?= csrf_field(); ?>
                                <input type="hidden" name="id" value="<?= (int) $row['id']; ?>">
                                <button type="submit" class="btn btn-danger btn-sm"
                                        aria-label="Hapus mata pelajaran">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>

                        </td>
                        <?php } ?>

                    </tr>

                <?php } ?>

                </tbody>

            </table>

        </div>

    </div>

</div>

</body>
</html>
