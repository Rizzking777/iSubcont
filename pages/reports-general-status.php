<?php
// menghubungkan php dengan koneksi database
require_once __DIR__ . '/../config/function.php';
require_once __DIR__ . '/../config/auth.php';
checkAuth('general_status'); // cek apakah sudah login dan punya akses ke menu ini

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

  .dataTables_empty {
    text-align: center !important;
    vertical-align: middle !important;
    padding: 40px !important;
    font-size: 14px;
    color: #6c757d;
  }

  .dataTables_processing {
    border: none !important;
    background: white !important;
    box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
    border-radius: 10px;
    padding: 20px !important;
  }

  .sticky-col {
    position: sticky;
    z-index: 5;
    box-shadow: 2px 0 6px rgba(0, 0, 0, 0.04);
  }

  #example1 thead th {
    position: sticky;
    top: 0;
    z-index: 10;
  }

  .sticky-1 {
    left: 0;
    min-width: 120px;
  }

  .sticky-2 {
    left: 120px;
    min-width: 120px;
  }

  .sticky-3 {
    left: 240px;
    min-width: 120px;
  }

  .sticky-4 {
    left: 240px;
    min-width: 120px;
  }

  .sticky-5 {
    left: 240px;
    min-width: 120px;
  }

  .balance-positive {
    color: #15803d;
    font-weight: 700;
  }

  .balance-negative {
    color: #dc2626;
    font-weight: 700;
  }

  #example1 thead th {
    text-align: center !important;
    vertical-align: middle !important;
  }

  #example1 tbody td {
    text-align: center !important;
    vertical-align: middle !important;
  }

  #example1 th,
  #example1 td {
    text-align: center !important;
    vertical-align: middle !important;
  }

  .dataTables_scrollHead table thead tr:first-child th {
    text-align: center !important;
  }

  .dataTables_scrollHead table thead tr:nth-child(2) th {
    text-align: center !important;
  }

  .dataTables_scrollHead th {
    text-align: center !important;
    vertical-align: middle !important;
  }

  .dataTables_wrapper .row:first-child {
    margin-bottom: 16px !important;
    align-items: center !important;
  }

  .dt-buttons {
    display: inline-flex;
    align-items: center;
    gap: 10px;
  }

  .dt-buttons .btn {
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0 18px !important;
    margin: 0 !important;
  }

  .dataTables_length {
    margin-bottom: 0 !important;
  }

  .dataTables_length label {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 0 !important;
  }

  .dataTables_length select {
    height: 40px !important;
    border-radius: 10px !important;
    padding: 4px 10px !important;
  }

  .table-wrapper {
    padding-top: 18px !important;
  }

  .dataTables_length select {
    min-width: 75px !important;
    height: 40px !important;
    padding: 4px 36px 4px 12px !important;
    border-radius: 10px !important;
    appearance: none !important;
    -webkit-appearance: none !important;
    -moz-appearance: none !important;
    background-position:
      right 12px center !important;
  }

  .dataTables_length {
    position: relative;
  }

  .dataTables_length::after {
    content: '\F282';
    font-family: bootstrap-icons;
    position: absolute;
    right: 14px;
    top: 50%;
    transform: translateY(-50%);
    pointer-events: none;
    font-size: 12px;
    color: #64748b;
  }
