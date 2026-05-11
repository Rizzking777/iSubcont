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
        "status_lot" => "status_lot",
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

if ($action == "getStatusLotDetail") {

    $status_lot = $_POST['status_lot'] ?? '';

    if (!$status_lot) {
        echo json_encode(["success" => false, "error" => "Status lot kosong"]);
        exit;
    }

    $sql = "SELECT DISTINCT job_order, bucket, po_code, po_item, model, style, ncvs
            FROM tbl_master_data
            WHERE status_lot = '" . $conn->real_escape_string($status_lot) . "'
            ORDER BY job_order ASC";

    $result = $conn->query($sql);

    if (!$result || $result->num_rows == 0) {
        echo json_encode(["success" => false, "error" => "Data tidak ditemukan"]);
        exit;
    }

    $job_order = [];
    $bucket = [];
    $po_code = [];
    $po_item = [];
    $model = [];
    $style = [];
    $ncvs = [];

    while ($row = $result->fetch_assoc()) {

        $job_order[] = $row['job_order'];
        $bucket[] = $row['bucket'];
        $po_code[] = $row['po_code'];
        $po_item[] = $row['po_item'];
        $model[] = $row['model'];
        $style[] = $row['style'];
        $ncvs[] = $row['ncvs'];
    }

    $data = [
        "job_order" => implode(", ", $job_order),
        "bucket" => implode(", ", array_unique($bucket)),
        "po_code" => implode(", ", $po_code),
        "po_item" => implode(", ", $po_item),
        "model" => implode(", ", array_unique($model)),
        "style" => implode(", ", array_unique($style)),
        "ncvs" => implode(", ", array_unique($ncvs))
    ];

    echo json_encode([
        "success" => true,
        "data" => $data
    ]);
    exit;
}

