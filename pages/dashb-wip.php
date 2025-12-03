<?php
// menghubungkan php dengan koneksi database
require_once __DIR__ . '/../config/function.php';
require_once __DIR__ . '/../config/auth.php';
checkAuth('wip'); // cek apakah sudah login dan punya akses ke menu ini

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

  /* CARD UTAMA */
  .wip-card {
    background: #e8ecf3;
    /* warna dasar neomorph */
    border-radius: 20px;
    width: 260px;
    padding: 0;
    cursor: pointer;

    /* Neomorphism shadow */
    box-shadow:
      8px 8px 16px #c1c5cd,
      -8px -8px 16px #ffffff;

    display: flex;
    flex-direction: column;
    justify-content: space-between;

    transition: 0.25s ease;
    overflow: hidden;

    border-left: none !important;
    /* dihilangkan, diganti glow */
  }

  /* Hover */
  .wip-card:hover {
    transform: translateY(-6px);
    box-shadow:
      4px 4px 12px #c1c5cd,
      -4px -4px 12px #ffffff;
  }

  /* ANIMATED BORDER BIRU - PUTIH */
  .wip-card {
    position: relative;
    z-index: 1;
  }

  .wip-card::after {
    content: "";
    position: absolute;
    inset: 0;
    padding: 2px;
    /* ketebalan */
    border-radius: 20px;
    mask:
      linear-gradient(#fff 0 0) content-box,
      linear-gradient(#fff 0 0);

    background: linear-gradient(120deg,
        #1e88e5,
        #ffffff,
        #1e88e5);

    background-size: 200% 200%;
    animation: softBorder 4s ease infinite;

    /* masking supaya hanya border yg kelihatan */
    -webkit-mask:
      linear-gradient(#fff 0 0) content-box,
      linear-gradient(#fff 0 0);
    -webkit-mask-composite: xor;
    mask-composite: exclude;

    pointer-events: none;
    z-index: -1;
  }

  /* ANIMASI HALUS */
  @keyframes softBorder {
    0% {
      background-position: 0% 50%;
    }

    50% {
      background-position: 100% 50%;
    }

    100% {
      background-position: 0% 50%;
    }
  }

  /* ICON */
  .wip-icon {
    font-size: 50px;
    margin-bottom: 14px;
    color: #56657a;
  }

  /* BODY TENGAH */
  .wip-body {
    text-align: center;
    padding: 40px 10px 50px;
  }

  /* ANGKA QTY */
  .wip-qty {
    font-size: 58px;
    font-weight: 800;
    color: #222;
    line-height: 1;
  }

  /* FOOTER */
  .wip-footer {
    background: #f1f4f9;
    padding: 14px;
    text-align: center;
    border-radius: 0 0 20px 20px;

    /* subtle top-line neomorph */
    box-shadow: inset 0 2px 4px rgba(255, 255, 255, 0.6),
      inset 0 -2px 4px rgba(0, 0, 0, 0.08);
  }

  .wip-ncvs {
    font-size: 20px;
    font-weight: 700;
    color: #333;
  }

  .info-btn {
    position: absolute;
    top: 10px;
    right: 12px;
    background: rgba(255, 255, 255, 0.7);
    backdrop-filter: blur(6px);
    padding: 6px 8px;
    border-radius: 50%;
    cursor: pointer;
    transition: 0.2s;
    z-index: 10;
  }

  .info-btn:hover {
    background: rgba(255, 255, 255, 0.95);
    transform: scale(1.1);
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

    <div class="pagetitle text-black" style="background-color: #f0e6d2; padding: 10px 20px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
      <h1 style="font-size: 1.8rem; font-weight: 700; font-family: 'Roboto', sans-serif;">
        WIP Activity Dashboard
      </h1>
    </div>

    <section class="section">
      <div class="row mb-3">
        <div class="col-md-4">
          <select id="wipFilter" class="form-select">
            <option value="SCAN_IN_WAREHOUSE">In Warehouse</option>
            <option value="SCAN_OUT_TO_VENDOR">Out Vendor</option>
            <option value="SCAN_IN_INCOMING">In Incoming</option>
            <option value="SCAN_OUT_TO_PRODUCTION">Out Production</option>
          </select>
        </div>
      </div>

      <div id="cardContainer" class="d-flex flex-wrap gap-3"></div>
    </section>

    <div class="modal fade" id="infoModal" tabindex="-1">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">

          <div class="modal-header bg-primary text-white">
            <h5 class="modal-title">Informasi WIP</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
          </div>

          <div class="modal-body" id="infoContent">
            <!-- dynamic text -->
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
    function loadWIP() {
      const type = $('#wipFilter').val() || "SCAN_IN_WAREHOUSE"; // ✅ fallback default

      fetch("../config/get_wip.php?type=" + type)
        .then(res => res.json())
        .then(data => {

          let html = "";

          if (data.length === 0) {
            html = `
          <div class="col-12 text-center">
            <div class="alert alert-secondary" 
              style="padding: 20px; font-size: 18px; border-radius: 12px;">
              Belum ada transaksi untuk filter ini.
            </div>
          </div>`;
            $("#cardContainer").html(html);
            return;
          }

          data.forEach(row => {
            let total = 0;

            if (type === "SCAN_IN_WAREHOUSE") total = row.wip_in_wh ?? 0;
            if (type === "SCAN_OUT_TO_VENDOR") total = row.wip_out_vendor ?? 0;
            if (type === "SCAN_IN_INCOMING") total = row.wip_in_incoming ?? 0;
            if (type === "SCAN_OUT_TO_PRODUCTION") total = row.wip_out_prod ?? 0;

            html += `
            <div class="wip-card blue" onclick="goDetail('${row.ncvs}', '${type}')">
              <div class="info-btn" onclick="event.stopPropagation(); showInfo('${type}')">
                <i class="bi bi-info-circle"></i>
              </div>
              <div class="wip-body">
                <div class="wip-qty">${total}</div>
              </div>
              <div class="wip-footer">
                <div class="wip-ncvs">NCVS ${row.ncvs}</div>
              </div>
            </div>
          `;
          });

          $("#cardContainer").html(html);
        });
    }

    $("#wipFilter").on("change", loadWIP);

    // ✅ Perbaikan UTAMA: jalankan loadWIP SETELAH halaman siap
    $(document).ready(function() {
      loadWIP();
    });
  </script>

  <script>
    function showInfo(type) {
      let text = "";

      switch (type) {
        case "SCAN_IN_WAREHOUSE":
          text = `
          <b>WIP IN WAREHOUSE</b> adalah komponen yang sudah diterima dari Production
          dan <b>sedang menunggu proses berikutnya</b>. Komponen masih tersimpan di Warehouse
          dan belum dikirim ke Vendor.
        `;
          break;

        case "SCAN_OUT_TO_VENDOR":
          text = `
          <b>WIP OUT VENDOR</b> adalah komponen yang sudah dikirim dari Warehouse ke Vendor
          dan <b>sedang dikerjakan di Vendor</b>. Komponen belum kembali ke Warehouse.
        `;
          break;

        case "SCAN_IN_INCOMING":
          text = `
          <b>WIP IN INCOMING</b> adalah komponen yang sudah selesai diproses oleh Vendor
          dan sudah kembali ke Warehouse, namun <b>belum diserahkan kembali ke Production</b>.
        `;
          break;

        case "SCAN_OUT_TO_PRODUCTION":
          text = `
          <b>WIP OUT TO PRODUCTION</b> adalah komponen yang sudah dikirim dari Warehouse
          ke Production setelah proses Vendor selesai. Komponen <b>proses selanjutnya di Production</b>.
        `;
          break;
      }

      document.getElementById("infoContent").innerHTML = text;

      const modal = new bootstrap.Modal(document.getElementById('infoModal'));
      modal.show();
    }
  </script>

  <!-- <script>
    function goDetail(ncvs, type) {
      window.open(`dashb-wip-detail.php?ncvs=${ncvs}&type=${type}`, '_blank');
    }
  </script> -->

</body>

</html>