<?php
require __DIR__ . '/config/function.php';

if (!isset($_SESSION["login"])) {
  header("Location: login.php");
  exit;
}

if ($_SESSION["authority"] !== "User") {
  header("Location: includes/forbidden.php");
  exit;
}

$page = 'index';
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta content="width=device-width, initial-scale=1.0" name="viewport">

  <title>iSubcont - Dashboard</title>
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

<!-- Header -->
  <?php
  include __DIR__ . '/includes/header.php';
  ?>
<!-- End Header -->
  <main id="main" class="main">

    <div class="pagetitle">
      <h1>Home</h1>
      <nav>
        <ol class="breadcrumb">
          <li class="breadcrumb-item"><a href="index.php">Home</a></li>
          <!-- <li class="breadcrumb-item active">Dashboard</li> -->
        </ol>
      </nav>
    </div><!-- End Page Title -->

    <section class="section dashboard">
      <div class="row">

        <!-- Left side columns -->
        <div class="col-lg-12">
          <div class="row">
              <div class="card">
                <div class="card-body">
                  <br>
                  <h4 class="card-title" style='text-align:center;'> <img src="assets/img/Assy-Logo.png" width="20%" alt=""></h4>
                  <p style= text-align:justify;>Is a tailor-made solution designed to streamline the workflow of the Production team (Workstation Operators, Team Leaders, and Quality Control Inspectors) 
                    in managing the inventory of goods entering and exiting the Assembling process. <br><br> This application facilitates the accurate tracking of upper materials as they move through the Assembling phase,
                    ensuring seamless coordination and efficient inventory management for Team Leaders. With intuitive features and functionalities, Istitch empowers Team Leaders to effortlessly monitor and maintain inventory levels during the Assembling process.<br><br>
                    Additionally, <strong>I-Assembly</strong> offers a comprehensive feature set to document and manage returns and defects encountered during both the Assembling process and incoming material inspections. This enables swift identification and resolution of any issues, 
                    contributing to enhanced quality control and operational efficiency.
                  </p>
                  <p style= font-weight:bold;>
                    Key features:
                  </p>
                    <ul style= text-align:justify;>
                      <li><strong>Inventory Tracking: </strong>Real-time monitoring of upper materials entering and leaving the stitching process, providing visibility into stock levels and facilitating timely replenishment.</li>
                      <li><strong>Customizable Reporting: </strong>Robust reporting capabilities to generate custom reports on inventory status, defect trends, and performance metrics, empowering stakeholders to make informed decisions and drive continuous improvement.</li>
                      <li><strong>Integration Capabilities: </strong>Seamless integration with existing production systems and databases, facilitating data exchange and enhancing overall operational efficiency.</li>
                    </ul>
                  <p style= text-align:justify;>
                  <strong>I-Assembly</strong> is an indispensable tool for Production teams, offering a comprehensive solution to streamline inventory management, enhance quality control, and optimize production processes within the stitching department.
                  </p>
                </div>
              </div>

          </div>
        </div>
         <!-- DASHBOARD PRODUCTION -->

        <!-- <div class="col-lg-4">
          <div class="row">
            <a href="Dashboard-Actual-perHour.php">
              <div class="card">
                <div class="card-body">
                  <br>
                  <h4 class="card-title" style='text-align:center;'>ACTUAL PER HOUR STITCH</h4>
                </div>
              </div>
            </a>
          </div>
        </div>  -->
        <!-- ACTUAL PER HOUR STITCHING-->

        <!-- <div class="col-lg-4">
          <div class="row">
            <a href="Dashboard-Wip-Stitching.php">
              <div class="card">
                <div class="card-body">
                  <br>
                  <h4 class="card-title" style='text-align:center;'>DASHBOARD WIP LASTING</h4>
                </div>
              </div>
            </a>
          </div>
        </div> -->
        <!-- ACTUAL PER HOUR STITCHING-->

        <!-- <div class="col-lg-4">
          <div class="row">
            <a href="Dashboard-Actual-perHour-lasting.php">
              <div class="card">
                <div class="card-body">
                  <br>
                  <h4 class="card-title" style='text-align:center;'>ACTUAL PER HOUR ASSEMBLING</h4>
                </div>
              </div>
            </a>
          </div>
        </div> -->
        <!-- ACTUAL PER HOUR STITCHING-->

      </div>
    </section>

  </main><!-- End #main -->

  <!-- ======= Footer ======= -->
  <?php include __DIR__ . '/includes/footer.php'; ?>
  <!-- End Footer -->

  <a href="#" class="back-to-top d-flex align-items-center justify-content-center"><i class="bi bi-arrow-up-short"></i></a>

  <!-- Vendor JS Files -->
  <script src="assets/vendor/apexcharts/apexcharts.min.js"></script>
  <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
  <script src="assets/vendor/chart.js/chart.umd.js"></script>
  <script src="assets/vendor/echarts/echarts.min.js"></script>
  <script src="assets/vendor/quill/quill.min.js"></script>
  <script src="assets/vendor/simple-datatables/simple-datatables.js"></script>
  <script src="assets/vendor/tinymce/tinymce.min.js"></script>
  <script src="assets/vendor/php-email-form/validate.js"></script>

  <!-- Template Main JS File -->
  <script src="assets/js/main.js"></script>

</body>

</html>