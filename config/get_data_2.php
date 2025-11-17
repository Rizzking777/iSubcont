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

// Jika semua filter kosong
if (empty($bucket) && empty($ncvs) && empty($po_code) && empty($job_order)) {
    echo json_encode([
        "draw" => intval($draw),
        "recordsTotal" => 0,
        "recordsFiltered" => 0,
        "data" => []
    ]);
    exit;
}

// ============================
// Buat query utama
// ============================
$sql = "FROM tbl_transaksi t WHERE 1=1";
$where = [];
$params = [];
$types = "";

// Tambahkan filter dinamis
if (!empty($bucket)) {
    $where[] = "t.bucket = ?";
    $params[] = $bucket;
    $types   .= "s";
}
if (!empty($ncvs)) {
    $where[] = "t.ncvs = ?";
    $params[] = $ncvs;
    $types   .= "s";
}
if (!empty($po_code)) {
    $where[] = "t.po_code = ?";
    $params[] = $po_code;
    $types   .= "s";
}
if (!empty($job_order)) {
    $where[] = "t.job_order = ?";
    $params[] = $job_order;
    $types   .= "s";
}
if (!empty($where)) {
    $sql .= " AND " . implode(" AND ", $where);
}

// ============================
// Hitung total records
// ============================
$totalQuery = "SELECT COUNT(*) as cnt " . $sql;
$stmt = $conn->prepare($totalQuery);
if ($types) $stmt->bind_param($types, ...$params);
$stmt->execute();
$totalResult = $stmt->get_result()->fetch_assoc();
$recordsTotal = $totalResult['cnt'] ?? 0;

// ============================
// Ambil data utama
// ============================
$dataQuery = "SELECT 
    t.id_trans, t.job_order, t.ncvs, t.bucket, t.po_code, 
    t.po_item, t.lot, t.model, t.style, t.komponen_qty
    " . $sql . " 
    ORDER BY t.ncvs ASC, t.job_order ASC, t.id_trans ASC
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

// ============================
// Loop data + Tambah nomor urut per job_order
// ============================
$jobOrderCounter = []; // menyimpan urutan transaksi per job_order

while ($row = $dataResult->fetch_assoc()) {

    // Hitung urutan transaksi per job_order
    $job = $row['job_order'];
    if (!isset($jobOrderCounter[$job])) {
        $jobOrderCounter[$job] = 1;
    } else {
        $jobOrderCounter[$job]++;
    }
    $row['no_urut'] = $jobOrderCounter[$job]; // <-- kolom tambahan

    // --- Decode LOT jadi range
    $displayLot = '';
    if (!empty($row['lot']) && is_string($row['lot'])) {
        $decodedLot = json_decode($row['lot'], true);
        if (is_array($decodedLot) && count($decodedLot) > 0) {
            $minLot = min($decodedLot);
            $maxLot = max($decodedLot);
            $displayLot = ($minLot === $maxLot) ? $minLot : "$minLot-$maxLot";
        } else {
            $displayLot = htmlspecialchars($row['lot']);
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

    // --- Link ke Job Order detail
    $row['job_order'] =
        '<a href="dashb-timeline-detail.php?job_order=' . urlencode($row['job_order']) .
        '&lot=' . urlencode($displayLot) .
        '&id_trans=' . urlencode($row['id_trans']) . '" ' .
        'class="btn btn-sm btn-outline-primary" target="_blank">' .
        htmlspecialchars($row['job_order']) .
        '</a>';

    $row['lot_display'] = $displayLot;
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
