<?php
// menghubungkan php dengan koneksi database
require_once __DIR__ . '/../config/function.php';
require_once __DIR__ . '/../config/auth.php';
checkAuth('home'); // cek apakah sudah login dan punya akses ke menu ini

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

  body {
    background: #f5f7fa;
    font-family: 'Poppins', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    color: #333;
  }

  .container-home {
    max-width: 900px;
    margin: 30px auto;
    background: #fff;
    padding: 50px 40px;
    border-radius: 20px;
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.08);
    position: relative;
    top: -40px;
    text-align: center;
  }

  .logo-stg-wrapper {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: #fff;
    padding: 25px;
    border-radius: 50%;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    margin-bottom: 5px;
    margin-top: -30px;
    /* biar jarak lebih dekat */
  }

  .logo-stg {
    max-width: 100px;
  }

  .logo-subcont {
    display: flex;
    justify-content: center;
    align-items: center;
    margin-top: -40px;
    /* hapus jarak ekstra */
  }

  .logo-subcont img {
    max-width: 200px;
    width: 100%;
    height: auto;
  }

  h1 {
    font-weight: 700;
    margin-bottom: 20px;
  }

  ul {
    margin-top: 15px;
  }

  li {
    margin-bottom: 10px;
  }
</style>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta content="width=device-width, initial-scale=1.0" name="viewport">

  <title>iSubcont - Home</title>
  <meta content="" name="description">
  <meta content="" name="keywords">

  <!-- Favicons -->
  <link href="../assets/img/Logo-Stg.png" rel="icon">
  <link href="../assets/img/Logo-Stg.png" rel="apple-touch-icon">

  <!-- Google Fonts -->
  <link href="https://fonts.gstatic.com" rel="preconnect">
  <link href="https://fonts.googleapis.com/css?family=Open+Sans:300,300i,400,400i,600,600i,700,700i|Nunito:300,300i,400,400i,600,600i,700,700i|Poppins:300,300i,400,400i,500,500i,600,600i,700,700i" rel="stylesheet">

  <!-- Vendor CSS Files -->
  <link href="../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link href="../assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
  <link href="../assets/vendor/boxicons/css/boxicons.min.css" rel="stylesheet">
  <link href="../assets/vendor/quill/quill.snow.css" rel="stylesheet">
  <link href="../assets/vendor/quill/quill.bubble.css" rel="stylesheet">
  <link href="../assets/vendor/remixicon/remixicon.css" rel="stylesheet">
  <link href="../assets/vendor/simple-datatables/style.css" rel="stylesheet">

  <!-- Template Main CSS File -->
  <link href="../assets/css/style.css" rel="stylesheet">

  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">

  <!-- =======================================================
  * Template Name: NiceAdmin
  * Updated: May 30 2023 with Bootstrap v5.3.0
  * Template URL: https://bootstrapmade.com/nice-admin-bootstrap-admin-html-template/
  * Author: BootstrapMade.com
  * License: https://bootstrapmade.com/license/
  ======================================================== -->
</head>

<body>

  <!-- Header -->
  <?php
  $page = 'home';
  include_once __DIR__ . '/../includes/header.php';
  ?>
  <!-- End Header -->

  <main id="main" class="main">

    <div class="container container-home">
      <!-- Logo Section -->
      <div class="logo-stg-wrapper">
        <img src="../assets/img/logo-stg.png" alt="Logo STG" class="logo-stg">
      </div>
      <div class="logo-subcont">
        <img src="../assets/img/isubcont.png" alt="Logo STG" class="logo-stg">
      </div>

      <!-- Description -->
      <p class="text-start">
        Is a tailor-made solution designed to streamline the workflow of the Production team
        (Workstation Operators, Team Leaders, and Quality Control Inspectors) in managing
        the inventory of goods entering and exiting the stitching process.
      </p>

      <p class="text-start">
        This user-friendly application facilitates the accurate tracking of upper materials
        as they move through the stitching phase, ensuring seamless coordination and efficient
        inventory management for Team Leaders. With intuitive features and functionalities,
        iSubcont empowers Team Leaders to effortlessly monitor and maintain inventory levels
        during the stitching process.
      </p>

      <p class="text-start">
        Additionally, iSubcont offers a comprehensive feature set to document and manage returns
        and defects encountered during both the stitching process and incoming material inspections.
        This enables swift identification and resolution of any issues, contributing to enhanced
        quality control and operational efficiency.
      </p>

      <!-- Key Features -->
      <h4 class="text-start mt-4">Key features:</h4>
      <ul class="text-start">
        <li><strong>Inventory Tracking:</strong> Real-time monitoring of upper materials entering and leaving the stitching process, providing visibility into stock levels and facilitating timely replenishment.</li>
        <li><strong>Defect Documentation:</strong> Seamless recording and tracking of defects identified during stitching or incoming inspections, enabling prompt resolution and minimizing production disruptions.</li>
        <li><strong>User-Friendly Interface:</strong> Intuitive interface designed for easy navigation and accessibility, ensuring efficient utilization by Production team members at all levels.</li>
      </ul>
    </div>

  </main><!-- End #main -->

  <!-- ======= Footer ======= -->
  <?php include_once __DIR__ . '/../includes/footer.php' ?>
  <!-- End Footer -->

  <a href="#" class="back-to-top d-flex align-items-center justify-content-center"><i class="bi bi-arrow-up-short"></i></a>

  <!-- Vendor JS Files -->
  <script src="../assets/vendor/apexcharts/apexcharts.min.js"></script>
  <script src="../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
  <script src="../assets/vendor/chart.js/chart.umd.js"></script>
  <script src="../assets/vendor/echarts/echarts.min.js"></script>
  <script src="../assets/vendor/quill/quill.min.js"></script>
  <script src="../assets/vendor/simple-datatables/simple-datatables.js"></script>
  <script src="../assets/vendor/tinymce/tinymce.min.js"></script>
  <script src="../assets/vendor/php-email-form/validate.js"></script>

  <!-- Template Main JS File -->
  <script src="../assets/js/main.js"></script>

  <?php if (isset($_SESSION['login_status']) && $_SESSION['login_status'] === 'success'): ?>
    <?php include_once __DIR__ . '/../includes/notification.php'; ?>
    <?php unset($_SESSION['login_status']); ?>
  <?php endif; ?>
  <?php include_once __DIR__ . '/../includes/notification.php'; ?>

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