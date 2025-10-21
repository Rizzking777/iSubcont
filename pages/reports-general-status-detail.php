<?php
// menghubungkan php dengan koneksi database
require_once __DIR__ . '/../config/function.php';
require_once __DIR__ . '/../config/auth.php';
checkAuth('general_status'); // cek apakah sudah login dan punya akses ke menu ini

$nik = $_SESSION['nik_user'];
$username = $_SESSION['username']; // Query ringkasan per job_order

$job_order = $_GET['job_order'] ?? '';
if (empty($job_order)) {
  die("<div class='alert alert-danger'>Job Order tidak ditemukan.</div>");
}

// Fungsi normalisasi size
function normalizeSize($s)
{
  return strtoupper(trim($s)); // semua size jadi uppercase dan trim spasi
}

// 🔹 1️⃣ Ambil informasi umum
$infoQuery = "
    SELECT 
        job_order, po_code, po_item, style, model, ncvs, bucket,
        MIN(date_updated) AS doc_date
    FROM tbl_master_data
     WHERE job_order = '$job_order'
    GROUP BY job_order
";
$info = $conn->query($infoQuery)->fetch_assoc();

// 🔹 1️⃣ Ambil LOT dan SIZE
$lots = $conn->query("
    SELECT DISTINCT lot 
    FROM tbl_master_data 
     WHERE job_order = '$job_order'
    ORDER BY CAST(SUBSTRING_INDEX(lot, ' ', -1) AS UNSIGNED)
")->fetch_all(MYSQLI_ASSOC);

$sizesQuery = $conn->query("
    SELECT DISTINCT size
    FROM tbl_master_data
     WHERE job_order = '$job_order'
");
$sizes = $sizesQuery->fetch_all(MYSQLI_ASSOC);

if (empty($lots)) die("<div class='alert alert-warning'>Tidak ada LOT ditemukan untuk job order $job_order.</div>");
if (empty($sizes)) die("<div class='alert alert-warning'>Tidak ada SIZE ditemukan untuk job order $job_order.</div>");

// Normalisasi dan sorting size
$sizes = array_map(fn($s) => ['size' => normalizeSize($s['size'] ?? $s)], $sizes);
usort($sizes, function ($a, $b) {
  $sa = $a['size'];
  $sb = $b['size'];
  preg_match('/\d+/', $sa, $ma);
  preg_match('/\d+/', $sb, $mb);
  $na = intval($ma[0] ?? 0);
  $nb = intval($mb[0] ?? 0);
  return $na !== $nb ? $na <=> $nb : strnatcasecmp($sa, $sb);
});

// 🔹 2️⃣ Ambil data PLAN
$planData = [];
$planQuery = $conn->query("
    SELECT lot, size, SUM(qty) AS total_plan
    FROM tbl_master_data
     WHERE job_order = '$job_order'
    GROUP BY lot, size
");
while ($r = $planQuery->fetch_assoc()) {
  $lotKey = (string)$r['lot'];
  $sizeKey = normalizeSize($r['size']);
  $planData[$lotKey][$sizeKey] = (int)$r['total_plan'];
}

// 🔹 3️⃣ Ambil log SCAN_OUT_TO_PRODUCTION (dengan defect + distribusi proporsional)
$logData = [];

$logQ = $conn->query("
    SELECT 
        JSON_UNQUOTE(JSON_EXTRACT(new_data,'$.lot')) AS lot_json,
        JSON_UNQUOTE(JSON_EXTRACT(new_data,'$.komponen_qty')) AS komponen_json,
        JSON_UNQUOTE(JSON_EXTRACT(new_data,'$.defect_qty')) AS defect_json
    FROM tlog_transaksi
    WHERE JSON_UNQUOTE(JSON_EXTRACT(new_data,'$.job_order'))='$job_order'
      AND JSON_UNQUOTE(JSON_EXTRACT(new_data,'$.type_scan'))='SCAN_OUT_TO_PRODUCTION'
");

while ($r = $logQ->fetch_assoc()) {
  $lotsArr   = json_decode($r['lot_json'], true);
  $kompArr   = json_decode($r['komponen_json'], true);
  $defectArr = json_decode($r['defect_json'], true) ?: [];

  if (!is_array($lotsArr) || !is_array($kompArr)) continue;

  // Buat map defect
  $defectMap = [];
  foreach ($defectArr as $d) {
    $size = normalizeSize($d['size'] ?? '');
    $defectMap[$size] = (int)($d['qty'] ?? 0);
  }

  foreach ($kompArr as $k) {
    $size = normalizeSize($k['size'] ?? '');
    $qtyAsli = (int)($k['qty'] ?? 0);
    if ($size === '' || $qtyAsli <= 0) continue;

    // Kurangi defect (defect tidak dibagi antar lot)
    $defect = $defectMap[$size] ?? 0;
    $qtyFinal = max($qtyAsli - $defect, 0);

    // 🔸 Hitung total plan semua lot untuk size ini
    $totalPlanForSize = 0;
    foreach ($lotsArr as $lot) {
      $lotKey = (string)$lot;
      $totalPlanForSize += $planData[$lotKey][$size] ?? 0;
    }

    // 🔸 Distribusi proporsional berdasarkan plan
    foreach ($lotsArr as $lot) {
      $lotKey = (string)$lot;
      $planLot = $planData[$lotKey][$size] ?? 0;
      if ($totalPlanForSize > 0) {
        $allocatedQty = round(($planLot / $totalPlanForSize) * $qtyFinal, 2);
      } else {
        // fallback: bagi rata kalau plan-nya nol semua
        $allocatedQty = round($qtyFinal / count($lotsArr), 2);
      }

      $logData[$lotKey][$size]['SCAN_OUT_TO_PRODUCTION'] =
        ($logData[$lotKey][$size]['SCAN_OUT_TO_PRODUCTION'] ?? 0) + $allocatedQty;
    }
  }
}

// 🔹 4️⃣ Ambil log SCAN_IN_WAREHOUSE
$inData = [];
$inQ = $conn->query("
    SELECT 
        JSON_UNQUOTE(JSON_EXTRACT(new_data,'$.lot')) AS lot_json,
        JSON_UNQUOTE(JSON_EXTRACT(new_data,'$.komponen_qty')) AS komponen_json
    FROM tlog_transaksi
    WHERE JSON_UNQUOTE(JSON_EXTRACT(new_data,'$.job_order'))='$job_order'
      AND JSON_UNQUOTE(JSON_EXTRACT(new_data,'$.type_scan'))='SCAN_IN_WAREHOUSE'
");
while ($r = $inQ->fetch_assoc()) {
  $lotsArr = json_decode($r['lot_json'], true);
  $kompArr = json_decode($r['komponen_json'], true);
  if (!is_array($lotsArr) || !is_array($kompArr)) continue;

  foreach ($lotsArr as $lot) {
    $lotKey = (string)$lot;
    foreach ($kompArr as $k) {
      $size = normalizeSize($k['size'] ?? '');
      $qty  = (int)($k['qty'] ?? 0);
      if ($size === '' || $qty <= 0) continue;

      $inData[$lotKey][$size] = ($inData[$lotKey][$size] ?? 0) + $qty;
    }
  }
}

// 🔹 5️⃣ Ambil data kekurangan (CONFIRM_KEKURANGAN atau masih PENDING)
$kekuranganData = [];

// 🔸 Coba ambil dari log konfirmasi dulu
$kekLogQ = $conn->query("
    SELECT new_data
    FROM tlog_transaksi
    WHERE action_type = 'CONFIRM_KEKURANGAN'
      AND JSON_UNQUOTE(JSON_EXTRACT(new_data,'$.job_order')) = '$job_order'
");

if ($kekLogQ->num_rows > 0) {
  // ✅ Jika ada konfirmasi, pakai data dari log
  while ($r = $kekLogQ->fetch_assoc()) {
    $newData = json_decode($r['new_data'], true);
    if (!$newData) continue;

    $status = strtolower(trim($newData['status'] ?? ''));
    $kompArr = json_decode($newData['komponen_qty'], true);
    if (!is_array($kompArr)) continue;

    // --- Parsing CONFIRM_KEKURANGAN dengan perlindungan variabel ---
    foreach ($kompArr as $k) {
      $size = normalizeSize($k['size'] ?? '');
      $qty  = (int)($k['kekurangan'] ?? $k['qty'] ?? 0);
      $lotField = $k['lot'] ?? '[]';

      // pastikan $lotArr selalu array valid
      if (is_string($lotField)) {
        $lotArr = json_decode($lotField, true);
      } elseif (is_array($lotField)) {
        $lotArr = $lotField;
      } else {
        $lotArr = [];
      }

      if (empty($lotArr)) {
        // fallback: kekurangan tanpa lot spesifik
        $kekuranganData[$size]['status'] = $status;
        $kekuranganData[$size]['qty'] = $qty;
      } else {
        foreach ($lotArr as $l) {
          $lotKey = (string)$l;
          if (!isset($kekuranganData[$size]['per_lot'])) {
            $kekuranganData[$size]['per_lot'] = [];
          }
          $kekuranganData[$size]['per_lot'][$lotKey] = [
            'status' => $status,
            'qty'    => $qty
          ];
        }
      }
    }
  }
} else {
  // 🔸 Jika belum ada konfirmasi, ambil dari tabel kekurangan (PENDING)
  $kekTblQ = $conn->query("
        SELECT komponen_qty, status, created_at
        FROM tbl_transaksi_kekurangan
        WHERE job_order = '$job_order' AND status = 'PENDING'
        ORDER BY created_at DESC;
  ");

  while ($r = $kekTblQ->fetch_assoc()) {
    $status = strtolower(trim($r['status'] ?? ''));
    $kompArr = json_decode($r['komponen_qty'], true);
    if (!is_array($kompArr)) continue;

    foreach ($kompArr as $k) {
      $size = normalizeSize($k['size'] ?? '');
      $qty  = (int)($k['kekurangan'] ?? $k['qty'] ?? 0);

      $kekuranganData[$size] = [
        'status' => $status,
        'qty'    => $qty
      ];
    }
  }
}

// 🔹 6️⃣ Hitung tableData lengkap (plan + balance in + balance out)
$tableData = [];

foreach ($sizes as $s) {
  $sizeKey = $s['size'];

  foreach ($lots as $lotRow) {
    $lot = (string)$lotRow['lot'];
    $plan = $planData[$lot][$sizeKey] ?? 0;

    // 🔹 BALANCE IN
    $inScan = $inData[$lot][$sizeKey] ?? 0;
    $balanceIn = ($inScan >= $plan) ? 0 : ($inScan - $plan);

    // 🔹 BALANCE OUT
    $outQty = $logData[$lot][$sizeKey]['SCAN_OUT_TO_PRODUCTION'] ?? 0;

    // --- cek apakah ada CONFIRM_KEKURANGAN per lot & size ---
    $confirmLot = false;
    $kekuranganLotQty = 0;

    if (
      isset($kekuranganData[$sizeKey]['per_lot']) &&
      is_array($kekuranganData[$sizeKey]['per_lot']) &&
      isset($kekuranganData[$sizeKey]['per_lot'][$lot]) &&
      is_array($kekuranganData[$sizeKey]['per_lot'][$lot])
    ) {
      $confirmLot = strtolower($kekuranganData[$sizeKey]['per_lot'][$lot]['status'] ?? '') === 'confirmed';
      $kekuranganLotQty = (int)($kekuranganData[$sizeKey]['per_lot'][$lot]['qty'] ?? 0);
    }

    if ($confirmLot) {
      // ✅ Jika sudah dikonfirmasi kekurangannya untuk lot ini
      $balanceOut = 0;
    } else {
      // 🔸 Belum dikonfirmasi → cek selisih
      if ($outQty < $plan) {
        $deduct = $plan - $outQty;
        $balanceOut = -$deduct;
      } else {
        $balanceOut = 0;
      }
    }

    $tableData[$lot][$sizeKey] = [
      'plan' => $plan,
      'in'   => $balanceIn,
      'out'  => $balanceOut
    ];
  }
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
  $page = 'general_status';
  include_once __DIR__ . '/../includes/header.php';
  ?>
  <!-- End Header -->

  <main id="main" class="main">

    <div class="pagetitle text-black" style="background-color: #f0e6d2; padding: 10px 20px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
      <h1 style="font-size: 1.8rem; font-weight: 700; font-family: 'Roboto', sans-serif;">
        Details General Status
      </h1>
    </div>

    <section class="section">
      <div class="row">
        <div class="col-lg-12">
          <div class="card">
            <div class="card-body" style="margin-top: 10px;">

              <div class="container mt-4">

                <!-- Info Job Order -->
                <div class="row g-2">
                  <div class="col-md-3">
                    <label style="font-weight: bold;">Job Order</label>
                    <input class="form-control" readonly style="background-color: #EEEEEE;" value="<?= $info['job_order'] ?>">
                  </div>
                  <div class="col-md-3">
                    <label style="font-weight: bold;">Bucket</label>
                    <input class="form-control" readonly style="background-color: #EEEEEE;" value="<?= $info['bucket'] ?>">
                  </div>
                  <div class="col-md-3">
                    <label style="font-weight: bold;">PO Code</label>
                    <input class="form-control" readonly style="background-color: #EEEEEE;" value="<?= $info['po_code'] ?>">
                  </div>
                  <div class="col-md-3">
                    <label style="font-weight: bold;">PO Item</label>
                    <input class="form-control" readonly style="background-color: #EEEEEE;" value="<?= $info['po_item'] ?>">
                  </div>
                </div>

                <div class="row g-2 mt-2">
                  <div class="col-md-3">
                    <label style="font-weight: bold;">Model</label>
                    <input class="form-control" readonly style="background-color: #EEEEEE;" value="<?= $info['model'] ?>">
                  </div>
                  <div class="col-md-3">
                    <label style="font-weight: bold;">Style</label>
                    <input class="form-control" readonly style="background-color: #EEEEEE;" value="<?= $info['style'] ?>">
                  </div>
                  <div class="col-md-6">
                    <label style="font-weight: bold;">NCVS</label>
                    <input class="form-control" readonly style="background-color: #EEEEEE;" value="<?= $info['ncvs'] ?>">
                  </div>
                </div>
              </div>

              

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