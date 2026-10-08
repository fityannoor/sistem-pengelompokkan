<?php
include_once __DIR__ . '/loading_screen.php';

$sidebar_reset_filter_link = static function(string $url): string {
    $separator = strpos($url, '?') === false ? '?' : '&';
    return $url.$separator.'reset_filter=1';
};

$audit_link = $audit_link ?? (
    strpos($user_link ?? '', 'pages/') === 0
        ? 'pages/aktivitas_log.php'
        : 'aktivitas_log.php'
);
$backup_link = $backup_link ?? (
    strpos($user_link ?? '', 'pages/') === 0
        ? 'pages/backup_database.php'
        : 'backup_database.php'
);
?>

<style>
    .sidebar{
        transition:width 0.25s ease, padding 0.25s ease;
        z-index:20;
        box-sizing:border-box;
        height:100vh;
        height:100dvh;
        overflow:visible;
    }

    .sidebar-menu{
        flex:1 1 auto;
        min-height:0;
        overflow-y:auto;
        overflow-x:hidden;
        padding-right:6px;
        scrollbar-width:thin;
        scrollbar-color:#cbd5e1 transparent;
    }

    .sidebar-menu::-webkit-scrollbar{
        width:6px;
    }

    .sidebar-menu::-webkit-scrollbar-thumb{
        background:#cbd5e1;
        border-radius:999px;
    }

    .sidebar-footer{
        flex:0 0 auto;
        background:#ffffff;
    }

    .content{
        transition:margin-left 0.25s ease;
    }

    .sidebar-toggle{
        position:absolute;
        top:50%;
        right:-16px;
        transform:translateY(-50%);
        width:32px;
        height:32px;
        border:none;
        border-radius:999px;
        background:#2563eb;
        color:#ffffff;
        display:flex;
        align-items:center;
        justify-content:center;
        font-size:20px;
        font-weight:700;
        line-height:1;
        box-shadow:0 8px 18px rgba(37,99,235,0.28);
        cursor:pointer;
        z-index:30;
    }

    .sidebar-toggle:hover{
        background:#1d4ed8;
    }

    body.sidebar-collapsed .sidebar{
        width:82px !important;
        padding-left:14px !important;
        padding-right:14px !important;
    }

    body.sidebar-collapsed .content{
        margin-left:102px !important;
    }

    body.sidebar-collapsed .sidebar-logo-text,
    body.sidebar-collapsed .sidebar-link-text,
    body.sidebar-collapsed .sidebar small,
    body.sidebar-collapsed .sidebar hr{
        display:none !important;
    }

    body.sidebar-collapsed .sidebar img{
        width:46px;
        margin-bottom:0 !important;
    }

    body.sidebar-collapsed .sidebar a{
        justify-content:center;
        gap:0 !important;
        padding:14px 10px !important;
    }

    body.sidebar-collapsed .sidebar a i{
        font-size:20px;
    }

    @media screen and (max-width:768px){
        .sidebar-toggle{
            display:none;
        }

        body.sidebar-collapsed .sidebar{
            width:100% !important;
            padding:24px !important;
        }

        body.sidebar-collapsed .content{
            margin-left:0 !important;
        }

        body.sidebar-collapsed .sidebar-logo-text,
        body.sidebar-collapsed .sidebar-link-text,
        body.sidebar-collapsed .sidebar small,
        body.sidebar-collapsed .sidebar hr{
            display:initial !important;
        }
    }
</style>

