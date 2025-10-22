<?php
// menghubungkan php dengan koneksi database
require_once __DIR__ . '/../config/function.php';
require_once __DIR__ . '/../config/auth.php';
checkAuth('timeline_transaction'); // cek apakah sudah login dan punya akses ke menu ini

$nik = $_SESSION['nik_user'];
$username = $_SESSION['username']; // Query ringkasan per job_order

// ===== Ambil parameter =====
$job_order   = $_GET['job_order'] ?? '';
$lotParam    = $_GET['lot'] ?? '';     // contoh: "1,2"
$idTransParam = $_GET['id_trans'] ?? ''; // contoh: "123,124"

if ($job_order == '') {
  die('Job Order tidak ditemukan.');
}

// Escape
$job_order = mysqli_real_escape_string($conn, $job_order);

// ===== Lot =====
$lotArray = [];
if (!empty($lotParam)) {
  $lotArray = array_filter(array_map('trim', explode(',', $lotParam)), fn($v) => $v !== '');
}

// ===== ID Trans =====
$idTransArray = [];
if (!empty($idTransParam)) {
  $idTransArray = array_filter(array_map('intval', explode(',', $idTransParam)));
}

// ===== Ambil data transaksi utama =====
$lotWhere = '';
if (!empty($lotArray)) {
  $lotConds = [];
  foreach ($lotArray as $lotVal) {
    $safeLotVal = mysqli_real_escape_string($conn, $lotVal);
    $lotConds[] = "(JSON_CONTAINS(lot, '\"$safeLotVal\"') OR JSON_CONTAINS(lot, '$safeLotVal'))";
  }
  $lotWhere = ' AND (' . implode(' OR ', $lotConds) . ')';
}

$queryTrans = "
    SELECT *
    FROM tbl_transaksi
    WHERE job_order = '$job_order'
    $lotWhere
    LIMIT 1
";

$resultTrans = mysqli_query($conn, $queryTrans);
$trans = $resultTrans && mysqli_num_rows($resultTrans) > 0
  ? mysqli_fetch_assoc($resultTrans)
  : null;

if (!$trans) {
  echo "<div class='text-danger'>
        ⚠️ Tidak ditemukan data transaksi untuk Job Order: <b>" . htmlspecialchars($job_order) . "</b>" .
    (!empty($lotParam) ? " dan Lot: <b>" . htmlspecialchars($lotParam) . "</b>" : "") .
    (!empty($idTransParam) ? " dan ID Trans: <b>" . htmlspecialchars($idTransParam) . "</b>" : "") .
    "</div>";
}

// ===== Ambil vendor =====
$vendor = [
  'name_vendor' => 'Belum Ditentukan',
  'code_vendor' => '-',
  'vendor_address' => '-'
];

if ($trans) {
  $komponenList = json_decode($trans['komponen_qty'], true);
  $firstKomponenID = $komponenList[0]['komponen'] ?? null;

  if ($firstKomponenID) {
    $sqlKomponen = "SELECT nama_komponen, model FROM tbl_komponen WHERE id_komponen = '$firstKomponenID' LIMIT 1";
    $resultKomponen = mysqli_query($conn, $sqlKomponen);
    $dataKomponen = mysqli_fetch_assoc($resultKomponen);

    if ($dataKomponen) {
      $namaKomponen = mysqli_real_escape_string($conn, $dataKomponen['nama_komponen']);
      $model = mysqli_real_escape_string($conn, $dataKomponen['model']);

      $sqlVendor = "
                SELECT v.id_vendor, v.name_vendor, v.code_vendor, v.alamat AS vendor_address
                FROM tbl_komponen k
                JOIN tbl_komponen_proses kp ON kp.id_input = k.id_komponen
                JOIN tbl_vendor_proses vp ON vp.id_proses = kp.id_proses
                JOIN tbl_vendor v ON v.id_vendor = vp.id_vendor
                WHERE k.nama_komponen = '$namaKomponen'
                  AND k.model = '$model'
                LIMIT 1
            ";
      $resultVendor = mysqli_query($conn, $sqlVendor);
      if ($resultVendor && mysqli_num_rows($resultVendor) > 0) {
        $vendor = mysqli_fetch_assoc($resultVendor);
      }
    }
  }
}

