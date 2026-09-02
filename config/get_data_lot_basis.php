<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

/** @var mysqli $conn */

require_once "function.php";

//Request DataTables
$draw   = $_POST['draw'] ?? 1;
$start  = $_POST['start'] ?? 0;
$length = $_POST['length'] ?? 10;

//FLOW TYPE
$type = $_POST['type'] ?? 'sm';

if (!in_array($type, ['sm', 'wh'], true)) {
    $type = 'sm';
}

// FILTER
$bucket    = $_POST['bucket'] ?? '';
$ncvs      = $_POST['ncvs'] ?? '';
$po_code   = $_POST['po_code'] ?? '';
$job_order = $_POST['job_order'] ?? '';

if (
    empty($bucket) &&
    empty($ncvs) &&
    empty($po_code) &&
    empty($job_order)
) {
    echo json_encode([
        "draw"            => intval($draw),
        "recordsTotal"    => 0,
        "recordsFiltered" => 0,
        "data"            => []
    ]);

    exit;
}

// BASE QUERY
$sql = "
    FROM tbl_master_data m
    INNER JOIN (
        SELECT DISTINCT job_order
        FROM tbl_transaksi
    ) t
        ON m.job_order = t.job_order
    WHERE 1=1
";

$where  = [];
$params = [];
$types  = "";

// DYNAMIC FILTERS
if (!empty($bucket)) {
    $where[]  = "m.bucket = ?";
    $params[] = $bucket;
    $types   .= "s";
}

if (!empty($ncvs)) {
    $where[]  = "m.ncvs = ?";
    $params[] = $ncvs;
    $types   .= "s";
}

if (!empty($po_code)) {
    $where[]  = "m.po_code = ?";
    $params[] = $po_code;
    $types   .= "s";
}

if (!empty($job_order)) {
    $where[]  = "m.job_order = ?";
    $params[] = $job_order;
    $types   .= "s";
}

if (!empty($where)) {
    $sql .= " AND " . implode(" AND ", $where);
}

// TOTAL RECORDS
$totalQuery = "
    SELECT COUNT(DISTINCT m.job_order) AS cnt
    " . $sql;

$stmt = $conn->prepare($totalQuery);

if ($types) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();

$totalResult  = $stmt->get_result()->fetch_assoc();
$recordsTotal = $totalResult['cnt'] ?? 0;

$detailPage = ($type === 'wh')
    ? 'dashboard-lot-basis-wh-detail.php'
    : 'dashboard-lot-basis-detail.php';

$dataQuery = "
    SELECT
        m.job_order,
        MAX(m.ncvs) AS ncvs,
        MAX(m.bucket) AS bucket,
        MAX(m.po_code) AS po_code,
        MAX(m.po_item) AS po_item,
        MAX(m.style) AS style,
        MAX(m.model) AS model

    " . $sql . "

    GROUP BY m.job_order

    ORDER BY
        CAST(SUBSTRING_INDEX(m.job_order, '-', 1) AS UNSIGNED),
        CAST(
            SUBSTRING_INDEX(
                SUBSTRING_INDEX(m.job_order, '-', 2),
                '-',
                -1
            ) AS UNSIGNED
        ),
        CAST(
            SUBSTRING_INDEX(
                SUBSTRING_INDEX(m.job_order, '-', 3),
                '-',
                -1
            ) AS UNSIGNED
        ),
        CAST(
            SUBSTRING_INDEX(
                SUBSTRING_INDEX(m.job_order, '-', 4),
                '-',
                -1
            ) AS UNSIGNED
        ),
        m.ncvs ASC

    LIMIT ?, ?
";

$params2 = $params;
$types2  = $types . "ii";

$params2[] = intval($start);
$params2[] = intval($length);

$stmt2 = $conn->prepare($dataQuery);

$stmt2->bind_param($types2, ...$params2);

$stmt2->execute();

$dataResult = $stmt2->get_result();

// FORMAT DATA
$detailPage = ($type === 'wh')
    ? 'dashboard-lot-basis-wh-detail.php'
    : 'dashboard-lot-basis-detail.php';

$flow = ($type === 'wh') ? 'wh' : 'sm';

$data = [];

while ($row = $dataResult->fetch_assoc()) {

    $jobOrderValue = $row['job_order'];

    $row['job_order'] = '<a href="' .
        $detailPage .
        '?job_order=' .
        urlencode($jobOrderValue) .
        '&flow=' .
        $flow .
        '" 
        class="btn btn-sm btn-outline-primary"
        target="_blank">'
        . htmlspecialchars($jobOrderValue) .
        '</a>';

    $data[] = $row;
}

// RESPONSE DATATABLES
echo json_encode([
    "draw"            => intval($draw),
    "recordsTotal"    => $recordsTotal,
    "recordsFiltered" => $recordsTotal,
    "data"            => $data
]);
