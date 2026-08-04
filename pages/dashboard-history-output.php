<?php
// menghubungkan php dengan koneksi database
require_once __DIR__ . '/../config/function.php';
require_once __DIR__ . '/../config/auth.php';
checkAuth('history_dashboard'); // cek apakah sudah login dan punya akses ke menu ini

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

  .select2-container .select2-selection--single {

    height: 38px !important;

    border: 1px solid #ced4da !important;

    border-radius: 0.375rem !important;

  }

  .select2-container--default .select2-selection--single .select2-selection__rendered {

    line-height: 36px !important;

  }

  .select2-container--default .select2-selection--single .select2-selection__arrow {

    height: 36px !important;

  }

  #hourlyTable thead th {

    vertical-align: middle !important;

    text-align: center;

  }

  .hourly-detail {

    cursor: pointer;

    transition: .2s;

  }

  .hourly-detail:hover {

    filter: brightness(.92);

  }

  #hourlyDetailTable {

    white-space: nowrap;

  }

  #hourlyDetailTable th {

    white-space: nowrap;

  }

  #hourlyDetailTable td {

    white-space: nowrap;

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
  $page = 'history_dashboard';
  include_once __DIR__ . '/../includes/header.php';
  ?>
  <!-- End Header -->

  <main id="main" class="main">

    <div class="pagetitle text-black" style="background-color: #f0e6d2; padding: 10px 20px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
      <h1 style="font-size: 1.8rem; font-weight: 700; font-family: 'Roboto', sans-serif;">
        History Hourly Output Production
      </h1>
    </div>

    <section class="section">

      <div class="card shadow-sm mb-3">

        <div class="card-body">

          <div class="row align-items-end">

            <div class="col-md-3">

              <label class="form-label">

                Date <span class="text-danger">*</span>

              </label>

              <input
                type="date"
                class="form-control"
                id="historyDate">

            </div>

            <div class="col-md-3">

              <label class="form-label">

                NCVS

              </label>

              <select
                id="historyNcvs"
                class="form-select select2">
              </select>

            </div>

            <div class="col-md-3 d-flex gap-2">

              <button type="button" id="btnResetHistory" class="btn btn-secondary"> <i class="bi bi-arrow-counterclockwise"></i> Reset</button>
              <button type="button" id="btnSearchHistory" class="btn btn-success" disabled><i class="bi bi-search"></i> Search</button>

            </div>

          </div>

        </div>

      </div>

      <div class="card shadow-sm">

        <div class="card-body p-4">

          <div class="table-responsive">

            <table
              id="hourlyTable"
              class="table table-bordered align-middle text-center">

              <tbody>

                <tr>

                  <td

                    colspan="20"

                    class="text-center text-muted py-5">

                    Please select Date then click Search.

                  </td>

                </tr>

              </tbody>

            </table>

          </div>

        </div>

      </div>

      <!-- ========================================= -->
      <!-- HOURLY DETAIL MODAL -->
      <!-- ========================================= -->

      <div class="modal fade"
        id="hourlyDetailModal"
        tabindex="-1"
        aria-hidden="true">

        <div class="modal-dialog modal-xl modal-dialog-scrollable">

          <div class="modal-content border-0 shadow">

            <div class="modal-header">

              <h5
                class="modal-title"
                id="hourlyDetailTitle">

                History Hourly Detail :

              </h5>

              <button
                class="btn-close"
                data-bs-dismiss="modal">

              </button>

            </div>

            <div class="modal-body">

              <div class="table-responsive">

                <table
                  id="hourlyDetailTable"
                  class="table table-bordered table-striped align-middle text-center">

                </table>

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
    function getCellClass(actual, plan) {

      if (actual == 0) {

        return "";

      }

      if (actual >= plan) {

        return "table-success";

      }

      return "table-danger";

    }

    function renderHourlyTable(data) {

      let html = `

    <thead>

        <tr class="table-secondary">

            <th rowspan="2" width ="7%">NCVS</th>

            <th rowspan="2" width ="11%">Plan Cycle / Jam</th>

            <th rowspan="2" width ="10%">Process</th>

            <th colspan="12" class="fw-bold fs-6">

                Hourly Achievement

            </th>

        </tr>

        <tr class="table-secondary">
    `;

      for (let i = 1; i <= 11; i++) {

        html += `<th width ="5%">${i}</th>`;

      }

      html += `

            <th>Total</th>

        </tr>

    </thead>

    <tbody>

    `;

      data.forEach(function(row) {

        // =======================
        // SCAN IN
        // =======================

        html += `

        <tr>

            <td rowspan="2">

                ${row.ncvs}

            </td>

            <td rowspan="2">

                ${row.plan_cycle}

            </td>

            <td>

                Scan In

            </td>

        `;

        for (let i = 1; i <= 11; i++) {

          let qty = row.scan_in[i] ?? 0;

          html += `

        <td

            class="

            ${getCellClass(qty,row.plan_cycle)}

            ${qty > 0 ? 'hourly-detail' : ''}

            "

            data-ncvs="${row.ncvs}"

            data-process="IN"

            data-hour="${i}">

            ${qty}

      </td>

        `;

        }

        html += `

        <td

            class="${row.scan_in_total > 0 ? 'fw-bold hourly-detail' : 'fw-bold'}"

            data-ncvs="${row.ncvs}"

            data-process="IN"

            data-hour="TOTAL">

            ${row.scan_in_total}

        </td>

        `;

        html += `</tr>`;

        // =======================
        // SCAN OUT
        // =======================

        html += `

        <tr>

            <td>

                Scan Out

            </td>

        `;

        for (let i = 1; i <= 11; i++) {

          let qty = row.scan_out[i] ?? 0;

          html += `

        <td

        class="

        ${getCellClass(qty,row.plan_cycle)}

        ${qty > 0 ? 'hourly-detail' : ''}

        "

        data-ncvs="${row.ncvs}"

        data-process="OUT"

        data-hour="${i}">

        ${qty}

        </td>

            `;

        }

        html += `

        <td

            class="${row.scan_out_total > 0 ? 'fw-bold hourly-detail' : 'fw-bold'}"

            data-ncvs="${row.ncvs}"

            data-process="OUT"

            data-hour="TOTAL">

            ${row.scan_out_total}

        </td>

        `;

        html += `</tr>`;

      });

      html += `

    </tbody>

    `;

      $("#hourlyTable").html(html);

    }

    $(document).on(

      "click",

      ".hourly-detail",

      function() {

        openHourlyDetail({

          ncvs: $(this).data("ncvs"),

          process: $(this).data("process"),

          hour: $(this).data("hour")

        });

      }

    );

    function openHourlyDetail(data) {

      const processLabel =
        data.process === "IN" ?
        "SCAN IN" :
        "SCAN OUT";

      const hourLabel =
        data.hour === "TOTAL" ?
        "TOTAL" :
        "HOUR " + data.hour;

      $("#hourlyDetailTitle").text(
        `Detail History Hourly : ${data.hour}, ${processLabel}, NCVS : ${data.ncvs}`
      );

      $("#hourlyDetailTable").html(`

<tbody>

<tr>

<td colspan="20" class="text-center py-5">

<div class="spinner-border text-primary mb-3"></div>

<div>

Loading history detail...

</div>

</td>

</tr>

</tbody>

`);

      $("#hourlyDetailModal").modal("show");

      $.ajax({

        url: './../config/get_history_hourly_detail.php',

        type: 'GET',

        dataType: 'json',

        data: {

          date: $("#historyDate").val(),
          ncvs: data.ncvs,
          process: data.process,
          hour: data.hour

        },

        success: function(response) {

          $("#hourlyDetailModal")
            .off("shown.bs.modal")
            .on("shown.bs.modal", function() {

              renderHourlyDetailTable(response);

            });

        },

        error: function() {

          alert("Failed load detail.");

        }

      });

    }

    function renderHourlyDetailTable(response) {

      // Destroy DataTable jika sudah ada
      if ($.fn.DataTable.isDataTable('#hourlyDetailTable')) {

        $('#hourlyDetailTable')
          .DataTable()
          .destroy();

      }

      let sizes = response.sizes ?? [];
      let rows = response.rows ?? [];

      let footerTotals = {};
      let grandTotal = 0;

      let html = '';

      /* =======================================
         HEADER
      ======================================= */

      html += `

        <thead class="table-secondary">

            <tr>

                <th>Bucket</th>
                <th>Style</th>
                <th>Model</th>
                <th>PO</th>
                <th>PO Item</th>
                <th>Component</th>

    `;

      sizes.forEach(function(size) {

        html += `<th>${size}</th>`;

      });

      html += `

                <th>Total</th>

            </tr>

        </thead>

        <tbody>

    `;

      /* =======================================
         BODY
      ======================================= */

      rows.forEach(function(row) {

        let rowTotal = 0;

        html += `

            <tr>

                <td>${row.bucket}</td>
                <td>${row.style}</td>
                <td>${row.model}</td>
                <td>${row.po}</td>
                <td>${row.po_item}</td>
                <td>${row.component}</td>

        `;

        sizes.forEach(function(size) {

          let qty = row.sizes[size] ?? 0;

          rowTotal += qty;

          footerTotals[size] =
            (footerTotals[size] ?? 0) + qty;

          html += `

                <td>

                    ${qty}

                </td>

            `;

        });

        grandTotal += rowTotal;

        html += `

                <td class="fw-bold">

                    ${rowTotal}

                </td>

            </tr>

        `;

      });

      html += `

        </tbody>

    `;

      html += `
<tr class="table-secondary fw-bold">

    <td colspan="6" class="text-end">

        TOTAL

    </td>
`;

      sizes.forEach(function(size) {

        html += `
        <td>${footerTotals[size] ?? 0}</td>
    `;

      });

      html += `
    <td>${grandTotal}</td>
</tr>

`;

      html += `
</tbody>
`;

      $("#hourlyDetailTable").html(html);

      let table = $('#hourlyDetailTable').DataTable({

        destroy: true,

        processing: false,

        searching: true,

        paging: true,

        ordering: false,

        info: true,

        scrollX: true,

        scrollCollapse: true,

        autoWidth: false,

        responsive: false,

        pageLength: 10,

        lengthMenu: [

          [10, 25, 50, -1],

          [10, 25, 50, "All"]

        ],

        dom:

          "<'row align-items-center'<'col-md-6 d-flex align-items-center gap-2'l><'col-md-6 text-end'f>>"

          +

          "rt"

          +

          "<'row mt-3 align-items-center'<'col-md-6'i><'col-md-6 text-end'p>>",

        columnDefs: [

          {

            targets: "_all",

            className: "text-center align-middle",

            defaultContent: ""

          }

        ],

        language: {

          emptyTable: "No data available."

        }

      });

      setTimeout(function() {

        table.columns.adjust();

      }, 100);

    }
  </script>

  <script>
    $("#historyDate").on("change", function() {

      let value = $(this).val();

      $("#btnSearchHistory").prop("disabled", value === "");

      $("#historyNcvs").empty();

      $("#historyNcvs").append(
        new Option("All", "")
      );

      if (value == "") {

        return;

      }

      $.ajax({

        url: "../config/get_history_ncvs.php",

        type: "GET",

        dataType: "json",

        data: {

          date: value

        },

        success: function(response) {

          response.data.forEach(function(item) {

            $("#historyNcvs").append(

              new Option(

                item.text,

                item.id

              )

            );

          });

          $("#historyNcvs").trigger("change");

        }

      });

    });

    $("#btnResetHistory").on("click", function() {

      $("#historyDate").val("");

      $("#historyNcvs")
        .empty()
        .append(new Option("All", ""))
        .trigger("change");

      $("#btnSearchHistory")
        .prop("disabled", true);

      $("#hourlyTable").html(`

<tbody>

<tr>

<td colspan="15"

class="text-center text-muted py-5">

Please select Date then click Search.

</td>

</tr>

</tbody>

`);

    });

    $("#btnSearchHistory").on("click", function() {

      $("#hourlyTable").html(`

        <tbody>

            <tr>

                <td colspan="20" class="text-center py-5">

                    <div class="spinner-border text-primary mb-3"></div>

                    <div>

                        Loading history...

                    </div>

                </td>

            </tr>

        </tbody>

    `);

      $.ajax({

        url: '../config/get_history_hourly_output.php',

        type: 'GET',

        dataType: 'json',

        data: {

          date: $("#historyDate").val(),

          ncvs: $("#historyNcvs").val()

        },

        success: function(response) {

          if (response.data.length == 0) {

            $("#hourlyTable").html(`

        <tbody>

            <tr>

                <td colspan="20"

                class="text-center py-5 text-muted">

                    No data found.

                </td>

            </tr>

        </tbody>

        `);

            return;

          }

          renderHourlyTable(response.data);

        },

        error: function() {

          alert("Failed load history.");

        }

      });

    });
  </script>

  <script>
    $("#historyNcvs").select2({

      placeholder: "Select NCVS",

      allowClear: true,

      width: "100%",

      minimumInputLength: 1

    });

    $(document).on("select2:open", function() {

      document.querySelector(".select2-search__field").focus();

    });
  </script>

</body>

</html>