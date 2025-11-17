<?php
// menghubungkan php dengan koneksi database
require_once __DIR__ . '/../config/function.php';
require_once __DIR__ . '/../config/auth.php';
checkAuth('out_control'); // cek apakah sudah login dan punya akses ke menu ini

$nik = $_SESSION['nik_user'];
$username = $_SESSION['username']; // Query ringkasan per job_order

$job_order = $_GET['job_order'] ?? '';
$lot_param = $_GET['lot'] ?? '';
$id_trans = $_GET['id_trans'] ?? '';

if (!$job_order) {
  die("Job Order tidak ditemukan.");
}

// --- Ambil header info ---
$sql_header = "
    SELECT t.job_order, t.ncvs, t.bucket, t.po_code, t.po_item, 
           t.model, t.style, t.lot, t.date_created
    FROM tbl_transaksi t
    WHERE t.job_order = ?
    LIMIT 1
";
$stmt = $conn->prepare($sql_header);
$stmt->bind_param("s", $job_order);
$stmt->execute();
$res_header = $stmt->get_result();
$header = $res_header->fetch_assoc();

if (!$header) die("Data job order tidak ditemukan.");

// --- Parse lot parameter ---
// Bisa berupa: "1" atau "1-5,7,8-10"
$selectedLots = [];
if (!empty($lot_param)) {
  $parts = array_map('trim', explode(',', $lot_param));
  foreach ($parts as $part) {
    if (strpos($part, '-') !== false) {
      [$start, $end] = explode('-', $part);
      $range = range(intval($start), intval($end));
      $selectedLots = array_merge($selectedLots, $range);
    } else {
      $selectedLots[] = intval($part);
    }
  }
  $selectedLots = array_unique($selectedLots);
}

// --- Ambil transaksi untuk job_order dan filter lot & id_trans ---
$stmt2_sql = "SELECT t.id_trans, t.job_order, t.lot, t.komponen_qty
              FROM tbl_transaksi t 
              WHERE t.job_order = ? AND t.id_trans = ?";
$params = [$job_order, $id_trans];
$types = "si";

$stmt2 = $conn->prepare($stmt2_sql);
$stmt2->bind_param($types, ...$params);
$stmt2->execute();
$res_detail = $stmt2->get_result();

// --- Pivot data total (tanpa per-lot) ---
$pivot = [];
$sizes = [];
$vendor_cache = [];

// --- Pivot data total (tanpa per-lot, gabung semua komponen unik) ---
$pivot = [];
$sizes = [];
$vendor_cache = [];

