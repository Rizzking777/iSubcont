<?php
// menghubungkan php dengan koneksi database
require_once __DIR__ . '/../config/function.php';
require_once __DIR__ . '/../config/auth.php';
checkAuth('trans_barcode'); // cek apakah sudah login dan punya akses ke menu ini

$nik = $_SESSION['nik_user'];
$username = $_SESSION['username']; // Query ringkasan per job_order

// ambil job order dari URL
// $job_order = $_GET['job_order'] ?? '';

$job_order = $_GET['job_order'] ?? '';
if (empty($job_order)) {
  die("<div class='alert alert-danger'>Job Order tidak ditemukan.</div>");
}

// Normalisasi size — biarkan sesuai database
function normalizeSize($s)
{
  $s = strtoupper(trim($s));

  // Jika size angka murni → pad kiri dengan 0
  if (preg_match('/^\d+$/', $s)) {
    return str_pad($s, 3, "0", STR_PAD_LEFT);
  }

  // Jika format misal 4T → jadi 04T
  if (preg_match('/^(\d+)([A-Z]+)$/', $s, $m)) {
    return str_pad($m[1], 2, "0", STR_PAD_LEFT) . $m[2];
  }

  return $s;
}

// 1️⃣ Ambil informasi umum
$infoQuery = "
  SELECT 
      job_order, po_code, po_item, style, model, ncvs, bucket, status_lot,
      MIN(date_updated) AS doc_date
  FROM tbl_master_data
  WHERE job_order = '$job_order'
  GROUP BY job_order
";
$info = $conn->query($infoQuery)->fetch_assoc();

$model = $conn->real_escape_string($info['model'] ?? '');

