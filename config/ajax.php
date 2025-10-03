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

    $sql = "SELECT id_komponen, nama_komponen 
            FROM tbl_komponen 
            WHERE " . implode(" AND ", $where) . "
            ORDER BY nama_komponen ASC";

    $result = $conn->query($sql);
    $options = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $options[] = [
                "id" => $row['id_komponen'],
                "text" => $row['nama_komponen']
            ];
        }
    }

    echo json_encode(["komponen" => $options]);
    exit;
}

if ($action == "getJobOrderDetail") {
    $job_order = $_POST['job_order'] ?? '';

    if (!$job_order) {
        echo json_encode(["success" => false, "error" => "Job order kosong"]);
        exit;
    }

    $sql = "SELECT job_order, bucket, po_code, po_item, model, style, ncvs, size, lot 
            FROM tbl_master_data 
            WHERE job_order = '" . $conn->real_escape_string($job_order) . "' 
            LIMIT 1";

    $result = $conn->query($sql);

    if ($result && $row = $result->fetch_assoc()) {
        echo json_encode([
            "success" => true,
            "data" => $row
        ]);
    } else {
        echo json_encode(["success" => false, "error" => "Data tidak ditemukan"]);
    }
    exit;
}

if ($action == "getSize") {
    $job_order = $_POST['job_order'] ?? '';

    if (!$job_order) {
        echo json_encode(["sizes" => []]);
        exit;
    }

    $sql = "SELECT DISTINCT size FROM tbl_master_data 
            WHERE job_order = '" . $conn->real_escape_string($job_order) . "'
            ORDER BY size ASC";

    $result = $conn->query($sql);

    $sizes = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $sizes[] = $row['size'];
        }
    }

    echo json_encode(["sizes" => $sizes]);
    exit;
}

if ($action == "searchJobOrder") {
    $search = $_POST['search'] ?? '';

    $sql = "SELECT DISTINCT job_order FROM tbl_master_data WHERE 1=1";
    if ($search) {
        $sql .= " AND job_order LIKE '%" . $conn->real_escape_string($search) . "%'";
    }
    $sql .= " ORDER BY job_order ASC LIMIT 50";

    $result = $conn->query($sql);

    $options = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $val = $row['job_order'];
            $options[] = ["id" => $val, "text" => $val];
        }
    }

    echo json_encode(["job_order" => $options]);
    exit;
}

if ($action == "searchKomponen") {
    $search = $_POST['search'] ?? '';
    $model  = $_POST['model'] ?? '';

    $sql = "SELECT id_komponen, nama_komponen 
            FROM tbl_komponen 
            WHERE is_deleted = 0";
    if ($model) {
        $sql .= " AND model = '" . $conn->real_escape_string($model) . "'";
    }
    if ($search) {
        $sql .= " AND nama_komponen LIKE '%" . $conn->real_escape_string($search) . "%'";
    }
    $sql .= " ORDER BY nama_komponen ASC LIMIT 50";

    $result = $conn->query($sql);

    $options = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $options[] = ["id" => $row['id_komponen'], "text" => $row['nama_komponen']];
        }
    }
    echo json_encode(["komponen" => $options]);
    exit;
}

if ($action == "searchSize") {
    $job_order = $_POST['job_order'] ?? '';
    $search    = $_POST['search'] ?? '';

    $sql = "SELECT DISTINCT size FROM tbl_master_data WHERE job_order = '" . $conn->real_escape_string($job_order) . "'";
    if ($search) {
        $sql .= " AND size LIKE '%" . $conn->real_escape_string($search) . "%'";
    }
    $sql .= " ORDER BY size ASC LIMIT 50";

    $result = $conn->query($sql);
    $options = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $options[] = ["id" => $row['size'], "text" => $row['size']];
        }
    }
    echo json_encode(["sizes" => $options]);
    exit;
}


echo json_encode(["error" => "No valid action"]);
exit;
