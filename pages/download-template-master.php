<?php
require __DIR__ . '/../vendor/autoload.php'; // sesuaikan path

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

// Buat spreadsheet baru
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Header kolom
$headers = [
    'job_order', 'bucket', 'po_code', 'po_item',
    'style', 'model', 'ncvs', 'qr_code', 'lot', 'size', 'qty'
];

// Isi header di baris pertama
$col = 'A';
foreach ($headers as $header) {
    $sheet->setCellValue($col.'1', strtoupper($header)); // biar lebih rapi huruf besar semua
    $col++;
}

// Range header (A1 sampai kolom terakhir di baris 1)
$lastCol = chr(ord('A') + count($headers) - 1);
$headerRange = "A1:{$lastCol}1";

// Tambah style ke header
$sheet->getStyle($headerRange)->applyFromArray([
    'font' => [
        'bold' => true,
        'color' => ['rgb' => 'FFFFFF'], // putih
        'size' => 12
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical'   => Alignment::VERTICAL_CENTER,
    ],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => '4CAF50'] // hijau (bisa ganti kode hex lain)
    ]
]);

// Auto size kolom
foreach (range('A', $lastCol) as $columnID) {
    $sheet->getColumnDimension($columnID)->setAutoSize(true);
}

// Output ke browser
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="template_tbl_master_data.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
