<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once "function.php";

/** @var mysqli $conn */

$jobOrder = trim($_GET['job_order'] ?? '');

if ($jobOrder === '') {
    die("Job Order tidak ditemukan.");
}

$flow = $_GET['flow'] ?? 'sm';

if (!in_array($flow, ['sm', 'wh'], true)) {
    $flow = 'sm';
}

if ($flow === 'wh') {

    $gateMap = [
        'WH_SUBCONT_FROM_SM_SUBCONT' => 'wh_incoming',
        'WH_SUBCONT_TO_VENDOR'       => 'wh_vendor',
        'WH_SUBCONT_FROM_VENDOR'     => 'wh_return',
        'WH_SUBCONT_TO_SM_SUBCONT'   => 'wh_to_sm'
    ];
} else {

    $gateMap = [
        'SM_SUBCONT_FROM_CUT'         => 'in_sm',
        'SM_SUBCONT_TO_WH_SUBCONT'    => 'send_wh',
        'SM_SUBCONT_FROM_WH_SUBCONT'  => 'return_sm',
        'SM_SUBCONT_TO_NCVS'          => 'out_prod'
    ];
}

// 1. HEADER JOB ORDER
$stmt = $conn->prepare("
    SELECT
        job_order,
        bucket,
        po_code,
        po_item,
        model,
        style,
        ncvs,
        status_lot
    FROM tbl_master_data
    WHERE job_order = ?
    LIMIT 1
");

$stmt->bind_param("s", $jobOrder);
$stmt->execute();

$info = $stmt->get_result()->fetch_assoc();

if (!$info) {
    die("Data Job Order tidak ditemukan.");
}

// 2. MAIN COMPONENT
$stmt = $conn->prepare("
    SELECT DISTINCT
        COALESCE(t.id_komponen_out, t.id_komponen_in) AS id_komponen,
        COALESCE(t.nm_komponen_out, t.nm_komponen_in) AS nama_komponen
    FROM tbl_transaksi t
    WHERE t.job_order = ?
      AND t.is_main_komponen = 1
    ORDER BY
        COALESCE(t.id_komponen_out, t.id_komponen_in)
");

$stmt->bind_param("s", $jobOrder);
$stmt->execute();

$result = $stmt->get_result();

$komponenList = [];

while ($row = $result->fetch_assoc()) {

    $komponenList[] = [
        'id'   => (int) $row['id_komponen'],
        'nama' => $row['nama_komponen']
    ];
}

if (empty($komponenList)) {
    die("Main Component tidak ditemukan untuk Job Order ini.");
}


// 3. BASE GRID: MAIN COMPONENT + LOT + SIZE
$stmt = $conn->prepare("
    SELECT DISTINCT
        COALESCE(t.id_komponen_out, t.id_komponen_in) AS id_komponen,
        COALESCE(t.nm_komponen_out, t.nm_komponen_in) AS nama_komponen,
        t.lot,
        t.size
    FROM tbl_transaksi t
    WHERE t.job_order = ?
      AND t.is_main_komponen = 1
    ORDER BY
        COALESCE(t.id_komponen_out, t.id_komponen_in),
        t.lot,
        t.size
");

$stmt->bind_param("s", $jobOrder);
$stmt->execute();

$result = $stmt->get_result();

$tableData = [];

while ($row = $result->fetch_assoc()) {

    $kompId = (int) $row['id_komponen'];
    $lot    = (string) $row['lot'];
    $size   = (string) $row['size'];

    if (!isset($tableData[$kompId])) {
        $tableData[$kompId] = [];
    }

    if (!isset($tableData[$kompId][$lot])) {
        $tableData[$kompId][$lot] = [];
    }

    if (!isset($tableData[$kompId][$lot][$size])) {
        $cellData = [
            'plan' => 0
        ];

        foreach (array_unique(array_values($gateMap)) as $field) {
            $cellData[$field] = 0;
        }

        $tableData[$kompId][$lot][$size] = $cellData;
    }
}


// 4. PLAN
$stmt = $conn->prepare("
    SELECT
        lot,
        size,
        SUM(qty) AS plan_qty
    FROM tbl_master_data
    WHERE job_order = ?
    GROUP BY lot, size
");

$stmt->bind_param("s", $jobOrder);
$stmt->execute();

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {

    $lot  = (string) $row['lot'];
    $size = (string) $row['size'];
    $plan = (float) $row['plan_qty'];

    foreach ($komponenList as $komponen) {

        $kompId = $komponen['id'];

        if (
            isset($tableData[$kompId][$lot][$size])
        ) {
            $tableData[$kompId][$lot][$size]['plan'] = $plan;
        }
    }
}

// 5. ACTUAL EVENT
$gateList = array_keys($gateMap);

$gateSql = "'" . implode(
    "','",
    array_map([$conn, 'real_escape_string'], $gateList)
) . "'";

$stmt = $conn->prepare("
    SELECT
        COALESCE(t.id_komponen_out, t.id_komponen_in) AS id_komponen,
        COALESCE(t.nm_komponen_out, t.nm_komponen_in) AS nama_komponen,
        te.lot,
        te.size,
        te.gate,
        SUM(te.qty) AS actual_qty

    FROM tbl_transaksi_event te

    INNER JOIN tbl_transaksi t
        ON t.id_trans = te.id_trans

    WHERE t.job_order = ?
      AND t.is_main_komponen = 1
      AND te.gate IN ($gateSql)

    GROUP BY
        COALESCE(t.id_komponen_out, t.id_komponen_in),
        COALESCE(t.nm_komponen_out, t.nm_komponen_in),
        te.lot,
        te.size,
        te.gate

    ORDER BY
        COALESCE(t.id_komponen_out, t.id_komponen_in),
        te.lot,
        te.size,
        te.gate
");

$stmt->bind_param("s", $jobOrder);
$stmt->execute();

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {

    $kompId = (int) $row['id_komponen'];
    $lot    = (string) $row['lot'];
    $size   = (string) $row['size'];
    $gate   = $row['gate'];
    $actual = (float) $row['actual_qty'];

    if (!isset($tableData[$kompId][$lot][$size])) {
        continue;
    }

    if (!isset($gateMap[$gate])) {
        continue;
    }

    $field = $gateMap[$gate];

    $tableData[$kompId][$lot][$size][$field] += $actual;
}

// 6. HITUNG BALANCE TERHADAP PLAN
$stageFields = array_unique(array_values($gateMap));

foreach ($tableData as &$lotsData) {

    foreach ($lotsData as &$sizesData) {

        foreach ($sizesData as &$data) {

            $plan = (float) ($data['plan'] ?? 0);

            foreach ($stageFields as $field) {

                $actual = (float) ($data[$field] ?? 0);

                $data[$field] = $actual - $plan;
            }
        }

        unset($data);
    }

    unset($sizesData);
}

unset($lotsData);

// 7. DATA PENDUKUNG UNTUK HTML
$officialSizes = [];

foreach ($tableData as $kompLots) {
    foreach ($kompLots as $sizes) {
        foreach ($sizes as $size => $data) {
            $officialSizes[$size] = true;
        }
    }
}

$officialSizes = array_keys($officialSizes);

usort($officialSizes, function ($a, $b) {

    preg_match('/\d+/', $a, $ma);
    preg_match('/\d+/', $b, $mb);

    $na = (int) ($ma[0] ?? 0);
    $nb = (int) ($mb[0] ?? 0);

    if ($na !== $nb) {
        return $na <=> $nb;
    }

    return strnatcasecmp($a, $b);
});

$lots = [];

foreach ($tableData as $kompLots) {
    foreach ($kompLots as $lot => $sizes) {
        $lots[$lot] = true;
    }
}

$lots = array_keys($lots);

sort($lots);
