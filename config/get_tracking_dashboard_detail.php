<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

require_once "function.php";
/** @var mysqli $conn */

$response = [
    "status" => false,
    "message" => "Data tidak ditemukan.",
    "summary" => [],
    "lot_detail" => []
];

// VALIDASI
$batch_transaksi = trim(
    $_POST['batch_transaksi'] ?? ''
);

$component = trim(
    $_POST['component'] ?? ''
);

$board = trim(
    $_POST['board'] ?? ''
);

$movement_status = trim(
    $_POST['movement_status'] ?? ''
);

if (
    !$batch_transaksi
    ||
    !$component
    ||
    !$board
    ||
    !$movement_status
) {

    echo json_encode($response);
    exit;
}

// MOVEMENT GATE FILTER
function getMovementGate(
    $board,
    $movement_status
) {

    $map = [

        'SM' => [

            'IN PROCESS' => [
                'SM_SUBCONT_FROM_CUT'
            ],

            'TRANSIT' => [
                'SM_SUBCONT_TO_WH_SUBCONT'
            ]
        ],

        'WH' => [

            'IN PROCESS' => [
                'WH_SUBCONT_FROM_SM_SUBCONT'
            ],

            'TRANSIT' => [
                'WH_SUBCONT_TO_VENDOR'
            ]
        ],

        'Vendor' => [

            'IN PROCESS' => [
                'VENDOR_FROM_WH_SUBCONT'
            ],

            'TRANSIT' => [
                'VENDOR_TO_WH_SUBCONT'
            ]
        ],

        'Ready Transfer' => [

            'IN PROCESS' => [
                'WH_SUBCONT_FROM_VENDOR'
            ],

            'TRANSIT' => [
                'WH_SUBCONT_TO_SM_SUBCONT'
            ]
        ],

        'Ready Pickup' => [

            'READY' => [
                'SM_SUBCONT_FROM_WH_SUBCONT'
            ]
        ],

        'Done' => [

            'FINISH' => [
                'SM_SUBCONT_TO_NCVS'
            ]
        ]
    ];

    return
        $map[$board][$movement_status]
        ?? [];
}

// CURRENT GATE
$gate_filter =
    getMovementGate(
        $board,
        $movement_status
    );

$current_gate =
    $gate_filter[0] ?? '';

if (!$current_gate) {

    echo json_encode([
        "status" => false,
        "message" => "Gate mapping tidak ditemukan."
    ]);

    exit;
}

// GATE LABEL
$gate_label = [

    'SM_SUBCONT_FROM_CUT' =>
    'In SM Subcont',

    'SM_SUBCONT_TO_WH_SUBCONT' =>
    'Out SM Subcont',

    'WH_SUBCONT_FROM_SM_SUBCONT' =>
    'In WH Subcont',

    'WH_SUBCONT_TO_VENDOR' =>
    'Out WH Subcont',

    'VENDOR_FROM_WH_SUBCONT' =>
    'In Vendor',

    'VENDOR_TO_WH_SUBCONT' =>
    'Out Vendor',

    'WH_SUBCONT_FROM_VENDOR' =>
    'Return WH Subcont',

    'WH_SUBCONT_TO_SM_SUBCONT' =>
    'Transfer To SM',

    'SM_SUBCONT_FROM_WH_SUBCONT' =>
    'Return SM Subcont',

    'SM_SUBCONT_TO_NCVS' =>
    'Handover to NCVS'
];

// COMPONENT FIELD LOGIC
$component_case = "

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

";

// SUMMARY
$sql_summary = "

SELECT
    batch_transaksi,
    MAX(model) AS model,
    MAX(style) AS style,
    MAX(ncvs) AS ncvs,
    MAX(bucket) AS bucket,
    MAX(po_code) AS po_code,
    MAX(po_item) AS po_item,
    MAX(nm_vendor) AS vendor

FROM tbl_transaksi

WHERE
    batch_transaksi = ?
    AND barcode_status = 'ACTIVE'
    AND last_gate = ?

GROUP BY batch_transaksi

LIMIT 1

";

$stmt = $conn->prepare($sql_summary);

$stmt->bind_param(
    "ss",
    $batch_transaksi,
    $current_gate
);

$stmt->execute();
$result = $stmt->get_result();
$summary = mysqli_fetch_assoc($result);

if (!$summary) {

    echo json_encode($response);
    exit;
}

// OVERRIDE SUMMARY
$summary['board']
    = $board;

$summary['movement_status']
    = $movement_status;

$summary['last_gate']
    = $current_gate;

$summary['component']
    = $component;



$response['summary']
    = $summary;

// LOT DETAIL
$sql_lot = "

