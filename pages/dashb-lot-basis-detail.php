<?php
// menghubungkan php dengan koneksi database
require_once __DIR__ . '/../config/function.php';
require_once __DIR__ . '/../config/auth.php';
checkAuth('lot_basis'); // cek apakah sudah login dan punya akses ke menu ini

$nik = $_SESSION['nik_user'];
$username = $_SESSION['username']; // Query ringkasan per job_order

$job_order = $_GET['job_order'] ?? '';
if (empty($job_order)) {
  die("<div class='alert alert-danger'>Job Order tidak ditemukan.</div>");
}

// Normalisasi size — biarkan sesuai database
function normalizeSize($s)
{
  $s = strtoupper(trim($s));

  // Jika size angka murni → pad kiri dengan 0
  if (preg_match('/^\d+$/', $s)) {
    return str_pad($s, 3, "0", STR_PAD_LEFT);
  }

  // Jika format misal 4T → jadi 04T
  if (preg_match('/^(\d+)([A-Z]+)$/', $s, $m)) {
    return str_pad($m[1], 2, "0", STR_PAD_LEFT) . $m[2];
  }

  return $s;
}

// 1️⃣ Ambil informasi umum
$infoQuery = "
  SELECT 
      job_order, po_code, po_item, style, model, ncvs, bucket,
      MIN(date_updated) AS doc_date
  FROM tbl_master_data
  WHERE job_order = '$job_order'
  GROUP BY job_order
";
$info = $conn->query($infoQuery)->fetch_assoc();

