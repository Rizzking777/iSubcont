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

// Normalisasi size
function normalizeSize($s)
{
  return strtoupper(trim($s));
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
      SELECT id_komponen, nama_komponen
      FROM tbl_komponen
      WHERE model = '{$modelForKomponen}' AND is_deleted = 0
      ORDER BY nama_komponen ASC
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

// 🧩 Ambil data transaksi aktif dari tlog_transaksi (CREATE_BARCODE terakhir)
$transQ = $conn->query("
  SELECT new_data
  FROM tlog_transaksi
  WHERE JSON_UNQUOTE(JSON_EXTRACT(new_data,'$.job_order')) = '{$job_order}'
    AND JSON_UNQUOTE(JSON_EXTRACT(new_data,'$.type_scan')) = 'CREATE_BARCODE'
 
  LIMIT 1
");

$transaksiData = [];
if ($transQ && $transQ->num_rows > 0) {
  $rT = $transQ->fetch_assoc();
  $transaksiData = json_decode($rT['new_data'], true);
}

// ✅ Ambil ID komponen dari transaksi aktif
$komponenIDsInTrans = [];
if (!empty($transaksiData['komponen_qty'])) {
  foreach ($transaksiData['komponen_qty'] as $kq) {
    if (!empty($kq['komponen'])) {
      $komponenIDsInTrans[] = (int)$kq['komponen'];
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

// 3️⃣ PLAN DATA
$planData = [];
$planQuery = $conn->query("
  SELECT lot, size, SUM(qty) AS total_plan
  FROM tbl_master_data
  WHERE job_order = '$job_order'
  GROUP BY lot, size
");
while ($r = $planQuery->fetch_assoc()) {
  $planData[$r['lot']][normalizeSize($r['size'])] = (int)$r['total_plan'];
}

// 4️⃣ SCAN_IN_WAREHOUSE (IN WH)
$inData = [];
$defisitData = []; // defisit kumulatif per komponen per lot per size

$inQ = $conn->query("
    SELECT new_data FROM tlog_transaksi
    WHERE JSON_UNQUOTE(JSON_EXTRACT(new_data,'$.job_order'))='$job_order'
      AND JSON_UNQUOTE(JSON_EXTRACT(new_data,'$.type_scan'))='SCAN_IN_WAREHOUSE'
    ORDER BY id_trans ASC
");

while ($r = $inQ->fetch_assoc()) {
    $data = json_decode($r['new_data'], true);
    $lotsArr = json_decode($data['lot'] ?? '[]', true);
    $kompArr = json_decode($data['komponen_qty'] ?? '[]', true);
    if (!$lotsArr || !$kompArr) continue;

    echo "<hr><strong>Transaksi ID {$data['id_trans']}</strong><br>";

    foreach ($kompArr as $k) {
        $size = normalizeSize($k['size'] ?? '');
        $qty  = (int)($k['qty'] ?? 0);
        $komp = (int)($k['komponen'] ?? 0);
        if (!$size) continue;

        foreach ($lotsArr as $lot) {
            // Ambil planData dengan aman, default 0 kalau ga ada
            $planLotKomponen = (int)($planData[$lot][$size][$komp] ?? $planData[$lot][$size] ?? 0);
            if ($planLotKomponen <= 0) continue;

            // Ambil defisit sekarang, kalau belum ada inisialisasi pakai planData
            $defisitSisa = $defisitData[$lot][$size][$komp] ?? $planLotKomponen;

            // 🔹 DEBUG sebelum Take
            echo "[DEBUG] Lot: $lot | Size: $size | Komp: $komp | Qty scan: $qty | Defisit sebelum: $defisitSisa<br>";

            // Ambil sebanyak mungkin dari scan tapi tidak lebih dari defisit
            $take = min($defisitSisa, $qty);

            // Simpan hasil scan
            $inData[$lot][$size][$komp] = ($inData[$lot][$size][$komp] ?? 0) + $take;
            $defisitData[$lot][$size][$komp] = $defisitSisa - $take;

            // 🔹 DEBUG setelah Take
            echo "[DEBUG] Lot: $lot | Size: $size | Komp: $komp | Take: $take | Defisit setelah: {$defisitData[$lot][$size][$komp]}<br>";
        }
    }
}

// 🔹 Debug kumulatif defisit setelah semua transaksi
echo "<hr><strong>Hasil kumulatif semua transaksi:</strong><br>";
foreach ($defisitData as $lot => $sizesArr) {
    foreach ($sizesArr as $size => $kompArr) {
        foreach ($kompArr as $komp => $deficit) {
            echo "Lot: $lot | Size: $size | Komp: $komp | Defisit akhir: $deficit<br>";
        }
    }
}


// 🔧 5️⃣ SCAN_OUT_TO_VENDOR (WH TO VENDOR)
$outVendorData = [];
$outVendorQ = $conn->query("
  SELECT new_data FROM tlog_transaksi
  WHERE JSON_UNQUOTE(JSON_EXTRACT(new_data,'$.job_order'))='$job_order'
    AND JSON_UNQUOTE(JSON_EXTRACT(new_data,'$.type_scan'))='SCAN_OUT_TO_VENDOR'
");

while ($r = $outVendorQ->fetch_assoc()) {
  $data = json_decode($r['new_data'], true);
  $lotsArr = json_decode($data['lot'] ?? '[]', true);
  $kompArr = json_decode($data['komponen_qty'] ?? '[]', true);
  if (!$lotsArr || !$kompArr) continue;

  foreach ($lotsArr as $lot) {
    foreach ($kompArr as $k) {
      $size = normalizeSize($k['size'] ?? '');
      $qty  = (int)($k['qty'] ?? 0);
      if (!$size || $qty <= 0) continue;

      // 🧩 hanya proses jika lot ini punya plan / data inScan
      $hasPlan = isset($planData[$lot][$size]) && $planData[$lot][$size] > 0;
      $hasIn   = isset($inData[$lot][$size]) && $inData[$lot][$size] > 0;

      if (!$hasPlan && !$hasIn) continue; // ❌ skip lot kosong tanpa plan/in-scan

      // ✅ baru tambahkan kalau lolos filter
      $outVendorData[$lot][$size] = ($outVendorData[$lot][$size] ?? 0) + $qty;
    }
  }
}

// 🧩 Pastikan LOTS berbentuk array
$lotsArr = [];
if (isset($lots)) {
  if ($lots instanceof mysqli_result) {
    while ($r = $lots->fetch_assoc()) {
      $lotsArr[] = $r;
    }
  } elseif (is_array($lots)) {
    $lotsArr = $lots;
  }
}
$lots = $lotsArr; // sekarang $lots pasti array


// 🔧 6️⃣ SCAN_IN_INCOMING (INCOMING) - versi distribusi per lot sesuai urutan (lot terakhir dulu)
$incomingData = [];
$incomingQ = $conn->query("
  SELECT 
    JSON_UNQUOTE(JSON_EXTRACT(new_data,'$.komponen_qty')) AS komponen_json,
    JSON_UNQUOTE(JSON_EXTRACT(new_data,'$.lot')) AS lot_json
  FROM tlog_transaksi
  WHERE JSON_UNQUOTE(JSON_EXTRACT(new_data,'$.job_order'))='$job_order'
    AND JSON_UNQUOTE(JSON_EXTRACT(new_data,'$.type_scan'))='SCAN_IN_INCOMING'
");

while ($r = $incomingQ->fetch_assoc()) {
  $kompArr = json_decode($r['komponen_json'], true) ?: [];
  $lotArr = json_decode($r['lot_json'], true) ?: [];

  foreach ($kompArr as $k) {
    $size = normalizeSize($k['size'] ?? '');
    $qty  = (int)($k['qty'] ?? 0);
    if ($size === '' || $qty <= 0) continue;

    // 🔹 Tentukan lot target
    $targetLots = !empty($lotArr) ? $lotArr : array_column($lots, 'lot');

    // 🧮 Distribusi qty ke lot aktif (mulai dari lot terakhir)
    for ($i = count($targetLots) - 1; $i >= 0 && $qty > 0; $i--) {
      $lot = (string)$targetLots[$i];
      $planQty = (int)($planData[$lot][$size] ?? 0);
      if ($planQty <= 0) continue;

      $fill = min($planQty, $qty);
      $incomingData[$lot][$size] = ($incomingData[$lot][$size] ?? 0) + $fill;
      $qty -= $fill;
    }

    // ⚠️ Jika qty masih sisa tapi semua lot sudah penuh, masukkan ke lot terakhir (fallback)
    if ($qty > 0 && !empty($targetLots)) {
      $lastLot = (string)end($targetLots);
      $incomingData[$lastLot][$size] = ($incomingData[$lastLot][$size] ?? 0) + $qty;
    }
  }
}

// 🔧 7 CONFIRM_KEKURANGAN - kurangi incoming sesuai pending kekurangan 
$kekuranganData = [];
$kekQ = $conn->query("
  SELECT new_data
  FROM tlog_transaksi
  WHERE action_type='CONFIRM_KEKURANGAN'
    AND JSON_UNQUOTE(JSON_EXTRACT(new_data,'$.job_order'))='$job_order'
");

while ($r = $kekQ->fetch_assoc()) {
  $newData = json_decode($r['new_data'], true);
  if (!$newData) continue;

  // 🧠 Filter last_gate
  $lastGate = strtoupper(trim($newData['last_gate'] ?? ''));
  if ($lastGate !== 'SCAN_IN_INCOMING') continue;

  // 🧩 Pastikan komponen_qty terbaca meskipun double-encoded
  $kompArrRaw = $newData['komponen_qty'] ?? '[]';
  if (is_string($kompArrRaw)) {
    $kompArr = json_decode($kompArrRaw, true);
    if (is_string($kompArr)) {
      $kompArr = json_decode($kompArr, true);
    }
  } else {
    $kompArr = $kompArrRaw;
  }
  $kompArr = is_array($kompArr) ? $kompArr : [];
  $status = strtolower(trim($newData['status'] ?? ''));

  foreach ($kompArr as $k) {
    $size = normalizeSize($k['size'] ?? '');
    $qty  = (int)($k['kekurangan'] ?? $k['qty'] ?? 0);
    if ($size === '' || $qty <= 0) continue;

    if ($status === 'confirmed') {
      // ✅ sudah terpenuhi, semua lot dianggap 0 untuk size ini
      foreach ($lots as $lotRow) {
        $lot = (string)$lotRow['lot'];
        if (!isset($planData[$lot][$size])) continue;
        $incomingData[$lot][$size] = 0;
      }
    } elseif ($status === 'pending') {
      // ⚠️ reset dulu semua lot untuk size ini supaya distribusi akurat
      foreach ($lots as $lotRow) {
        $lot = (string)$lotRow['lot'];
        if (!isset($planData[$lot][$size])) continue;
        $incomingData[$lot][$size] = 0;
      }

      $sisa = $qty;
      foreach ($lots as $lotRow) {
        $lot = (string)$lotRow['lot'];
        if (!isset($planData[$lot][$size])) continue;
        if ($sisa <= 0) break;

        $planLot = (int)$planData[$lot][$size];
        $kurang = min($sisa, $planLot);
        $incomingData[$lot][$size] -= $kurang;
        $sisa -= $kurang;
      }

      foreach ($lots as $lotRow) {
        $lot = (string)$lotRow['lot'];
        if (!isset($planData[$lot][$size])) continue;

        $curIncoming = (int)($incomingData[$lot][$size] ?? 0);
        $planLot     = (int)($planData[$lot][$size]);

        if ($sisa <= 0) break;

        // distribusi kekurangan: min(planLot, sisa)
        $kurang = min($planLot, $sisa);
        $incomingData[$lot][$size] = $curIncoming - $kurang;
        $sisa -= $kurang;
      }

      // kalau sisa masih > 0 (total plan lebih kecil dari kekurangan)
      if ($sisa > 0 && !empty($lots)) {
        $firstLot = (string)$lots[0]['lot'];
        $incomingData[$firstLot][$size] -= $sisa;
      }
    }
  }
}

// 🔧 Reset semua size yang tidak disebut di kekurangan menjadi 0 (agar size lain gak bawa nilai lama)
$semuaSize = array_column($sizes, 'size');
$sizeKekurangan = [];

$kekQ3 = $conn->query("
  SELECT JSON_UNQUOTE(JSON_EXTRACT(new_data,'$.komponen_qty')) AS komponen_json
  FROM tlog_transaksi
  WHERE action_type='CONFIRM_KEKURANGAN'
    AND JSON_UNQUOTE(JSON_EXTRACT(new_data,'$.job_order'))='$job_order'
");

while ($r3 = $kekQ3->fetch_assoc()) {
  $arr3 = json_decode($r3['komponen_json'], true) ?: [];
  foreach ($arr3 as $k3) {
    $key = normalizeSize($k3['size'] ?? '');
    if ($key !== '') $sizeKekurangan[$key] = true;
  }
}

foreach ($incomingData as $lot => &$sizeArr) {
  foreach ($semuaSize as $s) {
    if (empty($sizeKekurangan[$s]) && isset($sizeArr[$s])) {
      $sizeArr[$s] = 0; // reset yang tidak disebut di kekurangan
    }
  }
}
unset($sizeArr);

// 🧹 Tambahan: reset size yang tidak ada di kekurangan atau plan agar tidak tampil angka lama
$allKekuranganSizes = [];
$kekQ2 = $conn->query("
  SELECT JSON_UNQUOTE(JSON_EXTRACT(new_data,'$.komponen_qty')) AS komponen_json
  FROM tlog_transaksi
  WHERE action_type='CONFIRM_KEKURANGAN'
    AND JSON_UNQUOTE(JSON_EXTRACT(new_data,'$.job_order'))='$job_order'
");

while ($r2 = $kekQ2->fetch_assoc()) {
  $arr = json_decode($r2['komponen_json'], true) ?: [];
  foreach ($arr as $k2) {
    $sizeKey = normalizeSize($k2['size'] ?? '');
    if ($sizeKey !== '') $allKekuranganSizes[$sizeKey] = true;
  }
}

foreach ($incomingData as $lot => &$sizesArr) {
  foreach ($sizesArr as $sizeKey => &$val) {
    // kalau size ini gak ada di kekurangan dan gak ada plan, reset ke 0
    if (empty($allKekuranganSizes[$sizeKey]) && empty($planData[$lot][$sizeKey])) {
      $val = 0;
    }
  }
}
unset($val, $sizesArr);

// 🧹 Bersihkan incomingData dari lot yang gak relevan
foreach ($incomingData as $lot => $sizesArr) {
  if (empty($planData[$lot]) && empty($inData[$lot])) {
    unset($incomingData[$lot]);
  }
}

// 🔧 7️⃣ SCAN_OUT_TO_PRODUCTION (OUT TO PROD)
$outProdData = [];
$outProdQ = $conn->query("
    SELECT 
        new_data
    FROM tlog_transaksi
    WHERE JSON_UNQUOTE(JSON_EXTRACT(new_data,'$.job_order'))='$job_order'
      AND JSON_UNQUOTE(JSON_EXTRACT(new_data,'$.type_scan'))='SCAN_OUT_TO_PRODUCTION'
");

while ($r = $outProdQ->fetch_assoc()) {
  $newData = json_decode($r['new_data'], true);
  if (!$newData) continue;

  $lotsArr = $newData['lot'] ?? [];
  $kompArrRaw = $newData['komponen_qty'] ?? [];

  if (is_string($kompArrRaw)) {
    $kompArr = json_decode($kompArrRaw, true);
    if (is_string($kompArr)) $kompArr = json_decode($kompArr, true);
  } else {
    $kompArr = $kompArrRaw;
  }
  $kompArr = is_array($kompArr) ? $kompArr : [];
  $lotsArr = is_array($lotsArr) ? $lotsArr : [];

  foreach ($lotsArr as $lot) {
    $lotKey = (string)$lot;
    foreach ($kompArr as $k) {
      $size = normalizeSize($k['size'] ?? '');
      $qty  = (int)($k['qty'] ?? 0); // biasanya qty di sini adalah kekurangan
      if ($size === '' || $qty <= 0) continue;

      $outProdData[$lotKey][$size] = ($outProdData[$lotKey][$size] ?? 0) + $qty;
    }
  }
}

// 🔧 CONFIRM_KEKURANGAN untuk OUT TO PROD (kurangi pending kekurangan)
$kekQ = $conn->query("
    SELECT new_data
    FROM tlog_transaksi
    WHERE action_type='CONFIRM_KEKURANGAN'
      AND JSON_UNQUOTE(JSON_EXTRACT(new_data,'$.job_order'))='$job_order'
");

while ($r = $kekQ->fetch_assoc()) {
  $newData = json_decode($r['new_data'], true);
  if (!$newData) continue;

  $lastGate = strtoupper(trim($newData['last_gate'] ?? ''));
  if ($lastGate !== 'SCAN_CHECK_QC') continue;

  $status = strtolower(trim($newData['status'] ?? ''));
  $kompArrRaw = $newData['komponen_qty'] ?? [];
  if (is_string($kompArrRaw)) {
    $kompArr = json_decode($kompArrRaw, true);
    if (is_string($kompArr)) $kompArr = json_decode($kompArr, true);
  } else {
    $kompArr = $kompArrRaw;
  }
  $kompArr = is_array($kompArr) ? $kompArr : [];

  foreach ($kompArr as $k) {
    $size = normalizeSize($k['size'] ?? '');
    $qty  = (int)($k['kekurangan'] ?? $k['qty'] ?? 0);
    if ($size === '' || $qty <= 0) continue;

    if ($status === 'confirmed') {
      foreach ($lots as $lotRow) {
        $lot = (string)$lotRow['lot'];
        if (!isset($planData[$lot][$size])) continue;
        $outProdData[$lot][$size] = 0;
      }
    } elseif ($status === 'pending') {
      $sisa = $qty;
      foreach ($lots as $lotRow) {
        $lot = (string)$lotRow['lot'];
        if (!isset($planData[$lot][$size])) continue;

        $planLot = (int)($planData[$lot][$size] ?? 0);
        $cur = (int)($outProdData[$lot][$size] ?? 0);

        if ($sisa <= 0) break;

        $kurang = min($planLot, $sisa);
        $outProdData[$lot][$size] = $cur - $kurang;
        $sisa -= $kurang;
      }

      if ($sisa > 0 && !empty($lots)) {
        $firstLot = (string)$lots[0]['lot'];
        $outProdData[$firstLot][$size] = ($outProdData[$firstLot][$size] ?? 0) - $sisa;
      }
    }
  }
}

// Masukkan semua array scan
$scanTypes = [
  'IN WH' => $inData,
  'WH TO VENDOR' => $outVendorData,
  'INCOMING' => $incomingData,
  'OUT TO PROD' => $outProdData
];

// 🔧 Distribusi total scan antar lot untuk setiap size dan komponen
$tableData = [];

foreach ($sizes as $s) {
  $sizeKey = $s['size'];

  foreach ($lots as $lotRow) {
    $lot = (string)$lotRow['lot'];

    $plan      = (int)($planData[$lot][$sizeKey] ?? 0);
    $outVendor = (int)($outVendorData[$lot][$sizeKey] ?? 0);
    $incoming  = (int)($incomingData[$lot][$sizeKey] ?? 0);
    $outProd   = (int)($outProdData[$lot][$sizeKey] ?? 0);

    // --- Hitung IN WH per komponen berdasarkan komponenList ---
    $inWhComponents = [];
    foreach ($komponenList as $komponen) {
      $kompId = $komponen['id'] ?? $komponen['nama'];
      $planLotKomponen = $planData[$lot][$sizeKey][$kompId] ?? $plan;

      $scanQty = $inData[$lot][$sizeKey][$kompId] ?? 0;

      // defisit = scan kumulatif dikurangi plan per komponen
      $inWhComponents[$kompId] = $scanQty - $planLotKomponen;
      if ($inWhComponents[$kompId] > 0) $inWhComponents[$kompId] = 0; // jangan positif
    }

    // --- Kolom lain tetap sama ---
    $whVendorVal = ($outVendor > 0) ? $outVendor - $plan : -$plan;
    if ($whVendorVal > 0) $whVendorVal = 0;

    $incomingVal = ($incoming > 0) ? $incoming - $plan : -$plan;
    if ($incomingVal > 0) $incomingVal = 0;

    $outProdVal = ($outProd > 0) ? $outProd - $plan : -$plan;
    if ($outProdVal > 0) $outProdVal = 0;

    // --- Hutang tersisa per lot per size ---
    $alokasi = (int)($allocatedMap[$lot] ?? 0);
    $hutang = $plan - $alokasi - $outVendor - $incoming - $outProd;
    if ($hutang < 0) $hutang = 0;

    $tableData[$lot][$sizeKey] = [
      'plan'      => $plan,
      'in'        => $inWhComponents, // per komponen
      'wh_vendor' => (int)$whVendorVal,
      'incoming'  => (int)$incomingVal,
      'out'       => (int)$outProdVal,
      'hutang'    => (int)$hutang
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
                          <?php foreach ($sizes as $s): ?>
                            <th style="white-space: nowrap;"><?= htmlspecialchars($s['size']) ?></th>
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
                            <?php foreach ($sizes as $s):
                              $size = $s['size'];
                              $d = $row[$size] ?? [
                                'plan' => 0,
                                'in' => [],
                                'wh_vendor' => 0,
                                'incoming' => 0,
                                'out' => 0
                              ];

                              $plan = $d['plan'];
                              $inComponents = $d['in'] ?? [];

                              // Ambil hanya komponen yang sesuai
                              $inQty = $inComponents[$komponen['id'] ?? $komponenName] ?? 0;

                              $wh_vendor = $d['wh_vendor'];
                              $incoming  = $d['incoming'];
                              $out  = $d['out'];

                              $lotPlan += $plan;
                              $lotIn   += $inQty;
                              $lotWhVendor += $wh_vendor;
                              $lotIncoming += $incoming;
                              $lotOut  += $out;

                              $isEmpty = ($plan == 0 && $inQty == 0 && $wh_vendor == 0 && $incoming == 0 && $out == 0);
                              $cellClass = $isEmpty ? "bg-dark text-white" : "";
                            ?>
                              <td class="<?= $cellClass ?>" style="white-space: nowrap;">
                                <?php if (!$isEmpty): ?>
                                  <span class="text-success"><?= $plan ?></span> |
                                  <span class="<?= $inQty < 0 ? 'text-danger' : 'text-success' ?>"><?= $inQty ?></span> |
                                  <span class="<?= $wh_vendor < 0 ? 'text-danger' : 'text-success' ?>"><?= $wh_vendor ?></span> |
                                  <span class="<?= $incoming < 0 ? 'text-danger' : 'text-success' ?>"><?= $incoming ?></span> |
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
                          <?php foreach ($sizes as $s):
                            $size = $s['size'];
                            $sumPlan = $sumIn = $sumWhVendor = $sumIncoming = $sumOut = 0;
                            foreach ($tableData as $lot => $row) {
                              $d = $row[$size] ?? [
                                'plan' => 0,
                                'in' => [],
                                'wh_vendor' => 0,
                                'incoming' => 0,
                                'out' => 0
                              ];
                              $sumPlan += $d['plan'];
                              $sumIn += $d['in'][$komponen['id'] ?? $komponenName] ?? 0;
                              $sumWhVendor += $d['wh_vendor'];
                              $sumIncoming += $d['incoming'];
                              $sumOut  += $d['out'];
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