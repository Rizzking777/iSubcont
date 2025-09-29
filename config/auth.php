<?php

include_once 'function.php'; // koneksi

function checkAuth($menuKey = null) {
    // 1. Belum login → unauthorized
    if (!isset($_SESSION['login']) || $_SESSION['login'] !== true) {
        header("Location: ../unauthorized.php"); // 401
        exit;
    }

    // 2. Kalau tidak dicek menu → cukup cek login aja
    if ($menuKey === null) {
        return true;
    }

    global $conn;
    $role_id = (int) $_SESSION['role_id'];

    // 3. Cek permission di DB
    $sql = "
        SELECT 1 
        FROM role_permissions rp
        JOIN menus m ON m.id = rp.menu_id
        WHERE rp.role_id = ? AND rp.allowed = 1 AND m.key_name = ?
        LIMIT 1
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("is", $role_id, $menuKey);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows === 0) {
        // Tidak ada izin → forbidden
        header("Location: ../forbidden.php"); // 403
        exit;
    }

    return true;
}
