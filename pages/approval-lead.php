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
                            // ambil daftar ID komponen unik
                            $ids = array_values(array_unique(array_map(function ($i) {
                              return (int)$i['komponen'];
                            }, $komponen_qty)));
                            $mapKomponen = [];
                            if (!empty($ids)) {
                              $id_list = implode(",", $ids);
                              $sql_komp = "SELECT id_komponen, nama_komponen FROM tbl_komponen WHERE id_komponen IN ($id_list)";
                              $res_komp = $conn->query($sql_komp);
                              while ($k = $res_komp->fetch_assoc()) {
                                $mapKomponen[$k['id_komponen']] = $k['nama_komponen'];
                              }
                            }

                            // group per komponen -> array of [size, qty]
                            $grouped = [];
                            foreach ($komponen_qty as $kq) {
                              $id_komp = (int)($kq['komponen'] ?? 0);
                              $size = isset($kq['size']) ? (string)$kq['size'] : '-';
                              $qty = (int)($kq['qty'] ?? 0);
                              $grouped[$id_komp][] = ['size' => $size, 'qty' => $qty];
                            }

                            echo "<ul class='list-unstyled m-0'>";
                            foreach ($grouped as $id => $items) {
                              $nama = htmlspecialchars($mapKomponen[$id] ?? "Unknown");
                              $parts = [];
                              foreach ($items as $it) {
                                $parts[] = htmlspecialchars($it['size']) . " (" . intval($it['qty']) . ")";
                              }
                              echo "<li><strong>{$nama} :</strong> " . implode(", ", $parts) . "</li>";
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

                        <!-- Kolom Remaining (per component per size) -->
                        <td>
                          <?php
                          // pastikan kita punya grouped (reuse dari block Komponen & Qty), kalau belum build ulang:
                          if (!isset($grouped)) {
                            $komponen_qty = json_decode($row["komponen_qty"], true);
                            $grouped = [];
                            if (is_array($komponen_qty)) {
                              $ids_tmp = [];
                              foreach ($komponen_qty as $kq) {
                                $id = (int)($kq['komponen'] ?? 0);
                                $ids_tmp[] = $id;
                                $size = isset($kq['size']) ? (string)$kq['size'] : '-';
                                $qty = (int)($kq['qty'] ?? 0);
                                $grouped[$id][] = ['size' => $size, 'qty' => $qty];
                              }
                              if (!empty($ids_tmp)) {
                                $id_list2 = implode(",", array_unique($ids_tmp));
                                $mapKomponen = [];
                                $resk = $conn->query("SELECT id_komponen,nama_komponen FROM tbl_komponen WHERE id_komponen IN ($id_list2)");
                                while ($r = $resk->fetch_assoc()) $mapKomponen[$r['id_komponen']] = $r['nama_komponen'];
                              }
                            }
                          }

                          // 1) ambil total_order per size dari tbl_master_data (menggunakan lot IN (...))
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

                          // 2) hitung total used per size dari semua transaksi (kriteria sama, lot string sama seperti kamu pakai)
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

                          // 3) tampilkan remaining per komponen per size (menggunakan total_order_per_size dan used_per_size)
                          if (!empty($grouped)) {
                            echo "<ul class='list-unstyled m-0'>";
                            foreach ($grouped as $id => $items) {
                              $nama = htmlspecialchars($mapKomponen[$id] ?? "Unknown");
                              $parts = [];
                              foreach ($items as $it) {
                                $sz = $it['size'];
                                $total_for_size = $total_order_per_size[$sz] ?? 0;
                                $used_for_size = $used_per_size[$sz] ?? 0;
                                $remaining = $total_for_size - $used_for_size;
                                $parts[] = htmlspecialchars($sz) . ": " . intval($remaining);
                              }
                              echo "<li><strong>{$nama} :</strong> " . implode(", ", $parts) . "</li>";
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

</body>

</html>