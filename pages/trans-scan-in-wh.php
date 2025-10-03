<?php
// menghubungkan php dengan koneksi database
require_once __DIR__ . '/../config/function.php';
require_once __DIR__ . '/../config/auth.php';
checkAuth('scan_in_warehouse'); // cek apakah sudah login dan punya akses ke menu ini

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

</head>

<body>

  <!-- Header -->
  <?php
  $page = 'scan_in_warehouse';
  include_once __DIR__ . '/../includes/header.php';
  ?>
  <!-- End Header -->

  <main id="main" class="main">

    <div class="pagetitle text-black" style="background-color: #f0e6d2; padding: 10px 20px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
      <h1 style="font-size: 1.8rem; font-weight: 700; font-family: 'Roboto', sans-serif;">
        Scan-In Warehouse
      </h1>
    </div>

    <section class="section">
      <div class="row">
        <div class="col-lg-12">
          <div class="card">
            <div class="card-body" style="margin-top: 10px;">
              <form action="./../config/function.php" method="post" id="scanForm">
                <input type="hidden" name="scan-barcode"> <!-- penting -->
                <div class="row mb-3">
                  <label for="barcode" class="col-sm-2 col-form-label">Scan QR Code</label>
                  <div class="col-sm-10">
                    <input type="text" name="barcode" id="barcode"
                      class="form-control" placeholder="Scan QR Code here..." autofocus>
                  </div>
                </div>
              </form>

              <!-- Detail hasil scan -->
              <?php if (isset($_GET['success'])): ?>
                <?php
                $barcode_success = $_GET['success'];
                $stmt = $conn->prepare("SELECT * FROM tbl_transaksi WHERE barcode=?");
                $stmt->bind_param("s", $barcode_success);
                $stmt->execute();
                $result = $stmt->get_result();
                $row = $result->fetch_assoc();
                ?>
                <?php if ($row): ?>
                  <div class="alert alert-info mt-3">
                    <h6>Detail Transaksi Scan In Warehouse:</h6>
                    <ul class="mb-0">
                      <li><strong>Job Order:</strong> <?= htmlspecialchars($row['job_order']); ?></li>
                      <li><strong>PO Code:</strong> <?= htmlspecialchars($row['po_code']); ?></li>
                      <li><strong>PO Item:</strong> <?= htmlspecialchars($row['po_item']); ?></li>
                      <li><strong>Model:</strong> <?= htmlspecialchars($row['model']); ?></li>
                      <li><strong>Style:</strong> <?= htmlspecialchars($row['style']); ?></li>
                      <li><strong>NCVS:</strong> <?= htmlspecialchars($row['ncvs']); ?></li>
                      <li><strong>Lot:</strong>
                        <?php
                        $lots = json_decode($row['lot'], true);
                        echo is_array($lots) ? implode(", ", $lots) : htmlspecialchars($row['lot']);
                        ?>
                      </li>
                      <li><strong>Komponen Sebelum Proses, Size & Qty:</strong></li>
                      <ul>
                        <?php
                        $qty_data = json_decode($row['komponen_qty'], true);
                        if (is_array($qty_data)) {
                          // Grouping by komponen
                          $grouped = [];
                          foreach ($qty_data as $item) {
                            $id_komponen = $item['komponen'];
                            $size_val    = $item['size'];
                            $qty_val     = $item['qty'];

                            if (!isset($grouped[$id_komponen])) {
                              $grouped[$id_komponen] = [];
                            }
                            $grouped[$id_komponen][] = [
                              'size' => $size_val,
                              'qty'  => $qty_val
                            ];
                          }

                          foreach ($grouped as $id_komponen => $details) {
                            // ambil nama komponen dari tbl_komponen
                            $stmt_kmp = $conn->prepare("SELECT nama_komponen FROM tbl_komponen WHERE id_komponen=?");
                            $stmt_kmp->bind_param("i", $id_komponen);
                            $stmt_kmp->execute();
                            $res_kmp = $stmt_kmp->get_result();
                            $komponen_row = $res_kmp->fetch_assoc();
                            $nama_komponen = $komponen_row['nama_komponen'] ?? "Komponen #$id_komponen";

                            echo "<li class='mb-2'>";
                            echo "<label><strong>" . htmlspecialchars($nama_komponen) . "</strong></label><br>";

                            // Tampilkan size dan qty: "006 (6), 007 (6)"
                            $parts = [];
                            foreach ($details as $d) {
                              $parts[] = htmlspecialchars($d['size']) . " (" . htmlspecialchars($d['qty']) . ")";
                            }
                            echo implode(", ", $parts);

                            echo "</li>";
                          }
                        }
                        ?>
                      </ul>

                      <li><strong>Type Scan:</strong> <?= htmlspecialchars($row['type_scan']); ?></li>
                      <li><strong>Scan At:</strong> <?= htmlspecialchars($row['scan_at']); ?></li>
                      <li><strong>Scan With:</strong> <?= htmlspecialchars($row['scan_with']); ?></li>
                      <li><strong>Hour:</strong> <?= htmlspecialchars($row['hour']); ?></li>
                    </ul>
                  </div>
                <?php endif; ?>
              <?php endif; ?>
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
    const barcodeInput = document.getElementById("barcode");
    const scanForm = document.getElementById("scanForm");

    // kalau sudah ada input dari scanner, otomatis submit
    barcodeInput.addEventListener("input", function() {
      if (barcodeInput.value.trim() !== "") {
        setTimeout(() => {
          scanForm.submit();
        }, 300); // delay dikit biar input scanner kelar
      }
    });
  </script>

</body>

</html>