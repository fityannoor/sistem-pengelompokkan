<?php
include '../auth/cek_login.php';
include '../koneksi.php';
include '../includes/filter_session.php';

reset_persistent_filters_if_requested(
    ['filter_data_siswa_kelas'],
    'data_siswa.php'
);

$active = 'siswa';

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
$is_admin = ($_SESSION['role'] ?? '') === 'admin';
?>
<?php
include '../koneksi.php';

$kelas_data = mysqli_query($conn,"
    SELECT DISTINCT kelas
    FROM siswa
    ORDER BY kelas ASC
");
$kelas_valid = [];
while($item = mysqli_fetch_assoc($kelas_data)){
    $kelas_valid[] = $item['kelas'];
}
mysqli_data_seek($kelas_data, 0);
$total_kelas_info = count($kelas_valid);
$total_siswa_info = (int) mysqli_fetch_assoc(
    mysqli_query($conn, 'SELECT COUNT(*) AS total FROM siswa')
)['total'];

$kelas = persistent_filter_value(
    'filter_data_siswa_kelas', 'kelas', $kelas_valid
);

$result = null;
if($kelas !== ''){
    $siswa_stmt = mysqli_prepare($conn,"
        SELECT * FROM siswa WHERE kelas = ? ORDER BY nama_siswa ASC
    ");
    mysqli_stmt_bind_param($siswa_stmt, 's', $kelas);
    mysqli_stmt_execute($siswa_stmt);
    $result = mysqli_stmt_get_result($siswa_stmt);
    mysqli_stmt_close($siswa_stmt);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Siswa</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">

    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/data_siswa.css">

</head>
<body>


    <?php include '../includes/sidebar.php'; ?>


    <div class="content">

        <div class="topbar">

        

            <div>
                <h3>Data Siswa</h3>
                <small><?= $is_admin ? 'Kelola' : 'Lihat'; ?> data siswa</small>
            </div>

            <?php if($is_admin){ ?>
            <a href="tambah_siswa.php<?= $kelas !== ''
                ? '?'.http_build_query(['kelas_asal' => $kelas])
                : ''; ?>" class="btn btn-primary">
                <i class="bi bi-plus"></i>
                Tambah Siswa
            </a>
            <?php } ?>

        </div>
        <div class="card-box mb-4">

        <form method="GET"
              data-filter-required="kelas"
              data-filter-message="Silakan pilih kelas terlebih dahulu sebelum melakukan filter.">

        <div class="row align-items-end">

        <div class="col-md-4">

        <label class="form-label fw-semibold">

        Filter Kelas

        </label>

        <select name="kelas"
                class="form-select">

        <option value="">
        -- Pilih Kelas --
        </option>

        <?php
        while($k =
        mysqli_fetch_assoc($kelas_data)){
        ?>

        <option value="<?= htmlspecialchars($k['kelas'], ENT_QUOTES, 'UTF-8'); ?>"

        <?php
        if($kelas == $k['kelas']){

            echo "selected";
        }
        ?>

        >

        <?= htmlspecialchars($k['kelas'], ENT_QUOTES, 'UTF-8'); ?>

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
        <?php if($kelas !== ''){ ?>
        <div class="card-box">

            <div class="table-responsive">

                <table class="table table-hover align-middle">

                    <thead>

                        <tr>
                            <th>No</th>
                            <th>NIS</th>
                            <th>Nama Siswa</th>
                            <th>Kelas</th>
                            <?php if($is_admin){ ?><th>Aksi</th><?php } ?>
                        </tr>

                    </thead>

                    <tbody>

                    <?php
                    $no = 1;

                    while($row = mysqli_fetch_assoc($result)){
                    ?>

                        <tr>

                            <td><?= $no++; ?></td>

                            <td><?= htmlspecialchars($row['nis'], ENT_QUOTES, 'UTF-8'); ?></td>

                            <td><?= htmlspecialchars($row['nama_siswa'], ENT_QUOTES, 'UTF-8'); ?></td>

                            <td>

                                <span class="badge bg-primary px-3 py-2">

                                <?= htmlspecialchars($row['kelas'], ENT_QUOTES, 'UTF-8'); ?>

                                </span>

                            </td>

                            <?php if($is_admin){ ?>
                            <td>

                                <form method="POST"
                                      action="pilih_edit_siswa.php"
                                      class="d-inline">

                                    <?= csrf_field(); ?>

                                    <input type="hidden"
                                           name="siswa_id"
                                           value="<?= (int) $row['id']; ?>">

                                    <input type="hidden"
                                           name="kelas_kembali"
                                           value="<?= htmlspecialchars($kelas); ?>">

                                    <button type="submit"
                                            class="btn btn-warning btn-sm"
                                            aria-label="Edit siswa">

                                        <i class="bi bi-pencil"></i>

                                    </button>

                                </form>
                                

                                <form method="POST"
                                      action="hapus_siswa.php"
                                      class="d-inline"
                                      onsubmit="return confirm('Hapus data?')">

                                    <?= csrf_field(); ?>

                                    <input type="hidden"
                                           name="id"
                                           value="<?= (int) $row['id']; ?>">

                                    <input type="hidden"
                                           name="kelas"
                                           value="<?= htmlspecialchars($kelas); ?>">

                                    <button type="submit"
                                            class="btn btn-danger btn-sm"
                                            aria-label="Hapus siswa">

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

        <?php }else{ ?>

        <div class="card-box text-center py-5">
            <i class="bi bi-people text-primary" style="font-size:48px;"></i>
            <h5 class="mt-3 mb-2">Pilih kelas terlebih dahulu</h5>
            <p class="text-muted mb-3">
                Data siswa akan ditampilkan setelah filter kelas dipilih.
            </p>
            <div class="d-flex justify-content-center flex-wrap gap-2">
                <span class="badge bg-primary-subtle text-primary px-3 py-2">
                    <i class="bi bi-collection me-1"></i>
                    <?= $total_kelas_info; ?> kelas tersedia
                </span>
                <span class="badge bg-success-subtle text-success px-3 py-2">
                    <i class="bi bi-person-check me-1"></i>
                    <?= $total_siswa_info; ?> siswa terdaftar
                </span>
            </div>
        </div>

        <?php } ?>

    </div>

</body>
</html>
