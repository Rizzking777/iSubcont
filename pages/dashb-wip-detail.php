<?php
// menghubungkan php dengan koneksi database
require_once __DIR__ . '/../config/function.php';
require_once __DIR__ . '/../config/auth.php';
checkAuth('wip'); // cek apakah sudah login dan punya akses ke menu ini

$nik = $_SESSION['nik_user'];
$username = $_SESSION['username']; // Query ringkasan per job_order

$ncvs = $_GET['ncvs'] ?? '';
$type = $_GET['type'] ?? '';

$typeLabelMap = [
  'SCAN_IN_WAREHOUSE'   => 'IN WAREHOUSE',
  'SCAN_OUT_TO_VENDOR'  => 'OUT TO VENDOR',
  'SCAN_IN_INCOMING'  => 'INCOMING FROM VENDOR',
  'SCAN_OUT_TO_PRODUCTION' => 'OUT TO PRODUCTION',
];

$typeLabel = $typeLabelMap[$type] ?? 'UNKNOWN';

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

  /* Table Styling */
  #detailTable {
    border-collapse: separate;
    border-spacing: 0;
    overflow: hidden;
    border-radius: 10px;
  }

  #detailTable thead {
    background: #343a40;
    color: #fff;
  }

  #detailTable tbody tr {
    transition: 0.2s;
    cursor: pointer;
  }

  /* Hover Effect */
  #detailTable tbody tr:hover {
    background-color: #f1f1f1 !important;
    transform: scale(1.01);
  }

  #detailTable th,
  #detailTable td {
    text-align: center;
    vertical-align: middle;
  }

  .wip-scroll {
    overflow-x: auto;
    white-space: nowrap;
  }

  .wip-scroll table {
    min-width: 1200px;
    /* biar ga gepeng */
  }

  .wip-scroll th,
  .wip-scroll td {
    text-align: center;
    vertical-align: middle;
    font-size: 13px;
  }

  .wip-scroll th {
    background: #f8f9fa;
    font-weight: 600;
  }

  .wip-scroll td.text-end {
    text-align: center !important;
    /* override */
  }

  .wip-scroll thead th,
  .wip-scroll tfoot th {
    background-color: #DCDCDC;
    /* abu-abu Bootstrap */
    color: #212529;
    font-weight: 600;
    text-align: center;
    vertical-align: middle;
    border-top: 2px solid #DCDCDC;
    border-bottom: 2px solid #DCDCDC;
  }

  .wip-scroll tbody td {
    text-align: center;
    vertical-align: middle;
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

</head>

<body>

  <!-- Header -->
  <?php
  $page = 'wip';
  include_once __DIR__ . '/../includes/header.php';
  ?>
  <!-- End Header -->

  <main id="main" class="main">

    <div class="pagetitle text-black"
      style="background-color: #f0e6d2; padding: 10px 20px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
      <h1 style="font-size: 1.8rem; font-weight: 700; font-family: 'Roboto', sans-serif;">
        WIP Activity Dashboard Detail NCVS :
        <?= htmlspecialchars($ncvs) ?>
        <span class="text-muted">| <?= htmlspecialchars($typeLabel) ?></span>
      </h1>
    </div>

    <section class="section">
      <div class="card">
        <div class="card-body pt-3">

          <div class="table-responsive">
            <div id="content"></div>
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
    $(function() {
      $("#example1").DataTable({
        "responsive": true,
        "lengthChange": false,
        "autoWidth": false,
        // "buttons": ["copy", "excel"]
      }).buttons().container().appendTo('#example1_wrapper .col-md-6:eq(0)');
      $('#example2').DataTable({
        "paging": true,
        "lengthChange": false,
        "searching": false,
        "ordering": true,
        "info": true,
        "autoWidth": false,
        "responsive": true,
      });
    });
  </script>

  <script>
    $(document).ready(function() {
      $('#example1').DataTable({
        scrollX: true,
        destroy: true // biar gak error reinit
      });
    });
  </script>

  <script>
    let ncvs = "<?= $ncvs ?>";
    let type = "<?= $type ?>";

    fetch(`../config/get_wip.php?filter=${type}`)
      .then(res => res.json())
      .then(data => {

        const row = data.find(r => r.ncvs == ncvs);
        if (!row) {
          document.getElementById("content").innerHTML = "Data tidak ditemukan";
          return;
        }

        // ================================
        // GATE 1 : SCAN IN WAREHOUSE
        // ================================
        if (type === "SCAN_IN_WAREHOUSE") {

          const details = row.detail || [];

          // hitung total WIP dulu
          const totalWip = details.reduce((sum, r) => {
            const t = Object.values(r.size || {}).reduce((a, b) => a + b, 0);
            return sum + t;
          }, 0);

          if (totalWip <= 0) {
            document.getElementById("content").innerHTML = `
      <div class="alert alert-secondary text-center d-flex justify-content-center align-items-center gap-2">
  <i class="bi bi-info-circle-fill"></i>
  <strong>Tidak ada data WIP In Warehouse.</strong>
</div>`;
            return;
          }

          renderTableInWH(details);
          return;
        }

        // ================================
        // GATE 2 : SCAN OUT TO VENDOR
        // ================================
        if (type === "SCAN_OUT_TO_VENDOR") {

          const totalWip = row.wip_out_vendor || 0;

          if (totalWip <= 0) {
            document.getElementById("content").innerHTML = `
      <div class="alert alert-secondary text-center d-flex justify-content-center align-items-center gap-2">
        <i class="bi bi-info-circle-fill"></i>
        <strong>Tidak ada data WIP Out to Vendor.</strong>
      </div>`;
            return;
          }

          // 🔥 SAMA PERSIS DENGAN IN WH
          renderTableInWH(row.detail);
          return;
        }

      });
  </script>

  <script>
    function sortSize(a, b) {
      const parse = s => {
        const isT = s.endsWith("T");
        return {
          num: parseInt(s.replace("T", ""), 10),
          t: isT ? 1 : 0
        };
      };

      const A = parse(a);
      const B = parse(b);

      if (A.num !== B.num) return A.num - B.num;
      return A.t - B.t; // non-T dulu, lalu T
    }
  </script>

  <script>
    function renderTableInWH(details) {

      if (!Array.isArray(details) || details.length === 0) {
        document.getElementById("content").innerHTML = `
      <div class="alert alert-info text-center">
        Tidak ada WIP IN Warehouse
      </div>`;
        return;
      }

      // 🔥 FILTER: hanya row yang masih punya qty > 0
      const filteredDetails = details.filter(r => {
        const total = Object.values(r.size || {}).reduce((a, b) => a + b, 0);
        return total > 0;
      });

      if (filteredDetails.length === 0) {
        document.getElementById("content").innerHTML = `
      <div class="alert alert-secondary text-center">
        Semua PO pada NCVS ini sudah selesai diproses
      </div>`;
        return;
      }

      // =========================
      // 1. UNION SIZE (dari filtered)
      // =========================
      const sizeSet = new Set();
      filteredDetails.forEach(r => {
        Object.keys(r.size || {}).forEach(s => sizeSet.add(s));
      });
      const sizes = Array.from(sizeSet).sort(sortSize);

      // =========================
      // 2. HEADER
      // =========================
      let html = `
  <div class="table-responsive wip-scroll">
    <table class="table table-bordered table-sm">
      <thead class="table-secondary text-center">
        <tr>
          <th>No</th>
          <th>Bucket</th>
          <th>PO Code</th>
          <th>PO Item</th>
          <th>Style</th>
          <th>Model</th>`;

      sizes.forEach(s => html += `<th>${s}</th>`);
      html += `<th>Total</th></tr></thead><tbody>`;

      // =========================
      // 3. BODY
      // =========================
      let grandTotal = 0;

      filteredDetails.forEach((r, i) => {
        let rowTotal = 0;

        html += `
      <tr class="text-center">
        <td>${i + 1}</td>
        <td>${r.bucket || "-"}</td>
        <td>${r.po_code || "-"}</td>
        <td>${r.po_item || "-"}</td>
        <td>${r.style || "-"}</td>
        <td>${r.model || "-"}</td>`;

        sizes.forEach(s => {
          const qty = Number(r.size?.[s] || 0);
          rowTotal += qty;
          html += `<td>${qty}</td>`;
        });

        grandTotal += rowTotal;
        html += `<td class="fw-bold">${rowTotal}</td></tr>`;
      });

      // =========================
      // 4. FOOTER
      // =========================
      html += `
      </tbody>
      <tfoot class="table-secondary">
        <tr>
          <th colspan="${6 + sizes.length}" class="text-center">TOTAL</th>
          <th class="text-center fw-bold">${grandTotal}</th>
        </tr>
      </tfoot>
    </table>
  </div>`;

      document.getElementById("content").innerHTML = html;
    }
  </script>

</body>

</html>