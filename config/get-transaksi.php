<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');
/** @var mysqli $conn */

require 'function.php';

if (!$conn) {

    echo json_encode([
        'status' => false,
        'message' => 'Koneksi database gagal'
    ]);

    exit;
}

$job_order = $_GET['job_order'] ?? '';

if (empty($job_order)) {

    echo json_encode([]);

    exit;
}

$sql = "

SELECT

    barcode,

    MAX(job_order) AS job_order,
    MAX(bucket) AS bucket,
    MAX(po_code) AS po_code,
    MAX(po_item) AS po_item,
    MAX(model) AS model,
    MAX(style) AS style,
    MAX(ncvs) AS ncvs,
    MAX(is_main_komponen) AS is_main_komponen,
    MAX(count_barcode) AS count_barcode,
    MAX(qty_smsubcont_fr_cut) AS qty_smsubcont_fr_cut,
    MAX(
    CASE
        WHEN is_main_komponen = 1
        THEN nm_komponen_in
    END
) AS nm_komponen_main,

    GROUP_CONCAT(DISTINCT lot ORDER BY lot SEPARATOR ', ') AS lot,

    GROUP_CONCAT(
        DISTINCT CONCAT(size, ' (', qty_cut_to_smsubcont, ')')
        ORDER BY size
        SEPARATOR ', '
    ) AS size_detail,

    SUM(qty_cut_to_smsubcont) AS total_qty,

    GROUP_CONCAT(
        DISTINCT nm_komponen_in
        SEPARATOR ', '
    ) AS nm_komponen_in,

    MAX(transac_by) AS transac_by,
    MAX(created_at) AS created_at

FROM tbl_transaksi

WHERE job_order = '$job_order'

GROUP BY barcode

ORDER BY created_at, count_barcode DESC

";

$query = mysqli_query($conn, $sql);

if (!$query) {

    echo json_encode([
        'status' => false,
        'message' => mysqli_error($conn)
    ]);

    exit;
}

$data = [];

while ($row = mysqli_fetch_assoc($query)) {

    $data[] = $row;
}

echo json_encode($data);
