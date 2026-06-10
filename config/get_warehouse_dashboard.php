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

/* ===================================== */
/* VENDOR MONITORING CONFIG */
/* ===================================== */

$vendorSlaDays = 4;

$fetchRow = static function (
    mysqli $conn,
    string $sql,
    string $context
): array {

    $result = mysqli_query(
        $conn,
        $sql
    );

    if (!$result) {

        throw new RuntimeException(
            $context .
                ': ' .
                mysqli_error($conn)
        );
    }

    return mysqli_fetch_assoc(
        $result
    ) ?: [];
};

/* ===================================== */
/* REQUIRED INPUT PER OUTPUT */
/* ===================================== */

$sqlRequiredInputByOutput = "

    SELECT

        kp.id_output,

        COUNT(
            DISTINCT kp.id_input
        ) AS required_input_count

    FROM tbl_komponen_proses AS kp

    INNER JOIN tbl_komponen AS input_component

        ON input_component.id_komponen =
           kp.id_input

        AND IFNULL(
            input_component.is_deleted,
            0
        ) = 0

    INNER JOIN tbl_komponen AS output_component

        ON output_component.id_komponen =
           kp.id_output

        AND IFNULL(
            output_component.is_deleted,
            0
        ) = 0

    GROUP BY
        kp.id_output

";

/* ===================================== */
/* VENDOR RECEIVE EVENT */
/* ===================================== */

$sqlVendorReceiveEvent = "

    SELECT

        id_trans,

        MIN(
            created_at
        ) AS first_receive_at,

        MAX(
            created_at
        ) AS last_receive_at

    FROM tbl_transaksi_event

    WHERE
        gate = 'VENDOR_FROM_WH_SUBCONT'

    GROUP BY
        id_trans

";

/* ===================================== */
/* COMPONENT RECEIVE PER OUTPUT */
/* ===================================== */

$sqlComponentReceiveByOutput = "

    SELECT

        transaksi.nm_vendor,

        transaksi.job_order,

        transaksi.lot,

        transaksi.size,

        transaksi.id_komponen_out,

        transaksi.id_komponen_in,

        SUM(

            IFNULL(
                transaksi.qty_vendor_fr_whsubcont,
                0
            )

        ) AS receive_qty,

        MIN(
            receive_event.first_receive_at
        ) AS first_receive_at,

        MAX(
            receive_event.last_receive_at
        ) AS last_receive_at

    FROM tbl_transaksi AS transaksi

    LEFT JOIN (

        $sqlVendorReceiveEvent

    ) AS receive_event

        ON receive_event.id_trans =
           transaksi.id_trans

    WHERE

        transaksi.nm_vendor IS NOT NULL

        AND TRIM(
            transaksi.nm_vendor
        ) <> ''

        AND transaksi.id_komponen_out
            IS NOT NULL

    GROUP BY

        transaksi.nm_vendor,

        transaksi.job_order,

        transaksi.lot,

        transaksi.size,

        transaksi.id_komponen_out,

        transaksi.id_komponen_in

";

/* ===================================== */
/* WAREHOUSE RECEIVE OUTPUT */
/* ===================================== */

$sqlWarehouseReceiveByOutput = "

    SELECT

        transaksi.nm_vendor,

        transaksi.job_order,

        transaksi.lot,

        transaksi.size,

        transaksi.id_komponen_out,

        SUM(

            IFNULL(
                transaksi.qty_whsubcont_fr_vendor,
                0
            )

        ) AS warehouse_receive_qty

    FROM tbl_transaksi AS transaksi

    WHERE

        transaksi.is_main_komponen = 1

        AND transaksi.nm_vendor
            IS NOT NULL

        AND TRIM(
            transaksi.nm_vendor
        ) <> ''

        AND transaksi.id_komponen_out
            IS NOT NULL

    GROUP BY

        transaksi.nm_vendor,

        transaksi.job_order,

        transaksi.lot,

        transaksi.size,

        transaksi.id_komponen_out

";

/* ===================================== */
/* VENDOR OUTPUT GROUP */
/* ===================================== */

