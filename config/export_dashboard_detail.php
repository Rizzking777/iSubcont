<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);

/** @var mysqli $conn */

require_once "function.php";

/* PARAM */
$section = $_GET['section'] ?? '';
$type = $_GET['type'] ?? '';
$ncvs = $_GET['ncvs'] ?? '';

/* DEFAULT */
$where = "1=1";
$qtyFormula = "0";
$mainComponentWhere = "";
$ncvsWhere = "";
$componentField = "
    COALESCE(
        nm_komponen_in,
        '-'
    )
";

/* NCVS FILTER */

if (!empty($ncvs)) {

    $ncvsWhere = "
        AND ncvs = '$ncvs'
    ";
}

/* CUTTING */

if ($section == 'cutting') {

    /* ALL COMPONENT */
    $mainComponentWhere = "";

    /* IN */
    if ($type == 'in') {

        $where = "
            qty_cut_to_smsubcont > 0
        ";

        $qtyFormula = "
            IFNULL(
                qty_cut_to_smsubcont,
                0
            )
        ";
    }

    /* OUT */ else if ($type == 'out') {
        $where = "
            qty_smsubcont_fr_cut > 0
        ";

        $qtyFormula = "
            IFNULL(
                qty_smsubcont_fr_cut,
                0
            )
        ";
    }

    /* INVENTORY */ else if ($type == 'inventory') {
        $where = "
            (
                IFNULL(
                    qty_cut_to_smsubcont,
                    0
                )
                -
                IFNULL(
                    qty_smsubcont_fr_cut,
                    0
                )
            ) > 0
        ";

        $qtyFormula = "
            (
                IFNULL(
                    qty_cut_to_smsubcont,
                    0
                )
                -
                IFNULL(
                    qty_smsubcont_fr_cut,
                    0
                )
            )

        ";
    }
}

/* PRE VENDOR */

if ($section == 'pre_vendor') {

    /* ALL COMPONENT */
    $mainComponentWhere = "";

    /* IN */
    if ($type == 'in') {
        $where = "
            qty_smsubcont_fr_cut > 0
        ";
        $qtyFormula = "
            IFNULL(
                qty_smsubcont_fr_cut,
                0
            )
        ";
    }

    /* OUT */ else if ($type == 'out') {
        $where = "
            qty_smsubcont_to_whsubcont > 0
        ";
        $qtyFormula = "
            IFNULL(
                qty_smsubcont_to_whsubcont,
                0
            )
        ";
    }

    /* INVENTORY */ else if ($type == 'inventory') {
        $where = "
            (
                IFNULL(
                    qty_smsubcont_fr_cut,
                    0
                )
                -
                IFNULL(
                    qty_smsubcont_to_whsubcont,
                    0
                )

            ) > 0
        ";

        $qtyFormula = "
            (
                IFNULL(
                    qty_smsubcont_fr_cut,
                    0
                )
                -
                IFNULL(
                    qty_smsubcont_to_whsubcont,
                    0
                )
            )
        ";
    }
}

/* AFTER VENDOR */
if ($section == 'after_vendor') {

    $componentField = "

        COALESCE(
            nm_komponen_out,
            nm_komponen_in,
            '-'
        )

    ";

    /* MAIN COMPONENT ONLY */
    $mainComponentWhere = "
        AND is_main_komponen = 1
    ";

    /* IN */
    if ($type == 'in') {
        $where = "
            qty_smsubcont_fr_whsubcont > 0
        ";
        $qtyFormula = "
            IFNULL(
                qty_smsubcont_fr_whsubcont,
                0
            )
        ";
    }

    /* OUT */ else if ($type == 'out') {

        $where = "
            qty_smsubcont_to_prod > 0
        ";
        $qtyFormula = "
            IFNULL(
                qty_smsubcont_to_prod,
                0
            )
        ";
    }

    /* INVENTORY */ else if ($type == 'inventory') {
        $where = "
            (
                IFNULL(
                    qty_smsubcont_fr_whsubcont,
                    0
                )
                -
                IFNULL(
                    qty_smsubcont_to_prod,
                    0
                )

            ) > 0
        ";
        $qtyFormula = "
            (
                IFNULL(
                    qty_smsubcont_fr_whsubcont,
                    0
                )
                -
                IFNULL(
                    qty_smsubcont_to_prod,
                    0
                )
            )
        ";
    }
}

/* READY TRANSFER */

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

/* RETURN VENDOR */

if ($section == 'return_vendor') {

    $componentField = "

        COALESCE(
            nm_komponen_out,
            nm_komponen_in,
            '-'
        )

    ";

    /* MAIN COMPONENT ONLY */
    $mainComponentWhere = "
        AND is_main_komponen = 1
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
        $componentField AS component,
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
        component,
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
        $row['component']

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
            'component' => $row['component'],
            'sizes' => []

        ];
    }

    /* SIZE QTY */

    $rows[$key]['sizes'][$row['size']] =
        (int)$row['qty_total'];
}

/* SORT SIZE */

usort($sizes, function ($a, $b) {
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

/* HEADER */

header(
    "Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
);

$fileName = strtoupper($section)
    . "_"
    . strtoupper($type);

header(
    "Content-Disposition: attachment; filename={$fileName}.xlsx"
);

/* TABLE */

echo "
<table border='1'>

<tr>
    <th>NCVS</th>
    <th>Bucket</th>
    <th>Style</th>
    <th>Model</th>
    <th>PO</th>
    <th>PO Item</th>
    <th>Component</th>
";

/* SIZE HEADER */

foreach ($sizes as $size) {

    echo "
        <th>$size</th>
    ";
}

echo "
    <th>Total</th>
</tr>
";

/* BODY */

foreach ($rows as $row) {

    $total = 0;
    echo "<tr>";
    echo "<td>{$row['ncvs']}</td>";
    echo "<td>{$row['bucket']}</td>";
    echo "<td>{$row['style']}</td>";
    echo "<td>{$row['model']}</td>";
    echo "<td>{$row['po']}</td>";
    echo "<td>{$row['po_item']}</td>";
    echo "<td>{$row['component']}</td>";

    foreach ($sizes as $size) {

        $qty =
            $row['sizes'][$size] ?? 0;

        $total += $qty;

        echo "<td>$qty</td>";
    }

    echo "<td><b>$total</b></td>";

    echo "</tr>";
}

echo "</table>";
