<?php
// menghubungkan php dengan koneksi database
require_once __DIR__ . '/../config/function.php';
require_once __DIR__ . '/../config/auth.php';
checkAuth('prod_dashboard'); // cek apakah sudah login dan punya akses ke menu ini

$nik = $_SESSION['nik_user'];
$username = $_SESSION['username']; // Query ringkasan per job_order

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

  /* ===================================== */
  /* PAGE */
  /* ===================================== */

  body {
    background: #f8fafc;
  }

  /* ===================================== */
  /* TITLE */
  /* ===================================== */

  .dashboard-title {
    background: #f0e6d2;
    padding: 12px 20px;
    border-radius: 14px;
    margin-bottom: 20px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, .05);
  }

  .dashboard-title h1 {
    margin: 0;
    font-size: 28px;
    font-weight: 700;
    color: #0f172a;
  }

  /* ===================================== */
  /* CARD */
  /* ===================================== */

  .dashboard-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 18px;
    padding: 18px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, .04);
    transition: all .2s ease;
  }

  .dashboard-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(0, 0, 0, .06);
  }

  /* ===================================== */
  /* SECTION HEADER */
  /* ===================================== */

  .section-header {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 20px;
    font-size: 16px;
    font-weight: 600;
    color: #0f172a;
  }

  .section-header i {
    color: #334155;
  }

  /* ===================================== */
  /* SUB CARD */
  /* ===================================== */

  .sub-card {
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    padding: 20px;
    background: #fcfcfd;
    min-height: 320px;

    display: flex;
    align-items: center;
  }

  /* ===================================== */
  /* TITLE */
  /* ===================================== */

  .sub-title {
    font-size: 15px;
    font-weight: 600;
    margin-bottom: 24px;
    color: #0f172a;
  }

  .chart-title {
    font-size: 15px;
    font-weight: 600;
    margin-bottom: 10px;
    color: #0f172a;
  }

  /* ===================================== */
  /* METRIC GROUP */
  /* ===================================== */

  .metric-group {
    display: flex;
    flex-direction: column;
    justify-content: center;
    width: 100%;
  }

  .metric-group-full {
    height: 280px;
    display: flex;
    flex-direction: column;
    justify-content: center;
  }

  /* ===================================== */
  /* METRIC */
  /* ===================================== */

  .metric-row {
    display: flex;
    align-items: center;
    gap: 14px;
    margin-bottom: 24px;
  }

  .metric-label {
    width: 80px;
    font-size: 14px;
    font-weight: 600;
    color: #334155;
  }

  .metric-value {
    min-width: 55px;
    font-size: 18px;
    font-weight: 700;
    color: #0f172a;
  }

  /* ===================================== */
  /* PROGRESS */
  /* ===================================== */

  .progress-custom {
    flex: 1;
    height: 38px;
    background: #e2e8f0;
    border-radius: 10px;
    overflow: hidden;
    box-shadow: inset 0 1px 2px rgba(0, 0, 0, .05);
  }

  .progress-custom .progress-bar {
    border-radius: 10px;
    box-shadow: inset 0 -1px 0 rgba(0, 0, 0, .08);
  }

  /* ===================================== */
  /* COLORS */
  /* ===================================== */

  .bg-in {
    background: #9bc47c !important;
  }

  .bg-out {
    background: #e6a775 !important;
  }

  .bg-inventory {
    background: #357ABD !important;
  }

  /* ===================================== */
  /* CHART */
  /* ===================================== */

  .chart-container {
    height: 280px;
  }
</style>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta content="width=device-width, initial-scale=1.0" name="viewport">

  <title>iSubcont - Dashboards</title>
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

  <!-- Tambahkan pustaka Select2 di bagian <head> -->
  <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script> <!-- Pastikan jQuery ada -->

  <!-- Select2 CSS & JS (CDN) -->
  <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
  <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

  <!-- Datatables -->
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
  <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.bootstrap5.min.css">
  <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css">

  <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

  <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

