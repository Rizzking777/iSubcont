<?php
// menghubungkan php dengan koneksi database
require_once __DIR__ . '/../config/function.php';
require_once __DIR__ . '/../config/auth.php';
checkAuth('scan_out_prod'); // cek apakah sudah login dan punya akses ke menu ini

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
  $page = 'scan_out_prod';
  include_once __DIR__ . '/../includes/header.php';
  ?>
  <!-- End Header -->

  <main id="main" class="main">

    <div class="pagetitle text-black" style="background-color: #f0e6d2; padding: 10px 20px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
      <h1 style="font-size: 1.8rem; font-weight: 700; font-family: 'Roboto', sans-serif;">
        Scan-Out to Production
      </h1>
    </div>

    <section class="section">
      <div class="row">
        <div class="col-lg-12">

          <!-- ========== SCAN QR CODE CARD (IDENTIK) ========== -->
          <div class="card border-0 shadow-sm mb-4">
            <div class="card-body text-center py-5">
              <div class="mb-3 text-primary">
                <i class="bi bi-upc-scan" style="font-size: 3rem;"></i>
              </div>
              <h5 class="fw-semibold mb-4 text-primary"></h5>

              <form action="./../config/function.php" method="post" id="scanForm">
                <input type="hidden" name="scan-out-production">
                <div class="col-md-8 mx-auto">
                  <input type="text" name="barcode" id="barcode"
                    class="form-control form-control-lg text-center"
                    placeholder="Scan barcode here..." autofocus>
                </div>
              </form>
            </div>
          </div>

          <!-- ========== HASIL SCAN CARD (MATCHED STYLE) ========== -->
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
              <div class="card border-0 shadow-sm fade-in">
                <div class="card-body p-4">

                  <!-- HEADER -->
                  <div class="d-flex align-items-center mb-4">
                    <div class="bg-primary bg-opacity-10 p-3 rounded-circle me-3">
                      <i class="bi bi-info-circle text-primary" style="font-size: 1.5rem;"></i>
                    </div>
                    <h5 class="mb-0 text-primary fw-semibold">Detail Transaksi Scan Out to Production</h5>
                  </div>

                  <!-- GRID INFO -->
                  <div class="row g-4 mb-4">
                    <div class="col-md-6">
                      <div class="info-box p-3 rounded-3 bg-light border-start border-4 border-primary shadow-sm-sm">
                        <p class="mb-1"><strong>Job Order:</strong> <?= htmlspecialchars($row['job_order']); ?></p>
                        <p class="mb-1"><strong>PO Code:</strong> <?= htmlspecialchars($row['po_code']); ?></p>
                        <p class="mb-1"><strong>PO Item:</strong> <?= htmlspecialchars($row['po_item']); ?></p>
                        <p class="mb-0"><strong>Model:</strong> <?= htmlspecialchars($row['model']); ?></p>
                      </div>
                    </div>
                    <div class="col-md-6">
                      <div class="info-box p-3 rounded-3 bg-light border-start border-4 border-success shadow-sm-sm">
                        <p class="mb-1"><strong>Style:</strong> <?= htmlspecialchars($row['style']); ?></p>
                        <p class="mb-1"><strong>NCVS:</strong> <?= htmlspecialchars($row['ncvs']); ?></p>
                        <p class="mb-1"><strong>Lot:</strong>
                          <?php
                          $lots = json_decode($row['lot'], true);
                          echo is_array($lots) ? implode(", ", $lots) : htmlspecialchars($row['lot']);
                          ?>
                        </p>
                        <p class="mb-0"><strong>Type Scan:</strong> <?= htmlspecialchars($row['type_scan']); ?></p>
                      </div>
                    </div>
                  </div>

                  <!-- KOMPONEN -->
                  <div class="mb-4">
                    <h6 class="fw-semibold text-dark mb-3">
                      <i class="bi bi-gear-wide-connected me-2 text-secondary"></i>
                      Komponen Sesudah Check QC, Size & Qty:
                    </h6>
                    <div class="p-3 rounded-3 bg-light border shadow-sm-sm">
                      <div class="row g-3">
                        <?php
                        // ambil data log scan out
                        $stmt_log_qc = $conn->prepare("
                      SELECT new_data FROM tlog_transaksi
                      WHERE id_trans = ? AND action_type = 'SCAN_IN_INCOMING'
                      ORDER BY created_at DESC LIMIT 1
                    ");
                        $stmt_log_qc->bind_param("i", $row['id_trans']);
                        $stmt_log_qc->execute();
                        $res_log_qc = $stmt_log_qc->get_result();
                        $log_qc_row = $res_log_qc->fetch_assoc();
                        $stmt_log_qc->close();

                        $qty_data = [];
                        if ($log_qc_row && !empty($log_qc_row['new_data'])) {
                          $log_data = json_decode($log_qc_row['new_data'], true);
                          if (!empty($log_data['komponen_qty'])) {
                            $komponen_qty_raw = $log_data['komponen_qty'];
                            $qty_data = is_string($komponen_qty_raw)
                              ? json_decode($komponen_qty_raw, true)
                              : $komponen_qty_raw;
                          }
                        }

                        if (is_array($qty_data)) {
                          foreach ($qty_data as $item) {
                            $id_komponen = (int)($item['komponen'] ?? 0);
                            $size_val    = htmlspecialchars($item['size'] ?? "-");
                            $qty_val     = (int)($item['qty'] ?? 0);

                            $stmt_kmp = $conn->prepare("SELECT nama_komponen FROM tbl_komponen WHERE id_komponen=?");
                            $stmt_kmp->bind_param("i", $id_komponen);
                            $stmt_kmp->execute();
                            $res_kmp = $stmt_kmp->get_result();
                            $komponen_row = $res_kmp->fetch_assoc();
                            $nama_komponen = $komponen_row['nama_komponen'] ?? "Komponen #$id_komponen";
                            $stmt_kmp->close();
                        ?>
                            <div class="col-md-6">
                              <input type="text" class="form-control"
                                value="<?= htmlspecialchars($nama_komponen) ?>: <?= $size_val ?> (<?= $qty_val ?>)"
                                readonly>
                            </div>
                        <?php
                          }
                        }
                        ?>
                      </div>
                    </div>
                  </div>

                  <!-- INFO TAMBAHAN -->
                  <div class="border-top pt-3 text-muted small mt-3 d-flex flex-wrap gap-3">
                    <div>
                      <strong>Scan At:</strong> <?= htmlspecialchars($row['scan_at']); ?> |
                      <strong>Scan With:</strong> <?= htmlspecialchars($row['scan_with']); ?> |
                      <strong>Hour:</strong> <?= htmlspecialchars($row['hour']); ?>
                    </div>
                  </div>

                </div>
              </div>

              <!-- STYLE -->
              <style>
                .fade-in {
                  animation: fadeIn 0.6s ease-in-out;
                }

                @keyframes fadeIn {
                  from {
                    opacity: 0;
                    transform: translateY(10px);
                  }

                  to {
                    opacity: 1;
                    transform: translateY(0);
                  }
                }

                .info-box {
                  transition: all 0.3s ease;
                }

                .info-box:hover {
                  background-color: #f8f9fa;
                  box-shadow: 0 3px 8px rgba(0, 0, 0, 0.05);
                  transform: translateY(-2px);
                }
              </style>
            <?php endif; ?>
          <?php endif; ?>

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

    let typingTimer;
    let submitted = false;

    barcodeInput.addEventListener("input", function() {
      clearTimeout(typingTimer);

      // tunggu scanner benar-benar selesai ketik
      typingTimer = setTimeout(() => {

        if (submitted) return; // block double submit
        if (!barcodeInput.value.trim()) return;

        submitted = true;
        scanForm.submit();

      }, 350);
    });
  </script>

</body>

</html>