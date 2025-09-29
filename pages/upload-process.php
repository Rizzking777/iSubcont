<?php
require __DIR__ . '/../config/function.php';

if (isset($_POST['upload'])) {
    $fileName = $_FILES['filedata']['name'];
    $fileTmp  = $_FILES['filedata']['tmp_name'];
    $userId   = 1; // ambil dari session login user

    uploadExcelToDB($fileTmp, $fileName, $conn);

    header("Location: master-upload.php");
    exit;
}
