<?php
if(session_status() !== PHP_SESSION_ACTIVE){
    session_start();
}

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');

if(($_SESSION['login'] ?? false) === true){
    header('Location: ../dashboard.php');
    exit;
}

$login_error = $_SESSION['login_error'] ?? '';
unset($_SESSION['login_error']);

if($login_error === '' &&
   ($_GET['status'] ?? '') === 'account_invalid'){
    $login_error = 'Sesi berakhir karena akun sudah tidak aktif atau telah dihapus.';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Login</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
<link rel="preconnect" href="https://fonts.googleapis.com">

<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

<style>
body{
    min-height:100vh;
    display:flex;
    justify-content:center;
    align-items:center;
    background:#f4f7fb;
    font-family:'Poppins',sans-serif;
    overflow:hidden;
    position:relative;
}

body::before{
    content:'';
    position:absolute;
    width:500px;
    height:500px;
    background:linear-gradient(135deg,#2563eb,#60a5fa);
    border-radius:50%;
    top:-200px;
    left:-200px;
    opacity:0.1;
}

body::after{
    content:'';
    position:absolute;
    width:400px;
    height:400px;
    background:linear-gradient(135deg,#22c55e,#4ade80);
    border-radius:50%;
    bottom:-180px;
    right:-180px;
    opacity:0.08;
}

.login-box{
    width:100%;
    max-width:430px;
    background:white;
    padding:45px;
    border-radius:32px;
    box-shadow:0 15px 50px rgba(0,0,0,0.08);
    position:relative;
    z-index:2;
}

.icon-box{
    width:95px;
    height:95px;
    border-radius:26px;
    background:linear-gradient(135deg,#2563eb,#1d4ed8);
    display:flex;
    align-items:center;
    justify-content:center;
    margin:auto;
    margin-bottom:25px;
    color:white;
    font-size:44px;
}

.logo{
    text-align:center;
    margin-bottom:35px;
}

.logo h2{
    font-weight:700;
    margin-bottom:10px;
}

.logo small{
    color:#6b7280;
}

.badge-login{
    background:#dbeafe;
    color:#1d4ed8;
    padding:10px 18px;
    border-radius:30px;
    font-size:13px;
    font-weight:600;
    display:inline-block;
    margin-bottom:20px;
}

.form-label{
    font-weight:600;
    margin-bottom:10px;
}

.form-control{
    height:55px;
    border-radius:14px;
    border:1px solid #d1d5db;
    padding-left:16px;
}

.form-control:focus{
    box-shadow:none;
    border-color:#2563eb;
}

.btn-login{
    height:55px;
    border:none;
    border-radius:14px;
    background:linear-gradient(135deg,#2563eb,#1d4ed8);
    color:white;
    font-weight:600;
    transition:0.3s;
}

.btn-login:hover{
    transform:translateY(-2px);
    opacity:0.95;
}

.info-text{
    text-align:center;
    margin-top:25px;
    color:#6b7280;
    font-size:14px;
}

@media screen and (max-width:576px){

.login-box{
    margin:20px;
    padding:35px 25px;
}

}
</style>

</head>
<body>

<div class="login-box">

<div class="text-center">

<div class="badge-login">

<i class="bi bi-shield-lock"></i>

Academic Login System

</div>

</div>

<div class="icon-box">

<i class="bi bi-mortarboard"></i>

</div>

<div class="logo">

<h2>
SMK Negeri 4 <br> Tanah Grogot
</h2>

<small>
Sistem Pengelompokan Prestasi Akademik Siswa
</small>

</div>

<form action="proses_login.php"
      method="POST">

<?php if($login_error !== ''){ ?>
<div class="alert alert-danger" role="alert">
    <?= htmlspecialchars($login_error, ENT_QUOTES, 'UTF-8'); ?>
</div>
<?php } ?>

<div class="mb-4">

<label class="form-label">

Username

</label>

<input type="text"
       name="username"
       class="form-control"
       placeholder="Masukkan username"
       required>

</div>

<div class="mb-4">

<label class="form-label">

Password

</label>

<input type="password"
       name="password"
       class="form-control"
       placeholder="Masukkan password"
       required>

</div>

<button type="submit"
        name="login"
        class="btn-login w-100">

<i class="bi bi-box-arrow-in-right"></i>

Login Sistem

</button>

</form>

<div class="info-text">

SMKN 4 Tanah Grogot<br>
Sistem Clustering K-Means Akademik

</div>

</div>

</body>
</html>
