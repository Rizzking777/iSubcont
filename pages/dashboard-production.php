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

  body {
    background: #f8fafc;
  }

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

  .sub-card {
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    padding: 20px;
    background: #fcfcfd;
    min-height: 320px;
    display: flex;
    align-items: center;
  }

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
    min-width: 75px;
    font-size: 22px;
    font-weight: 700;
    letter-spacing: -.5px;
    color: #0f172a;
  }

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
    transition: all .25s ease;
  }

  .progress-custom:hover .progress-bar {
    filter: brightness(1.08);
    transform: scaleY(1.08);
  }

  .bg-in {
    background: #9bc47c !important;
  }

  .bg-out {
    background: #e6a775 !important;
  }

  .bg-inventory {
    background: #357ABD !important;
  }

  .chart-container {
    height: 280px;
  }

  .tooltip-inner {
    background: #0f172a;
    color: #ffffff;
    font-size: 12px;
    padding: 8px 12px;
    border-radius: 8px;
    font-weight: 500;
  }

  .tooltip.bs-tooltip-top .tooltip-arrow::before {
    border-top-color: #0f172a;
  }

  .chart-scroll {
    overflow-x: auto;
    overflow-y: hidden;
    width: 100%;
  }

  .dashboard-card {
    position: relative;
    overflow: hidden;
  }

  .dashboard-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 4px;
    background: linear-gradient(to right,
        #5f84ad,
        #7aa37a);
  }

  .progress-bar {
    position: relative;
    overflow: hidden;
  }

  .progress-bar::after {
    content: '';
    position: absolute;
    top: 0;
    left: -40%;
    width: 40%;
    height: 100%;
    background: rgba(255, 255, 255, .18);
    transform: skewX(-20deg);
  }

  /* MODAL  */
  .dashboard-detail-modal {
    border: 0;
    border-radius: 20px;
  }

  .dashboard-modal-header {
    border-bottom: 1px solid #e2e8f0;
    padding: 20px 24px;
  }

  .dashboard-modal-footer {
    border-top: 1px solid #e2e8f0;
    padding: 16px 24px;
  }

  .dashboard-modal-subtitle {
    font-size: 13px;
    color: #64748b;
    margin-top: 2px;
  }

  .dashboard-detail-table thead th {
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
    color: #334155;
    font-size: 13px;
    font-weight: 700;
    white-space: nowrap;
  }

  .dashboard-detail-table tbody td {
    font-size: 13px;
    color: #475569;
    vertical-align: middle;
    white-space: nowrap;
  }

  .dashboard-detail-table tbody tr:hover {
    background: #f8fafc;
  }

  .btn-export {
    background: #2f8a9e;
    color: #ffffff;
    border-radius: 10px;
    padding: 8px 14px;
    font-weight: 600;
  }

  .btn-export:hover {
    background: #256d7d;
    color: #ffffff;
  }

  /* DATATABLE */

  .dashboard-detail-table {
    width: 100% !important;
  }

  .dashboard-detail-table th,
  .dashboard-detail-table td {
    white-space: nowrap;
    vertical-align: middle;
  }

  .dataTables_scrollHeadInner,
  .dataTables_scrollHeadInner table {
    width: 100% !important;
  }

  table.dataTable {
    width: 100% !important;
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

        <!-- RIGHT SIDE -->

        <div class="col-lg-12">

          <!-- PRE PROCESS -->
          <div class="dashboard-card mb-3">

            <div class="section-header">
              <i class="bi bi-grid"></i>
              <span>Supermarket Subcont Overview</span>
            </div>

            <div class="row align-items-center">

              <!-- LEFT -->
              <div class="col-lg-5">

                <div class="sub-card mb-3">

                  <div class="metric-group metric-group-full">

                    <div class="sub-title">
                      Ready to Warehouse Subcont
                    </div>

                    <!-- IN -->
                    <div class="metric-row">

                      <div class="metric-label">
                        In
                      </div>

                      <div
                        id="tooltipPreVendorIn"
                        class="progress progress-custom clickable-card"
                        data-bs-toggle="tooltip"
                        data-bs-placement="top"
                        data-section="pre_vendor"
                        data-type="in"
                        title="">

                        <div
                          id="barPreVendorIn"
                          class="progress-bar bg-in"
                          style="width: 0%"></div>

                      </div>

                      <div
                        id="totalPreVendorIn"
                        class="metric-value">
                        0
                      </div>

                    </div>

                    <!-- OUT -->
                    <div class="metric-row">

                      <div class="metric-label">
                        Out
                      </div>

                      <div
                        id="tooltipPreVendorOut"
                        class="progress progress-custom clickable-card"
                        data-bs-toggle="tooltip"
                        data-bs-placement="top"
                        data-section="pre_vendor"
                        data-type="out"
                        title="">

                        <div
                          id="barPreVendorOut"
                          class="progress-bar bg-out"
                          style="width: 0%"></div>

                      </div>

                      <div
                        id="totalPreVendorOut"
                        class="metric-value">
                        0
                      </div>

                    </div>

                    <!-- INVENTORY -->
                    <div class="metric-row mb-0">

                      <div class="metric-label">
                        Inventory
                      </div>

                      <div
                        id="tooltipPreVendorInventory"
                        class="progress progress-custom clickable-card"
                        data-bs-toggle="tooltip"
                        data-bs-placement="top"
                        data-section="pre_vendor"
                        data-type="inventory"
                        title="">

                        <div
                          id="barPreVendorInventory"
                          class="progress-bar bg-inventory"
                          style="width: 0%"></div>

                      </div>

                      <div
                        id="totalPreVendorInventory"
                        class="metric-value">
                        0
                      </div>

                    </div>

                  </div>

                </div>

              </div>

              <!-- RIGHT -->
              <div class="col-lg-7">

                <div class="chart-title">
                  Detail Inventory Per-Line
                </div>

                <div class="chart-scroll">

                  <div
                    id="chartPreVendor"
                    class="chart-container"></div>

                </div>

              </div>

            </div>

          </div>

          <!-- AFTER PROCESS -->
          <div class="dashboard-card">

            <div class="row align-items-center">

              <!-- LEFT -->
              <div class="col-lg-5">

                <div class="sub-card">

                  <div class="metric-group metric-group-full">

                    <div class="sub-title">
                      Return from Warehouse Subcont
                    </div>

                    <!-- IN -->
                    <div class="metric-row">

                      <div class="metric-label">
                        In
                      </div>

                      <div
                        id="tooltipAfterVendorIn"
                        class="progress progress-custom clickable-card"
                        data-bs-toggle="tooltip"
                        data-bs-placement="top"
                        data-section="after_vendor"
                        data-type="in"
                        title="">

                        <div
                          id="barAfterVendorIn"
                          class="progress-bar bg-in"
                          style="width: 0%"></div>

                      </div>

                      <div
                        id="totalAfterVendorIn"
                        class="metric-value">
                        0
                      </div>

                    </div>

                    <!-- OUT -->
                    <div class="metric-row">

                      <div class="metric-label">
                        Out
                      </div>

                      <div
                        id="tooltipAfterVendorOut"
                        class="progress progress-custom clickable-card"
                        data-bs-toggle="tooltip"
                        data-bs-placement="top"
                        data-section="after_vendor"
                        data-type="out"
                        title="">

                        <div
                          id="barAfterVendorOut"
                          class="progress-bar bg-out"
                          style="width: 0%"></div>

                      </div>

                      <div
                        id="totalAfterVendorOut"
                        class="metric-value">
                        0
                      </div>

                    </div>

                    <!-- INVENTORY -->
                    <div class="metric-row mb-0">

                      <div class="metric-label">
                        Inventory
                      </div>

                      <div
                        id="tooltipAfterVendorInventory"
                        class="progress progress-custom clickable-card"
                        data-bs-toggle="tooltip"
                        data-bs-placement="top"
                        data-section="after_vendor"
                        data-type="inventory"
                        title="">

                        <div
                          id="barAfterVendorInventory"
                          class="progress-bar bg-inventory"
                          style="width: 0%"></div>

                      </div>

                      <div
                        id="totalAfterVendorInventory"
                        class="metric-value">
                        0
                      </div>

                    </div>

                  </div>

                </div>

              </div>

              <!-- RIGHT -->
              <div class="col-lg-7">

                <div class="chart-title">
                  Detail Inventory Per-Line
                </div>

                <div class="chart-scroll">

                  <div
                    id="chartAfterVendor"
                    class="chart-container"></div>

                </div>

              </div>

            </div>

          </div>

        </div>

      </div>

    </section>

    <!-- DETAIL MODAL -->

    <div
      class="modal fade"
      id="dashboardDetailModal"
      tabindex="-1">

      <div class="modal-dialog modal-xl modal-dialog-scrollable">

        <div class="modal-content dashboard-detail-modal">

          <!-- HEADER -->
          <div class="modal-header dashboard-modal-header">

            <div>

              <h4
                id="dashboardModalTitle"
                class="modal-title">
                Detail Dashboard
              </h4>
            </div>

            <button
              type="button"
              class="btn-close"
              data-bs-dismiss="modal"></button>

          </div>

          <!-- BODY -->
          <div class="modal-body">

            <div class="table-responsive">

              <table
                id="dashboardDetailTable"
                class="
                table
                dashboard-detail-table
                align-middle
              ">
              </table>

            </div>

          </div>

          <!-- FOOTER -->
          <div class="modal-footer dashboard-modal-footer">

            <button
              type="button"

              class="
                  btn
                  btn-secondary
                  px-4
                "

              data-bs-dismiss="modal">

              Close
            </button>

            <button
              type="button"
              id="btnExportDashboardDetail"
              class="btn btn-success">
              <i class="bi bi-file-earmark-excel"></i>
              Export
            </button>

          </div>

        </div>

      </div>

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
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));

    var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
      return new bootstrap.Tooltip(tooltipTriggerEl);
    });
  </script>

  <script>
    var dashboardCharts = {};
    const chartColor = {

      cutting: '#5f84ad',
      preVendor: '#5f84ad',
      afterVendor: '#5f84ad'

    };

    $(document).ready(function() {
      loadDashboard();

      /* AUTO REFRESH */
      setInterval(function() {
        loadDashboard();
      }, 60000);

    });

    function loadDashboard() {

      $.ajax({
        url: './../config/get_production_dashboard.php',
        type: 'GET',
        dataType: 'json',

        success: function(response) {
          console.log(response);
          renderCuttingOverview(response);
          renderCuttingChart(response);
          renderPreVendorOverview(response);
          renderPreVendorChart(response);
          renderAfterVendorOverview(response);
          renderAfterVendorChart(response);
          initTooltip();
        },

        error: function(xhr) {
          console.log(xhr.responseText);
        }

      });

    }

    function initTooltip() {

      $('[data-bs-toggle="tooltip"]').tooltip('dispose');

      var tooltipTriggerList = [].slice.call(
        document.querySelectorAll('[data-bs-toggle="tooltip"]')
      );

      tooltipTriggerList.map(function(tooltipTriggerEl) {

        return new bootstrap.Tooltip(tooltipTriggerEl);

      });

    }

    function renderCuttingOverview(response) {

      let data = response.cutting.summary;
      let totalIn = data.in ?? 0;
      let totalOut = data.out ?? 0;
      let totalInventory = data.inventory ?? 0;

      let maxValue = Math.max(
        totalIn,
        totalOut,
        totalInventory,
        1
      );

      let inPercent =
        (totalIn / maxValue) * 100;

      let outPercent =
        (totalOut / maxValue) * 100;

      let inventoryPercent =
        (totalInventory / maxValue) * 100;

      $('#totalIn')
        .text(totalIn.toLocaleString());

      $('#totalOut')
        .text(totalOut.toLocaleString());

      $('#totalInventory')
        .text(totalInventory.toLocaleString());

      $('#barIn')
        .css('width', inPercent + '%');

      $('#barOut')
        .css('width', outPercent + '%');

      $('#barInventory')
        .css('width', inventoryPercent + '%');

      $('#tooltipIn')
        .attr(
          'data-bs-original-title',
          'In : ' +
          totalIn.toLocaleString() + ' Prs'
        )
        .attr(
          'data-value',
          totalIn
        );

      $('#tooltipOut')
        .attr(
          'data-bs-original-title',
          'Out : ' +
          totalOut.toLocaleString() + ' Prs'
        )
        .attr(
          'data-value',
          totalOut
        );

      $('#tooltipInventory')
        .attr(
          'data-bs-original-title',
          'Inventory : ' +
          totalInventory.toLocaleString() + ' Prs'
        )
        .attr(
          'data-value',
          totalInventory
        );

    }

    function renderCuttingChart(response) {

      let categories =
        response.cutting.chart.categories ?? [];

      let seriesData =
        response.cutting.chart.series ?? [];

      let dynamicChartWidth =
        categories.length * 70;

      dynamicChartWidth = Math.max(
        dynamicChartWidth,
        350
      );

      if (dashboardCharts.chartCutting) {

        dashboardCharts.chartCutting.destroy();

      }

      var chartOptions = {

        chart: {

          type: 'bar',
          height: 280,
          width: dynamicChartWidth,

          toolbar: {
            show: false
          },

          animations: {
            enabled: true,
            easing: 'easeinout',
            speed: 700
          },

          events: {

            dataPointSelection: function(
              event,
              chartContext,
              config
            ) {

              let selectedNcvs =
                categories[
                  config.dataPointIndex
                ];

              /* VALUE */
              let selectedValue =
                seriesData[
                  config.dataPointIndex
                ];

              /* PREVENT ZERO CLICK */
              if (selectedValue <= 0) {
                return;
              }

              /* OPEN DETAIL */
              openDashboardDetail({
                section: 'cutting',
                type: 'inventory',
                ncvs: selectedNcvs

              });
            }
          }
        },

        legend: {
          show: false
        },

        series: [{
          name: 'Inventory',
          data: seriesData
        }],

        xaxis: {
          categories: categories
        },

        colors: [
          chartColor.cutting
        ],

        plotOptions: {
          bar: {
            borderRadius: 6,
            columnWidth: '35%'
          }
        },

        tooltip: {

          theme: 'light',

          y: {
            formatter: function(val) {
              return val.toLocaleString() + ' prs';

            }
          }
        },

        dataLabels: {
          enabled: true
        },

        grid: {
          borderColor: '#e2e8f0'
        }

      };

      dashboardCharts.chartCutting =
        new ApexCharts(
          document.querySelector("#chartCutting"),
          chartOptions
        );

      dashboardCharts.chartCutting.render();

    }

    function renderPreVendorOverview(response) {

      let data = response.pre_vendor.summary;
      let totalIn = data.in ?? 0;
      let totalOut = data.out ?? 0;
      let totalInventory = data.inventory ?? 0;

      let maxValue = Math.max(
        totalIn,
        totalOut,
        totalInventory,
        1
      );

      let inPercent =
        (totalIn / maxValue) * 100;

      let outPercent =
        (totalOut / maxValue) * 100;

      let inventoryPercent =
        (totalInventory / maxValue) * 100;

      $('#totalPreVendorIn')
        .text(totalIn.toLocaleString());

      $('#totalPreVendorOut')
        .text(totalOut.toLocaleString());

      $('#totalPreVendorInventory')
        .text(totalInventory.toLocaleString());

      $('#barPreVendorIn')
        .css('width', inPercent + '%');

      $('#barPreVendorOut')
        .css('width', outPercent + '%');

      $('#barPreVendorInventory')
        .css('width', inventoryPercent + '%');

      $('#tooltipPreVendorIn')
        .attr(
          'data-bs-original-title',
          'In : ' +
          totalIn.toLocaleString() +
          ' Prs'
        )
        .attr(
          'data-value',
          totalIn
        );

      $('#tooltipPreVendorOut')
        .attr(
          'data-bs-original-title',
          'Out : ' +
          totalOut.toLocaleString() +
          ' Prs'
        )
        .attr(
          'data-value',
          totalOut
        );

      $('#tooltipPreVendorInventory')
        .attr(
          'data-bs-original-title',
          'Inventory : ' +
          totalInventory.toLocaleString() +
          ' Prs'
        )
        .attr(
          'data-value',
          totalInventory
        );

    }

    function renderPreVendorChart(response) {

      let categories =
        response.pre_vendor.chart.categories ?? [];

      let seriesData =
        response.pre_vendor.chart.series ?? [];

      let dynamicChartWidth =
        categories.length * 70;

      dynamicChartWidth = Math.max(
        dynamicChartWidth,
        350
      );

      if (dashboardCharts.chartPreVendor) {

        dashboardCharts.chartPreVendor.destroy();

      }

      var chartOptions = {

        chart: {

          type: 'bar',

          height: 280,

          width: dynamicChartWidth,

          toolbar: {
            show: false
          },

          animations: {
            enabled: true,
            easing: 'easeinout',
            speed: 700
          },

          events: {

            dataPointSelection: function(
              event,
              chartContext,
              config
            ) {

              let selectedNcvs =
                categories[
                  config.dataPointIndex
                ];

              let selectedValue =
                seriesData[
                  config.dataPointIndex
                ];

              if (selectedValue <= 0) {
                return;
              }

              openDashboardDetail({

                section: 'pre_vendor',

                type: 'inventory',

                ncvs: selectedNcvs

              });

            }

          }

        },

        legend: {
          show: false
        },

        series: [{
          name: 'Inventory',
          data: seriesData
        }],

        xaxis: {
          categories: categories
        },

        colors: [
          chartColor.preVendor
        ],

        plotOptions: {
          bar: {
            borderRadius: 6,
            columnWidth: '35%'
          }
        },

        tooltip: {

          theme: 'light',

          y: {
            formatter: function(val) {

              return val.toLocaleString() + ' prs';

            }
          }
        },

        dataLabels: {
          enabled: true
        },

        grid: {
          borderColor: '#e2e8f0'
        }

      };

      dashboardCharts.chartPreVendor =
        new ApexCharts(
          document.querySelector("#chartPreVendor"),
          chartOptions
        );

      dashboardCharts.chartPreVendor.render();

    }

    function renderAfterVendorOverview(response) {

      let data =
        response.after_vendor.summary;
      let totalIn = data.in ?? 0;
      let totalOut = data.out ?? 0;
      let totalInventory =
        data.inventory ?? 0;
      let maxValue = Math.max(
        totalIn,
        totalOut,
        totalInventory,
        1
      );

      let inPercent =
        (totalIn / maxValue) * 100;

      let outPercent =
        (totalOut / maxValue) * 100;

      let inventoryPercent =
        (totalInventory / maxValue) * 100;

      $('#totalAfterVendorIn')
        .text(totalIn.toLocaleString());

      $('#totalAfterVendorOut')
        .text(totalOut.toLocaleString());

      $('#totalAfterVendorInventory')
        .text(totalInventory.toLocaleString());

      $('#barAfterVendorIn')
        .css('width', inPercent + '%');

      $('#barAfterVendorOut')
        .css('width', outPercent + '%');

      $('#barAfterVendorInventory')
        .css(
          'width',
          inventoryPercent + '%'
        );

      $('#tooltipAfterVendorIn')
        .attr(
          'data-bs-original-title',
          'In : ' +
          totalIn.toLocaleString() +
          ' Pairs'
        )
        .attr(
          'data-value',
          totalIn
        );

      $('#tooltipAfterVendorOut')
        .attr(
          'data-bs-original-title',
          'Out : ' +
          totalOut.toLocaleString() +
          ' Pairs'
        )
        .attr(
          'data-value',
          totalOut
        );

      $('#tooltipAfterVendorInventory')
        .attr(
          'data-bs-original-title',
          'Inventory : ' +
          totalInventory.toLocaleString() +
          ' Pairs'
        )
        .attr(
          'data-value',
          totalInventory
        );

    }


    function renderAfterVendorChart(response) {

      let categories =
        response.after_vendor.chart.categories ?? [];
      let seriesData =
        response.after_vendor.chart.series ?? [];
      let dynamicChartWidth =
        categories.length * 70;

      dynamicChartWidth = Math.max(
        dynamicChartWidth,
        350
      );

      if (dashboardCharts.chartAfterVendor) {

        dashboardCharts.chartAfterVendor.destroy();

      }

      var chartOptions = {

        chart: {

          type: 'bar',

          height: 280,

          width: dynamicChartWidth,

          toolbar: {
            show: false
          },

          animations: {
            enabled: true,
            easing: 'easeinout',
            speed: 700
          },

          events: {

            dataPointSelection: function(
              event,
              chartContext,
              config
            ) {

              let selectedNcvs =
                categories[
                  config.dataPointIndex
                ];

              let selectedValue =
                seriesData[
                  config.dataPointIndex
                ];

              /* PREVENT 0 CLICK */

              if (selectedValue <= 0) {
                return;
              }

              openDashboardDetail({

                section: 'after_vendor',
                type: 'inventory',
                ncvs: selectedNcvs

              });

            }

          }

        },

        legend: {
          show: false
        },

        series: [{
          name: 'Inventory',
          data: seriesData
        }],

        xaxis: {
          categories: categories
        },

        colors: [
          chartColor.afterVendor
        ],

        plotOptions: {
          bar: {
            borderRadius: 6,
            columnWidth: '35%'
          }
        },

        tooltip: {

          theme: 'light',

          y: {
            formatter: function(val) {
              return val.toLocaleString() + ' Qty';

            }
          }
        },

        dataLabels: {
          enabled: true
        },

        grid: {
          borderColor: '#e2e8f0'
        }

      };

      dashboardCharts.chartAfterVendor =
        new ApexCharts(
          document.querySelector("#chartAfterVendor"),
          chartOptions
        );

      dashboardCharts.chartAfterVendor.render();

    }

    /* OPEN MODAL */

    $(document).on(
      'click',
      '.progress-custom',
      function() {

        let value =
          parseInt(
            $(this).attr('data-value')
          ) || 0;

        /* PREVENT ZERO CLICK */

        if (value <= 0) {
          return;
        }

        let type =
          $(this).data('type');

        let section =
          $(this).data('section');

        openDashboardDetail({
          section: section,
          type: type
        });

      }
    );

    function openDashboardDetail(params) {

      currentDetailParams = params;

      $.ajax({

        url: './../config/get_production_dashboard_detail.php',

        type: 'GET',

        dataType: 'json',

        data: params,

        success: function(response) {

          console.log(response);

          renderDashboardDetailTable(
            response
          );

          $('#dashboardModalTitle')
            .text('Detail Production Dashboard Monitoring');

          $('#dashboardModalSubtitle')
            .text(
              params.section
              .replaceAll('_', ' ')
              .toUpperCase()
            );

          $('#dashboardDetailModal')
            .modal('show');

        }

      });

    }

    /* RENDER DETAIL TABLE */

    function renderDashboardDetailTable(response) {

      /* DESTROY DATATABLE */

      if ($.fn.DataTable.isDataTable(
          '#dashboardDetailTable'
        )) {

        $('#dashboardDetailTable')
          .DataTable()
          .destroy();

      }

      /* DATA */

      let sizes = response.sizes ?? [];
      let rows = response.rows ?? [];

      /* HEADER */

      let headerHtml = `

        <tr>
            <th>NCVS</th>
            <th>Bucket</th>
            <th>Style</th>
            <th>Model</th>
            <th>PO</th>
            <th>PO Item</th>
            <th>Component</th>
    `;

      /* SIZE HEADER */
      sizes.forEach(function(size) {

        headerHtml += `
            <th>${size}</th>
        `;

      });

      /* TOTAL HEADER */

      headerHtml += `

            <th>Total</th>

        </tr>

    `;

      /* BODY */
      let bodyHtml = '';

      rows.forEach(function(row) {

        let totalQty = 0;

        bodyHtml += `

            <tr>

                <td>${row.ncvs ?? ''}</td>
                <td>${row.bucket ?? ''}</td>
                <td>${row.style ?? ''}</td>
                <td>${row.model ?? ''}</td>
                <td>${row.po ?? ''}</td>
                <td>${row.po_item ?? ''}</td>
                <td>${row.component ?? ''}</td>

        `;

        /* SIZE QTY */
        sizes.forEach(function(size) {

          let qty =
            row.sizes[size] ?? 0;

          totalQty += qty;

          /* EMPTY STYLE */
          let qtyDisplay =
            qty > 0 ? qty : '0';

          let tdClass =
            qty > 0 ?
            '' :
            'empty-size';

          bodyHtml += `

                <td class="${tdClass}">
                    ${qtyDisplay}
                </td>

            `;

        });

        /* TOTAL */
        bodyHtml += `

                <td class="fw-bold">
                    ${totalQty}
                </td>

            </tr>

        `;

      });

      /* RENDER FULL TABLE */
      $('#dashboardDetailTable')
        .html(

          `

        <thead>
            ${headerHtml}
        </thead>

        <tbody>
            ${bodyHtml}
        </tbody>

        `

        );

      /* INIT DATATABLE */

      setTimeout(function() {

        let table =
          $('#dashboardDetailTable')
          .DataTable({

            pageLength: 10,

            lengthMenu: [

              [10, 15, 20, 50, 100, -1],

              [10, 15, 20, 50, 100, 'All']

            ],

            responsive: false,
            ordering: false,
            searching: true,
            paging: true,
            info: true,
            autoWidth: true,
            language: {
              search: '',
              searchPlaceholder: 'Search...'
            }

          });

        /* ADJUST */

        table.columns.adjust();

      }, 100);

    }

    /* EXPORT DETAIL */

    $(document).on(
      'click',
      '#btnExportDashboardDetail',
      function() {

        let query =
          $.param(
            currentDetailParams
          );

        window.open(

          './../config/export_dashboard_detail.php?' +
          query,

          '_blank'

        );

      }
    );
  </script>

</body>

</html>