// ===== Ambil semua id_trans untuk job_order + lot (kecuali jika id_trans sudah dikirim) =====
$idTransList = [];
if (!empty($lotArray)) {
  $idTransQueryParts = [];
  foreach ($lotArray as $lotVal) {
    $lotVal = mysqli_real_escape_string($conn, $lotVal);
    $idTransQueryParts[] = "JSON_SEARCH(lot, 'one', '$lotVal') IS NOT NULL OR JSON_UNQUOTE(lot) LIKE '%\"$lotVal\"%' OR lot LIKE '%$lotVal%'";
  }
  $idTransQuery = implode(' OR ', $idTransQueryParts);

  $qIdTrans = mysqli_query($conn, "
        SELECT id_trans 
        FROM tbl_transaksi
        WHERE job_order = '$job_order'
          AND ($idTransQuery)
    ");

  if ($qIdTrans) {
    while ($r = mysqli_fetch_assoc($qIdTrans)) {
      $idTransList[] = $r['id_trans'];
    }
  }
}

// ===== Tentukan id_trans filter =====
if (!empty($idTransArray)) {
  $idTransFilter = implode(',', $idTransArray);
} else {
  $idTransFilter = !empty($idTransList)
    ? implode(',', array_map('intval', $idTransList))
    : '0';
}

// ===== Ambil log =====
$logWhere = "WHERE JSON_UNQUOTE(JSON_EXTRACT(new_data, '$.job_order')) = '$job_order'";

if (!empty($lotArray)) {
  $lotConditions = [];
  foreach ($lotArray as $lotVal) {
    $lotVal = mysqli_real_escape_string($conn, $lotVal);
    $lotConditions[] = "JSON_SEARCH(new_data, 'one', '$lotVal', NULL, '$.lot') IS NOT NULL
                            OR JSON_UNQUOTE(JSON_EXTRACT(new_data, '$.lot')) LIKE '%$lotVal%'";
  }
  $logWhere .= " AND (" . implode(" OR ", $lotConditions) . ")";
}

if (!empty($idTransFilter) && $idTransFilter !== '0') {
  $logWhere .= " AND id_trans IN ($idTransFilter)";
}

$queryLog = "
    SELECT id_log_trans, id_trans, action_type, created_at, new_data,
           JSON_UNQUOTE(JSON_EXTRACT(new_data, '$.lot')) AS lot_json
    FROM tlog_transaksi
    $logWhere
    ORDER BY created_at ASC
";
$resultLog = mysqli_query($conn, $queryLog);

// ===== Ambil kekurangan =====
$queryKekurangan = "
    SELECT tk.id_kekurangan, tk.id_trans_asal, tk.job_order, tk.komponen_qty AS tk_komponen_qty,
           tk.total_kekurangan, tk.status AS tk_status, tk.last_gate, tk.created_at
    FROM tbl_transaksi_kekurangan tk
    WHERE tk.job_order = '$job_order'
      AND tk.id_trans_asal IN ($idTransFilter)
    ORDER BY tk.created_at ASC
";
$resultKekurangan = mysqli_query($conn, $queryKekurangan);

?>

<style>
  .timeline-step.warning .timeline-circle i {
    color: #ffc107 !important;
  }

  .timeline-step.warning .timeline-label {
    color: #ffc107;
    font-weight: 600;
  }

  .progress-container {
    font-family: 'Poppins', sans-serif;
  }

  .progress {
    background-color: #e9ecef;
    border-radius: 10px;
    overflow: hidden;
    box-shadow: inset 0 1px 3px rgba(0, 0, 0, 0.1);
  }

  .progress-bar {
    transition: width 0.6s ease-in-out;
  }

  .timeline-container {
    display: flex;
    align-items: center;
    justify-content: space-between;
    overflow-x: auto;
    padding: 30px 0;
    position: relative;
  }

  /* Garis horizontal */
  .timeline-container::before {
    content: '';
    position: absolute;
    top: 50%;
    left: 0;
    right: 0;
    height: 4px;
    background: #dee2e6;
    transform: translateY(-50%);
    z-index: 0;
  }

  /* Tiap step */
  .timeline-step {
    text-align: center;
    flex: 1;
    position: relative;
    z-index: 1;
  }

  /* Lingkaran utama */
  .timeline-circle {
    width: 50px;
    height: 50px;
    margin: 0 auto;
    border-radius: 50%;
    background: #f8f9fa;
    border: 3px solid #dee2e6;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
    position: relative;
    z-index: 2;
  }

  /* Icon di tengah */
  .timeline-circle i {
    font-size: 24px;
    color: inherit;
  }

  /* Label di bawah */
  .timeline-label {
    margin-top: 10px;
    font-size: 14px;
    color: #212529;
  }

  /* Step selesai (hijau) */
  .timeline-step.completed .timeline-circle {
    background: #eaf7ee;
    border-color: #198754;
    color: #198754;
  }

  /* Step aktif (biru, ada glow) */
  .timeline-step.active .timeline-circle {
    background: #e7f1ff;
    border-color: #0d6efd;
    color: #0d6efd;
    box-shadow: 0 0 12px rgba(13, 110, 253, 0.4);
  }

  /* Step warning (kuning, kekurangan pending) */
  .timeline-step.warning .timeline-circle {
    background: #fffbea;
    border-color: #ffc107;
    color: #856404;
    box-shadow: 0 0 10px rgba(255, 193, 7, 0.4);
  }

  .timeline-step.warning .timeline-circle i {
    color: #ffc107 !important;
  }

  .timeline-step.warning .timeline-label {
    color: #856404;
    font-weight: 600;
  }

  /* Untuk layout horizontal rapi */
  .timeline-step:not(:last-child) {
    margin-right: 10px;
  }

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

  <title>iSubcont - Dashboards</title>
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
  $page = 'timeline_transaction';
  include_once __DIR__ . '/../includes/header.php';
  ?>
  <!-- End Header -->

  <main id="main" class="main">

    <div class="pagetitle text-black" style="background-color: #f0e6d2; padding: 10px 20px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
      <h1 style="font-size: 1.8rem; font-weight: 700; font-family: 'Roboto', sans-serif;">
        Details Transaction Timeline
      </h1>
    </div>

    <section class="section">
      <div class="row">
        <div class="col-lg-12">
          <div class="card">
            <div class="card-body" style="margin-top: 10px;">

              <?php

              // 🔹 Inisialisasi array id_trans
              $idTransArray = !empty($idTransFilter) ? array_map('intval', explode(',', $idTransFilter)) : [];

              $hasPendingKekurangan = false;
              $pendingGates = [];

              if ($resultKekurangan && mysqli_num_rows($resultKekurangan) > 0) {
                mysqli_data_seek($resultKekurangan, 0);
                while ($kr = mysqli_fetch_assoc($resultKekurangan)) {
                  // Filter per id_trans
                  if (!empty($idTransArray) && !in_array(intval($kr['id_trans_asal']), $idTransArray)) continue;

                  if (strtoupper(trim($kr['tk_status'])) !== 'CONFIRMED') {
                    $hasPendingKekurangan = true;
                    $pendingGates[] = $kr['last_gate'];
                  }
                }
                mysqli_data_seek($resultKekurangan, 0);
              }

              // 🔹 Mapping stages & labels
              $stages = [
                'SCAN_IN_WAREHOUSE' => 'In Warehouse',
                'SCAN_OUT_TO_VENDOR' => 'Out WH to Vendor',
                // 'SCAN_IN_VENDOR' => 'In Vendor',
                // 'SCAN_OUT_VENDOR' => 'Out Vendor',
                'SCAN_IN_INCOMING' => 'Incoming WH',
                'SCAN_CHECK_QC' => 'Check QC',
                'SCAN_OUT_TO_PRODUCTION' => 'Out to Production'
              ];

              $typeScanLabels = [
                'CREATE_BARCODE'      => 'Create QR Code',
                'SCAN_IN_WAREHOUSE'   => 'In Warehouse',
                'SCAN_OUT_TO_VENDOR'  => 'Out WH to Vendor',
                'SCAN_IN_VENDOR' => 'In Vendor',
                'SCAN_OUT_VENDOR' => 'Out Vendor',
                'SCAN_IN_INCOMING' => 'Incoming WH',
                'SCAN_CHECK_QC' => 'Check QC',
                'SCAN_OUT_TO_PRODUCTION' => 'Out to Production'
              ];

              $gateLabels = $typeScanLabels;

              mysqli_data_seek($resultLog, 0);
              $logs = [];

              while ($log = mysqli_fetch_assoc($resultLog)) {
                // Filter per id_trans
                if (!empty($idTransArray) && !in_array(intval($log['id_trans']), $idTransArray)) continue;

                $newData = json_decode($log['new_data'], true);

                $typeScan = $newData['type_scan'] ?? '';
                if (empty($typeScan)) {
                  if (!empty($log['action_type'])) {
                    $typeScan = $log['action_type'];
                  } else {
                    $typeScan = 'CREATE_BARCODE';
                  }
                }

                $createdBy = $newData['created_by'] ?? $log['updated_by'] ?? 'Unknown';

                $logs[] = [
                  'type_scan' => $typeScan,
                  'created_by' => $createdBy,
                  'created_at' => $log['created_at']
                ];
              }

              // 🔹 Hitung stages
              $completedStages = [];
              $lastStage = null;
              $stageKeys = array_keys($stages);

              foreach ($stageKeys as $key) {
                foreach ($logs as $log) {
                  if ($log['type_scan'] === $key) {
                    $completedStages[] = $key;
                    $lastStage = $key;
                    break;
                  }
                }
              }

              $totalStages = count($stageKeys);
              $completedCount = count($completedStages);

              $progressPercent = $totalStages > 0 ? round(($completedCount / $totalStages) * 100) : 0;
              if ($progressPercent > 100) $progressPercent = 100;
              if ($hasPendingKekurangan && $progressPercent >= 100) $progressPercent = 90;

              // 🔹 Tentukan next stage & ETA
              $nextStage = null;
              $etaDays = 0;

              if (!empty($lastStage)) {
                $lastIndex = array_search($lastStage, $stageKeys, true);
                if ($lastIndex !== false && isset($stageKeys[$lastIndex + 1])) {
                  $nextStageKey = $stageKeys[$lastIndex + 1];
                  $nextStage = $stages[$nextStageKey];
                }
              }

              if (!empty($nextStage)) {
                $remainingStages = $totalStages - $completedCount;
                $etaDays = max(1, $remainingStages * 1);
              }

              // === Hitung jumlah qty per stage berdasarkan komponen_qty ===
              $stageCounts = array_fill_keys(array_keys($stages), 0);

              mysqli_data_seek($resultLog, 0);
              while ($log = mysqli_fetch_assoc($resultLog)) {
                // Filter id_trans sesuai filter
                if (!empty($idTransArray) && !in_array(intval($log['id_trans']), $idTransArray)) continue;

                $newData = json_decode($log['new_data'], true);
                if (empty($newData)) continue;

                $typeScan = strtoupper(trim($newData['type_scan'] ?? $log['type_scan'] ?? $log['action_type'] ?? ''));
                if (empty($typeScan) || !isset($stageCounts[$typeScan])) continue;

                // Ambil komponen_qty
                if (!empty($newData['komponen_qty'])) {
                  $komponenList = json_decode($newData['komponen_qty'], true);
                  if (is_array($komponenList)) {
                    foreach ($komponenList as $komp) {
                      $qty = intval($komp['qty'] ?? 0);
                      $stageCounts[$typeScan] += $qty;
                    }
                  }
                }
              }

              // Hitung total order dari tbl_master_data berdasarkan job_order
              $stmt = $conn->prepare("SELECT SUM(qty) AS total_order FROM tbl_master_data WHERE job_order = ?");
              $stmt->bind_param("s", $job_order);
              $stmt->execute();
              $resultTotal = $stmt->get_result();
              $totalOrder = $resultTotal->fetch_assoc()['total_order'] ?? 0;

              // Hitung persentase tiap stage
              $stagePercents = [];
              foreach ($stageCounts as $stageKey => $count) {
                $stagePercents[$stageKey] = $totalOrder > 0 ? round(($count / $totalOrder) * 100, 2) : 0;
              }
              ?>

              <!-- Bagian Header -->
              <div class="row mb-3 align-items-start">
                <div class="col-md-8">
                  <div><strong>Bucket:</strong>
                    <?= isset($trans['bucket'])
                      ? htmlspecialchars($trans['bucket'])
                      : '<span class="text-muted">-</span>' ?>
                  </div>

                  <div><strong>Lot:</strong>
                    <?= !empty($lotArray)
                      ? htmlspecialchars(implode(', ', $lotArray))
                      : '<span class="text-muted">-</span>' ?>
                  </div>

                  <div><strong>Vendor:</strong>
                    <?= !empty($vendor['name_vendor'])
                      ? htmlspecialchars($vendor['name_vendor'])
                      : '<span class="text-muted">Belum ditentukan</span>' ?>
                  </div>
                </div>

              </div>

              <!-- Timeline -->
              <div class="timeline-container">
                <?php foreach ($stages as $key => $label):
                  $percent = $stagePercents[$key] ?? 0;
                  $class = '';
                  $icon = '<i class="bi bi-circle text-secondary"></i>'; // default abu-abu

                  if ($percent >= 100) {
                    // ✅ Stage selesai
                    $class = 'completed';
                    $icon = '<i class="bi bi-check-circle-fill text-success"></i>';
                  } elseif ($percent > 0 && $percent < 100) {
                    // ⚠️ Stage belum lengkap (progress tapi belum 100%)
                    $class = 'warning';
                    $icon = '<i class="bi bi-exclamation-triangle-fill text-warning"></i>';
                  } elseif ($percent == 0 && isset($stageKeys[$completedCount]) && $key === $stageKeys[$completedCount]) {
                    // ⏳ Stage aktif saat ini
                    $class = 'active';
                    $icon = '<i class="bi bi-hourglass-split text-primary"></i>';
                  }
                ?>
                  <div class="timeline-step <?= $class ?>">
                    <div class="timeline-circle"><?= $icon ?></div>
                    <div class="timeline-label text-center">
  <div class="fw-bold" style="font-size: 1rem;">
    <?= htmlspecialchars($label) ?>
  </div>
  <div class="fw-bold 
      <?= $percent >= 100 ? 'text-success' : 
         ($percent > 0 ? 'text-warning' : 'text-muted') ?>"
       style="font-size: 1.1rem;">
    <?= $percent ?>%
  </div>
</div>

                  </div>
                <?php endforeach; ?>
              </div>

              <hr>

              <!-- Riwayat Aktivitas -->
              <h6 class="fw-bold mb-3" style="border-left: 4px solid #0d6efd; padding-left: 8px; color: #0d6efd;">
                <i class="bi bi-clock-history me-1"></i> Riwayat Aktivitas
              </h6>
              <table class="table table-sm table-striped mt-3">
                <thead>
                  <tr>
                    <th>#</th>
                    <th>Status</th>
                    <th>User</th>
                    <th>Waktu</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (!empty($logs)): ?>
                    <?php foreach ($logs as $i => $log): ?>
                      <tr>
                        <td><?= $i + 1 ?></td>
                        <td><?= htmlspecialchars($typeScanLabels[$log['type_scan']] ?? $log['type_scan']) ?></td>
                        <td><?= htmlspecialchars($log['created_by'] ?? '—') ?></td>
                        <td><?= htmlspecialchars($log['created_at']) ?></td>
                      </tr>
                    <?php endforeach; ?>
                  <?php else: ?>
                    <tr>
                      <td colspan="4" class="text-center text-muted">Belum ada aktivitas.</td>
                    </tr>
                  <?php endif; ?>
                </tbody>
              </table>

              <!-- Data Kekurangan -->
              <?php if (mysqli_num_rows($resultKekurangan) > 0): ?>
                <hr>
                <h6 class="fw-bold mb-3" style="border-left: 4px solid #0d6efd; padding-left: 8px; color: #0d6efd;">
                  <i class="bi bi-exclamation-triangle me-1"></i> Data Kekurangan Barang
                </h6>

                <table class="table table-sm table-striped mt-3 align-middle">
                  <thead class="table-light">
                    <tr>
                      <th style="width:50px;">No</th>
                      <th>Komponen (Size & Qty)</th>
                      <th class="text-center">Total Kekurangan</th>
                      <th class="text-center">At</th>
                      <th class="text-center">Status</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php
                    $no = 1;
                    $komponen_map = [];

                    while ($k = mysqli_fetch_assoc($resultKekurangan)):
                      // Filter per id_trans
                      if (!empty($idTransArray) && !in_array(intval($k['id_trans_asal']), $idTransArray)) continue;

                      $komponen_display = [];
                      if (!empty($k['tk_komponen_qty'])) {
                        $komponen_list = json_decode($k['tk_komponen_qty'], true);
                        if (is_array($komponen_list)) {
                          $grouped = [];
                          foreach ($komponen_list as $item) {
                            $id_komponen = intval($item['komponen'] ?? 0);
                            $size = htmlspecialchars($item['size'] ?? '-', ENT_QUOTES, 'UTF-8');
                            $qty = intval($item['kekurangan'] ?? $item['qty'] ?? 0);

                            $grouped[$id_komponen][] = "{$size} ({$qty})";
                          }

                          foreach ($grouped as $komp_id => $sizes) {
                            if (!isset($komponen_map[$komp_id])) {
                              $resKom = mysqli_query($conn, "SELECT nama_komponen FROM tbl_komponen WHERE id_komponen = '$komp_id' LIMIT 1");
                              $rowKom = mysqli_fetch_assoc($resKom);
                              $komponen_map[$komp_id] = $rowKom['nama_komponen'] ?? "Komponen {$komp_id}";
                            }
                            $komponen_display[] = "{$komponen_map[$komp_id]} : " . implode(', ', $sizes);
                          }
                        }
                      }

                      $komponen_html = implode('<br>', $komponen_display) ?: '-';
                    ?>
                      <tr>
                        <td class="text-center"><?= $no++ ?></td>
                        <td><?= $komponen_html ?></td>
                        <td class="text-center fw-bold"><?= htmlspecialchars($k['total_kekurangan']) ?></td>
                        <td class="text-center"><?= htmlspecialchars($gateLabels[$k['last_gate']] ?? $k['last_gate']) ?></td>
                        <td class="text-center">
                          <?php
                          $status = htmlspecialchars($k['tk_status']);
                          $badgeClass = match ($status) {
                            'pending' => 'bg-warning text-dark',
                            'confirmed' => 'bg-success',
                            'resolved' => 'bg-secondary',
                            default => 'bg-light text-dark'
                          };
                          ?>
                          <span class="badge <?= $badgeClass ?>"><?= $status ?></span>
                        </td>
                      </tr>
                    <?php endwhile; ?>
                  </tbody>
                </table>
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

</body>

</html>