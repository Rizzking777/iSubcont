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
                  <label for="bucket" class="form-label">Bucket<span class="text-danger">*</span></label>
                  <input type="text" id="bucket" name="bucket" class="form-control" readonly>
                </div>
              </div>

              <!-- Section 2: PO Info -->
              <div class="row g-3 mb-3">
                <div class="col-md-6">
                  <label for="po_code" class="form-label">PO Code<span class="text-danger">*</span></label>
                  <input type="text" id="po_code" name="po_code" class="form-control" readonly>
                </div>
                <div class="col-md-6">
                  <label for="po_item" class="form-label">PO Item<span class="text-danger">*</span></label>
                  <input type="text" id="po_item" name="po_item" class="form-control" readonly>
                </div>
              </div>

              <!-- Section 3: Product Info -->
              <div class="row g-3 mb-3">
                <div class="col-md-6">
                  <label for="model" class="form-label">Model<span class="text-danger">*</span></label>
                  <input type="text" id="model" name="model" class="form-control" readonly>
                </div>
                <div class="col-md-6">
                  <label for="style" class="form-label">Style<span class="text-danger">*</span></label>
                  <input type="text" id="style" name="style" class="form-control" readonly>
                </div>
              </div>

              <div class="row g-3 mb-3">
                <div class="col-md-6">
                  <label for="ncvs" class="form-label">NCVS<span class="text-danger">*</span></label>
                  <input type="text" id="ncvs" name="ncvs" class="form-control" readonly>
                </div>
                <div class="col-md-6">
                  <label for="lot" class="form-label">Lot<span class="text-danger">*</span></label>
                  <input id="lot" name="lot" type="text" class="form-control" placeholder="contoh: 1-8,10,12">
                </div>
              </div>

              <!-- Section 5: Komponen + Size + Qty -->
              <div class="mb-3">
                <button type="button" id="addKomponenBtn" class="btn btn-secondary d-flex align-items-center mb-2">
                  <i class="bi bi-plus-circle me-1"></i> <span>Komponen</span>
                </button>

                <div id="komponenContainer">
                  <div class="row g-3 mb-2 komponen-row">
                    <!-- Komponen -->
                    <div class="col-md-4">
                      <label class="form-label">Komponen<span class="text-danger">*</span></label>
                      <select name="komponen[]" class="form-control select2 komponen-select" required>
                        <option value="">Pilih Komponen</option>
                      </select>
                    </div>

                    <!-- Size -->
                    <div class="col-md-4">
                      <label class="form-label">Size<span class="text-danger">*</span></label>
                      <select name="size[]" class="form-control select2 size-select" required>
                        <option value="">Pilih Size</option>
                      </select>
                    </div>

                    <!-- Qty -->
                    <div class="col-md-3">
                      <label class="form-label">Quantity<span class="text-danger">*</span></label>
                      <input type="number" name="qty[]" class="form-control" placeholder="Input qty" required>
                    </div>

                    <!-- Remove -->
                    <div class="col-md-1 d-flex align-items-end">
                      <button type="button" class="btn btn-danger btn-sm removeKomponenBtn">
                        <i class="bi bi-trash"></i>
                      </button>
                    </div>
                  </div>
                </div>
              </div>

            </div> <!-- end modal-body -->

            <!-- Footer -->
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                <i class="bi bi-x-circle me-1"></i> Batal
              </button>
              <button type="submit" class="btn btn-success" name="submit-transaksi">
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
                        echo is_array($lots) ? implode(", ", $lots) : htmlspecialchars($row["lot"]);
                        ?>
                      </td>

                      <!-- Kolom Komponen & Qty -->
                      <td>
                        <?php
                        $komponen_qty = json_decode($row["komponen_qty"], true);

                        if ($komponen_qty && is_array($komponen_qty)) {
                          $ids = array_column($komponen_qty, 'komponen');
                          if (!empty($ids)) {
                            $id_list = implode(",", array_map('intval', $ids));
                            $sql_komp = "SELECT id_komponen, nama_komponen 
                           FROM tbl_komponen 
                           WHERE id_komponen IN ($id_list)";
                            $res_komp = $conn->query($sql_komp);

                            $mapKomponen = [];
                            while ($k = $res_komp->fetch_assoc()) {
                              $mapKomponen[$k['id_komponen']] = $k['nama_komponen'];
                            }

                            echo "<ul class='list-unstyled m-0'>";
                            foreach ($komponen_qty as $kq) {
                              $id_komp = (int)$kq['komponen'];
                              $nama = htmlspecialchars($mapKomponen[$id_komp] ?? "Unknown");
                              $qty_input = (int)$kq['qty'];
                              echo "<li>$nama ($qty_input)</li>";
                            }
                            echo "</ul>";
                          } else {
                            echo "-";
                          }
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

                        if (is_array($lots) && count($lots) > 0) {
                          foreach ($lots as $lot_value) {
                            $sql_total = "
                SELECT SUM(qty) as total_order
                FROM tbl_master_data
                WHERE job_order = '{$row["job_order"]}'
                  AND bucket = '{$row["bucket"]}'
                  AND po_code = '{$row["po_code"]}'
                  AND po_item = '{$row["po_item"]}'
                  AND model = '{$row["model"]}'
                  AND style = '{$row["style"]}'
                  AND lot = '{$lot_value}'
              ";
                            $res_total = $conn->query($sql_total);
                            if ($res_total && $res_total->num_rows > 0) {
                              $row_total = $res_total->fetch_assoc();
                              $total_order += (int)($row_total["total_order"] ?? 0);
                            }
                          }
                        }
                        echo $total_order;
                        ?>
                      </td>

                      <!-- Kolom Remaining -->
                      <td>
                        <?php
                        $komponen_qty = json_decode($row["komponen_qty"], true);
                        if ($komponen_qty && is_array($komponen_qty)) {
                          $ids = array_column($komponen_qty, 'komponen');
                          $id_list = implode(",", array_map('intval', $ids));

                          // Ambil nama komponen
                          $mapKomponen = [];
                          if (!empty($id_list)) {
                            $sql_komp = "SELECT id_komponen, nama_komponen 
                                FROM tbl_komponen 
                                WHERE id_komponen IN ($id_list)";
                            $res_komp = $conn->query($sql_komp);
                            while ($k = $res_komp->fetch_assoc()) {
                              $mapKomponen[$k['id_komponen']] = $k['nama_komponen'];
                            }
                          }

                          echo "<ul class='list-unstyled m-0'>";
                          foreach ($komponen_qty as $kq) {
                            $id_komp = (int)$kq['komponen'];
                            $nama = htmlspecialchars($mapKomponen[$id_komp] ?? "Unknown");

                            // Hitung total input qty untuk komponen ini di semua transaksi
                            $sql_used = "
                                SELECT komponen_qty
                                FROM tbl_transaksi
                                WHERE job_order = '{$row["job_order"]}'
                                  AND bucket = '{$row["bucket"]}'
                                  AND po_code = '{$row["po_code"]}'
                                  AND po_item = '{$row["po_item"]}'
                                  AND model = '{$row["model"]}'
                                  AND style = '{$row["style"]}'
                                  AND lot = '{$row["lot"]}'
                              ";
                            $res_used = $conn->query($sql_used);
                            $used_qty = 0;
                            if ($res_used && $res_used->num_rows > 0) {
                              while ($row_used = $res_used->fetch_assoc()) {
                                $arr_used = json_decode($row_used["komponen_qty"], true);
                                if ($arr_used && is_array($arr_used)) {
                                  foreach ($arr_used as $u) {
                                    if ((int)$u['komponen'] === $id_komp) {
                                      $used_qty += (int)$u['qty'];
                                    }
                                  }
                                }
                              }
                            }

                            $remaining = $total_order - $used_qty;
                            echo "<li>$nama: $remaining</li>";
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
                        <button class="btn btn-sm btn-primary" data-bs-toggle="modal"
                          data-bs-target="#barcodeModal<?= $row['id_trans']; ?>">
                          <i class="bi bi-upc-scan"></i>
                        </button>

                        <!-- Modal QR -->
                        <div class="modal fade" id="barcodeModal<?= $row['id_trans']; ?>" tabindex="-1" aria-hidden="true">
                          <div class="modal-dialog modal-dialog-centered" style="max-width:220px;">
                            <div class="modal-content" style="border-radius:12px; font-size:12px;">
                              <div class="modal-body" style="padding:12px;">
                                <div style="text-align:left; margin-left:5px;">
                                  <!-- Info -->
                                  <div><strong><?= htmlspecialchars($row['ncvs'] . '-' . $row['po_code'] . '-' . $row['po_item']); ?></strong></div>
                                  <div><?= htmlspecialchars($row['bucket']); ?></div>
                                  <div><?= htmlspecialchars($row['style']); ?></div>
                                  <div><?= htmlspecialchars($row['model']); ?></div>
                                  <div>Lot: <?= is_array(json_decode($row['lot'], true)) ? implode(", ", json_decode($row['lot'], true)) : htmlspecialchars($row['lot']); ?></div>

                                  <!-- Komponen & Qty -->
                                  <div style="margin-top:5px;">
                                    <strong>Komponen Sebelum Proses :</strong>
                                    <?php
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

                                    // Grouping by komponen
                                    $grouped = [];
                                    foreach ($komponen_qty as $kq) {
                                      $id_komp = (int)$kq['komponen'];
                                      $nama = $mapKomponen[$id_komp] ?? "Unknown";
                                      $size = $kq['size'] ?? '-';
                                      $qty  = $kq['qty'] ?? 0;
                                      $grouped[$nama][] = "{$size} ({$qty})";
                                    }

                                    foreach ($grouped as $nama => $arr) {
                                      echo "<div><strong>$nama :</strong> " . implode(", ", $arr) . "</div>";
                                    }
                                    ?>
                                  </div>

                                  <div style="margin-top:5px;">
                                    <strong>Komponen Sesudah Proses :</strong>
                                    <?php
                                    if (!empty($ids)) {
                                      // cari id_output dari tabel proses
                                      $id_list = implode(",", array_map('intval', $ids));
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
                                          echo "<div>" . htmlspecialchars($o['nama_komponen']) . "</div>";
                                        }
                                      } else {
                                        echo "<div>-</div>";
                                      }
                                    } else {
                                      echo "<div>-</div>";
                                    }
                                    ?>
                                  </div>

                                  <!-- QR -->
                                  <div style="margin-top:10px; text-align:center; width:100%;">
                                    <div id="qrcode<?= $row['id_trans']; ?>" style="display:inline-block; padding:5px; background:#fff; border-radius:6px;"></div>
                                  </div>

                                  <!-- Footer -->

                                  <div style="margin-top:20px; text-align:center;">
                                    <button class="btn btn-primary btn-sm printNow"
                                      data-id="<?= $row['id_trans']; ?>"
                                      data-barcode="<?= htmlspecialchars($row['barcode']); ?>">Print</button>
                                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                                  </div>

                                </div>
                              </div>
                            </div>
                          </div>
                        </div>
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
    document.addEventListener('DOMContentLoaded', function() {

      <?php foreach ($result_transaksi as $row): ?>
        const modal<?= $row['id_trans']; ?> = document.getElementById('barcodeModal<?= $row['id_trans']; ?>');
        let qrGenerated<?= $row['id_trans']; ?> = false;

        modal<?= $row['id_trans']; ?>.addEventListener('shown.bs.modal', function() {
          if (!qrGenerated<?= $row['id_trans']; ?>) {
            new QRCode(document.getElementById('qrcode<?= $row['id_trans']; ?>'), {
              text: "<?= $row['barcode']; ?>",
              width: 60,
              height: 60
            });
            qrGenerated<?= $row['id_trans']; ?> = true;
          }
        });
      <?php endforeach; ?>

      // Print via Web Bluetooth MT200
      document.querySelectorAll('.printNow').forEach(btn => {
        btn.addEventListener('click', async function() {
          const id = this.dataset.id;
          const barcode = this.dataset.barcode;

          try {
            // 1. Pilih printer MT200
            const device = await navigator.bluetooth.requestDevice({
              filters: [{
                namePrefix: 'MT200'
              }],
              optionalServices: [0xFFE0]
            });

            const server = await device.gatt.connect();
            const service = await server.getPrimaryService(0xFFE0);
            const characteristic = await service.getCharacteristic(0xFFE1);

            // 2. Ambil data dari modal
            const modalBody = document.getElementById('barcodeModal' + id).querySelector('.modal-body');

            // Ambil teks info
            let lines = [];
            const infoDivs = modalBody.querySelectorAll('div > div, div'); // ambil semua info
            infoDivs.forEach(d => {
              const text = d.innerText.trim();
              if (text) lines.push(text);
            });
            const infoText = lines.join('\n') + '\n\n';

            // 3. Generate QR code canvas
            const qrCanvas = modalBody.querySelector('canvas, img'); // QR code di modal
            let qrData = null;

            if (qrCanvas) {
              const canvas = qrCanvas.tagName === 'CANVAS' ? qrCanvas : qrCanvas;
              qrData = canvas.toDataURL('image/png'); // base64
            }

            // 4. Encode ESC/POS
            function encodeText(str) {
              return new TextEncoder().encode(str);
            }

            // Kirim info text
            await characteristic.writeValue(encodeText(infoText));

            // Kirim QR image jika printer support (MT200 ESC/POS)
            if (qrData) {
              const res = await fetch(qrData);
              const blob = await res.blob();
              const arrayBuffer = await blob.arrayBuffer();
              await characteristic.writeValue(new Uint8Array(arrayBuffer));
            }

            alert('Print berhasil!');

            // 5. Update count_barcode via AJAX
            fetch('./../config/update_count_barcode.php', {
              method: 'POST',
              headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
              },
              body: `id_trans=${id}`
            }).then(res => res.json()).then(data => {
              if (data.success) {
                const btnEl = document.querySelector(`.printBtn[data-id='${id}']`);
                if (btnEl) btnEl.innerHTML = `<i class="bi bi-upc-scan"></i> ${data.count}`;
              }
            });

          } catch (err) {
            console.error('Gagal print via Bluetooth:', err);
            alert('Tidak dapat terhubung ke printer MT200. Pastikan printer menyala dan Bluetooth aktif.');
          }
        });
      });

    });
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

      // Autofocus search ketika select2 dibuka
      $(document).on('select2:open', function() {
        const $search = $('.select2-container--open .select2-search__field');
        if ($search.length) $search.focus();
      });

      // ==============================
      // Autofill fields dari JobOrder
      // ==============================
      $('#job_order').on('change select2:select', function() {
        let jobOrder = $(this).val();
        if (!jobOrder) return;

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
            // ❌ jangan isi lot, biar manual
          } else {
            alert(res.error || "Data Job Order tidak ditemukan");
          }
        }, "json");
      });

      // ==============================
      // Fungsi bikin Select2 Komponen & Size (AJAX)
      // ==============================
      function initKomponenSelect($el) {
        $el.select2({
          width: "100%",
          dropdownParent: $("#tambahTransaksi"),
          placeholder: "Cari Komponen...",
          allowClear: true,
          minimumInputLength: 1,
          ajax: {
            url: "./../config/ajax.php",
            type: "POST",
            dataType: "json",
            delay: 250,
            data: function(params) {
              return {
                action: "searchKomponen",
                model: $("#model").val(),
                search: params.term
              };
            },
            processResults: function(data) {
              return {
                results: data.komponen || []
              };
            }
          }
        });
      }

      function initSizeSelect($el) {
        $el.select2({
          width: "100%",
          dropdownParent: $("#tambahTransaksi"),
          placeholder: "Cari Size...",
          allowClear: true,
          minimumInputLength: 1,
          ajax: {
            url: "./../config/ajax.php",
            type: "POST",
            dataType: "json",
            delay: 250,
            data: function(params) {
              return {
                action: "searchSize",
                job_order: $("#job_order").val(),
                search: params.term
              };
            },
            processResults: function(data) {
              return {
                results: data.sizes || []
              };
            }
          }
        });
      }

      // ==============================
      // Add Komponen Row
      // ==============================
      $('#addKomponenBtn').on('click', function() {
        const $row = $(`
      <div class="row g-3 mb-2 komponen-row">
        <div class="col-md-4">
          <select name="komponen[]" class="form-control komponen-select" required></select>
        </div>
        <div class="col-md-4">
          <select name="size[]" class="form-control size-select" required></select>
        </div>
        <div class="col-md-3">
          <input type="number" name="qty[]" class="form-control" placeholder="Input qty" required>
        </div>
        <div class="col-md-1 d-flex align-items-end">
          <button type="button" class="btn btn-danger btn-sm removeKomponenBtn"><i class="bi bi-trash"></i></button>
        </div>
      </div>
    `);

        $('#komponenContainer').append($row);

        // init select2 untuk row baru
        initKomponenSelect($row.find('.komponen-select'));
        initSizeSelect($row.find('.size-select'));
      });

      // Remove row
      $(document).on('click', '.removeKomponenBtn', function() {
        $(this).closest('.komponen-row').remove();
      });

      // ==============================
      // Init row pertama (yang sudah ada di HTML)
      // ==============================
      initKomponenSelect($('.komponen-select'));
      initSizeSelect($('.size-select'));
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

</body>

</html>