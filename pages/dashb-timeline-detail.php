<?php
// menghubungkan php dengan koneksi database
require_once __DIR__ . '/../config/function.php';
require_once __DIR__ . '/../config/auth.php';
checkAuth('timeline_transaction'); // cek apakah sudah login dan punya akses ke menu ini

$nik = $_SESSION['nik_user'];
$username = $_SESSION['username']; // Query ringkasan per job_order

// ======================= PARAMETER =======================
$job_order   = $_GET['job_order'] ?? '';
$lotParam    = $_GET['lot'] ?? '';
$idTransParam = $_GET['id_trans'] ?? '';

if (empty($job_order)) {
  die('❌ Job Order tidak ditemukan.');
}

$job_order = mysqli_real_escape_string($conn, $job_order);

// ======================= LOT HANDLER =======================
$lotArray = [];
if (!empty($lotParam)) {
  $parts = array_map('trim', explode(',', $lotParam));
  foreach ($parts as $p) {
    if (str_contains($p, '-')) {
      [$start, $end] = array_map('intval', explode('-', $p));
      if ($start <= $end) $lotArray = array_merge($lotArray, range($start, $end));
    } else $lotArray[] = intval($p);
  }
  $lotArray = array_unique($lotArray);
}

function lotArrayToRanges(array $arr): string
{
  if (empty($arr)) return '';
  sort($arr);
  $ranges = [];
  $start = $prev = $arr[0];
  for ($i = 1; $i < count($arr); $i++) {
    $num = $arr[$i];
    if ($num == $prev + 1) $prev = $num;
    else {
      $ranges[] = ($start == $prev) ? $start : "$start-$prev";
      $start = $prev = $num;
    }
  }
  $ranges[] = ($start == $prev) ? $start : "$start-$prev";
  return implode(',', $ranges);
}

// ======================= ID TRANS =======================
$idTransArray = [];
if (!empty($idTransParam)) {
  $idTransArray = array_filter(array_map('intval', explode(',', $idTransParam)));
}

// ======================= AMBIL TRANSAKSI =======================
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
  SELECT * FROM tbl_transaksi 
  WHERE job_order = '$job_order' $lotWhere
  LIMIT 1
";
$resultTrans = mysqli_query($conn, $queryTrans);
$trans = ($resultTrans && mysqli_num_rows($resultTrans) > 0)
  ? mysqli_fetch_assoc($resultTrans)
  : null;

if (!$trans) {
  echo "<div class='text-danger'>⚠️ Tidak ada data transaksi untuk Job Order <b>$job_order</b></div>";
  exit;
}

// ======================= VENDOR & KOMPONEN =======================
$vendor = ['name_vendor' => '-', 'vendors' => '-'];

$komponenList = json_decode($trans['komponen_qty'], true);
$komponenIDs = [];

if (is_array($komponenList)) {
  foreach ($komponenList as $item) {
    if (!empty($item['komponen'])) {
      $komponenIDs[] = intval($item['komponen']);
    }
  }
  $komponenIDs = array_unique($komponenIDs);
}

if (!empty($komponenIDs)) {
  $idList = implode(',', $komponenIDs);

  $sqlVendor = "
    SELECT GROUP_CONCAT(DISTINCT v.name_vendor SEPARATOR ', ') AS vendors
    FROM tbl_vendor v
    JOIN tbl_vendor_proses vp ON v.id_vendor = vp.id_vendor
    JOIN tbl_komponen_proses kp ON vp.id_proses = kp.id_proses
    WHERE kp.id_input IN ($idList) OR kp.id_output IN ($idList)
  ";

  $resVendor = mysqli_query($conn, $sqlVendor);
  if ($resVendor && mysqli_num_rows($resVendor) > 0) {
    $vendorRow = mysqli_fetch_assoc($resVendor);
    if (!empty($vendorRow['vendors'])) {
      $vendor['name_vendor'] = $vendorRow['vendors'];
    }
  }
}

