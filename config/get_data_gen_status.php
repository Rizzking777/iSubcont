<?php
require 'function.php';

header('Content-Type: application/json; charset=utf-8');
error_reporting(0);
ini_set('display_errors', 0);

// Ambil request DataTables
$draw   = $_POST['draw'] ?? 1;
$start  = $_POST['start'] ?? 0;
$length = $_POST['length'] ?? 10;

// Filter
$bucket    = $_POST['bucket'] ?? '';
$ncvs      = $_POST['ncvs'] ?? '';
$po_code   = $_POST['po_code'] ?? '';
$job_order = $_POST['job_order'] ?? '';

// Kalau semua filter kosong, langsung return kosong
if (empty($bucket) && empty($ncvs) && empty($po_code) && empty($job_order)) {
  echo json_encode([
    "draw" => intval($draw),
    "recordsTotal" => 0,
    "recordsFiltered" => 0,
    "data" => []
  ]);
  exit;
}

// ===============================
// Base query (ambil dari master yang punya transaksi)
// ===============================
$sql = "
FROM tbl_master_data m
INNER JOIN (
  SELECT DISTINCT job_order FROM tbl_transaksi
) t ON m.job_order = t.job_order
WHERE 1=1
";

$where = [];
$params = [];
$types = "";

// Filter dinamis
if (!empty($bucket)) {
  $where[] = "m.bucket = ?";
  $params[] = $bucket;
  $types   .= "s";
}
if (!empty($ncvs)) {
  $where[] = "m.ncvs = ?";
  $params[] = $ncvs;
  $types   .= "s";
}
if (!empty($po_code)) {
  $where[] = "m.po_code = ?";
  $params[] = $po_code;
  $types   .= "s";
}
if (!empty($job_order)) {
  $where[] = "m.job_order = ?";
  $params[] = $job_order;
  $types   .= "s";
}

if (!empty($where)) {
  $sql .= " AND " . implode(" AND ", $where);
}

// ===============================
// Hitung total unik job_order
// ===============================
$totalQuery = "SELECT COUNT(DISTINCT m.job_order) as cnt " . $sql;
$stmt = $conn->prepare($totalQuery);
if ($types) $stmt->bind_param($types, ...$params);
$stmt->execute();
$totalResult = $stmt->get_result()->fetch_assoc();
$recordsTotal = $totalResult['cnt'] ?? 0;

// ===============================
// Ambil data utama + total order
// ===============================
$dataQuery = "
SELECT 
  m.job_order,
  MAX(m.ncvs) AS ncvs,
  MAX(m.bucket) AS bucket,
  MAX(m.po_code) AS po_code,
  MAX(m.po_item) AS po_item,
  MAX(m.style) AS style,
  MAX(m.model) AS model,
  SUM(m.qty) AS total_order
" . $sql . "
GROUP BY m.job_order
ORDER BY m.job_order ASC, m.ncvs ASC
LIMIT ?, ?";

$params2 = $params;
$types2  = $types . "ii";
$params2[] = intval($start);
$params2[] = intval($length);

$stmt2 = $conn->prepare($dataQuery);
$stmt2->bind_param($types2, ...$params2);
$stmt2->execute();
$dataResult = $stmt2->get_result();

$data = [];

// ===============================
// Loop setiap job_order dan ambil Scan In / Out dari tlog_transaksi
// ===============================
while ($row = $dataResult->fetch_assoc()) {
  $job_order = $row['job_order'];

  // Ambil semua log berdasarkan job_order
  $queryLog = "
    SELECT action_type, new_data
    FROM tlog_transaksi
    WHERE JSON_UNQUOTE(JSON_EXTRACT(new_data, '$.job_order')) = ?
      AND action_type IN ('SCAN_IN_WAREHOUSE', 'SCAN_OUT_TO_PRODUCTION')
  ";
  $stmtLog = $conn->prepare($queryLog);
  $stmtLog->bind_param("s", $job_order);
  $stmtLog->execute();
  $resultLog = $stmtLog->get_result();

  $scan_in_total  = 0;
  $scan_out_total = 0;

  while ($log = $resultLog->fetch_assoc()) {
    $action_type = $log['action_type'];
    $new_data    = json_decode($log['new_data'], true);

    // ✅ Decode double JSON komponen_qty
    $komponen_qty = $new_data['komponen_qty'] ?? [];
    if (is_string($komponen_qty)) {
      $komponen_qty = json_decode($komponen_qty, true);
    }

    if (!empty($komponen_qty) && is_array($komponen_qty)) {
      foreach ($komponen_qty as $item) {
        $qty = isset($item['qty']) ? floatval($item['qty']) : 0;

        if ($action_type === 'SCAN_IN_WAREHOUSE') {
          $scan_in_total += $qty;
        } elseif ($action_type === 'SCAN_OUT_TO_PRODUCTION') {
          $scan_out_total += $qty;
        }
      }
    }
  }

  $total_order = floatval($row['total_order']);
  $balance_in  = $scan_in_total - $total_order;
  $balance_out = $scan_out_total - $total_order;

  // Format link job_order
  $row['job_order'] = '<a href="reports-general-status-detail.php?job_order=' . urlencode($job_order) . '" 
    class="btn btn-sm btn-outline-primary" target="_blank">'
    . htmlspecialchars($job_order) . '</a>';

  // Tambahkan ke data output
  $row['scan_in']     = $scan_in_total;
  $row['scan_out']    = $scan_out_total;
  $row['balance_in']  = $balance_in;
  $row['balance_out'] = $balance_out;

  $data[] = $row;
}

// ===============================
// Response ke DataTables
// ===============================
echo json_encode([
  "draw" => intval($draw),
  "recordsTotal" => $recordsTotal,
  "recordsFiltered" => $recordsTotal,
  "data" => $data
]);
?>