$komponenList = $conn->query("
  SELECT 
    kp.id_proses,
    kp.is_main,

    -- INPUT
    ki.id_komponen AS id_input,
    ki.nama_komponen AS nm_input,

    -- OUTPUT
    ko.id_komponen AS id_output,
    ko.nama_komponen AS nm_output,

    -- VENDOR
    v.id_vendor,
    v.name_vendor

FROM tbl_komponen_proses kp

JOIN tbl_komponen ki 
  ON kp.id_input = ki.id_komponen

JOIN tbl_komponen ko 
  ON kp.id_output = ko.id_komponen

LEFT JOIN tbl_vendor_proses vp
  ON vp.id_proses = kp.id_proses

LEFT JOIN tbl_vendor v
  ON v.id_vendor = vp.id_vendor
  AND v.is_deleted = 0

WHERE ki.model = '{$model}'
  AND ki.is_deleted = 0
  AND ko.is_deleted = 0

ORDER BY kp.is_main DESC, ki.nama_komponen ASC
")->fetch_all(MYSQLI_ASSOC);

// 2️⃣ Ambil LOT & SIZE
$lots = $conn->query("
  SELECT DISTINCT lot 
  FROM tbl_master_data 
  WHERE job_order = '$job_order'
  ORDER BY CAST(lot AS UNSIGNED)
")->fetch_all(MYSQLI_ASSOC);

$sizes = $conn->query("
  SELECT DISTINCT size
  FROM tbl_master_data
  WHERE job_order = '$job_order'
")->fetch_all(MYSQLI_ASSOC);

if (empty($lots)) die("<div class='alert alert-warning'>Tidak ada LOT ditemukan untuk job order $job_order.</div>");
if (empty($sizes)) die("<div class='alert alert-warning'>Tidak ada SIZE ditemukan untuk job order $job_order.</div>");

// 3️⃣ PLAN DATA (mapping ke komponen dari qty_real)
$planData = [];

$modelName = $conn->real_escape_string($info['model'] ?? '');

$qLotSize = $conn->query("
  SELECT lot, size, SUM(qty) AS total_plan
  FROM tbl_master_data
  WHERE job_order = '{$job_order}'
  GROUP BY lot, size
  ORDER BY CAST(lot AS UNSIGNED), size
");

if ($qLotSize && $qLotSize->num_rows > 0) {
  while ($r = $qLotSize->fetch_assoc()) {
    $lot  = (string)$r['lot'];
    $size = normalizeSize($r['size']);
    $plan = (float)$r['total_plan'];

    $planData[$lot][$size] = $plan;
  }
}

// 🔹 Hitung total plan per LOT dan SIZE (tanpa peduli komponen)
$planTotal = $planData;

// 🔹 Ambil daftar ukuran hanya dari plan resmi
$officialSizes = [];
foreach ($planData as $lot => $sizes) {
  foreach ($sizes as $size => $val) {
    $officialSizes[$size] = true;
  }
}
$officialSizes = array_keys($officialSizes);

// 🔹 Urutkan size berdasarkan angka dan karakter tambahan (misal 07T)
usort($officialSizes, function ($a, $b) {
  preg_match('/\d+/', $a, $ma);
  preg_match('/\d+/', $b, $mb);
  $na = intval($ma[0] ?? 0);
  $nb = intval($mb[0] ?? 0);
  return $na !== $nb ? $na <=> $nb : strnatcasecmp($a, $b);
});

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

  .select2-container {
    width: 100% !important;
  }

  .select2-selection {
    min-height: 38px;
    /* biar seragam sama form-control bootstrap */
    display: flex;
    align-items: center;
  }

  .bg-soft-gray {
    background-color: #BFC9D1 !important;
  }

  .komponen-card {
    position: relative;
    border: 1px solid #e0e0e0;
    border-radius: 10px;
    padding: 14px 14px 14px 45px;
    background: #fff;
    transition: all 0.2s ease;
    cursor: pointer;
  }

  .komponen-card:hover {
    border-color: #0d6efd;
    background: #f8fbff;
  }

  .komponen-card input[type="checkbox"] {
    position: absolute;
    left: 14px;
    top: 18px;
    transform: scale(1.2);
  }

  .komponen-card.checked {
    border-color: #198754;
    background: #f0fff5;
    box-shadow: 0 0 0 1px #198754;
  }

  .komponen-header {
    font-weight: 600;
    font-size: 15px;
  }

  .komponen-sub {
    font-size: 13px;
    color: #6c757d;
    margin-top: 2px;
  }

  .komponen-sub .arrow {
    margin-right: 4px;
  }

  .komponen-vendor {
    font-size: 12px;
    margin-top: 6px;
    color: #0d6efd;
  }
</style>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta content="width=device-width, initial-scale=1.0" name="viewport">

  <title>iSubcont - Transactions</title>
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

  <!-- Barcode -->
  <!-- <script src="https://unpkg.com/bwip-js/dist/bwip-js-min.js"></script> -->

</head>

<body>

  <!-- Header -->
  <?php
  $page = 'trans_barcode';
  include_once __DIR__ . '/../includes/header.php';
  ?>
  <!-- End Header -->

  <main id="main" class="main">

    <div class="pagetitle text-black" style="background-color: #f0e6d2; padding: 10px 20px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
      <h1 style="font-size: 1.8rem; font-weight: 700; font-family: 'Roboto', sans-serif;">
        Details Create Barcode by Lot Basis
      </h1>
    </div>

    <section class="section">
      <div class="row">
        <div class="col-lg-12">
          <div class="card">
            <div class="card-body" style="margin-top: 10px;">
              <div id="global-data"
                data-job="<?= $job_order ?>"
                data-bucket="<?= $info['bucket'] ?>"
                data-po="<?= $info['po_code'] ?>"
                data-po-item="<?= $info['po_item'] ?>"
                data-model="<?= $info['model'] ?>"
                data-style="<?= $info['style'] ?>"
                data-ncvs="<?= $info['ncvs'] ?>"
                data-lot-code="<?= !empty($info['status_lot']) ? $info['status_lot'] : 'N/A' ?>">
              </div>
              <div id="komponen-data" class="d-none"
                data-komponen='<?= json_encode($komponenList, JSON_HEX_APOS | JSON_HEX_QUOT) ?>'>
              </div>

              <div class="container mt-4">

                <!-- Info Job Order -->
                <div class="row g-2">
                  <div class="col-md-3">
                    <label style="font-weight: bold;">Job Order</label>
                    <input class="form-control" readonly style="background-color: #EEEEEE;" value="<?= $info['job_order'] ?>">
                  </div>
                  <div class="col-md-3">
                    <label style="font-weight: bold;">Bucket</label>
                    <input class="form-control" readonly style="background-color: #EEEEEE;" value="<?= $info['bucket'] ?>">
                  </div>
                  <div class="col-md-3">
                    <label style="font-weight: bold;">PO Code</label>
                    <input class="form-control" readonly style="background-color: #EEEEEE;" value="<?= $info['po_code'] ?>">
                  </div>
                  <div class="col-md-3">
                    <label style="font-weight: bold;">PO Item</label>
                    <input class="form-control" readonly style="background-color: #EEEEEE;" value="<?= $info['po_item'] ?>">
                  </div>
                </div>

                <div class="row g-2 mt-2">
                  <div class="col-md-3">
                    <label style="font-weight: bold;">Model</label>
                    <input class="form-control" readonly style="background-color: #EEEEEE;" value="<?= $info['model'] ?>">
                  </div>
                  <div class="col-md-3">
                    <label style="font-weight: bold;">Style</label>
                    <input class="form-control" readonly style="background-color: #EEEEEE;" value="<?= $info['style'] ?>">
                  </div>
                  <div class="col-md-3">
                    <label style="font-weight: bold;">NCVS</label>
                    <input class="form-control" readonly style="background-color: #EEEEEE;" value="<?= $info['ncvs'] ?>">
                  </div>
                  <div class="col-md-3">
                    <label style="font-weight: bold;">Lot Code</label>
                    <input class="form-control" readonly style="background-color: #EEEEEE;" value="<?= !empty($info['status_lot']) ? htmlspecialchars($info['status_lot']) : 'N/A' ?>">
                  </div>
                </div>

                <hr>
                <div style="overflow-x:auto;">
                  <table class="table table-bordered text-center align-middle" style="min-width: 1000px;">
                    <thead class="table-light">
                      <tr>
                        <th>
                          <div class="d-flex justify-content-center align-items-center">
                            <input type="checkbox" id="check_all">
                          </div>
                        </th>
                        <th>LOT</th>
                        <?php foreach ($officialSizes as $size): ?>
                          <th>
                            <div class="d-flex justify-content-center align-items-center gap-1">
                              <span><?= htmlspecialchars($size) ?></span>
                              <input type="checkbox"
                                class="check-size-header"
                                data-size="<?= $size ?>">
                            </div>
                          </th>
                        <?php endforeach; ?>
                        <th>Total</th>
                      </tr>
                    </thead>
                    <tbody>

                      <?php
                      $grandTotal = 0;
                      foreach ($planTotal as $lot => $sizes):
                        $lotTotal = 0;
                      ?>
                        <tr>
                          <td>
                            <div class="d-flex justify-content-center align-items-center">
                              <input type="checkbox"
                                class="check-lot"
                                data-lot="<?= $lot ?>">
                            </div>
                          </td>
                          <td class="fw-bold bg-light"><?= htmlspecialchars($lot) ?></td>

                          <?php foreach ($officialSizes as $size):
                            $val = $sizes[$size] ?? 0;
                            $lotTotal += $val;
                            $isEmpty = ($val == 0);
                          ?>
                            <td class="<?= ($val == 0) ? 'bg-soft-gray text-muted' : '' ?>">

                              <?php if ($val > 0): ?>
                                <div class="d-flex justify-content-center align-items-center gap-1">
                                  <span class="text-success"><?= $val ?></span>
                                  <input type="checkbox"
                                    class="check-cell"
                                    data-lot="<?= $lot ?>"
                                    data-size="<?= $size ?>"
                                    data-qty="<?= $val ?>">
                                </div>
                              <?php endif; ?>

                            </td>
                          <?php endforeach; ?>

                          <td class="fw-bold text-success bg-light"><?= $lotTotal ?></td>
                        </tr>
                      <?php
                        $grandTotal += $lotTotal;
                      endforeach;
                      ?>

                      <!-- Total per size -->
                      <tr class="fw-bold table-light">
                        <td></td>
                        <td>Total</td>
                        <?php foreach ($officialSizes as $size):
                          $sum = 0;
                          foreach ($planTotal as $lot => $sizes) {
                            $sum += $sizes[$size] ?? 0;
                          }
                        ?>
                          <td class="text-success fw-bold"><?= $sum ?></td>
                        <?php endforeach; ?>
                        <td class="text-success fw-bold"><?= $grandTotal ?></td>
                      </tr>

                    </tbody>
                  </table>
                </div>
                <div id="selection-panel" class="alert alert-info d-none">
                  <span id="selection-info"></span>
                  <button class="btn btn-primary btn-sm" id="btn-next">
                    Lanjutkan
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
      </div>
    </section>

    <div class="modal fade" id="modalKonfirmasi" tabindex="-1">
      <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content shadow">

          <!-- HEADER -->
          <div class="modal-header bg-primary text-white">
            <h5 class="modal-title d-flex align-items-center" id="tambahUserModalLabel">
              <i class="bi bi-card-checklist me-2"></i> Konfirmasi Detail Create Barcode
            </h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>

          <div class="modal-body">

            <!-- ===================== -->
            <!-- GLOBAL INFO (CARD) -->
            <!-- ===================== -->
            <div class="card border-0 shadow-sm mb-3">
              <div class="card-body p-3">

                <div class="row text-center">

                  <div class="col-md-3">
                    <div class="small text-muted">Job Order</div>
                    <div class="fw-bold" id="m-job"></div>
                  </div>

                  <div class="col-md-3">
                    <div class="small text-muted">Model</div>
                    <div class="fw-bold" id="m-model"></div>
                  </div>

                  <div class="col-md-3">
                    <div class="small text-muted">Style</div>
                    <div class="fw-bold" id="m-style"></div>
                  </div>

                  <div class="col-md-3">
                    <div class="small text-muted">Lot Code</div>
                    <div class="fw-bold text-primary" id="m-lotcode"></div>
                  </div>

                </div>

              </div>
            </div>

            <!-- ===================== -->
            <!-- SUMMARY -->
            <!-- ===================== -->
            <div class="mb-3">
              <h6 class="fw-bold mb-2">
                Detail Pilihan:
              </h6>

              <div id="m-summary"></div>
            </div>

            <!-- ===================== -->
            <!-- KOMPONEN -->
            <!-- ===================== -->
            <div>
              <h6 class="fw-bold mb-2">
                Pilih Komponen:
              </h6>

              <div id="m-komponen" class="row g-2"></div>
            </div>

          </div>

          <!-- FOOTER -->
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
              <i class="bi bi-x-circle me-1"></i> Batal
            </button>
            <button type="button" class="btn btn-success" id="btn-submit-barcode">
              <i class="bi bi-check-circle me-1"></i> Simpan
            </button>
          </div>

        </div>
      </div>
    </div>

    <!-- ========================= -->
    <!-- HEADER DATA -->
    <!-- ========================= -->

    <div class="d-none">

      <div class="card-body">

        <div class="row">

          <div class="col-md-3">

            <label class="fw-bold">
              Job Order
            </label>

            <!-- JOB ORDER -->
            <div
              id="job-order-data"
              data-job="<?= htmlspecialchars($job_order); ?>"
              class="form-control bg-light">
              <?= htmlspecialchars($job_order); ?>
            </div>

          </div>

        </div>

      </div>

    </div>


    <!-- ========================= -->
    <!-- TABEL STRUK BARCODE -->
    <!-- ========================= -->

    <div class="card shadow-sm mt-4" id="section-struk">

      <div
        class="
        card-header
        d-flex
        justify-content-between
        align-items-center
    "

        style="
        background-color:#f0e6d2;
        color:#1f2937;
        padding:14px 20px;
        border-bottom:1px solid #e5dcc7;
    ">

        <h5
          class="mb-0"

          style="
        font-weight:700;
        font-family:'Roboto', sans-serif;
        font-size:1.2rem;
    ">
          <i class="bi bi-receipt"></i>
          Preview Print Barcode
        </h5>

        <!-- <button
                class="btn btn-light btn-sm"
                onclick="window.print()"
            >
                <i class="bi bi-printer"></i>
                Print
            </button> -->

      </div>

      <div class="card-body">

        <div class="table-responsive">

          <table class="table table-bordered table-hover align-middle text-nowrap">

            <thead class="table-light text-center">

              <tr>
                <th>No</th>
                <th>Bucket</th>
                <th>PO Code</th>
                <th>PO Item</th>
                <th>Model</th>
                <th>Style</th>
                <th>NCVS</th>
                <th>Lot</th>
                <th>Size</th>
                <th>Qty</th>
                <th>Komponen</th>
                <th>User</th>
                <th>Created At</th>
                <!-- <th>Barcode</th> -->
                <th>Action</th>
              </tr>

            </thead>

            <tbody id="tbody-struk">

              <!-- AUTO JS -->

            </tbody>

          </table>

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

  <!-- Barcode Generate -->
  <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
  <!-- <script src="https://cdn.jsdelivr.net/npm/bwip-js@3.0.9/dist/bwip-js-min.js"></script> -->

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

  <!-- ========================= -->
  <!-- AUTO LOAD DATA TRANSAKSI -->
  <!-- ========================= -->

  <script>
    document.addEventListener('DOMContentLoaded', function() {

      // ambil job order
      const el = document.getElementById('job-order-data');

      if (!el) {

        console.error('Element job-order-data tidak ditemukan');

        return;
      }

      const job = el.dataset.job;

      console.log('JOB ORDER =', job);

      if (!job || job.trim() === '') {

        alert('Job Order tidak ditemukan');

        return;
      }

      // endpoint php
      const url =
        './../config/get-transaksi.php?job_order=' +
        encodeURIComponent(job);

      console.log('FETCH =>', url);

      fetch(url)

        .then(response => {

          if (!response.ok) {
            throw new Error('Response server gagal');
          }

          return response.json();
        })

        .then(res => {

          console.log('RESULT:', res);

          const tbody =
            document.getElementById('tbody-struk');

          tbody.innerHTML = '';

          // jika kosong
          if (!Array.isArray(res) || res.length === 0) {

            tbody.innerHTML = `
                <tr>
                    <td colspan="15"
                        class="text-center text-danger">
                        Data transaksi tidak ditemukan
                    </td>
                </tr>
            `;

            return;
          }

          // render table
          res.forEach((row, index) => {

            tbody.innerHTML += `

                <tr>

                    <td class="text-center">
                        ${index + 1}
                    </td>

                    <td>${row.bucket ?? ''}</td>

                    <td>${row.po_code ?? ''}</td>

                    <td>${row.po_item ?? ''}</td>

                    <td>${row.model ?? ''}</td>

                    <td>${row.style ?? ''}</td>

                    <td>${row.ncvs ?? ''}</td>

                    <td class="text-center">
                        ${row.lot ?? ''}
                    </td>

                    <td>
                        ${row.size_detail ?? ''}
                    </td>

                    <td class="text-center fw-bold text-success">
                        ${row.total_qty ?? ''}
                    </td>

                    <td>
                        ${
                            row.is_main_komponen == 1
                                ? `${row.nm_komponen_in ?? ''} *`
                                : `${row.nm_komponen_in ?? ''}`
                        }
                    </td>

                    <td>
                        ${row.transac_by ?? ''}
                    </td>

                    <td>
                        ${row.created_at ?? ''}
                    </td>

                    <!--- <td class="text-center">

                        <div class="small fw-bold mt-1">
                            ${row.barcode ?? ''}
                        </div>

                    </td> !--->

                    <td class="text-center">

                    <button
                        class="btn btn-sm ${
                            row.qty_smsubcont_fr_cut != null &&
                            row.count_barcode != null
                                ? 'btn-secondary'
                                : 'btn-primary'
                        } btnPrintRow"

                        ${
                            row.qty_smsubcont_fr_cut != null &&
                            row.count_barcode != null
                                ? 'disabled'
                                : ''
                        }

                        data-joborder="${row.job_order ?? ''}"
                        data-bucket="${row.bucket ?? ''}"

                        data-po_code="${row.po_code ?? ''}"
                        data-poitem="${row.po_item ?? ''}"

                        data-model="${row.model ?? ''}"
                        data-style="${row.style ?? ''}"
                        data-ncvs="${row.ncvs ?? ''}"

                        data-created_by="${row.transac_by ?? ''}"
                        data-created_at="${row.created_at ?? ''}"

                        data-nm_komponen_in="${
                            row.is_main_komponen == 1
                                ? `${row.nm_komponen_in ?? ''} *`
                                : `${row.nm_komponen_in ?? ''}`
                        }"

                        data-size="${row.size_detail ?? ''}"
                        data-total_qty="${row.total_qty ?? ''}"

                        data-lot='${JSON.stringify([row.lot ?? ""])}'

                        data-barcode="${row.barcode ?? ''}"
                    >
                        <i class="bi bi-printer"></i>

                        ${
                            row.qty_smsubcont_fr_cut != null &&
                            row.count_barcode != null
                                ? 'Sudah Scan'
                                : 'Print'
                        }
                    </button>

                </td>

                </tr>

            `;
          });



        })

        .catch(error => {

          console.error(error);

          document.getElementById('tbody-struk')
            .innerHTML = `
            <tr>
                <td colspan="15"
                    class="text-center text-danger">
                    Gagal mengambil data transaksi
                </td>
            </tr>
        `;
        });

    });
  </script>



  <!-- Modul print -->
  <script>
    let bluetoothDevice = null;
    let printerCharacteristic = null;

    // UUID Printer
    const SERVICE_UUID =
      '000018f0-0000-1000-8000-00805f9b34fb';

    const CHARACTERISTIC_UUID =
      '00002af1-0000-1000-8000-00805f9b34fb';

    // ==========================
    // CEK SUPPORT BLUETOOTH
    // ==========================
    function isBluetoothSupported() {

      if (!navigator.bluetooth) {

        showError(
          '❌ Browser tidak support Bluetooth'
        );

        return false;
      }

      return true;
    }

    // ==========================
    // CONNECT BLUETOOTH
    // ==========================
    async function connectPrinterBluetooth() {

      try {

        // cek support browser
        if (!isBluetoothSupported()) {
          return false;
        }

        // cek android
        const isAndroid =
          /Android/i.test(navigator.userAgent);

        if (isAndroid) {

          showSuccess(
            '📱 Pastikan Bluetooth & Lokasi aktif'
          );
        }

        bluetoothDevice =
          await navigator.bluetooth.requestDevice({

            acceptAllDevices: true,

            optionalServices: [SERVICE_UUID]
          });

        if (!bluetoothDevice) {

          showError(
            '❌ Device printer tidak dipilih'
          );

          return false;
        }

        const server =
          await bluetoothDevice.gatt.connect();

        const service =
          await server.getPrimaryService(
            SERVICE_UUID
          );

        printerCharacteristic =
          await service.getCharacteristic(
            CHARACTERISTIC_UUID
          );

        showSuccess(
          '✅ Printer berhasil terhubung'
        );

        return true;

      } catch (err) {

        console.error(err);

        // Android biasanya ini
        if (
          err.message.includes('User cancelled')
        ) {

          showError(
            '❌ Pemilihan printer dibatalkan'
          );

        } else {

          showError(
            '❌ Gagal connect printer'
          );
        }

        return false;
      }
    }

    // ==========================
    // SEND DATA
    // ==========================
    async function sendToPrinter(data) {

      if (!printerCharacteristic) {

        const ok =
          await connectPrinterBluetooth();

        if (!ok) {
          throw new Error(
            'Printer tidak terhubung'
          );
        }
      }

      try {

        const chunkSize = 100;

        for (
          let i = 0; i < data.length; i += chunkSize
        ) {

          const chunk =
            data.slice(i, i + chunkSize);

          await printerCharacteristic.writeValue(
            chunk
          );

          await new Promise(
            r => setTimeout(r, 60)
          );
        }

        return true;

      } catch (err) {

        console.error(err);

        throw err;
      }
    }

    // ==========================
    // PRINT SMALL TEXT
    // ==========================
    async function printSmallText(
      text,
      align = 'left'
    ) {

      const alignCode =
        align === 'center' ? 0x01 :
        align === 'right' ? 0x02 :
        0x00;

      await sendToPrinter(
        new Uint8Array([0x1B, 0x61, alignCode])
      );

      // font kecil
      await sendToPrinter(
        new Uint8Array([0x1B, 0x4D, 0x01])
      );

      // line rapat
      await sendToPrinter(
        new Uint8Array([0x1B, 0x33, 24])
      );

      const encoder = new TextEncoder();

      await sendToPrinter(
        encoder.encode(text + "\n")
      );

      // reset font
      await sendToPrinter(
        new Uint8Array([0x1B, 0x4D, 0x00])
      );

      // reset spacing
      await sendToPrinter(
        new Uint8Array([0x1B, 0x32])
      );
    }

    // ==========================
    // PRINT TEXT
    // ==========================
    async function printText(
      text,
      align = 'left'
    ) {

      const alignCode =
        align === 'center' ? 0x01 :
        align === 'right' ? 0x02 :
        0x00;

      await sendToPrinter(
        new Uint8Array([0x1B, 0x61, alignCode])
      );

      const encoder = new TextEncoder();

      return sendToPrinter(
        encoder.encode(text + "\n")
      );
    }

    // ==========================
    // PRINT BARCODE
    // ==========================
    async function printBarcode(barcodeText) {

      try {

        const canvas =
          document.createElement("canvas");

        JsBarcode(canvas, barcodeText, {

          format: "CODE128",

          displayValue: true,

          width: 3,
          height: 80,

          margin: 6,

          fontSize: 16,
          textMargin: 2,

          lineColor: "#000000",
          background: "#FFFFFF",

          flat: true
        });

        const ctx =
          canvas.getContext("2d");

        const imageData =
          ctx.getImageData(
            0,
            0,
            canvas.width,
            canvas.height
          );

        const pixels =
          imageData.data;

        const width =
          canvas.width;

        const height =
          canvas.height;

        const bytesPerRow =
          Math.ceil(width / 8);

        const imageBytes = [];

        imageBytes.push(
          0x1D,
          0x76,
          0x30,
          0x00,

          bytesPerRow % 256,
          Math.floor(bytesPerRow / 256),

          height % 256,
          Math.floor(height / 256)
        );

        for (let y = 0; y < height; y++) {

          for (
            let x = 0; x < bytesPerRow; x++
          ) {

            let byte = 0;

            for (
              let bit = 0; bit < 8; bit++
            ) {

              const px =
                x * 8 + bit;

              if (px < width) {

                const i =
                  (y * width + px) * 4;

                const r = pixels[i];
                const g = pixels[i + 1];
                const b = pixels[i + 2];

                const gray =
                  (r + g + b) / 3;

                if (gray < 160) {

                  byte |= (
                    1 << (7 - bit)
                  );
                }
              }
            }

            imageBytes.push(byte);
          }
        }

        // reset printer
        await sendToPrinter(
          new Uint8Array([0x1B, 0x40])
        );

        // darkness
        await sendToPrinter(
          new Uint8Array([0x1D, 0x7C, 0x01])
        );

        // center
        await sendToPrinter(
          new Uint8Array([0x1B, 0x61, 0x01])
        );

        // print image
        await sendToPrinter(
          new Uint8Array(imageBytes)
        );

        // tunggu printer render
        await new Promise(
          r => setTimeout(r, 300)
        );

        // feed sedikit
        await sendToPrinter(
          new Uint8Array([0x0A])
        );

        return true;

      } catch (err) {

        console.error(err);

        showError("❌ Gagal print barcode");

        return false;
      }
    }

    // ==========================
    // EVENT PRINT
    // ==========================
    document.addEventListener(
      "click",
      async function(e) {

        const btn =
          e.target.closest(".btnPrintRow");

        if (!btn) return;

        try {

          const createdBy =
            btn.dataset.created_by || "-";

          const createdAt =
            btn.dataset.created_at || "-";

          const jobOrder =
            btn.dataset.joborder || "-";

          const poCode =
            btn.dataset.po_code || "-";

          const poItem =
            btn.dataset.poitem || "-";

          const ncvs =
            btn.dataset.ncvs || "-";

          const bucket =
            btn.dataset.bucket || "-";

          const style =
            btn.dataset.style || "-";

          const model =
            btn.dataset.model || "-";

          const nmKomponen =
            btn.dataset.nm_komponen_in || "-";

          const size =
            btn.dataset.size || "-";

          const totalQty =
            btn.dataset.total_qty || "-";

          const barcode =
            btn.dataset.barcode || "";

          const lot =
            JSON.parse(
              btn.dataset.lot || "[]"
            );

          if (!barcode) {

            showError("Barcode kosong");

            return;
          }

          const lotText =
            Array.isArray(lot) && lot.length ?
            lot.join(", ") :
            "-";

          // ==========================
          // PRINT TEXT
          // ==========================
          await printSmallText(
            `${createdBy} - ${createdAt}`
          );

          await printSmallText("");

          await printSmallText(`${model}`);

          await printSmallText(
            `NCVS      : ${ncvs}`
          );

          await printSmallText(
            `Job order : ${jobOrder}`
          );

          await printSmallText(
            `Bucket    : ${bucket}`
          );

          await printSmallText(
            `PO-PO Item: ${poCode} - ${poItem}`
          );

          await printSmallText(
            `Style     : ${style}`
          );

          await printSmallText(
            `Komp      : ${nmKomponen}`
          );

          await printSmallText(
            `Lot       : ${lotText}`
          );

          await printSmallText(
            `Size/Qty  : ${size}`
          );

          await printSmallText(
            `Total Qty : ${totalQty}`
          );

          await printSmallText("");

          // ==========================
          // PRINT BARCODE
          // ==========================
          const ok =
            await printBarcode(barcode);

          if (!ok) {
            return;
          }

          // feed bawah
          await printText("");

          // ==========================
          // UPDATE COUNT
          // ==========================
          try {

            const response =
              await fetch(
                './../config/update_count_barcode.php', {

                  method: 'POST',

                  headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                  },

                  body: 'barcode=' +
                    encodeURIComponent(barcode)
                }
              );

            const data =
              await response.json();

            if (!data.status) {

              showError(data.message);
            }

          } catch (err) {

            console.error(err);

            showError(
              'Gagal update count barcode'
            );
          }

          showSuccess("✅ Print berhasil");

        } catch (err) {

          console.error(err);

          showError(
            "❌ Printer tidak terhubung"
          );
        }
      });
  </script>

  <?php include_once __DIR__ . '/../includes/notification.php'; ?>
  <!-- Notification print Struk -->
  <script>
    // ==========================
    // TOAST SUCCESS
    // ==========================
    function showSuccess(message) {

      const toastEl =
        document.getElementById('toastSuccess');

      const toastMsg =
        document.getElementById('toastSuccessMsg');

      toastMsg.innerText = message;

      const toast =
        new bootstrap.Toast(toastEl, {
          delay: 3000
        });

      toast.show();
    }

    // ==========================
    // TOAST ERROR
    // ==========================
    function showError(message) {

      const toastEl =
        document.getElementById('toastError');

      const toastMsg =
        document.getElementById('toastErrorMsg');

      toastMsg.innerText = message;

      const toast =
        new bootstrap.Toast(toastEl, {
          delay: 5000
        });

      toast.show();
    }
  </script>

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
    // ===============================
    // GLOBAL DATA
    // ===============================
    const komponenList = JSON.parse(
      document.getElementById('komponen-data').dataset.komponen || '[]'
    );

    const globalEl = document.getElementById('global-data');

    const globalData = {
      job_order: globalEl.dataset.job,
      bucket: globalEl.dataset.bucket,
      po_code: globalEl.dataset.po,
      po_item: globalEl.dataset.poItem,
      model: globalEl.dataset.model,
      style: globalEl.dataset.style,
      ncvs: globalEl.dataset.ncvs,
      lot_code: globalEl.dataset.lotCode
    };

    // ===============================
    // STATE UPDATE FUNCTIONS
    // ===============================
    function updateLotState(lot) {
      const $cells = $(`.check-cell[data-lot='${lot}']`);
      const checked = $cells.filter(':checked').length;
      const total = $cells.length;
      const $lot = $(`.check-lot[data-lot='${lot}']`);

      $lot.prop('checked', checked === total);
      $lot.prop('indeterminate', checked > 0 && checked < total);
    }

    function updateAllState() {
      const total = $('.check-cell').length;
      const checked = $('.check-cell:checked').length;
      const $all = $('#check_all');

      $all.prop('checked', checked === total);
      $all.prop('indeterminate', checked > 0 && checked < total);
    }

    function updateSizeHeaderState() {
      $('.check-size-header').each(function() {
        const size = $(this).data('size');
        const $cells = $(`.check-cell[data-size='${size}']`);
        const checked = $cells.filter(':checked').length;
        const total = $cells.length;

        $(this).prop('checked', checked === total);
        $(this).prop('indeterminate', checked > 0 && checked < total);
      });
    }

    function updateSelectionPanel() {
      const cells = $('.check-cell:checked');

      if (cells.length === 0) {
        $('#selection-panel').addClass('d-none');
        return;
      }

      const lots = new Set();
      const sizes = new Set();
      let totalQty = 0;

      cells.each(function() {
        lots.add($(this).data('lot'));
        sizes.add($(this).data('size'));
        totalQty += parseFloat($(this).data('qty')) || 0;
      });

      $('#selection-panel').removeClass('d-none');
      $('#selection-info').text(
        `${lots.size} LOT | ${sizes.size} SIZE | ${cells.length} item | Qty: ${totalQty.toLocaleString('id-ID')}`
      );
    }

    // SATU PINTU UPDATE
    function refreshState() {
      updateAllState();
      updateSizeHeaderState();
      updateSelectionPanel();
    }

    // ===============================
    // EVENT HANDLER
    // ===============================

    // per cell
    $(document).on('change', '.check-cell', function() {
      const lot = $(this).data('lot');
      updateLotState(lot);
      refreshState();
    });

    // per lot
    $(document).on('change', '.check-lot', function() {
      const lot = $(this).data('lot');
      const isChecked = $(this).is(':checked');

      $(`.check-cell[data-lot='${lot}']`).prop('checked', isChecked);

      $(this).prop('indeterminate', false);

      refreshState();
    });

    // select all
    $('#check_all').on('change', function() {
      const isChecked = $(this).is(':checked');

      $('.check-cell').prop('checked', isChecked);
      $('.check-lot')
        .prop('checked', isChecked)
        .prop('indeterminate', false);

      refreshState();
    });

    // per size (OPTIMIZED — ga loop semua LOT)
    $(document).on('change', '.check-size-header', function() {
      const size = $(this).data('size');
      const isChecked = $(this).is(':checked');

      const $cells = $(`.check-cell[data-size='${size}']`);
      $cells.prop('checked', isChecked);

      // cuma update lot yang kena
      const affectedLots = new Set();
      $cells.each(function() {
        affectedLots.add($(this).data('lot'));
      });

      affectedLots.forEach(lot => updateLotState(lot));

      refreshState();
    });

    function getSelectionDetail() {
      const result = {};

      $('.check-cell:checked').each(function() {
        const lot = $(this).data('lot');
        const size = $(this).data('size');
        const qty = parseFloat($(this).data('qty')) || 0;

        if (!result[lot]) result[lot] = {};
        result[lot][size] = qty;
      });

      return result;
    }

    // ===============================
    // SHOW MODAL
    // ===============================
    $('#btn-next').on('click', function() {

      const lotSummary = getLotSummary();

      // =========================
      // 1. Isi GLOBAL INFO
      // =========================
      $('#m-job').text(globalData.job_order);
      $('#m-model').text(globalData.model);
      $('#m-style').text(globalData.style);
      $('#m-lotcode').text(globalData.lot_code);

      // =========================
      // 2. Render SUMMARY
      // =========================
      let html = '<div class="row g-2">';

      Object.keys(lotSummary).forEach(lot => {
        html += `
    <div class="col-md-4">
      <div class="border rounded p-2 text-center bg-light">
        <div style="font-size:12px; color:#888;">LOT</div>
        <div style="font-weight:bold; font-size:16px;">${lot}</div>
        <div style="font-size:12px; color:#888;">TOTAL QTY</div>
        <div style="color:green; font-weight:bold; font-size:18px;">
          ${lotSummary[lot].toLocaleString('id-ID')}
        </div>
      </div>
    </div>
  `;
      });

      html += '</div>';

      $('#m-summary').html(html);

      // =========================
      // 3. Render KOMPONEN
      // =========================
      let komponenHtml = '<div class="row g-2">';

      if (!komponenList || komponenList.length === 0) {

        komponenHtml = `
    <div class="alert alert-danger text-center fw-semibold">
      Belum ada data komponen untuk model yang dipilih. <br>
      <small>Silahkan hubungi admin</small>
    </div>
  `;

        // disable tombol submit
        $('#btn-submit-barcode').prop('disabled', true);

      } else {

        komponenHtml = '<div class="row g-2">';

        komponenList.forEach(k => {
          komponenHtml += `
      <div class="col-md-6">
        <div class="komponen-card">

          <input 
            class="check-komponen"
            type="checkbox"
            data-id-input="${k.id_input}"
            data-nm-input="${k.nm_input}"
            data-id-output="${k.id_output}"
            data-nm-output="${k.nm_output}"
            data-id-vendor="${k.id_vendor || ''}"
            data-nm-vendor="${k.name_vendor || ''}"
            data-is-main="${k.is_main || 0}"
          >

          <div class="komponen-content">
            <div class="komponen-header">
              ${k.nm_input}
              ${k.is_main == 1 ? '<span class="text-danger fw-bold ms-1">*</span>' : ''}
            </div>

            <div class="komponen-sub">
              → ${k.nm_output}
            </div>

            <div class="komponen-vendor">
              → ${k.name_vendor ?? '-'}
            </div>
          </div>

        </div>
      </div>
    `;
        });

        komponenHtml += '</div>';

        // default tetap disable (nunggu user pilih)
        $('#btn-submit-barcode').prop('disabled', true);
      }

      $('#m-komponen').html(komponenHtml);

      // =========================
      // 4. OPEN MODAL
      // =========================
      const modal = new bootstrap.Modal(document.getElementById('modalKonfirmasi'));
      modal.show();

    });

    function getLotSummary() {
      const result = {};

      $('.check-cell:checked').each(function() {
        const lot = $(this).data('lot');
        const qty = parseFloat($(this).data('qty')) || 0;

        if (!result[lot]) result[lot] = 0;
        result[lot] += qty;
      });

      return result;
    }

    $('#btn-submit-barcode').on('click', function() {

      const lotSummary = getLotSummary();

      const selectedKomponen = [];

      $('.check-komponen:checked').each(function() {
        selectedKomponen.push({
          id_input: $(this).data('id-input'),
          nm_input: $(this).data('nm-input'),

          id_output: $(this).data('id-output'),
          nm_output: $(this).data('nm-output'),

          id_vendor: $(this).data('id-vendor'),
          nm_vendor: $(this).data('nm-vendor'),

          is_main: $(this).data('is-main')
        });
      });

      const payload = {
        global: globalData,
        detail: getSelectionDetail(),
        komponen: selectedKomponen
      };

      $.ajax({
        url: './../config/function.php',
        method: 'POST',
        data: {
          action: 'create-barcode',
          data: JSON.stringify(payload)
        },

        success: function(res) {

          let r;

          // 🔥 handle kalau response bukan JSON (error PHP dll)
          try {
            r = typeof res === 'object' ? res : JSON.parse(res);
          } catch (e) {
            console.error('Invalid JSON:', res);
            alert('Response server tidak valid');
            return;
          }

          // 🔥 handle error dari backend
          if (r.status === 'error') {
            alert(r.message || 'Terjadi error');
            return;
          }

          // =============================
          // BUILD MESSAGE
          // =============================
          let msg = `✅ Berhasil: ${r.success}\n❌ Gagal: ${r.failed}`;

          if (r.failed > 0 && Array.isArray(r.failed_detail)) {
            msg += '\n\nDetail gagal:\n';

            r.failed_detail.forEach(f => {
              msg += `- LOT ${f.lot} | SIZE ${f.size} | ${f.komponen}\n`;
            });
          }

          alert(msg);

          // =============================
          // RELOAD (kalau ada sukses)
          // =============================
          if (r.success > 0) {
            location.reload();
          }
        },

        error: function(xhr, status, error) {
          console.error('AJAX ERROR:', error);
          alert('Gagal komunikasi ke server');
        }
      });

    });

    $(document).on('change', '.check-komponen', function() {
      const card = $(this).closest('.komponen-card');

      if ($(this).is(':checked')) {
        card.addClass('checked');
      } else {
        card.removeClass('checked');
      }

      const checked = $('.check-komponen:checked').length;

      $('#btn-submit-barcode').prop('disabled', checked === 0);
    });
  </script>

</body>

</html>