<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once "function.php"; // koneksi db

header("Content-Type: application/json"); // selalu JSON

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["error" => "Invalid request"]);
    exit;
}

$action = $_POST['action'] ?? '';

if ($action == "getOptions") {
    $filters = $_POST['filters'] ?? [];
    $response = [];

    $map = [
        "job_order" => "job_order",
        "bucket"    => "bucket",
        "po_code"   => "po_code",
        "po_item"   => "po_item",
        "model"     => "model",
        "style"     => "style",
        "ncvs"      => "ncvs",
        "lot"       => "lot"
    ];

    foreach ($map as $key => $col) {
        $where = [];
        foreach ($filters as $fKey => $val) {
            if ($val && $map[$fKey] != $col) {
                $where[] = "$map[$fKey] = '" . $conn->real_escape_string($val) . "'";
            }
        }

        $sql = "SELECT DISTINCT $col FROM tbl_master_data";
        if (!empty($where)) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }
        $sql .= " ORDER BY $col ASC";

        $result = $conn->query($sql);

        $options = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $val = $row[$col];
                $options[] = ["id" => $val, "text" => $val]; // 🔑 Select2 format
            }
        }
        $response[$key] = $options;
    }

    echo json_encode($response);
    exit;
}


if ($action == "getKomponen") {
    $model = $_POST['model'] ?? '';
   

    $where = ["is_deleted = 0"];
    if ($model) $where[] = "model = '" . $conn->real_escape_string($model) . "'";
    

    $sql = "SELECT id_komponen, nama_komponen FROM tbl_komponen";
    if (!empty($where)) {
        $sql .= " WHERE " . implode(" AND ", $where);
    }
    $sql .= " ORDER BY nama_komponen ASC";

    $result = $conn->query($sql);

    if (!$result) {
        echo json_encode(["error" => $conn->error]);
        exit;
    }

    $options = [];
    while ($row = $result->fetch_assoc()) {
        $options[] = [
            "id" => $row['id_komponen'],
            "text" => $row['nama_komponen']
        ];
    }

    echo json_encode(["komponen" => $options]);
    exit;
}

echo json_encode(["error" => "No valid action"]);
exit;
