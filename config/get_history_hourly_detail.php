<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');
/** @var mysqli $conn */

require_once "function.php";

$date = mysqli_real_escape_string($conn, $_GET['date'] ?? '');

$ncvs = mysqli_real_escape_string($conn, $_GET['ncvs'] ?? '');

$process = mysqli_real_escape_string($conn, $_GET['process'] ?? '');

$hour = mysqli_real_escape_string($conn, $_GET['hour'] ?? '');

$gate = '';

if ($process == 'IN') {

    $gate = 'SM_SUBCONT_FROM_CUT';
} else {

    $gate = 'SM_SUBCONT_TO_WH_SUBCONT';
}

$joinTime = "";

$hourWhere = "";

if ($hour != "TOTAL") {

    $joinTime = "

    INNER JOIN tbl_time tm

        ON TIME(te.created_at)>=tm.start_hour

        AND TIME(te.created_at)<=tm.end_hour

        AND tm.date_plan='$date'

        AND tm.status=1

    ";

    $hourWhere = "

        AND tm.hour='$hour'

    ";
}

$sql = "

SELECT

t.bucket,

t.style,

t.model,

t.po_code,

t.po_item,

t.nm_komponen_in,

t.size,

SUM(te.qty) AS qty

FROM tbl_transaksi_event te

INNER JOIN tbl_transaksi t

ON t.id_trans=te.id_trans

$joinTime

WHERE

te.gate='$gate'

AND DATE(te.created_at)='$date'

AND t.ncvs='$ncvs'

AND t.is_main_komponen=1

$hourWhere

GROUP BY

t.bucket,

t.style,

t.model,

t.po_code,

t.po_item,

t.nm_komponen_in,

t.size

ORDER BY

t.bucket,

t.model


";

$result = mysqli_query($conn, $sql);

/* ===================================== */
/* PREPARE */
/* ===================================== */

$sizes = [];

$rows = [];

while ($row = mysqli_fetch_assoc($result)) {

    $key = implode('|', [

        $row['bucket'],

        $row['style'],

        $row['model'],

        $row['po_code'],

        $row['po_item'],

        $row['nm_komponen_in']

    ]);

    // Simpan daftar size

    if (!in_array($row['size'], $sizes)) {

        $sizes[] = $row['size'];
    }

    // Init row

    if (!isset($rows[$key])) {

        $rows[$key] = [

            "bucket" => $row['bucket'],

            "style" => $row['style'],

            "model" => $row['model'],

            "po" => $row['po_code'],

            "po_item" => $row['po_item'],

            "component" => $row['nm_komponen_in'],

            "sizes" => []

        ];
    }

    $rows[$key]["sizes"][$row["size"]] = (int)$row["qty"];
}

usort($sizes, function ($a, $b) {

    $aNum = (int)preg_replace('/[^0-9]/', '', $a);

    $bNum = (int)preg_replace('/[^0-9]/', '', $b);

    if ($aNum == $bNum) {

        $aIsT = str_contains($a, 'T');

        $bIsT = str_contains($b, 'T');

        if ($aIsT == $bIsT) {

            return 0;
        }

        return $aIsT ? 1 : -1;
    }

    return $aNum <=> $bNum;
});

echo json_encode([

    "sizes" => $sizes,

    "rows" => array_values($rows)

]);