SELECT
    lot,
    size,
    last_gate,
    updated_at,
    transac_by,

    CASE

        WHEN last_gate = 'SM_SUBCONT_FROM_CUT'
        THEN qty_smsubcont_fr_cut

        WHEN last_gate = 'SM_SUBCONT_TO_WH_SUBCONT'
        THEN qty_smsubcont_to_whsubcont

        WHEN last_gate = 'WH_SUBCONT_FROM_SM_SUBCONT'
        THEN qty_whsubcont_fr_smsubcont

        WHEN last_gate = 'WH_SUBCONT_TO_VENDOR'
        THEN qty_whsubcont_to_vendor

        WHEN last_gate = 'VENDOR_FROM_WH_SUBCONT'
        THEN qty_vendor_fr_whsubcont

        WHEN last_gate = 'VENDOR_TO_WH_SUBCONT'
        THEN qty_vendor_to_whsubcont

        WHEN last_gate = 'WH_SUBCONT_FROM_VENDOR'
        THEN qty_whsubcont_fr_vendor

        WHEN last_gate = 'WH_SUBCONT_TO_SM_SUBCONT'
        THEN qty_whsubcont_to_smsubcont

        WHEN last_gate = 'SM_SUBCONT_FROM_WH_SUBCONT'
        THEN qty_smsubcont_fr_whsubcont

        WHEN last_gate = 'SM_SUBCONT_TO_NCVS'
        THEN qty_smsubcont_to_prod

        ELSE 0

    END AS qty

FROM tbl_transaksi

WHERE
    batch_transaksi = ?
    AND barcode_status = 'ACTIVE'
    AND last_gate = ?

ORDER BY

    CAST(lot AS UNSIGNED) ASC,
    size ASC

";

$stmt = $conn->prepare($sql_lot);

$stmt->bind_param(
    "ss",
    $batch_transaksi,
    $current_gate
);

$stmt->execute();

$result = $stmt->get_result();

// FORMAT LOT
$temp_lot = [];

while ($row = mysqli_fetch_assoc($result)) {

    $lot = $row['lot'];

    if (!isset($temp_lot[$lot])) {
        $temp_lot[$lot] = [
            "lot" => $lot,
            "sizes" => [],
            "movement_status" =>
            $movement_status,

            "last_gate_label" =>
            $gate_label[$current_gate]
                ?? $current_gate,
            "timeline_info" =>
            date(
                'd M Y H:i',
                strtotime(
                    $row['updated_at']
                )
            )

                .

                ' - '

                .

                $row['transac_by']
        ];
    }

    // SIZE
    $temp_lot[$lot]['sizes'][$row['size']]
        = (int)$row['qty'];
}

$response['lot_detail']
    = array_values($temp_lot);

// STANDARD TIMELINE
$standard_timeline = [
    'SM_SUBCONT_FROM_CUT',
    'SM_SUBCONT_TO_WH_SUBCONT',
    'WH_SUBCONT_FROM_SM_SUBCONT',
    'WH_SUBCONT_TO_VENDOR',
    'VENDOR_FROM_WH_SUBCONT',
    'VENDOR_TO_WH_SUBCONT',
    'WH_SUBCONT_FROM_VENDOR',
    'WH_SUBCONT_TO_SM_SUBCONT',
    'SM_SUBCONT_FROM_WH_SUBCONT',
    'SM_SUBCONT_TO_NCVS'
];

$current_index =
    array_search(
        $current_gate,
        $standard_timeline
    );

$allowed_gate = array_slice(
    $standard_timeline,
    0,
    $current_index + 1
);

$placeholder =
    implode(
        ',',
        array_fill(
            0,
            count($allowed_gate),
            '?'
        )
    );

// TIMELINE EVENT
$sql_event = "

SELECT
    gate,
    created_at,
    transac_by,
    pickup_name,
    pickup_ncvs,
    pickup_at

FROM tbl_transaksi_event

WHERE
    batch_transaksi = ?
    AND gate IN ($placeholder)

ORDER BY created_at ASC

";

$stmt = $conn->prepare($sql_event);

$params = array_merge(
    [
        $batch_transaksi
    ],
    $allowed_gate
);

$types =
    str_repeat(
        's',
        count($params)
    );

$stmt->bind_param(
    $types,
    ...$params
);

$stmt->execute();

$result = $stmt->get_result();

// FORMAT TIMELINE EVENT
$timeline_event = [];

while ($row = mysqli_fetch_assoc($result)) {

    $timeline_event[$row['gate']] = [

        "datetime" =>

        date(
            'd M Y H:i',
            strtotime(
                $row['created_at']
            )
        ),

        "user" =>
        $row['transac_by'],

        "pickup_name" =>
        $row['pickup_name'],

        "pickup_ncvs" =>
        $row['pickup_ncvs'],

        "pickup_at" =>

        $row['pickup_at']

            ?

            date(
                'd M Y H:i',
                strtotime(
                    $row['pickup_at']
                )
            )

            :

            null
    ];
}

$response['timeline_event']
    = $timeline_event;

// SUCCESS
$response['status']
    = true;

$response['message']
    = "Success";

echo json_encode($response);