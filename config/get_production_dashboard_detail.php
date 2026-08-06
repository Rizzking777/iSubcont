<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');
/** @var mysqli $conn */

require_once "function.php";

/* PARAM */
$section =
    $_GET['section'] ?? '';

$type =
    $_GET['type'] ?? '';

$ncvs =
    $_GET['ncvs'] ?? '';

$dateFrom =
    $_GET['date_from'] ?? '';

$dateTo =
    $_GET['date_to'] ?? '';

$gateMap = [

    "pre_vendor" => [

        "in" => "SM_SUBCONT_FROM_CUT",

        "out" => "SM_SUBCONT_TO_WH_SUBCONT"

    ],

    "after_vendor" => [

        "in" => "SM_SUBCONT_FROM_WH_SUBCONT",

        "out" => "SM_SUBCONT_TO_PROD"

    ]

];

$ncvsWhere = "";

if (!empty($ncvs)) {

    $ncvsWhere = "

        AND t.ncvs='$ncvs'

    ";
}

$gate = "";

if ($type != "inventory") {

    $gate = $gateMap[$section][$type] ?? "";

}

/* QUERY */

if ($type == "inventory") {

    $gateIn = $gateMap[$section]["in"];
    $gateOut = $gateMap[$section]["out"];

    $sql = "

SELECT

    t.job_order,
    t.ncvs,
    t.bucket,
    t.style,
    t.model,
    t.po_code,
    t.po_item,
    t.nm_komponen_in AS main_component,
    t.size,
    t.id_group,

    SUM(
        CASE
            WHEN te.gate='$gateIn'
            THEN te.qty
            ELSE 0
        END
    )

    -

    SUM(
        CASE
            WHEN te.gate='$gateOut'
            THEN te.qty
            ELSE 0
        END
    )

    AS qty_total

FROM tbl_transaksi_event te

INNER JOIN tbl_transaksi t
ON t.id_trans = te.id_trans

WHERE

t.is_main_komponen = 1

AND DATE(te.created_at)
BETWEEN '$dateFrom'
AND '$dateTo'

$ncvsWhere

GROUP BY

    t.job_order,
    t.ncvs,
    t.bucket,
    t.style,
    t.model,
    t.po_code,
    t.po_item,
    t.nm_komponen_in,
    t.size,
    t.id_group

HAVING qty_total > 0

ORDER BY

    t.bucket,
    t.job_order,
    t.ncvs

";
} else {

    $sql = "

SELECT

    t.job_order,
    t.ncvs,
    t.bucket,
    t.style,
    t.model,
    t.po_code,
    t.po_item,
    t.nm_komponen_in AS main_component,
    t.size,
    t.id_group,

    SUM(te.qty) qty_total

FROM tbl_transaksi_event te

INNER JOIN tbl_transaksi t
ON t.id_trans = te.id_trans

WHERE

    t.is_main_komponen = 1

    AND te.gate='$gate'

    AND DATE(te.created_at)
        BETWEEN '$dateFrom'
        AND '$dateTo'

    $ncvsWhere

GROUP BY

    t.job_order,
    t.ncvs,
    t.bucket,
    t.style,
    t.model,
    t.po_code,
    t.po_item,
    t.nm_komponen_in,
    t.size,
    t.id_group

ORDER BY

    t.bucket,
    t.job_order,
    t.ncvs

";
}

$result = mysqli_query($conn, $sql);

/* PREPARE */

$sizes = [];
$rows = [];

/* LOOP */

while ($row = mysqli_fetch_assoc($result)) {

    $key = implode('|', [

        $row['ncvs'],
        $row['bucket'],
        $row['style'],
        $row['model'],
        $row['po_code'],
        $row['po_item'],
        $row['main_component']

    ]);

    /* SIZE */

    if (!in_array($row['size'], $sizes)) {

        $sizes[] = $row['size'];
    }

    /* ROW INIT */

    if (!isset($rows[$key])) {

        $rows[$key] = [

            'job_order' => $row['job_order'],
            'ncvs' => $row['ncvs'],
            'bucket' => $row['bucket'],
            'style' => $row['style'],
            'model' => $row['model'],
            'po' => $row['po_code'],
            'po_item' => $row['po_item'],
            'component' => $row['main_component'],
            'id_group' => $row['id_group'],
            'sizes' => []

        ];
    }

    /* SIZE QTY */
    $rows[$key]['sizes'][$row['size']] = (int)$row['qty_total'];
}

/* SORT SIZE */
usort($sizes, function ($a, $b) {

    /* NUMBER */
    $aNum = (int) preg_replace(
        '/[^0-9]/',
        '',
        $a
    );

    $bNum = (int) preg_replace(
        '/[^0-9]/',
        '',
        $b
    );

    /* SAME NUMBER */
    if ($aNum == $bNum) {

        /* NORMAL FIRST */

        $aIsT = str_contains($a, 'T');
        $bIsT = str_contains($b, 'T');
        if ($aIsT == $bIsT) {
            return 0;
        }
        return $aIsT ? 1 : -1;
    }

    return $aNum <=> $bNum;
});

/* RESPONSE */

$response = [
    'sizes' => $sizes,
    'rows' => array_values($rows)

];

echo json_encode($response);
