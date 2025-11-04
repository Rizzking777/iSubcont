<?php
require 'function.php';

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Ambil request DataTables
$draw   = $_POST['draw'] ?? 1;
$start  = $_POST['start'] ?? 0;
$length = $_POST['length'] ?? 10;

// Filter
$bucket    = $_POST['bucket'] ?? $_GET['bucket'] ?? '';
$ncvs      = $_POST['ncvs'] ?? $_GET['ncvs'] ?? '';
$po_code   = $_POST['po_code'] ?? $_GET['po_code'] ?? '';
$job_order = $_POST['job_order'] ?? $_GET['job_order'] ?? '';

if (empty($bucket) && empty($ncvs) && empty($po_code) && empty($job_order)) {
  echo json_encode([
    "draw" => intval($draw),
    "recordsTotal" => 0,
    "recordsFiltered" => 0,
    "data" => []
  ]);
  exit;
}

// -----------------------
// Ambil daftar job_order
// -----------------------
$baseSql = "
FROM tbl_master_data m
INNER JOIN (
    SELECT DISTINCT job_order, po_code, po_item, bucket, style, model, ncvs
    FROM tbl_transaksi
) t ON m.job_order = t.job_order
   AND m.po_code = t.po_code
   AND m.po_item = t.po_item
   AND m.bucket = t.bucket
   AND m.style = t.style
   AND m.model = t.model
   AND m.ncvs = t.ncvs
WHERE 1=1
";

$where = [];
$params = [];
$types = "";

if (!empty($bucket)) {
  $where[] = "m.bucket = ?";
  $params[] = $bucket;
  $types .= "s";
}
if (!empty($ncvs)) {
  $where[] = "m.ncvs = ?";
  $params[] = $ncvs;
  $types .= "s";
}
if (!empty($po_code)) {
  $where[] = "m.po_code = ?";
  $params[] = $po_code;
  $types .= "s";
}
if (!empty($job_order)) {
  $where[] = "m.job_order = ?";
  $params[] = $job_order;
  $types .= "s";
}
if (!empty($where)) $baseSql .= " AND " . implode(" AND ", $where);

// Ambil data job_order
$dataJobsQuery = "
SELECT 
    m.job_order,
    MAX(m.ncvs) AS ncvs,
    MAX(m.bucket) AS bucket,
    MAX(m.po_code) AS po_code,
    MAX(m.po_item) AS po_item,
    MAX(m.style) AS style,
    MAX(m.model) AS model,
    SUM(m.qty) AS total_order
" . $baseSql . "
GROUP BY m.job_order
ORDER BY m.job_order ASC
LIMIT ?, ?
";

$paramsJobs = $params;
$typesJobs  = $types . "ii";
$paramsJobs[] = intval($start);
$paramsJobs[] = intval($length);

$stmtJobs = $conn->prepare($dataJobsQuery);
if ($typesJobs) $stmtJobs->bind_param($typesJobs, ...$paramsJobs);
$stmtJobs->execute();
$jobsResult = $stmtJobs->get_result();

// Hitung total job_order
$countQuery = "SELECT COUNT(DISTINCT m.job_order) AS cnt " . $baseSql;
$stmtCount = $conn->prepare($countQuery);
if ($types) $stmtCount->bind_param($types, ...$params);
$stmtCount->execute();
$recordsTotal = intval($stmtCount->get_result()->fetch_assoc()['cnt'] ?? 0);
$stmtCount->close();

$finalData = [];
$vendor_cache = [];

