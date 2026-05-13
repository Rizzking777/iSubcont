<?php

header('Content-Type: application/json');
require_once "function.php"; 
/** @var mysqli $conn */

$id_card =
    trim($_POST['id_card'] ?? '');

if (!$id_card) {

    echo json_encode([
        'status' => false,
        'message' => 'ID Card kosong'
    ]);

    exit;
}



$q = mysqli_query($conn, "
    SELECT *
    FROM tbl_user
    WHERE

        id_card = '$id_card'

        AND is_deleted = 0

    LIMIT 1
");



if (mysqli_num_rows($q) == 0) {

    echo json_encode([
        'status' => false,
        'message' => 'ID Card tidak terdaftar'
    ]);

    exit;
}



$user = mysqli_fetch_assoc($q);



$_SESSION['pickup_session'] = [

    'nik' =>
        $user['nik_user'],

    'name' =>
        $user['username'],

    'ncvs' =>
        $user['ncvs'],

    'id_card' =>
        $user['id_card']
];



echo json_encode([

    'status' => true,

    'nik' =>
        $user['nik_user'],

    'name' =>
        $user['username'],

    'ncvs' =>
        $user['ncvs']
]);