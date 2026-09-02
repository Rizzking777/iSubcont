<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');
/** @var mysqli $conn */

require_once "function.php";

$data = [
    "status" => true,
    "boards" => [
    "SM" => [],
    "WH" => [],
    "Vendor" => [],
    "Ready Transfer" => [],
    "Ready Pickup" => [],
    "Done" => []
    ]
];

// FILTER
$where = "WHERE barcode_status = 'ACTIVE'
          AND is_main_komponen = 1";

$params = [];
$types = "";

$filters = [
    'bucket'  => 'bucket',
    'ncvs'    => 'ncvs',
    'po_code' => 'po_code',
    'po_item' => 'po_item',
    'style'   => 'style',
    'model'   => 'model',
    'nm_vendor'  => 'nm_vendor'
];

foreach ($filters as $post => $field) {

    if (!empty($_POST[$post])) {
        $where .= " AND $field = ?";
        $params[] = $_POST[$post];
        $types .= "s";
    }
}

// DATE RANGE
if (!empty($_POST['date_range'])) {

    $date = explode(
        ' - ',
        $_POST['date_range']
    );

    if (count($date) == 2) {

        $start = date(
            'Y-m-d',
            strtotime(trim($date[0]))
        );

        $end = date(
            'Y-m-d',
            strtotime(trim($date[1]))
        );

        $where .= "
            AND DATE(updated_at)
            BETWEEN ? AND ?
        ";

        $params[] = $start;
        $params[] = $end;
        $types .= "ss";
    }
}

// QUERY
$sql = "

SELECT
    batch_transaksi,
    COUNT(DISTINCT lot) AS total_lot,
    MAX(po_code) AS po_code,
    MAX(po_item) AS po_item,
    MAX(ncvs) AS ncvs,
    MAX(bucket) AS bucket,
    MAX(style) AS style,
    MAX(model) AS model,
    MAX(nm_vendor) AS nm_vendor,
    MAX(last_gate) AS last_gate,
    DATE_FORMAT(
        MAX(updated_at),
        '%d %b %Y'
    ) AS updated_at,

    CASE

        WHEN MAX(last_gate) IN (
        'VENDOR_TO_WH_SUBCONT',
        'WH_SUBCONT_FROM_VENDOR',
        'WH_SUBCONT_TO_SM_SUBCONT',
        'SM_SUBCONT_FROM_WH_SUBCONT',
        'SM_SUBCONT_TO_NCVS'
    )

        THEN MAX(nm_komponen_out)
        ELSE MAX(nm_komponen_in)
    END AS dashboard_component

FROM tbl_transaksi

$where

GROUP BY
    batch_transaksi,
    last_gate,

    CASE
        WHEN last_gate IN (
        'VENDOR_TO_WH_SUBCONT',
        'WH_SUBCONT_FROM_VENDOR',
        'WH_SUBCONT_TO_SM_SUBCONT',
        'SM_SUBCONT_FROM_WH_SUBCONT',
        'SM_SUBCONT_TO_NCVS'

        )

        THEN nm_komponen_out
        ELSE nm_komponen_in

    END

ORDER BY updated_at DESC
";

$stmt = $conn->prepare($sql);

if (!empty($params)) {

    $stmt->bind_param(
        $types,
        ...$params
    );
}

$stmt->execute();

$result = $stmt->get_result();

// GROUP BOARD + MOVEMENT STATUS
while ($row = mysqli_fetch_assoc($result)) {

    $gate = $row['last_gate'];

    // DEFAULT
    $row['movement_status'] = '';
    $row['movement_color']  = '';

    // SM
    if (in_array($gate, [

        'SM_SUBCONT_FROM_CUT',
        'SM_SUBCONT_TO_WH_SUBCONT'

    ])) {

        // BADGE
        if ($gate == 'SM_SUBCONT_FROM_CUT') {

            $row['movement_status'] = 'IN PROCESS';
            $row['movement_color']  = 'primary';
        } else {

            $row['movement_status'] = 'TRANSIT';
            $row['movement_color']  = 'warning';
        }

        $data['boards']['SM'][] = $row;
    }

    // WH
    else if (in_array($gate, [

        'WH_SUBCONT_FROM_SM_SUBCONT',
        'WH_SUBCONT_TO_VENDOR'

    ])) {

        // BADGE
        if ($gate == 'WH_SUBCONT_FROM_SM_SUBCONT') {

            $row['movement_status'] = 'IN PROCESS';
            $row['movement_color']  = 'primary';
        } else {

            $row['movement_status'] = 'TRANSIT';
            $row['movement_color']  = 'warning';
        }

        $data['boards']['WH'][] = $row;
    }

    // VENDOR
    else if (in_array($gate, [

        'VENDOR_FROM_WH_SUBCONT',
        'VENDOR_TO_WH_SUBCONT'

    ])) {

        // BADGE
        if ($gate == 'VENDOR_FROM_WH_SUBCONT') {

            $row['movement_status'] = 'IN PROCESS';
            $row['movement_color']  = 'primary';
        } else {

            $row['movement_status'] = 'TRANSIT';
            $row['movement_color']  = 'warning';
        }

        $data['boards']['Vendor'][] = $row;
    }

    // READY TRANSFER
    else if (in_array($gate, [

        'WH_SUBCONT_FROM_VENDOR',
        'WH_SUBCONT_TO_SM_SUBCONT'

    ])) {

        // BADGE
        if ($gate == 'WH_SUBCONT_FROM_VENDOR') {

            $row['movement_status'] = 'IN PROCESS';
            $row['movement_color']  = 'info';
        } else {

            $row['movement_status'] = 'TRANSIT';
            $row['movement_color']  = 'warning';
        }

        $data['boards']['Ready Transfer'][] = $row;
    }

    // READY PICKUP
    else if ($gate == 'SM_SUBCONT_FROM_WH_SUBCONT') {

        $row['movement_status'] = 'READY';
        $row['movement_color']  = 'success';

        $data['boards']['Ready Pickup'][] = $row;
    }

    // DONE
    else if ($gate == 'SM_SUBCONT_TO_NCVS') {

        $row['movement_status'] = 'FINISH';
        $row['movement_color']  = 'dark';

        $data['boards']['Done'][] = $row;
    }
}

echo json_encode($data);