// 🧩 Ambil daftar komponen untuk model ini
if (!isset($komponenList) || !is_array($komponenList) || count($komponenList) === 0) {
  $komponenList = [];
  $modelForKomponen = $conn->real_escape_string($info['model'] ?? '');
  $qK = $conn->query("
    SELECT k.id_komponen, k.nama_komponen
    FROM tbl_komponen k
    INNER JOIN tbl_komponen_proses p 
      ON k.id_komponen = p.id_input
    WHERE k.model = '{$modelForKomponen}'
      AND k.is_deleted = 0
    ORDER BY k.nama_komponen ASC
");

  if ($qK && $qK->num_rows > 0) {
    while ($r = $qK->fetch_assoc()) {
      $komponenList[] = [
        'id' => (int)$r['id_komponen'],
        'nama' => $r['nama_komponen']
      ];
    }
  }

  if (empty($komponenList)) {
    $komponenList = [['id' => 0, 'nama' => 'Komponen']];
  }
}

// 🧩 Ambil data transaksi aktif dari tlog_transaksi (INSERT pertama untuk job_order)
$transQ = $conn->query("
  SELECT new_data
  FROM tlog_transaksi
  WHERE action_type = 'INSERT'
    AND JSON_UNQUOTE(JSON_EXTRACT(new_data, '$.job_order')) = '{$job_order}'
  ORDER BY id_log_trans ASC
  LIMIT 1
");

$transaksiData = [];
$komponenIDsInTrans = [];
$kompArr = [];

if ($transQ && $transQ->num_rows > 0) {
  $rT = $transQ->fetch_assoc();
  $transaksiData = json_decode($rT['new_data'], true);

  if (!empty($transaksiData['qty_real'])) {
    $qtyRealArr = json_decode($transaksiData['qty_real'], true);
    if (is_array($qtyRealArr)) {
      foreach ($qtyRealArr as $qr) {
        $qr['size'] = normalizeSize($qr['size'] ?? '');
        if (!empty($qr['komponen'])) {
          $komponenIDsInTrans[] = (int)$qr['komponen'];
        }
      }
      $kompArr = $qtyRealArr; // ✅ Simpan untuk mapping ke planData
    }
  }
}

$komponenIDsInTrans = array_unique($komponenIDsInTrans);

// ⚠️ Filter hanya komponen yang muncul di transaksi
if (!empty($komponenIDsInTrans)) {
  $komponenList = array_filter($komponenList, function ($k) use ($komponenIDsInTrans) {
    return in_array((int)$k['id'], $komponenIDsInTrans);
  });
  $komponenList = array_values($komponenList);
}

// 2️⃣ Ambil LOT & SIZE
$lots = $conn->query("
  SELECT DISTINCT lot 
  FROM tbl_master_data 
  WHERE job_order = '$job_order'
  ORDER BY CAST(SUBSTRING_INDEX(lot, ' ', -1) AS UNSIGNED)
")->fetch_all(MYSQLI_ASSOC);

$sizes = $conn->query("
  SELECT DISTINCT size
  FROM tbl_master_data
  WHERE job_order = '$job_order'
")->fetch_all(MYSQLI_ASSOC);

if (empty($lots)) die("<div class='alert alert-warning'>Tidak ada LOT ditemukan untuk job order $job_order.</div>");
if (empty($sizes)) die("<div class='alert alert-warning'>Tidak ada SIZE ditemukan untuk job order $job_order.</div>");

// 3️⃣ PLAN DATA (mapping ke komponen dari qty_real)
$planData = [];

$modelName = $conn->real_escape_string($info['model'] ?? '');

$qLotSize = $conn->query("
  SELECT lot, size, SUM(qty) AS total_plan
  FROM tbl_master_data
  WHERE model = '{$modelName}'
    AND job_order = '{$job_order}'
  GROUP BY lot, size
");

if ($qLotSize && $qLotSize->num_rows > 0) {
  while ($r = $qLotSize->fetch_assoc()) {
    $lot  = (string)$r['lot'];
    $size = normalizeSize($r['size']);
    $plan = (float)$r['total_plan'];

    // Assign ke semua komponen secara dinamis
    foreach ($komponenList as $komp) {
      $kompId = $komp['id'];
      $planData[$lot][$size][$kompId] = $plan;
    }
  }
}

// 🔹 Hitung total plan per LOT dan SIZE (tanpa peduli komponen)
$planTotal = [];
foreach ($planData as $lot => $sizes) {
  foreach ($sizes as $size => $komps) {
    $planTotal[$lot][$size] = reset($komps);
  }
}

// 🔹 Ambil daftar ukuran hanya dari plan resmi
$officialSizes = [];
foreach ($planData as $lot => $sizes) {
  foreach ($sizes as $size => $komps) {
    $officialSizes[$size] = true;
  }
}
$officialSizes = array_keys($officialSizes);

// 🔹 Urutkan size berdasarkan angka dan karakter tambahan (misal 07T)
usort($officialSizes, function ($a, $b) {
  preg_match('/\d+/', $a, $ma);
  preg_match('/\d+/', $b, $mb);
  $na = intval($ma[0] ?? 0);
  $nb = intval($mb[0] ?? 0);
  return $na !== $nb ? $na <=> $nb : strnatcasecmp($a, $b);
});

// 4️⃣ SCAN_IN_WAREHOUSE dengan distribusi FIFO antar lot
$inData = [];
$defisitData = [];

$sortedLots = array_keys($planData);
sort($sortedLots);

$inQ = $conn->query("
    SELECT *
    FROM tlog_transaksi
    WHERE action_type = 'SCAN_IN_WAREHOUSE'
      AND JSON_UNQUOTE(JSON_EXTRACT(new_data, '$.job_order')) = '$job_order'
    ORDER BY id_log_trans ASC
");

while ($r = $inQ->fetch_assoc()) {
  $data = json_decode($r['new_data'], true);
  $lotsArr = json_decode($data['lot'] ?? '[]', true);
  if (!$lotsArr) continue;

  $kompArr = json_decode($r['qty_real'] ?? '[]', true);
  if (!is_array($kompArr) || empty($kompArr)) continue;

  foreach ($kompArr as $k) {
    $size = normalizeSize($k['size'] ?? '');
    $komp = (int)($k['komponen'] ?? 0);
    $qty  = (float)($k['qty'] ?? 0);
    if (!$size || $qty <= 0) continue;

    $sisaQty = $qty;
    if (empty($planTotal)) continue;

    foreach ($sortedLots as $lot) {
      $planLot = (float)($planTotal[$lot][$size] ?? 0);
      if ($planLot <= 0) continue;

      $sudahMasuk = $inData[$lot][$size][$komp] ?? 0;
      $defisit = $planLot - $sudahMasuk;
      if ($defisit <= 0) continue;

      $ambil = min($defisit, $sisaQty);
      $inData[$lot][$size][$komp] = $sudahMasuk + $ambil;

      $sisaQty -= $ambil;
      if ($sisaQty <= 0) break;
    }
  }
}

// 🔧 5️⃣ SCAN_OUT_TO_VENDOR (Distribusi FIFO antar LOT)
$outVendorData = [];
$sortedLots = array_keys($planData);
sort($sortedLots);

$outVendorQ = $conn->query("
    SELECT *
    FROM tlog_transaksi
    WHERE action_type = 'SCAN_OUT_TO_VENDOR'
      AND JSON_UNQUOTE(JSON_EXTRACT(new_data, '$.job_order')) = '$job_order'
    ORDER BY id_log_trans ASC
");

while ($r = $outVendorQ->fetch_assoc()) {
  $data = json_decode($r['new_data'], true);
  $lotsArr = json_decode($data['lot'] ?? '[]', true);
  if (!$lotsArr) continue;

  // Gunakan qty_real — bukan komponen_qty
  $kompArr = json_decode($r['qty_real'] ?? '[]', true);
  if (!is_array($kompArr) || empty($kompArr)) continue;

  foreach ($kompArr as $k) {
    $size = normalizeSize($k['size'] ?? '');
    $komp = (int)($k['komponen'] ?? 0);
    $qty  = (float)($k['qty'] ?? 0);
    if (!$size || $qty <= 0) continue;

    $sisaQty = $qty;
    if (empty($planTotal)) continue;

    // 🔁 Distribusi FIFO antar LOT
    foreach ($sortedLots as $lot) {
      $planLot = (float)($planTotal[$lot][$size] ?? 0);
      if ($planLot <= 0) continue;

      $sudahKeluar = $outVendorData[$lot][$size][$komp] ?? 0;
      $defisit = $planLot - $sudahKeluar;
      if ($defisit <= 0) continue;

      $ambil = min($defisit, $sisaQty);
      $outVendorData[$lot][$size][$komp] = $sudahKeluar + $ambil;

      $sisaQty -= $ambil;
      if ($sisaQty <= 0) break;
    }
  }
}

// 🔧 7️⃣ SCAN_OUT_TO_PRODUCTION — dibuat IDENTIK dengan Vendor
$outProdData = [];
$defisitData = [];

$sortedLots = array_keys($planData);
sort($sortedLots);

$outProdQ = $conn->query("
    SELECT *
    FROM tlog_transaksi
    WHERE action_type = 'SCAN_OUT_TO_PRODUCTION'
      AND JSON_UNQUOTE(JSON_EXTRACT(new_data, '$.job_order')) = '$job_order'
    ORDER BY id_log_trans ASC
");

while ($r = $outProdQ->fetch_assoc()) {

  // --- 1) Decode new_data ---
  $data = json_decode($r['new_data'], true);
  $lotsArr = json_decode($data['lot'] ?? '[]', true);
  if (!$lotsArr) continue;

  // --- 3) Ambil qty_real (WAJIB, sama dengan Vendor) ---
  $kompArr = json_decode($r['qty_real'] ?? '[]', true);
  if (!is_array($kompArr) || empty($kompArr)) continue;


  // --- 4) Loop qty_real ---
  foreach ($kompArr as $k) {

    // Normalisasi size → WAJIB 3 DIGIT
    $size = normalizeSize($k['size'] ?? '');
    $komp = (int)($k['komponen'] ?? 0);
    $qty  = (float)($k['qty'] ?? 0);
    if (!$size || $qty <= 0) continue;

    $sisaQty = $qty;
    if (empty($planTotal)) continue;

    // --- 5) Distribusi FIFO antar LOT (IDENTIK dengan VENDOR) ---
    foreach ($sortedLots as $lot) {

      $planLot = (float)($planTotal[$lot][$size] ?? 0);
      if ($planLot <= 0) continue;

      $sudahKeluar = $outProdData[$lot][$size][$komp] ?? 0;
      $defisit = $planLot - $sudahKeluar;
      if ($defisit <= 0) continue;

      $ambil = min($defisit, $sisaQty);

      $outProdData[$lot][$size][$komp] = $sudahKeluar + $ambil;

      $sisaQty -= $ambil;
      if ($sisaQty <= 0) break;
    }
  }
}

// ========================================================
// 6️⃣ SCAN_IN_INCOMING – Distribusi FIFO per komponen & size
// ========================================================
$incomingData = [];
$sortedLots = array_keys($planData);
sort($sortedLots);

$incomingQ = $conn->query("
  SELECT *
  FROM tlog_transaksi
  WHERE action_type = 'SCAN_IN_INCOMING'
    AND JSON_UNQUOTE(JSON_EXTRACT(new_data, '$.job_order')) = '$job_order'
  ORDER BY id_log_trans ASC
");

// Inisialisasi agar aman jika tidak ada transaksi sama sekali
$incomingPerTrans = []; // incomingPerTrans[komp][size] = array of actual qty per transaction
$planPerSize = [];      // planPerSize[komp][size] = total plan per komponen-size

// Pastikan $incomingData minimal ada agar bagian lain tidak error
$incomingData = $incomingData ?? [];


while ($r = $incomingQ->fetch_assoc()) {

  // ambil JSON
  $newData = json_decode($r['new_data'] ?? '{}', true);
  if (!is_array($newData)) continue;

  // --- Normalisasi LOT: samakan dengan OUT PROD ---
  $rawLot = $newData['lot'] ?? '[]';

  // Jika LOT masih string JSON → decode
  $lotsArr = json_decode($rawLot, true);

  // Jika decode gagal & format berupa "[1,2,3]" atau "1,2,3"
  if (!is_array($lotsArr)) {
    if (preg_match('/^\[?(\d+(,\d+)*)\]?$/', $rawLot)) {
      $clean = trim($rawLot, "[]");
      $lotsArr = explode(",", $clean);
    }
  }

  // Fallback
  if (!is_array($lotsArr)) $lotsArr = [];

  // Normalisasi index
  $lotsArr = array_values($lotsArr);

  if (empty($lotsArr)) continue;


  $rawKomp = $newData['komponen_qty'] ?? [];
  $kompArr = is_string($rawKomp) ? json_decode($rawKomp, true) : $rawKomp;
  if (!is_array($kompArr)) $kompArr = [];

  $rawReal = $r['qty_real'] ?? [];
  $qtyRealArr = is_string($rawReal) ? json_decode($rawReal, true) : $rawReal;
  if (!is_array($qtyRealArr)) $qtyRealArr = [];

  $statusTrans = strtolower(trim($r['status_kekurangan'] ?? ''));

  // ========================================================
  // 🔁 LOOP per komponen & size di transaksi ini
  // ========================================================
  foreach ($kompArr as $item) {

    $komp = (int)($item['komponen'] ?? 0);
    $size = normalizeSize($item['size'] ?? '');
    $qtyNew  = (float)($item['qty'] ?? 0);

    if ($komp <= 0 || !$size) continue;

    // ambil qty_real kalau ada
    $qtyReal = 0;
    foreach ($qtyRealArr as $x) {
      if ((int)$x['komponen'] === $komp && normalizeSize($x['size']) === $size) {
        $qtyReal = (float)$x['qty'];
        break;
      }
    }

    // total plan utk komponen-size ini
    $planTotal = 0;
    foreach ($sortedLots as $lot) {
      $planTotal += (float)($planData[$lot][$size][$komp] ?? 0);
    }

    // ========================================================
    // 🎯 NEW LOGIC: status pending per size
    // ========================================================
    // aturan:
    // - qtyReal hanya dipakai jika transaksi confirmed
    // - qtyNew dipakai jika pending
    // - jika qty >= planSize → dianggap fully confirmed walaupun transaksi pending
    // - sisanya dianggap pending
    // ========================================================

    $actual = ($statusTrans === 'confirmed') ? $qtyReal : $qtyNew;

    // hitung total actual di transaksi ini
    // actualQty tidak bisa langsung dipakai distribusi → digabung nanti
    $incomingPerTrans[$komp][$size][] = $actual;

    // simpan plan per size
    $planPerSize[$komp][$size] = $planTotal;
  }
}

// ========================================================
// AKUMULASI TOTAL QTY PER SIZE (aman jika tidak ada transaksi)
// ========================================================
$totalActual = [];

if (!is_array($incomingPerTrans)) $incomingPerTrans = [];
if (!is_array($planPerSize)) $planPerSize = [];
// pastikan incomingPerTrans adalah array sebelum sum
if (is_array($incomingPerTrans) && count($incomingPerTrans) > 0) {
  $totalActual = [];

  foreach ($incomingPerTrans as $komp => $sizes) {
    if (!is_array($sizes)) continue;
    foreach ($sizes as $size => $arr) {
      $totalActual[$komp][$size] = is_array($arr) ? array_sum($arr) : 0;
    }
  }
}

// ========================================================
// HITUNG CONFIRMED & PENDING PER SIZE (aman jika planPerSize/totalActual kosong)
// ========================================================
$confirmedQty = [];
$pendingQty = [];
if (!is_array($planPerSize)) $planPerSize = [];

if (is_array($planPerSize) && count($planPerSize) > 0) {
  foreach ($planPerSize as $komp => $sizes) {
    if (!is_array($sizes)) continue;
    foreach ($sizes as $size => $planTotal) {
      $actual = $totalActual[$komp][$size] ?? 0;

      if ($actual >= $planTotal) {
        // fully confirmed
        $confirmedQty[$komp][$size] = $planTotal;
        $pendingQty[$komp][$size] = 0;
      } else {
        // partial
        $confirmedQty[$komp][$size] = $actual;
        $pendingQty[$komp][$size] = max(0, $planTotal - $actual);
      }
    }
  }
}

// ========================================================
// DISTRIBUSI FIFO CONFIRMED KE LOT
// ========================================================
foreach ($confirmedQty as $komp => $sizes) {
  foreach ($sizes as $size => $qtyConf) {

    $sisa = $qtyConf;

    foreach ($sortedLots as $lot) {
      $planLot = (float)($planData[$lot][$size][$komp] ?? 0);
      if ($planLot <= 0) continue;

      $fill = min($sisa, $planLot);
      if ($fill <= 0) break;

      $incomingData[$lot][$size][$komp] = 0;

      $sisa -= $fill;
    }
  }
}

// ========================================================
// PENDING REVERSE FIFO (dihuungkan dulu ke LOT terakhir)
// ========================================================
foreach ($pendingQty as $komp => $sizes) {
  foreach ($sizes as $size => $pendingTotal) {

    if ($pendingTotal <= 0) continue;

    // reverse LOT → mulai dari LOT paling akhir
    $reverseLots = array_reverse($sortedLots);

    $sisaPending = $pendingTotal;

    foreach ($reverseLots as $lot) {

      $planLot = (float)($planData[$lot][$size][$komp] ?? 0);
      if ($planLot <= 0) continue;

      if ($sisaPending <= 0) {
        // sisa pending habis → lot tidak minus
        if (!isset($incomingData[$lot][$size][$komp])) {
          $incomingData[$lot][$size][$komp] = 0;
        }
        continue;
      }

      // alokasi REVERSE FIFO
      if ($sisaPending >= $planLot) {
        // full minus
        $incomingData[$lot][$size][$komp] = -$planLot;
        $sisaPending -= $planLot;
      } else {
        // sebagian minus
        $incomingData[$lot][$size][$komp] = -$sisaPending;
        $sisaPending = 0;
      }
    }
  }
}

// ========================================================
// Distribusi total scan antar LOT (alokasi FIFO per komponen & size)
// ========================================================
$tableData = [];

foreach ($komponenList as $komponen) {
  $kompId = (string)($komponen['id'] ?? $komponen['nama']);

  foreach ($officialSizes as $sizeKey) {
    $sizeKey = normalizeSize($sizeKey);

    // Hitung total per LOT
    $totalInWh = $totalOutVendor = $totalIncomingConfirmed = $totalIncomingPending = $totalOutProd = 0;

    foreach ($lots as $lotRow) {
      $lot = (string)$lotRow['lot'];

      $totalInWh += (float)($inData[$lot][$sizeKey][$kompId] ?? 0);
      $totalOutVendor += (float)($outVendorData[$lot][$sizeKey][$kompId] ?? 0);

      $val = $incomingData[$lot][$sizeKey][$kompId] ?? -(float)($planData[$lot][$sizeKey][$kompId] ?? 0);
      $totalIncomingConfirmed += max(0, $val);
      $totalIncomingPending += min(0, $val);

      // OUT TO PROD dari outProdData
      $totalOutProd += (float)($outProdData[$lot][$sizeKey][$kompId] ?? 0);
    }

    // Variabel bantu FIFO
    $sisaInWh = $totalInWh;
    $sisaOutVendor = $totalOutVendor;
    $sisaOutProd = $totalOutProd;

    foreach ($lots as $lotRow) {
      $lot = (string)$lotRow['lot'];
      $planLot = (float)($planData[$lot][$sizeKey][$kompId] ?? 0);

      // ===== IN WH FIFO =====
      $inWhComponents = $tableData[$lot][$sizeKey]['in'] ?? [];
      if ($planLot > 0) {
        if ($sisaInWh >= $planLot) {
          $inWhComponents[$kompId] = 0;
          $sisaInWh -= $planLot;
        } else {
          $inWhComponents[$kompId] = - ($planLot - $sisaInWh);
          $sisaInWh = 0;
        }
      } else {
        $inWhComponents[$kompId] = 0;
      }

      // ===== OUT TO VENDOR FIFO =====
      $outVendorComponents = $tableData[$lot][$sizeKey]['wh_vendor'] ?? [];
      if ($planLot > 0) {
        if ($sisaOutVendor >= $planLot) {
          $outVendorComponents[$kompId] = 0;
          $sisaOutVendor -= $planLot;
        } else {
          $outVendorComponents[$kompId] = - ($planLot - $sisaOutVendor);
          $sisaOutVendor = 0;
        }
      } else {
        $outVendorComponents[$kompId] = 0;
      }

      // ===== INCOMING =====
      $incomingComponents = $tableData[$lot][$sizeKey]['incoming'] ?? [];
      $val = $incomingData[$lot][$sizeKey][$kompId] ?? -(float)($planData[$lot][$sizeKey][$kompId] ?? 0);
      $incomingComponents[$kompId] = [
        'confirmed' => max(0, $val),
        'pending'   => min(0, $val),
      ];

      // ===== OUT TO PROD FIFO (HARUS 1:1 DENGAN OUT TO VENDOR) =====
      $outComponents = $tableData[$lot][$sizeKey]['out'] ?? [];

      if ($planLot > 0) {
        if ($sisaOutProd >= $planLot) {
          // full cover → tidak minus
          $outComponents[$kompId] = 0;
          $sisaOutProd -= $planLot;
        } else {
          // partial → minus kekurangan
          $shortage = $planLot - $sisaOutProd;
          $outComponents[$kompId] = -$shortage;
          $sisaOutProd = 0;
        }
      } else {
        $outComponents[$kompId] = 0;
      }

      // Simpan ke tableData
      $tableData[$lot][$sizeKey] = [
        'plan'      => (int)$planLot,
        'in'        => $inWhComponents,
        'wh_vendor' => $outVendorComponents,
        'incoming'  => $incomingComponents,
        'out'       => $outComponents,
      ];
    }
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
  $page = 'lot_basis';
  include_once __DIR__ . '/../includes/header.php';
  ?>
  <!-- End Header -->

  <main id="main" class="main">

    <div class="pagetitle text-black" style="background-color: #f0e6d2; padding: 10px 20px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
      <h1 style="font-size: 1.8rem; font-weight: 700; font-family: 'Roboto', sans-serif;">
        Details Lot Basis
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

                <hr>
                <p class="text-success">Description data per cell</p>
                <p class="text-success fs-4">[ PLAN | IN WH | WH TO VENDOR | INCOMING | OUT TO PROD ]</p>

                <?php foreach ($komponenList as $komponen): ?>
                  <?php $komponenName = $komponen['nama']; ?>
                  <h5 class="mt-4 fw-bold">Komponen: <?= htmlspecialchars($komponenName) ?></h5>

                  <div style="overflow-x:auto;">
                    <table class="table table-bordered text-center align-middle" style="min-width: 1000px;">
                      <thead class="table-light">
                        <tr>
                          <th style="white-space: nowrap;">LOT</th>
                          <?php foreach ($officialSizes as $size): ?>
                            <th style="white-space: nowrap;"><?= htmlspecialchars($size) ?></th>
                          <?php endforeach; ?>
                          <th style="white-space: nowrap;">Total</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php
                        $grandPlan = $grandIn = $grandWhVendor = $grandIncoming = $grandOut = 0;
                        foreach ($tableData as $lot => $row):
                          $lotPlan = $lotIn = $lotWhVendor = $lotIncoming = $lotOut = 0;
                        ?>
                          <tr>
                            <td class="fw-bold bg-light"><?= htmlspecialchars($lot) ?></td>
                            <?php foreach ($officialSizes as $size):
                              $d = $row[$size] ?? [
                                'plan' => 0,
                                'in' => [],
                                'wh_vendor' => [],
                                'incoming' => [],
                                'out' => []
                              ];

                              $kompKey = (string)($komponen['id'] ?? $komponenName);

                              // --- PLAN ---
                              $plan = (float)($d['plan'] ?? 0);

                              // --- IN WH ---
                              $inQty = (float)($d['in'][$kompKey] ?? 0);

                              // --- WH TO VENDOR ---
                              $wh_vendor = (float)($d['wh_vendor'][$kompKey] ?? 0);

                              // --- INCOMING ---
                              $incomingQty = 0;
                              if (isset($d['incoming'][$kompKey])) {
                                $incomingQty = max(0, $d['incoming'][$kompKey]['confirmed'] ?? 0) +
                                  min(0, $d['incoming'][$kompKey]['pending'] ?? 0);
                              }

                              // --- OUT TO PROD (gunakan persis seperti OUT TO VENDOR) ---
                              $out = (float)($d['out'][$kompKey] ?? 0);

                              // --- Akumulasi per LOT ---
                              $lotPlan += $plan;
                              $lotIn   += $inQty;
                              $lotWhVendor += $wh_vendor;
                              $lotIncoming += $incomingQty;
                              $lotOut  += $out;

                              $isEmpty = ($plan == 0 && $inQty == 0 && $wh_vendor == 0 && $incomingQty == 0 && $out == 0);
                              $cellClass = $isEmpty ? "bg-dark text-white" : "";
                            ?>
                              <td class="<?= $cellClass ?>" style="white-space: nowrap;">
                                <?php if (!$isEmpty): ?>
                                  <span class="text-success"><?= $plan ?></span> |
                                  <span class="<?= $inQty < 0 ? 'text-danger' : 'text-success' ?>"><?= $inQty ?></span> |
                                  <span class="<?= $wh_vendor < 0 ? 'text-danger' : 'text-success' ?>"><?= $wh_vendor ?></span> |
                                  <span class="<?= $incomingQty < 0 ? 'text-danger' : 'text-success' ?>"><?= $incomingQty ?></span> |
                                  <span class="<?= $out < 0 ? 'text-danger' : 'text-success' ?>"><?= $out ?></span>
                                <?php endif; ?>
                              </td>
                            <?php endforeach; ?>
                            <td class="fw-bold bg-light" style="white-space: nowrap;">
                              <span class="text-success"><?= $lotPlan ?></span> |
                              <span class="<?= $lotIn < 0 ? 'text-danger' : 'text-success' ?>"><?= $lotIn ?></span> |
                              <span class="<?= $lotWhVendor < 0 ? 'text-danger' : 'text-success' ?>"><?= $lotWhVendor ?></span> |
                              <span class="<?= $lotIncoming < 0 ? 'text-danger' : 'text-success' ?>"><?= $lotIncoming ?></span> |
                              <span class="<?= $lotOut < 0 ? 'text-danger' : 'text-success' ?>"><?= $lotOut ?></span>
                            </td>
                          </tr>
                        <?php
                          $grandPlan += $lotPlan;
                          $grandIn   += $lotIn;
                          $grandWhVendor += $lotWhVendor;
                          $grandIncoming += $lotIncoming;
                          $grandOut  += $lotOut;
                        endforeach;
                        ?>

                        <!-- Total keseluruhan -->
                        <tr class="fw-bold table-light">
                          <td>Total</td>
                          <?php foreach ($officialSizes as $size):
                            $sumPlan = $sumIn = $sumWhVendor = $sumIncoming = $sumOut = 0;
                            foreach ($tableData as $lot => $row) {
                              $d = $row[$size] ?? ['plan' => 0, 'in' => [], 'wh_vendor' => [], 'incoming' => [], 'out' => []];
                              $kompKey = (string)($komponen['id'] ?? $komponenName);
                              $sumPlan += (float)($d['plan'] ?? 0);
                              $sumIn += (float)($d['in'][$kompKey] ?? 0);
                              $sumWhVendor += (float)($d['wh_vendor'][$kompKey] ?? 0);
                              $incomingVal = 0;
                              if (isset($d['incoming'][$kompKey])) {
                                $incomingVal = max(0, $d['incoming'][$kompKey]['confirmed'] ?? 0) +
                                  min(0, $d['incoming'][$kompKey]['pending'] ?? 0);
                              }
                              $sumIncoming += $incomingVal;
                              $sumOut += (float)($d['out'][$kompKey] ?? 0);
                            }
                            $inClass  = ($sumIn < 0) ? 'text-danger' : 'text-success';
                            $whVendorClass = ($sumWhVendor < 0) ? 'text-danger' : 'text-success';
                            $incomingClass = ($sumIncoming < 0) ? 'text-danger' : 'text-success';
                            $outClass = ($sumOut < 0) ? 'text-danger' : 'text-success';
                          ?>
                            <td style="white-space: nowrap;">
                              <span class="text-success"><?= $sumPlan ?></span> |
                              <span class="<?= $inClass ?>"><?= $sumIn ?></span> |
                              <span class="<?= $whVendorClass ?>"><?= $sumWhVendor ?></span> |
                              <span class="<?= $incomingClass ?>"><?= $sumIncoming ?></span> |
                              <span class="<?= $outClass ?>"><?= $sumOut ?></span>
                            </td>
                          <?php endforeach; ?>
                          <td style="white-space: nowrap;">
                            <span class="text-success"><?= $grandPlan ?></span> |
                            <span class="<?= ($grandIn < 0 ? 'text-danger' : 'text-success') ?>"><?= $grandIn ?></span> |
                            <span class="<?= ($grandWhVendor < 0 ? 'text-danger' : 'text-success') ?>"><?= $grandWhVendor ?></span> |
                            <span class="<?= ($grandIncoming < 0 ? 'text-danger' : 'text-success') ?>"><?= $grandIncoming ?></span> |
                            <span class="<?= ($grandOut < 0 ? 'text-danger' : 'text-success') ?>"><?= $grandOut ?></span>
                          </td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                <?php endforeach; ?>

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

</body>

</html>