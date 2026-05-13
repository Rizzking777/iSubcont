<?php
// menghubungkan php dengan koneksi database
require_once __DIR__ . '/../config/function.php';
require_once __DIR__ . '/../config/auth.php';
checkAuth('sm_out_to_prod'); // cek apakah sudah login dan punya akses ke menu ini

$nik = $_SESSION['nik_user'];
$username = $_SESSION['username'];
$pickup_session = $_SESSION['pickup_session'] ?? null;
$_SESSION['pickup_last_activity'] = time();

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

  .fade-in {
    animation: fadeIn 0.4s ease;
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

  .success-card {
    border-radius: 18px;
    overflow: hidden;
  }

  .success-icon {
    font-size: 50px;
    color: #198754;
    line-height: 1;
  }

  .flow-box {
    background: #f8f9fa;
    border-radius: 16px;
    padding: 20px;
    text-align: center;
  }

  .flow-prev {
    color: #6c757d;
    font-size: 14px;
  }

  .flow-arrow {
    font-size: 22px;
    color: #0d6efd;
    margin: 8px 0;
  }

  .flow-current {
    font-size: 22px;
    font-weight: 700;
    color: #0d6efd;
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
    font-size: 22px;
    font-weight: 700;
    color: #212529;
  }

  .qty-highlight {
    color: #198754;
    font-size: 30px;
  }

  .size-wrapper {
    background: #f8f9fa;
    border-radius: 16px;
    padding: 20px;
  }

  .size-title {
    font-size: 18px;
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
  $page = 'sm_out_to_prod';
  include_once __DIR__ . '/../includes/header.php';
  ?>
  <!-- End Header -->

  <main id="main" class="main">

    <div class="pagetitle text-black" style="background-color: #f0e6d2; padding: 10px 20px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
      <h1 style="font-size: 1.8rem; font-weight: 700; font-family: 'Roboto', sans-serif;">
        Scan-Out SM Subcont (Send to Production)
      </h1>
    </div>

    <section class="section">
      <div class="row">
        <div class="col-lg-12">

          <!-- TAP ID CARD -->
          <div
            id="pickupCard" class="card border-0 shadow-sm mb-4 fade-in">
            <div class="card-body text-center py-4">
              <!-- ICON -->
              <div id="pickupIcon" class="mb-3">
                <?php if ($pickup_session): ?>
                  <i class="bi bi-person-check-fill text-success" style="font-size: 3rem;"></i>
                <?php else: ?>
                  <i class="bi bi-person-badge text-primary" style="font-size: 3rem;"></i>
                <?php endif; ?>
              </div>

              <!-- INPUT -->
              <div id="pickupInputWrapper" class="<?= $pickup_session ? 'd-none' : '' ?> col-md-6 mx-auto">
                <input
                  type="password"
                  id="pickup_card"
                  class="form-control form-control-lg text-center"
                  placeholder="Tap ID Card..."
                  autofocus>
              </div>

              <!-- SUCCESS INFO -->
              <div id="pickupSuccess" class="<?= $pickup_session ? '' : 'd-none' ?>">

                <h4
                  class="fw-bold text-success mb-1"
                  id="pickupName">
                  <?= htmlspecialchars(
                    $pickup_session['name'] ?? ''
                  ) ?>
                </h4>

                <div
                  id="pickupNik"
                  class="text-muted mb-2">

                  <?= htmlspecialchars(
                    $pickup_session['nik'] ?? ''
                  ) ?>
                </div>

                <div
                  class="badge bg-success-subtle text-success px-3 py-2">
                  NCVS:
                  <span id="pickupNcvs">

                    <?= htmlspecialchars(
                      $pickup_session['ncvs'] ?? ''
                    ) ?>

                  </span>
                </div>

                <div class="mt-3">
                  <button
                    type="button"
                    id="resetPickupSession"
                    class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-repeat me-1"></i>
                    End Session
                  </button>
                </div>

              </div>
            </div>
          </div>

          <!-- ========== SCAN QR CODE CARD ========== -->
          <div
            id="barcodeCard"
            class="card border-0 shadow-sm mb-4 fade-in <?= $pickup_session ? '' : 'd-none' ?>">
            <div class="card-body text-center py-5">
              <div class="mb-3 text-primary">
                <i class="bi bi-upc-scan" style="font-size: 3rem;"></i>
              </div>

              <form
                action="./../config/function.php"
                method="post"
                id="scanForm">
                <input
                  type="hidden"
                  name="action"
                  value="scan_out_smsubcont_to_prod">

                <!-- PICKUP DATA -->
                <input
                  type="hidden"
                  name="pickup_nik"
                  id="pickup_nik"
                  value="<?= htmlspecialchars($pickup_session['nik'] ?? '') ?>">

                <input
                  type="hidden"
                  name="pickup_name"
                  id="pickup_name"
                  value="<?= htmlspecialchars($pickup_session['name'] ?? '') ?>">

                <input
                  type="hidden"
                  name="pickup_ncvs"
                  id="pickup_ncvs"
                  value="<?= htmlspecialchars($pickup_session['ncvs'] ?? '') ?>">

                <!-- BARCODE -->
                <div class="col-md-8 mx-auto">

                  <input
                    type="text"
                    name="barcode"
                    id="barcode"
                    class="form-control form-control-lg text-center"

                    placeholder="<?= $pickup_session
                                    ? 'Scan barcode here...'
                                    : 'Tap ID Card terlebih dahulu...' ?>"

                    <?= $pickup_session ? '' : 'disabled' ?>>

                </div>
              </form>
            </div>
          </div>

          <!-- ========== HASIL SCAN SUCCESS ========== -->
          <?php if (isset($_GET['success'])): ?>

            <?php
            $barcode_success = $_GET['barcode'] ?? '';
            if (empty($barcode_success)) {
              return;
            }

            $stmt = $conn->prepare("
              SELECT * 
              FROM tbl_transaksi 
              WHERE barcode = ?
              ORDER BY 
              CAST(REPLACE(size, 'T', '') AS UNSIGNED),
              size ASC
          ");
            $stmt->bind_param("s", $barcode_success);
            $stmt->execute();
            $result = $stmt->get_result();
            $rows = [];
            $total_qty = 0;
            while ($r = $result->fetch_assoc()) {
              $rows[] = $r;
              $total_qty += (float)$r['qty_smsubcont_to_prod'];
            }
            $first = $rows[0] ?? null;
            ?>

            <?php if ($first): ?>
              <div class="card border-0 shadow-lg success-card mb-4 fade-in">
                <div class="card-body p-4">
                  <!-- SUCCESS HEADER -->
                  <div class="text-center mb-4">
                    <div class="success-icon mb-3">
                      <i class="bi bi-check-circle-fill"></i>
                    </div>
                    <h2 class="fw-bold text-success mb-1">
                      TRANSAKSI BERHASIL
                    </h2>
                    <div class="text-muted">
                      Barcode berhasil diproses.
                    </div>
                  </div>

                  <!-- SUMMARY -->
                  <div class="row g-3 mb-4">
                    <div class="col-md-6">
                      <div class="detail-box">
                        <div class="detail-label">
                          Komponen
                        </div>
                        <div class="detail-value">
                          <?= htmlspecialchars($first['nm_komponen_out']) ?>
                        </div>
                      </div>
                    </div>

                    <div class="col-md-6">
                      <div class="detail-box">
                        <div class="detail-label">
                          Total Qty
                        </div>
                        <div class="detail-value qty-highlight">
                          <?= number_format($total_qty) ?>
                        </div>
                      </div>
                    </div>
                  </div>

                  <!-- SIZE DETAIL -->
                  <div class="size-wrapper mb-4">
                    <div class="size-title mb-3">
                      Detail Size
                    </div>
                    <div class="table-responsive">
                      <table class="table align-middle table-bordered">
                        <thead class="table-light">
                          <tr>
                            <th class="text-center">LOT</th>
                            <th class="text-center">SIZE</th>
                            <th class="text-center">QTY</th>
                          </tr>
                        </thead>
                        <tbody>
                          <?php foreach ($rows as $r): ?>
                            <tr>
                              <td class="text-center fw-semibold">
                                <?= htmlspecialchars($r['lot']) ?>
                              </td>
                              <td class="text-center">
                                <?= htmlspecialchars($r['size']) ?>
                              </td>
                              <td class="text-center text-success fw-bold">
                                <?= number_format($r['qty_smsubcont_to_prod']) ?>
                              </td>
                            </tr>
                          <?php endforeach; ?>
                        </tbody>
                      </table>
                    </div>
                  </div>

                  <!-- INFO -->
                  <div class="scan-info">
                    <div>
                      <i class="bi bi-person-circle me-1"></i>
                      <?= htmlspecialchars($first['transac_by']) ?>
                    </div>
                    <div>
                      <i class="bi bi-clock-history me-1"></i>
                      <?= date('d M Y H:i', strtotime($first['updated_at'])) ?>
                    </div>
                  </div>
                </div>
              </div>
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

  <!-- RFID TAP -->
  <script>
    document
      .getElementById('pickup_card')
      .addEventListener('keypress', function(e) {
        if (e.key !== 'Enter') {
          return;
        }
        e.preventDefault();
        const id_card =
          this.value.trim();

        if (!id_card) {
          return;
        }
        fetch('./../config/get_id_card.php', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: 'id_card=' +
              encodeURIComponent(id_card)

          })

          .then(res => res.json())
          .then(res => {

            // VALIDATION
            if (!res.status) {

              alert(res.message);

              return;
            }

            // SAVE LAST ACTIVITY
            localStorage.setItem(
              'pickup_last_activity',
              Date.now()
            );

            // SHOW BARCODE CARD
            document
              .getElementById('barcodeCard')
              .classList
              .remove('d-none');

            // HIDE RFID INPUT
            document
              .getElementById('pickupInputWrapper')
              .classList
              .add('d-none');

            // SHOW SUCCESS INFO
            document
              .getElementById('pickupSuccess')
              .classList
              .remove('d-none');

            // CHANGE ICON
            document
              .getElementById('pickupIcon')
              .innerHTML = `
            <i
              class="bi bi-person-check-fill"
              style="font-size: 3rem; color: #198754;">
            </i>
        `;

            // SET USER INFO
            document
              .getElementById('pickupName')
              .innerText = res.name;

            document
              .getElementById('pickupNik')
              .innerText = res.nik;

            document
              .getElementById('pickupNcvs')
              .innerText = res.ncvs;

            // SET HIDDEN INPUT
            document
              .getElementById('pickup_nik')
              .value = res.nik;

            document
              .getElementById('pickup_name')
              .value = res.name;

            document
              .getElementById('pickup_ncvs')
              .value = res.ncvs;

            // ENABLE BARCODE
            const barcode =
              document.getElementById('barcode');

            barcode.disabled = false;

            barcode.placeholder =
              'Scan barcode here...';

            // AUTO FOCUS
            setTimeout(() => {

              barcode.focus();

            }, 150);

            // LOCK RFID INPUT
            document
              .getElementById('pickup_card')
              .disabled = true;

          })

          .catch(err => {

            console.error(err);

            alert('Terjadi kesalahan.');
          });

      });
  </script>

  <!-- RESET SESSION -->
  <script>
    document
      .addEventListener('click', function(e) {
        if (
          e.target.closest('#resetPickupSession')
        ){
          // CLEAR STORAGE
          localStorage.removeItem(
            'pickup_last_activity'
          );

          // RESET RFID INPUT
          const pickupInput =
            document.getElementById('pickup_card');

          pickupInput.disabled = false;

          pickupInput.value = '';

          // SHOW INPUT
          document
            .getElementById('pickupInputWrapper')
            .classList
            .remove('d-none');

          // HIDE SUCCESS
          document
            .getElementById('pickupSuccess')
            .classList
            .add('d-none');

          // RESET ICON
          document
            .getElementById('pickupIcon')
            .innerHTML = `
            <i
              class="bi bi-person-badge text-primary"
              style="font-size: 3rem;">
            </i>
        `;

          // HIDE BARCODE CARD
          document
            .getElementById('barcodeCard')
            .classList
            .add('d-none');

          // RESET BARCODE
          const barcode =
            document.getElementById('barcode');
          barcode.value = '';
          barcode.disabled = true;
          barcode.placeholder =
            'Tap ID Card terlebih dahulu...';

          // RESET HIDDEN INPUT
          document
            .getElementById('pickup_nik')
            .value = '';

          document
            .getElementById('pickup_name')
            .value = '';

          document
            .getElementById('pickup_ncvs')
            .value = '';

          // RESET SERVER SESSION
          fetch('./../config/reset_pickup_session.php');

          // FOCUS RFID
          pickupInput.focus();
        }

      });
  </script>

  <!-- UPDATE LAST ACTIVITY -->
  <script>
    document
      .getElementById('scanForm')

      .addEventListener('submit', function() {

        localStorage.setItem(
          'pickup_last_activity',
          Date.now()
        );

      });

    document
      .getElementById('barcode')

      .addEventListener('input', function() {

        localStorage.setItem(
          'pickup_last_activity',
          Date.now()
        );

      });
  </script>

  <!-- AUTO FOCUS BARCODE -->
  <script>
    setInterval(() => {
      const barcode =
        document.getElementById('barcode');

      // SESSION ACTIVE
      if (
        barcode &&
        !barcode.disabled
      ) {

        // JIKA TIDAK SEDANG FOCUS
        if (
          document.activeElement !== barcode
        ) {

          barcode.focus();
        }
      }

    }, 500);
  </script>

  <!-- AUTO TIMEOUT SESSION -->
  <script>
    const SESSION_TIMEOUT =
      5 * 60 * 1000;

    setInterval(() => {

      const lastActivity =
        localStorage.getItem(
          'pickup_last_activity'
        );

      if (!lastActivity) {
        return;
      }

      const now =
        Date.now();

      const diff =
        now - parseInt(lastActivity, 10);

      // SESSION TIMEOUT
      if (diff > SESSION_TIMEOUT) {

        // CLEAR STORAGE
        localStorage.removeItem(
          'pickup_last_activity'
        );

        // RESET SERVER SESSION
        fetch('./../config/reset_pickup_session.php')

          .finally(() => {

            location.reload();
          });
      }

    }, 10000);
  </script>

</body>

</html>