$sqlVendorOutputGroup = "

    SELECT

        component_receive.nm_vendor,

        component_receive.job_order,

        component_receive.lot,

        component_receive.size,

        component_receive.id_komponen_out,

        required_input.required_input_count,

        COUNT(

            DISTINCT CASE

                WHEN
                    component_receive.receive_qty
                    >
                    0

                THEN
                    component_receive.id_komponen_in

            END

        ) AS received_input_count,

        SUM(
            component_receive.receive_qty
        ) AS raw_component_receive,

        MIN(

            CASE

                WHEN
                    component_receive.receive_qty
                    >
                    0

                THEN
                    component_receive.first_receive_at

            END

        ) AS first_receive_at,

        CASE

            WHEN COUNT(

                DISTINCT CASE

                    WHEN
                        component_receive.receive_qty
                        >
                        0

                    THEN
                        component_receive.id_komponen_in

                END

            ) = required_input.required_input_count

            THEN MAX(
                component_receive.last_receive_at
            )

            ELSE NULL

        END AS complete_set_at,

        CASE

            WHEN COUNT(

                DISTINCT CASE

                    WHEN
                        component_receive.receive_qty
                        >
                        0

                    THEN
                        component_receive.id_komponen_in

                END

            ) = required_input.required_input_count

            THEN MIN(

                CASE

                    WHEN
                        component_receive.receive_qty
                        >
                        0

                    THEN
                        component_receive.receive_qty

                END

            )

            ELSE 0

        END AS complete_set_qty,

        MAX(

            IFNULL(
                warehouse_receive.warehouse_receive_qty,
                0
            )

        ) AS warehouse_receive_qty,

        SUM(

            GREATEST(

                component_receive.receive_qty

                -

                IFNULL(
                    warehouse_receive.warehouse_receive_qty,
                    0
                ),

                0

            )

        ) AS inventory_at_vendor

    FROM (

        $sqlComponentReceiveByOutput

    ) AS component_receive

    INNER JOIN (

        $sqlRequiredInputByOutput

    ) AS required_input

        ON required_input.id_output =
           component_receive.id_komponen_out

    LEFT JOIN (

        $sqlWarehouseReceiveByOutput

    ) AS warehouse_receive

        ON warehouse_receive.nm_vendor =
           component_receive.nm_vendor

        AND warehouse_receive.job_order =
            component_receive.job_order

        AND warehouse_receive.lot =
            component_receive.lot

        AND warehouse_receive.size =
            component_receive.size

        AND warehouse_receive.id_komponen_out =
            component_receive.id_komponen_out

    GROUP BY

        component_receive.nm_vendor,

        component_receive.job_order,

        component_receive.lot,

        component_receive.size,

        component_receive.id_komponen_out,

        required_input.required_input_count

";

/* ===================================== */
/* VENDOR OVERVIEW */
/* ===================================== */

$sqlVendorOverview = "

    SELECT

        COUNT(

            DISTINCT CASE

                WHEN
                    inventory_at_vendor > 0

                THEN
                    nm_vendor

            END

        ) AS active_vendor,

        SUM(
            inventory_at_vendor
        ) AS inventory_at_vendor,

        SUM(
            complete_set_qty
        ) AS total_complete_set_receive,

        SUM(
            warehouse_receive_qty
        ) AS total_returned_output,

        CASE

            WHEN SUM(
                complete_set_qty
            ) > 0

            THEN ROUND(

                (

                    SUM(
                        warehouse_receive_qty
                    )

                    /

                    SUM(
                        complete_set_qty
                    )

                )

                * 100,

                1

            )

            ELSE 0

        END AS return_achievement

    FROM (

        $sqlVendorOutputGroup

    ) AS vendor_monitoring

";

/* ===================================== */
/* WAREHOUSE RECEIVE EVENT */
/* ===================================== */

$sqlWarehouseReceiveEvent = "

    SELECT

        transaksi.nm_vendor,

        transaksi.job_order,

        transaksi.lot,

        transaksi.size,

        transaksi.id_komponen_out,

        receive_event.created_at
            AS warehouse_receive_at,

        SUM(

            IFNULL(
                receive_event.qty,
                0
            )

        ) AS warehouse_receive_qty

    FROM tbl_transaksi AS transaksi

    INNER JOIN tbl_transaksi_event AS receive_event

        ON receive_event.id_trans =
           transaksi.id_trans

    WHERE

        receive_event.gate =
        'WH_SUBCONT_FROM_VENDOR'

        AND transaksi.is_main_komponen = 1

        AND transaksi.nm_vendor
            IS NOT NULL

        AND TRIM(
            transaksi.nm_vendor
        ) <> ''

        AND transaksi.id_komponen_out
            IS NOT NULL

    GROUP BY

        transaksi.nm_vendor,

        transaksi.job_order,

        transaksi.lot,

        transaksi.size,

        transaksi.id_komponen_out,

        receive_event.created_at