// -----------------------
// Loop job_order
// -----------------------
while ($jobRow = $jobsResult->fetch_assoc()) {
  $job = $jobRow['job_order'];
  $total_order_job = floatval($jobRow['total_order']);

  // Ambil semua komponen unik
  $stmtKomp = $conn->prepare("SELECT komponen_qty FROM tbl_transaksi WHERE job_order = ?");
  $stmtKomp->bind_param("s", $job);
  $stmtKomp->execute();
  $resKomp = $stmtKomp->get_result();

  $komponen_set = [];
  while ($r = $resKomp->fetch_assoc()) {
    $kj = json_decode($r['komponen_qty'] ?? '[]', true);
    if (is_array($kj)) {
      foreach ($kj as $it) {
        $kid = (string)($it['komponen'] ?? '');
        if ($kid === '') continue;
        $komponen_set[$kid] = true;
      }
    }
  }
  $stmtKomp->close();
  if (empty($komponen_set)) $komponen_set = ['-1' => true];

  foreach (array_keys($komponen_set) as $komp_id) {
    // Ambil vendor & nama komponen
    if ($komp_id !== '-1') {
      if (!isset($vendor_cache[$komp_id])) {
        $stmt_k = $conn->prepare("
                    SELECT k.nama_komponen, GROUP_CONCAT(DISTINCT v.name_vendor SEPARATOR ', ') AS vendors
                    FROM tbl_komponen k
                    LEFT JOIN tbl_komponen_proses p ON p.id_input = k.id_komponen OR p.id_output = k.id_komponen
                    LEFT JOIN tbl_vendor_proses vp ON vp.id_proses = p.id_proses
                    LEFT JOIN tbl_vendor v ON v.id_vendor = vp.id_vendor
                    WHERE k.id_komponen = ?
                    GROUP BY k.id_komponen
                ");
        $stmt_k->bind_param("s", $komp_id);
        $stmt_k->execute();
        $vendor_cache[$komp_id] = $stmt_k->get_result()->fetch_assoc() ?: [];
        $stmt_k->close();
      }
      $nama_komponen = $vendor_cache[$komp_id]['nama_komponen'] ?? $komp_id;
      $vendor_names = $vendor_cache[$komp_id]['vendors'] ?? '-';
    } else {
      $nama_komponen = '-';
      $vendor_names = '-';
    }

    // Ambil log transaksi job_order
    $stmtLog = $conn->prepare("
    SELECT action_type, qty_real, qty_kekurangan, status_kekurangan
    FROM tlog_transaksi
    WHERE JSON_UNQUOTE(JSON_EXTRACT(new_data, '$.job_order')) = ?
    ORDER BY created_at ASC, id_log_trans ASC
");
    $stmtLog->bind_param("s", $job);
    $stmtLog->execute();
    $resLog = $stmtLog->get_result();

    // Init counters
    $in_wh = $out_vendor = $in_incoming = $out_prod = 0;
    $scan_qc_found = false;
    $scan_qc_logs = [];
    $scan_out_logs = [];

    while ($log = $resLog->fetch_assoc()) {
      $action = $log['action_type'];
      $qty_real_list = json_decode($log['qty_real'] ?? '[]', true);
      if (!is_array($qty_real_list)) $qty_real_list = [];

      // Ambil kekurangan jika status pending
      $qty_kekurangan_list = [];
      if (strtolower(trim($log['status_kekurangan'] ?? '')) === 'pending' && !empty($log['qty_kekurangan'])) {
        $tmp = json_decode($log['qty_kekurangan'], true);
        if (is_array($tmp)) $qty_kekurangan_list = $tmp;
      }

      // Simpan log SCAN khusus
      if ($action === 'SCAN_CHECK_QC') {
        $scan_qc_logs[] = $log;
        $scan_qc_found = true;
        continue;
      } elseif ($action === 'SCAN_OUT_TO_PRODUCTION') {
        $scan_out_logs[] = $log;
        continue;
      }

      foreach ($qty_real_list as $qr_item) {
        if ((string)($qr_item['komponen'] ?? '') !== (string)$komp_id) continue;
        $size = $qr_item['size'] ?? '';
        $qty = floatval($qr_item['qty'] ?? 0);

        // Pengurangan tergantung action
        foreach ($qty_kekurangan_list as $qk) {
          if (
            (string)($qk['komponen'] ?? '') === (string)$komp_id &&
            (string)($qk['size'] ?? '') === (string)$size
          ) {

            if ($action === 'SCAN_IN_INCOMING') {
              // SCAN_IN_INCOMING pakai key 'qty'
              $qty -= floatval($qk['qty'] ?? 0);
            } else {
              // SCAN_IN_WAREHOUSE atau SCAN_OUT_TO_VENDOR (kalau ada kekurangan pakai key 'kekurangan')
              $qty -= floatval($qk['kekurangan'] ?? 0);
            }
          }
        }

        if ($qty < 0) $qty = 0;

        // Tambahkan ke counter sesuai action
        switch ($action) {
          case 'SCAN_IN_WAREHOUSE':
            $in_wh += $qty;
            break;
          case 'SCAN_OUT_TO_VENDOR':
            $out_vendor += $qty;
            break;
          case 'SCAN_IN_INCOMING':
            $in_incoming += $qty;
            break;
        }
      }
    }

    // Hitung Out to Production (pakai log SCAN_CHECK_QC jika ada)
    $out_prod = 0;
    $logs_to_use = !empty($scan_qc_logs) ? $scan_qc_logs : $scan_out_logs;

    foreach ($logs_to_use as $log) {
      $qty_real_list = json_decode($log['qty_real'] ?? '[]', true);
      if (!is_array($qty_real_list)) continue;

      $qty_kekurangan_list = [];
      if (strtolower(trim($log['status_kekurangan'] ?? '')) === 'pending' && !empty($log['qty_kekurangan'])) {
        $tmp = json_decode($log['qty_kekurangan'], true);
        if (is_array($tmp)) $qty_kekurangan_list = $tmp;
      }

      foreach ($qty_real_list as $qr_item) {
        if ((string)($qr_item['komponen'] ?? '') !== (string)$komp_id) continue;
        $size = $qr_item['size'] ?? '';
        $qty = floatval($qr_item['qty'] ?? 0);

        // SCAN_CHECK_QC pakai key 'kekurangan'
        foreach ($qty_kekurangan_list as $qk) {
          if (
            (string)($qk['komponen'] ?? '') === (string)$komp_id &&
            (string)($qk['size'] ?? '') === (string)$size
          ) {
            $qty -= floatval($qk['kekurangan'] ?? 0);
          }
        }

        if ($qty < 0) $qty = 0;
        $out_prod += $qty;
      }
    }

    // HITUNG BALANCE
    $bal_in_wh = $in_wh - $total_order_job;
    $bal_out_vendor = $out_vendor - $total_order_job;
    $bal_in_incoming = $in_incoming - $total_order_job;
    $bal_out_prod = $out_prod - $total_order_job;

    // Susun hasil per komponen
    $rowOut = $jobRow;
    $rowOut['komponen'] = $komp_id;
    $rowOut['nama_komponen'] = $nama_komponen;
    $rowOut['vendors'] = $vendor_names;

    $rowOut['total_order'] = $total_order_job;
    $rowOut['scan_in'] = $in_wh;
    $rowOut['balance_in'] = $bal_in_wh;
    $rowOut['wh_to_vendor'] = $out_vendor;
    $rowOut['balance_wh_to_vendor'] = $bal_out_vendor;
    $rowOut['incoming'] = $in_incoming;
    $rowOut['balance_incoming'] = $bal_in_incoming;
    $rowOut['scan_out'] = $out_prod;
    $rowOut['balance_out'] = $bal_out_prod;

    // Debug tambahan
    $rowOut['debug'] = [
      'qty_logs' => [
        'in_wh' => $in_wh,
        'out_vendor' => $out_vendor,
        'in_incoming' => $in_incoming,
        'out_prod' => $out_prod
      ],
      'total_order_job' => $total_order_job,
      'komponen_set' => $komponen_set,
      'scan_check_qc_found' => $scan_qc_found,
      'scan_qc_logs_count' => count($scan_qc_logs ?? []),
      'scan_out_logs_count' => count($scan_out_logs ?? [])
    ];

    $finalData[] = $rowOut;
  }
}


// -----------------------
// Output JSON
// -----------------------
$response = [
  "draw" => intval($draw),
  "recordsTotal" => intval($recordsTotal),
  "recordsFiltered" => intval($recordsTotal),
  "data" => $finalData
];

echo json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
exit;
