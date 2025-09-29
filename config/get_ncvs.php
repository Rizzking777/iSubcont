<?php
include 'function.php'; // koneksi DB

$search = $_GET['search'] ?? '';

$query = $conn->prepare("SELECT id_ncvs, ncvs FROM tbl_ncvs WHERE ncvs LIKE ? ORDER BY ncvs ASC LIMIT 20");
$param = "%$search%";
$query->bind_param("s", $param);
$query->execute();
$result = $query->get_result();

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = [
        "id" => $row['id_ncvs'],
        "text" => $row['ncvs']
    ];
}

echo json_encode($data);