<div class="sidebar">

    <button
        type="button"
        class="sidebar-toggle"
        id="sidebarToggle"
        aria-label="Tutup sidebar">
        &lt;
    </button>

    <div class="sidebar-menu">

        <div class="text-center mb-4">

            <img
            src="<?= isset($logo_path) ? $logo_path : '../assets/img/logo_smk_4.png' ?>"
            alt="logo_smk_4"
            width="90"
            class="mb-2">

            <h6 class="fw-bold mb-0">
                <span class="sidebar-logo-text">
                Academic Analytics
                </span>
            </h6>

            <small class="text-muted">
                SMKN 4 Tanah Grogot
            </small>

        </div>

        <hr>

        

        <a href="<?= htmlspecialchars($sidebar_reset_filter_link($dashboard_link)) ?>"
        class="<?= $active=='dashboard' ? 'active' : '' ?>">

            <i class="bi bi-grid"></i>

            <span class="sidebar-link-text">
            Dashboard
            </span>

        </a>

        

        <a href="<?= htmlspecialchars($sidebar_reset_filter_link($data_siswa_link)) ?>"
        class="<?= $active=='siswa' ? 'active' : '' ?>">

            <i class="bi bi-people"></i>

            <span class="sidebar-link-text">
            Data Siswa
            </span>

        </a>

        <a href="<?= htmlspecialchars($sidebar_reset_filter_link($mapel_link)) ?>"
        class="<?= $active=='mapel' ? 'active' : '' ?>">

            <i class="bi bi-book"></i>

            <span class="sidebar-link-text">
            Mata Pelajaran
            </span>

        </a>

        <a href="<?= htmlspecialchars($sidebar_reset_filter_link($nilai_link)) ?>"
        class="<?= $active=='nilai' ? 'active' : '' ?>">

            <i class="bi bi-journal-text"></i>

            <span class="sidebar-link-text">
            Nilai Akademik
            </span>

        </a>

        <a href="<?= $cluster_link ?>"
        class="<?= $active=='cluster' ? 'active' : '' ?>">

            <i class="bi bi-cpu"></i>

            <span class="sidebar-link-text">
            Clustering
            </span>

        </a>
        <?php if(($_SESSION['role'] ?? '') === 'admin'){ ?>
        <a href="<?= $user_link ?>"
        class="<?= $active=='users' ? 'active' : '' ?>">

            <i class="bi bi-people-fill"></i>

            <span class="sidebar-link-text">
            Kelola User
            </span>

        </a>

        <?php } ?>

        <?php if(($_SESSION['role'] ?? '') === 'admin'){ ?>
        <a href="<?= htmlspecialchars($audit_link, ENT_QUOTES, 'UTF-8'); ?>"
        class="<?= $active=='audit' ? 'active' : '' ?>">

            <i class="bi bi-clock-history"></i>

            <span class="sidebar-link-text">
            Log Aktivitas
            </span>

        </a>
        <?php } ?>

        <?php if(($_SESSION['role'] ?? '') === 'admin'){ ?>
        <a href="<?= htmlspecialchars($backup_link, ENT_QUOTES, 'UTF-8'); ?>"
        class="<?= $active=='backup' ? 'active' : '' ?>">

            <i class="bi bi-database-down"></i>

            <span class="sidebar-link-text">
            Backup Database
            </span>

        </a>
        <?php } ?>

        <a href="<?= htmlspecialchars($sidebar_reset_filter_link($hasil_link)) ?>"
        class="<?= $active=='hasil' ? 'active' : '' ?>">

            <i class="bi bi-bar-chart"></i>

            <span class="sidebar-link-text">
            Hasil Cluster
            </span>

        </a>

    </div>

    <div class="sidebar-footer">

        <hr>

        <a href="<?= $logout_link ?>">

            <i class="bi bi-box-arrow-right"></i>

            <span class="sidebar-link-text">
            Logout
            </span>

        </a>

    </div>

</div>

<script>
    (function(){
        const body =
        document.body;

        const toggle =
        document.getElementById(
            'sidebarToggle'
        );

        if(!toggle){
            return;
        }

        function setSidebarState(collapsed){
            body.classList.toggle(
                'sidebar-collapsed',
                collapsed
            );

            toggle.innerHTML =
            collapsed ? '&gt;' : '&lt;';

            toggle.setAttribute(
                'aria-label',
                collapsed ? 'Buka sidebar' : 'Tutup sidebar'
            );

            localStorage.setItem(
                'sidebarCollapsed',
                collapsed ? '1' : '0'
            );
        }

        setSidebarState(
            localStorage.getItem('sidebarCollapsed') === '1'
        );

        toggle.addEventListener(
            'click',
            function(){
                setSidebarState(
                    !body.classList.contains('sidebar-collapsed')
                );
            }
        );
    })();
</script>
