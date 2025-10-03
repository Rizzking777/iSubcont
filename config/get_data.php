<?php
require 'function.php';

// Ambil request DataTables
$draw   = $_POST['draw'] ?? 1;
$start  = $_POST['start'] ?? 0;
$length = $_POST['length'] ?? 10;

// Filter
$bucket    = $_POST['bucket'] ?? '';
$ncvs      = $_POST['ncvs'] ?? '';
$po_code   = $_POST['po_code'] ?? '';
$job_order = $_POST['job_order'] ?? '';

// ====== FIX: kalau semua filter kosong, balikin data kosong dulu ======
if (empty($bucket) && empty($ncvs) && empty($po_code) && empty($job_order)) {
    echo json_encode([
        "draw" => intval($draw),
        "recordsTotal" => 0,
        "recordsFiltered" => 0,
        "data" => []
    ]);
    exit;
}

// Base query
$sql = "FROM tbl_transaksi t WHERE 1=1";

// Filter dinamis
$where = [];
$params = [];
$types = "";

// kalau ada bucket
if (!empty($bucket)) {
    $where[] = "t.bucket = ?";
    $params[] = $bucket;
    $types   .= "s";
}

// kalau ada ncvs
if (!empty($ncvs)) {
    $where[] = "t.ncvs = ?";
    $params[] = $ncvs;
    $types   .= "s";
}

// kalau ada po_code
if (!empty($po_code)) {
    $where[] = "t.po_code = ?";
    $params[] = $po_code;
    $types   .= "s";
}

// kalau ada job_order
if (!empty($job_order)) {
    $where[] = "t.job_order = ?";
    $params[] = $job_order;
    $types   .= "s";
}

// satukan filter
if (!empty($where)) {
    $sql .= " AND " . implode(" AND ", $where);
}

// Hitung total records
$totalQuery = "SELECT COUNT(*) as cnt " . $sql;
$stmt = $conn->prepare($totalQuery);
if ($types) $stmt->bind_param($types, ...$params);
$stmt->execute();
$totalResult = $stmt->get_result()->fetch_assoc();
$recordsTotal = $totalResult['cnt'] ?? 0;

// Ambil data
$dataQuery = "SELECT 
    t.job_order, t.ncvs, t.bucket, t.po_code, t.po_item, t.model, t.style 
    " . $sql . " LIMIT ?, ?";
$params2 = $params;
$types2  = $types . "ii";
$params2[] = $start;
$params2[] = $length;

$stmt2 = $conn->prepare($dataQuery);
$stmt2->bind_param($types2, ...$params2);
$stmt2->execute();
$dataResult = $stmt2->get_result();

$data = [];
while ($row = $dataResult->fetch_assoc()) {
    $row['job_order'] = '<a href="reports-out-control-detail.php?job_order=' . urlencode($row['job_order']) . '" class="btn btn-sm btn-outline-primary">' . htmlspecialchars($row['job_order']) . '</a>';
    $data[] = $row;
}

// Response JSON
echo json_encode([
    "draw" => intval($draw),
    "recordsTotal" => $recordsTotal,
    "recordsFiltered" => $recordsTotal,
    "data" => $data
]);
