<?php
require 'function.php';
header('Content-Type: application/json');

// Ambil request DataTables
$draw   = $_POST['draw'] ?? 1;
$start  = $_POST['start'] ?? 0;
$length = $_POST['length'] ?? 10;

// Filter
$bucket    = $_POST['bucket'] ?? '';
$ncvs      = $_POST['ncvs'] ?? '';
$po_code   = $_POST['po_code'] ?? '';
$job_order = $_POST['job_order'] ?? '';

// ====== FIX: kalau semua filter kosong, balikin data kosong dulu ======
if (empty($bucket) && empty($ncvs) && empty($po_code) && empty($job_order)) {
    echo json_encode([
        "draw" => intval($draw),
        "recordsTotal" => 0,
        "recordsFiltered" => 0,
        "data" => []
    ]);
    exit;
}

// Base query
$sql = "FROM tbl_transaksi t WHERE 1=1";

// Filter dinamis
$where = [];
$params = [];
$types = "";

// kalau ada bucket
if (!empty($bucket)) {
    $where[] = "t.bucket = ?";
    $params[] = $bucket;
    $types   .= "s";
}

// kalau ada ncvs
if (!empty($ncvs)) {
    $where[] = "t.ncvs = ?";
    $params[] = $ncvs;
    $types   .= "s";
}

// kalau ada po_code
if (!empty($po_code)) {
    $where[] = "t.po_code = ?";
    $params[] = $po_code;
    $types   .= "s";
}

// kalau ada job_order
if (!empty($job_order)) {
    $where[] = "t.job_order = ?";
    $params[] = $job_order;
    $types   .= "s";
}

// satukan filter
if (!empty($where)) {
    $sql .= " AND " . implode(" AND ", $where);
}

// Hitung total records
$totalQuery = "SELECT COUNT(*) as cnt " . $sql;
$stmt = $conn->prepare($totalQuery);
if ($types) $stmt->bind_param($types, ...$params);
$stmt->execute();
$totalResult = $stmt->get_result()->fetch_assoc();
$recordsTotal = $totalResult['cnt'] ?? 0;

// Ambil data
$dataQuery = "SELECT 
    t.id_trans, t.job_order, t.ncvs, t.bucket, t.po_code, t.po_item, t.lot, t.model, t.style, t.komponen_qty
    " . $sql . " 
    ORDER BY t.ncvs ASC, t.job_order ASC
    LIMIT ?, ?";

$params2 = $params;
$types2  = $types . "ii";
$params2[] = $start;
$params2[] = $length;

$stmt2 = $conn->prepare($dataQuery);
$stmt2->bind_param($types2, ...$params2);
$stmt2->execute();
$dataResult = $stmt2->get_result();

$data = [];
while ($row = $dataResult->fetch_assoc()) {

    // ============================
    // Decode lot JSON & ubah jadi range (min-max) untuk tampilan
    // ============================
    $displayLot = '';        // Untuk ditampilkan di tabel HTML
    $filterLotArray = [];    // Untuk query/filter backend

    if (!empty($row['lot']) && is_string($row['lot'])) {
        $decodedLot = json_decode($row['lot'], true);

        if (is_array($decodedLot) && count($decodedLot) > 0) {
            $minLot = min($decodedLot);
            $maxLot = max($decodedLot);

            if ($minLot === $maxLot) {
                $displayLot = $minLot;       // Tampilan di tabel
                $filterLotArray = [$minLot]; // array untuk query/filter
            } else {
                $displayLot = $minLot . '-' . $maxLot; // Tampilan min-max
                $filterLotArray = range($minLot, $maxLot); // array lengkap untuk query/filter
            }
        } else {
            $displayLot = htmlspecialchars($row['lot']);
            $filterLotArray = [$row['lot']];
        }
    }

    // --- Ambil Size dari komponen_qty
    $row['size'] = '-';
    if (!empty($row['komponen_qty'])) {
        $kompList = json_decode($row['komponen_qty'], true);
        if (is_array($kompList) && count($kompList) > 0) {
            $sizes = array_map(fn($k) => $k['size'] ?? '-', $kompList);
            usort($sizes, function ($a, $b) {
                $aNum = rtrim($a, 'T');
                $bNum = rtrim($b, 'T');
                if ((int)$aNum !== (int)$bNum) return (int)$aNum - (int)$bNum;
                if (substr($a, -1) === 'T' && substr($b, -1) !== 'T') return 1;
                if (substr($a, -1) !== 'T' && substr($b, -1) === 'T') return -1;
                return 0;
            });

            $sizes = array_unique($sizes);
            $fullText  = htmlspecialchars(implode(', ', $sizes), ENT_QUOTES);
            $shortText = htmlspecialchars(implode(', ', array_slice($sizes, 0, 5)), ENT_QUOTES);

            if (count($sizes) > 5) {
                $row['size'] = "<span class=\"truncate-text\" data-full=\"{$fullText}\" onclick=\"toggleTooltip(this, event)\">{$shortText}...</span>";
            } else {
                $row['size'] = htmlspecialchars(implode(', ', $sizes));
            }
        }
    }

    // ============================
    // Buat link Job Order dengan tampilan range
    // ============================
    $row['job_order'] = '<a href="reports-out-control-detail.php?job_order=' . urlencode($row['job_order']) .
        '&lot=' . urlencode($displayLot) . // tetap 1-18
        '&id_trans=' . urlencode($row['id_trans']) . '" class="btn btn-sm btn-outline-primary" target="_blank">' .
        htmlspecialchars($row['job_order']) . '</a>';

    // ============================
    // Simpan $displayLot untuk ditampilkan di tabel
    // ============================
    $row['lot_display'] = $displayLot; // nanti dipakai di tabel HTML

    $data[] = $row;
}

// ============================
// Kirim response JSON sekali di akhir
// ============================
echo json_encode([
    "draw" => intval($draw),
    "recordsTotal" => $recordsTotal,
    "recordsFiltered" => $recordsTotal,
    "data" => $data
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
