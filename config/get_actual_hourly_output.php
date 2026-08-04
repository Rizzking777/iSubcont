<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');
/** @var mysqli $conn */

require_once "function.php";

$sql = "

SELECT

    n.ncvs,
    pd.plan_cycle

FROM tbl_plan_detail pd

INNER JOIN tbl_ncvs n
ON n.id_ncvs = pd.id_ncvs

WHERE

    pd.plan_date = CURDATE()

    AND pd.status = 1

ORDER BY

    n.ncvs ASC

";

$result = mysqli_query($conn, $sql);

$scanInSql = "

SELECT

    t.ncvs,

    tm.hour,

    SUM(te.qty) qty

FROM tbl_transaksi_event te

INNER JOIN tbl_transaksi t

ON t.id_trans=te.id_trans

INNER JOIN tbl_time tm

ON TIME(te.created_at)>=tm.start_hour

AND TIME(te.created_at)<=tm.end_hour

AND tm.date_plan=CURDATE()

AND tm.status = 1

WHERE

te.gate='SM_SUBCONT_FROM_CUT'

AND DATE(te.created_at)=CURDATE()

AND t.is_main_komponen=1

GROUP BY

t.ncvs,

tm.hour

";

$scanInResult = mysqli_query($conn, $scanInSql);

$scanIn = [];

while ($r = mysqli_fetch_assoc($scanInResult)) {

    $scanIn[$r['ncvs']][$r['hour']]

        = (int)$r['qty'];
}

$scanOutSql = "

SELECT

    t.ncvs,

    tm.hour,

    SUM(te.qty) qty

FROM tbl_transaksi_event te

INNER JOIN tbl_transaksi t

ON t.id_trans=te.id_trans

INNER JOIN tbl_time tm

ON TIME(te.created_at)>=tm.start_hour

AND TIME(te.created_at)<=tm.end_hour

AND tm.date_plan=CURDATE()

AND tm.status = 1

WHERE

te.gate='SM_SUBCONT_TO_WH_SUBCONT'

AND DATE(te.created_at)=CURDATE()

AND t.is_main_komponen=1

GROUP BY

t.ncvs,

tm.hour

";

$scanOutResult = mysqli_query($conn, $scanOutSql);

$scanOut = [];

while ($r = mysqli_fetch_assoc($scanOutResult)) {

    $scanOut[$r['ncvs']][$r['hour']]

        = (int)$r['qty'];
}

$data = [];

while ($row = mysqli_fetch_assoc($result)) {

    $hours = [];

    $hoursOut = [];

    $total = 0;

    $totalOut = 0;

    for ($i = 1; $i <= 11; $i++) {

        $hours[$i]

            =

            $scanIn[$row['ncvs']][$i]

            ??

            0;

        $hoursOut[$i]

            =

            $scanOut[$row['ncvs']][$i]

            ??

            0;

        $total += $hours[$i];

        $totalOut += $hoursOut[$i];
    }

    $data[] = [

        "ncvs" => $row['ncvs'],

        "plan_cycle" => (int)$row['plan_cycle'],

        "scan_in" => $hours,

        "scan_in_total" => $total,

        "scan_out" => $hoursOut,

        "scan_out_total" => $totalOut

    ];
}

echo json_encode([
    "data" => $data
]);
