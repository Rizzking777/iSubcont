<?php

header('Content-Type: application/json');
require_once "function.php";
/** @var mysqli $conn */

$action = $_POST['action'] ?? "";
$search = trim($_POST['search'] ?? "");
$data = [
    "results" => []
];

try {

    // COMMON FILTER BUILDER
    function buildFilter(&$sql, &$params, &$types)
    {

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

                $sql .= "
                AND DATE(updated_at)
                BETWEEN ? AND ?
            ";

                $params[] = $start;
                $params[] = $end;
                $types .= "ss";
            }
        }

        // NORMAL FILTER
        $filters = [

            'bucket'    => 'bucket',
            'ncvs'      => 'ncvs',
            'po_code'   => 'po_code',
            'po_item'   => 'po_item',
            'job_order' => 'job_order',
            'style'     => 'style',
            'model'     => 'model',
            'nm_vendor' => 'nm_vendor',
            'nm_komponen_in'  => 'nm_komponen_in'

        ];

        foreach ($filters as $post => $field) {

            if (!empty($_POST[$post])) {

                $sql .= "
                AND $field = ?
            ";

                $params[] = $_POST[$post];
                $types .= "s";
            }
        }
    }

    switch ($action) {

        // BUCKET
        case "searchBucket":

            $sql = "
                SELECT DISTINCT
                    bucket AS id,
                    bucket AS text
                FROM tbl_transaksi
                WHERE bucket LIKE ?
            ";

            $params = ["%$search%"];
            $types = "s";
            buildFilter($sql, $params, $types);

            $sql .= "
                ORDER BY bucket ASC
                LIMIT 20
            ";

            break;

        // NCVS
        case "searchNCVS":

            $sql = "
                SELECT DISTINCT
                    ncvs AS id,
                    ncvs AS text
                FROM tbl_transaksi
                WHERE ncvs LIKE ?
            ";

            $params = ["%$search%"];
            $types = "s";
            buildFilter($sql, $params, $types);
            $sql .= "
                ORDER BY ncvs ASC
                LIMIT 20
            ";

            break;

        // PO CODE
        case "searchPOCode":

            $sql = "
                SELECT DISTINCT
                    po_code AS id,
                    po_code AS text
                FROM tbl_transaksi
                WHERE po_code LIKE ?
            ";

            $params = ["%$search%"];
            $types = "s";
            buildFilter($sql, $params, $types);
            $sql .= "
                ORDER BY po_code ASC
                LIMIT 20
            ";

            break;

        // PO ITEM
        case "searchPOItem":

            $sql = "
                SELECT DISTINCT
                    po_item AS id,
                    po_item AS text
                FROM tbl_transaksi
                WHERE po_item LIKE ?
            ";

            $params = ["%$search%"];
            $types = "s";
            buildFilter($sql, $params, $types);
            $sql .= "
                ORDER BY po_item ASC
                LIMIT 20
            ";

            break;

        // JOB ORDER
        case "searchJobOrder":

            $sql = "
                SELECT DISTINCT
                    job_order AS id,
                    job_order AS text
                FROM tbl_transaksi
                WHERE job_order LIKE ?
            ";

            $params = ["%$search%"];
            $types = "s";
            buildFilter($sql, $params, $types);
            $sql .= "
                ORDER BY job_order ASC
                LIMIT 20
            ";

            break;

        // STYLE
        case "searchStyle":

            $sql = "
                SELECT DISTINCT
                    style AS id,
                    style AS text
                FROM tbl_transaksi
                WHERE style LIKE ?
            ";

            $params = ["%$search%"];
            $types = "s";
            buildFilter($sql, $params, $types);
            $sql .= "
                ORDER BY style ASC
                LIMIT 20
            ";

            break;

        // MODEL
        case "searchModel":

            $sql = "
                SELECT DISTINCT
                    model AS id,
                    model AS text
                FROM tbl_transaksi
                WHERE model LIKE ?
            ";

            $params = ["%$search%"];
            $types = "s";
            buildFilter($sql, $params, $types);
            $sql .= "
                ORDER BY model ASC
                LIMIT 20
            ";

            break;

        // VENDOR
        case "searchVendor":

            $sql = "
                SELECT DISTINCT
                    nm_vendor AS id,
                    nm_vendor AS text
                FROM tbl_transaksi
                WHERE nm_vendor LIKE ?
            ";

            $params = ["%$search%"];
            $types = "s";
            buildFilter($sql, $params, $types);
            $sql .= "
                ORDER BY nm_vendor ASC
                LIMIT 20
            ";

            break;

        // Komponen

        case "searchKomponen":

            $sql = "
            SELECT DISTINCT
                nm_komponen_in AS id,
                nm_komponen_in AS text
            FROM tbl_transaksi
            WHERE
                is_main_komponen = 1
                AND nm_komponen_in COLLATE utf8mb4_general_ci LIKE ?
            ";

            $params = ["%$search%"];
            $types = "s";
            buildFilter($sql, $params, $types);
            $sql .= "
                ORDER BY nm_vendor ASC
                LIMIT 20
            ";

            break;

        default:

            echo json_encode($data);

            exit;
    }

    // EXECUTE
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {

        $data['results'][] = $row;
    }

    $stmt->close();
    $conn->close();
} catch (Exception $e) {

    $data['error'] = $e->getMessage();
}

echo json_encode($data);
