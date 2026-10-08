<?php

include '../auth/cek_admin.php';
include '../koneksi.php';

$active = 'audit';
$dashboard_link = '../dashboard.php';
$data_siswa_link = 'data_siswa.php';
$mapel_link = 'mata_pelajaran.php';
$nilai_link = 'input_nilai.php';
$cluster_link = 'clustering.php';
$hasil_link = 'hasil_cluster.php';
$user_link = 'users.php';
$audit_link = 'aktivitas_log.php';
$logout_link = '../auth/logout.php';
$logo_path = '../assets/img/logo_smk_4.png';

$aksi_valid = [
    'login', 'logout', 'tambah', 'ubah', 'hapus',
    'simpan', 'sinkronisasi', 'proses', 'backup'
];
$aksi = trim((string) ($_GET['aksi'] ?? ''));
if(!in_array($aksi, $aksi_valid, true)){
    $aksi = '';
}

if($aksi !== ''){
    $log_stmt = mysqli_prepare($conn,"
        SELECT * FROM aktivitas_log
        WHERE aksi = ?
        ORDER BY id DESC
        LIMIT 300
    ");
    mysqli_stmt_bind_param($log_stmt, 's', $aksi);
    mysqli_stmt_execute($log_stmt);
    $logs = mysqli_stmt_get_result($log_stmt);
}else{
    $logs = mysqli_query($conn,"
        SELECT * FROM aktivitas_log
        ORDER BY id DESC
        LIMIT 300
    ");
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log Aktivitas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/user.css">
</head>
<body>
<?php include '../includes/sidebar.php'; ?>

<div class="content">
    <div class="topbar">
        <div>
            <h3 class="mb-0">Log Aktivitas</h3>
            <small class="text-muted">Riwayat aktivitas penting pengguna</small>
        </div>
    </div>

    <div class="card-box mb-4">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label fw-semibold">Filter Aktivitas</label>
                <select name="aksi" class="form-select">
                    <option value="">Semua aktivitas</option>
                    <?php foreach($aksi_valid as $opsi){ ?>
                    <option value="<?= htmlspecialchars($opsi, ENT_QUOTES, 'UTF-8'); ?>"
                        <?= $aksi === $opsi ? 'selected' : ''; ?>>
                        <?= htmlspecialchars(ucfirst($opsi), ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                    <?php } ?>
                </select>
            </div>
            <div class="col-md-2">
                <button class="btn btn-primary w-100" type="submit">
                    <i class="bi bi-funnel"></i> Filter
                </button>
            </div>
        </form>
    </div>

    <div class="card-box">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Waktu</th>
                        <th>Pengguna</th>
                        <th>Role</th>
                        <th>Aktivitas</th>
                        <th>Entitas</th>
                        <th>Keterangan</th>
                        <th>IP</th>
                    </tr>
                </thead>
                <tbody>
                <?php if(mysqli_num_rows($logs) === 0){ ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">
                        Belum ada aktivitas yang tercatat.
                    </td></tr>
                <?php } ?>
                <?php while($log = mysqli_fetch_assoc($logs)){ ?>
                    <tr>
                        <td><?= htmlspecialchars(date('d-m-Y H:i:s', strtotime($log['created_at'])), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?= htmlspecialchars($log['nama_pengguna'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><span class="badge bg-secondary"><?= htmlspecialchars(strtoupper($log['role_pengguna']), ENT_QUOTES, 'UTF-8'); ?></span></td>
                        <td><?= htmlspecialchars(ucfirst($log['aksi']), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?= htmlspecialchars($log['entitas'], ENT_QUOTES, 'UTF-8'); ?><?= $log['entitas_id'] !== null ? ' #'.(int) $log['entitas_id'] : ''; ?></td>
                        <td><?= htmlspecialchars($log['keterangan'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?= htmlspecialchars($log['ip_address'], ENT_QUOTES, 'UTF-8'); ?></td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
        <small class="text-muted">Menampilkan maksimal 300 aktivitas terbaru.</small>
    </div>
</div>
</body>
</html>
