<?php
require '../vendor/autoload.php';
require_once __DIR__ . '/../config/function.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Ambil job_order
$job_order = $_GET['job_order'] ?? '';
if (!$job_order) die("Job Order tidak ditemukan.");

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
$sql_detail = "SELECT komponen_qty FROM tbl_transaksi WHERE job_order = ?";
$stmt2 = $conn->prepare($sql_detail);
$stmt2->bind_param("s", $job_order);
$stmt2->execute();
$res_detail = $stmt2->get_result();

// Ambil semua nama komponen biar gak angka
$kompMap = [];
$qKom = $conn->query("SELECT id_komponen, nama_komponen FROM tbl_komponen");
while ($rowK = $qKom->fetch_assoc()) {
    $kompMap[$rowK['id_komponen']] = $rowK['nama_komponen'];
}

// Transform pivot
$sizes = [];
$rows = [];
while ($r = $res_detail->fetch_assoc()) {
    $komp_data = json_decode($r['komponen_qty'], true);
    if (!is_array($komp_data)) continue;

    foreach ($komp_data as $item) {
        $compId = $item['komponen'];
        $comp = $kompMap[$compId] ?? $compId; // ambil nama kalau ada
        $size = $item['size'];
        $qty  = $item['qty'];

        $sizes[$size] = true;
        $rows[$comp][$size] = ($rows[$comp][$size] ?? 0) + $qty;
    }
}
$sizes = array_keys($sizes);
sort($sizes);

// ================== BUAT SPREADSHEET ==================
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// === 1. Judul ===
$sheet->mergeCells("A1:H1");
$sheet->setCellValue('A1', 'Report Subcont Out Control');
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
$sheet->getStyle('A1')->getAlignment()->setHorizontal('center');

// === 2. Header Info (kolom A-B) ===
$info = [
    'Job Order' => $header['job_order'],
    'NCVS'      => $header['ncvs'],
    'Bucket'    => $header['bucket'],
    'PO Code'   => $header['po_code'],
    'PO Item'   => $header['po_item'],
    'Model'     => $header['model'],
    'Style'     => $header['style'],
    'Lot'       => trim($header['lot'], '[]'), // kalau data masih berbentuk [1], dibersihin jadi 1
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

// === 3. Table Header (mulai kolom D) ===
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

// >>> Tambahin align center header
$sheet->getStyle("D{$tableRow}:{$col}{$tableRow}")
    ->getAlignment()->setHorizontal('center')
    ->setVertical('center');

$tableRow++;

// === 4. Isi detail (Pivot) ===
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

    // border tiap row
    $sheet->getStyle("D{$tableRow}:{$col}{$tableRow}")
        ->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);

    // >>> Tambahin align center isi tabel
    $sheet->getStyle("D{$tableRow}:{$col}{$tableRow}")
        ->getAlignment()->setHorizontal('center')
        ->setVertical('center');

    $tableRow++;
}

// === 5. Auto-size column ===
foreach (range('A', $col) as $c) {
    $sheet->getColumnDimension($c)->setAutoSize(true);
}

// === 6. Print settings ===
$sheet->getPageSetup()
    ->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_PORTRAIT);
$sheet->getPageSetup()
    ->setFitToWidth(1);

// Horizontal center aktif, vertical center nonaktif
$sheet->getPageSetup()->setHorizontalCentered(true);
$sheet->getPageSetup()->setVerticalCentered(false);

// Margin
$sheet->getPageMargins()->setTop(0.5);
$sheet->getPageMargins()->setBottom(0.5);
$sheet->getPageMargins()->setLeft(0.5);
$sheet->getPageMargins()->setRight(0.5);

// ================== OUTPUT ==================
$writer = new Xlsx($spreadsheet);
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="Export_file_' . $job_order . '.xlsx"');
header('Cache-Control: max-age=0');
$writer->save('php://output');
exit;
