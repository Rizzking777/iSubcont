<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');
/** @var mysqli $conn */

require_once "function.php";

/* PARAM */
$section = $_GET['section'] ?? '';
$type = $_GET['type'] ?? '';
$ncvs = $_GET['ncvs'] ?? '';

/* CONDITION */
$where = "";
$qtyFormula = "0";
$mainComponentWhere = "";
$ncvsWhere = "";

/* ===================================== */
/* COMPONENT FIELD */
/* ===================================== */

$componentField = "

    COALESCE(
        nm_komponen_in,
        '-'
    )

";

if (!empty($ncvs)) {

    $ncvsWhere = "
        AND ncvs = '$ncvs'
    ";
}

if ($section == 'ready_transfer') {

    if ($type == 'receive') {

        $where = "
            qty_whsubcont_fr_smsubcont > 0
        ";

        $qtyFormula = "
            IFNULL(
                qty_whsubcont_fr_smsubcont,
                0
            )
        ";
    } else if ($type == 'transfer') {

        $where = "
            qty_whsubcont_to_vendor > 0
        ";

        $qtyFormula = "
            IFNULL(
                qty_whsubcont_to_vendor,
                0
            )
        ";
    } else if ($type == 'inventory') {

        $where = "

            (
                IFNULL(
                    qty_whsubcont_fr_smsubcont,
                    0
                )
                -
                IFNULL(
                    qty_whsubcont_to_vendor,
                    0
                )

            ) > 0

        ";

        $qtyFormula = "

            (
                IFNULL(
                    qty_whsubcont_fr_smsubcont,
                    0
                )
                -
                IFNULL(
                    qty_whsubcont_to_vendor,
                    0
                )
            )

        ";
    }
}

if ($section == 'return_vendor') {

    /* ===================================== */
    /* MAIN COMPONENT ONLY */
    /* ===================================== */

    $mainComponentWhere = "
        AND is_main_komponen = 1
    ";

    /* ===================================== */
    /* USE COMPONENT OUT */
    /* ===================================== */

    $componentField = "

        COALESCE(
            nm_komponen_out,
            nm_komponen_in,
            '-'
        )

    ";

    if ($type == 'receive') {

        $where = "
            qty_whsubcont_fr_vendor > 0
        ";

        $qtyFormula = "
            IFNULL(
                qty_whsubcont_fr_vendor,
                0
            )
        ";
    } else if ($type == 'send_prod') {

        $where = "
            qty_whsubcont_to_smsubcont > 0
        ";

        $qtyFormula = "
            IFNULL(
                qty_whsubcont_to_smsubcont,
                0
            )
        ";
    } else if ($type == 'inventory') {

        $where = "

            (
                IFNULL(
                    qty_whsubcont_fr_vendor,
                    0
                )
                -
                IFNULL(
                    qty_whsubcont_to_smsubcont,
                    0
                )
            ) > 0

        ";

        $qtyFormula = "

            (
                IFNULL(
                    qty_whsubcont_fr_vendor,
                    0
                )
                -
                IFNULL(
                    qty_whsubcont_to_smsubcont,
                    0
                )
            )

        ";
    }
}

/* QUERY */

$sql = "
    SELECT
        ncvs,
        bucket,
        style,
        model,
        po_code,
        po_item,
        $componentField AS main_component,
        size,
        SUM(
            $qtyFormula
        ) AS qty_total

    FROM tbl_transaksi

    WHERE
        $where
        $mainComponentWhere
        $ncvsWhere

    GROUP BY

        ncvs,
        bucket,
        style,
        model,
        po_code,
        po_item,
        main_component,
        size

    ORDER BY
        ncvs ASC

";

$result = mysqli_query($conn, $sql);

/* PREPARE */

$sizes = [];
$rows = [];

/* LOOP */

while ($row = mysqli_fetch_assoc($result)) {

    $key = implode('|', [

        $row['ncvs'],
        $row['bucket'],
        $row['style'],
        $row['model'],
        $row['po_code'],
        $row['po_item'],
        $row['main_component']

    ]);

    /* SIZE */

    if (!in_array($row['size'], $sizes)) {

        $sizes[] = $row['size'];
    }

    /* ROW INIT */

    if (!isset($rows[$key])) {

        $rows[$key] = [

            'ncvs' => $row['ncvs'],
            'bucket' => $row['bucket'],
            'style' => $row['style'],
            'model' => $row['model'],
            'po' => $row['po_code'],
            'po_item' => $row['po_item'],
            'component' =>
            $row['main_component'],
            'sizes' => []

        ];
    }

    /* SIZE QTY */
    $rows[$key]['sizes'][$row['size']] = (int)$row['qty_total'];
}

/* SORT SIZE */
usort($sizes, function ($a, $b) {

    /* NUMBER */
    $aNum = (int) preg_replace(
        '/[^0-9]/',
        '',
        $a
    );

    $bNum = (int) preg_replace(
        '/[^0-9]/',
        '',
        $b
    );

    /* SAME NUMBER */
    if ($aNum == $bNum) {

        /* NORMAL FIRST */

        $aIsT = str_contains($a, 'T');
        $bIsT = str_contains($b, 'T');
        if ($aIsT == $bIsT) {
            return 0;
        }
        return $aIsT ? 1 : -1;
    }

    return $aNum <=> $bNum;
});

/* RESPONSE */

$response = [
    'sizes' => $sizes,
    'rows' => array_values($rows)

];

echo json_encode($response);