while ($row = $res_detail->fetch_assoc()) {
  $komp_data = json_decode($row['komponen_qty'], true);
  if (!is_array($komp_data)) continue;

  foreach ($komp_data as $item) {
    $komp_id = $item['komponen'] ?? null;
    $size    = $item['size'] ?? null;
    $qty     = (int)($item['qty'] ?? 0);

    if (!$komp_id || !$size) continue;

    // ambil nama komponen & vendor (cache)
    if (!isset($vendor_cache[$komp_id])) {
      $stmt_k = $conn->prepare("
        SELECT k.nama_komponen, 
               GROUP_CONCAT(DISTINCT v.name_vendor SEPARATOR ', ') AS vendors
        FROM tbl_komponen k
        LEFT JOIN tbl_komponen_proses p 
              ON p.id_input = k.id_komponen OR p.id_output = k.id_komponen
        LEFT JOIN tbl_vendor_proses vp ON vp.id_proses = p.id_proses
        LEFT JOIN tbl_vendor v ON v.id_vendor = vp.id_vendor
        WHERE k.id_komponen = ?
        GROUP BY k.id_komponen
      ");
      $stmt_k->bind_param("i", $komp_id);
      $stmt_k->execute();
      $res_k = $stmt_k->get_result();
      $komp_row = $res_k->fetch_assoc();
      $nama_komp = $komp_row['nama_komponen'] ?? "Komponen #$komp_id";
      $vendors   = $komp_row['vendors'] ?? '-';
      $vendor_cache[$komp_id] = ['nama' => $nama_komp, 'vendors' => $vendors];
      $stmt_k->close();
    } else {
      $nama_komp = $vendor_cache[$komp_id]['nama'];
      $vendors   = $vendor_cache[$komp_id]['vendors'];
    }

    // total per komponen per size (tanpa per-lot)
    if (!isset($pivot[$nama_komp])) {
      $pivot[$nama_komp] = ['vendor' => $vendors];
    }

    $pivot[$nama_komp][$size] = ($pivot[$nama_komp][$size] ?? 0) + $qty;
    $sizes[$size] = true;
  }
}

// --- Urutkan komponen berdasarkan nama ---
ksort($pivot);

// --- Urutkan size secara alami (bukan alfabet) ---
uksort($sizes, 'strnatcmp');

// --- Hitung vendor per model ---
$all_vendors = [];
foreach ($pivot as $komp => $data) {
  foreach ($data as $komp_name => $komp_data) {
    if (isset($komp_data['vendor']) && $komp_data['vendor'] !== '-') {
      $vs = explode(', ', $komp_data['vendor']);
      $all_vendors = array_merge($all_vendors, $vs);
    }
  }
}

// --- Hitung vendor per model (ambil dari pivot yang baru) ---
$all_vendors = [];
foreach ($pivot as $komp_name => $komp_data) {
  if (isset($komp_data['vendor']) && $komp_data['vendor'] !== '-') {
    $vs = explode(', ', $komp_data['vendor']);
    $all_vendors = array_merge($all_vendors, $vs);
  }
}
$all_vendors = array_unique($all_vendors);
$vendors_per_model = !empty($all_vendors) ? implode(', ', $all_vendors) : '-';


// --- Urutkan size dengan logika angka + huruf ---
$sizes = array_keys($sizes);
usort($sizes, function ($a, $b) {
  preg_match('/(\d+)(.*)/', $a, $ma);
  preg_match('/(\d+)(.*)/', $b, $mb);

  $numA = (int)($ma[1] ?? 0);
  $numB = (int)($mb[1] ?? 0);

  if ($numA !== $numB) {
    return $numA - $numB;
  }

  return strcmp($ma[2] ?? '', $mb[2] ?? '');
});

function lotArrayToRanges(array $lots): string
{
  if (empty($lots)) return '-';

  sort($lots, SORT_NUMERIC);
  $ranges = [];
  $start = $prev = null;

  foreach ($lots as $lot) {
    $lot = intval($lot);
    if ($start === null) {
      $start = $prev = $lot;
    } elseif ($lot == $prev + 1) {
      $prev = $lot;
    } else {
      $ranges[] = ($start === $prev) ? $start : "$start-$prev";
      $start = $prev = $lot;
    }
  }
  // Tambahkan range terakhir
  $ranges[] = ($start === $prev) ? $start : "$start-$prev";

  return implode(',', $ranges);
}

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

  #addKomponenBtn {
    margin-top: 0px;
    /* atau sesuai kebutuhan */
    margin-bottom: 5px;
  }

  .komponen-row .form-label {
    display: block;
  }

  .komponen-row .form-control {
    width: 100%;
  }

  .qr-center {
    text-align: center;
    margin-top: 10px;
  }

  .match-height {
    height: calc(1.5em + 0.75rem + 2px);
    /* Cocokkan dengan .form-control Bootstrap */
    display: flex;
    justify-content: center;
    align-items: center;
  }

  @media print {
    @page {
      size: 50mm auto;
      /* Lebar 50mm, tinggi otomatis */
      margin: 0;
      /* Hilangkan margin default browser */
    }

    body {
      width: 50mm;
      font-size: 10px;
      /* Bisa kecilkan font supaya pas */
    }

    /* Hanya print konten modal */
    body * {
      visibility: hidden;
    }

    #barcodeContent<?= $row['id_trans']; ?>,
    #barcodeContent<?= $row['id_trans']; ?>* {
      visibility: visible;
    }

    #barcodeContent<?= $row['id_trans']; ?> {
      position: absolute;
      left: 0;
      top: 0;
    }
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

</head>

