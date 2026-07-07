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

    kp.id_group,

    kp.id_output,

    ko.nama_komponen AS nm_output,

    main_component.id_komponen AS id_main_input,
    main_component.nama_komponen AS nm_main_input,

    GROUP_CONCAT(
        ki.id_komponen
        ORDER BY kp.is_main DESC, ki.nama_komponen ASC
        SEPARATOR '|'
    ) AS id_input_list,

    GROUP_CONCAT(
        ki.nama_komponen
        ORDER BY kp.is_main DESC, ki.nama_komponen ASC
        SEPARATOR '|'
    ) AS nm_input_list,

    GROUP_CONCAT(
        kp.is_main
        ORDER BY kp.is_main DESC, ki.nama_komponen ASC
        SEPARATOR '|'
    ) AS is_main_list,

    MAX(v.id_vendor) AS id_vendor,

    MAX(v.name_vendor) AS name_vendor

FROM tbl_komponen_proses kp

JOIN tbl_komponen ki 
    ON ki.id_komponen = kp.id_input
    AND ki.is_deleted = 0

JOIN tbl_komponen ko 
    ON ko.id_komponen = kp.id_output
    AND ko.is_deleted = 0

JOIN tbl_komponen main_component
    ON main_component.id_komponen = kp.id_group
    AND main_component.is_deleted = 0

LEFT JOIN (
    SELECT DISTINCT
        id_proses,
        id_vendor
    FROM tbl_vendor_proses
) vp
    ON vp.id_proses = kp.id_proses

LEFT JOIN tbl_vendor v
    ON v.id_vendor = vp.id_vendor
    AND v.is_deleted = 0

WHERE 
    ki.model = '{$model}'

GROUP BY

    kp.id_group,
    kp.id_output,
    ko.nama_komponen,
    main_component.id_komponen,
    main_component.nama_komponen

