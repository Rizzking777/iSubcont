<?php
require '../vendor/autoload.php';
require_once __DIR__ . '/../config/function.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Ambil parameter
$job_order = $_GET['job_order'] ?? '';
$lot_param = $_GET['lot'] ?? '';

if (!$job_order) die("Job Order tidak ditemukan.");
if (!$lot_param) die("Lot tidak ditemukan.");

// Parse lot
$selectedLots = array_map('trim', explode(',', $lot_param));
$selectedLots = array_filter($selectedLots, fn($l) => $l !== '');

// ================== HEADER DATA ==================
$sql = "SELECT job_order, ncvs, bucket, po_code, po_item, model, style, lot, date_created
        FROM tbl_transaksi 
        WHERE job_order = ?
        LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $job_order);
$stmt->execute();
$result = $stmt->get_result();
$header = $result->fetch_assoc();

// ================== DETAIL DATA ==================
$sql_detail = "SELECT komponen_qty, lot FROM tbl_transaksi WHERE job_order = ?";
$stmt2 = $conn->prepare($sql_detail);
$stmt2->bind_param("s", $job_order);
$stmt2->execute();
$res_detail = $stmt2->get_result();

// Ambil semua nama komponen
$kompMap = [];
$qKom = $conn->query("SELECT id_komponen, nama_komponen FROM tbl_komponen");
while ($rowK = $qKom->fetch_assoc()) {
    $kompMap[$rowK['id_komponen']] = $rowK['nama_komponen'];
}

// ================== Pivot gabungan lot ==================
$rows = [];
$sizes = [];

while ($r = $res_detail->fetch_assoc()) {
    $lot_list = json_decode($r['lot'], true);
    if (!is_array($lot_list)) $lot_list = [$r['lot']];

    // Ambil hanya lot yang dipilih
    $lot_list = array_filter($lot_list, fn($l) => in_array($l, $selectedLots));
    if (empty($lot_list)) continue;

    $komp_data = json_decode($r['komponen_qty'], true);
    if (!is_array($komp_data)) continue;

    foreach ($lot_list as $lot_val) {
        foreach ($komp_data as $item) {
            $compId = $item['komponen'];
            $comp   = $kompMap[$compId] ?? $compId;
            $size   = $item['size'];
            $qty    = (int)($item['qty'] ?? 0);

            $sizes[$size] = true;

            // Gabungkan semua lot ke satu pivot
            $rows[$comp][$size] = ($rows[$comp][$size] ?? 0) + $qty;
        }
    }
}

// Urutkan size
$sizes = array_keys($sizes);
sort($sizes);

// ================== BUAT SPREADSHEET ==================
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// --- Judul ---
$sheet->mergeCells("A1:H1");
$sheet->setCellValue('A1', 'Report Subcont Out Control');
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
$sheet->getStyle('A1')->getAlignment()->setHorizontal('center');

// --- Header Info (kolom A-B) ---
$info = [
    'Job Order' => $header['job_order'],
    'NCVS'      => $header['ncvs'],
    'Bucket'    => $header['bucket'],
    'PO Code'   => $header['po_code'],
    'PO Item'   => $header['po_item'],
    'Model'     => $header['model'],
    'Style'     => $header['style'],
    'Lot'       => implode(', ', $selectedLots),
    'Date'      => date('d-m-Y H:i:s', strtotime($header['date_created']))
];

$row = 3;
foreach ($info as $label => $val) {
    $sheet->setCellValue('A' . $row, $label);
    $sheet->setCellValue('B' . $row, $val);
    $sheet->getStyle('A' . $row)->getFont()->setBold(true);
    $sheet->getStyle('B' . $row)->getAlignment()->setHorizontal('left');
    $row++;
}

// --- Table Header ---
$tableRow = 3;
$tableCol = 'D';

$sheet->setCellValue($tableCol . $tableRow, 'Komponen');
$col = chr(ord($tableCol) + 1);
foreach ($sizes as $s) {
    $sheet->setCellValue($col . $tableRow, $s);
    $col++;
}
$sheet->setCellValue($col . $tableRow, 'Total');

// Styling header
$sheet->getStyle("D{$tableRow}:{$col}{$tableRow}")
      ->getFont()->setBold(true);
$sheet->getStyle("D{$tableRow}:{$col}{$tableRow}")
      ->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
      ->getStartColor()->setARGB('FFE0E0E0');
$sheet->getStyle("D{$tableRow}:{$col}{$tableRow}")
      ->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
$sheet->getStyle("D{$tableRow}:{$col}{$tableRow}")
      ->getAlignment()->setHorizontal('center')->setVertical('center');

$tableRow++;

// --- Isi pivot gabungan lot ---
foreach ($rows as $comp => $data) {
    $sheet->setCellValue('D' . $tableRow, $comp);

    $col = 'E';
    $total = 0;
    foreach ($sizes as $s) {
        $val = $data[$s] ?? 0;
        $sheet->setCellValue($col . $tableRow, $val);
        $total += $val;
        $col++;
    }
    $sheet->setCellValue($col . $tableRow, $total);

    // border & align
    $sheet->getStyle("D{$tableRow}:{$col}{$tableRow}")
          ->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
    $sheet->getStyle("D{$tableRow}:{$col}{$tableRow}")
          ->getAlignment()->setHorizontal('center')->setVertical('center');

    $tableRow++;
}

// --- Auto-size column ---
foreach (range('A', $col) as $c) {
    $sheet->getColumnDimension($c)->setAutoSize(true);
}

// --- Page setup ---
$sheet->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_PORTRAIT);
$sheet->getPageSetup()->setFitToWidth(1);
$sheet->getPageSetup()->setHorizontalCentered(true);
$sheet->getPageSetup()->setVerticalCentered(false);
$sheet->getPageMargins()->setTop(0.5);
$sheet->getPageMargins()->setBottom(0.5);
$sheet->getPageMargins()->setLeft(0.5);
$sheet->getPageMargins()->setRight(0.5);

// --- Output Excel ---
$lotStr = implode(',', $selectedLots);
$filename = "Export_{$job_order}_Lot_{$lotStr}.xlsx";

$writer = new Xlsx($spreadsheet);
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');
$writer->save('php://output');
exit;
