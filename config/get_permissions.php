<?php
include 'function.php';

if (isset($_GET['role_id'])) {
    $role_id = intval($_GET['role_id']);

    $sql = "
        SELECT 
            m.id AS menu_id,
            m.name AS menu_name,
            m.ordering AS ordering,
            IFNULL(m.parent_id, 0) AS parent_id,
            IFNULL(rp.allowed, 0) AS allowed
        FROM menus m
        LEFT JOIN role_permissions rp 
            ON m.id = rp.menu_id AND rp.role_id = ?
        ORDER BY m.ordering ASC, m.id ASC;
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $role_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $menus = [];
    while ($row = $result->fetch_assoc()) {
        $menus[] = $row;
    }

    echo json_encode($menus);
}
