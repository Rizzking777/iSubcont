<?php

ini_set(
    'display_errors',
    1
);

error_reporting(
    E_ALL
);

/** @var mysqli $conn */

require_once "function.php";

/* ===================================== */
/* PARAM */
/* ===================================== */

$kpi =
    $_GET['kpi']
    ?? '';

$vendorSlaDays = 4;

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

    exit('Invalid KPI');
}

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

/* ===================================== */
/* KPI QUERY */
/* ===================================== */

$fileLabel = '';
$columns = [];
$sql = '';

switch ($kpi) {

    /* ===================================== */
    /* ACTIVE VENDOR */
    /* ===================================== */

    case 'active_vendor':

        $fileLabel =
            'ACTIVE_VENDOR';

        $columns = [

            'Vendor' =>
            'nm_vendor',

            'Job Order' =>
            'job_order',

            'Lot' =>
            'lot',

            'Size' =>
            'size',

            'Output Component' =>
            'nm_komponen_out',

            'Received Component' =>
            'received_component',

            'Required Component' =>
            'required_component',

            'Complete Set' =>
            'complete_set_qty',

            'WH Return' =>
            'warehouse_receive_qty',

            'Outstanding Component' =>
            'inventory_at_vendor',

            'Status' =>
            'status'

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

        $fileLabel =
            'INVENTORY_AT_VENDOR';

        $columns = [

            'Vendor' =>
            'nm_vendor',

            'Job Order' =>
            'job_order',

            'Lot' =>
            'lot',

            'Size' =>
            'size',

            'Output Component' =>
            'nm_komponen_out',

            'Input Component' =>
            'nm_komponen_in',

            'Vendor Receive' =>
            'receive_qty',

            'WH Return' =>
            'warehouse_receive_qty',

            'Outstanding' =>
            'outstanding_qty'

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

        $fileLabel =
            'RETURN_ACHIEVEMENT';

        $columns = [

            'Vendor' =>
            'nm_vendor',

            'Job Order' =>
            'job_order',

            'Lot' =>
            'lot',

            'Size' =>
            'size',

            'Output Component' =>
            'nm_komponen_out',

            'Received Component' =>
            'received_component',

            'Required Component' =>
            'required_component',

            'Complete Set' =>
            'complete_set_qty',

            'WH Return' =>
            'warehouse_receive_qty',

            'Achievement' =>
            'achievement'

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

        $fileLabel =
            'AVERAGE_LEAD_TIME';

        $columns = [

            'Vendor' =>
            'nm_vendor',

            'Job Order' =>
            'job_order',

            'Lot' =>
            'lot',

            'Size' =>
            'size',

            'Output Component' =>
            'nm_komponen_out',

            'Vendor Start' =>
            'complete_set_at',

            'WH Return At' =>
            'warehouse_receive_at',

            'Return Qty' =>
            'warehouse_receive_qty',

            'Lead Time' =>
            'lead_time_days'

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

        $fileLabel =
            'OVERDUE_VENDOR';

        $columns = [

            'Vendor' =>
            'nm_vendor',

            'Job Order' =>
            'job_order',

            'Lot' =>
            'lot',

            'Size' =>
            'size',

            'Output Component' =>
            'nm_komponen_out',

            'Status' =>
            'status',

            'Start At' =>
            'overdue_start_at',

            'Elapsed' =>
            'elapsed_days',

            'Outstanding Component' =>
            'inventory_at_vendor',

            'SLA' =>
            'sla'

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

    default:

        http_response_code(
            400
        );

        exit('Invalid KPI');
}

/* ===================================== */
/* QUERY */
/* ===================================== */

$result =
    mysqli_query(
        $conn,
        $sql
    );

if (!$result) {

    http_response_code(
        500
    );

    exit('Query export gagal: ' .
        mysqli_error(
            $conn
        ));
}

/* ===================================== */
/* DOWNLOAD HEADER */
/* ===================================== */

$fileName =
    'Vendor_' .
    ucwords(
        strtolower(
            str_replace(
                '_',
                ' ',
                $fileLabel
            )
        )
    ) .
    '.xlsx';

if (
    ob_get_length()
) {

    ob_clean();
}

header(
    'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
);

header(
    'Content-Disposition: attachment; filename="' .
        $fileName .
        '"'
);

header(
    'Cache-Control: max-age=0'
);

/* ===================================== */
/* TABLE */
/* ===================================== */

echo "
<table border='1'>

    <tr>
";

foreach (
    $columns
    as $label => $field
) {

    echo '<th>' .
        htmlspecialchars(
            $label
        ) .
        '</th>';
}

echo '
    </tr>
';

$textFields = [

    'nm_vendor',

    'job_order',

    'lot',

    'size',

    'nm_komponen_out',

    'nm_komponen_in',

    'status',

    'achievement',

    'complete_set_at',

    'warehouse_receive_at',

    'lead_time_days',

    'overdue_start_at',

    'elapsed_days',

    'sla'

];

while (
    $row =
    mysqli_fetch_assoc(
        $result
    )
) {

    echo '<tr>';

    foreach (
        $columns
        as $field
    ) {

        $value =
            (string)(
                $row[$field]
                ?? ''
            );

        $style =
            in_array(
                $field,
                $textFields,
                true
            )
            ? " style=\"mso-number-format:'\\@';\""
            : '';

        echo '<td' .
            $style .
            '>' .
            htmlspecialchars(
                $value
            ) .
            '</td>';
    }

    echo '</tr>';
}

echo '
</table>
';