if ($action == "getKomponenSizeQtyByStatusLot") {
    $statusLot = $_POST['status_lot'] ?? '';
    if (empty($statusLot)) {
        echo json_encode(["success" => false, "error" => "Status Lot kosong"]);
        exit;
    }

    // Ambil model dari tbl_master_data
    $qModel = $conn->query("SELECT model FROM tbl_master_data WHERE status_lot='$statusLot' LIMIT 1");
    if (!$qModel || $qModel->num_rows == 0) {
        echo json_encode(["success" => false, "error" => "Model tidak ditemukan"]);
        exit;
    }
    $model = $qModel->fetch_assoc()['model'];

    // Ambil semua komponen untuk model ini
    $qKomponen = $conn->query("
        SELECT k.id_komponen, k.nama_komponen
        FROM tbl_komponen k
        INNER JOIN tbl_komponen_proses p ON k.id_komponen = p.id_input
        WHERE k.model='$model' AND k.is_deleted=0
        GROUP BY k.id_komponen, k.nama_komponen
        ORDER BY k.nama_komponen ASC
    ");
    $komponenList = [];
    if ($qKomponen && $qKomponen->num_rows > 0) {
        while ($row = $qKomponen->fetch_assoc()) {
            $komponenList[] = [
                'id' => $row['id_komponen'],
                'nama' => $row['nama_komponen']
            ];
        }
    }

    // Parsing lot input
    $lotInput = $_POST['lot_input'] ?? '';
    $lots = [];
    if (!empty($lotInput)) {
        foreach (explode(',', $lotInput) as $part) {
            $part = trim($part);
            if (preg_match('/^(\d+)\s*-\s*(\d+)$/', $part, $m)) {
                for ($i = (int)$m[1]; $i <= (int)$m[2]; $i++) $lots[] = $i;
            } elseif (is_numeric($part)) {
                $lots[] = (int)$part;
            }
        }
    }

    $lotFilter = '';
    if (!empty($lots)) {
        $lotIn = implode(',', array_map('intval', $lots));
        $lotFilter = " AND CAST(lot AS UNSIGNED) IN ($lotIn)";
    }

    // Ambil total plan per size dari tbl_master_data
    $qSizePlan = $conn->query("
        SELECT size, SUM(qty) AS plan_qty
        FROM tbl_master_data
        WHERE status_lot='$statusLot' $lotFilter
        GROUP BY size
    ");
    $sizePlan = [];
    if ($qSizePlan && $qSizePlan->num_rows > 0) {
        while ($row = $qSizePlan->fetch_assoc()) {
            $sizePlan[$row['size']] = (int)$row['plan_qty'];
        }
    }

    // Ambil total qty yang sudah tersimpan di tbl_transaksi
    $qTrans = $conn->query("
        SELECT komponen_qty
        FROM tbl_transaksi
        WHERE status_lot='$statusLot' $lotFilter
    ");
    $usedQty = [];
    if ($qTrans && $qTrans->num_rows > 0) {
        while ($row = $qTrans->fetch_assoc()) {
            $transData = json_decode($row['komponen_qty'], true);
            if (is_array($transData)) {
                foreach ($transData as $item) {
                    $komponen = $item['komponen'];
                    $size = $item['size'];
                    $qty = (int)$item['qty'];
                    $usedQty[$komponen][$size] = ($usedQty[$komponen][$size] ?? 0) + $qty;
                }
            }
        }
    }

    // Bentuk array akhir dengan sisa qty per komponen
    $data = [];
    foreach ($komponenList as $k) {
        $componentSizes = [];
        foreach ($sizePlan as $size => $planQty) {
            $used = $usedQty[$k['id']][$size] ?? 0;
            $remaining = max(0, $planQty - $used);
            $componentSizes[] = [
                'size' => $size,
                'qty' => $remaining
            ];
        }

        // urutkan size natural
        usort($componentSizes, function ($a, $b) {
            preg_match('/^(\d+)([A-Za-z]*)$/', $a['size'], $aParts);
            preg_match('/^(\d+)([A-Za-z]*)$/', $b['size'], $bParts);
            $aNum = isset($aParts[1]) ? (int)$aParts[1] : 0;
            $bNum = isset($bParts[1]) ? (int)$bParts[1] : 0;
            $aSuffix = $aParts[2] ?? '';
            $bSuffix = $bParts[2] ?? '';
            if ($aNum === $bNum) return strcmp($aSuffix, $bSuffix);
            return $aNum - $bNum;
        });

        $data[] = [
            'id' => $k['id'],
            'nama' => $k['nama'],
            'items' => $componentSizes
        ];
    }

    // Ambil range lot dari tbl_master_data
    $qLot = $conn->query("
        SELECT MIN(CAST(lot AS UNSIGNED)) AS min_lot, 
               MAX(CAST(lot AS UNSIGNED)) AS max_lot
        FROM tbl_master_data
        WHERE status_lot='$statusLot'
    ");
    $lotRange = "";
    if ($qLot && $row = $qLot->fetch_assoc()) {
        if ($row['min_lot'] && $row['max_lot']) {
            $lotRange = ($row['min_lot'] == $row['max_lot'])
                ? (string)$row['min_lot']
                : "{$row['min_lot']}-{$row['max_lot']}";
        }
    }

    echo json_encode([
        "success" => true,
        "data" => $data,
        "lot_range" => $lotRange
    ]);
    exit;
}

// ==============================
// Endpoint tambahan untuk ambil range LOT
// ==============================
if ($action == 'getLotRangeByStatusLot') {
    $statusLot = $_POST['status_lot'] ?? '';

    $stmt = $conn->prepare("
        SELECT MIN(CAST(lot AS UNSIGNED)) AS min_lot, 
               MAX(CAST(lot AS UNSIGNED)) AS max_lot
        FROM tbl_master_data
        WHERE status_lot = ?
    ");
    $stmt->bind_param('s', $statusLot);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();

    if ($result && $result['min_lot'] && $result['max_lot']) {
        $lotRange = ($result['min_lot'] == $result['max_lot'])
            ? (string)$result['min_lot']
            : "{$result['min_lot']}-{$result['max_lot']}";

        echo json_encode([
            'success' => true,
            'lot_range' => $lotRange
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Lot tidak ditemukan'
        ]);
    }
    exit;
}

if ($action == "getSize") {
    $status_lot = $_POST['status_lot'] ?? '';

    if (!$status_lot) {
        echo json_encode(["sizes" => []]);
        exit;
    }

    $sql = "SELECT DISTINCT size FROM tbl_master_data 
            WHERE status_lot = '" . $conn->real_escape_string($status_lot) . "'
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

if ($action == "searchStatusLot") {
    $search = $_POST['search'] ?? '';

    $sql = "SELECT DISTINCT status_lot FROM tbl_master_data WHERE 1=1";
    if ($search) {
        $sql .= " AND status_lot LIKE '%" . $conn->real_escape_string($search) . "%'";
    }
    $sql .= " ORDER BY status_lot ASC LIMIT 50";

    $result = $conn->query($sql);

    $options = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $val = $row['status_lot'];
            $options[] = ["id" => $val, "text" => $val];
        }
    }

    echo json_encode(["status_lot" => $options]);
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
    $status_lot = $_POST['status_lot'] ?? '';
    $search    = $_POST['search'] ?? '';

    $sql = "SELECT DISTINCT size FROM tbl_master_data WHERE status_lot = '" . $conn->real_escape_string($status_lot) . "'";
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
