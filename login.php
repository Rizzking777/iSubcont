<?php
require_once __DIR__ . '/config/function.php';
error_reporting(0);

if (isset($_POST["login"])) {
  $nik_user   = mysqli_real_escape_string($conn, $_POST['nik_user']);
  $pass_user  = $_POST['pass_user'];

  // Ambil data user berdasarkan NIK
  $query = "
  SELECT 
    u.id_user, 
    u.nik_user, 
    u.pass_user, 
    u.role_id, 
    u.username,
    r.role_name,
    r.gate_type
  FROM tbl_user u
  JOIN roles r ON r.id = u.role_id
  WHERE u.nik_user='$nik_user' AND u.is_deleted = '0';
";

  $login = mysqli_query($conn, $query);

  if (mysqli_num_rows($login) > 0) {
    $data = mysqli_fetch_assoc($login);

    // Verifikasi password
    if (password_verify($pass_user, $data['pass_user'])) {

      // Set session
      $_SESSION["login"]      = true;
      $_SESSION['id_user']    = $data['id_user'];
      $_SESSION['nik_user']   = $data['nik_user'];
      $_SESSION['role_id']    = $data['role_id'];
      $_SESSION['role_name']  = $data['role_name'];
      $_SESSION['username']   = $data['username'];
      $_SESSION['type_scan']  = $data['gate_type'] ?? ''; // 🧩 Tambahan baru

      // Update last_login
      date_default_timezone_set('Asia/Jakarta');
      $now = date('Y-m-d H:i:s');
      mysqli_query($conn, "UPDATE tbl_user SET last_login = '$now' WHERE id_user = '{$data['id_user']}'");

      // Simpan ke log login
      $ip_address = $_SERVER['REMOTE_ADDR'];
      $user_agent = mysqli_real_escape_string($conn, $_SERVER['HTTP_USER_AGENT']);
      mysqli_query($conn, "
    INSERT INTO tlog_login (id_user, ip_address, user_agent, login_time) 
    VALUES ('{$data['id_user']}', '$ip_address', '$user_agent', '$now')
  ");

      $_SESSION['login_status'] = 'success';
      header("Location: pages/index.php");
      exit;
    } else {
      // Password salah
      $_SESSION['login_status'] = 'danger';
      header("Location: login.php");
      exit;
    }
  } else {
    // LOGIN GAGAL (NIK tidak ditemukan)
    $_SESSION['login_status'] = 'danger';
    header("Location: login.php");
    exit;
  }
}

?>

<style>
  .toast-progress {
    height: 4px;
    width: 100%;
    overflow: hidden;
    border-radius: 0 0 0.375rem 0.375rem;
  }

  .toast-progress-bar {
    height: 100%;
    animation: progressBar 3s linear forwards;
  }

  @keyframes progressBar {
    0% {
      width: 100%;
    }

    100% {
      width: 0%;
    }
  }
</style>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta content="width=device-width, initial-scale=1.0" name="viewport">

  <title>iSubcont - Login</title>
  <meta content="" name="description">
  <meta content="" name="keywords">

  <!-- Favicons -->
  <link href="assets/img/Logo-Stg.png" rel="icon">
  <link href="assets/img/Logo-Stg.png" rel="apple-touch-icon">

  <!-- Google Fonts -->
  <link href="https://fonts.gstatic.com" rel="preconnect">
  <link href="https://fonts.googleapis.com/css?family=Open+Sans:300,300i,400,400i,600,600i,700,700i|Nunito:300,300i,400,400i,600,600i,700,700i|Poppins:300,300i,400,400i,500,500i,600,600i,700,700i" rel="stylesheet">

  <!-- Vendor CSS Files -->
  <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
  <link href="assets/vendor/boxicons/css/boxicons.min.css" rel="stylesheet">
  <link href="assets/vendor/quill/quill.snow.css" rel="stylesheet">
  <link href="assets/vendor/quill/quill.bubble.css" rel="stylesheet">
  <link href="assets/vendor/remixicon/remixicon.css" rel="stylesheet">
  <link href="assets/vendor/simple-datatables/style.css" rel="stylesheet">

  <!-- Template Main CSS File -->
  <link href="assets/css/style.css" rel="stylesheet">

  <!-- =======================================================
  * Template Name: NiceAdmin
  * Updated: May 30 2023 with Bootstrap v5.3.0
  * Template URL: https://bootstrapmade.com/nice-admin-bootstrap-admin-html-template/
  * Author: BootstrapMade.com
  * License: https://bootstrapmade.com/license/
  ======================================================== -->
</head>

<body>

  <main>
    <div class="container">
      <section class="section register min-vh-100 d-flex flex-column align-items-center justify-content-center py-4">
        <div class="container">
          <div class="row justify-content-center">
            <div class="col-lg-4 col-md-6 d-flex flex-column align-items-center justify-content-center">

              <div class="d-flex justify-content-center py-4">
                <a href="index.php" class="logo d-flex align-items-center w-auto">
                  <img src="assets/img/Shoetown.png" alt="">

                </a>
              </div><!-- End Logo -->

              <div class="card mb-3">

                <div class="card-body">
                  <div class="pt-4 pb-2">
                    <h5 class="card-title text-center pb-0 fs-4">Login to Your Account</h5>
                    <p class="text-center small">Enter your nik & password to login</p>
                  </div>

                  <form class="row g-3 needs-validation" method="post" enctype="multipart/form-data" novalidate>

                    <div class="col-12">
                      <label for="yourUsername" class="form-label">NIK</label>
                      <div class="input-group has-validation">
                        <!-- <span class="input-group-text" id="inputGroupPrepend">@</span> -->
                        <input type="text" name="nik_user" class="form-control" id="nik_user" required>
                        <div class="invalid-feedback">Please enter your nik.</div>
                      </div>
                    </div>

                    <div class="col-12">
                      <label for="yourPassword" class="form-label">Password</label>
                      <input type="password" name="pass_user" class="form-control" id="pass_user" required>
                      <div class="invalid-feedback">Please enter your password!</div>
                    </div>
                    <div class="col-12">
                      <button class="btn btn-primary w-100" type="submit" name="login">Login</button>
                    </div>
                  </form>

                </div>
              </div>

              <div class="credits">
                <!-- All the links in the footer should remain intact. -->
                <!-- You can delete the links only if you purchased the pro version. -->
                <!-- Licensing information: https://bootstrapmade.com/license/ -->
                <!-- Purchase the pro version with working PHP/AJAX contact form: https://bootstrapmade.com/nice-admin-bootstrap-admin-html-template/ -->
                <a href="users-profile.php">&copy; Copyright <strong><span>Manufacturing Project Officer</span></strong></a>
              </div>

            </div>
          </div>
        </div>

      </section>

    </div>
  </main><!-- End #main -->

  <a href="#" class="back-to-top d-flex align-items-center justify-content-center"><i class="bi bi-arrow-up-short"></i></a>

  <!-- Vendor JS Files -->
  <script src="..\assets/vendor/apexcharts/apexcharts.min.js"></script>
  <script src="..\assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
  <script src="..\assets/vendor/chart.js/chart.umd.js"></script>
  <script src="..\assets/vendor/echarts/echarts.min.js"></script>
  <script src="..\assets/vendor/quill/quill.min.js"></script>
  <script src="..\assets/vendor/simple-datatables/simple-datatables.js"></script>
  <script src="..\assets/vendor/tinymce/tinymce.min.js"></script>
  <script src="..\assets/vendor/php-email-form/validate.js"></script>

  <!-- Template Main JS File -->
  <script src="..\assets/js/main.js"></script>

  <?php if (isset($_SESSION['login_status'])): ?>
    <?php include_once __DIR__ . '/includes/notification.php'; ?>
    <?php unset($_SESSION['login_status']); ?>
  <?php endif; ?>

  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const toastEl = document.getElementById('liveToast');
      if (toastEl) {
        const toast = new bootstrap.Toast(toastEl, {
          delay: 3000
        });
        toast.show();
      }
    });
  </script>

</body>

</html>