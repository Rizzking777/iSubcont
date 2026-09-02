<?php
// menghubungkan php dengan koneksi database
require_once __DIR__ . '/../config/function.php';
require_once __DIR__ . '/../config/auth.php';
checkAuth('wh_dashboard'); // cek apakah sudah login dan punya akses ke menu ini

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

  #componentList .list-group-item {

    border-left: 0;
    border-right: 0;

    font-size: 14px;

  }

  #componentList .main-component {

    font-weight: 700;

    color: #198754;

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
  $page = 'wh_dashboard';
  include_once __DIR__ . '/../includes/header.php';
  ?>
  <!-- End Header -->

  <main id="main" class="main">

    <div class="pagetitle text-black" style="background-color: #f0e6d2; padding: 10px 20px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
      <h1 style="font-size: 1.8rem; font-weight: 700; font-family: 'Roboto', sans-serif;">
        Warehouse Dashboard Monitoring
      </h1>
    </div>

    <section class="section">

      <div class="card shadow-sm mb-3">

        <div class="card-body">

          <div class="row align-items-end">

            <div class="col-md-4">

              <label class="form-label">

                Date Range

              </label>

              <input
                id="dateRange"
                class="form-control"
                type="text">

            </div>

            <div class="col-md-3 d-flex gap-2">

              <button
                id="btnResetDashboard"
                class="btn btn-secondary">

                <i class="bi bi-arrow-counterclockwise"></i>

                Reset

              </button>

              <button
                id="btnSearchDashboard"
                class="btn btn-success">

                <i class="bi bi-search"></i>

                Search

              </button>

            </div>

            <div class="col-md-5 text-end">

              <span class="text-muted">

                Period :

              </span>

              <strong id="currentPeriod">

                Today

              </strong>

            </div>

          </div>

        </div>

      </div>

      <div class="row g-3">

        <!-- RIGHT SIDE -->

        <div class="col-lg-12">

          <!-- PRE PROCESS -->
          <div class="dashboard-card mb-3">

            <div class="section-header">
              <i class="bi bi-grid"></i>
              <span>Warehouse Subcont Overview</span>
            </div>

            <div class="row align-items-center">

              <!-- LEFT -->
              <div class="col-lg-5">

                <div class="sub-card mb-3">

                  <div class="metric-group metric-group-full">

                    <div class="sub-title">
                      Ready to Vendor
                    </div>

                    <!-- IN -->
                    <div class="metric-row">

                      <div class="metric-label">
                        In
                      </div>

                      <div
                        id="tooltipIncomingWHIn"
                        class="progress progress-custom clickable-card"
                        data-bs-toggle="tooltip"
                        data-bs-placement="top"
                        data-section="incoming_wh"
                        data-type="in"
                        title="">

                        <div
                          id="barIncomingWHIn"
                          class="progress-bar bg-in"
                          style="width: 0%"></div>

                      </div>

                      <div
                        id="totalIncomingWHIn"
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
                        id="tooltipIncomingWHOut"
                        class="progress progress-custom clickable-card"
                        data-bs-toggle="tooltip"
                        data-bs-placement="top"
                        data-section="incoming_wh"
                        data-type="out"
                        title="">

                        <div
                          id="barIncomingWHOut"
                          class="progress-bar bg-out"
                          style="width: 0%"></div>

                      </div>

                      <div
                        id="totalIncomingWHOut"
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
                        id="tooltipIncomingWHInventory"
                        class="progress progress-custom clickable-card"
                        data-bs-toggle="tooltip"
                        data-bs-placement="top"
                        data-section="incoming_wh"
                        data-type="inventory"
                        title="">

                        <div
                          id="barIncomingWHInventory"
                          class="progress-bar bg-inventory"
                          style="width: 0%"></div>

                      </div>

                      <div
                        id="totalIncomingWHInventory"
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
                    id="chartIncomingWH"
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
                      Return from Vendor
                    </div>

                    <!-- IN -->
                    <div class="metric-row">

                      <div class="metric-label">
                        In
                      </div>

                      <div
                        id="tooltipReturnWHIn"
                        class="progress progress-custom clickable-card"
                        data-bs-toggle="tooltip"
                        data-bs-placement="top"
                        data-section="return_wh"
                        data-type="in"
                        title="">

                        <div
                          id="barReturnWHIn"
                          class="progress-bar bg-in"
                          style="width: 0%"></div>

                      </div>

                      <div
                        id="totalReturnWHIn"
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
                        id="tooltipReturnWHOut"
                        class="progress progress-custom clickable-card"
                        data-bs-toggle="tooltip"
                        data-bs-placement="top"
                        data-section="return_wh"
                        data-type="out"
                        title="">

                        <div
                          id="barReturnWHOut"
                          class="progress-bar bg-out"
                          style="width: 0%"></div>

                      </div>

                      <div
                        id="totalReturnWHOut"
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
                        id="tooltipReturnWHInventory"
                        class="progress progress-custom clickable-card"
                        data-bs-toggle="tooltip"
                        data-bs-placement="top"
                        data-section="return_wh"
                        data-type="inventory"
                        title="">

                        <div
                          id="barReturnWHInventory"
                          class="progress-bar bg-inventory"
                          style="width: 0%"></div>

                      </div>

                      <div
                        id="totalReturnWHInventory"
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
                    id="chartReturnWH"
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
                  table-bordered
                  table-striped
                  table-hover
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

    <!-- ========================================= -->
    <!-- COMPONENT DETAIL MODAL -->
    <!-- ========================================= -->

    <div class="modal fade"
      id="componentDetailModal"
      tabindex="-1"
      aria-hidden="true">

      <div class="modal-dialog modal-lg modal-dialog-scrollable">

        <div class="modal-content border-0 shadow">

          <div class="modal-header">

            <h5 class="modal-title">
              <i class="bi bi-diagram-3 me-2"></i>
              Component Detail
            </h5>

            <button
              type="button"
              class="btn-close"
              data-bs-dismiss="modal">
            </button>

          </div>

          <div class="modal-body">

            <!-- HEADER INFO -->

            <div class="border rounded p-3 mb-3">

              <div class="row">

                <div class="col-md-6">

                  <div>
                    <strong>NCVS :</strong>
                    <span id="cd_ncvs">-</span>
                  </div>

                  <div>
                    <strong>Bucket :</strong>
                    <span id="cd_bucket">-</span>
                  </div>

                  <div>
                    <strong>PO - PO Item :</strong>
                    <span id="cd_po">-</span>
                  </div>

                </div>

                <div class="col-md-6">

                  <div>
                    <strong>Style :</strong>
                    <span id="cd_style">-</span>
                  </div>

                  <div>
                    <strong>Model :</strong>
                    <span id="cd_model">-</span>
                  </div>

                  <div>
                    <strong>Main Component :</strong>
                    <span id="cd_component">-</span>
                  </div>

                </div>

              </div>

            </div>

            <hr>

            <h6 class="fw-bold mb-3">

              List Component

            </h6>

            <div class="table-responsive">

              <table
                class="table table-bordered table-striped align-middle"
                id="componentTable">

                <thead class="table-secondary">

                  <tr>
                    <th width="10%">No</th>
                    <th>Component</th>
                  </tr>

                </thead>

                <tbody id="componentList">

                  <tr>

                    <td colspan="2" class="text-center text-muted">

                      No data

                    </td>

                  </tr>

                </tbody>

              </table>

            </div>

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

  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css">
  <script src="https://cdn.jsdelivr.net/momentjs/latest/moment.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>

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
      incoming_wh: '#5f84ad',
      return_wh: '#5f84ad'

    };

    const dashboardConfig = {

      cutting: {

        prefix: "",

        chartId: "#chartCutting",

        chartKey: "chartCutting",

        color: chartColor.cutting,

        unit: "Prs"

      },

      incoming_wh: {

        prefix: "IncomingWH",

        chartId: "#chartIncomingWH",

        chartKey: "chartIncomingWH",

        color: chartColor.incoming_wh,

        unit: "Prs"

      },

      return_wh: {

        prefix: "ReturnWH",

        chartId: "#chartReturnWH",

        chartKey: "chartReturnWH",

        color: chartColor.return_wh,

        unit: "Pairs"

      }

    };

    $(function() {

      initDateRange();

      loadDashboard();

      setInterval(loadDashboard, 60000);

    });

    function initDateRange() {

      const today = moment();

      $("#dateRange").daterangepicker({

        autoApply: true,

        opens: "left",

        startDate: today,

        endDate: today,

        locale: {

          format: "YYYY-MM-DD"

        }

      });

      updateCurrentPeriod();

    }

    function updateCurrentPeriod() {

      let picker = $("#dateRange").data("daterangepicker");

      let start = picker.startDate.format("YYYY-MM-DD");

      let end = picker.endDate.format("YYYY-MM-DD");

      $("#currentPeriod").text(

        start == end

        ?

        start

        :

        start + " s/d " + end

      );

    }

    $("#btnSearchDashboard").on("click", function() {

      updateCurrentPeriod();

      loadDashboard();

    });

    $("#btnResetDashboard").on("click", function() {

      let picker = $("#dateRange").data("daterangepicker");

      picker.setStartDate(moment());

      picker.setEndDate(moment());

      updateCurrentPeriod();

      loadDashboard();

    });

    function loadDashboard() {

      let picker = $("#dateRange").data("daterangepicker");

      let dateFrom =

        picker.startDate.format("YYYY-MM-DD");

      let dateTo =

        picker.endDate.format("YYYY-MM-DD");

      $.ajax({
        url: './../config/get_warehouse_dashboard.php',
        type: 'GET',
        dataType: 'json',
        data: {

          date_from: dateFrom,

          date_to: dateTo

        },

        success: function(response) {

          // renderSection("cutting", response.cutting);

          renderSection("incoming_wh", response.incoming_wh);

          renderSection("return_wh", response.return_wh);

          initTooltip();

        },

        error: function(xhr) {
          console.log(xhr.responseText);
        }

      });

    }

    function initTooltip() {

      $('[data-bs-toggle="tooltip"]').tooltip('dispose');

      const tooltipTriggerList = [].slice.call(
        document.querySelectorAll('[data-bs-toggle="tooltip"]')
      );

      tooltipTriggerList.map(function(tooltipTriggerEl) {

        return new bootstrap.Tooltip(tooltipTriggerEl);

      });

    }

    function calculateProgress(summary) {

      const max = Math.max(

        summary.in,

        summary.out,

        summary.inventory,

        1

      );

      return {

        in: (summary.in / max) * 100,

        out: (summary.out / max) * 100,

        inventory: (summary.inventory / max) * 100

      };

    }

    function setMetric(prefix, name, value, width, unit) {

      $("#total" + prefix + name)

        .text(value.toLocaleString());

      $("#bar" + prefix + name)

        .css("width", width + "%");

      $("#tooltip" + prefix + name)

        .attr(

          "data-bs-original-title",

          name + " : " + value.toLocaleString() + " " + unit

        )

        .attr(

          "data-value",

          value

        );

    }

    function renderOverview(section, data) {

      const cfg = dashboardConfig[section];

      const {
        summary
      } = data;

      const progress = calculateProgress(summary);

      setMetric(

        cfg.prefix,

        "In",

        summary.in,

        progress.in,

        cfg.unit

      );

      setMetric(

        cfg.prefix,

        "Out",

        summary.out,

        progress.out,

        cfg.unit

      );

      setMetric(

        cfg.prefix,

        "Inventory",

        summary.inventory,

        progress.inventory,

        cfg.unit

      );

    }

    function renderSection(section, data) {

      if (!data) {

        console.warn(section + " not found");

        return;

      }

      renderOverview(section, data);

      renderChart(section, data);

    }

    function renderChart(section, data) {

      const cfg = dashboardConfig[section];

      const categories = data.chart.categories ?? [];

      const seriesData = data.chart.series ?? [];

      let dynamicChartWidth = Math.max(categories.length * 70, 350);

      if (dashboardCharts[cfg.chartKey]) {
        dashboardCharts[cfg.chartKey].destroy();
      }

      dashboardCharts[cfg.chartKey] = new ApexCharts(

        document.querySelector(cfg.chartId),

        {

          chart: {

            type: "bar",

            height: 280,

            width: dynamicChartWidth,

            toolbar: {
              show: false
            },

            animations: {
              enabled: true,
              easing: "easeinout",
              speed: 700
            },

            events: {

              dataPointSelection: function(event, chartContext, config) {

                let ncvs = categories[config.dataPointIndex];

                let value = seriesData[config.dataPointIndex];

                if (value <= 0) return;

                openDashboardDetail({

                  section: section,

                  type: "inventory",

                  ncvs: ncvs

                });

              }

            }

          },

          legend: {
            show: false
          },

          series: [

            {

              name: "Inventory",

              data: seriesData

            }

          ],

          xaxis: {

            categories: categories

          },

          colors: [

            cfg.color

          ],

          plotOptions: {

            bar: {

              borderRadius: 6,

              columnWidth: "35%"

            }

          },

          tooltip: {

            theme: "light",

            y: {

              formatter: function(val) {

                return val.toLocaleString() + " " + cfg.unit;

              }

            }

          },

          dataLabels: {

            enabled: true

          },

          grid: {

            borderColor: "#e2e8f0"

          }

        }

      );

      dashboardCharts[cfg.chartKey].render();

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

      let picker = $("#dateRange").data("daterangepicker");

      $.ajax({

        url: "./../config/get_warehouse_dashboard_detail.php",

        type: "GET",

        dataType: "json",

        data: {

          section: params.section,

          type: params.type,

          ncvs: params.ncvs ?? "",

          date_from: picker.startDate.format("YYYY-MM-DD"),

          date_to: picker.endDate.format("YYYY-MM-DD")

        },

        success: function(response) {

          renderDashboardDetailTable(response);

          $("#dashboardModalTitle")
            .text("Detail Warehouse Dashboard Monitoring");

          $("#dashboardModalSubtitle")
            .text(params.section.replaceAll("_", " ").toUpperCase());

          $("#dashboardDetailModal").modal("show");

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
                <td>

    <a href="#"
       class="component-detail text-decoration-none fw-semibold"

       data-id_group="${row.id_group ?? ''}"
       data-ncvs="${row.ncvs ?? ''}"
       data-bucket="${row.bucket ?? ''}"
       data-style="${row.style ?? ''}"
       data-model="${row.model ?? ''}"
       data-po="${row.po ?? ''}"
       data-po_item="${row.po_item ?? ''}"
       data-component="${row.component ?? ''}">

        ${row.component ?? ''}

    </a>

</td>

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

        let picker = $("#dateRange").data("daterangepicker");

        let query = $.param({

          ...currentDetailParams,

          date_from: picker.startDate.format("YYYY-MM-DD"),

          date_to: picker.endDate.format("YYYY-MM-DD")

        });

        window.open(

          './../config/export_dashboard_detail.php?' +
          query,

          '_blank'

        );

      }
    );

    $(document).on(
      'click',
      '.component-detail',
      function(e) {

        e.preventDefault();

        openComponentModal({

          id_group: $(this).data("id_group"),
          ncvs: $(this).data('ncvs'),
          bucket: $(this).data('bucket'),
          style: $(this).data('style'),
          model: $(this).data('model'),
          po: $(this).data('po'),
          po_item: $(this).data('po_item'),
          component: $(this).data('component')

        });

      }
    );

    function openComponentModal(data) {

      $("#cd_ncvs").text(data.ncvs);

      $("#cd_bucket").text(data.bucket);

      $("#cd_style").text(data.style);

      $("#cd_model").text(data.model);

      $("#cd_po").text(data.po + " - " + data.po_item);

      $("#cd_component").text(data.component);

      $("#componentList").html(`
          <tr>
              <td colspan="2" class="text-center py-3">
                  Loading component...
              </td>
          </tr>
      `);

      $.ajax({

        url: './../config/get_production_dashboard_component.php',

        type: 'GET',

        dataType: 'json',

        data: {

          id_group: data.id_group

        },

        success: function(response) {

          let html = '';

          response.data.forEach(function(item, index) {

            html += `
        <tr>

            <td class="text-center">
                ${index + 1}
            </td>

            <td>
                ${item.nama_komponen}
                ${item.is_main == 1 ? ' *' : ''}
            </td>

        </tr>
    `;

          });

          if (html === '') {

            html = `
        <tr>
            <td colspan="2" class="text-center text-muted">
                No component found
            </td>
        </tr>
    `;

          }

          $("#componentList").html(html);

        }

      });

      $("#componentDetailModal").modal("show");

    }
  </script>

</body>

</html>