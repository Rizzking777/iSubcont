<?php
require 'function.php';
/** @var mysqli $conn */

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

// POST
$date_range =
    $_POST['date_range']
    ?? '';

$bucket =
    $_POST['bucket']
    ?? '';

$po_code =
    $_POST['po_code']
    ?? '';

$po_item =
    $_POST['po_item']
    ?? '';

$ncvs =
    $_POST['ncvs']
    ?? '';

$model =
    $_POST['model']
    ?? '';

$style =
    $_POST['style']
    ?? '';

$vendor =
    $_POST['vendor']
    ?? '';

// WHERE
$where = [];

$params = [];

$types = '';

// ACTIVE
$where[] =
    "barcode_status = 'ACTIVE'";

// MAIN KOMPONEN ONLY
$where[] =
    "is_main_komponen = 1";

// DATE RANGE
if (!empty($date_range)) {

    $date =
        explode(
            ' - ',
            $date_range
        );

    if (count($date) == 2) {

        $start =
            date(
                'Y-m-d',
                strtotime(
                    $date[0]
                )
            );

        $end =
            date(
                'Y-m-d',
                strtotime(
                    $date[1]
                )
            );

        $where[] =
            "DATE(created_at)
            BETWEEN ? AND ?";

        $params[] = $start;
        $params[] = $end;

        $types .= 'ss';
    }
}

// FILTER
$filters = [
    'bucket' => $bucket,
    'po_code' => $po_code,
    'po_item' => $po_item,
    'ncvs' => $ncvs,
    'model' => $model,
    'style' => $style,
    'nm_vendor' => $vendor
];

foreach (
    $filters
    as $field => $value
) {

    if (!empty($value)) {

        $where[] =
            "$field = ?";

        $params[] =
            $value;

        $types .= 's';
    }
}

// WHERE SQL
$where_sql = '';

if (count($where) > 0) {

    $where_sql =
        'WHERE ' .
        implode(
            ' AND ',
            $where
        );
}

// QUERY
$sql = "

SELECT

    job_order,
    ncvs,
    bucket,
    po_code,
    po_item,
    model,
    style,
    nm_komponen_in AS komponen,
    nm_vendor AS vendor,

    SUM(IFNULL(qty_plan,0))
        AS total_order,

    /* ===================================== */
    /* SM CUTTING */
    /* ===================================== */

    SUM(IFNULL(qty_cut_to_smsubcont,0))
        AS sm_cutting_in,

    (
        SUM(IFNULL(qty_cut_to_smsubcont,0))
        -
        SUM(IFNULL(qty_plan,0))
    )
        AS sm_cutting_balance,

    SUM(IFNULL(qty_smsubcont_fr_cut,0))
        AS sm_cutting_out,

    (
        SUM(IFNULL(qty_smsubcont_fr_cut,0))
        -
        SUM(IFNULL(qty_cut_to_smsubcont,0))
    )
        AS sm_cutting_out_balance,

    /* ===================================== */
    /* SM SUBCONT */
    /* ===================================== */

    SUM(IFNULL(qty_smsubcont_fr_cut,0))
        AS in_sm,

    (
        SUM(IFNULL(qty_smsubcont_fr_cut,0))
        -
        SUM(IFNULL(qty_plan,0))
    )
        AS balance_in_sm,

    SUM(IFNULL(qty_smsubcont_to_whsubcont,0))
        AS out_sm,

    (
        SUM(IFNULL(qty_smsubcont_to_whsubcont,0))
        -
        SUM(IFNULL(qty_plan,0))
    )
        AS balance_out_sm,

    /* ===================================== */
    /* WH SUBCONT */
    /* ===================================== */

    SUM(IFNULL(qty_whsubcont_fr_smsubcont,0))
        AS in_wh,

    (
        SUM(IFNULL(qty_whsubcont_fr_smsubcont,0))
        -
        SUM(IFNULL(qty_plan,0))
    )
        AS balance_in_wh,

    SUM(IFNULL(qty_whsubcont_to_vendor,0))
        AS out_wh,

    (
        SUM(IFNULL(qty_whsubcont_to_vendor,0))
        -
        SUM(IFNULL(qty_plan,0))
    )
        AS balance_out_wh,

    /* ===================================== */
    /* VENDOR */
    /* ===================================== */

    SUM(IFNULL(qty_vendor_fr_whsubcont,0))
        AS in_vendor,

    (
        SUM(IFNULL(qty_vendor_fr_whsubcont,0))
        -
        SUM(IFNULL(qty_plan,0))
    )
        AS balance_in_vendor,

    SUM(IFNULL(qty_vendor_to_whsubcont,0))
        AS out_vendor,

    (
        SUM(IFNULL(qty_vendor_to_whsubcont,0))
        -
        SUM(IFNULL(qty_plan,0))
    )
        AS balance_out_vendor,

    /* ===================================== */
    /* RETURN WH */
    /* ===================================== */

    SUM(IFNULL(qty_whsubcont_fr_vendor,0))
        AS return_wh,

    (
        SUM(IFNULL(qty_whsubcont_fr_vendor,0))
        -
        SUM(IFNULL(qty_plan,0))
    )
        AS balance_return_wh,

    SUM(IFNULL(qty_whsubcont_to_smsubcont,0))
        AS transfer_sm,

    (
        SUM(IFNULL(qty_whsubcont_to_smsubcont,0))
        -
        SUM(IFNULL(qty_plan,0))
    )
        AS balance_transfer_sm,

    /* ===================================== */
    /* RETURN SM */
    /* ===================================== */

    SUM(IFNULL(qty_smsubcont_fr_whsubcont,0))
        AS return_sm,

    (
        SUM(IFNULL(qty_smsubcont_fr_whsubcont,0))
        -
        SUM(IFNULL(qty_plan,0))
    )
        AS balance_return_sm,

    /* ===================================== */
    /* TRANSFER NCVS */
    /* ===================================== */

    SUM(IFNULL(qty_smsubcont_to_prod,0))
        AS transfer_ncvs,

    (
        SUM(IFNULL(qty_smsubcont_to_prod,0))
        -
        SUM(IFNULL(qty_plan,0))
    )
        AS balance_transfer_ncvs