// ======================= ID TRANS FILTER =======================
$idTransList = [];
if (!empty($lotArray)) {
  $idTransQueryParts = [];
  foreach ($lotArray as $lotVal) {
    $lotVal = mysqli_real_escape_string($conn, $lotVal);
    $idTransQueryParts[] = "JSON_SEARCH(lot, 'one', '$lotVal') IS NOT NULL";
  }
  $idTransQuery = implode(' OR ', $idTransQueryParts);

  $qIdTrans = mysqli_query($conn, "
    SELECT id_trans FROM tbl_transaksi
    WHERE job_order = '$job_order' AND ($idTransQuery)
  ");
  while ($r = mysqli_fetch_assoc($qIdTrans)) $idTransList[] = $r['id_trans'];
}

$idTransFilter = !empty($idTransArray)
  ? implode(',', $idTransArray)
  : (!empty($idTransList) ? implode(',', $idTransList) : '0');

// ======================= LOG =======================
$logWhere = "WHERE JSON_UNQUOTE(JSON_EXTRACT(new_data, '$.job_order')) = '$job_order'";
if (!empty($idTransFilter) && $idTransFilter !== '0') $logWhere .= " AND id_trans IN ($idTransFilter)";

$queryLog = "
  SELECT id_log_trans, id_trans, action_type, created_at, new_data,
         qty_real, qty_kekurangan, status_kekurangan
  FROM tlog_transaksi
  $logWhere
  ORDER BY created_at ASC
";
$resultLog = mysqli_query($conn, $queryLog);

// ======================= TOTAL ORDER (PLAN) =======================
$stmt = $conn->prepare("SELECT SUM(qty) AS total_order FROM tbl_master_data WHERE job_order = ?");
$stmt->bind_param("s", $job_order);
$stmt->execute();
$resultTotal = $stmt->get_result();
$totalOrder = $resultTotal->fetch_assoc()['total_order'] ?? 0;

// ======================= STAGES =======================
$stages = [
  'SCAN_IN_WAREHOUSE'    => 'In Warehouse',
  'SCAN_OUT_TO_VENDOR'   => 'Out WH to Vendor',
  'SCAN_IN_INCOMING'     => 'Incoming WH',
  'SCAN_OUT_TO_PRODUCTION' => 'Out to Production'
];

$stageCounts = array_fill_keys(array_keys($stages), 0);

if ($resultLog) {
  while ($log = mysqli_fetch_assoc($resultLog)) {
    $newData = json_decode($log['new_data'], true);
    $typeScan = strtoupper(trim($newData['type_scan'] ?? $log['action_type'] ?? ''));
    if (empty($typeScan) || !isset($stageCounts[$typeScan])) continue;

    $qty_real = intval($log['qty_real'] ?? 0);
    $qty_kekurangan = intval($log['qty_kekurangan'] ?? 0);
    $status_kekurangan = strtolower(trim($log['status_kekurangan'] ?? ''));

    // Hitung efektif
    $effective_qty = ($status_kekurangan === 'pending')
      ? max(0, $qty_real - $qty_kekurangan)
      : $qty_real;

    $stageCounts[$typeScan] += $effective_qty;
  }
}

// ======================= PERSENTASE =======================
$stagePercents = [];
foreach ($stages as $key => $label) {
  $percent = ($totalOrder > 0)
    ? round(($stageCounts[$key] / $totalOrder) * 100, 2)
    : 0;
  $stagePercents[$key] = min(100, $percent);
}

// ======================= IN WAREHOUSE (PLAN vs ACTUAL by KOMPN) =======================
$inWarehouseProgress = 0;

if (!empty($idTransFilter) && $idTransFilter !== '0') {
  // Ambil plan dari tbl_transaksi
  $planQuery = "
    SELECT komponen_qty FROM tbl_transaksi 
    WHERE id_trans IN ($idTransFilter)
  ";
  $planResult = mysqli_query($conn, $planQuery);

  $planByKomponen = [];

  while ($p = mysqli_fetch_assoc($planResult)) {
    $planList = json_decode($p['komponen_qty'], true);
    foreach ($planList as $item) {
      $komp = $item['komponen'];
      $planByKomponen[$komp] = ($planByKomponen[$komp] ?? 0) + ($item['qty'] ?? 0);
    }
  }

  // Ambil actual (SCAN_IN_WAREHOUSE)
  $actualQuery = "
    SELECT new_data 
    FROM tlog_transaksi
    WHERE id_trans IN ($idTransFilter)
    AND action_type = 'SCAN_IN_WAREHOUSE'
  ";
  $actualResult = mysqli_query($conn, $actualQuery);

  $actualByKomponen = [];

  while ($a = mysqli_fetch_assoc($actualResult)) {
    $newData = json_decode($a['new_data'], true);
    if (isset($newData['komponen_qty'])) {
      $kompList = json_decode($newData['komponen_qty'], true);
      if (is_array($kompList)) {
        foreach ($kompList as $k) {
          $komp = $k['komponen'];
          $actualByKomponen[$komp] = ($actualByKomponen[$komp] ?? 0) + ($k['qty'] ?? 0);
        }
      }
    }
  }

  // Ambil satu komponen unik pertama buat progress
  $firstKomponen = array_key_first($planByKomponen);
  if ($firstKomponen && !empty($planByKomponen[$firstKomponen])) {
    $plan = $planByKomponen[$firstKomponen];
    $actual = $actualByKomponen[$firstKomponen] ?? 0;
    $inWarehouseProgress = round(($actual / $plan) * 100, 2);
  }

  // Ganti nilai di stageCounts dan stagePercents
  $stageCounts['SCAN_IN_WAREHOUSE'] = $actualByKomponen[$firstKomponen] ?? 0;
  $stagePercents['SCAN_IN_WAREHOUSE'] = $inWarehouseProgress;
}

// ======================= OUT WH TO VENDOR (PLAN vs ACTUAL by KOMPN) =======================
$outToVendorProgress = 0;

if (!empty($idTransFilter) && $idTransFilter !== '0') {
  // Ambil plan dari tbl_transaksi
  $planQueryOut = "
    SELECT komponen_qty FROM tbl_transaksi 
    WHERE id_trans IN ($idTransFilter)
  ";
  $planResultOut = mysqli_query($conn, $planQueryOut);

  $planByKomponenOut = [];

  while ($p = mysqli_fetch_assoc($planResultOut)) {
    $planList = json_decode($p['komponen_qty'], true);
    foreach ($planList as $item) {
      $komp = $item['komponen'];
      $planByKomponenOut[$komp] = ($planByKomponenOut[$komp] ?? 0) + ($item['qty'] ?? 0);
    }
  }

  // Ambil actual (SCAN_OUT_TO_VENDOR)
  $actualQueryOut = "
    SELECT new_data 
    FROM tlog_transaksi
    WHERE id_trans IN ($idTransFilter)
    AND action_type = 'SCAN_OUT_TO_VENDOR'
  ";
  $actualResultOut = mysqli_query($conn, $actualQueryOut);

  $actualByKomponenOut = [];

  while ($a = mysqli_fetch_assoc($actualResultOut)) {
    $newData = json_decode($a['new_data'], true);
    if (isset($newData['komponen_qty'])) {
      $kompList = json_decode($newData['komponen_qty'], true);
      if (is_array($kompList)) {
        foreach ($kompList as $k) {
          $komp = $k['komponen'];
          $actualByKomponenOut[$komp] = ($actualByKomponenOut[$komp] ?? 0) + ($k['qty'] ?? 0);
        }
      }
    }
  }

  // Ambil satu komponen unik pertama buat progress
  $firstKomponenOut = array_key_first($planByKomponenOut);
  if ($firstKomponenOut && !empty($planByKomponenOut[$firstKomponenOut])) {
    $plan = $planByKomponenOut[$firstKomponenOut];
    $actual = $actualByKomponenOut[$firstKomponenOut] ?? 0;
    $outToVendorProgress = round(($actual / $plan) * 100, 2);
  }

  // Ganti nilai di stageCounts dan stagePercents
  $stageCounts['SCAN_OUT_TO_VENDOR'] = $actualByKomponenOut[$firstKomponenOut] ?? 0;
  $stagePercents['SCAN_OUT_TO_VENDOR'] = $outToVendorProgress;
}

// ======================= SCAN_IN_INCOMING (Average Progress) =======================
$queryIncoming = "
  SELECT 
    id_trans,
    qty_real,
    qty_kekurangan,
    status_kekurangan
  FROM tlog_transaksi
  WHERE action_type = 'SCAN_IN_INCOMING'
";

if (!empty($idTransFilter) && $idTransFilter !== '0') {
  $queryIncoming .= " AND id_trans IN ($idTransFilter)";
}

$resultIncoming = mysqli_query($conn, $queryIncoming);
$progressIncoming = 0;

if ($resultIncoming && mysqli_num_rows($resultIncoming) > 0) {

  // Ambil plan sesuai id_transFilter (bukan job_order)
  $qPlan = mysqli_query($conn, "
    SELECT komponen_qty 
    FROM tbl_transaksi 
    WHERE id_trans IN ($idTransFilter)
  ");

  $planData = [];
  while ($rowPlan = mysqli_fetch_assoc($qPlan)) {
    $planArray = json_decode($rowPlan['komponen_qty'], true) ?? [];
    foreach ($planArray as $p) {
      $idKomp = intval($p['komponen'] ?? 0);
      $sz = trim($p['size'] ?? '');
      $qty = intval($p['qty'] ?? 0);
      if ($idKomp && $sz !== '') {
        $planData[$idKomp][$sz] = $qty;
      }
    }
  }

  $totalPersen = 0;
  $jumlahKomponen = 0;

  while ($rowIncoming = mysqli_fetch_assoc($resultIncoming)) {
    $qtyReal = json_decode($rowIncoming['qty_real'] ?? '[]', true);
    $qtyKekurangan = json_decode($rowIncoming['qty_kekurangan'] ?? '[]', true);
    $statusKekurangan = strtolower(trim($rowIncoming['status_kekurangan'] ?? ''));

    foreach ($qtyReal as $real) {
      $idKomponen = intval($real['komponen']);
      $size = $real['size'] ?? '-';
      $qtyRealValue = intval($real['qty'] ?? 0);

      $qtyKurang = 0;
      if (!empty($qtyKekurangan) && $statusKekurangan === 'pending') {
        foreach ($qtyKekurangan as $kurang) {
          if (
            intval($kurang['komponen']) === $idKomponen &&
            ($kurang['size'] ?? '-') === $size
          ) {
            $qtyKurang = intval($kurang['qty'] ?? 0);
            break;
          }
        }
      } elseif ($statusKekurangan === 'confirmed') {
        $qtyKurang = 0; // sudah selesai
      }

      $qtyFinal = max(0, $qtyRealValue - $qtyKurang);
      $planQty = $planData[$idKomponen][$size] ?? 0;

      if ($planQty > 0) {
        $persen = ($qtyFinal / $planQty) * 100;
        $totalPersen += $persen;
        $jumlahKomponen++;
      }
    }
  }

  $progressIncoming = $jumlahKomponen > 0 ? round($totalPersen / $jumlahKomponen, 2) : 0;
  $stagePercents['SCAN_IN_INCOMING'] = $progressIncoming;
}

// ======================= SCAN_OUT_TO_PRODUCTION =======================
$progressProduction = 0;

$queryOutProd = "
  SELECT COUNT(*) AS total
  FROM tlog_transaksi
  WHERE action_type = 'SCAN_OUT_TO_PRODUCTION'
";

if (!empty($idTransFilter) && $idTransFilter !== '0') {
  $queryOutProd .= " AND id_trans IN ($idTransFilter)";
}

$resultOutProd = mysqli_query($conn, $queryOutProd);
$dataOutProd = mysqli_fetch_assoc($resultOutProd);

if ($dataOutProd && intval($dataOutProd['total']) > 0) {
  $progressProduction = $progressIncoming >= 100 ? 100 : $progressIncoming;
}

$stagePercents['SCAN_OUT_TO_PRODUCTION'] = $progressProduction;


// ======================= KEKURANGAN =======================
$queryKekurangan = "
  SELECT *
  FROM tlog_transaksi
  WHERE JSON_UNQUOTE(JSON_EXTRACT(new_data, '$.job_order')) = '$job_order'
  AND JSON_LENGTH(qty_kekurangan) > 0
";
if (!empty($idTransFilter) && $idTransFilter !== '0') {
  $queryKekurangan .= " AND id_trans IN ($idTransFilter)";
}

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

              $allKekurangan = [];
              $hasPendingKekurangan = false;
              $pendingGates = [];

              if ($resultKekurangan && mysqli_num_rows($resultKekurangan) > 0) {
                // bangun array manual supaya fleksibel
                while ($kr = mysqli_fetch_assoc($resultKekurangan)) {
                  // decode new_data JSON (jika ada)
                  $newData = is_array($kr['new_data']) ? $kr['new_data'] : json_decode($kr['new_data'] ?? '', true);

                  // status kekurangan berasal dari kolom status_kekurangan, fallback ke newData jika perlu
                  $statusKekurangan = strtolower(trim($kr['status_kekurangan'] ?? $newData['status_kekurangan'] ?? 'pending'));

                  // last gate ambil dari new_data.type_scan, fallback ke action_type
                  $lastGate = strtoupper(trim($newData['type_scan'] ?? $kr['action_type'] ?? ''));

                  // pastikan id_trans yang dipakai ada (pakai id_trans)
                  $idTrans = intval($kr['id_trans'] ?? 0);

                  // jika ada filter idTransArray, skip yang bukan relevan
                  if (!empty($idTransArray) && !in_array($idTrans, $idTransArray)) {
                    continue;
                  }

                  // tandai pending
                  if ($statusKekurangan !== 'confirmed') {
                    $hasPendingKekurangan = true;
                    $pendingGates[] = $lastGate;
                  }

                  // simpan row, tapi juga tambahkan parsed fields supaya mudah pakai di view
                  $kr['_parsed'] = [
                    'status_kekurangan' => $statusKekurangan,
                    'last_gate' => $lastGate,
                    'new_data' => $newData
                  ];

                  $allKekurangan[] = $kr;
                }
              }

              // 🔹 Mapping stages & labels
              $stages = [
                'SCAN_IN_WAREHOUSE' => 'In Warehouse',
                'SCAN_OUT_TO_VENDOR' => 'Out WH to Vendor',
                'SCAN_IN_INCOMING' => 'Incoming WH',
                // 'SCAN_CHECK_QC' => 'Check QC',
                'SCAN_OUT_TO_PRODUCTION' => 'Out to Production'
              ];

              $typeScanLabels = [
                'CREATE_BARCODE'      => 'Create Barcode',
                'SCAN_IN_WAREHOUSE'   => 'In Warehouse',
                'SCAN_OUT_TO_VENDOR'  => 'Out WH to Vendor',
                'SCAN_IN_VENDOR' => 'In Vendor',
                'SCAN_OUT_VENDOR' => 'Out Vendor',
                'SCAN_IN_INCOMING' => 'Incoming WH',
                'SCAN_CHECK_QC' => 'Check QC',
                'SCAN_OUT_TO_PRODUCTION' => 'Out to Production'
              ];

              $gateLabels = $typeScanLabels;

              // 🔹 Proses log
              mysqli_data_seek($resultLog, 0);
              $logs = [];

              while ($log = mysqli_fetch_assoc($resultLog)) {
                if (!empty($idTransArray) && !in_array(intval($log['id_trans']), $idTransArray)) continue;

                $newData = json_decode($log['new_data'], true);
                $typeScan = strtoupper(trim($newData['type_scan'] ?? $log['action_type'] ?? 'CREATE_BARCODE'));
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
              if ($hasPendingKekurangan && $completedCount >= $totalStages) $progressPercent = 90;

              // 🔹 Next stage & ETA
              $nextStage = null;
              if (!empty($lastStage)) {
                $lastIndex = array_search($lastStage, $stageKeys, true);
                if ($lastIndex !== false && isset($stageKeys[$lastIndex + 1])) {
                  $nextStage = $stages[$stageKeys[$lastIndex + 1]];
                }
              }

              // 🔹 Ambil total order
              $stmt = $conn->prepare("SELECT SUM(qty) AS total_order FROM tbl_master_data WHERE job_order = ?");
              $stmt->bind_param("s", $job_order);
              $stmt->execute();
              $resultTotal = $stmt->get_result();
              $totalOrder = $resultTotal->fetch_assoc()['total_order'] ?? 0;


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
                      ? htmlspecialchars(lotArrayToRanges($lotArray))
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
                          <?= $percent >= 100 ? 'text-success' : ($percent > 0 ? 'text-warning' : 'text-muted') ?>"
                        style="font-size: 1.1rem;">
                        <?= $percent ?>%
                        <?= $percent >= 100 ? '✅' : ($percent > 0 ? '⏳' : '') ?>
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
                  <?php
                  $no = 1;
                  if (!empty($logs)):
                    foreach ($logs as $log):
                      // skip kalau type_scan tidak ada di daftar label
                      if (!isset($typeScanLabels[$log['type_scan']])) continue;

                      $statusLabel = $typeScanLabels[$log['type_scan']];
                  ?>
                      <tr>
                        <td><?= $no++ ?></td>
                        <td><?= htmlspecialchars($statusLabel) ?></td>
                        <td><?= htmlspecialchars($log['created_by'] ?? '—') ?></td>
                        <td><?= htmlspecialchars($log['created_at']) ?></td>
                      </tr>
                    <?php
                    endforeach;

                    // kalau ternyata semua di-skip
                    if ($no === 1):
                    ?>
                      <tr>
                        <td colspan="4" class="text-center text-muted">Belum ada aktivitas.</td>
                      </tr>
                    <?php
                    endif;
                  else:
                    ?>
                    <tr>
                      <td colspan="4" class="text-center text-muted">Belum ada aktivitas.</td>
                    </tr>
                  <?php endif; ?>
                </tbody>
              </table>

              <!-- Data Kekurangan -->
              <?php if (!empty($allKekurangan)): ?>
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

                    foreach ($allKekurangan as $k):
                      $newData = $k['_parsed']['new_data'] ?? json_decode($k['new_data'], true);
                      $komponen_list = json_decode($k['qty_kekurangan'] ?? '[]', true);

                      if (empty($komponen_list) || !is_array($komponen_list)) continue;

                      // Ambil type_scan dari new_data
                      $typeScan = strtoupper(trim($newData['type_scan'] ?? $k['action_type'] ?? '-'));

                      // Hitung total kekurangan
                      $total_kekurangan = 0;
                      foreach ($komponen_list as $item) {
                        $total_kekurangan += intval($item['qty'] ?? 0);
                      }

                      // Gabungkan komponen + size + qty
                      $komponen_display = [];
                      $grouped = [];

                      foreach ($komponen_list as $item) {
                        $id_komponen = intval($item['komponen'] ?? 0);
                        $size = htmlspecialchars($item['size'] ?? '-', ENT_QUOTES, 'UTF-8');
                        $qty = intval($item['qty'] ?? 0);
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

                      $komponen_html = implode('<br>', $komponen_display) ?: '-';

                      // Status kekurangan
                      $status = strtolower(trim($k['_parsed']['status_kekurangan'] ?? 'pending'));
                      $badgeClass = match ($status) {
                        'pending'   => 'bg-warning text-dark',
                        'confirmed' => 'bg-success',
                        'resolved'  => 'bg-secondary',
                        default     => 'bg-light text-dark'
                      };
                    ?>
                      <tr>
                        <td class="text-center"><?= $no++ ?></td>
                        <td><?= $komponen_html ?></td>
                        <td class="text-center fw-bold"><?= $total_kekurangan ?></td>
                        <?php
                        $atLabel = $typeScanLabels[$typeScan] ?? $typeScan; // fallback kalau belum ada di daftar
                        ?>
                        <td class="text-center"><?= htmlspecialchars($atLabel) ?></td>

                        <td class="text-center">
                          <span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($status) ?></span>
                        </td>
                      </tr>
                    <?php endforeach; ?>
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