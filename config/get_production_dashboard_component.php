<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');
/** @var mysqli $conn */

require_once "function.php";

$sql = "

SELECT
    kp.is_main,
    k.nama_komponen
FROM tbl_komponen_proses kp
JOIN tbl_komponen k
    ON k.id_komponen = kp.id_input
WHERE
    kp.id_group = ?
ORDER BY
    kp.is_main DESC,
    k.nama_komponen ASC;

";

$id_group = $_GET['id_group'] ?? '';

$stmt = $conn->prepare($sql);

$stmt->bind_param("i", $id_group);

$stmt->execute();

$result = $stmt->get_result();

$data = [];

while($row = $result->fetch_assoc()){

    $data[] = $row;

}

echo json_encode([
    "data"=>$data
]);