</style>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta content="width=device-width, initial-scale=1.0" name="viewport">

  <title>iSubcont - Reports</title>
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
  $page = 'general_status';
  include_once __DIR__ . '/../includes/header.php';
  ?>
  <!-- End Header -->

  <main id="main" class="main">

    <div class="pagetitle text-black" style="background-color: #f0e6d2; padding: 10px 20px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
      <h1 style="font-size: 1.8rem; font-weight: 700; font-family: 'Roboto', sans-serif;">
        General Status
      </h1>
    </div>

    <div class="card border-0 shadow-sm mb-4 fade-in">
      <div class="card-body p-4">
        <form id="filterForm">

          <!-- ROW 1 -->
          <div class="row g-3">

            <!-- DATE -->
            <!-- <div class="col-md-3">
              <label class="form-label fw-semibold">
                Date Range
              </label>

              <input
                type="text"
                id="date_range"
                name="date_range"
                class="form-control"
                placeholder="Select date range">
            </div> -->

            <!-- komponen -->
            <div class="col-md-3">
              <label class="form-label fw-semibold">
                Komponen
              </label>

              <select
                id="nm_komponen_in"
                name="nm_komponen_in"
                class="form-control select2-remote">
              </select>
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

    <section class="section">
      <div class="row">
        <div class="col-lg-12">
          <div class="card">

            <div class="card-body table-wrapper">

              <table
                id="example1"

                class="
                    table
                    table-bordered
                    table-striped
                    text-center
                    align-middle
                    nowrap
                  "

                style="
                    width:100%;
                    min-width:3000px;
                  ">
                <thead>

                  <!-- HEADER 1 -->
                  <tr>

                    <th rowspan="2" class="sticky-col sticky-1">NCVS</th>
                    <th rowspan="2" class="sticky-col sticky-2">Bucket</th>
                    <th rowspan="2" class="sticky-col sticky-3">PO Code</th>
                    <th rowspan="2" class="sticky-col sticky-4">PO Item</th>
                    <th rowspan="2">Model</th>
                    <th rowspan="2">Style</th>
                    <th rowspan="2">Komponen</th>
                    <th rowspan="2">Vendor</th>
                    <th rowspan="2" class="sticky-col sticky-5">Total Order</th>

                    <!-- <th colspan="4">
                      SM Cutting
                    </th> -->

                    <th colspan="4">
                      SM Subcont Plant 3
                    </th>

                    <th colspan="4">
                      WH Subcont
                    </th>

                    <th colspan="4">
                      Vendor
                    </th>

                    <th colspan="4">
                      Return WH
                    </th>

                    <th colspan="4">
                      Return SM Plant 3
                    </th>

                  </tr>

                  <!-- HEADER 2 -->
                  <tr>

                    <!-- <th>In</th>
                    <th>Balance</th>

                    <th>Out</th>
                    <th>Balance</th> -->

                    <th>In SM</th>
                    <th>Balance</th>

                    <th>Out SM</th>
                    <th>Balance</th>

                    <th>In WH</th>
                    <th>Balance</th>

                    <th>Out WH</th>
                    <th>Balance</th>

                    <th>In Vendor</th>
                    <th>Balance</th>

                    <th>Out Vendor</th>
                    <th>Balance</th>

                    <th>Return WH</th>
                    <th>Balance</th>

                    <th>Transfer to SM</th>
                    <th>Balance</th>

                    <th>Return SM</th>
                    <th>Balance</th>

                    <th>Transfer to NCVS</th>
                    <th>Balance</th>

                  </tr>

                </thead>
                <tbody></tbody>
              </table>

            </div>
            <!-- End Table with stripped rows -->
          </div>
        </div>
      </div>

      <div class="modal fade"
        id="balanceModal"
        tabindex="-1"
        aria-hidden="true">

        <div class="modal-dialog modal-xl modal-dialog-centered">

          <div class="modal-content">

            <div class="modal-header">

              <h5 class="modal-title">
                <i class="bi bi-search me-2"></i>
                Balance Detail
              </h5>

              <button
                class="btn-close"
                data-bs-dismiss="modal">
              </button>

            </div>

            <div class="modal-body">

              <div class="row g-3 mb-3">

                <div class="col-md-6">

                  <div class="card bg-light border-0">

                    <div class="card-body py-2">

                      <div><strong>NCVS</strong> :
                        <span id="modal_ncvs"></span>
                      </div>

                      <div><strong>Bucket</strong> :
                        <span id="modal_bucket"></span>
                      </div>

                      <div><strong>PO</strong> :
                        <span id="modal_po"></span>
                      </div>

                      <div><strong>Model</strong> :
                        <span id="modal_model"></span>
                      </div>

                    </div>

                  </div>

                </div>

                <div class="col-md-6">

                  <div class="card bg-light border-0">

                    <div class="card-body py-2">

                      <div><strong>Style</strong> :
                        <span id="modal_style"></span>
                      </div>

                      <div><strong>Stage</strong> :
                        <span id="modal_stage"></span>
                      </div>

                      <div><strong>Component</strong> :
                        <span id="modal_component"></span>
                      </div>

                      <div><strong>Vendor</strong> :
                        <span id="modal_vendor"></span>
                      </div>

                    </div>

                  </div>

                </div>

              </div>

              <hr>

              <div id="modal_loading"
                class="text-center py-5">

                <div class="spinner-border text-primary"></div>

                <div class="mt-2">
                  Loading...
                </div>

              </div>

              <div id="modal_content"
                style="display:none;">

                <div class="table-responsive">

                  <table
                    class="table table-bordered table-striped table-hover align-middle text-center mb-0">

                    <thead class="table-secondary">

                      <tr>

                        <th>Size</th>

                        <th>Plan</th>

                        <th id="actualHeader">
                          Actual
                        </th>

                        <th>Balance</th>

                      </tr>

                    </thead>

                    <tbody id="balanceBody">

                    </tbody>

                    <tfoot>

                      <tr class="table-secondary fw-bold">

                        <td>Total</td>

                        <td id="totalPlan"></td>

                        <td id="totalActual"></td>

                        <td id="totalBalance"></td>

                      </tr>

                    </tfoot>

                  </table>

                </div>

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
  <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

  <!-- Responsive extension -->
  <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
  <script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap5.min.js"></script>

  <!-- Buttons extension -->
  <script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
  <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.bootstrap5.min.js"></script>
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

      // INIT SELECT2 AJAX
      function initSelect2(
        id,
        action,
        placeholder
      ) {

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
                style: $("#style").val(),
                model: $("#model").val(),
                vendor: $("#vendor").val(),
                nm_komponen_in: $("#nm_komponen_in").val()
              };
            },

            processResults: function(data) {

              return {
                results: data.results || []
              };
            }
          }
        });

        // AUTO FOCUS
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

      initSelect2(
        "#nm_komponen_in",
        "searchKomponen",
        "Komponen"
      );

      // ENABLE SEARCH BUTTON
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

      function renderBalance(data, row, stage) {

        if (data >= 0) {

          return `
            <span class="balance-positive">
                ${data}
            </span>
        `;

        }

        return `
        <a href="#"

          class="balance-detail text-danger fw-bold"

          data-stage="${stage}"

          data-job="${row.job_order}"

          data-ncvs="${row.ncvs}"

          data-bucket="${row.bucket}"

          data-po="${row.po_code}"

          data-po_item="${row.po_item}"

          data-component="${row.nm_komponen_in}"

          data-style="${row.style}"

          data-model="${row.model}"

          data-vendor="${row.vendor}"

          data-total="${row.total_order}"

        >

            ${data}

        </a>
    `;

      }

      // DATATABLE
      const table = $('#example1').DataTable({

        processing: true,
        dom: "<'row align-items-center'<'col-md-6 d-flex align-items-center gap-2'lB><'col-md-6 text-end'f>>" +

          "rt" +

          "<'row mt-3 align-items-center'<'col-md-6'i><'col-md-6 text-end'p>>",

        serverSide: false,
        searching: false,
        paging: true,
        ordering: false,
        info: true,
        scrollX: true,
        autoWidth: false,
        responsive: false,
        destroy: true,
        data: [],
        columnDefs: [{
          targets: "_all",
          className: "text-center align-middle"
        }],

        language: {

          emptyTable: `
        Pilih filter lalu klik Search.`,

          processing: `
            <div class="py-3">
              <div class="spinner-border text-primary"></div>
              <div class="mt-2">
                Loading data...
              </div>
            </div>`
        },

        columns: [

          // NCVS
          {
            data: 'ncvs',
            className: 'sticky-col sticky-1'
          },

          // BUCKET
          {
            data: 'bucket',
            className: 'sticky-col sticky-2'
          },

          // PO CODE
          {
            data: 'po_code',
            className: 'sticky-col sticky-3'
          },

          // PO ITEM
          {
            data: 'po_item',
            className: 'sticky-col sticky-4'
          },

          // MODEL
          {
            data: 'model'
          },

          // STYLE
          {
            data: 'style'
          },

          // KOMPONEN
          {
            data: 'nm_komponen_in'
          },

          // VENDOR
          {
            data: 'vendor'
          },

          // TOTAL ORDER
          {
            data: 'total_order',
            className: 'sticky-col sticky-5 total-order'
          },

          // // SM CUTTING - IN
          // {
          //   data: 'sm_cutting_in'
          // },

          // // SM CUTTING - BALANCE
          // {
          //   data: 'sm_cutting_balance',

          //   render: function(data) {
          //     return renderBalance(data);
          //   }
          // },

          // // SM CUTTING - OUT
          // {
          //   data: 'sm_cutting_out'
          // },

          // // SM CUTTING - OUT BALANCE
          // {
          //   data: 'sm_cutting_out_balance',

          //   render: function(data) {
          //     return renderBalance(data);
          //   }
          // },

          // SM SUBCONT - IN
          {
            data: 'in_sm'
          },

          // SM SUBCONT - BALANCE
          {
            data: 'balance_in_sm',

            render: function(data, type, row) {

              return renderBalance(
                data,
                row,
                "SM Subcont In"
              );

            }
          },

          // SM SUBCONT - OUT
          {
            data: 'out_sm'
          },

          // SM SUBCONT - OUT BALANCE
          {
            data: 'balance_out_sm',

            render: function(data, type, row) {

              return renderBalance(
                data,
                row,
                "SM Subcont Out"
              );

            }
          },

          // WH SUBCONT - IN
          {
            data: 'in_wh'
          },

          // WH SUBCONT - BALANCE
          {
            data: 'balance_in_wh',

            render: function(data, type, row) {

              return renderBalance(
                data,
                row,
                "WH Subcont In"
              );

            }
          },

          // WH SUBCONT - OUT
          {
            data: 'out_wh'
          },

          // WH SUBCONT - OUT BALANCE
          {
            data: 'balance_out_wh',

            render: function(data, type, row) {

              return renderBalance(
                data,
                row,
                "WH Subcont Out"
              );

            }
          },

          // VENDOR - IN
          {
            data: 'in_vendor'
          },

          // VENDOR - BALANCE
          {
            data: 'balance_in_vendor',

            render: function(data, type, row) {

              return renderBalance(
                data,
                row,
                "Vendor Subcont In"
              );

            }
          },

          // VENDOR - OUT
          {
            data: 'out_vendor'
          },

          // VENDOR - OUT BALANCE
          {
            data: 'balance_out_vendor',

            render: function(data, type, row) {

              return renderBalance(
                data,
                row,
                "Vendor Subcont Out"
              );

            }
          },

          // RETURN WH - IN
          {
            data: 'return_wh'
          },

          // RETURN WH - BALANCE
          {
            data: 'balance_return_wh',

            render: function(data, type, row) {

              return renderBalance(
                data,
                row,
                "Return WH Subcont"
              );

            }
          },

          // RETURN WH - TRANSFER SM
          {
            data: 'transfer_sm'
          },

          // RETURN WH - BALANCE
          {
            data: 'balance_transfer_sm',

            render: function(data, type, row) {

              return renderBalance(
                data,
                row,
                "Transfer to SM Subcont"
              );

            }
          },

          // RETURN SM - IN
          {
            data: 'return_sm'
          },

          // RETURN SM - BALANCE
          {
            data: 'balance_return_sm',

            render: function(data, type, row) {

              return renderBalance(
                data,
                row,
                "Return SM Subcont"
              );

            }
          },

          // RETURN SM - OUT PROD
          {
            data: 'transfer_ncvs'
          },

          // RETURN SM - BALANCE
          {
            data: 'balance_transfer_ncvs',

            render: function(data, type, row) {

              return renderBalance(
                data,
                row,
                "Transfer to NCVS"
              );

            }
          }

        ],


        buttons: [

          {

            extend: 'excelHtml5',
            enabled: false,

            text: `
      <i class="bi bi-file-earmark-excel"></i>
      Export
    `,

            className: 'btn btn-success',
            title: null,
            filename: 'General Status Report',
            exportOptions: {
              columns: ':visible',
              stripHtml: true
            },

            customize: function(xlsx) {

              const sheet =
                xlsx.xl.worksheets['sheet1.xml'];

              const rows =
                $('sheetData row', sheet);

              // SHIFT ALL ROW DOWN
              rows.each(function() {

                const row = $(this);

                const r =
                  parseInt(
                    row.attr('r')
                  );

                row.attr(
                  'r',
                  r + 1
                );

                row.find('c').each(function() {

                  const cell = $(this);
                  const cellRef =
                    cell.attr('r');
                  const col =
                    cellRef.replace(/[0-9]/g, '');
                  const rowNum =
                    parseInt(
                      cellRef.replace(/[A-Z]/g, '')
                    );

                  cell.attr(
                    'r',
                    col + (rowNum + 1)
                  );

                });

              });

              // ADD CUSTOM HEADER ROW
              const headerRow = `
                <row r="1">

                <c t="inlineStr" r="A1"><is><t>NCVS</t></is></c>
                <c t="inlineStr" r="B1"><is><t>Bucket</t></is></c>
                <c t="inlineStr" r="C1"><is><t>PO Code</t></is></c>
                <c t="inlineStr" r="D1"><is><t>PO Item</t></is></c>
                <c t="inlineStr" r="E1"><is><t>Model</t></is></c>
                <c t="inlineStr" r="F1"><is><t>Style</t></is></c>
                <c t="inlineStr" r="G1"><is><t>Komponen</t></is></c>
                <c t="inlineStr" r="H1"><is><t>Vendor</t></is></c>
                <c t="inlineStr" r="I1"><is><t>Total Order</t></is></c>

                <c t="inlineStr" r="J1"><is><t>SM Subcont Plant 3</t></is></c>
                <c t="inlineStr" r="N1"><is><t>WH Subcont</t></is></c>
                <c t="inlineStr" r="R1"><is><t>Vendor</t></is></c>
                <c t="inlineStr" r="V1"><is><t>Return WH</t></is></c>
                <c t="inlineStr" r="Z1"><is><t>Return SM Plant 3</t></is></c>

                </row>
                `;

              sheet.childNodes[0]
                .childNodes[1]
                .innerHTML =
                headerRow +
                sheet.childNodes[0]
                .childNodes[1]
                .innerHTML;

              // MERGE
              let mergeCells =
                $('mergeCells', sheet);

              if (!mergeCells.length) {

                mergeCells =
                  $('<mergeCells count="0"/>')
                  .appendTo(sheet);
              }

              mergeCells.append(`

                <mergeCell ref="J1:M1"/>
                <mergeCell ref="N1:Q1"/>
                <mergeCell ref="R1:U1"/>
                <mergeCell ref="V1:Y1"/>
                <mergeCell ref="Z1:AC1"/>

                `);

              mergeCells.attr(
                'count',
                mergeCells.children().length
              );

              // AUTO WIDTH
              $('col', sheet).each(function() {

                $(this).attr(
                  'width',
                  18
                );

              });

            }

          }

        ],

      });

      // SEARCH
      $('#searchBtn').on(
        'click',

        function() {

          table.clear().draw();

          $('#example1 tbody').html(`

            <tr>

              <td colspan="999"
                  class="text-center py-5">

                <div class="py-4">

                  <div class="spinner-border text-primary"></div>

                  <div class="mt-3">
                    Loading data...
                  </div>

                </div>

              </td>

            </tr>

           `);

          table.settings()[0].ajax = {
            url: './../config/get_general_status.php',
            type: 'POST',

            data: function(d) {

              d.date_range =
                $('#date_range').val();

              d.bucket =
                $('#bucket').val();

              d.po_code =
                $('#po_code').val();

              d.po_item =
                $('#po_item').val();

              d.ncvs =
                $('#ncvs').val();

              d.model =
                $('#model').val();

              d.style =
                $('#style').val();

              d.vendor =
                $('#vendor').val();

              d.nm_komponen_in =
                $('#nm_komponen_in').val();
            },

            dataSrc: function(json) {

              table.button(0).enable(
                json.data &&
                json.data.length > 0
              );

              // NO DATA
              if (
                !json.data ||
                json.data.length === 0
              ) {

                setTimeout(() => {

                  $('#example1 tbody').html(`

              <tr>

                <td colspan="999"
                    class="text-center py-5">
                      No data found
                </td>

              </tr>

            `);

                }, 100);

                return [];
              }

              return json.data;
            },

            error: function() {

              $('#example1 tbody').html(`

          <tr>

            <td colspan="999"
                class="text-center py-5">
                  Failed to load data
            </td>

          </tr>

        `);
            }
          };

          table.ajax.reload();
        }
      );

      // RESET
      $('#resetBtn').on(
        'click',

        function() {

          table.button(0).disable();

          $('#filterForm')[0].reset();

          $('.select2-remote')
            .val(null)
            .trigger('change');

          $('#searchBtn')
            .prop('disabled', true);

          table.clear().draw();

          $('#example1 tbody').html(`

      <tr>

        <td colspan="999"
            class="text-center py-5">
              Pilih filter lalu klik Search.
            
        </td>

      </tr>

    `);
        }
      );

    });

    $(document).on(
      "click",
      ".balance-detail",

      function(e) {

        e.preventDefault();

        $("#modal_loading").show();

        $.ajax({

          url: "./../config/get_general_status_detail.php",

          type: "POST",

          data: {

            stage: $(this).data("stage"),

            job_order: $(this).data("job"),

            bucket: $(this).data("bucket"),

            po_code: $(this).data("po"),

            po_item: $(this).data("po_item"),

            component: $(this).data("component")

          },

          success: function(res) {

            $("#modal_loading").hide();

            $("#modal_content").show();

            $("#balanceBody").html("");

            $("#actualHeader").text(
              $(e.currentTarget).data("stage")
            );

            $.each(res.rows, function(i, row) {

              let color = "";

              if (row.balance < 0) {

                color = "text-danger fw-bold";

              } else if (row.balance > 0) {

                color = "text-success fw-bold";

              }

              $("#balanceBody").append(`

            <tr>

                <td>${row.size}</td>

                <td>${row.plan}</td>

                <td>${row.actual}</td>

                <td class="${color}">

                    ${row.balance}

                </td>

            </tr>

        `);

            });

            $("#totalPlan").text(res.summary.plan);

            $("#totalActual").text(res.summary.actual);

            $("#totalBalance").html(

              res.summary.balance < 0

              ?

              `<span class="text-danger fw-bold">

            ${res.summary.balance}

        </span>`

              :

              `<span class="text-success fw-bold">

            ${res.summary.balance}

        </span>`

            );

          }

        });

        $("#modal_content").hide();

        $("#modal_ncvs")
          .text($(this).data("ncvs"));

        $("#modal_bucket")
          .text($(this).data("bucket"));

        $("#modal_po")
          .text(

            $(this).data("po")

            +

            " - "

            +

            $(this).data("po_item")

          );

        $("#modal_stage")
          .text($(this).data("stage"));

        $("#modal_component")
          .text($(this).data("component"));

        $("#modal_style").text(
          $(this).data("style")
        );

        $("#modal_model").text(
          $(this).data("model")
        );

        $("#modal_vendor").text(
          $(this).data("vendor")
        );

        new bootstrap.Modal(
          document.getElementById(
            "balanceModal"
          )
        ).show();

      });
  </script>

</body>

</html>