";

/* ===================================== */
/* AVERAGE LEAD TIME */
/* ===================================== */

$sqlVendorLeadTime = "

    SELECT

        CASE

            WHEN SUM(
                warehouse_receive.warehouse_receive_qty
            ) > 0

            THEN ROUND(

                SUM(

                    warehouse_receive.warehouse_receive_qty

                    *

                    TIMESTAMPDIFF(

                        SECOND,

                        vendor_output.complete_set_at,

                        warehouse_receive.warehouse_receive_at

                    )

                )

                /

                SUM(
                    warehouse_receive.warehouse_receive_qty
                )

                /

                86400,

                1

            )

            ELSE 0

        END AS average_lead_time

    FROM (

        $sqlVendorOutputGroup

    ) AS vendor_output

    INNER JOIN (

        $sqlWarehouseReceiveEvent

    ) AS warehouse_receive

        ON warehouse_receive.nm_vendor =
           vendor_output.nm_vendor

        AND warehouse_receive.job_order =
            vendor_output.job_order

        AND warehouse_receive.lot =
            vendor_output.lot

        AND warehouse_receive.size =
            vendor_output.size

        AND warehouse_receive.id_komponen_out =
            vendor_output.id_komponen_out

    WHERE

        vendor_output.complete_set_qty > 0

        AND vendor_output.complete_set_at
            IS NOT NULL

        AND warehouse_receive.warehouse_receive_at
            >=
            vendor_output.complete_set_at

";

/* ===================================== */
/* OVERDUE VENDOR */
/* ===================================== */

$sqlOverdueVendor = "

    SELECT

        COUNT(
            DISTINCT nm_vendor
        ) AS overdue_vendor,

        COUNT(*) AS overdue_work_group,

        SUM(
            inventory_at_vendor
        ) AS overdue_inventory

    FROM (

        SELECT

            vendor_output.*,

            CASE

                WHEN
                    received_input_count
                    <
                    required_input_count

                THEN
                    first_receive_at

                ELSE
                    complete_set_at

            END AS overdue_start_at

        FROM (

            $sqlVendorOutputGroup

        ) AS vendor_output

    ) AS vendor_monitoring

    WHERE

        inventory_at_vendor > 0

        AND overdue_start_at
            IS NOT NULL

        AND TIMESTAMPDIFF(

            HOUR,

            overdue_start_at,

            NOW()

        ) > ($vendorSlaDays * 24)

";

/* ===================================== */
/* EXECUTE */
/* ===================================== */

try {

    $dataVendorOverview =
        $fetchRow(
            $conn,
            $sqlVendorOverview,
            'Vendor overview query failed'
        );

    $dataVendorLeadTime =
        $fetchRow(
            $conn,
            $sqlVendorLeadTime,
            'Vendor lead time query failed'
        );

    $dataOverdueVendor =
        $fetchRow(
            $conn,
            $sqlOverdueVendor,
            'Overdue vendor query failed'
        );
} catch (Throwable $error) {

    http_response_code(500);

    echo json_encode([
        'status' => 'error',
        'message' => $error->getMessage()
    ]);

    exit;
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

    ],

    'vendor_overview' => [

        'active_vendor' =>
        (int)(
            $dataVendorOverview['active_vendor']
            ?? 0
        ),

        'inventory_at_vendor' =>
        (int)(
            $dataVendorOverview['inventory_at_vendor']
            ?? 0
        ),

        'inventory_unit' =>
        'Pcs',

        'return_achievement' =>
        (float)(
            $dataVendorOverview['return_achievement']
            ?? 0
        ),

        'total_complete_set_receive' =>
        (int)(
            $dataVendorOverview['total_complete_set_receive']
            ?? 0
        ),

        'total_returned_output' =>
        (int)(
            $dataVendorOverview['total_returned_output']
            ?? 0
        ),

        'average_lead_time' =>
        (float)(
            $dataVendorLeadTime['average_lead_time']
            ?? 0
        ),

        'overdue_vendor' =>
        (int)(
            $dataOverdueVendor['overdue_vendor']
            ?? 0
        ),

        'overdue_work_group' =>
        (int)(
            $dataOverdueVendor['overdue_work_group']
            ?? 0
        ),

        'overdue_inventory' =>
        (int)(
            $dataOverdueVendor['overdue_inventory']
            ?? 0
        ),

        'sla_days' =>
        $vendorSlaDays

    ]

];

header('Content-Type: application/json');

echo json_encode($response);
