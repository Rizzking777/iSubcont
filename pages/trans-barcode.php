<?php
// menghubungkan php dengan koneksi database
require_once __DIR__ . '/../config/function.php';
require_once __DIR__ . '/../config/auth.php';
checkAuth('trans_barcode'); // cek apakah sudah login dan punya akses ke menu ini

$nik = $_SESSION['nik_user'];
$username = $_SESSION['username'];

// ambil tanggal pencarian dari GET
$search_date = $_GET['search_date'] ?? date('Y-m-d'); // default = hari ini

// query transaksi
$sql = "
  SELECT t.*
  FROM tbl_transaksi t
  WHERE DATE(t.date_created) = ?
  ORDER BY t.id_trans DESC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $search_date);
$stmt->execute();
$result_transaksi = $stmt->get_result();

// --- Ambil vendor berdasarkan komponen di transaksi ---
$vendor_cache = [];
$vendors_all = [];

while ($row = $result_transaksi->fetch_assoc()) {
  $job_order = $row['job_order'];
  $komp_data = json_decode($row['komponen_qty'], true);

  if (!is_array($komp_data)) continue;

  foreach ($komp_data as $item) {
    $komp_id = (int)($item['komponen'] ?? 0);
    if (!$komp_id) continue;

    // caching supaya gak query berulang untuk komponen yang sama
    if (!isset($vendor_cache[$komp_id])) {
      $stmt_v = $conn->prepare("
        SELECT GROUP_CONCAT(DISTINCT v.name_vendor SEPARATOR ', ') AS vendors
        FROM tbl_komponen k
        LEFT JOIN tbl_komponen_proses p 
              ON p.id_input = k.id_komponen OR p.id_output = k.id_komponen
        LEFT JOIN tbl_vendor_proses vp ON vp.id_proses = p.id_proses
        LEFT JOIN tbl_vendor v ON v.id_vendor = vp.id_vendor
        WHERE k.id_komponen = ?
        GROUP BY k.id_komponen
      ");
      $stmt_v->bind_param("i", $komp_id);
      $stmt_v->execute();
      $res_v = $stmt_v->get_result();
      $vendor_name = $res_v->fetch_assoc()['vendors'] ?? '-';
      $vendor_cache[$komp_id] = $vendor_name;
      $stmt_v->close();
    }

    // kumpulkan semua vendor valid
    if (!empty($vendor_cache[$komp_id]) && $vendor_cache[$komp_id] !== '-') {
      $vendors_all[] = $vendor_cache[$komp_id];
    }
  }
}

// --- Bersihkan duplikat dan format akhir ---
$vendors_all = array_unique(explode(', ', implode(', ', $vendors_all)));
$vendors_per_model = !empty($vendors_all) ? implode(', ', $vendors_all) : '-';
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

  .komponen-block {
    text-align: center;
    /* Biar semua isi di dalam card center */
  }

  .komponen-block .d-flex {
    justify-content: center;
    /* Posisikan semua size box ke tengah */
  }

  .size-box {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    /* Tengah vertikal */
    min-width: 50px;
  }

  .size-box .fw-semibold {
    font-size: 0.8rem;
    line-height: 1;
    margin-bottom: 4px;
  }

  .qty-input {
    width: auto;
    min-width: 50px;
    max-width: 70px;
    padding: 3px 5px;
    font-size: 0.8rem;
    text-align: center;
  }

  .fade-in {
    animation: fadeIn 0.3s ease-in-out;
  }

  @keyframes fadeIn {
    from {
      opacity: 0;
      transform: translateY(-4px);
    }

    to {
      opacity: 1;
      transform: translateY(0);
    }
  }

  .truncate-text {
    display: inline-block;
    max-width: 350px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    cursor: pointer;
    color: #0d6efd;
    position: relative;
  }

  .truncate-text:hover {
    text-decoration: underline;
  }

  .full-popup {
    position: absolute;
    background: #fff;
    border: 1px solid #ccc;
    border-radius: 6px;
    padding: 8px 10px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    z-index: 99999;
    width: 320px;
    font-size: 0.9rem;
    color: #212529;
    display: none;
    word-wrap: break-word;
  }
</style>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta content="width=device-width, initial-scale=1.0" name="viewport">

  <title>iSubcont - QR Code Transaction</title>
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
  <script src="https://unpkg.com/bwip-js/dist/bwip-js-min.js"></script>


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
        QR Code Transaction
      </h1>
    </div>

    <!-- Modal Tambah Transaksi -->
    <div class="modal fade" id="tambahTransaksi" tabindex="-1" aria-labelledby="tambahTransaksiLabel" aria-hidden="true">
      <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content shadow-lg border-0 rounded-3">

          <!-- Header -->
          <div class="modal-header bg-primary text-white">
            <h5 class="modal-title d-flex align-items-center" id="tambahTransaksiLabel">
              <i class="bi bi-upc me-2"></i> Add Transaction & QR Code
            </h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>

          <!-- Form -->
          <form action="./../config/function.php" method="POST">
            <div class="modal-body">

              <!-- Section 1: Job Order -->
              <div class="row g-3 mb-3">
                <div class="col-md-6">
                  <label for="job_order" class="form-label">Job Order<span class="text-danger">*</span></label>
                  <select id="job_order" name="job_order" class="form-control select2" required></select>
                </div>
                <div class="col-md-6">
                  <label for="bucket" class="form-label">Bucket</label>
                  <input type="text" id="bucket" name="bucket" class="form-control" readonly>
                </div>
              </div>

              <!-- Section 2: PO Info -->
              <div class="row g-3 mb-3">
                <div class="col-md-6">
                  <label for="po_code" class="form-label">PO Code</label>
                  <input type="text" id="po_code" name="po_code" class="form-control" readonly>
                </div>
                <div class="col-md-6">
                  <label for="po_item" class="form-label">PO Item</label>
                  <input type="text" id="po_item" name="po_item" class="form-control" readonly>
                </div>
              </div>

              <!-- Section 3: Product Info -->
              <div class="row g-3 mb-3">
                <div class="col-md-6">
                  <label for="model" class="form-label">Model</label>
                  <input type="text" id="model" name="model" class="form-control" readonly>
                </div>
                <div class="col-md-6">
                  <label for="style" class="form-label">Style</label>
                  <input type="text" id="style" name="style" class="form-control" readonly>
                </div>
              </div>

              <div class="row g-3 mb-3">
                <div class="col-md-6">
                  <label for="ncvs" class="form-label">NCVS</label>
                  <input type="text" id="ncvs" name="ncvs" class="form-control" readonly>
                </div>
                <div class="col-md-6">
                  <label for="lot" class="form-label">Lot</label>
                  <input id="lot" name="lot" type="text" class="form-control" readonly>
                </div>
              </div>

              <!-- Section 5: Komponen + Size + Qty -->
              <div class="mb-3">
                <label class="form-label fw-semibold">Komponen, Size & Quantity</label>
                <p class="text-muted mb-2" style="font-size: 0.85rem;">
                  Data komponen dan size akan muncul otomatis berdasarkan <strong>Job Order</strong>.
                  Silakan sesuaikan nilai <strong>Qty</strong> dengan barang / komponen yang diterima.
                </p>

                <div id="komponenContainer" class="border rounded p-3 bg-light" style="max-height: 400px; overflow-y: auto;">
                  <div class="text-center text-muted">
                    <em>Silakan pilih Job Order terlebih dahulu...</em>
                  </div>
                </div>
              </div>

            </div> <!-- end modal-body -->

            <!-- Footer -->
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                <i class="bi bi-x-circle me-1"></i> Batal
              </button>
              <button type="submit" id="btnSimpan" class="btn btn-success" name="submit-transaksi" disabled>
                <i class="bi bi-check-circle me-1"></i> Simpan
              </button>

            </div>
          </form>
        </div>
      </div>
    </div>

    <section class="section">
      <div class="row">
        <div class="col-lg-12">
          <div class="card">
            <div class="card-header text-black">
              <div class="d-flex justify-content-between align-items-center w-100">
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#tambahTransaksi">
                  <i class="bi bi-plus-circle me-1"></i> Create
                </button>
              </div>
            </div>

            <div class="card-body" style="margin-top: 10px;">

              <div class="d-flex justify-content-end mb-3">
                <form method="get" class="d-flex align-items-center gap-2">
                  <!-- Date Picker -->
                  <input type="date"
                    name="search_date"
                    class="form-control form-control-sm"
                    value="<?= htmlspecialchars($search_date); ?>">

                  <!-- Search Button -->
                  <button type="submit"
                    class="btn btn-success btn-sm d-flex align-items-center justify-content-center">
                    <i class="bi bi-search"></i>
                  </button>

                  <!-- Reset Button -->
                  <a href="trans-barcode.php"
                    class="btn btn-secondary btn-sm d-flex align-items-center justify-content-center">
                    <i class="bi bi-arrow-repeat"></i>
                  </a>
                </form>
              </div>

              <table id="example1" class="table table-bordered table-striped text-center align-middle nowrap" style="width:100%">
                <thead class="table-light">
                  <tr>
                    <th class="text-center">#</th>
                    <th class="text-center">Job Order</th>
                    <th class="text-center">Bucket</th>
                    <th class="text-center">PO Code</th>
                    <th class="text-center">PO Item</th>
                    <th class="text-center">Model</th>
                    <th class="text-center">Style</th>
                    <th class="text-center">NCVS</th>
                    <th class="text-center">Lot</th>
                    <th class="text-center">Komponen & Qty</th>
                    <th class="text-center">Total Order</th>
                    <th class="text-center">Remaining</th>
                    <th class="text-center">Status Validasi</th>
                    <th class="text-center">Workflow Stage</th>
                    <th class="text-center">Created By</th>
                    <th class="text-center">Validated By</th>
                    <th class="text-center">Options</th>
                    <th class="text-center">Count</th>
                  </tr>
                </thead>
                <tbody>
                  <?php $i = 1; ?>
                  <?php foreach ($result_transaksi as $row) : ?>
                    <tr>
                      <td><?= $i ?></td>
                      <td><?= htmlspecialchars($row["job_order"]); ?></td>
                      <td><?= htmlspecialchars($row["bucket"]); ?></td>
                      <td><?= htmlspecialchars($row["po_code"]); ?></td>
                      <td><?= htmlspecialchars($row["po_item"]); ?></td>
                      <td><?= htmlspecialchars($row["model"]); ?></td>
                      <td><?= htmlspecialchars($row["style"]); ?></td>
                      <td><?= htmlspecialchars($row["ncvs"]); ?></td>
                      <td>
                        <?php
                        $lots = json_decode($row["lot"], true);

                        if (is_array($lots) && !empty($lots)) {
                          // pastikan array berisi angka & urut
                          $lots = array_map('intval', $lots);
                          sort($lots);

                          $ranges = [];
                          $start = $lots[0];
                          $prev = $lots[0];

                          for ($i = 1; $i < count($lots); $i++) {
                            $curr = $lots[$i];
                            // kalau jeda (tidak berurutan), tutup range
                            if ($curr != $prev + 1) {
                              $ranges[] = ($start == $prev) ? "$start" : "$start-$prev";
                              $start = $curr;
                            }
                            $prev = $curr;
                          }

                          // tambahkan range terakhir
                          $ranges[] = ($start == $prev) ? "$start" : "$start-$prev";

                          // tampilkan hasilnya
                          echo htmlspecialchars(implode(", ", $ranges));
                        } else {
                          echo htmlspecialchars($row["lot"]);
                        }
                        ?>
                      </td>

                      <!-- Kolom Komponen & Qty -->
                      <td>
                        <?php
                        $komponen_qty = json_decode($row["komponen_qty"], true);

                        if ($komponen_qty && is_array($komponen_qty)) {
                          // ambil daftar ID komponen unik
                          $ids = array_values(array_unique(array_map(fn($i) => (int)$i['komponen'], $komponen_qty)));

                          $mapKomponen = [];
                          if (!empty($ids)) {
                            $id_list = implode(",", $ids);
                            $sql_komp = "SELECT id_komponen, nama_komponen FROM tbl_komponen WHERE id_komponen IN ($id_list)";
                            $res_komp = $conn->query($sql_komp);
                            while ($k = $res_komp->fetch_assoc()) {
                              $mapKomponen[$k['id_komponen']] = $k['nama_komponen'];
                            }
                          }

                          // group per komponen
                          $grouped = [];
                          foreach ($komponen_qty as $kq) {
                            $id_komp = (int)($kq['komponen'] ?? 0);
                            $size = $kq['size'] ?? '-';
                            $qty = (int)($kq['qty'] ?? 0);
                            $grouped[$id_komp][] = ['size' => $size, 'qty' => $qty];
                          }

                          echo "<ul class='list-unstyled m-0'>";
                          foreach ($grouped as $id => $items) {
                            $nama = htmlspecialchars($mapKomponen[$id] ?? "Unknown");

                            // gabung semua size + qty
                            $parts = array_map(fn($it) => htmlspecialchars($it['size']) . " (" . intval($it['qty']) . ")", $items);
                            $full_text = implode(", ", $parts);

                            // tampilkan versi pendek (misal cuma 5 item pertama)
                            $preview = array_slice($parts, 0, 5);
                            $preview_text = implode(", ", $preview);
                            if (count($parts) > 5) $preview_text .= " ...";

                            echo "<li><strong>{$nama} :</strong> ";
                            echo "<span class='truncate-text' onclick='toggleFullText(this)' data-full=\"" . htmlspecialchars($full_text) . "\">{$preview_text}</span>";
                            echo "</li>";
                          }
                          echo "</ul>";
                        } else {
                          echo "-";
                        }
                        ?>
                      </td>

                      <!-- Kolom Total Order -->
                      <td>
                        <?php
                        $total_order = 0;
                        $lots = json_decode($row["lot"], true);
                        if (!is_array($lots)) $lots = [];

                        if (!empty($lots)) {
                          // buat list lot numeric untuk IN-clause
                          $lot_in = implode(",", array_map('intval', $lots));

                          // Ambil size-size yang user pilih (misal dari transaksi)
                          $sizes = [];
                          $komponen_qty = json_decode($row["komponen_qty"], true);
                          if (is_array($komponen_qty)) {
                            foreach ($komponen_qty as $item) {
                              if (!empty($item['size'])) {
                                $sizes[] = "'" . $conn->real_escape_string($item['size']) . "'";
                              }
                            }
                          }
                          $size_in = !empty($sizes) ? implode(",", $sizes) : "''";

                          $sql_total = "
                            SELECT SUM(qty) as total_order
                            FROM tbl_master_data
                            WHERE job_order = '{$row["job_order"]}'
                              AND bucket = '{$row["bucket"]}'
                              AND po_code = '{$row["po_code"]}'
                              AND po_item = '{$row["po_item"]}'
                              AND model = '{$row["model"]}'
                              AND style = '{$row["style"]}'
                              AND lot IN ($lot_in)
                              AND size IN ($size_in)
                          ";

                          $res_total = $conn->query($sql_total);
                          if ($res_total && $res_total->num_rows > 0) {
                            $row_total = $res_total->fetch_assoc();
                            $total_order = (int)($row_total["total_order"] ?? 0);
                          }
                        }
                        echo $total_order;
                        ?>
                      </td>

                      <!-- Kolom Remaining (per komponen per size) -->
                      <td>
                        <?php
                        // --- Decode data komponen dari transaksi
                        $komponen_qty = json_decode($row["komponen_qty"], true);
                        $grouped = [];
                        $ids_tmp = [];

                        if (is_array($komponen_qty)) {
                          foreach ($komponen_qty as $kq) {
                            $id = (int)($kq['komponen'] ?? 0);
                            $ids_tmp[] = $id;
                            $size = isset($kq['size']) ? (string)$kq['size'] : '-';
                            $qty  = (int)($kq['qty'] ?? 0);
                            $grouped[$id][] = ['size' => $size, 'qty' => $qty];
                          }
                        }

                        // --- Ambil nama komponen
                        $mapKomponen = [];
                        if (!empty($ids_tmp)) {
                          $id_list2 = implode(",", array_unique($ids_tmp));
                          $resk = $conn->query("SELECT id_komponen, nama_komponen FROM tbl_komponen WHERE id_komponen IN ($id_list2)");
                          while ($r = $resk->fetch_assoc()) {
                            $mapKomponen[$r['id_komponen']] = $r['nama_komponen'];
                          }
                        }

                        // --- Ambil total_order per size dari master data
                        $total_order_per_size = [];
                        $lots = json_decode($row["lot"], true);
                        if (!is_array($lots)) $lots = [];
                        if (!empty($lots)) {
                          $lot_in = implode(",", array_map('intval', $lots));
                          $sql_ps = "
                          SELECT size, SUM(qty) AS total_order_per_size
                          FROM tbl_master_data
                          WHERE job_order = '{$row["job_order"]}'
                            AND bucket = '{$row["bucket"]}'
                            AND po_code = '{$row["po_code"]}'
                            AND po_item = '{$row["po_item"]}'
                            AND model = '{$row["model"]}'
                            AND style = '{$row["style"]}'
                            AND lot IN ($lot_in)
                          GROUP BY size
                        ";
                          $res_ps = $conn->query($sql_ps);
                          while ($r = $res_ps->fetch_assoc()) {
                            $total_order_per_size[$r['size']] = (int)$r['total_order_per_size'];
                          }
                        }

                        // --- Hitung total used (komponen_qty) per size
                        $used_per_size = [];
                        $sql_used = "
                          SELECT komponen_qty
                          FROM tbl_transaksi
                          WHERE job_order = '{$row["job_order"]}'
                            AND bucket = '{$row["bucket"]}'
                            AND po_code = '{$row["po_code"]}'
                            AND po_item = '{$row["po_item"]}'
                            AND model = '{$row["model"]}'
                            AND style = '{$row["style"]}'
                            AND lot = '" . $conn->real_escape_string($row["lot"]) . "'
                        ";
                        $res_used = $conn->query($sql_used);
                        if ($res_used && $res_used->num_rows > 0) {
                          while ($ru = $res_used->fetch_assoc()) {
                            $arr_used = json_decode($ru['komponen_qty'], true);
                            if (is_array($arr_used)) {
                              foreach ($arr_used as $u) {
                                $sz = isset($u['size']) ? (string)$u['size'] : '-';
                                $used_per_size[$sz] = ($used_per_size[$sz] ?? 0) + (int)($u['qty'] ?? 0);
                              }
                            }
                          }
                        }

                        // --- Hitung total defect per size
                        $defect_per_size = [];
                        $sql_def = "
                        SELECT defect_qty
                        FROM tbl_transaksi
                        WHERE job_order = '{$row["job_order"]}'
                          AND bucket = '{$row["bucket"]}'
                          AND po_code = '{$row["po_code"]}'
                          AND po_item = '{$row["po_item"]}'
                          AND model = '{$row["model"]}'
                          AND style = '{$row["style"]}'
                          AND lot = '" . $conn->real_escape_string($row["lot"]) . "'
                      ";
                        $res_def = $conn->query($sql_def);
                        if ($res_def && $res_def->num_rows > 0) {
                          while ($rd = $res_def->fetch_assoc()) {
                            $arr_def = json_decode($rd['defect_qty'], true);
                            if (is_array($arr_def)) {
                              foreach ($arr_def as $d) {
                                $sz = isset($d['size']) ? (string)$d['size'] : '-';
                                // Gunakan field 'defect' atau 'qty' tergantung yang ada
                                $val = (int)($d['defect'] ?? $d['qty'] ?? 0);
                                $defect_per_size[$sz] = ($defect_per_size[$sz] ?? 0) + $val;
                              }
                            }
                          }
                        }

                        // --- Tampilkan Remaining per komponen per size
                        if (!empty($grouped)) {
                          echo "<ul class='list-unstyled m-0'>";
                          foreach ($grouped as $id => $items) {
                            $nama = htmlspecialchars($mapKomponen[$id] ?? "Unknown");
                            $parts = [];
                            foreach ($items as $it) {
                              $sz = $it['size'];
                              $total_for_size = $total_order_per_size[$sz] ?? 0;
                              $used_for_size = $used_per_size[$sz] ?? 0;
                              $defect_for_size = $defect_per_size[$sz] ?? 0;

                              // 💡 Rumus tetap sama:
                              $remaining = max(0, ($total_for_size - $used_for_size) + $defect_for_size);
                              $parts[] = htmlspecialchars($sz) . " (" . intval($remaining) . ")";
                            }

                            // gabung semua jadi string
                            $fullText = implode(", ", $parts);
                            $shortText = implode(", ", array_slice($parts, 0, 4)); // tampil 4 item pertama aja

                            if (count($parts) > 4) {
                              $shortText .= " ...";
                              echo "<li><strong>{$nama} :</strong> <span class='truncate-text' data-full='" . htmlspecialchars($fullText, ENT_QUOTES) . "' onclick='toggleFullText(this)'>{$shortText}</span></li>";
                            } else {
                              echo "<li><strong>{$nama} :</strong> {$fullText}</li>";
                            }
                          }
                          echo "</ul>";
                        } else {
                          echo "-";
                        }
                        ?>
                      </td>

                      <!-- Status -->
                      <td>
                        <?php
                        $status = strtoupper($row["status"] ?? "");
                        if (str_contains($status, "PENDING")):
                        ?>
                          <span class="badge bg-warning"><?= htmlspecialchars($row["status"]); ?></span>
                        <?php elseif ($status === "APPROVED"): ?>
                          <span class="badge bg-success"><?= htmlspecialchars($row["status"]); ?></span>
                        <?php elseif ($status === "REJECTED"): ?>
                          <span class="badge bg-danger"><?= htmlspecialchars($row["status"]); ?></span>
                        <?php else: ?>
                          <span class="badge bg-secondary"><?= htmlspecialchars($row["status"]); ?></span>
                        <?php endif; ?>
                      </td>

                      <td><?= htmlspecialchars($row["type_scan"]); ?></td>
                      <td><?= htmlspecialchars($row["created_by"]); ?></td>
                      <td><?= htmlspecialchars($row["validated_by"] ?? "-"); ?></td>

                      <!-- Options -->
                      <td>
                        <?php
                        // Ambil komponen sebelum proses
                        $komponen_qty = json_decode($row["komponen_qty"], true);
                        $ids = array_column($komponen_qty, 'komponen');
                        $id_list = implode(",", array_map('intval', $ids));
                        $mapKomponen = [];
                        if (!empty($id_list)) {
                          $res_komp = $conn->query("SELECT id_komponen,nama_komponen FROM tbl_komponen WHERE id_komponen IN ($id_list)");
                          while ($k = $res_komp->fetch_assoc()) {
                            $mapKomponen[$k['id_komponen']] = $k['nama_komponen'];
                          }
                        }

                        // Grouping komponen
                        $grouped = [];
                        foreach ($komponen_qty as $kq) {
                          $id_komp = (int)$kq['komponen'];
                          $nama = $mapKomponen[$id_komp] ?? "Unknown";
                          $size = $kq['size'] ?? '-';
                          $qty  = $kq['qty'] ?? 0;
                          $grouped[$nama][] = "{$size} ({$qty})";
                        }

                        // Komponen sesudah proses
                        $namaOutputArr = [];
                        if (!empty($ids)) {
                          $sql_out = "
                                  SELECT DISTINCT k2.nama_komponen 
                                  FROM tbl_komponen_proses p
                                  JOIN tbl_komponen k1 ON k1.id_komponen = p.id_input
                                  JOIN tbl_komponen k2 ON k2.id_komponen = p.id_output
                                  WHERE p.id_input IN ($id_list) AND k2.is_deleted = 0
                              ";
                          $res_out = $conn->query($sql_out);
                          if ($res_out && $res_out->num_rows > 0) {
                            while ($o = $res_out->fetch_assoc()) {
                              $namaOutputArr[] = $o['nama_komponen'];
                            }
                          }
                        }
                        ?>

                        <button class="btn btn-sm btn-success btnPrintRow"
                          data-id="<?= $row['id_trans']; ?>"
                          data-joborder="<?= htmlspecialchars($row['job_order']); ?>"
                          data-bucket="<?= htmlspecialchars($row['bucket']); ?>"
                          data-po="<?= htmlspecialchars($row['po_code']); ?>"
                          data-poitem="<?= htmlspecialchars($row['po_item']); ?>"
                          data-model="<?= htmlspecialchars($row['model']); ?>"
                          data-style="<?= htmlspecialchars($row['style']); ?>"
                          data-ncvs="<?= htmlspecialchars($row['ncvs']); ?>"
                          data-lot='<?= json_encode(is_array(json_decode($row['lot'], true)) ? json_decode($row['lot'], true) : [$row['lot']]); ?>'
                          data-komponen='<?= json_encode($grouped); ?>'
                          data-nama_komponen='<?= json_encode($namaOutputArr); ?>'
                          data-barcode="<?= $row['barcode']; ?>"
                          data-vendor="<?= htmlspecialchars($vendors_per_model, ENT_QUOTES); ?>">
                          Print
                        </button>

                      </td>

                      <td>
                        <?php
                        $count = (int)$row["count_barcode"];
                        if ($count <= 0) {
                          echo '<span class="badge bg-primary">0 kali print</span>';
                        } else {
                          echo '<span class="badge bg-primary">' . $count . ' kali di print</span>';
                        }
                        ?>
                      </td>
                    </tr>
                    <?php $i++; ?>
                  <?php endforeach; ?>
                </tbody>
              </table>

            </div>
            <!-- End Table with stripped rows -->
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

  <!-- Generate QR-Code -->
  <!-- <script src="https://cdn.jsdelivr.net/npm/qrcode/build/qrcode.min.js"></script> -->
  <!-- <script src="https://cdn.jsdelivr.net/npm/pica/dist/pica.min.js"></script> -->
  <script src="https://cdn.jsdelivr.net/npm/bwip-js@3.0.9/dist/bwip-js-min.js"></script>


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

  <script>
    let bluetoothDevice = null;
    let printerCharacteristic = null;

    const SERVICE_UUID = 0x18F0;
    const CHARACTERISTIC_UUID = 0x2AF1;
    // const SERVICE_UUID = '000018f0-0000-1000-8000-00805f9b34fb';
    // const CHARACTERISTIC_UUID = '00002af1-0000-1000-8000-00805f9b34fb';


    async function connectPrinterBluetooth() {
      try {
        bluetoothDevice = await navigator.bluetooth.requestDevice({
          acceptAllDevices: true,
          optionalServices: [SERVICE_UUID]
        });
        const server = await bluetoothDevice.gatt.connect();
        const service = await server.getPrimaryService(SERVICE_UUID);
        printerCharacteristic = await service.getCharacteristic(CHARACTERISTIC_UUID);
        console.log("✅ Printer Bluetooth terhubung");
        return true;
      } catch (err) {
        console.error("❌ Gagal konek printer:", err);
        alert("Gagal konek ke printer. Pastikan printer aktif & dekat.");
        return false;
      }
    }

    async function sendToPrinter(data) {
      if (!printerCharacteristic) {
        const ok = await connectPrinterBluetooth();
        if (!ok) return false;
      }

      try {
        const chunkSize = 256; // aman untuk BLE
        for (let i = 0; i < data.length; i += chunkSize) {
          const chunk = data.slice(i, i + chunkSize);
          await printerCharacteristic.writeValue(chunk);
          await new Promise(r => setTimeout(r, 30)); // delay antar-chunk
        }
        return true;
      } catch (err) {
        console.error("❌ Gagal kirim ke printer:", err);
        return false;
      }
    }

    async function printText(text, align = 'left') {
      const alignCode = align === 'center' ? 0x01 : align === 'right' ? 0x02 : 0x00;
      await sendToPrinter(new Uint8Array([0x1B, 0x61, alignCode]));
      const encoder = new TextEncoder('utf-8');
      const data = encoder.encode(text + "\n");
      return sendToPrinter(data);
    }


    /* ✅ Fungsi cetak barcode bitmap untuk Kassen MT200 */
    async function printBarcodeAsImage(barcode) {
      if (!printerCharacteristic) {
        const ok = await connectPrinterBluetooth();
        if (!ok) return false;
      }

      try {
        const cleanBarcode = String(barcode).trim();

        // 1️⃣ generate barcode ke canvas
        const canvas = document.createElement('canvas');
        bwipjs.toCanvas(canvas, {
          bcid: 'code128', // format barcode
          text: cleanBarcode,
          scale: 6, // lebar batang (sesuaikan: makin besar makin tebal)
          height: 18, // tinggi batang (sedikit lebih tinggi biar tajam)
          includetext: false, // teks ditulis manual nanti
          paddingwidth: 6, // sedikit jarak kiri kanan
          paddingheight: 0 // 0 = rapet atas bawah
        });

        // 2️⃣ resize ke lebar printer 58mm (384px)
        const targetWidth = 384;
        const scale = targetWidth / canvas.width;
        const targetHeight = Math.floor(canvas.height * scale);

        const resized = document.createElement('canvas');
        resized.width = targetWidth;
        resized.height = targetHeight;
        const ctx = resized.getContext('2d');
        ctx.fillStyle = 'white';
        ctx.fillRect(0, 0, targetWidth, targetHeight);
        ctx.drawImage(canvas, 0, 0, targetWidth, targetHeight);

        const imageData = ctx.getImageData(0, 0, targetWidth, targetHeight);
        const bytes = convertImageToRaster(imageData);

        // 3️⃣ kirim ke printer
        await sendToPrinter(new Uint8Array([0x1B, 0x61, 0x01])); // center align
        await sendToPrinter(bytes);

        // ❗kurangi feed agar rapet (dulu 0x0A, sekarang ganti jadi 0x0D = sedikit)
        await sendToPrinter(new Uint8Array([0x0D])); // feed pendek

        // 4️⃣ tampilkan kode barcode di bawah (font kecil + tengah)
        await sendToPrinter(new Uint8Array([0x1B, 0x4D, 0x01])); // font kecil (B)
        await printText(barcode, 'center');
        await sendToPrinter(new Uint8Array([0x1B, 0x4D, 0x00])); // kembalikan ke font normal

        return true;
      } catch (err) {
        console.error("❌ Gagal cetak barcode:", err);
        return false;
      }
    }

    function convertImageToRaster(imageData) {
      const {
        width,
        height,
        data
      } = imageData;
      const bytesPerRow = Math.ceil(width / 8);
      const imageBytes = [];

      for (let y = 0; y < height; y++) {
        for (let x = 0; x < bytesPerRow; x++) {
          let byte = 0;
          for (let bit = 0; bit < 8; bit++) {
            const px = x * 8 + bit;
            if (px >= width) continue;
            const i = (y * width + px) * 4;
            const gray = (data[i] + data[i + 1] + data[i + 2]) / 3;
            if (gray < 128) byte |= (0x80 >> bit);
          }
          imageBytes.push(byte);
        }
      }

      const pL = bytesPerRow & 0xff;
      const pH = (bytesPerRow >> 8) & 0xff;
      const yL = height & 0xff;
      const yH = (height >> 8) & 0xff;
      const header = [0x1D, 0x76, 0x30, 0x00, pL, pH, yL, yH];
      return new Uint8Array([...header, ...imageBytes]);
    }

    /* 🔽 Event print utama — alur tetap sama */
    document.addEventListener('DOMContentLoaded', () => {

      // 🧩 Fungsi untuk ubah array lot jadi format range (1-3, 5-6, 9)
      function formatLotRange(arr) {
        if (!Array.isArray(arr) || arr.length === 0) return '-';
        arr = arr.map(Number).sort((a, b) => a - b);

        const ranges = [];
        let start = arr[0];
        let prev = arr[0];

        for (let i = 1; i < arr.length; i++) {
          const curr = arr[i];
          if (curr !== prev + 1) {
            ranges.push(start === prev ? `${start}` : `${start}-${prev}`);
            start = curr;
          }
          prev = curr;
        }

        ranges.push(start === prev ? `${start}` : `${start}-${prev}`);
        return ranges.join(', ');
      }

      // 🖨️ Event untuk tombol print
      document.querySelectorAll('.btnPrintRow').forEach(btn => {
        btn.addEventListener('click', async () => {
          const id = btn.dataset.id;
          const jobOrder = btn.dataset.joborder;
          const bucket = btn.dataset.bucket;
          const po = btn.dataset.po;
          const poItem = btn.dataset.poitem;
          const model = btn.dataset.model;
          const style = btn.dataset.style;
          const ncvs = btn.dataset.ncvs;
          const lot = JSON.parse(btn.dataset.lot || '[]');
          const komponen = JSON.parse(btn.dataset.komponen || '{}');
          const namaOutputArr = JSON.parse(btn.dataset.nama_komponen || '[]');
          const barcode = btn.dataset.barcode;

          if (!barcode) return alert('❌ Barcode kosong');

          // 🧾 Susun teks print
          let printInfo = `${jobOrder} - ${po}-${poItem}\n`;
          printInfo += `NCVS   : ${ncvs}\n`;
          printInfo += `Bucket : ${bucket}\n`;
          printInfo += `Model  : ${model}\n`;
          printInfo += `Style  : ${style}\n`;
          printInfo += `Lot    : ${formatLotRange(lot)}\n`; // ✅ gunakan fungsi range

          // 🟢 Tambahkan vendor di bawah Lot (jika ada)
          const vendor = btn.dataset.vendor || "-";
          if (vendor && vendor !== "-") {
            printInfo += `Vendor : ${vendor}\n`;
          }

          printInfo += `-----------------------------\nKomponen & Qty:\n`;

          // 🧩 Tampilkan komponen dan qty
          for (const [nama, arr] of Object.entries(komponen)) {
            printInfo += `${nama} : ${arr.join(', ')}\n`;
          }

          printInfo += `Output : ${namaOutputArr.length ? namaOutputArr.join(', ') : '-'}\n`;
          printInfo += `-----------------------------\n`;

          // 1️⃣ Print teks
          const okText = await printText(printInfo);
          if (!okText) return alert('❌ Gagal print teks');

          // 2️⃣ Cetak barcode
          const okBarcode = await printBarcodeAsImage(barcode);
          if (!okBarcode) return alert('❌ Gagal print barcode');

          // 3️⃣ Feed kosong
          await printText('\n\n\n');

          // 4️⃣ Update counter
          fetch('./../config/update_count_barcode.php', {
              method: 'POST',
              headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
              },
              body: `id_trans=${id}`
            })
            .then(res => res.json())
            .then(data => {
              if (data.success) {
                const btnEl = document.querySelector(`.btnPrintRow[data-id='${id}']`);
                if (btnEl) btnEl.innerHTML = `<i class="bi bi-upc-scan"></i> ${data.count}`;
              }
            })
            .catch(err => console.error('❌ Gagal update count:', err));

          alert('✅ Print selesai!');
        });
      });
    });
  </script>

  <script>
    // Diagnostic printing: try several tiny patterns (8x8, stripe, checker)
    // Call runPrintDiagnostics() from console (e.g. after connect).
    async function runPrintDiagnostics() {
      if (!printerCharacteristic) {
        console.log('printer not connected - trying to connect...');
        const okc = await connectPrinterBluetooth();
        if (!okc) return console.log('connect failed');
      }
      console.log('Running diagnostics: trying 3 patterns in multiple formats...');

      // small black rectangle 384x32
      const w = 384,
        h = 32;
      const canvas = document.createElement('canvas');
      canvas.width = w;
      canvas.height = h;
      const ctx = canvas.getContext('2d');

      // Pattern A: full black stripe
      ctx.fillStyle = 'white';
      ctx.fillRect(0, 0, w, h);
      ctx.fillStyle = 'black';
      ctx.fillRect(0, 4, w - 1, 24);
      const imgA = ctx.getImageData(0, 0, w, h);

      // Pattern B: checker 8x8
      ctx.fillStyle = 'white';
      ctx.fillRect(0, 0, w, h);
      for (let y = 0; y < h; y += 8) {
        for (let x = 0; x < w; x += 8) {
          if (((x / 8) + (y / 8)) % 2 === 0) ctx.fillStyle = 'black';
          else ctx.fillStyle = 'white';
          ctx.fillRect(x, y, 8, 8);
        }
      }
      const imgB = ctx.getImageData(0, 0, w, h);

      // Pattern C: text big (draw text into canvas)
      ctx.fillStyle = 'white';
      ctx.fillRect(0, 0, w, h);
      ctx.fillStyle = 'black';
      ctx.font = '20px monospace';
      ctx.fillText('TEST IMG', 50, 22);
      const imgC = ctx.getImageData(0, 0, w, h);

      const tests = [{
          name: 'stripe',
          img: imgA
        },
        {
          name: 'checker',
          img: imgB
        },
        {
          name: 'text',
          img: imgC
        },
      ];

      // helper: convert to GS v0 m=0
      function toGSv0(img, m = 0) {
        const {
          width,
          height,
          data
        } = img;
        const bytesPerRow = Math.ceil(width / 8);
        const header = [0x1D, 0x76, 0x30, m, bytesPerRow & 0xFF, (bytesPerRow >> 8) & 0xFF, height & 0xFF, (height >> 8) & 0xFF];
        const body = [];
        for (let y = 0; y < height; y++) {
          for (let xb = 0; xb < bytesPerRow; xb++) {
            let b = 0;
            for (let bit = 0; bit < 8; bit++) {
              const px = xb * 8 + bit;
              if (px >= width) continue;
              const i = (y * width + px) * 4;
              const gray = (data[i] + data[i + 1] + data[i + 2]) / 3;
              if (gray < 128) b |= (0x80 >> bit);
            }
            body.push(b);
          }
        }
        return new Uint8Array([...header, ...body]);
      }

      // helper: ESC* 24-dot
      function toESCstar(img) {
        const {
          width,
          height,
          data
        } = img;
        const bytesPerRow = Math.ceil(width / 8);
        const out = [];
        for (let y = 0; y < height; y += 24) {
          const nL = bytesPerRow & 0xff,
            nH = (bytesPerRow >> 8) & 0xff;
          out.push(0x1B, 0x2A, 0x21, nL, nH);
          for (let xByte = 0; xByte < bytesPerRow; xByte++) {
            for (let k = 0; k < 3; k++) {
              let byte = 0;
              for (let bit = 0; bit < 8; bit++) {
                const yy = y + k * 8 + bit;
                const px = xByte * 8 + bit;
                let bitVal = 0;
                if (yy < height && px < width) {
                  const i = (yy * width + px) * 4;
                  const gray = (data[i] + data[i + 1] + data[i + 2]) / 3;
                  if (gray < 128) bitVal = 1;
                }
                byte |= (bitVal << (7 - bit));
              }
              out.push(byte);
            }
          }
          out.push(0x0A);
        }
        return new Uint8Array(out);
      }

      // helper: Feie-like 0xA2 (msb-first)
      function toFeieA2(img, flip = false) {
        const {
          width,
          height,
          data
        } = img;
        const bytesPerRow = Math.ceil(width / 8);
        const body = [];
        for (let y = 0; y < height; y++) {
          for (let xb = 0; xb < bytesPerRow; xb++) {
            let b = 0;
            for (let bit = 0; bit < 8; bit++) {
              const px = xb * 8 + bit;
              if (px >= width) continue;
              const i = (y * width + px) * 4;
              const gray = (data[i] + data[i + 1] + data[i + 2]) / 3;
              const p = (gray < 128) ? 1 : 0;
              if (flip) b |= (p << bit);
              else b |= (p << (7 - bit));
            }
            body.push(b & 0xFF);
          }
        }
        const header = [0xA2, bytesPerRow & 0xFF, (bytesPerRow >> 8) & 0xFF, height & 0xFF, (height >> 8) & 0xFF];
        return new Uint8Array([...header, ...body]);
      }

      // perform test: send each test as GSv0, ESC*, FeieA2
      for (const t of tests) {
        console.log('Testing pattern:', t.name);
        const p1 = toGSv0(t.img, 0);
        const p2 = toGSv0(t.img, 1);
        const p3 = toESCstar(t.img);
        const p4 = toFeieA2(t.img, false);
        const p5 = toFeieA2(t.img, true);

        const arr = [p1, p2, p3, p4, p5];
        for (let i = 0; i < arr.length; i++) {
          console.log(' sending format', i + 1, 'len', arr[i].length);
          await sendToPrinter(new Uint8Array([0x1B, 0x61, 0x01])); // center
          await sendToPrinter(arr[i]);
          await sendToPrinter(new Uint8Array([0x0A]));
          await new Promise(r => setTimeout(r, 300));
        }

        // wait a bit between patterns
        await new Promise(r => setTimeout(r, 600));
      }

      console.log('Diagnostics done. Check printed patterns on paper.');
    }
  </script>

  <script>
    $(function() {
      // ==============================
      // Job Order Select2 dengan AJAX Search
      // ==============================
      $('#job_order').select2({
        width: "100%",
        dropdownParent: $("#tambahTransaksi"),
        placeholder: "Cari Job Order...",
        allowClear: true,
        minimumInputLength: 1,
        ajax: {
          url: "./../config/ajax.php",
          type: "POST",
          dataType: "json",
          delay: 250,
          data: function(params) {
            return {
              action: "searchJobOrder",
              search: params.term
            };
          },
          processResults: function(data) {
            return {
              results: data.job_order || []
            };
          }
        }
      });

      // Autofocus pada kolom search ketika Select2 dibuka
      $(document).on('select2:open', function() {
        const $search = $('.select2-container--open .select2-search__field');
        if ($search.length) $search.focus();
      });

      // ==============================
      // Ketika Job Order dipilih
      // ==============================
      $('#job_order').on('change select2:select', function() {
        let jobOrder = $(this).val();
        if (!jobOrder) {
          $('#komponenContainer').html(`
        <div class="text-center text-muted">
          <em>Silakan pilih Job Order terlebih dahulu...</em>
        </div>
      `);
          checkFormReady();
          return;
        }

        $('#komponenContainer').html('<div class="text-muted small text-center">⏳ Memuat data komponen...</div>');
        checkFormReady();

        // === Ambil Lot Range otomatis ===
        $.post("./../config/ajax.php", {
          action: "getLotRangeByJobOrder",
          job_order: jobOrder
        }, function(res) {
          console.log("Lot range dari server:", res);
          if (res.success && res.lot_range) {
            $('#lot')
              .val(res.lot_range)
              .attr('placeholder', 'Format: 1-3,5,7-9')
              .data('lot-range', res.lot_range)
            $('#lot').prop('readonly', true);
          } else {
            $('#lot').val('').attr('placeholder', 'Lot tidak ditemukan');
          }
          checkFormReady();
        }, 'json');

        // === Autofill Job Order Detail ===
        $.post("./../config/ajax.php", {
          action: "getJobOrderDetail",
          job_order: jobOrder
        }, function(res) {
          if (res.success) {
            $('#bucket').val(res.data.bucket).prop("readonly", true);
            $('#po_code').val(res.data.po_code).prop("readonly", true);
            $('#po_item').val(res.data.po_item).prop("readonly", true);
            $('#model').val(res.data.model).prop("readonly", true);
            $('#style').val(res.data.style).prop("readonly", true);
            $('#ncvs').val(res.data.ncvs).prop("readonly", true);
          } else {
            alert(res.error || "Data Job Order tidak ditemukan.");
          }
        }, "json");

        // === Ambil Komponen, Size, dan Qty ===
        $.post("./../config/ajax.php", {
          action: "getKomponenSizeQtyByJobOrder",
          job_order: jobOrder
        }, function(res) {
          $('#komponenContainer').empty();

          if (res.success && res.data && res.data.length > 0) {
            let data = res.data;
            let lotInfo = res.lot_range || '';

            data.forEach(k => {
              let komponenId = k.id;
              let komponenNama = k.nama;
              let items = k.items;

              let html = `
            <div class="komponen-block mb-3">
              <div class="fw-bold mb-1 border-bottom pb-1 d-flex justify-content-between align-items-center">
                <span>${komponenNama}</span>
                ${lotInfo ? `<small class="text-muted">Lot: ${lotInfo}</small>` : ''}
              </div>
              <div class="d-flex flex-wrap align-items-start gap-2">
          `;

              items.forEach(it => {
                html += `
              <div class="text-center size-box">
                <div class="fw-semibold">${it.size}</div>
                <input type="number" 
                       name="qty[${komponenId}][${it.size}]"
                       class="form-control form-control-sm text-center qty-input"
                       value="${it.qty}"
                       min="0"
                       max="${it.qty}"
                       data-max="${it.qty}"
                       title="Maksimal ${it.qty}">
              </div>`;
              });

              html += `</div></div>`;
              $('#komponenContainer').append(html);
            });

            // 🧠 Validasi input qty
            $('.qty-input').on('input', function() {
              const max = parseInt($(this).data('max'));
              const val = parseInt($(this).val());

              if (val > max) {
                $(this).val(max);
                const tooltip = $('<div class="qty-tooltip">⚠️ Qty melebihi total order (' + max + ')</div>');
                $('body').append(tooltip);
                const offset = $(this).offset();
                tooltip.css({
                  top: offset.top - $(this).outerHeight() - 10,
                  left: offset.left,
                  position: 'absolute',
                  background: '#ffc107',
                  color: '#000',
                  padding: '4px 8px',
                  borderRadius: '4px',
                  fontSize: '0.8rem',
                  zIndex: 9999,
                  boxShadow: '0 2px 5px rgba(0,0,0,0.2)'
                });
                tooltip.fadeIn(150).delay(1200).fadeOut(400, function() {
                  $(this).remove();
                });
              }
              checkFormReady();
            });

          } else {
            $('#komponenContainer').html(`
          <div class="alert alert-warning text-center fade-in m-0" role="alert">
            ⚠️ <strong>Tidak ada data komponen</strong> yang sesuai dengan Job Order ini.<br>
            <small class="text-muted">Silakan periksa kembali Job Order yang dipilih atau hubungi admin.</small>
          </div>
        `);
          }

          checkFormReady();
        }, "json").fail(function() {
          $('#komponenContainer').html(`
        <div class="alert alert-danger text-center fade-in m-0" role="alert">
          ❌ <strong>Gagal mengambil data komponen dari server.</strong><br>
          <small>Periksa koneksi atau coba beberapa saat lagi.</small>
        </div>
      `);
          checkFormReady();
        });
      });

      // ==============================
      // Ketika LOT diubah oleh user
      // ==============================
      $(document).on('input', '#lot', function() {
        const $lot = $(this);
        const val = $lot.val().trim();
        const jobOrder = $('#job_order').val();
        const lotRange = $lot.data('lot-range');
        const $btnSimpan = $('#btnSimpan');

        if (!jobOrder) {
          showSmallTooltip($lot, '⚠️ Pilih Job Order terlebih dahulu.');
          $btnSimpan.prop('disabled', true);
          return;
        }

        if (!val) {
          $('.qty-input').val('');
          $btnSimpan.prop('disabled', true);
          return;
        }

        const validFormat = /^(\d+(-\d+)?)(,\d+(-\d+)?)*$/;
        if (!validFormat.test(val)) {
          showSmallTooltip($lot, '⚠️ Format salah. Contoh: 1-3,5,7-9');
          $btnSimpan.prop('disabled', true);
          return;
        }

        if (lotRange) {
          let [minLot, maxLot] = lotRange.split('-').map(Number);
          if (isNaN(maxLot)) maxLot = minLot; // fallback kalau cuma "1"
          const lots = parseLotInput(val);
          const invalid = lots.some(l => l < minLot || l > maxLot);
          if (invalid) {
            showSmallTooltip($lot, `⚠️ Lot di luar range (${lotRange})`);
            $btnSimpan.prop('disabled', true);
            return;
          }
        }

        // ✅ Jika valid, update qty dari server
        $.post('./../config/ajax.php', {
          action: 'getKomponenSizeQtyByJobOrder',
          job_order: jobOrder,
          lot_input: val
        }, function(res) {
          if (res.success && res.data && res.data.length > 0) {
            res.data.forEach(k => {
              k.items.forEach(it => {
                const input = $(`input[name="qty[${k.id}][${it.size}]"]`);
                if (input.length) {
                  input.val(it.qty);
                  input.attr('max', it.qty);
                  input.data('max', it.qty);
                }
              });
            });
            $btnSimpan.prop('disabled', false);
          } else {
            showSmallTooltip($lot, '⚠️ Tidak ada data sesuai Lot.');
            $('.qty-input').val('');
            $btnSimpan.prop('disabled', true);
          }
        }, 'json').fail(() => {
          showSmallTooltip($lot, '❌ Gagal mengambil data dari server.');
          $btnSimpan.prop('disabled', true);
        });
      });

      // ==============================
      // Helper Functions
      // ==============================
      function parseLotInput(input) {
        const lots = [];
        input.split(',').forEach(part => {
          if (part.includes('-')) {
            const [start, end] = part.split('-').map(Number);
            for (let i = start; i <= end; i++) lots.push(i);
          } else {
            lots.push(Number(part));
          }
        });
        return lots;
      }

      function showSmallTooltip($el, text) {
        const tooltip = $('<div class="lot-tooltip small bg-warning text-dark px-2 py-1 rounded shadow-sm">' + text + '</div>');
        $('body').append(tooltip);
        const offset = $el.offset();
        tooltip.css({
          top: offset.top - $el.outerHeight() - 10,
          left: offset.left,
          position: 'absolute',
          fontSize: '0.8rem',
          zIndex: 9999
        });
        tooltip.fadeIn(150).delay(1200).fadeOut(400, function() {
          $(this).remove();
        });
      }

      function checkFormReady() {
        const jobOrder = $('#job_order').val();
        const lot = $('#lot').val()?.trim();
        const hasQty = $('.qty-input').length > 0;
        const allQtyValid = $('.qty-input').toArray().every(inp => {
          const val = parseInt($(inp).val()) || 0;
          const max = parseInt($(inp).data('max')) || 0;
          return val >= 0 && val <= max;
        });

        const formValid = jobOrder && lot && hasQty && allQtyValid;
        $('#btnSimpan').prop('disabled', !formValid);
      }
    });
  </script>

  <script>
    // ===============================
    // Fungsi parsing lot
    // ===============================
    function parseLotInput(input) {
      let lots = [];
      let parts = input.split(",");
      parts.forEach(part => {
        part = part.trim();
        if (part.includes("-")) {
          let [start, end] = part.split("-").map(Number);
          for (let i = start; i <= end; i++) {
            lots.push(i);
          }
        } else if (part) {
          lots.push(Number(part));
        }
      });
      return [...new Set(lots)].sort((a, b) => a - b);
    }

    // Contoh validasi sebelum submit
    $("#formTransaksi").on("submit", function(e) {
      let lotInput = $("#lot").val();
      let lots = parseLotInput(lotInput);

      if (lots.length === 0) {
        e.preventDefault();
        alert("Lot tidak boleh kosong atau salah format!");
        return;
      }

      console.log("Lot final:", lots);
      // boleh lanjut submit
    });
  </script>

  <script>
    function toggleFullText(el) {
      // Hapus popup lain kalau ada
      const existing = document.querySelector(".full-popup");
      if (existing) existing.remove();

      const text = el.dataset.full;
      const popup = document.createElement("div");
      popup.className = "full-popup";
      popup.textContent = text;
      document.body.appendChild(popup);

      // Hitung posisi elemen
      const rect = el.getBoundingClientRect();
      const top = rect.bottom + window.scrollY + 5;
      const left = rect.left + window.scrollX;
      popup.style.top = `${top}px`;
      popup.style.left = `${left}px`;

      // Styling popup
      popup.style.position = "absolute";
      popup.style.background = "#fff";
      popup.style.border = "1px solid #ccc";
      popup.style.padding = "8px 12px";
      popup.style.borderRadius = "8px";
      popup.style.boxShadow = "0 4px 12px rgba(0,0,0,0.15)";
      popup.style.zIndex = "9999";
      popup.style.maxWidth = "300px";
      popup.style.display = "block";

      // ⏱ Delay sedikit sebelum pasang event listener biar klik awal gak ikut
      setTimeout(() => {
        document.addEventListener(
          "click",
          function handler(e) {
            if (!popup.contains(e.target) && e.target !== el) {
              popup.remove();
              document.removeEventListener("click", handler);
            }
          }, {
            once: true
          }
        );
      }, 10);
    }
  </script>

</body>

</html>