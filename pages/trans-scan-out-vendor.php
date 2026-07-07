<?php
// menghubungkan php dengan koneksi database
require_once __DIR__ . '/../config/function.php';
require_once __DIR__ . '/../config/auth.php';
checkAuth('scan_out_vendor'); // cek apakah sudah login dan punya akses ke menu ini

$nik = $_SESSION['nik_user'];
$username = $_SESSION['username'];

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
    from {
      width: 100%;
    }

    to {
      width: 0%;
    }
  }

  .fade-in {
    animation: fadeIn .35s ease;
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

  .flow-header {
    text-align: center;
  }

  .flow-title {
    font-size: 28px;
    font-weight: 700;
    color: #0d6efd;
  }

  .flow-sub {
    color: #6c757d;
  }

  .scan-card {
    border-radius: 24px;
  }

  .scan-icon {
    font-size: 42px;
    color: #0d6efd;
  }

  .scan-input {
    height: 58px;
    border-radius: 14px;
    font-size: 22px;
    font-weight: 600;
  }

  .success-card,
  .partial-card {
    border-radius: 18px;
    overflow: hidden;
  }

  .success-icon,
  .merge-success-icon,
  .partial-icon {
    line-height: 1;
  }

  .success-icon,
  .merge-success-icon {
    font-size: 42px;
    color: #198754;
  }

  .partial-icon {
    font-size: 42px;
    color: #ffc107;
  }

  .partial-icon i {
    animation: pulseWaiting 1.5s infinite;
  }

  @keyframes pulseWaiting {

    0% {
      opacity: .5;
      transform: scale(.95);
    }

    50% {
      opacity: 1;
      transform: scale(1);
    }

    100% {
      opacity: .5;
      transform: scale(.95);
    }
  }

  .detail-box {
    background: #f8f9fa;
    border-radius: 14px;
    padding: 18px;
    text-align: center;
    height: 100%;
  }

  .detail-label {
    font-size: 13px;
    color: #6c757d;
    margin-bottom: 8px;
  }

  .detail-value {
    font-size: 18px;
    font-weight: 700;
    color: #212529;
  }

  .qty-highlight {
    color: #198754;
    font-size: 24px;
  }

  .size-wrapper {
    background: #f8f9fa;
    border-radius: 16px;
    padding: 20px;
  }

  .size-title {
    font-size: 16px;
    font-weight: 700;
    color: #212529;
  }

  .scan-info {
    border-top: 1px solid #eee;
    padding-top: 15px;

    display: flex;
    justify-content: space-between;
    flex-wrap: wrap;

    color: #6c757d;
    font-size: 14px;
  }

  .component-status-wrapper {
    display: flex;
    flex-direction: column;
    gap: 12px;
  }

  .component-item {
    padding: 14px 18px;
    border-radius: 12px;
    font-weight: 600;
    font-size: 14px;
  }

  .component-item.success {
    background: #e9f7ef;
    color: #198754;
  }

  .component-item.waiting {
    background: #fff8e1;
    color: #ff9800;
  }

  .next-action-box {
    background: #e9f7ef;
    border: 1px solid #c7ebd3;
    border-radius: 14px;
    padding: 16px;
    text-align: center;
    color: #198754;
    font-weight: 600;
    font-size: 14px;
  }

  .next-action-box i {
    font-size: 18px;
  }

  .partial-progress {
    font-size: 18px;
    font-weight: 800;
    color: #212529;
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
  $page = 'scan_out_vendor';
  include_once __DIR__ . '/../includes/header.php';
  ?>
  <!-- End Header -->

  <main id="main" class="main">

    <div class="pagetitle text-black" style="background-color: #f0e6d2; padding: 10px 20px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
      <h1 style="font-size: 1.8rem; font-weight: 700; font-family: 'Roboto', sans-serif;">
        Scan-Out Vendor (Send to WH Subcont)
      </h1>
    </div>

    <section class="section">
      <div class="row">
        <div class="col-lg-12">

          <!-- ========== SCAN QR CODE CARD ========== -->
          <div class="card border-0 shadow-sm mb-4">
            <div class="card-body text-center py-5">
              <div class="mb-3 text-primary">
                <i class="bi bi-upc-scan" style="font-size: 3rem;"></i>
              </div>
              <h5 class="fw-semibold mb-4 text-primary"></h5>
              <form action="./../config/function.php" method="post" id="scanForm">
                <input type="hidden" name="action" value="scan_vendor_to_whsubcont">
                <div class="col-md-8 mx-auto">
                  <input type="text" name="barcode" id="barcode"
                    class="form-control form-control-lg text-center"
                    placeholder="Scan barcode here..." autofocus>
                </div>
              </form>
            </div>
          </div>

          <?php

          $status  = $_GET['status'] ?? '';
          $barcode = $_GET['barcode'] ?? '';

          if ($status && $barcode):

            // Ambil data barcode
            $stmt = $conn->prepare("
        SELECT *
        FROM tbl_transaksi
        WHERE barcode = ?
        LIMIT 1
    ");

            $stmt->bind_param("s", $barcode);
            $stmt->execute();

            $first = $stmt->get_result()->fetch_assoc();

            if ($first):

              // Ambil semua group pada output yang sama
              $stmtGroup = $conn->prepare("
            SELECT
                id_group,
                MIN(nm_komponen_in) AS nm_group,
                lot,
                size,
                MAX(qty_vendor_to_whsubcont) AS qty,
                MAX(last_gate='VENDOR_TO_WH_SUBCONT') AS ready
            FROM tbl_transaksi
            WHERE
                job_order=?
                AND lot=?
                AND size=?
                AND nm_komponen_out=?
            GROUP BY id_group, lot, size
            ORDER BY id_group
        ");

              $stmtGroup->bind_param(
                "ssss",
                $first['job_order'],
                $first['lot'],
                $first['size'],
                $first['nm_komponen_out']
              );

              $stmtGroup->execute();

              $groups = $stmtGroup->get_result();

              $rows = [];
              $totalQty = 0;
              $readyGroup = 0;

              while ($g = $groups->fetch_assoc()) {

                $rows[] = $g;

                $totalQty += $g['qty'];

                if ($g['ready']) {
                  $readyGroup++;
                }
              }

              $totalGroup = count($rows);

          ?>

              <div class="card border-0 shadow-lg success-card mb-4 fade-in">

                <div class="card-body p-4">

                  <!-- HEADER -->

                  <div class="text-center mb-4">

                    <?php if ($status == "complete"): ?>

                      <div class="merge-success-icon mb-3">
                        <i class="bi bi-check-circle-fill"></i>
                      </div>

                      <h2 class="fw-bold text-success">
                        TRANSAKSI BERHASIL
                      </h2>

                      <div class="text-muted">
                        Semua group berhasil diproses.
                      </div>

                    <?php else: ?>

                      <div class="partial-icon mb-3">
                        <i class="bi bi-hourglass-split"></i>
                      </div>

                      <h2 class="fw-bold text-warning">
                        TRANSAKSI PARSIAL
                      </h2>

                      <div class="text-muted">
                        Masih menunggu group lainnya.
                      </div>

                    <?php endif; ?>

                  </div>

                  <!-- SUMMARY -->

                  <div class="row mb-4">

                    <div class="col-md-6">

                      <div class="detail-box">

                        <div class="detail-label">
                          Output Process
                        </div>

                        <div class="detail-value">
                          <?= htmlspecialchars($first['nm_komponen_out']) ?>
                        </div>

                      </div>

                    </div>

                    <div class="col-md-6">

                      <div class="detail-box">

                        <div class="detail-label">
                          Progress
                        </div>

                        <div class="detail-value">

                          <?= $readyGroup ?>

                          /

                          <?= $totalGroup ?>

                          Group Ready

                        </div>

                      </div>

                    </div>

                  </div>

                  <!-- DETAIL -->

                  <div class="table-responsive">

                    <table class="table table-bordered align-middle">

                      <thead class="table-light">

                        <tr>

                          <th class="text-center">LOT</th>

                          <th class="text-center">SIZE</th>

                          <th class="text-center">GROUP</th>

                          <th class="text-center">QTY</th>

                          <th class="text-center">STATUS</th>

                        </tr>

                      </thead>

                      <tbody>

                        <?php foreach ($rows as $r): ?>

                          <tr>

                            <td class="text-center">

                              <?= $r['lot'] ?>

                            </td>

                            <td class="text-center">

                              <?= $r['size'] ?>

                            </td>

                            <td class="text-center">

                              <?= htmlspecialchars($r['nm_group']) ?>

                            </td>

                            <td class="text-center fw-bold">

                              <?= number_format($r['qty']) ?>

                            </td>

                            <td class="text-center">

                              <?php if ($r['ready']): ?>

                                <span class="badge bg-success">

                                  READY

                                </span>

                              <?php else: ?>

                                <span class="badge bg-warning text-dark">

                                  WAITING

                                </span>

                              <?php endif; ?>

                            </td>

                          </tr>

                        <?php endforeach; ?>

                      </tbody>

                    </table>

                  </div>

                  <div class="next-action-box mt-4">

                    <?php if ($status == "complete"): ?>

                      <i class="bi bi-check2-all me-2"></i>

                      Seluruh group berhasil diproses. Silakan lanjut ke proses berikutnya.

                    <?php else: ?>

                      <i class="bi bi-arrow-repeat me-2"></i>

                      Silakan scan group yang masih berstatus <strong>WAITING</strong>.

                    <?php endif; ?>

                  </div>

                </div>

              </div>

          <?php

            endif;

          endif;

          ?>

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

    let submitted = false;

    barcodeInput.addEventListener("keydown", function(e) {
      if (e.key === "Enter") {
        e.preventDefault();

        if (submitted) return;
        if (!this.value.trim()) return;

        submitted = true;

        // kunci input biar gak double scan
        this.setAttribute("readonly", true);

        scanForm.submit();
      }
    });
  </script>

</body>

</html>