ORDER BY

    ko.nama_komponen ASC,
    main_component.nama_komponen ASC
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

  .group-komponen-card {
    align-items: flex-start;
  }

  .group-child-component {
    font-size: 13px;
    color: #6c757d;
    line-height: 1.45;
    padding-left: 2px;
  }

  .komponen-header {
    font-weight: 600;
    color: #333;
    line-height: 1.45;
  }

  .komponen-sub {
    color: #6c757d;
    font-size: 13px;
    line-height: 1.45;
  }

  .komponen-vendor {
    color: #0d6efd;
    font-size: 13px;
    line-height: 1.45;
  }

  #tableStrukBarcode th,
  #tableStrukBarcode td {
    white-space: nowrap;
    vertical-align: middle;
  }

  #tableStrukBarcode_wrapper .dataTables_length,
  #tableStrukBarcode_wrapper .dataTables_filter {
    margin-bottom: 14px;
  }

  #tableStrukBarcode_wrapper .dataTables_filter input {
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    padding: 6px 10px;
    margin-left: 8px;
  }

  #tableStrukBarcode_wrapper .dataTables_length select {
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    padding: 5px 8px;
  }

  #tableStrukBarcode_wrapper .dataTables_paginate {
    margin-top: 12px;
  }

  #tableStrukBarcode_wrapper .dataTables_length,
  #tableStrukBarcode_wrapper .dataTables_filter {
    margin-bottom: 14px;
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
        class="card-header d-flex justify-content-between align-items-center"
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

      </div>

      <div class="card-body pt-3 px-3 pb-2">

        <table
          id="tableStrukBarcode"
          class="
          table
          table-bordered
          table-hover
          align-middle
          text-nowrap
          w-100
        ">

          <thead class="table-light text-center">

            <tr>
            <tr>
              <th class="text-center">
                <input
                  type="checkbox"
                  id="checkAll">
              </th>
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
              <th>Action</th>
            </tr>

          </thead>

          <tbody id="tbody-struk">
            <!-- AUTO JS -->
          </tbody>

        </table>

      </div>

      <div class="card-footer">
        <div class="mt-3 text-end">

          <button
            type="button"
            id="btnPrintAll"
            class="btn btn-success">
            <i class="bi bi-printer"></i>
            Print All
          </button>

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
  <!-- <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script> -->
  <!-- <script src="https://cdn.jsdelivr.net/npm/bwip-js@3.0.9/dist/bwip-js-min.js"></script> -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

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
    document.addEventListener(
      'DOMContentLoaded',
      function() {

        /* ===================================== */
        /* GET JOB ORDER */
        /* ===================================== */

        const jobOrderElement =
          document.getElementById(
            'job-order-data'
          );

        if (!jobOrderElement) {

          console.error(
            'Element job-order-data tidak ditemukan'
          );

          return;
        }

        const jobOrder =
          jobOrderElement.dataset.job;

        console.log(
          'JOB ORDER =',
          jobOrder
        );

        if (
          !jobOrder ||
          jobOrder.trim() === ''
        ) {

          alert(
            'Job Order tidak ditemukan'
          );

          return;
        }

        /* ===================================== */
        /* FETCH ENDPOINT */
        /* ===================================== */

        const url =
          './../config/get-transaksi.php?job_order=' +
          encodeURIComponent(
            jobOrder
          );

        console.log(
          'FETCH =>',
          url
        );

        fetch(url)

          .then(function(response) {

            if (!response.ok) {

              throw new Error(
                'Response server gagal'
              );
            }

            return response.json();

          })

          .then(function(response) {

            console.log(
              'RESULT:',
              response
            );

            renderTransactionTable(
              response
            );

          })

          .catch(function(error) {

            console.error(error);

            destroyTransactionDataTable();

            document
              .getElementById(
                'tbody-struk'
              )
              .innerHTML = `

              <tr>

                <td
                  colspan="14"
                  class="text-center text-danger py-4"
                >
                  Gagal mengambil data transaksi
                </td>

              </tr>

            `;

          });

      }
    );

    /* ===================================== */
    /* RENDER TABLE */
    /* ===================================== */

    function renderTransactionTable(rows) {

      const tbody =
        document.getElementById(
          'tbody-struk'
        );

      /* ===================================== */
      /* DESTROY OLD DATATABLE */
      /* ===================================== */

      destroyTransactionDataTable();

      tbody.innerHTML = '';

      /* ===================================== */
      /* EMPTY STATE */
      /* ===================================== */

      if (
        !Array.isArray(rows) ||
        rows.length === 0
      ) {

        tbody.innerHTML = `

        <tr>

          <td
            colspan="14"
            class="text-center text-danger py-4"
          >
            Data transaksi tidak ditemukan
          </td>

        </tr>

      `;

        return;
      }

      /* ===================================== */
      /* BUILD ROW */
      /* ===================================== */
      // console.table(rows);
      const rowHtml =
        rows.map(
          function(row, index) {

            console.log({
              barcode: row.barcode,
              qty_smsubcont_fr_cut: row.qty_smsubcont_fr_cut,
              count_barcode: row.count_barcode
            });

            // const isScanned =
            //   row.qty_smsubcont_fr_cut != null &&
            //   Number(row.count_barcode || 0) > 0;

            const isScanned =
              parseInt(row.count_barcode || 0) > 0;

            // console.log(
            //     row.barcode,
            //     row.count_barcode,
            //     isScanned
            // );

            const componentName =
              String(
                row.nm_komponen_in ?? ''
              ) +
              (
                Number(
                  row.is_main_komponen
                ) === 1 ?
                ' *' :
                ''
              );

            const lotData =
              JSON.stringify([
                row.lot ?? ''
              ]);

            return `

            <tr>

              <td class="text-center">
                <input
                type="checkbox"
                class="row-checkbox"
                data-barcode="${escapeHtml(row.barcode)}"
                ${
                    Number(row.count_barcode || 0) > 0
                    ? 'disabled'
                    : ''
                }
              >
              </td>

              <td class="text-center">
                ${index + 1}
              </td>

              <td>
                ${escapeHtml(row.bucket)}
              </td>

              <td>
                ${escapeHtml(row.po_code)}
              </td>

              <td>
                ${escapeHtml(row.po_item)}
              </td>

              <td>
                ${escapeHtml(row.model)}
              </td>

              <td>
                ${escapeHtml(row.style)}
              </td>

              <td>
                ${escapeHtml(row.ncvs)}
              </td>

              <td class="text-center">
                ${escapeHtml(row.lot)}
              </td>

              <td>
                ${escapeHtml(row.size_detail)}
              </td>

              <td class="text-center fw-bold text-success">
                ${escapeHtml(row.total_qty)}
              </td>

              <td>
                ${escapeHtml(componentName)}
              </td>

              <td>
                ${escapeHtml(row.transac_by)}
              </td>

              <td>
                ${escapeHtml(row.created_at)}
              </td>

              <td class="text-center">

                <button
                  type="button"

                  class="
                    btn
                    btn-sm
                    ${isScanned
                      ? 'btn-secondary'
                      : 'btn-primary'
                    }
                    btnPrintRow
                  "

                  ${isScanned
                    ? 'disabled'
                    : ''
                  }

                  data-joborder="${escapeHtml(row.job_order)}"
                  data-bucket="${escapeHtml(row.bucket)}"

                  data-po_code="${escapeHtml(row.po_code)}"
                  data-poitem="${escapeHtml(row.po_item)}"

                  data-model="${escapeHtml(row.model)}"
                  data-style="${escapeHtml(row.style)}"
                  data-ncvs="${escapeHtml(row.ncvs)}"

                  data-created_by="${escapeHtml(row.transac_by)}"
                  data-created_at="${escapeHtml(row.created_at)}"

                  data-nm_komponen_in="${escapeHtml(componentName)}"

                  data-size="${escapeHtml(row.size_detail)}"
                  data-total_qty="${escapeHtml(row.total_qty)}"

                  data-lot='${escapeHtml(lotData)}'

                  data-barcode="${escapeHtml(row.barcode)}"
                  data-count_barcode="${escapeHtml(row.count_barcode ?? 0)}"
                >

                  <i class="bi bi-printer"></i>

                  ${isScanned
                    ? 'Sudah Scan'
                    : 'Print'
                  }

                </button>

              </td>

            </tr>

          `;

          }
        )
        .join('');

      tbody.innerHTML =
        rowHtml;

      /* ===================================== */
      /* INIT DATATABLE */
      /* ===================================== */

      initTransactionDataTable();

    }

    /* ===================================== */
    /* INIT DATATABLE */
    /* ===================================== */

    function initTransactionDataTable() {

      $('#tableStrukBarcode')
        .DataTable({

          pageLength: 10,

          lengthMenu: [
            [10, 20, 50, 100, -1],
            [10, 20, 50, 100, 'All']
          ],

          searching: true,

          paging: true,

          ordering: false,

          info: true,

          responsive: false,

          autoWidth: false,

          scrollX: true,

          language: {

            search: '',

            searchPlaceholder: 'Search data...',

            lengthMenu: 'Show _MENU_ entries',

            info: 'Showing _START_ to _END_ of _TOTAL_ data',

            infoEmpty: 'Showing 0 data',

            zeroRecords: 'Data tidak ditemukan',

            paginate: {

              previous: 'Previous',

              next: 'Next'

            }

          }

        });

    }

    /* ===================================== */
    /* DESTROY DATATABLE */
    /* ===================================== */

    function destroyTransactionDataTable() {

      if (
        $.fn.DataTable.isDataTable(
          '#tableStrukBarcode'
        )
      ) {

        $('#tableStrukBarcode')
          .DataTable()
          .destroy();

      }

    }

    /* ===================================== */
    /* ESCAPE HTML */
    /* ===================================== */

    function escapeHtml(value) {

      return String(
          value ?? ''
        )
        .replace(
          /&/g,
          '&amp;'
        )
        .replace(
          /</g,
          '&lt;'
        )
        .replace(
          />/g,
          '&gt;'
        )
        .replace(
          /"/g,
          '&quot;'
        )
        .replace(
          /'/g,
          '&#039;'
        );

    }
  </script>

  <!-- // ===========================
  // Event Select All
  // =========================== -->
  <script>
    // Saat checkbox "Select All" berubah
    $(document).on(
      'change',
      '#checkAll',
      function() {

        $('.row-checkbox:not(:disabled)')
          .prop(
            'checked',
            $(this).is(':checked')
          );

      }
    );

    // get data dari row untuk print
    function getRowData(btn) {

      let lot = '';

      try {

        lot =
          JSON.parse(
            btn.attr('data-lot')
          )[0];

      } catch (e) {

        lot = '';
      }

      return {

        job_order: btn.data('joborder'),
        bucket: btn.data('bucket'),
        po_code: btn.data('po_code'),
        po_item: btn.data('poitem'),
        model: btn.data('model'),
        style: btn.data('style'),
        ncvs: btn.data('ncvs'),
        size: btn.data('size'),
        qty: btn.data('total_qty'),
        komponen: btn.data('nm_komponen_in'),
        barcode: btn.data('barcode'),
        lot: lot,

        count_barcode: btn.data('count_barcode') || 0

      };

    }
  </script>


  <!-- //============================
  // Fungsi Print
  //============================ -->
  <script>
    // Event print per row
    $(document).on(
      'click',
      '.btnPrintRow',
      function() {

        printLabels([
          getRowData(
            $(this)
          )
        ]);

      }
    );

    // Print massal
    $(document).on(
      'click',
      '#btnPrintAll',
      function() {

        const table =
          $('#tableStrukBarcode')
          .DataTable();

        const rows = [];

        table.$('.row-checkbox:checked')
          .each(function() {

            const btn =
              $(this)
              .closest('tr')
              .find('.btnPrintRow');

            rows.push(
              getRowData(btn)
            );

          });

        console.log(rows);

        if (rows.length === 0) {

          alert(
            'Pilih data terlebih dahulu'
          );

          return;
        }

        printLabels(rows);

      }
    );

    // Print function (dummy)
    function printLabels(rows) {

      const win =
        window.open(
          '',
          '_blank'
        );

      win.rowsToUpdate = rows;

      let htmlLabels = '';

      rows.forEach(function(row, index) {

        htmlLabels += `

        <div class="label">

            <div class="left">

                <div class="line1">
                    ${row.bucket} - ${row.ncvs}
                </div>

                <div class="line2">
                    ${row.po_code} - ${row.po_item}
                </div>

                <div class="line3">
                    ${row.style}
                </div>

                <div class="line4">
                    Lot ${row.lot}
                </div>

                <div class="line5">
                    ${row.komponen}
                </div>

                <div class="product">
                    ${row.model}
                </div>

            </div>

            <div class="right">

                <div class="size">
                    ${row.size}
                </div>

                <div
                    class="qr"
                    id="qr_${index}"
                    data-code="${row.barcode}"
                ></div>

                <div class="barcodeText">
                    ${String(row.barcode).substring(0,7)}
                </div>

            </div>

        </div>

        `;

      });

      win.rowsToUpdate = rows;
      win.document.write(`

    <!DOCTYPE html>

    <html>

    <head>

    <meta charset="UTF-8">

    <title>Print Label</title>

    <style>

    @page{
        size:50mm 30mm;
        margin:0;
    }

    html,
    body{
        margin:0;
        padding:0;
    }

    .label{

        width:50mm;
        height:30mm;

        box-sizing:border-box;

        display:flex;

        padding:1.5mm;

        font-family:Arial,sans-serif;

        overflow:hidden;

        page-break-after:always;
    }

    .label:last-child{
        page-break-after:auto;
    }

    .left{
        width:33mm;
        padding-right:1mm;
    }

    .right{
        width:14mm;
        text-align:center;
    }

    .line1{

        font-size:3.6mm;
        font-weight:bold;

        line-height:4mm;
    }

    .line2,
    .line3,
    .line4,
    .line5{

        font-size:2.5mm;
        line-height:3mm;
    }

    .product{

        font-size:2.5mm;
        font-weight:bold;

        line-height:3mm;
    }

    .size{

        font-size:4.5mm;
        font-weight:bold;
    }

    .size small{

        font-size:2.8mm;
    }

    .barcodeText{

        font-size:2mm;
        margin-top:1mm;
    }

    .qr{

        width:50px;
        height:50px;

        margin:auto;
    }

    </style>

    </head>

    <body>

    ${htmlLabels}

    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"><\/script>

    <script>

    document
    .querySelectorAll('.qr')
    .forEach(function(el){

        new QRCode(
            el,
            {
                text:
                    el.dataset.code,

                width:50,

                height:50
            }
        );

    });

    setTimeout(function(){

    window.print();

      },1000);

      window.onafterprint = function(){

          if(window.opener){

              window.opener.updatePrintCount(
                  window.rowsToUpdate
              );

          }

          window.close();

      };

    <\/script>

    </body>

    </html>

    `);

      win.document.close();

    }


    // ===========================
    // Update Count Print
    // ==========================
    function updatePrintCount(rows) {

      const barcodes =
        rows.map(
          row => row.barcode
        );

      console.log(
        'UPDATE BARCODE =>',
        barcodes
      );

      $.ajax({

        url: './../config/update_count_barcode.php',

        type: 'POST',

        dataType: 'json',

        data: {
          barcodes: barcodes
        },

        success: function(res) {

          console.log(res);

          if (res.status) {

            location.reload();

          }

        },

        error: function(xhr) {

          console.error(xhr);

        }

      });
    }
  </script>






  <!-- Notification print Struk -->
  <?php include_once __DIR__ . '/../includes/notification.php'; ?>

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

          const inputNames =
            String(k.nm_input_list || '')
            .split('|')
            .filter(Boolean);

          const inputIds =
            String(k.id_input_list || '')
            .split('|')
            .filter(Boolean);

          const isMainList =
            String(k.is_main_list || '')
            .split('|')
            .filter(Boolean);

          let childDetailHtml = '';

          inputNames.forEach(function(name, index) {

            const isMain =
              Number(isMainList[index] || 0);

            if (isMain !== 1) {

              childDetailHtml += `
        <div class="group-child-component">
          → ${escapeHtml(name)}
        </div>
      `;

            }

          });

          komponenHtml += `
    <div class="col-md-6">
      <div class="komponen-card group-komponen-card">

        <input 
          class="check-komponen"
          type="checkbox"

          data-id-group="${k.id_group}"
          data-nm-group="${k.nm_main_input}"

          data-id-main-input="${k.id_main_input}"
          data-nm-main-input="${k.nm_main_input}"

          data-id-input-list="${inputIds.join('|')}"
          data-nm-input-list="${inputNames.join('|')}"
          data-is-main-list="${isMainList.join('|')}"

          data-id-output="${k.id_output}"
          data-nm-output="${k.nm_output}"

          data-id-vendor="${k.id_vendor || ''}"
          data-nm-vendor="${k.name_vendor || ''}"
        >

        <div class="komponen-content">

          <div class="komponen-header">
            ${escapeHtml(k.nm_main_input)}
            <span class="text-danger fw-bold ms-1">*</span>
          </div>

          ${childDetailHtml}

          <div class="komponen-sub mt-1">
            to "${escapeHtml(k.nm_output)}"
          </div>

          <div class="komponen-vendor">
            → ${escapeHtml(k.name_vendor ?? '-')}
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

          id_group: $(this).data('id-group'),

          nm_group: $(this).data('nm-group'),

          id_main_input: $(this).data('id-main-input'),

          nm_main_input: $(this).data('nm-main-input'),

          id_input_list: String(
              $(this).data('id-input-list') || ''
            )
            .split('|')
            .filter(Boolean),

          nm_input_list: String(
              $(this).data('nm-input-list') || ''
            )
            .split('|')
            .filter(Boolean),

          is_main_list: String(
              $(this).data('is-main-list') || ''
            )
            .split('|')
            .filter(Boolean),

          id_output: $(this).data('id-output'),

          nm_output: $(this).data('nm-output'),

          id_vendor: $(this).data('id-vendor'),

          nm_vendor: $(this).data('nm-vendor')

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

              const objectName =
                f.group ??
                f.komponen ??
                '-';

              msg +=
                `- LOT ${f.lot} | SIZE ${f.size} | ${objectName}`;

              if (f.reason) {
                msg += ` | ${f.reason}`;
              }

              msg += '\n';

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