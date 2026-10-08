<?php
include '../auth/cek_login.php';
include '../koneksi.php';
include '../includes/filter_session.php';

reset_persistent_filters_if_requested(
    ['filter_input_nilai_kelas'],
    'input_nilai.php'
);

$active = 'nilai';

$dashboard_link = '../dashboard.php';
$data_siswa_link = 'data_siswa.php';
$mapel_link = 'mata_pelajaran.php';
$nilai_link = 'input_nilai.php';
$cluster_link = 'clustering.php';
$hasil_link = 'hasil_cluster.php';
$user_link = 'users.php';
$import_link = 'import_leger.php';

$logout_link = '../auth/logout.php';
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

$kelas_selected = persistent_filter_value(
    'filter_input_nilai_kelas', 'kelas', $kelas_valid
);
ensure_persistent_filter_url(
    'input_nilai.php',
    ['kelas' => $kelas_selected],
    ['toast']
);
$filter_aktif = false;

if($kelas_selected !== ''){
    $filter_aktif = true;
}

$query_siswa = null;
$mapel = null;
$nilai_matrix = [];

if($filter_aktif){

    $siswa_stmt = mysqli_prepare($conn,"
        SELECT * FROM siswa WHERE kelas = ? ORDER BY nama_siswa ASC
    ");
    mysqli_stmt_bind_param($siswa_stmt, 's', $kelas_selected);
    mysqli_stmt_execute($siswa_stmt);
    $query_siswa = mysqli_stmt_get_result($siswa_stmt);
    mysqli_stmt_close($siswa_stmt);

    $siswa_first =
    mysqli_fetch_assoc($query_siswa);

    $jurusan =
    $siswa_first['jurusan'] ?? '';

    if($siswa_first){

        mysqli_data_seek(
            $query_siswa,
            0
        );
    }


    $mapel_stmt = mysqli_prepare($conn,"
        SELECT * FROM mata_pelajaran
        WHERE jurusan = ? OR jurusan = 'Umum'
        ORDER BY nama_mapel ASC
    ");
    mysqli_stmt_bind_param($mapel_stmt, 's', $jurusan);
    mysqli_stmt_execute($mapel_stmt);
    $mapel = mysqli_stmt_get_result($mapel_stmt);
    mysqli_stmt_close($mapel_stmt);

    $nilai_stmt = mysqli_prepare($conn,"
        SELECT nilai_detail.siswa_id, nilai_detail.mapel_id, nilai_detail.nilai
        FROM nilai_detail
        JOIN siswa ON siswa.id = nilai_detail.siswa_id
        WHERE siswa.kelas = ?
    ");
    mysqli_stmt_bind_param($nilai_stmt, 's', $kelas_selected);
    mysqli_stmt_execute($nilai_stmt);
    $nilai_result = mysqli_stmt_get_result($nilai_stmt);
    while($nilai_row = mysqli_fetch_assoc($nilai_result)){
        $nilai_matrix[(int) $nilai_row['siswa_id']][(int) $nilai_row['mapel_id']] =
            $nilai_row['nilai'];
    }
    mysqli_stmt_close($nilai_stmt);
}


?>

<!DOCTYPE html>
<html lang="id">
<head>

<meta charset="UTF-8">
<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Input Nilai</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
<link rel="preconnect" href="https://fonts.googleapis.com">

<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="../assets/input_nilai.css?v=3">


</head>
<body>


<?php include '../includes/sidebar.php'; ?>


<div class="content">

    <div class="topbar">

        <div>

            <h3 class="page-title mb-0">
                Input Nilai
            </h3>

            <small class="text-muted">
                Kelola nilai akademik siswa
            </small>

        </div>
        <?php if($_SESSION['role']=='guru'){ ?>
        <div class="d-flex gap-2">

            <a href="tambah_nilai.php<?= $kelas_selected !== ''
                ? '?'.http_build_query(['kelas' => $kelas_selected])
                : ''; ?>"
                class="btn btn-primary px-4">

                <i class="bi bi-plus-circle"></i>

                Tambah Nilai

            </a>

            <a href="import_leger.php"
                class="btn btn-success px-4">

                <i class="bi bi-file-earmark-excel"></i>

                Import Leger

            </a>

        </div>
        <?php } ?>
    </div>
    <div class="card-box mb-4">

    <form method="GET"
          id="filterKelasForm"
          data-filter-required="kelas"
          data-filter-message="Silakan pilih kelas terlebih dahulu sebelum melakukan filter.">

    <div class="row align-items-end">

    <div class="col-md-4">

    <label class="form-label fw-semibold">

    Filter Kelas

    </label>

    <select name="kelas"
            id="filterKelas"
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
    if($kelas_selected ==
    $k['kelas']){

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
    <?php if($filter_aktif){ ?>

    <div class="card-box">

        <div class="table-responsive nilai-scroll-wrapper"
             id="nilaiScrollWrapper">

            <table class="table table-hover align-middle nilai-table">

                <thead class="table-primary">

                    <tr>

                    <th class="sticky-col sticky-no">No</th>
                    <th class="sticky-col sticky-nama">Nama Siswa</th>
                    <th class="sticky-col sticky-kelas">Kelas</th>

                    <?php if($_SESSION['role']=='guru'){ ?>

                    <th class="sticky-col sticky-aksi">Aksi</th>

                    <?php } ?>

                    <?php
                    while($m =
                    mysqli_fetch_assoc($mapel)){
                    ?>

                    <th>

                    <?= htmlspecialchars($m['nama_mapel'], ENT_QUOTES, 'UTF-8'); ?>

                    </th>

                    <?php } ?>

                    </tr>

                </thead>

                <tbody>
                    <?php
                        mysqli_data_seek($mapel,0);
                    ?>

                    <?php

                        $no = 1;

                        while($siswa =
                        mysqli_fetch_assoc($query_siswa)){

                    ?>

                        <tr>

                        <td class="sticky-col sticky-no">

                        <?= $no++; ?>

                        </td>

                        <td class="sticky-col sticky-nama">

                        <?= htmlspecialchars($siswa['nama_siswa'], ENT_QUOTES, 'UTF-8'); ?>

                        </td>

                        <td class="sticky-col sticky-kelas">

                        <?= htmlspecialchars($siswa['kelas'], ENT_QUOTES, 'UTF-8'); ?>

                        </td>

                        <?php if($_SESSION['role']=='guru'){ ?>

                        <td class="sticky-col sticky-aksi">

                            <a
                                href="edit_nilai.php?siswa_id=<?= $siswa['id']; ?>&kelas=<?= urlencode($kelas_selected); ?>"
                                class="btn btn-warning btn-sm btn-edit-nilai">

                                <i class="bi bi-pencil-square"></i>

                                Edit

                            </a>

                        </td>

                        <?php } ?>

                    <?php

                        mysqli_data_seek($mapel,0);

                        while($m =
                        mysqli_fetch_assoc($mapel)){

                            $id_siswa =
                            $siswa['id'];

                            $id_mapel =
                            $m['id'];

                    ?>

                        <td>

                    <?php

                        $nilai_angka =
                        $nilai_matrix[(int) $id_siswa][(int) $id_mapel] ?? null;

                        if($nilai_angka >= 85){

                            echo "
                            <span class='nilai-bagus'>
                            $nilai_angka
                            </span>
                            ";

                        }elseif($nilai_angka >= 75){

                            echo "
                            <span class='nilai-sedang'>
                            $nilai_angka
                            </span>
                            ";

                        }elseif($nilai_angka != null){

                            echo "
                            <span class='nilai-rendah'>
                            $nilai_angka
                            </span>
                            ";

                        }else{

                            echo "-";
                        }

                        ?>

                        </td>

                        <?php } ?>

                        </tr>

                    <?php } ?>


                </tbody>

            </table>
        </div>

    </div>

    <?php }else{ ?>

    <div class="card-box text-center py-5 empty-animate">

        <i class="bi bi-funnel text-primary"
           style="font-size:48px;"></i>

        <h5 class="mt-3 mb-2">
            Pilih kelas terlebih dahulu
        </h5>

        <p class="text-muted mb-3">
            Data nilai akan ditampilkan setelah filter kelas dipilih.
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

<script>
    const filterKelasForm =
    document.getElementById('filterKelasForm');

    const filterKelas =
    document.getElementById('filterKelas');

    if(filterKelasForm && filterKelas){
        filterKelasForm.addEventListener(
            'submit',
            function(event){
                if(filterKelas.value.trim() === ''){
                    event.preventDefault();
                    alert(
                        'Silakan pilih kelas terlebih dahulu sebelum melakukan filter.'
                    );
                    filterKelas.focus();
                }
            }
        );
    }

    (function(){
        const wrapper =
        document.getElementById(
            'nilaiScrollWrapper'
        );

        if(!wrapper){
            return;
        }

        const table =
        wrapper.querySelector(
            '.nilai-table'
        );

        if(!table){
            return;
        }

        const floating =
        document.createElement('div');

        floating.className =
        'floating-x-scroll';

        const inner =
        document.createElement('div');

        inner.className =
        'floating-x-scroll-inner';

        floating.appendChild(inner);
        document.body.appendChild(floating);

        let syncing = false;

        function updateFloatingScroll(){
            const rect =
            wrapper.getBoundingClientRect();

            const hasOverflow =
            wrapper.scrollWidth >
            wrapper.clientWidth + 2;

            const isVisible =
            rect.top < window.innerHeight - 80 &&
            rect.bottom > 120 &&
            hasOverflow &&
            rect.bottom > window.innerHeight - 24;

            floating.classList.toggle(
                'is-visible',
                isVisible
            );

            if(!isVisible){
                return;
            }

            const left =
            Math.max(rect.left, 16);

            const width =
            Math.min(
                rect.width,
                window.innerWidth - left - 16
            );

            floating.style.left =
            left + 'px';

            floating.style.width =
            width + 'px';

            inner.style.width =
            wrapper.scrollWidth + 'px';

            if(!syncing){
                floating.scrollLeft =
                wrapper.scrollLeft;
            }
        }

        wrapper.addEventListener(
            'scroll',
            function(){
                if(syncing){
                    return;
                }

                syncing = true;
                floating.scrollLeft =
                wrapper.scrollLeft;
                syncing = false;
            }
        );

        floating.addEventListener(
            'scroll',
            function(){
                if(syncing){
                    return;
                }

                syncing = true;
                wrapper.scrollLeft =
                floating.scrollLeft;
                syncing = false;
            }
        );

        window.addEventListener(
            'scroll',
            updateFloatingScroll
        );

        window.addEventListener(
            'resize',
            updateFloatingScroll
        );

        updateFloatingScroll();
    })();
</script>

</body>
</html>
