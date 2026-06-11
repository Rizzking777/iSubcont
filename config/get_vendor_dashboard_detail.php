<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');
/** @var mysqli $conn */

require_once "function.php";

$vendorSlaDays = 4;

$kpi =
    $_GET['kpi']
    ?? '';

$allowedKpi = [

    'active_vendor',

    'inventory_at_vendor',

    'return_achievement',

    'average_lead_time',

    'overdue_vendor'

];

if (
    !in_array(
        $kpi,
        $allowedKpi,
        true
    )
) {

    http_response_code(
        400
    );

    echo json_encode([
        'status' =>
        'error',

        'message' =>
        'Invalid KPI'
    ]);

    exit;
}

$fetchRows = static function (
    mysqli $conn,
    string $sql
): array {

    $result =
        mysqli_query(
            $conn,
            $sql
        );

    return mysqli_fetch_all(
        $result,
        MYSQLI_ASSOC
    );
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

        MAX(
            transaksi.nm_komponen_out
        ) AS nm_komponen_out,

        transaksi.id_komponen_in,

        MAX(
            transaksi.nm_komponen_in
        ) AS nm_komponen_in,

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

        transaksi.nm_vendor
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

        MAX(
            transaksi.nm_komponen_out
        ) AS nm_komponen_out,

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

        MAX(
            component_receive.nm_komponen_out
        ) AS nm_komponen_out,

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

$title = '';
$subtitle = '';
$columns = [];
$sql = '';


try {

    switch ($kpi) {

        /* ===================================== */
        /* ACTIVE VENDOR */
        /* ===================================== */

        case 'active_vendor':

            $title =
                'Detail Active Vendor';

            $subtitle =
                'Vendor dengan proses aktif';

            $columns = [

                [
                    'key' =>
                    'nm_vendor',

                    'label' =>
                    'Vendor'
                ],

                [
                    'key' =>
                    'job_order',

                    'label' =>
                    'Job Order'
                ],

                [
                    'key' =>
                    'lot',

                    'label' =>
                    'Lot'
                ],

                [
                    'key' =>
                    'size',

                    'label' =>
                    'Size'
                ],

                [
                    'key' =>
                    'nm_komponen_out',

                    'label' =>
                    'Output Component'
                ],

                [
                    'key' =>
                    'received_component',

                    'label' =>
                    'Received Component'
                ],

                [
                    'key' =>
                    'required_component',

                    'label' =>
                    'Required Component'
                ],

                [
                    'key' =>
                    'complete_set_qty',

                    'label' =>
                    'Complete Set'
                ],

                [
                    'key' =>
                    'warehouse_receive_qty',

                    'label' =>
                    'WH Return'
                ],

                [
                    'key' =>
                    'inventory_at_vendor',

                    'label' =>
                    'Outstanding Component'
                ],

                [
                    'key' =>
                    'status',

                    'label' =>
                    'Status'
                ]

            ];

            $sql = "

                SELECT

                    nm_vendor,

                    job_order,

                    lot,

                    size,

                    nm_komponen_out,

                    received_input_count
                        AS received_component,

                    required_input_count
                        AS required_component,

                    complete_set_qty,

                    warehouse_receive_qty,

                    inventory_at_vendor,

                    CASE

                        WHEN
                            received_input_count
                            <
                            required_input_count

                        THEN
                            'Partial'

                        ELSE
                            'On Process'

                    END AS status

                FROM (

                    $sqlVendorOutputGroup

                ) AS vendor_monitoring

                WHERE
                    inventory_at_vendor > 0

                ORDER BY

                    nm_vendor ASC,

                    job_order ASC,

                    lot ASC,

                    size ASC

            ";

            break;

        /* ===================================== */
        /* INVENTORY AT VENDOR */
        /* ===================================== */

        case 'inventory_at_vendor':

            $title =
                'Detail Inventory at Vendor';

            $subtitle =
                'Outstanding component qty per input component';

            $columns = [

                [
                    'key' =>
                    'nm_vendor',

                    'label' =>
                    'Vendor'
                ],

                [
                    'key' =>
                    'job_order',

                    'label' =>
                    'Job Order'
                ],

                [
                    'key' =>
                    'lot',

                    'label' =>
                    'Lot'
                ],

                [
                    'key' =>
                    'size',

                    'label' =>
                    'Size'
                ],

                [
                    'key' =>
                    'nm_komponen_out',

                    'label' =>
                    'Output Component'
                ],

                [
                    'key' =>
                    'nm_komponen_in',

                    'label' =>
                    'Input Component'
                ],

                [
                    'key' =>
                    'receive_qty',

                    'label' =>
                    'Vendor Receive'
                ],

                [
                    'key' =>
                    'warehouse_receive_qty',

                    'label' =>
                    'WH Return'
                ],

                [
                    'key' =>
                    'outstanding_qty',

                    'label' =>
                    'Outstanding'
                ]

            ];

            $sql = "

                SELECT

                    component_receive.nm_vendor,

                    component_receive.job_order,

                    component_receive.lot,

                    component_receive.size,

                    component_receive.nm_komponen_out,

                    component_receive.nm_komponen_in,

                    component_receive.receive_qty,

                    IFNULL(
                        warehouse_receive.warehouse_receive_qty,
                        0
                    ) AS warehouse_receive_qty,

                    GREATEST(

                        component_receive.receive_qty

                        -

                        IFNULL(
                            warehouse_receive.warehouse_receive_qty,
                            0
                        ),

                        0

                    ) AS outstanding_qty

                FROM (

                    $sqlComponentReceiveByOutput

                ) AS component_receive

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

                WHERE

                    component_receive.receive_qty > 0

                    AND GREATEST(

                        component_receive.receive_qty

                        -

                        IFNULL(
                            warehouse_receive.warehouse_receive_qty,
                            0
                        ),

                        0

                    ) > 0

                ORDER BY

                    component_receive.nm_vendor ASC,

                    component_receive.job_order ASC,

                    component_receive.lot ASC,

                    component_receive.size ASC,

                    component_receive.nm_komponen_in ASC

            ";

            break;

        /* ===================================== */
        /* RETURN ACHIEVEMENT */
        /* ===================================== */

        case 'return_achievement':

            $title =
                'Detail Return Achievement';

            $subtitle =
                'WH return dibandingkan complete set output';

            $columns = [

                [
                    'key' =>
                    'nm_vendor',

                    'label' =>
                    'Vendor'
                ],

                [
                    'key' =>
                    'job_order',

                    'label' =>
                    'Job Order'
                ],

                [
                    'key' =>
                    'lot',

                    'label' =>
                    'Lot'
                ],

                [
                    'key' =>
                    'size',

                    'label' =>
                    'Size'
                ],

                [
                    'key' =>
                    'nm_komponen_out',

                    'label' =>
                    'Output Component'
                ],

                [
                    'key' =>
                    'received_component',

                    'label' =>
                    'Received Component'
                ],

                [
                    'key' =>
                    'required_component',

                    'label' =>
                    'Required Component'
                ],

                [
                    'key' =>
                    'complete_set_qty',

                    'label' =>
                    'Complete Set'
                ],

                [
                    'key' =>
                    'warehouse_receive_qty',

                    'label' =>
                    'WH Return'
                ],

                [
                    'key' =>
                    'achievement',

                    'label' =>
                    'Achievement'
                ]

            ];

            $sql = "

                SELECT

                    nm_vendor,

                    job_order,

                    lot,

                    size,

                    nm_komponen_out,

                    received_input_count
                        AS received_component,

                    required_input_count
                        AS required_component,

                    complete_set_qty,

                    warehouse_receive_qty,

                    CONCAT(

                        CASE

                            WHEN
                                complete_set_qty
                                >
                                0

                            THEN ROUND(

                                (

                                    warehouse_receive_qty

                                    /

                                    complete_set_qty

                                )

                                * 100,

                                1

                            )

                            ELSE 0

                        END,

                        '%'

                    ) AS achievement

                FROM (

                    $sqlVendorOutputGroup

                ) AS vendor_monitoring

                WHERE
                    raw_component_receive > 0

                ORDER BY

                    nm_vendor ASC,

                    job_order ASC,

                    lot ASC,

                    size ASC

            ";

            break;

        /* ===================================== */
        /* AVERAGE LEAD TIME */
        /* ===================================== */

        case 'average_lead_time':

            $title =
                'Detail Average Lead Time';

            $subtitle =
                'Durasi dari complete set vendor sampai WH menerima output';

            $columns = [

                [
                    'key' =>
                    'nm_vendor',

                    'label' =>
                    'Vendor'
                ],

                [
                    'key' =>
                    'job_order',

                    'label' =>
                    'Job Order'
                ],

                [
                    'key' =>
                    'lot',

                    'label' =>
                    'Lot'
                ],

                [
                    'key' =>
                    'size',

                    'label' =>
                    'Size'
                ],

                [
                    'key' =>
                    'nm_komponen_out',

                    'label' =>
                    'Output Component'
                ],

                [
                    'key' =>
                    'complete_set_at',

                    'label' =>
                    'Vendor Start'
                ],

                [
                    'key' =>
                    'warehouse_receive_at',

                    'label' =>
                    'WH Return At'
                ],

                [
                    'key' =>
                    'warehouse_receive_qty',

                    'label' =>
                    'Return Qty'
                ],

                [
                    'key' =>
                    'lead_time_days',

                    'label' =>
                    'Lead Time'
                ]

            ];

            $sql = "

                SELECT

                    vendor_output.nm_vendor,

                    vendor_output.job_order,

                    vendor_output.lot,

                    vendor_output.size,

                    vendor_output.nm_komponen_out,

                    vendor_output.complete_set_at,

                    warehouse_receive.warehouse_receive_at,

                    warehouse_receive.warehouse_receive_qty,

                    CONCAT(

                        ROUND(

                            TIMESTAMPDIFF(

                                SECOND,

                                vendor_output.complete_set_at,

                                warehouse_receive.warehouse_receive_at

                            )

                            /

                            86400,

                            1

                        ),

                        ' Days'

                    ) AS lead_time_days

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

                ORDER BY

                    warehouse_receive.warehouse_receive_at
                    DESC

            ";

            break;

        /* ===================================== */
        /* OVERDUE VENDOR */
        /* ===================================== */

        case 'overdue_vendor':

            $title =
                'Detail Overdue Vendor';

            $subtitle =
                'Proses vendor melewati SLA ' .
                $vendorSlaDays .
                ' hari';

            $columns = [

                [
                    'key' =>
                    'nm_vendor',

                    'label' =>
                    'Vendor'
                ],

                [
                    'key' =>
                    'job_order',

                    'label' =>
                    'Job Order'
                ],

                [
                    'key' =>
                    'lot',

                    'label' =>
                    'Lot'
                ],

                [
                    'key' =>
                    'size',

                    'label' =>
                    'Size'
                ],

                [
                    'key' =>
                    'nm_komponen_out',

                    'label' =>
                    'Output Component'
                ],

                [
                    'key' =>
                    'status',

                    'label' =>
                    'Status'
                ],

                [
                    'key' =>
                    'overdue_start_at',

                    'label' =>
                    'Start At'
                ],

                [
                    'key' =>
                    'elapsed_days',

                    'label' =>
                    'Elapsed'
                ],

                [
                    'key' =>
                    'inventory_at_vendor',

                    'label' =>
                    'Outstanding Component'
                ],

                [
                    'key' =>
                    'sla',

                    'label' =>
                    'SLA'
                ]

            ];

            $sql = "

                SELECT

                    vendor_monitoring.nm_vendor,

                    vendor_monitoring.job_order,

                    vendor_monitoring.lot,

                    vendor_monitoring.size,

                    vendor_monitoring.nm_komponen_out,

                    vendor_monitoring.status,

                    vendor_monitoring.overdue_start_at,

                    CONCAT(

                        ROUND(

                            TIMESTAMPDIFF(

                                HOUR,

                                vendor_monitoring.overdue_start_at,

                                NOW()

                            )

                            /

                            24,

                            1

                        ),

                        ' Days'

                    ) AS elapsed_days,

                    vendor_monitoring.inventory_at_vendor,

                    '{$vendorSlaDays} Days'
                        AS sla

                FROM (

                    SELECT

                        vendor_output.*,

                        CASE

                            WHEN
                                received_input_count
                                <
                                required_input_count

                            THEN
                                'Partial Component'

                            ELSE
                                'Complete Set WIP'

                        END AS status,

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

                    vendor_monitoring.inventory_at_vendor
                    >
                    0

                    AND vendor_monitoring.overdue_start_at
                        IS NOT NULL

                    AND TIMESTAMPDIFF(

                        HOUR,

                        vendor_monitoring.overdue_start_at,

                        NOW()

                    ) > ($vendorSlaDays * 24)

                ORDER BY

                    vendor_monitoring.overdue_start_at
                    ASC

            ";

            break;
    }

    $rows =
        $fetchRows(
            $conn,
            $sql
        );

    echo json_encode([

        'status' =>
        'success',

        'title' =>
        $title,

        'subtitle' =>
        $subtitle,

        'columns' =>
        $columns,

        'rows' =>
        $rows

    ]);
} catch (
    Throwable $error
) {

    http_response_code(
        500
    );

    echo json_encode([

        'status' =>
        'error',

        'message' =>
        $error->getMessage()

    ]);
}
