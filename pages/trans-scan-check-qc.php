<?php
// menghubungkan php dengan koneksi database
require_once __DIR__ . '/../config/function.php';
require_once __DIR__ . '/../config/auth.php';
checkAuth('scan_check_qc'); // cek apakah sudah login dan punya akses ke menu ini

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

  .btn-square {
    width: 40px;
    height: 40px;
    padding: 0;
    display: flex;
    align-items: center;
    justify-content: center;
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
  $page = 'scan_check_qc';
  include_once __DIR__ . '/../includes/header.php';
  ?>
  <!-- End Header -->

  <main id="main" class="main">

    <div class="pagetitle text-black" style="background-color: #f0e6d2; padding: 10px 20px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
      <h1 style="font-size: 1.8rem; font-weight: 700; font-family: 'Roboto', sans-serif;">
        Scan Checking Quality Control
      </h1>
    </div>

    <section class="section">
      <div class="row">
        <div class="col-lg-12">
          <div class="card">
            <div class="card-body" style="margin-top: 10px;">

              <!-- Form Scan -->
              <form method="post" id="scanForm">
                <input type="hidden" name="scan-now" value="1"> <!-- biar $_POST['scan-now'] kebaca -->
                <div class="row mb-3">
                  <label for="barcode" class="col-sm-2 col-form-label">Scan QR Code</label>
                  <div class="col-sm-10">
                    <input type="text" name="barcode" id="barcode"
                      class="form-control" placeholder="Scan QR Code here..." autofocus>
                  </div>
                </div>
              </form>

              <?php
              $row = null;
              if (isset($_POST['scan-now']) && !empty($_POST['barcode'])) {
                $barcode = trim($_POST['barcode']);
                $stmt = $conn->prepare("SELECT * FROM tbl_transaksi WHERE barcode = ?");
                $stmt->bind_param("s", $barcode);
                $stmt->execute();
                $res = $stmt->get_result();
                $row = $res->fetch_assoc();
                $stmt->close();
              }
              ?>

              <!-- Detail hasil scan -->
              <?php
              // --- mulai block: Komponen Sebelum/Sesudah Proses ---
              // pastikan $row sudah ada
              $id_trans = (int)($row['id_trans'] ?? 0);

              // 1) Ambil komponen sebelum proses dari tlog_transaksi (old_data dari action_type SCAN_OUT_TO_VENDOR)
              $komponen_input = [];
              $stmt_log = $conn->prepare("
                        SELECT old_data
                        FROM tlog_transaksi
                        WHERE id_trans = ?
                          AND action_type = 'SCAN_OUT_TO_VENDOR'
                          AND old_data IS NOT NULL
                        ORDER BY created_at DESC
                        LIMIT 1
                      ");
              $stmt_log->bind_param("i", $id_trans);
              $stmt_log->execute();
              $res_log = $stmt_log->get_result();
              if ($res_log && ($rlog = $res_log->fetch_assoc())) {
                $old_data_raw = $rlog['old_data'];
                $old_data = json_decode($old_data_raw, true);
                if ($old_data && isset($old_data['komponen_qty'])) {
                  $kq = $old_data['komponen_qty'];
                  // old_data['komponen_qty'] kadang disimpan sebagai string JSON -> decode lagi
                  if (is_string($kq)) {
                    $komponen_input = json_decode($kq, true) ?: [];
                  } elseif (is_array($kq)) {
                    $komponen_input = $kq;
                  }
                }
              }
              $stmt_log->close();

              // 2) Siapkan outputs berdasarkan komponen di transaksi sekarang ($row['komponen_qty'])
              $outputs = []; // inisialisasi supaya tidak undefined
              $curr_kq_raw = $row['komponen_qty'] ?? '[]';

              // decode current komponen_qty robust
              if (is_string($curr_kq_raw)) {
                $curr_kq = json_decode($curr_kq_raw, true);
              } elseif (is_array($curr_kq_raw)) {
                $curr_kq = $curr_kq_raw;
              } else {
                $curr_kq = [];
              }
              if (!is_array($curr_kq)) $curr_kq = [];

              foreach ($curr_kq as $it) {
                $id_out = (int)($it['komponen'] ?? 0);
                $qty_out = (int)($it['qty'] ?? 0);

                if ($id_out <= 0) continue;

                // ambil nama komponen output (satu per id)
                $stmt_o = $conn->prepare("SELECT nama_komponen FROM tbl_komponen WHERE id_komponen = ?");
                $stmt_o->bind_param("i", $id_out);
                $stmt_o->execute();
                $res_o = $stmt_o->get_result();
                $row_o = $res_o->fetch_assoc();
                $nama_out = $row_o['nama_komponen'] ?? "Komponen #$id_out";
                $stmt_o->close();

                $outputs[$id_out] = [
                  'nama' => $nama_out,
                  'qty'  => $qty_out
                ];
              }
              ?>

              <?php if ($row): ?>

                <div class="alert alert-info mt-3">
                  <h6>Detail Transaksi Checking Quality:</h6>
                  <form action="./../config/function.php" method="post" id="qcForm">
                    <input type="hidden" name="barcode" value="<?= htmlspecialchars($row['barcode']); ?>">

                    <ul class="mb-3">
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

                      <li><strong>Komponen, Size & Qty:</strong></li>
                      <ul>
                        <li>
                          <!-- Group Input -->
                          <div class="mb-2">
                            <label><strong>Komponen Sebelum Proses:</strong></label>
                            <div class="row">
                              <?php
                              $id_trans = $row['id_trans'];

                              // === Ambil log terakhir untuk SCAN_OUT_TO_VENDOR ===
                              $stmt_log = $conn->prepare("
                                    SELECT old_data 
                                    FROM tlog_transaksi 
                                    WHERE id_trans = ? 
                                      AND action_type = 'SCAN_OUT_TO_VENDOR'
                                      AND old_data IS NOT NULL
                                    ORDER BY created_at DESC 
                                    LIMIT 1
                                ");
                              $stmt_log->bind_param("i", $id_trans);
                              $stmt_log->execute();
                              $res_log = $stmt_log->get_result();
                              $komponen_input = [];

                              if ($row_log = $res_log->fetch_assoc()) {
                                $old_data = json_decode($row_log['old_data'], true);
                                if (!empty($old_data['komponen_qty'])) {
                                  $komponen_input = json_decode($old_data['komponen_qty'], true);
                                }
                              }

                              // tampilkan komponen input (readonly) + size
                              if (!empty($komponen_input)) {
                                foreach ($komponen_input as $item) {
                                  $id_input = (int)$item['komponen'];
                                  $qty_val  = $item['qty'];
                                  $size_val = $item['size'] ?? "-";

                                  // ambil nama komponen input
                                  $stmt_in = $conn->prepare("SELECT nama_komponen FROM tbl_komponen WHERE id_komponen=?");
                                  $stmt_in->bind_param("i", $id_input);
                                  $stmt_in->execute();
                                  $res_in = $stmt_in->get_result();
                                  $in_row = $res_in->fetch_assoc();
                                  $nama_input = $in_row['nama_komponen'] ?? "Komponen #$id_input";
                              ?>
                                  <div class="col-md-6 mb-1">
                                    <input type="text" class="form-control"
                                      value="<?= htmlspecialchars($nama_input) ?>: <?= htmlspecialchars($size_val) ?> (<?= $qty_val ?>)" readonly>
                                  </div>
                              <?php
                                }
                              }
                              ?>
                            </div>
                          </div>

                          <?php
                          $options_defect = '';
                          $res_def = $conn->query("SELECT id_defect, defect FROM tbl_defect ORDER BY defect");
                          while ($d = $res_def->fetch_assoc()) {
                            $options_defect .= '<option value="' . $d['id_defect'] . '">' . htmlspecialchars($d['defect']) . '</option>';
                          }
                          ?>

                          <!-- Group Output -->
                          <div class="mb-3">
                            <label><strong>Komponen Sesudah Proses:</strong></label>
                            <?php
                            // ambil dari transaksi sekarang
                            $qty_data = json_decode($row['komponen_qty'], true);

                            if (is_array($qty_data) && !empty($qty_data)) {
                              foreach ($qty_data as $idx => $item) {
                                $id_out   = (int)($item['komponen'] ?? 0);
                                $qty_val  = (int)($item['qty'] ?? 0);
                                $size_val = $item['size'] ?? "-";

                                // ambil nama komponen
                                $stmt_out = $conn->prepare("SELECT nama_komponen FROM tbl_komponen WHERE id_komponen=?");
                                $stmt_out->bind_param("i", $id_out);
                                $stmt_out->execute();
                                $res_out = $stmt_out->get_result();
                                $out_row = $res_out->fetch_assoc();
                                $nama_output = $out_row['nama_komponen'] ?? "Komponen #$id_out";
                                $stmt_out->close();
                            ?>

                                <div class="input-group mb-2">
                                  <!-- Label Komponen + Size -->
                                  <span class="input-group-text" style="min-width:180px;">
                                    <?= htmlspecialchars($nama_output) ?>: <?= htmlspecialchars($size_val) ?>
                                  </span>

                                  <!-- Qty -->
                                  <input type="number"
                                    name="qty[<?= $id_out ?>][<?= htmlspecialchars($size_val) ?>]"
                                    class="form-control qty-field"
                                    value="<?= htmlspecialchars($qty_val) ?>"
                                    readonly>

                                  <!-- Tombol Defect -->
                                  <button type="button"
                                    class="btn btn-warning btn-add-defect btn-sm d-flex align-items-center justify-content-center"
                                    data-komponen="<?= $id_out ?>"
                                    data-size="<?= htmlspecialchars($size_val) ?>">
                                    <i class="bi bi-plus-circle me-1"></i> Defect
                                  </button>

                                </div>

                                <!-- Container untuk defect per komponen+size -->
                                <div id="defect-wrap-<?= $id_out ?>-<?= htmlspecialchars($size_val) ?>" class="mb-3">
                                  <!-- nanti diisi via JS -->
                                </div>

                            <?php
                              }
                            } else {
                              echo '<div><em>Tidak ada data komponen output pada transaksi ini.</em></div>';
                            }
                            ?>
                          </div>

                        </li>
                      </ul>
                    </ul>

                    <button type="submit" name="confirm-qc" class="btn btn-success">
                      <i class="bi bi-check-circle"></i> Confirm
                    </button>
                  </form>
                </div>
              <?php endif; ?>
              <?php
              ?>
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
    let typingTimer;

    barcodeInput.addEventListener("input", function() {
      clearTimeout(typingTimer);
      typingTimer = setTimeout(() => {
        if (barcodeInput.value.trim() !== "") {
          scanForm.submit();
        }
      }, 400); // delay biar scanner selesai
    });
  </script>

  <script>
    // Template defect row (pakai komponenId + size)
    function createDefectRow(komponenId, sizeVal) {
      const safeSize = sizeVal.replace(/[^a-zA-Z0-9_-]/g, "_"); // amanin id

      const row = document.createElement("div");
      row.classList.add("row", "g-2", "mb-2", "align-items-center", "defect-row");

      row.innerHTML = `
      <div class="col-md-6">
        <select name="defect[${komponenId}][${safeSize}][]" 
                class="form-select defect-select" 
                data-komponen="${komponenId}" 
                data-size="${safeSize}" required>
        </select>
      </div>
      <div class="col-md-4">
        <input type="number" 
               name="defect_qty[${komponenId}][${safeSize}][]" 
               class="form-control" min="1" step="1" placeholder="Qty" required>
      </div>
      <div class="col-md-2 d-grid">
        <button type="button" class="btn btn-danger btn-remove-defect btn-square">
  <i class="bi bi-trash"></i>
</button>

      </div>
    `;
      return row;
    }

    // Inisialisasi Select2 (Ajax + min input 1 huruf + auto focus)
    function initSelect2($el) {
      $el.select2({
        placeholder: "Cari defect...",
        allowClear: true,
        minimumInputLength: 1,
        ajax: {
          url: "./../config/get_defect.php",
          dataType: "json",
          delay: 250,
          data: function(params) {
            return {
              q: params.term
            };
          },
          processResults: function(data) {
            return {
              results: data.map(function(item) {
                return {
                  id: item.id,
                  text: item.text
                };
              })
            };
          }
        }
      }).on('select2:open', function() {
        let searchField = $('.select2-container--open .select2-search__field');
        if (searchField.length > 0) searchField[0].focus();
      });
    }

    // Event: Tambah defect
    document.querySelectorAll(".btn-add-defect").forEach(btn => {
      btn.addEventListener("click", function() {
        const komponenId = this.dataset.komponen;
        const sizeVal = this.dataset.size;
        const safeSize = sizeVal.replace(/[^a-zA-Z0-9_-]/g, "_");

        const wrap = document.getElementById(`defect-wrap-${komponenId}-${safeSize}`);
        const newRow = createDefectRow(komponenId, sizeVal);
        wrap.appendChild(newRow);

        // aktifkan select2 pada row baru
        initSelect2($(newRow).find(".defect-select"));
      });
    });

    // Event delegation: remove defect
    document.addEventListener("click", function(e) {
      if (e.target.closest(".btn-remove-defect")) {
        e.target.closest(".defect-row").remove();
      }
    });
  </script>

</body>

</html>