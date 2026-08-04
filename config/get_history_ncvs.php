<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');
/** @var mysqli $conn */

require_once "function.php";

$date = mysqli_real_escape_string(
    $conn,
    $_GET['date'] ?? ''
);

if (empty($date)) {

    echo json_encode([
        "data" => []
    ]);

    exit;
}

$sql = "

SELECT DISTINCT

    t.ncvs

FROM tbl_transaksi_event te

INNER JOIN tbl_transaksi t
ON t.id_trans = te.id_trans

WHERE

    DATE(te.created_at) = '$date'

ORDER BY

    t.ncvs ASC

";

$result = mysqli_query($conn, $sql);

$data = [];

while ($row = mysqli_fetch_assoc($result)) {

    $data[] = [

        "id" => $row['ncvs'],

        "text" => $row['ncvs']

    ];

}

echo json_encode([

    "data" => $data

]);