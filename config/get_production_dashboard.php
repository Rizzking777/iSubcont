<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');
/** @var mysqli $conn */

require_once "function.php";

/* CUTTING SUMMARY */

$sqlCutting = "
    SELECT
    SUM(IFNULL(qty_cut_to_smsubcont, 0)) AS total_in,
    SUM(IFNULL(qty_smsubcont_fr_cut, 0)) AS total_out,
    SUM(
        IFNULL(qty_cut_to_smsubcont, 0)
        -
        IFNULL(qty_smsubcont_fr_cut, 0)
    ) AS total_inventory
FROM tbl_transaksi
";

$resultCutting = mysqli_query($conn, $sqlCutting);
$dataCutting = mysqli_fetch_assoc($resultCutting);

/* CUTTING CHART */
$sqlChart = "
    SELECT
        ncvs,
        SUM(
            IFNULL(qty_cut_to_smsubcont, 0)
        -
        IFNULL(qty_smsubcont_fr_cut, 0)
        ) AS total_inventory
    FROM tbl_transaksi
    GROUP BY ncvs
    ORDER BY ncvs ASC;
";

$resultChart = mysqli_query($conn, $sqlChart);
$categories = [];
$seriesData = [];

while ($row = mysqli_fetch_assoc($resultChart)) {

    $categories[] = $row['ncvs'];
    $seriesData[] = (int)$row['total_inventory'];
}

/* PRE PROCESS VENDOR SUMMARY */
$sqlPreVendor = "
    SELECT
        SUM(
            IFNULL(qty_smsubcont_fr_cut, 0)
        ) AS total_in,
        SUM(
            IFNULL(qty_smsubcont_to_whsubcont, 0)
        ) AS total_out,
        SUM(
            IFNULL(qty_smsubcont_fr_cut, 0)
            -
            IFNULL(qty_smsubcont_to_whsubcont, 0)
        ) AS total_inventory
    FROM tbl_transaksi;
";

$resultPreVendor = mysqli_query($conn, $sqlPreVendor);
$dataPreVendor = mysqli_fetch_assoc($resultPreVendor);

/* PRE VENDOR CHART */
$sqlChartPreVendor = "
    SELECT
        ncvs,
        SUM(
            IFNULL(qty_smsubcont_fr_cut, 0)
            -
            IFNULL(qty_smsubcont_to_whsubcont, 0)
        ) AS total_inventory

    FROM tbl_transaksi
    GROUP BY ncvs
    ORDER BY ncvs ASC;

";

$resultChartPreVendor = mysqli_query(
    $conn,
    $sqlChartPreVendor
);

$preVendorCategories = [];
$preVendorSeries = [];

while ($row = mysqli_fetch_assoc($resultChartPreVendor)) {

    $preVendorCategories[] = $row['ncvs'];
    $preVendorSeries[] = (int)$row['total_inventory'];
}

/* AFTER PROCESS VENDOR SUMMARY */
$sqlAfterVendor = "
    SELECT
        SUM(
            IFNULL(
                qty_smsubcont_fr_whsubcont,
                0
            )
        ) AS total_in,
        SUM(

            IFNULL(
                qty_smsubcont_to_prod,
                0
            )
        ) AS total_out,
        SUM(

            IFNULL(
                qty_smsubcont_fr_whsubcont,
                0
            )
            -
            IFNULL(
                qty_smsubcont_to_prod,
                0
            )

        ) AS total_inventory

    FROM tbl_transaksi
    WHERE is_main_komponen = 1

";

$resultAfterVendor = mysqli_query(
    $conn,
    $sqlAfterVendor
);

$dataAfterVendor = mysqli_fetch_assoc(
    $resultAfterVendor
);

/* AFTER VENDOR CHART */
$sqlChartAfterVendor = "
    SELECT
        ncvs,
        SUM(

            IFNULL(
                qty_smsubcont_fr_whsubcont,
                0
            )
            -
            IFNULL(
                qty_smsubcont_to_prod,
                0
            )
        ) AS total_inventory
    FROM tbl_transaksi
    WHERE is_main_komponen = 1
    GROUP BY ncvs
    ORDER BY ncvs ASC

";

$resultChartAfterVendor = mysqli_query(
    $conn,
    $sqlChartAfterVendor
);

$afterVendorCategories = [];
$afterVendorSeries = [];

while ($row = mysqli_fetch_assoc($resultChartAfterVendor)) {

    $afterVendorCategories[] = $row['ncvs'];
    $afterVendorSeries[] =
        (int)$row['total_inventory'];
}

/* RESPONSE */
$response = [
    /* CUTTING */
    'cutting' => [
        'summary' => [

            'in' => (int)$dataCutting['total_in'],
            'out' => (int)$dataCutting['total_out'],
            'inventory' => (int)$dataCutting['total_inventory']

        ],

        'chart' => [
            'categories' => $categories,
            'series' => $seriesData
        ]

    ],

    /* PRE VENDOR */
    'pre_vendor' => [
        'summary' => [
            'in' => (int)$dataPreVendor['total_in'],
            'out' => (int)$dataPreVendor['total_out'],
            'inventory' => (int)$dataPreVendor['total_inventory']
        ],

        'chart' => [
            'categories' => $preVendorCategories,
            'series' => $preVendorSeries
        ]

    ],

    /* AFTER VENDOR */
    'after_vendor' => [
        'summary' => [
            'in' => (int)$dataAfterVendor['total_in'],
            'out' => (int)$dataAfterVendor['total_out'],
            'inventory' => (int)$dataAfterVendor['total_inventory']
        ],

        'chart' => [

            'categories' => $afterVendorCategories,
            'series' => $afterVendorSeries

        ]

    ],

];

header('Content-Type: application/json');

echo json_encode($response);