<body>

  <!-- Header -->
  <?php
  $page = 'out_control';
  include_once __DIR__ . '/../includes/header.php';
  ?>
  <!-- End Header -->

  <main id="main" class="main">

    <div class="pagetitle text-black" style="background-color: #f0e6d2; padding: 10px 20px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
      <h1 style="font-size: 1.8rem; font-weight: 700; font-family: 'Roboto', sans-serif;">
        Details Subcont Out Control
      </h1>
    </div>

    <section class="section">
      <div class="row">
        <div class="col-lg-12">
          <div class="card">

            <div class="card-body" style="margin-top: 10px;">

              <!-- Header Info -->
              <div class="card mb-4 shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center">
                  <div>
                    <a href="../export/export_excel.php?job_order=<?= urlencode($header['job_order']); ?>&lot=<?= urlencode($lot_param); ?>&id_trans=<?= urlencode($id_trans); ?>"
                      class="btn btn-outline-success btn-sm">
                      <i class="bi bi-file-earmark-excel"></i> Export
                    </a>

                  </div>
                </div>

                <div class="card-body" style="margin-top: 15px;">
                  <div class="row mb-2">
                    <div class="col-sm-4 fw-bold">Job Order:</div>
                    <div class="col-sm-8"><?= htmlspecialchars($header['job_order']); ?></div>
                  </div>
                  <div class="row mb-2">
                    <div class="col-sm-4 fw-bold">NCVS:</div>
                    <div class="col-sm-8"><?= htmlspecialchars($header['ncvs']); ?></div>
                  </div>
                  <div class="row mb-2">
                    <div class="col-sm-4 fw-bold">Bucket:</div>
                    <div class="col-sm-8"><?= htmlspecialchars($header['bucket']); ?></div>
                  </div>
                  <div class="row mb-2">
                    <div class="col-sm-4 fw-bold">PO Code:</div>
                    <div class="col-sm-8"><?= htmlspecialchars($header['po_code']); ?></div>
                  </div>
                  <div class="row mb-2">
                    <div class="col-sm-4 fw-bold">PO Item:</div>
                    <div class="col-sm-8"><?= htmlspecialchars($header['po_item']); ?></div>
                  </div>
                  <div class="row mb-2">
                    <div class="col-sm-4 fw-bold">Model:</div>
                    <div class="col-sm-8"><?= htmlspecialchars($header['model']); ?></div>
                  </div>
                  <div class="row mb-2">
                    <div class="col-sm-4 fw-bold">Style:</div>
                    <div class="col-sm-8"><?= htmlspecialchars($header['style']); ?></div>
                  </div>
                  <div class="row mb-2">
                    <div class="col-sm-4 fw-bold">Lot:</div>
                    <div class="col-sm-8"><?= htmlspecialchars(lotArrayToRanges($selectedLots)); ?></div>
                  </div>

                  <div class="row mb-2">
                    <div class="col-sm-4 fw-bold">To Vendor:</div>
                    <div class="col-sm-8 text-primary"><?= htmlspecialchars($vendors_per_model); ?></div>
                  </div>
                  <div class="row mb-2">
                    <div class="col-sm-4 fw-bold">Date:</div>
                    <div class="col-sm-8"><?= date('d-m-Y H:i:s', strtotime($header['date_created'])); ?></div>
                  </div>
                </div>
              </div>

              <!-- Pivot Table Total -->
              <table class="table table-bordered table-striped text-center align-middle nowrap" style="width:100%">
                <thead class="table-light">
                  <tr>
                    <th class="text-center">Komponen</th>
                    <?php foreach ($sizes as $s): ?>
                      <th class="text-center"><?= htmlspecialchars($s); ?></th>
                    <?php endforeach; ?>
                    <th class="text-center">Total</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($pivot as $komp => $data): ?>
                    <tr>
                      <td><?= htmlspecialchars($komp); ?></td>
                      <?php
                      $row_total = 0;
                      foreach ($sizes as $s):
                        $val = $data[$s] ?? 0;
                        $row_total += $val;
                      ?>
                        <td><?= $val; ?></td>
                      <?php endforeach; ?>
                      <td><strong><?= $row_total; ?></strong></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>


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

</body>

</html>