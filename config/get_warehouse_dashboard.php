<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');
/** @var mysqli $conn */

require_once "function.php";

/* READY TRANSFER SUMMARY */
$sqlReadyTransfer = "
    SELECT
        SUM(
            IFNULL(
                qty_whsubcont_fr_smsubcont,
                0
            )
        ) AS total_receive,

        SUM(
            IFNULL(
                qty_whsubcont_to_vendor,
                0
            )
        ) AS total_transfer,

        SUM(
            IFNULL(
                qty_whsubcont_fr_smsubcont,
                0
            )
            -
            IFNULL(
                qty_whsubcont_to_vendor,
                0
            )
        ) AS total_inventory

    FROM tbl_transaksi
";

$resultReadyTransfer =
    mysqli_query(
        $conn,
        $sqlReadyTransfer
    );

$dataReadyTransfer =
    mysqli_fetch_assoc(
        $resultReadyTransfer
    );

/* READY TRANSFER CHART */

$sqlChartReadyTransfer = "
    SELECT

        ncvs,

        SUM(
            IFNULL(
                qty_whsubcont_fr_smsubcont,
                0
            )
            -
            IFNULL(
                qty_whsubcont_to_vendor,
                0
            )
        ) AS total_inventory

    FROM tbl_transaksi

    GROUP BY ncvs

    ORDER BY ncvs ASC
";

$resultChartReadyTransfer =
    mysqli_query(
        $conn,
        $sqlChartReadyTransfer
    );

$readyTransferCategories = [];
$readyTransferSeries = [];

while (
    $row =
    mysqli_fetch_assoc(
        $resultChartReadyTransfer
    )
) {

    $readyTransferCategories[] =
        $row['ncvs'];

    $readyTransferSeries[] =
        (int)$row['total_inventory'];
}

/* RETURN SUMMARY */

$sqlReturnVendor = "
    SELECT

        SUM(
            IFNULL(
                qty_whsubcont_fr_vendor,
                0
            )
        ) AS total_receive,

        SUM(
            IFNULL(
                qty_whsubcont_to_smsubcont,
                0
            )
        ) AS total_send_prod,

        SUM(
            IFNULL(
                qty_whsubcont_fr_vendor,
                0
            )
            -
            IFNULL(
                qty_whsubcont_to_smsubcont,
                0
            )
        ) AS total_inventory

    FROM tbl_transaksi
    WHERE is_main_komponen = 1
";

$resultReturnVendor =
    mysqli_query(
        $conn,
        $sqlReturnVendor
    );

$dataReturnVendor =
    mysqli_fetch_assoc(
        $resultReturnVendor
    );

/* RETURN CHART */

$sqlChartReturnVendor = "
    SELECT

        ncvs,

        SUM(
            IFNULL(
                qty_whsubcont_fr_vendor,
                0
            )
            -
            IFNULL(
                qty_whsubcont_to_smsubcont,
                0
            )
        ) AS total_inventory

    FROM tbl_transaksi

    WHERE is_main_komponen = 1

    GROUP BY ncvs

    ORDER BY ncvs ASC
";

$resultChartReturnVendor =
    mysqli_query(
        $conn,
        $sqlChartReturnVendor
    );

$returnVendorCategories = [];
$returnVendorSeries = [];

while (
    $row =
    mysqli_fetch_assoc(
        $resultChartReturnVendor
    )
) {

    $returnVendorCategories[] =
        $row['ncvs'];

    $returnVendorSeries[] =
        (int)$row['total_inventory'];
}

// RESPONSE
$response = [

    'ready_transfer' => [

        'summary' => [

            'receive' =>
            (int)$dataReadyTransfer['total_receive'],

            'transfer' =>
            (int)$dataReadyTransfer['total_transfer'],

            'inventory' =>
            (int)$dataReadyTransfer['total_inventory']

        ],

        'chart' => [

            'categories' =>
            $readyTransferCategories,

            'series' =>
            $readyTransferSeries

        ]

    ],

    'return_vendor' => [

        'summary' => [

            'receive' =>
            (int)$dataReturnVendor['total_receive'],

            'send_prod' =>
            (int)$dataReturnVendor['total_send_prod'],

            'inventory' =>
            (int)$dataReturnVendor['total_inventory']

        ],

        'chart' => [

            'categories' =>
            $returnVendorCategories,

            'series' =>
            $returnVendorSeries

        ]

    ]

];

header('Content-Type: application/json');

echo json_encode($response);