FROM tbl_transaksi

$where_sql

GROUP BY
    job_order,
    ncvs,
    bucket,
    po_code,
    po_item,
    model,
    style,
    nm_vendor,
    nm_komponen_in

ORDER BY
    MAX(ncvs) ASC

";

// PREPARE
$stmt =
    $conn->prepare($sql);

// BIND
if (
    count($params) > 0
) {

    $stmt->bind_param(
        $types,
        ...$params
    );
}

// EXECUTE
$stmt->execute();

$result =
    $stmt->get_result();

// DATA
$data = [];

while (
    $row =
    mysqli_fetch_assoc($result)
) {

    $data[] = [

        "job_order" =>
        $row['job_order'],

        "ncvs" =>
        $row['ncvs'],

        "bucket" =>
        $row['bucket'],

        "po_code" =>
        $row['po_code'],

        "po_item" =>
        $row['po_item'],

        "model" =>
        $row['model'],

        "style" =>
        $row['style'],

        "komponen" =>
        $row['komponen'],

        "vendor" =>
        $row['vendor'],

        "total_order" =>
        (int)$row['total_order'],

        // SM CUTTING
        "sm_cutting_in" =>
        (int)$row['sm_cutting_in'],

        "sm_cutting_balance" =>
        (int)$row['sm_cutting_balance'],

        "sm_cutting_out" =>
        (int)$row['sm_cutting_out'],

        "sm_cutting_out_balance" =>
        (int)$row['sm_cutting_out_balance'],

        // SM SUBCONT
        "in_sm" =>
        (int)$row['in_sm'],

        "balance_in_sm" =>
        (int)$row['balance_in_sm'],

        "out_sm" =>
        (int)$row['out_sm'],

        "balance_out_sm" =>
        (int)$row['balance_out_sm'],

        // WH SUBCONT
        "in_wh" =>
        (int)$row['in_wh'],

        "balance_in_wh" =>
        (int)$row['balance_in_wh'],

        "out_wh" =>
        (int)$row['out_wh'],

        "balance_out_wh" =>
        (int)$row['balance_out_wh'],

        // VENDOR
        "in_vendor" =>
        (int)$row['in_vendor'],

        "balance_in_vendor" =>
        (int)$row['balance_in_vendor'],

        "out_vendor" =>
        (int)$row['out_vendor'],

        "balance_out_vendor" =>
        (int)$row['balance_out_vendor'],

        // RETURN WH
        "return_wh" =>
        (int)$row['return_wh'],

        "balance_return_wh" =>
        (int)$row['balance_return_wh'],

        "transfer_sm" =>
        (int)$row['transfer_sm'],

        "balance_transfer_sm" =>
        (int)$row['balance_transfer_sm'],

        // RETURN SM
        "return_sm" =>
        (int)$row['return_sm'],

        "balance_return_sm" =>
        (int)$row['balance_return_sm'],

        "transfer_ncvs" =>
        (int)$row['transfer_ncvs'],

        "balance_transfer_ncvs" =>
        (int)$row['balance_transfer_ncvs']
    ];
}

// JSON
echo json_encode([
    "data" => $data
]);
