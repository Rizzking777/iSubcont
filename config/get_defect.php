<?php
require_once 'function.php'; // koneksi

$q = $_GET['q'] ?? '';
$q = "%".$q."%";

$stmt = $conn->prepare("SELECT id_defect, defect FROM tbl_defect WHERE defect LIKE ? ORDER BY defect LIMIT 20");
$stmt->bind_param("s", $q);
$stmt->execute();
$res = $stmt->get_result();

$data = [];
while ($row = $res->fetch_assoc()) {
  $data[] = [
    "id" => $row['id_defect'],   // wajib: key = "id"
    "text" => $row['defect']     // wajib: key = "text"
  ];
}
echo json_encode($data);