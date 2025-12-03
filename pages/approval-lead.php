<?php
// menghubungkan php dengan koneksi database
require_once __DIR__ . '/../config/function.php';
require_once __DIR__ . '/../config/auth.php';
checkAuth('approval_lead'); // cek apakah sudah login dan punya akses ke menu ini

$nik = $_SESSION['nik_user'];
$username = $_SESSION['username'];

// ambil tanggal pencarian dari GET
$search_date = $_GET['search_date'] ?? date('Y-m-d'); // default = hari ini

// Ambil data transaksi terbaru
$sql = "
  SELECT t.*
  FROM tbl_transaksi t
  WHERE DATE(t.date_created) = ?
  ORDER BY t.id_trans ASC
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

  <title>iSubcont - Approval</title>
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
  $page = 'approval_lead';
  include_once __DIR__ . '/../includes/header.php';
  ?>
  <!-- End Header -->

  <main id="main" class="main">

    <div class="pagetitle text-black" style="background-color: #f0e6d2; padding: 10px 20px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
      <h1 style="font-size: 1.8rem; font-weight: 700; font-family: 'Roboto', sans-serif;">
        Approval Lead
      </h1>
    </div>

    <section class="section">
      <div class="row">
        <div class="col-lg-12">
          <div class="card">

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
                  <a href="approval-lead.php"
                    class="btn btn-secondary btn-sm d-flex align-items-center justify-content-center">
                    <i class="bi bi-arrow-repeat"></i>
                  </a>
                </form>
              </div>

              <!-- Table with stripped rows -->
              <div class="table-responsive" id="userTable">

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
                      <th class="text-center">Validate By</th>
                      <th class="text-center">Options</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php $mcount = 1; ?>
                    <?php foreach ($result_transaksi as $row) : ?>
                      <tr>
                        <td><?= $mcount++; ?></td>
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
                          $status = strtoupper($row['status'] ?? "");

                          // kalau status pending atau qty_tidak_sesuai → dua tombol
                          if ($status === "PENDING" || $status === "QTY_TIDAK_SESUAI"):
                          ?>
                            <!-- Approve -->
                            <form method="post" style="display:inline;" onsubmit="return confirm('Yakin ingin approve transaksi ini?');">
                              <input type="hidden" name="id_trans" value="<?= $row['id_trans']; ?>">
                              <input type="hidden" name="status" value="APPROVED">
                              <button type="submit" name="action-transaksi" class="btn btn-sm btn-success" title="Approve">
                                <i class="bi bi-check-lg"></i>
                              </button>
                            </form>

                            <!-- Reject -->
                            <form method="post" style="display:inline;" onsubmit="return confirm('Yakin ingin reject transaksi ini?');">
                              <input type="hidden" name="id_trans" value="<?= $row['id_trans']; ?>">
                              <input type="hidden" name="status" value="REJECTED">
                              <button type="submit" name="action-transaksi" class="btn btn-sm btn-danger" title="Reject">
                                <i class="bi bi-x-lg"></i>
                              </button>
                            </form>

                          <?php elseif ($status === "APPROVED"): ?>
                            <!-- Hanya tombol Reject -->
                            <form method="post" style="display:inline;" onsubmit="return confirm('Yakin ingin reject transaksi ini?');">
                              <input type="hidden" name="id_trans" value="<?= $row['id_trans']; ?>">
                              <input type="hidden" name="status" value="REJECTED">
                              <button type="submit" name="action-transaksi" class="btn btn-sm btn-danger" title="Reject">
                                <i class="bi bi-x-lg"></i>
                              </button>
                            </form>

                          <?php elseif ($status === "REJECTED"): ?>
                            <!-- Hanya tombol Approve -->
                            <form method="post" style="display:inline;" onsubmit="return confirm('Yakin ingin approve transaksi ini?');">
                              <input type="hidden" name="id_trans" value="<?= $row['id_trans']; ?>">
                              <input type="hidden" name="status" value="APPROVED">
                              <button type="submit" name="action-transaksi" class="btn btn-sm btn-success" title="Approve">
                                <i class="bi bi-check-lg"></i>
                              </button>
                            </form>
                          <?php endif; ?>

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
          delay: 3000
        });
        toast.show();
      }
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