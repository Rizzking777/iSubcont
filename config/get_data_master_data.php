<?php
require 'function.php';
/** @var mysqli $conn */

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

// Base query — ambil dari master
$sql = "
FROM tbl_master_data m
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
// Ambil data unik per job_order
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
    MAX(m.status_lot) AS status_lot,
    SUM(m.qty) AS Qty_Order
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
while ($row = $dataResult->fetch_assoc()) {
    // 🔗 Link ke detail lot basis
    $row['job_order'] = '<a href="trans-barcode-detail.php?job_order=' . urlencode($row['job_order']) . '" 
    class="btn btn-sm btn-outline-primary" 
    target="_blank">'
        . htmlspecialchars($row['job_order']) .
        '</a>';

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