</head>

<body>

  <!-- Header -->
  <?php
  $page = 'prod_dashboard';
  include_once __DIR__ . '/../includes/header.php';
  ?>
  <!-- End Header -->

  <main id="main" class="main">

    <div class="pagetitle text-black" style="background-color: #f0e6d2; padding: 10px 20px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
      <h1 style="font-size: 1.8rem; font-weight: 700; font-family: 'Roboto', sans-serif;">
        Production Dashboard Monitoring
      </h1>
    </div>

    <section class="section">

      <div class="row g-3">

        <!-- ===================================== -->
        <!-- LEFT SIDE -->
        <!-- ===================================== -->

        <div class="col-lg-4">

          <div class="dashboard-card h-100">

            <!-- HEADER -->
            <div class="section-header">
              <i class="bi bi-grid"></i>
              <span>Cutting Overview</span>
            </div>

            <!-- SUB CARD -->
            <div class="sub-card mb-4">

              <div class="metric-group">

                <div class="sub-title">
                  Supermarket Cutting 1 Area
                </div>

                <!-- IN -->
                <div class="metric-row">

                  <div class="metric-label">
                    In
                  </div>

                  <div class="progress progress-custom">
                    <div class="progress-bar bg-in" style="width: 85%"></div>
                  </div>

                  <div class="metric-value">
                    3000
                  </div>

                </div>

                <!-- OUT -->
                <div class="metric-row">

                  <div class="metric-label">
                    Out
                  </div>

                  <div class="progress progress-custom">
                    <div class="progress-bar bg-out" style="width: 70%"></div>
                  </div>

                  <div class="metric-value">
                    2000
                  </div>

                </div>

                <!-- INVENTORY -->
                <div class="metric-row mb-0">

                  <div class="metric-label">
                    Inventory
                  </div>

                  <div class="progress progress-custom">
                    <div class="progress-bar bg-inventory" style="width: 55%"></div>
                  </div>

                  <div class="metric-value">
                    1000
                  </div>

                </div>

              </div>

            </div>

            <!-- CHART -->
            <div>

              <div class="chart-title">
                Detail Inventory Per-Line
              </div>

              <div id="chartCutting" class="chart-container"></div>

            </div>

          </div>

        </div>

        <!-- ===================================== -->
        <!-- RIGHT SIDE -->
        <!-- ===================================== -->

        <div class="col-lg-8">

          <!-- PRE PROCESS -->
          <div class="dashboard-card mb-3">

            <div class="section-header">
              <i class="bi bi-grid"></i>
              <span>Supermarket Subcont Overview</span>
            </div>

            <div class="row align-items-center">

              <!-- LEFT -->
              <div class="col-lg-5">

                <div class="metric-group metric-group-full">

                  <div class="sub-title">
                    Pre Process Vendor
                  </div>

                  <!-- IN -->
                  <div class="metric-row">

                    <div class="metric-label">
                      In
                    </div>

                    <div class="progress progress-custom">
                      <div class="progress-bar bg-in" style="width: 85%"></div>
                    </div>

                    <div class="metric-value">
                      3000
                    </div>

                  </div>

                  <!-- OUT -->
                  <div class="metric-row">

                    <div class="metric-label">
                      Out
                    </div>

                    <div class="progress progress-custom">
                      <div class="progress-bar bg-out" style="width: 70%"></div>
                    </div>

                    <div class="metric-value">
                      2000
                    </div>

                  </div>

                  <!-- INVENTORY -->
                  <div class="metric-row mb-0">

                    <div class="metric-label">
                      Inventory
                    </div>

                    <div class="progress progress-custom">
                      <div class="progress-bar bg-inventory" style="width: 55%"></div>
                    </div>

                    <div class="metric-value">
                      1000
                    </div>

                  </div>

                </div>

              </div>

              <!-- RIGHT -->
              <div class="col-lg-7">

                <div class="chart-title">
                  Detail Inventory Per-Line
                </div>

                <div id="chartPreVendor" class="chart-container"></div>

              </div>

            </div>

          </div>

          <!-- AFTER PROCESS -->
          <div class="dashboard-card">

            <div class="row align-items-center">

              <!-- LEFT -->
              <div class="col-lg-5">

                <div class="metric-group metric-group-full">

                  <div class="sub-title">
                    After Process Vendor
                  </div>

                  <!-- IN -->
                  <div class="metric-row">

                    <div class="metric-label">
                      In
                    </div>

                    <div class="progress progress-custom">
                      <div class="progress-bar bg-in" style="width: 85%"></div>
                    </div>

                    <div class="metric-value">
                      3000
                    </div>

                  </div>

                  <!-- OUT -->
                  <div class="metric-row">

                    <div class="metric-label">
                      Out
                    </div>

                    <div class="progress progress-custom">
                      <div class="progress-bar bg-out" style="width: 70%"></div>
                    </div>

                    <div class="metric-value">
                      2000
                    </div>

                  </div>

                  <!-- INVENTORY -->
                  <div class="metric-row mb-0">

                    <div class="metric-label">
                      Inventory
                    </div>

                    <div class="progress progress-custom">
                      <div class="progress-bar bg-inventory" style="width: 55%"></div>
                    </div>

                    <div class="metric-value">
                      1000
                    </div>

                  </div>

                </div>

              </div>

              <!-- RIGHT -->
              <div class="col-lg-7">

                <div class="chart-title">
                  Detail Inventory Per-Line
                </div>

                <div id="chartAfterVendor" class="chart-container"></div>

              </div>

            </div>

          </div>

        </div>

      </div>

    </section>

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

  <!-- Select2 CSS -->
  <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
  <!-- jQuery -->
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <!-- Select2 JS -->
  <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

  <!-- DataTables core -->
  <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap4.min.js"></script>

  <!-- Responsive extension -->
  <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
  <script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap5.min.js"></script>

  <!-- Buttons extension -->
  <script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
  <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.bootstrap4.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
  <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
  <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>
  <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.colVis.min.js"></script>

  <?php include_once __DIR__ . '/../includes/notification.php'; ?>

  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const toastEl = document.getElementById('liveToast');
      if (toastEl) {
        const toast = new bootstrap.Toast(toastEl, {
          delay: 5000
        });
        toast.show();
      }
    });
  </script>

  <script>
    /* ===================================== */
    /* CHART OPTIONS */
    /* ===================================== */

    var chartOptions = {

      chart: {
        type: 'bar',
        height: 280,
        toolbar: {
          show: false
        }
      },

      series: [{
        name: 'Inventory',
        data: [3613, 7977, 4511, 6611, 2329, 1208, 7966]
      }],

      xaxis: {
        categories: ['103', '105', '108', '110', '112', '114', '116']
      },

      colors: ['#5f84ad'],

      plotOptions: {
        bar: {
          borderRadius: 6,
          columnWidth: '55%'
        }
      },

      dataLabels: {
        enabled: true,
        style: {
          fontSize: '12px',
          fontWeight: 600
        }
      },

      grid: {
        borderColor: '#e2e8f0'
      },

      stroke: {
        show: false
      }

    };

    /* ===================================== */
    /* INIT CHART */
    /* ===================================== */

    var chartCutting = new ApexCharts(
      document.querySelector("#chartCutting"),
      chartOptions
    );

    chartCutting.render();

    var chartPreVendor = new ApexCharts(
      document.querySelector("#chartPreVendor"),
      chartOptions
    );

    chartPreVendor.render();

    var chartAfterVendor = new ApexCharts(
      document.querySelector("#chartAfterVendor"),
      chartOptions
    );

    chartAfterVendor.render();
  </script>

</body>

</html>