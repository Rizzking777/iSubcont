<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');
/** @var mysqli $conn */

require_once "function.php";

/* ===========================================
   FILTER DATE
=========================================== */

$dateFrom = mysqli_real_escape_string(
    $conn,
    $_GET['date_from'] ?? date('Y-m-d')
);

$dateTo = mysqli_real_escape_string(
    $conn,
    $_GET['date_to'] ?? date('Y-m-d')
);

/* ===========================================
   SUMMARY
=========================================== */

function getSummary($conn, $dateFrom, $dateTo, $gateIn, $gateOut)
{

    $sql = "

    SELECT

        SUM(
            CASE
                WHEN te.gate='$gateIn'
                THEN te.qty
                ELSE 0
            END
        ) total_in,

        SUM(
            CASE
                WHEN te.gate='$gateOut'
                THEN te.qty
                ELSE 0
            END
        ) total_out

    FROM tbl_transaksi_event te

    INNER JOIN tbl_transaksi t

        ON t.id_trans=te.id_trans

    WHERE

t.is_main_komponen = 1

    AND DATE(te.created_at)
        BETWEEN '$dateFrom'
        AND '$dateTo'

    ";

    $result = mysqli_query($conn, $sql);

    $row = mysqli_fetch_assoc($result);

    $in = (int)$row['total_in'];

    $out = (int)$row['total_out'];

    return [

        "in" => $in,

        "out" => $out,

        "inventory" => $in - $out

    ];
}

/* ===========================================
   CHART
=========================================== */

function getChart($conn, $dateFrom, $dateTo, $gateIn, $gateOut)
{

    $sql = "

    SELECT

        t.ncvs,

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

        total_inventory

    FROM tbl_transaksi_event te

INNER JOIN tbl_transaksi t
ON t.id_trans=te.id_trans

WHERE

t.is_main_komponen = 1

    AND DATE(te.created_at)
        BETWEEN '$dateFrom'
        AND '$dateTo'

    GROUP BY

        t.ncvs

    ORDER BY

        t.ncvs

    ";

    $result = mysqli_query($conn, $sql);

    $categories = [];

    $series = [];

    while ($row = mysqli_fetch_assoc($result)) {

        $categories[] = $row['ncvs'];

        $series[] = (int)$row['total_inventory'];
    }

    return [

        "categories" => $categories,

        "series" => $series

    ];
}

/* ===========================================
   RESPONSE
=========================================== */

$response = [

    "cutting" => [

        "summary" => getSummary(

            $conn,

            $dateFrom,

            $dateTo,

            "CUT_TO_SM_SUBCONT",

            "SM_SUBCONT_FROM_CUT"

        ),

        "chart" => getChart(

            $conn,

            $dateFrom,

            $dateTo,

            "CUT_TO_SM_SUBCONT",

            "SM_SUBCONT_FROM_CUT"

        )

    ],

    "incoming_wh" => [

        "summary" => getSummary(

            $conn,

            $dateFrom,

            $dateTo,

            "WH_SUBCONT_FROM_SM_SUBCONT",

            "WH_SUBCONT_TO_VENDOR"

        ),

        "chart" => getChart(

            $conn,

            $dateFrom,

            $dateTo,

            "WH_SUBCONT_FROM_SM_SUBCONT",

            "WH_SUBCONT_TO_VENDOR"

        )

    ],

    "return_wh" => [

        "summary" => getSummary(

            $conn,

            $dateFrom,

            $dateTo,

            "WH_SUBCONT_FROM_VENDOR",

            "WH_SUBCONT_TO_SM_SUBCONT"

        ),

        "chart" => getChart(

            $conn,

            $dateFrom,

            $dateTo,

            "WH_SUBCONT_FROM_VENDOR",

            "WH_SUBCONT_TO_SM_SUBCONT"

        )

    ]

];

echo json_encode($response);
