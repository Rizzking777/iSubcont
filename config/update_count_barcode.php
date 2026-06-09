<?php

header('Content-Type: application/json');

require 'function.php';

$barcodes = $_POST['barcodes'] ?? [];

if (empty($barcodes)) {

    echo json_encode([
        'status' => false,
        'message' => 'Barcode kosong'
    ]);

    exit;
}

foreach ($barcodes as $barcode) {

    $barcode = mysqli_real_escape_string(
        $conn,
        $barcode
    );

    $sql = "
        UPDATE tbl_transaksi
        SET count_barcode =
            IFNULL(count_barcode,0) + 1
        WHERE barcode = '$barcode'
    ";

    mysqli_query($conn, $sql);
}

echo json_encode([
    'status' => true,
    'message' => 'Count barcode berhasil update'
]);