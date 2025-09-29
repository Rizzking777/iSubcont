<?php
require 'function.php'; // sesuaikan path
if(!isset($_POST['id_trans'])) exit;

$id_trans = intval($_POST['id_trans']);

// Ambil count saat ini
$res = $conn->query("SELECT count_barcode FROM tbl_transaksi WHERE id_trans = $id_trans");
$row = $res->fetch_assoc();
$count = ($row['count_barcode'] ?? 0) + 1;

// Update count
$conn->query("UPDATE tbl_transaksi SET count_barcode = $count WHERE id_trans = $id_trans");

// Kembalikan json
echo json_encode(['success'=>true, 'count'=>$count]);
