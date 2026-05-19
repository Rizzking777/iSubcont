<?php
// menghubungkan php dengan koneksi database
require_once __DIR__ . '/../config/function.php';
require_once __DIR__ . '/../config/auth.php';
checkAuth('tracking_status'); // cek apakah sudah login dan punya akses ke menu ini

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

  .card {
    border: none !important;
    border-radius: 16px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
  }

  .form-control,
  .select2-container--default .select2-selection--single {
    height: 40px !important;
    border-radius: 10px !important;
    border: 1px solid #dbe2ea !important;
  }

  .btn {
    border-radius: 10px !important;
    padding: 8px 18px;
  }

  .daterangepicker .drp-calendar.left {
    border-right: none !important;
  }

  .daterangepicker .drp-calendar.right {
    display: none !important;
  }

  .daterangepicker {
    width: auto !important;
  }

  .tracking-board {
    display: flex;
    gap: 18px;
    overflow-x: auto;
    overflow-y: hidden;
    padding-bottom: 10px;
    align-items: flex-start;
    scroll-behavior: smooth;
  }

  .tracking-column {
    min-width: 320px;
    max-width: 320px;
    flex-shrink: 0;
    display: flex;
    flex-direction: column;
    gap: 16px;
  }

  .tracking-header {
    background: white;
    border-radius: 18px;
    padding: 10px 14px;
    box-shadow:
      0 2px 10px rgba(0, 0, 0, .05);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
  }

  .tracking-header i {
    font-size: 13px;
    color: #6b7280;
  }

  .tracking-title {
    font-size: 15px;
    font-weight: 700;
  }

  .tracking-count {
    background: #eef2ff;
    color: #4f46e5;
    border-radius: 999px;
    padding: 2px 10px;
    font-size: 12px;
    font-weight: 600;
  }

  .tracking-card {
    background: white;
    border-radius: 18px;
    padding: 18px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, .05);
    border-left: 6px solid #3b82f6;
    transition: .2s;
  }

  .tracking-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 18px rgba(0, 0, 0, .08);
  }

  .tracking-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 600;
    background: #eef2ff;
    color: #4f46e5;
  }

  .tracking-label {
    font-size: 12px;
    color: #6b7280;
    margin-bottom: 2px;
  }

  .tracking-value {
    font-size: 15px;
    font-weight: 600;
    color: #111827;
    line-height: 1.2;
  }

  .tracking-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 14px 24px;
    margin-top: 18px;
  }

  .tracking-divider {
    border-top: 1px solid #e5e7eb;
    margin: 18px 0 14px;
  }

  .tracking-date {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
    color: #374151;
  }

  .tracking-btn {
    margin-top: 14px;
    width: 100%;
    font-size: 13px;
    padding: 6px 10px;
    border-radius: 10px;
  }

  @media (max-width: 1400px) {
    .tracking-board {
      grid-template-columns:
        repeat(3, 1fr);
    }
  }

  @media (max-width: 992px) {
    .tracking-board {
      grid-template-columns:
        repeat(2, 1fr);
    }
  }

  @media (max-width: 576px) {
    .tracking-board {
      grid-template-columns:
        repeat(1, 1fr);
    }
  }

  .tracking-board::-webkit-scrollbar {
    height: 8px;
  }

  .tracking-board::-webkit-scrollbar-thumb {
    background: #d1d5db;
    border-radius: 999px;
  }

  .timeline-item {
    display: flex;
    gap: 16px;
    position: relative;
    min-height: 95px;
  }

  .timeline-icon-wrapper {
    display: flex;
    flex-direction: column;
    align-items: center;
    width: 30px;
    flex-shrink: 0;
  }

  .timeline-line {
    width: 2px;
    flex: 1;
    background: #d1d5db;
    margin-top: 4px;
  }

  .timeline-circle {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
  }

  .timeline-circle.done {
    background: #7c9b5e;
    color: white;
    font-size: 12px;
  }

  .timeline-circle.current {
    border: 2px solid #7c9b5e;
    background: white;
  }

  .timeline-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #7c9b5e;
  }

  .timeline-circle.pending {
    border: 1.5px solid #7c9b5e;
    background: white;
  }

  .timeline-title {
    font-size: 16px;
    font-weight: 500;
    color: #7c9b5e;
  }

  .pending-text {
    color: #8b8b8b;
  }

  .timeline-date {
    margin-top: 4px;
    font-size: 14px;
    color: #6b7280;
  }

  .timeline-pickup {
    margin-top: 4px;
    font-size: 14px;
    color: #6b7280;
  }

  .timeline-user {
    font-size: 14px;
    color: #64748b;
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

  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css">

</head>

<body>

  <!-- Header -->
  <?php
  $page = 'tracking_status';
  include_once __DIR__ . '/../includes/header.php';
  ?>
  <!-- End Header -->

  <main id="main" class="main">

    <div class="pagetitle text-black" style="background-color: #f0e6d2; padding: 10px 20px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
      <h1 style="font-size: 1.8rem; font-weight: 700; font-family: 'Roboto', sans-serif;">
        Tracking Status Dashboard
      </h1>
    </div>

    <div class="card border-0 shadow-sm mb-4 fade-in">
      <div class="card-body p-4">
        <form id="filterForm">

          <!-- ROW 1 -->
          <div class="row g-3">

            <!-- DATE -->
            <div class="col-md-3">
              <label class="form-label fw-semibold">
                Date Range
              </label>

              <input
                type="text"
                id="date_range"
                name="date_range"
                class="form-control"
                placeholder="Select date range">
            </div>

            <!-- BUCKET -->
            <div class="col-md-3">
              <label class="form-label fw-semibold">
                Bucket
                <span class="text-danger">*</span>
              </label>

              <select
                id="bucket"
                name="bucket"
                class="form-control select2-remote">
              </select>
            </div>

            <!-- PO CODE -->
            <div class="col-md-3">
              <label class="form-label fw-semibold">
                PO Code
              </label>

              <select
                id="po_code"
                name="po_code"
                class="form-control select2-remote">
              </select>
            </div>

            <!-- PO ITEM -->
            <div class="col-md-3">
              <label class="form-label fw-semibold">
                PO Item
              </label>

              <select
                id="po_item"
                name="po_item"
                class="form-control select2-remote">
              </select>
            </div>
          </div>

          <!-- ROW 2 -->
          <div class="row g-3 mt-1">

            <!-- NCVS -->
            <div class="col-md-3">
              <label class="form-label fw-semibold">
                NCVS
              </label>

              <select
                id="ncvs"
                name="ncvs"
                class="form-control select2-remote">
              </select>
            </div>

            <!-- MODEL -->
            <div class="col-md-3">
              <label class="form-label fw-semibold">
                Model
              </label>

              <select
                id="model"
                name="model"
                class="form-control select2-remote">
              </select>
            </div>

            <!-- STYLE -->
            <div class="col-md-3">
              <label class="form-label fw-semibold">
                Style
              </label>

              <select
                id="style"
                name="style"
                class="form-control select2-remote">
              </select>
            </div>

            <!-- VENDOR -->
            <div class="col-md-3">
              <label class="form-label fw-semibold">
                Vendor
              </label>

              <select
                id="vendor"
                name="vendor"
                class="form-control select2-remote">
              </select>
            </div>

          </div>

          <!-- ROW 3 -->
          <div class="col-md-12 mt-3">
            <button type="button" id="resetBtn" class="btn btn-secondary"> <i class="bi bi-arrow-counterclockwise"></i> Reset</button>
            <button type="button" id="searchBtn" class="btn btn-success" disabled><i class="bi bi-search"></i> Search</button>
          </div>

        </form>

      </div>
    </div>

    <div id="trackingBoardWrapper">

      <!-- EMPTY STATE -->
      <div
        id="emptyState"
        class="card border-0 shadow-sm text-center py-5">

        <div class="mb-3 text-secondary">

          <i
            class="bi bi-box-seam"
            style="font-size: 3rem;">
          </i>

        </div>

        <h5 class="fw-bold">
          Tracking Dashboard
        </h5>

        <div class="text-muted">
          Pilih filter lalu klik Search.
        </div>

      </div>
    </div>

    <div
      class="modal fade"
      id="trackingDetailModal"
      tabindex="-1">

      <div
        class="modal-dialog
               modal-xl
               modal-dialog-scrollable">

        <div
          class="modal-content border-0 shadow-lg">

          <div class="modal-body">

            <div id="trackingDetailContent">

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
    $(document).ready(function() {

      // STATUS CONFIG
      const statusConfig = {

        "SM": {
          border: "#3b82f6",
          badgeBg: "#dbeafe",
          badgeText: "#2563eb",
          headerBg: "#dbeafe",
          headerText: "#2563eb",
          icon: "bi-box-seam"
        },

        "WH": {
          border: "#6b7280",
          badgeBg: "#e5e7eb",
          badgeText: "#374151",
          headerBg: "#e5e7eb",
          headerText: "#374151",
          icon: "bi-building"
        },

        "Vendor": {
          border: "#f59e0b",
          badgeBg: "#fef3c7",
          badgeText: "#d97706",
          headerBg: "#fef3c7",
          headerText: "#d97706",
          icon: "bi-truck"
        },

        "Ready Transfer": {
          border: "#06b6d4",
          badgeBg: "#cffafe",
          badgeText: "#0891b2",
          headerBg: "#cffafe",
          headerText: "#0891b2",
          icon: "bi-arrow-left-right"
        },

        "Ready Pickup": {
          border: "#8b5cf6",
          badgeBg: "#ede9fe",
          badgeText: "#7c3aed",
          headerBg: "#ede9fe",
          headerText: "#7c3aed",
          icon: "bi-hand-index-thumb"
        },

        "Done": {
          border: "#22c55e",
          badgeBg: "#dcfce7",
          badgeText: "#15803d",
          headerBg: "#dcfce7",
          headerText: "#15803d",
          icon: "bi-check-circle"
        }
      };

      // INIT SELECT2 AJAX
      function initSelect2(id, action, placeholder) {

        $(id).select2({
          width: "100%",
          placeholder: placeholder,
          allowClear: true,
          minimumInputLength: 1,
          ajax: {
            url: "./../config/get_options.php",
            type: "POST",
            dataType: "json",
            delay: 250,
            data: function(params) {

              return {
                action: action,
                search: params.term,
                date_range: $("#date_range").val(),
                bucket: $("#bucket").val(),
                ncvs: $("#ncvs").val(),
                po_code: $("#po_code").val(),
                po_item: $("#po_item").val(),
                job_order: $("#job_order").val(),
                style: $("#style").val(),
                model: $("#model").val(),
                vendor: $("#vendor").val()
              };
            },

            processResults: function(data) {
              return {
                results: data.results || []
              };
            }
          }
        });

        // AUTO FOCUS SELECT2 SEARCH
        $(id).on(
          'select2:open',

          function() {
            setTimeout(() => {

              document
                .querySelector(
                  '.select2-container--open .select2-search__field'
                )

                ?.focus();

            }, 0);
          }
        );
      }

      // INIT FILTER
      initSelect2(
        "#bucket",
        "searchBucket",
        "Bucket"
      );

      initSelect2(
        "#ncvs",
        "searchNCVS",
        "NCVS"
      );

      initSelect2(
        "#po_code",
        "searchPOCode",
        "PO Code"
      );

      initSelect2(
        "#po_item",
        "searchPOItem",
        "PO Item"
      );

      initSelect2(
        "#job_order",
        "searchJobOrder",
        "Job Order"
      );

      initSelect2(
        "#style",
        "searchStyle",
        "Style"
      );

      initSelect2(
        "#model",
        "searchModel",
        "Model"
      );

      initSelect2(
        "#vendor",
        "searchVendor",
        "Vendor"
      );

      // SEARCH BUTTON ENABLE
      function toggleSearchBtn() {
        if ($("#bucket").val()) {
          $("#searchBtn")
            .prop("disabled", false);
        } else {
          $("#searchBtn")
            .prop("disabled", true);
        }
      }

      $("#bucket").on(
        "change",
        function() {
          toggleSearchBtn();
        }
      );

      // DATE RANGE
      $('#date_range').daterangepicker({
        autoUpdateInput: false,
        autoApply: true,
        linkedCalendars: true,
        showDropdowns: true,
        opens: 'left',
        locale: {
          cancelLabel: 'Clear',
          format: 'DD MMM YYYY'
        }
      });

      $('#date_range').on(
        'apply.daterangepicker',

        function(ev, picker) {
          $(this).val(

            picker.startDate.format(
              'DD MMM YYYY'
            ) +
            ' - ' +
            picker.endDate.format(
              'DD MMM YYYY'
            )
          );
        }
      );

      $('#date_range').on(
        'cancel.daterangepicker',
        function() {
          $(this).val('');
        }
      );

      $('#date_range').on(
        'focus',
        function() {
          $(this).click();
        }
      );

      // SEARCH
      $("#searchBtn").on(
        "click",
        function() {
          if (
            !$("#bucket").val()
          ) {
            alert(
              "Harap pilih Bucket terlebih dahulu!"
            );
            return;
          }

          $.ajax({
            url: './../config/get_tracking_dashboard.php',
            type: 'POST',
            dataType: 'json',
            data: {
              date_range: $("#date_range").val(),
              bucket: $("#bucket").val(),
              ncvs: $("#ncvs").val(),
              po_code: $("#po_code").val(),
              po_item: $("#po_item").val(),
              style: $("#style").val(),
              model: $("#model").val(),
              vendor: $("#vendor").val()
            },

            beforeSend: function() {
              $('#trackingBoardWrapper').html(`

            <div class="card border-0 shadow-sm">
                <div class="card-body py-5 text-center">
                    <div
                        class="spinner-grow text-primary"
                        role="status">
                    </div>
                    <div
                        class="spinner-grow text-secondary mx-2"
                        role="status">
                    </div>
                    <div
                        class="spinner-grow text-info"
                        role="status">
                    </div>
                    <div class="mt-4 fw-semibold">
                        Loading tracking dashboard...
                    </div>
                </div>
            </div>
    `);
            },

            success: function(res) {

              // EMPTY DATA
              if (
                !res.status ||
                Object.values(res.boards)
                .every(board => board.length === 0)
              ) {

                $('#trackingBoardWrapper').html(`

        <div
            class="card border-0 shadow-sm text-center py-5 fade-in">
            <div class="mb-3 text-secondary">
                <i
                    class="bi bi-inbox"
                    style="font-size:3rem;">
                </i>
            </div>
            <h5 class="fw-bold mb-2">
                Data Not Available
            </h5>
            <div class="text-muted">
                Tidak ada data tracking
                yang sesuai dengan filter.
            </div>
        </div>
    `);

                return;
              }

              renderBoard(
                res.boards
              );
            },

            error: function(xhr) {
              console.log(xhr);
              $('#trackingBoardWrapper').html(`

                        <div
                            class="card border-0 shadow-sm text-center py-5">
                            <div class="mb-3 text-danger">
                                <i
                                    class="bi bi-exclamation-triangle"
                                    style="font-size:3rem;">
                                </i>
                            </div>
                            <h5 class="fw-bold text-danger">
                                Server Error
                            </h5>
                        </div>
                    `);
            }
          });

        }
      );

      // RESET
      $("#resetBtn").on(
        "click",

        function() {
          $("#filterForm")[0]
            .reset();

          $(".select2-remote")
            .val(null)
            .trigger("change");

          $('#trackingBoardWrapper').html(`
                <div
                    id="emptyState"
                    class="card border-0 shadow-sm text-center py-5">
                    <div class="mb-3 text-secondary">
                        <i
                            class="bi bi-box-seam"
                            style="font-size: 3rem;">
                        </i>
                    </div>
                    <h5 class="fw-bold">
                        Tracking Dashboard
                    </h5>
                    <div class="text-muted">
                        Pilih filter lalu klik Search.
                    </div>
                </div>
            `);

          toggleSearchBtn();
        }
      );

      // INITIAL
      toggleSearchBtn();

      // RENDER BOARD
      function renderBoard(boards) {
        let html = `
            <div class="tracking-board">
        `;

        Object.keys(boards).forEach(status => {
          const cfg =
            statusConfig[status];

          html += `
                <div class="tracking-column">
                    <div class="tracking-header">
                        <i class="bi ${cfg.icon}"></i>
                        <div class="tracking-title">
                            ${status}
                        </div>
                        <div
                            class="tracking-count"
                            style="
                                background:${cfg.headerBg};
                                color:${cfg.headerText};
                            ">
                            ${boards[status].length}
                        </div>
                    </div>
            `;

          boards[status].forEach(item => {
            html += generateRealCard(
              status,
              item
            );
          });

          html += `
                </div>
            `;
        });

        html += `
            </div>
        `;

        $('#trackingBoardWrapper')
          .html(html);
      }

      // GENERATE CARD
      function generateRealCard(status, item) {
        const cfg =
          statusConfig[status];

        return `
            <div
                class="tracking-card"
                style="
                    border-left:
                    6px solid ${cfg.border};
                ">
                <!-- MODEL -->
                <div
                  class="d-flex
                        justify-content-between
                        align-items-start
                        mb-3">

    <!-- MODEL -->
    <div
        class="fw-bold"
        style="font-size: 17px;">

        ${item.model ?? '-'}

    </div>

    <!-- RIGHT BADGES -->
    <div
        class="d-flex
               flex-column
               align-items-end
               gap-1">

        <!-- LOT -->
        <span
            class="badge rounded-pill"

            style="
                background:#eef2ff;
                color:#4338ca;
                font-size:11px;
            ">

            ${item.total_lot} Lot

        </span>

        <!-- MOVEMENT -->
        <span
            class="badge bg-${item.movement_color}"
            style="font-size:10px;">

            ${item.movement_status}

        </span>
    </div>
</div>

                <!-- GRID -->
                <div class="tracking-grid">
                    <div>
                        <div class="tracking-label">
                            PO - PO Item
                        </div>
                        <div class="tracking-value">
                            ${item.po_code ?? '-'}
                            -
                            ${item.po_item ?? '-'}
                        </div>
                    </div>

                    <div>
                        <div class="tracking-label">
                            NCVS
                        </div>
                        <div class="tracking-value">
                            ${item.ncvs ?? '-'}
                        </div>
                    </div>

                    <div>
                        <div class="tracking-label">
                            Style
                        </div>
                        <div class="tracking-value">
                            ${item.style ?? '-'}
                        </div>
                    </div>

                    <div>
                        <div class="tracking-label">
                            Bucket
                        </div>
                        <div class="tracking-value">
                            ${item.bucket ?? '-'}
                        </div>
                    </div>

                    <div>
                        <div class="tracking-label">
                            Component
                        </div>
                        <div class="tracking-value">
                            ${item.dashboard_component ?? '-'}
                        </div>
                    </div>

                    <div>
                        <div class="tracking-label">
                            Vendor
                        </div>
                        <div class="tracking-value">
                            ${item.nm_vendor ?? '-'}
                        </div>
                    </div>
                </div>

                <!-- FOOTER -->
                <div class="tracking-divider"></div>

                <div
                    class="d-flex
                           justify-content-between
                           align-items-center">

                    <div class="tracking-date">
                        <i class="bi bi-calendar-event"></i>
                        ${item.updated_at ?? '-'}
                    </div>

                    <span
                        class="tracking-badge"
                        style="
                            background:${cfg.badgeBg};
                            color:${cfg.badgeText};
                        ">
                        ${status}

                    </span>
                </div>

                <button
                    class="btn btn-success btn-sm tracking-btn btn-detail"
                    data-batch="${item.batch_transaksi}"
                    data-component="${item.dashboard_component}"
                    data-status="${item.movement_status}"
                    data-board="${status}">
                    View Detail
                </button>

            </div>
        `;
      }

      // DETAIL MODAL
      $(document).on(
        'click',
        '.btn-detail',
        function() {

          const batch =
            $(this).data('batch');

          const component =
            $(this).data('component');

          const board =
            $(this).data('board');

          const movementStatus =
            $(this).data('status');

          // OPEN MODAL
          $('#trackingDetailModal').modal('show');

          // LOADING
          $('#trackingDetailContent').html(`

      <div class="text-center py-5">
        <div class="spinner-border text-primary"></div>
        <div class="mt-3">
          Loading detail...
        </div>
      </div>

    `);


          // AJAX
          $.ajax({
            url: './../config/get_tracking_dashboard_detail.php',
            type: 'POST',
            dataType: 'json',
            data: {
              batch_transaksi: batch,
              component: component,
              board: board,
              movement_status: movementStatus
            },

            success: function(res) {

              console.log(res);

              // VALIDATION
              if (!res.status) {

                $('#trackingDetailContent').html(`

            <div class="text-center py-5 text-danger">
              ${res.message}
            </div>

          `);

                return;
              }

              // SUMMARY
              const s =
                res.summary;

              // BOARD CONFIG
              const boardCfg =
                statusConfig[s.board];

              // STATUS BADGE CONFIG
              function getMovementBadge(status) {

                if (status == 'IN PROCESS') {
                  return {
                    bg: '#dbeafe',
                    text: '#2563eb'
                  };
                }

                if (status == 'TRANSIT') {
                  return {
                    bg: '#fef3c7',
                    text: '#d97706'
                  };
                }

                if (status == 'READY') {
                  return {
                    bg: '#ede9fe',
                    text: '#7c3aed'
                  };
                }

                if (status == 'FINISH') {
                  return {
                    bg: '#dcfce7',
                    text: '#15803d'
                  };
                }

                return {
                  bg: '#e5e7eb',
                  text: '#374151'
                };
              }

              // UNIQUE SIZES
              let allSizes = [];

              res.lot_detail.forEach(lot => {

                Object.keys(lot.sizes)
                  .forEach(size => {
                    if (
                      !allSizes.includes(size)
                    ) {
                      allSizes.push(size);
                    }
                  });
              });

              allSizes.sort();

              // TABLE HEADER
              let tableHeader = `
          <th>Lot</th>
        `;

              allSizes.forEach(size => {

                tableHeader += `
            <th>${size}</th>
          `;
              });

              tableHeader += `
          <th>Total</th>
          <th>Status</th>
          <th>Last Gate</th>
        `;

              // TABLE BODY
              let tableBody = '';

              res.lot_detail.forEach(lot => {

                const badgeCfg =
                  getMovementBadge(
                    lot.movement_status
                  );

                const totalQty =
                  Object.values(
                    lot.sizes
                  ).reduce(
                    (total, qty) => total + qty,
                    0
                  );

                let row = `
            <tr>
              <td>
                <span class="fw-bold">
                  ${lot.lot}
                </span>
              </td>
          `;

                allSizes.forEach(size => {
                  const qty =
                    lot.sizes[size] ?? 0;
                  row +=
                    qty == 0 ?
                    `
      <td
  style="
    background:#cbd5e1;
  ">

</td>
    ` :

                    `

      <td>

        ${qty}

      </td>

    `;
                });



                row += `

            <td>

              <span
                class="fw-bold">

                ${totalQty}

              </span>

            </td>

            <td>

              <span
                class="badge rounded-pill"

                style="
                  background:${badgeCfg.bg};
                  color:${badgeCfg.text};
                  font-size:11px;
                  padding:6px 10px;
                ">

                ${lot.movement_status}

              </span>

            </td>

            <td>

              <span class="small text-muted">

                ${lot.last_gate_label}

              </span>

            </td>

          </tr>

          `;



                tableBody += row;
              });



              // =====================================
              // STANDARD TIMELINE
              // =====================================
              const standardTimeline = [

                {
                  gate: 'SM_SUBCONT_FROM_CUT',
                  label: 'In SM Subcont'
                },

                {
                  gate: 'SM_SUBCONT_TO_WH_SUBCONT',
                  label: 'Out SM Subcont'
                },

                {
                  gate: 'WH_SUBCONT_FROM_SM_SUBCONT',
                  label: 'In WH Subcont'
                },

                {
                  gate: 'WH_SUBCONT_TO_VENDOR',
                  label: 'Out WH Subcont'
                },

                {
                  gate: 'VENDOR_FROM_WH_SUBCONT',
                  label: 'In Vendor'
                },

                {
                  gate: 'VENDOR_TO_WH_SUBCONT',
                  label: 'Out Vendor'
                },

                {
                  gate: 'WH_SUBCONT_FROM_VENDOR',
                  label: 'Return WH / Ready Transfer'
                },

                {
                  gate: 'WH_SUBCONT_TO_SM_SUBCONT',
                  label: 'Transfer To SM Subcont'
                },

                {
                  gate: 'SM_SUBCONT_FROM_WH_SUBCONT',
                  label: 'Ready Pickup'
                },

                {
                  gate: 'SM_SUBCONT_TO_NCVS',
                  label: 'Finish'
                }
              ];

              // =====================================
              // CURRENT GATE
              // =====================================
              const currentGate =
                s.last_gate;



              // =====================================
              // CURRENT INDEX
              // =====================================
              const currentIndex =
                standardTimeline.findIndex(
                  x => x.gate == currentGate
                );

              if (currentIndex < 0) {

                console.error(
                  'Invalid current gate:',
                  currentGate
                );

                return;
              }



              // =====================================
              // FINISH
              // =====================================
              const isFinish =
                currentGate ==
                'SM_SUBCONT_TO_NCVS';


              // =====================================
              // TIMELINE HTML
              // =====================================
              let timelineHtml = '';



              standardTimeline.forEach((step, index) => {

                // =====================================
                // STEP INDEX
                // =====================================
                const stepIndex =
                  index;

                const eventInfo =
                  res.timeline_event?.[
                    step.gate
                  ];

                // =====================================
                // STATUS
                // =====================================
                const isDone =
                  stepIndex < currentIndex;



                const isCurrent =
                  stepIndex == currentIndex &&
                  !isFinish;


                // =====================================
                // ICON
                // =====================================
                let iconHtml = '';



                // FINISH
                if (
                  isFinish &&
                  stepIndex <= currentIndex
                ) {

                  iconHtml = `

      <div class="timeline-circle done">

        <i class="bi bi-check"></i>

      </div>

    `;
                }



                // DONE
                else if (isDone) {

                  iconHtml = `

      <div class="timeline-circle done">

        <i class="bi bi-check"></i>

      </div>

    `;
                }



                // CURRENT
                else if (isCurrent) {

                  iconHtml = `

      <div class="timeline-circle current">

        <div class="timeline-dot"></div>

      </div>

    `;
                }



                // PENDING
                else {

                  iconHtml = `

      <div class="timeline-circle pending">

      </div>

    `;
                }


                // =====================================
                // RENDER
                // =====================================
                timelineHtml += `

  <div class="timeline-item">

    <div class="timeline-icon-wrapper">

      ${iconHtml}

      ${
        index != standardTimeline.length - 1
          ? '<div class="timeline-line"></div>'
          : ''
      }

    </div>

    <div class="timeline-content">

      <div
        class="
          timeline-title
          ${
            (!isDone && !isCurrent && !isFinish)
              ? 'pending-text'
              : ''
          }
        ">

        ${step.label}

      </div>

      ${
  eventInfo

  ?

  `

    <div class="timeline-date">

      Transaction by :

      ${eventInfo.user}

      -

      ${eventInfo.datetime}

    </div>

    ${
      step.gate ==
      'SM_SUBCONT_TO_NCVS'

      &&

      eventInfo.pickup_name

      ?

      `

        <div class="timeline-pickup">

          Pickup by :

          ${eventInfo.pickup_name}

          (${eventInfo.pickup_ncvs})

          -

          ${eventInfo.pickup_at}

        </div>

      `

      :

      ''
    }

  `

  :

  ''
}

    </div>

  </div>

`;
              });

              // FINAL RENDER
              $('#trackingDetailContent').html(`

          <div>

            <!-- HEADER -->
            <div class="mb-4">

              <div class="text-muted mb-1">
                Details Item :
              </div>

              <div
                class="
                  d-flex
                  align-items-center
                  gap-2
                ">

                <h3
                  class="fw-bold mb-0">

                  ${s.model ?? '-'}

                </h3>

                <span
                  class="badge rounded-pill"
                  style="
                    background:${boardCfg.badgeBg};
                    color:${boardCfg.badgeText};
                    padding:6px 10px;
                    font-size:12px;
                  ">

                  ${s.board}

                </span>
              </div>
            </div>

            <!-- SUMMARY -->
            <div
              class="
                card
                border-0
                shadow-sm
                mb-4
              ">

              <div class="card-body">
                <div class="row g-3">
                  <div class="col-md-3">
                    <div class="tracking-label">
                      Style
                    </div>

                    <div class="tracking-value">
                      ${s.style ?? '-'}
                    </div>
                  </div>
                  <div class="col-md-3">
                    <div class="tracking-label">
                      NCVS
                    </div>
                    <div class="tracking-value">
                      ${s.ncvs ?? '-'}
                    </div>
                  </div>

                  <div class="col-md-3">
                    <div class="tracking-label">
                      Bucket
                    </div>

                    <div class="tracking-value">
                      ${s.bucket ?? '-'}
                    </div>
                  </div>

                  <div class="col-md-3">
                    <div class="tracking-label">
                      PO - PO Item
                    </div>

                    <div class="tracking-value">
                      ${s.po_code ?? '-'}
                      -
                      ${s.po_item ?? '-'}
                    </div>

                  </div>

                  <div class="col-md-3">
                    <div class="tracking-label">
                      Vendor
                    </div>

                    <div class="tracking-value">
                      ${s.vendor ?? '-'}
                    </div>
                  </div>

                  <div class="col-md-3">
                    <div class="tracking-label">
                      Component
                    </div>

                    <div class="tracking-value">
                      ${s.component ?? '-'}
                    </div>

                  </div>
                </div>
              </div>
            </div>

            <!-- LOT DETAIL -->
            <div
              class="
                card
                border-0
                shadow-sm
                mb-4
              ">

              <div class="card-body">

                <div
                  class="
                    d-flex
                    justify-content-between
                    align-items-center
                    mb-3
                  ">
                  <h5
                    class="fw-bold mb-0">
                    Lot Detail
                  </h5>

                  <span
                    class="badge bg-secondary">
                    ${res.lot_detail.length}
                    Lot
                  </span>

                </div>

                <div
  class="table-responsive"
  style="
    overflow-x:auto;
  ">

  <table
    class="
      table
      table-bordered
      align-middle
      text-center
    "

    style="
      min-width:max-content;
      white-space:nowrap;
    ">

    <thead class="table-light">

      <tr>

        ${tableHeader}

      </tr>

    </thead>

    <tbody>

      ${tableBody}

    </tbody>

  </table>

</div>

              </div>

            </div>

            <!-- TIMELINE -->
            <div
              class="
                card
                border-0
                shadow-sm
              ">

              <div class="card-body">

                <h5
                  class="fw-bold mb-4">

                  Process Timeline

                </h5>

                <div>

                  ${timelineHtml}

                </div>

              </div>

            </div>

            <!-- FOOTER -->
            <div class="text-end mt-4">

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
            </div>
          </div>

        `);

            },

            error: function(err) {
              console.log(err);
              $('#trackingDetailContent').html(`

          <div
            class="
              text-center
              py-5
              text-danger
            ">

            Failed load detail.

          </div>

        `);
            }
          });
        }
      );

    });
  </script>

</body>

</html>