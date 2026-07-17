<?php
session_start(); // ready to go!

//Koneksi ke DBMS
$conn = mysqli_connect("localhost", "root", "", "db_isubcont");
date_default_timezone_set('Asia/Jakarta');

// REGISTER USERS
if (isset($_POST['submit-user'])) {
    date_default_timezone_set('Asia/Jakarta');

    // Ambil dan filter data
    $updated_by = mysqli_real_escape_string($conn, $_POST['updated_by']);
    $username   = mysqli_real_escape_string($conn, $_POST['username']);
    $nik_user   = mysqli_real_escape_string($conn, $_POST['nik_user']);
    $id_card = trim($_POST['id_card']);

    if ($id_card === '') {
        $id_card = null;
    } else {
        $id_card = mysqli_real_escape_string($conn, $id_card);
    }
    $ncvs   = mysqli_real_escape_string($conn, $_POST['ncvs']);
    $role_id  = mysqli_real_escape_string($conn, $_POST['role_id']);
    $password   = mysqli_real_escape_string($conn, $_POST['password']);
    $timestamp  = date('Y-m-d H:i:s');

    // Hash password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Cek apakah NIK sudah ada
    $check_nik = mysqli_query($conn, "SELECT 1 FROM tbl_user WHERE nik_user = '$nik_user'");
    if (mysqli_num_rows($check_nik) > 0) {
        $_SESSION['red_notif'] = "NIK sudah terdaftar, mohon gunakan NIK lain.";
        header("Location: /isubcont/pages/master-user.php");
        exit();
    }

    if (!empty($id_card)) {

        $check_id_card = mysqli_query($conn, "
        SELECT 1
        FROM tbl_user
        WHERE id_card = '$id_card'
    ");

        if (mysqli_num_rows($check_id_card) > 0) {

            $_SESSION['red_notif'] = "ID Card sudah terdaftar, mohon gunakan ID Card lain.";
            header("Location: /isubcont/pages/master-user.php");
            exit();
        }
    }

    $id_card_sql = ($id_card === null)
        ? "NULL"
        : "'$id_card'";

    // Simpan ke tbl_user
    $query_user = mysqli_query($conn, "INSERT INTO tbl_user 
        (username, nik_user, id_card, ncvs, pass_user, pass_plain, role_id, is_deleted, updated_by, timestamp) 
        VALUES 
        ('$username', '$nik_user', $id_card_sql, '$ncvs', '$hashed_password', '$password', '$role_id', '0', '$updated_by', '$timestamp')");

    if ($query_user) {
        $last_user_id = mysqli_insert_id($conn);

        // Siapkan log (hanya simpan data baru)
        $new_data = [
            "username" => $username,
            "nik_user" => $nik_user,
            "id_card" => $id_card,
            "ncvs" => $ncvs,
            "role_id" => $role_id
        ];
        $new_data_json = mysqli_real_escape_string($conn, json_encode($new_data));

        $query_log = mysqli_query($conn, "INSERT INTO tlog_user 
            (id_user, updated_by, action_type, old_data, new_data, created_at, updated_at) 
            VALUES 
            ('$last_user_id', '$updated_by', 'INSERT', NULL, '$new_data_json', NOW(), NOW())");

        if ($query_log) {
            $_SESSION['green_notif'] = "User berhasil didaftarkan.";
        } else {
            $_SESSION['red_notif'] = "User berhasil didaftarkan, tapi log gagal.";
        }

        header("Location: /isubcont/pages/master-user.php");
        exit();
    } else {
        $_SESSION['red_notif'] = "User tidak berhasil didaftarkan.";
        header("Location: /isubcont/pages/master-user.php");
        exit();
    }
}

// UPDATE USERS
if (isset($_POST['update-user'])) {
    date_default_timezone_set('Asia/Jakarta');

    // Ambil dan filter data
    $id_user    = mysqli_real_escape_string($conn, $_POST['id_user']);
    $updated_by = mysqli_real_escape_string($conn, $_POST['updated_by']);
    $username   = mysqli_real_escape_string($conn, $_POST['username']);
    $nik_user   = mysqli_real_escape_string($conn, $_POST['nik_user']);
    $role_id  = mysqli_real_escape_string($conn, $_POST['role_id']);
    $password   = mysqli_real_escape_string($conn, $_POST['password']);
    $timestamp  = date('Y-m-d H:i:s');

    // Ambil data lama untuk logging
    $old_query = mysqli_query($conn, "SELECT username, nik_user, role_id FROM tbl_user WHERE id_user = '$id_user'");
    $old_data = mysqli_fetch_assoc($old_query);
    $old_data_json = mysqli_real_escape_string($conn, json_encode($old_data));

    // Siapkan SQL update
    if (!empty($password)) {
        // Jika password diubah
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $update_sql = "UPDATE tbl_user 
                       SET username='$username', nik_user='$nik_user', role_id='$role_id', 
                           pass_user='$hashed_password', pass_plain='$password',
                           updated_by='$updated_by', timestamp='$timestamp'
                       WHERE id_user='$id_user'";
    } else {
        // Jika password tidak diubah
        $update_sql = "UPDATE tbl_user 
                       SET username='$username', nik_user='$nik_user', role_id='$role_id',
                           updated_by='$updated_by', timestamp='$timestamp'
                       WHERE id_user='$id_user'";
    }

    $query_update = mysqli_query($conn, $update_sql);

    if ($query_update) {
        // Siapkan data baru untuk logging
        $new_data = [
            "username"  => $username,
            "nik_user"  => $nik_user,
            "role_id" => $role_id
        ];
        $new_data_json = mysqli_real_escape_string($conn, json_encode($new_data));

        // Insert log
        $query_log = mysqli_query($conn, "INSERT INTO tlog_user 
            (id_user, updated_by, action_type, old_data, new_data, created_at, updated_at) 
            VALUES 
            ('$id_user', '$updated_by', 'UPDATE', '$old_data_json', '$new_data_json', NOW(), NOW())");

        if ($query_log) {
            $_SESSION['green_notif'] = "Data user berhasil diperbarui.";
        } else {
            $_SESSION['red_notif'] = "User berhasil diupdate, tapi log gagal.";
        }

        header("Location: /isubcont/pages/master-user.php");
        exit;
    } else {
        $_SESSION['red_notif'] = "User tidak berhasil diupdate.";
        header("Location: /isubcont/pages/master-user.php");
        exit();
    }
}

// REMOVE data user
if (isset($_POST['remove-user'])) {
    $id_user  = $_POST['id_user'];
    $username = $_SESSION['username'] ?? 'SYSTEM';

    // 1. Ambil data user (yang belum dihapus)
    $stmt = $conn->prepare("SELECT * FROM tbl_user WHERE id_user = ? AND is_deleted = 0 LIMIT 1");
    $stmt->bind_param("i", $id_user);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if (!$user) {
        $_SESSION['red_notif'] = "Data user tidak ditemukan atau sudah dihapus.";
        header('Location: /isubcont/pages/master-user.php');
        exit;
    }

    // Simpan data lama
    $old_data_json = json_encode($user, JSON_UNESCAPED_UNICODE);

    // Simulasi data baru
    $user['is_deleted'] = 1;
    $new_data_json = json_encode($user, JSON_UNESCAPED_UNICODE);

    // 2. Update tbl_user (soft delete)
    $stmt = $conn->prepare("UPDATE tbl_user SET is_deleted = 1, updated_by = ?, timestamp = NOW() WHERE id_user = ?");
    $stmt->bind_param("si", $username, $id_user);
    $success = $stmt->execute();
    $stmt->close();

    if ($success) {
        // 3. Log ke tlog_user
        $stmt = $conn->prepare("
            INSERT INTO tlog_user (id_user, updated_by, action_type, old_data, new_data, created_at, updated_at)
            VALUES (?, ?, 'REMOVE', ?, ?, NOW(), NOW())
        ");
        $stmt->bind_param("isss", $id_user, $username, $old_data_json, $new_data_json);
        $stmt->execute();
        $stmt->close();

        $_SESSION['green_notif'] = "Data user berhasil dihapus.";
    } else {
        $_SESSION['red_notif'] = "Gagal menghapus data user.";
    }

    header('Location: /isubcont/pages/master-user.php');
    exit;
}

// RESTORE user
if (isset($_POST['restore-user'])) {
    $id_user = $_POST['id_user'];
    $username = $_SESSION['username'] ?? 'SYSTEM';

    // Ambil data user sebelum restore
    $stmt = $conn->prepare("SELECT * FROM tbl_user WHERE id_user = ? LIMIT 1");
    $stmt->bind_param("i", $id_user);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if ($user && $user['is_deleted'] == 1) {
        $old_data_json = json_encode($user, JSON_UNESCAPED_UNICODE);

        // Update (restore)
        $stmt = $conn->prepare("UPDATE tbl_user SET is_deleted = 0, updated_by = ?, timestamp = NOW() WHERE id_user = ?");
        $stmt->bind_param("si", $username, $id_user);
        $success = $stmt->execute();
        $stmt->close();

        if ($success) {
            // Simulasi data baru
            $user['is_deleted'] = 0;
            $new_data_json = json_encode($user, JSON_UNESCAPED_UNICODE);

            // Log
            $stmt = $conn->prepare("
                INSERT INTO tlog_user (id_user, updated_by, action_type, old_data, new_data, created_at, updated_at)
                VALUES (?, ?, 'RESTORE', ?, ?, NOW(), NOW())
            ");
            $stmt->bind_param("isss", $id_user, $username, $old_data_json, $new_data_json);
            $stmt->execute();
            $stmt->close();

            $_SESSION['green_notif'] = "Data user berhasil direstore.";
        } else {
            $_SESSION['red_notif'] = "Data user gagal direstore.";
        }
    } else {
        $_SESSION['red_notif'] = "Data user tidak ditemukan atau belum dihapus.";
    }

    header("Location: /isubcont/pages/archive-user.php");
    exit();
}

// DELETE permanent user
if (isset($_POST['delete-user'])) {
    $id_user = $_POST['id_user'];
    $username = $_SESSION['username'] ?? 'SYSTEM';

    // Ambil data lama sebelum delete permanent
    $stmt = $conn->prepare("SELECT * FROM tbl_user WHERE id_user = ? LIMIT 1");
    $stmt->bind_param("i", $id_user);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if ($user) {
        $old_data_json = json_encode($user, JSON_UNESCAPED_UNICODE);

        // DELETE permanen
        $stmt = $conn->prepare("DELETE FROM tbl_user WHERE id_user = ?");
        $stmt->bind_param("i", $id_user);
        $success = $stmt->execute();
        $stmt->close();

        if ($success) {
            // Tambahkan note ke new_data
            $new_data = [
                "note" => "User dihapus permanen oleh {$username} pada " . date('Y-m-d H:i:s')
            ];
            $new_data_json = json_encode($new_data, JSON_UNESCAPED_UNICODE);

            // Log
            $stmt = $conn->prepare("
                INSERT INTO tlog_user (id_user, updated_by, action_type, old_data, new_data, created_at, updated_at)
                VALUES (?, ?, 'DELETE', ?, ?, NOW(), NOW())
            ");
            $stmt->bind_param("isss", $id_user, $username, $old_data_json, $new_data_json);
            $stmt->execute();
            $stmt->close();

            $_SESSION['green_notif'] = "Data user berhasil dihapus secara permanen.";
        } else {
            $_SESSION['red_notif'] = "Data user gagal dihapus permanen.";
        }
    } else {
        $_SESSION['red_notif'] = "Data user tidak ditemukan.";
    }

    header("Location: /isubcont/pages/archive-user.php");
    exit();
}

// Fungsi format waktu "time ago"
function time_elapsed_string($datetime, $full = false): string
{
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    // Hitung minggu dari total hari
    $weeks = floor($diff->days / 7);
    $days  = $diff->days % 7;

    $string = [
        'y' => $diff->y . ' year' . ($diff->y > 1 ? 's' : ''),
        'm' => $diff->m . ' month' . ($diff->m > 1 ? 's' : ''),
        'w' => $weeks . ' week' . ($weeks > 1 ? 's' : ''),
        'd' => $days . ' day' . ($days > 1 ? 's' : ''),
        'h' => $diff->h . ' hour' . ($diff->h > 1 ? 's' : ''),
        'i' => $diff->i . ' minute' . ($diff->i > 1 ? 's' : ''),
        's' => $diff->s . ' second' . ($diff->s > 1 ? 's' : ''),
    ];

    // Buang nilai 0 supaya nggak tampil "0 day"
    foreach ($string as $k => $v) {
        if (strpos($v, '0') === 0) {
            unset($string[$k]);
        }
    }

    if (!$full) {
        $string = array_slice($string, 0, 1);
    }

    return $string ? implode(', ', $string) . ' ago' : 'just now';
}

// Ambil statistik logging user
function get_user_log_stats($conn)
{
    // Total Actions
    $sql = "SELECT COUNT(*) as total FROM tlog_user";
    $total_actions = $conn->query($sql)->fetch_assoc()['total'];

    // Most Active User
    $sql = "SELECT updated_by, COUNT(*) as jumlah 
            FROM tlog_user 
            GROUP BY updated_by 
            ORDER BY jumlah DESC 
            LIMIT 1";
    $most_active = $conn->query($sql)->fetch_assoc();

    // Latest Activity
    $sql = "SELECT updated_by, action_type, created_at 
            FROM tlog_user 
            ORDER BY created_at DESC 
            LIMIT 1";
    $latest = $conn->query($sql)->fetch_assoc();
    $latest['time_ago'] = time_elapsed_string($latest['created_at']); // ✅ fungsi sudah dikenal

    // Breakdown
    $sql = "SELECT action_type, COUNT(*) as jumlah FROM tlog_user GROUP BY action_type";
    $result = $conn->query($sql);
    $total = 0;
    $counts = ['UPDATE' => 0, 'INSERT' => 0, 'REMOVE' => 0, 'DELETE' => 0];
    while ($row = $result->fetch_assoc()) {
        $counts[$row['action_type']] = $row['jumlah'];
        $total += $row['jumlah'];
    }

    return [
        'total_actions' => $total_actions,
        'most_active'   => $most_active,
        'latest'        => $latest,
        'breakdown'     => [
            'update' => $total ? round(($counts['UPDATE'] / $total) * 100) : 0,
            'insert' => $total ? round(($counts['INSERT'] / $total) * 100) : 0,
            'remove' => $total ? round(($counts['REMOVE'] / $total) * 100) : 0,
            'delete' => $total ? round(($counts['DELETE'] / $total) * 100) : 0,
            'restore' => $total ? round(($counts['RESTORE'] / $total) * 100) : 0,
        ]
    ];
}

// Ambil statistik log login
function get_login_log_stats($conn)
{
    // Total Logins
    $sql = "SELECT COUNT(*) as total FROM tlog_login";
    $total_logins = $conn->query($sql)->fetch_assoc()['total'];

    // Unique Users
    $sql = "SELECT COUNT(DISTINCT id_user) as unique_users FROM tlog_login";
    $unique_users = $conn->query($sql)->fetch_assoc()['unique_users'];

    // Most Active User
    $sql = "SELECT id_user, COUNT(*) as jumlah 
            FROM tlog_login 
            GROUP BY id_user 
            ORDER BY jumlah DESC 
            LIMIT 1";
    $most_active = $conn->query($sql)->fetch_assoc();

    // Latest Login
    $sql = "SELECT l.id_user, u.username, l.login_time, l.ip_address 
            FROM tlog_login l
            LEFT JOIN tbl_user u ON l.id_user = u.id_user
            ORDER BY l.login_time DESC 
            LIMIT 1;";
    $latest = $conn->query($sql)->fetch_assoc();
    $latest['time_ago'] = time_elapsed_string($latest['login_time']);

    // Peak Login Hour (jam tersibuk)
    $sql = "SELECT HOUR(login_time) as jam, COUNT(*) as jumlah 
            FROM tlog_login 
            GROUP BY jam 
            ORDER BY jumlah DESC 
            LIMIT 1";
    $peak = $conn->query($sql)->fetch_assoc();
    $peak_hour = $peak ? sprintf("%02d:00", $peak['jam']) : '-';

    return [
        'total_logins'   => $total_logins,
        'unique_users'   => $unique_users,
        'most_active'    => $most_active,
        'latest'         => $latest,
        'peak_login'     => [
            'hour'           => $peak_hour,
            'jumlah'         => $peak['jumlah'] ?? 0
        ]
    ];
}

// Untuk upload excel
require __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Fungsi untuk upload excel ke database
 */
function uploadExcelToDB($fileTmp, $fileName, $conn)
{
    // --- Ambil user info dari session atau fallback query ---
    $id_user   = $_SESSION['id_user'] ?? 0;
    $username = $_SESSION['username'] ?? 'unknown';

    if ($id_user == 0 && $username !== 'unknown') {
        $stmtUser = $conn->prepare("SELECT id_user FROM tbl_user WHERE username = ?");
        $stmtUser->bind_param("s", $username);
        $stmtUser->execute();
        $resUser = $stmtUser->get_result();
        if ($rowUser = $resUser->fetch_assoc()) {
            $id_user = $rowUser['id_user'];
        }
    }

    // --- Validasi ekstensi ---
    $allowedExt = ['xls', 'xlsx'];
    $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

    if (!in_array($fileExt, $allowedExt)) {
        $_SESSION['red_notif'] = "Upload gagal. Format file tidak valid (hanya .xls atau .xlsx).";
        return false;
    }

    try {
        $reader = IOFactory::createReaderForFile($fileTmp);
        $spreadsheet = $reader->load($fileTmp);
    } catch (\Exception $e) {
        $_SESSION['red_notif'] = "Upload gagal. File tidak bisa dibaca sebagai Excel.";
        return false;
    }

    $sheet = $spreadsheet->getActiveSheet();
    $rows  = $sheet->toArray();

    // --- Validasi header ---
    $expectedHeader = [
        'bucket',
        'job_order',
        'po_code',
        'po_item',
        'style',
        'model',
        'ncvs',
        'status_lot',
        'lot',
        'size',
        'qty',
        'qr_code'
    ];
    $header = array_map('strtolower', $rows[0] ?? []);

    if ($header !== $expectedHeader) {
        $_SESSION['red_notif'] = "Upload gagal. Struktur header Excel tidak sesuai template.";
        return false;
    }

    // --- Validasi jumlah maksimal baris ---
    $totalRows = count($rows) - 1;
    $maxRows   = 20000;
    if ($totalRows > $maxRows) {
        $_SESSION['red_notif'] = "Upload gagal. Maksimal {$maxRows} baris per upload. File Anda memiliki {$totalRows} baris.";
        return false;
    }

    // --- Proses baris data ---
    $successRows = 0;
    $failedRows  = 0;
    $insertData  = [];

    foreach ($rows as $i => $row) {
        if ($i == 0) continue; // skip header

        list(
            $bucket,
            $job_order,
            $po_code,
            $po_item,
            $style,
            $model,
            $ncvs,
            $status_lot,
            $lot,
            $size,
            $qty,
            $qr_code
        ) = $row;

        if (empty($job_order) || empty($po_code) || !is_numeric($qty)) {
            $failedRows++;
            continue;
        }

        $insertData[] = "(
            '$job_order',
            '$bucket',
            '$po_code',
            '$po_item',
            '$style',
            '$model',
            '$ncvs',
            '$qr_code',
            '$lot',
            '$size',
            '$qty',
            '$status_lot',
            NOW(),
            NOW()
        )";
        $successRows++;
    }

    // --- Insert ke tabel utama (per batch 1000) ---
    if (!empty($insertData)) {
        $chunks = array_chunk($insertData, 1000);
        foreach ($chunks as $chunk) {
            $values = implode(',', $chunk);
            $sql = "INSERT INTO tbl_master_data 
                    (
                    job_order,
                    bucket,
                    po_code,
                    po_item,
                    style,
                    model,
                    ncvs,
                    qr_code,
                    lot,
                    size,
                    qty,
                    status_lot,
                    date_updated,
                    timestamp
                )
                    VALUES $values";
            mysqli_query($conn, $sql);
        }
    }

    // --- Logging upload ---
    $status = ($successRows == 0) ? 'failed' : (($failedRows > 0) ? 'partial' : 'success');

    $stmt = $conn->prepare("INSERT INTO tlog_upload_master 
        (id_user, username, file_name, total_rows, success_rows, failed_rows, status) 
        VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("issiiis", $id_user, $username, $fileName, $totalRows, $successRows, $failedRows, $status);
    $stmt->execute();

    // --- Notifikasi ---
    if ($status === 'success') {
        $_SESSION['green_notif'] = "Upload berhasil. <strong>{$successRows}</strong> baris data masuk ke database.";
    } elseif ($status === 'partial') {
        $_SESSION['green_notif'] = "Upload selesai dengan catatan: <strong>{$successRows}</strong> baris berhasil, <strong>{$failedRows}</strong> baris gagal.";
    } else {
        $_SESSION['red_notif'] = "Upload gagal. Tidak ada data yang berhasil masuk.";
    }

    return compact('totalRows', 'successRows', 'failedRows', 'status');
}

function checkPermission($menuKey)
{
    global $conn;
    $role_id = $_SESSION['role_id'];

    $sql = "SELECT 1
            FROM menus m
            JOIN role_permissions rp ON rp.menu_id = m.id
            WHERE rp.role_id = '$role_id' AND m.key_name = '$menuKey'
            LIMIT 1";

    $result = mysqli_query($conn, $sql);
    if (mysqli_num_rows($result) == 0) {
        header("HTTP/1.1 403 Forbidden");
        die("Akses ditolak!");
    }
}

function renderMenu($parent_id, $menus, $page)
{
    if (!isset($menus[$parent_id])) return;

    foreach ($menus[$parent_id] as $menu) {
        $hasChild = isset($menus[$menu['id']]);

        // cek apakah menu ini aktif
        $isActive = ($page === $menu['key_name']);

        // cek apakah ada child yg aktif
        $isChildActive = $hasChild ? hasActiveChild($menu['id'], $menus, $page) : false;

        // parent dianggap open kalau child aktif
        $isOpen = $isChildActive ? "show" : "";
        $collapsed = $isChildActive ? "" : "collapsed";

        echo '<li class="nav-item">';

        if ($hasChild) {
            // menu parent
            echo '
                <a class="nav-link ' . $collapsed . '" 
                   data-bs-target="#menu-' . $menu['id'] . '" 
                   data-bs-toggle="collapse" href="#">
                  <i class="' . (!empty($menu["icon"]) ? $menu["icon"] : "bi bi-folder") . '"></i>
                  <span>' . $menu['name'] . '</span>
                  <i class="bi bi-chevron-down ms-auto"></i>
                </a>
                <ul id="menu-' . $menu['id'] . '" 
                    class="nav-content collapse ' . $isOpen . '" 
                    data-bs-parent="#sidebar-nav">';

            renderMenu($menu['id'], $menus, $page);

            echo '</ul>';
        } else {
            // menu child
            echo '<a class="nav-link ' . ($isActive ? "active" : "") . '" href="' . $menu['url'] . '">
                    <i class="' . (!empty($menu["icon"]) ? $menu["icon"] : "bi bi-circle") . '"></i>
                    <span>' . $menu['name'] . '</span>
                  </a>';
        }

        echo '</li>';
    }
}

function hasActiveChild($parent_id, $menus, $page)
{
    if (!isset($menus[$parent_id])) {
        // debug
        echo "<pre>[$parent_id] tidak punya child</pre>";
        return false;
    }

    foreach ($menus[$parent_id] as $child) {
        // debug
        echo "<pre>Cek child {$child['id']} ({$child['key_name']}) dari parent $parent_id</pre>";

        // kalau langsung cocok
        if ($child['key_name'] === $page) {
            echo "<pre>--> MATCH ketemu! ({$child['key_name']} == $page)</pre>";
            return true;
        }

        // kalau punya cucu, cek lagi
        if (isset($menus[$child['id']])) {
            $result = hasActiveChild($child['id'], $menus, $page);
            if ($result) {
                echo "<pre>--> Parent $parent_id jadi aktif karena child {$child['id']}</pre>";
                return true;
            }
        }
    }

    echo "<pre>Parent $parent_id tidak ada child yg aktif</pre>";
    return false;
}

// REGISTER role
if (isset($_POST['submit-role'])) {
    date_default_timezone_set('Asia/Jakarta');

    // Ambil dan filter data
    $updated_by = mysqli_real_escape_string($conn, $_POST['updated_by']);
    $role_name  = mysqli_real_escape_string($conn, $_POST['role_name']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $timestamp  = date('Y-m-d H:i:s');

    // Gate type otomatis sama dengan role_name
    $gate_type = $role_name;

    // Cek apakah role_name sudah ada
    $check_role = mysqli_query($conn, "SELECT 1 FROM roles WHERE role_name = '$role_name'");
    if (mysqli_num_rows($check_role) > 0) {
        $_SESSION['red_notif'] = "Role sudah terdaftar, mohon ganti role lain.";
        header("Location: /isubcont/pages/master-role.php");
        exit();
    }

    // Simpan ke tabel roles
    $query_role = mysqli_query($conn, "INSERT INTO roles 
        (role_name, gate_type, description, is_deleted, updated_by, timestamp) 
        VALUES 
        ('$role_name', '$gate_type', '$description', '0', '$updated_by', '$timestamp')");

    if ($query_role) {
        $last_role_id = mysqli_insert_id($conn);

        // Siapkan log (hanya simpan data baru)
        $new_data = [
            "role_name" => $role_name,
            "gate_type" => $gate_type,
            "description" => $description
        ];
        $new_data_json = mysqli_real_escape_string($conn, json_encode($new_data));

        $query_log = mysqli_query($conn, "INSERT INTO tlog_roles 
            (id, updated_by, action_type, old_data, new_data, created_at, updated_at) 
            VALUES 
            ('$last_role_id', '$updated_by', 'INSERT', NULL, '$new_data_json', NOW(), NOW())");

        if ($query_log) {
            $_SESSION['green_notif'] = "Role berhasil didaftarkan.";
        } else {
            $_SESSION['red_notif'] = "Role berhasil didaftarkan, tapi log gagal.";
        }

        header("Location: /isubcont/pages/master-role.php");
        exit();
    } else {
        $_SESSION['red_notif'] = "Role tidak berhasil didaftarkan.";
        header("Location: /isubcont/pages/master-role.php");
        exit();
    }
}

if (isset($_POST['update-role'])) {
    date_default_timezone_set('Asia/Jakarta');

    // Ambil data dan sanitasi
    $id          = $_POST['id'];
    $updated_by  = $_POST['updated_by'];
    $role_name   = $_POST['role_name'];
    $description = $_POST['description'];
    $timestamp   = date('Y-m-d H:i:s');

    // Gate type otomatis mengikuti role_name
    $gate_type = $role_name;

    // Ambil data lama untuk logging
    $stmt_old = $conn->prepare("SELECT role_name, gate_type, description FROM roles WHERE id = ?");
    $stmt_old->bind_param("i", $id);
    $stmt_old->execute();
    $old_data = $stmt_old->get_result()->fetch_assoc();
    $old_data_json = json_encode($old_data, JSON_UNESCAPED_UNICODE);

    // Update roles
    $stmt_update = $conn->prepare("UPDATE roles 
                                   SET role_name = ?, gate_type = ?, description = ?, updated_by = ?, timestamp = ? 
                                   WHERE id = ?");
    $stmt_update->bind_param("sssssi", $role_name, $gate_type, $description, $updated_by, $timestamp, $id);

    if ($stmt_update->execute()) {
        // Siapkan data baru untuk logging
        $new_data = [
            "role_name"   => $role_name,
            "gate_type"   => $gate_type,
            "description" => $description
        ];
        $new_data_json = json_encode($new_data, JSON_UNESCAPED_UNICODE);

        // Insert log ke tlog_roles
        $stmt_log = $conn->prepare("INSERT INTO tlog_roles 
            (id, updated_by, action_type, old_data, new_data, created_at, updated_at) 
            VALUES (?, ?, 'UPDATE', ?, ?, NOW(), NOW())");
        $stmt_log->bind_param("isss", $id, $updated_by, $old_data_json, $new_data_json);
        $stmt_log->execute();

        $_SESSION['green_notif'] = "Data role berhasil diperbarui.";
    } else {
        $_SESSION['red_notif'] = "Role tidak berhasil diupdate.";
    }

    header("Location: /isubcont/pages/master-role.php");
    exit;
}

// REMOVE role (soft delete)
if (isset($_POST['remove-role'])) {
    date_default_timezone_set('Asia/Jakarta');

    $id        = $_POST['id'];
    $username  = $_SESSION['username'] ?? 'SYSTEM';

    // 1. Ambil data role (termasuk gate_type)
    $stmt = $conn->prepare("SELECT id, role_name, gate_type, description, is_deleted, updated_by, timestamp 
                            FROM roles 
                            WHERE id = ? AND is_deleted = 0 
                            LIMIT 1");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $role = $result->fetch_assoc();
    $stmt->close();

    if (!$role) {
        $_SESSION['red_notif'] = "Data role tidak ditemukan atau sudah dihapus.";
        header('Location: /isubcont/pages/master-role.php');
        exit;
    }

    // Simpan data lama untuk log
    $old_data_json = json_encode($role, JSON_UNESCAPED_UNICODE);

    // Simulasi data baru (set is_deleted = 1)
    $role['is_deleted'] = 1;
    $new_data_json = json_encode($role, JSON_UNESCAPED_UNICODE);

    // 2. Update roles (soft delete)
    $stmt = $conn->prepare("UPDATE roles 
                            SET is_deleted = 1, updated_by = ?, timestamp = NOW() 
                            WHERE id = ?");
    $stmt->bind_param("si", $username, $id);
    $success = $stmt->execute();
    $stmt->close();

    if ($success) {
        // 3. Log ke tlog_roles
        $stmt = $conn->prepare("INSERT INTO tlog_roles
            (id, updated_by, action_type, old_data, new_data, created_at, updated_at)
            VALUES (?, ?, 'REMOVE', ?, ?, NOW(), NOW())");
        $stmt->bind_param("isss", $id, $username, $old_data_json, $new_data_json);
        $stmt->execute();
        $stmt->close();

        $_SESSION['green_notif'] = "Data role berhasil dihapus.";
    } else {
        $_SESSION['red_notif'] = "Gagal menghapus data role.";
    }

    header('Location: /isubcont/pages/master-role.php');
    exit;
}

// RESTORE role
if (isset($_POST['restore-role'])) {
    date_default_timezone_set('Asia/Jakarta');

    $id       = $_POST['id'];
    $username = $_SESSION['username'] ?? 'SYSTEM';

    // 1. Ambil data role (termasuk gate_type)
    $stmt = $conn->prepare("SELECT id, role_name, gate_type, description, is_deleted, updated_by, timestamp 
                            FROM roles 
                            WHERE id = ? 
                            LIMIT 1");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $role = $result->fetch_assoc();
    $stmt->close();

    if ($role && $role['is_deleted'] == 1) {
        $old_data_json = json_encode($role, JSON_UNESCAPED_UNICODE);

        // 2. Update restore (set is_deleted = 0)
        $stmt = $conn->prepare("UPDATE roles 
                                SET is_deleted = 0, updated_by = ?, timestamp = NOW() 
                                WHERE id = ?");
        $stmt->bind_param("si", $username, $id);
        $success = $stmt->execute();
        $stmt->close();

        if ($success) {
            // 3. Siapkan data baru untuk log
            $role['is_deleted'] = 0;
            $new_data_json = json_encode($role, JSON_UNESCAPED_UNICODE);

            // 4. Simpan ke log
            $stmt = $conn->prepare("INSERT INTO tlog_roles 
                (id, updated_by, action_type, old_data, new_data, created_at, updated_at)
                VALUES (?, ?, 'RESTORE', ?, ?, NOW(), NOW())");
            $stmt->bind_param("isss", $id, $username, $old_data_json, $new_data_json);
            $stmt->execute();
            $stmt->close();

            $_SESSION['green_notif'] = "Data role berhasil direstore.";
        } else {
            $_SESSION['red_notif'] = "Data role gagal direstore.";
        }
    } else {
        $_SESSION['red_notif'] = "Data role tidak ditemukan atau belum dihapus.";
    }

    header("Location: /isubcont/pages/archive-role.php");
    exit();
}

// DELETE permanent role
if (isset($_POST['delete-role'])) {
    date_default_timezone_set('Asia/Jakarta');

    $id       = $_POST['id'];
    $username = $_SESSION['username'] ?? 'SYSTEM';

    // 1. Ambil data role (termasuk gate_type)
    $stmt = $conn->prepare("SELECT id, role_name, gate_type, description, is_deleted, updated_by, timestamp 
                            FROM roles 
                            WHERE id = ? 
                            LIMIT 1");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $role = $result->fetch_assoc();
    $stmt->close();

    if ($role) {
        $old_data_json = json_encode($role, JSON_UNESCAPED_UNICODE);

        // 2. Hapus permanen
        $stmt = $conn->prepare("DELETE FROM roles WHERE id = ?");
        $stmt->bind_param("i", $id);
        $success = $stmt->execute();
        $stmt->close();

        if ($success) {
            // 3. Siapkan catatan untuk log
            $new_data = [
                "note" => "Role dihapus permanen oleh {$username} pada " . date('Y-m-d H:i:s')
            ];
            $new_data_json = json_encode($new_data, JSON_UNESCAPED_UNICODE);

            // 4. Simpan ke log
            $stmt = $conn->prepare("INSERT INTO tlog_roles 
                (id, updated_by, action_type, old_data, new_data, created_at, updated_at)
                VALUES (?, ?, 'DELETE', ?, ?, NOW(), NOW())");
            $stmt->bind_param("isss", $id, $username, $old_data_json, $new_data_json);
            $stmt->execute();
            $stmt->close();

            $_SESSION['green_notif'] = "Data role berhasil dihapus permanen.";
        } else {
            $_SESSION['red_notif'] = "Data role gagal dihapus permanen.";
        }
    } else {
        $_SESSION['red_notif'] = "Data role tidak ditemukan.";
    }

    header("Location: /isubcont/pages/archive-role.php");
    exit();
}

if (isset($_POST['save-permissions'])) {
    $role_id   = intval($_POST['role_id']);
    $updated_by = $_SESSION['username'] ?? 'system';
    $timestamp = date('Y-m-d H:i:s');

    // ===== Ambil data lama untuk logging =====
    $old_permissions = [];
    $result = $conn->query("SELECT menu_id FROM role_permissions WHERE role_id = {$role_id}");
    while ($row = $result->fetch_assoc()) {
        $old_permissions[] = $row['menu_id'];
    }

    // Hapus semua permission lama untuk role ini
    $stmt = $conn->prepare("DELETE FROM role_permissions WHERE role_id = ?");
    $stmt->bind_param("i", $role_id);
    $stmt->execute();

    // ===== Insert baru sesuai checklist =====
    $new_permissions = [];
    if (!empty($_POST['perm'])) {
        $stmt = $conn->prepare("
            INSERT INTO role_permissions (role_id, menu_id, allowed, updated_by, timestamp) 
            VALUES (?, ?, ?, ?, ?)
        ");

        foreach ($_POST['perm'] as $menu_id => $val) {
            $allowed = 1; // hanya 1 kolom allowed
            $stmt->bind_param("iiiss", $role_id, $menu_id, $allowed, $updated_by, $timestamp);
            $stmt->execute();

            $new_permissions[] = $menu_id;
        }
    }

    // ===== Tentukan action_type =====
    $old_json = json_encode($old_permissions);
    $new_json = json_encode($new_permissions);

    if (empty($old_permissions) && !empty($new_permissions)) {
        $action_type = 'INSERT CHECKLIST';
    } elseif (!empty($old_permissions) && !empty($new_permissions)) {
        $action_type = 'UPDATE CHECKLIST';
    } elseif (!empty($old_permissions) && empty($new_permissions)) {
        $action_type = 'DELETE CHECKLIST';
    } else {
        $action_type = 'NO CHANGE';
    }

    // ===== Logging ke tlog_roles =====
    $log_stmt = $conn->prepare("
        INSERT INTO tlog_roles (id, updated_by, action_type, old_data, new_data, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $log_stmt->bind_param(
        "issssss",
        $role_id,
        $updated_by,
        $action_type,
        $old_json,
        $new_json,
        $timestamp,
        $timestamp
    );
    $log_stmt->execute();

    // ===== Notifikasi & redirect =====
    $_SESSION['green_notif'] = "Permissions berhasil disimpan.";
    header("Location: /isubcont/pages/master-role.php");
    exit();
}

// REGISTER role
if (isset($_POST['submit-vendor'])) {
    date_default_timezone_set('Asia/Jakarta');

    // Ambil dan filter data
    $updated_by = mysqli_real_escape_string($conn, $_POST['updated_by']);
    $name_vendor    = mysqli_real_escape_string($conn, $_POST['name_vendor']);
    $code_vendor = mysqli_real_escape_string($conn, $_POST['code_vendor']);
    $alamat = mysqli_real_escape_string($conn, $_POST['alamat']);
    $timestamp  = date('Y-m-d H:i:s');

    // Cek apakah NIK sudah ada
    $check_role = mysqli_query($conn, "SELECT 1 FROM tbl_vendor WHERE code_vendor = '$code_vendor'");
    if (mysqli_num_rows($check_role) > 0) {
        $_SESSION['red_notif'] = "Vendor sudah terdaftar, mohon ganti vendor lain.";
        header("Location: /isubcont/pages/master-vendor.php");
        exit();
    }

    // Simpan ke tbl_vendor
    $query_role = mysqli_query($conn, "INSERT INTO tbl_vendor 
        (name_vendor, code_vendor, alamat, is_deleted, updated_by, timestamp) 
        VALUES 
        ('$name_vendor', '$code_vendor', '$alamat', '0', '$updated_by', '$timestamp')");

    if ($query_role) {
        $last_user_id = mysqli_insert_id($conn);

        // Siapkan log (hanya simpan data baru)
        $new_data = [
            "name_vendor" => $name_vendor,
            "code_vendor" => $code_vendor,
            "alamat" => $alamat
        ];
        $new_data_json = mysqli_real_escape_string($conn, json_encode($new_data));

        $query_log = mysqli_query($conn, "INSERT INTO tlog_vendor 
            (id_vendor, updated_by, action_type, old_data, new_data, created_at, updated_at) 
            VALUES 
            ('$last_user_id', '$updated_by', 'INSERT', NULL, '$new_data_json', NOW(), NOW())");

        if ($query_log) {
            $_SESSION['green_notif'] = "Vendor berhasil didaftarkan.";
        } else {
            $_SESSION['red_notif'] = "Vendor berhasil didaftarkan, tapi log gagal.";
        }

        header("Location: /isubcont/pages/master-vendor.php");
        exit();
    } else {
        $_SESSION['red_notif'] = "Vendor tidak berhasil didaftarkan.";
        header("Location: /isubcont/pages/master-vendor.php");
        exit();
    }
}

if (isset($_POST['update-vendor'])) {
    date_default_timezone_set('Asia/Jakarta');

    // Ambil data dan sanitasi
    $id_vendor   = $_POST['id_vendor'];
    $updated_by  = $_POST['updated_by'];
    $name_vendor   = $_POST['name_vendor'];
    $code_vendor = $_POST['code_vendor'];
    $alamat = $_POST['alamat'];
    $timestamp   = date('Y-m-d H:i:s');

    // Ambil data lama untuk logging
    $stmt_old = $conn->prepare("SELECT name_vendor, code_vendor, alamat FROM tbl_vendor WHERE id_vendor = ?");
    $stmt_old->bind_param("i", $id_vendor);
    $stmt_old->execute();
    $old_data = $stmt_old->get_result()->fetch_assoc();
    $old_data_json = json_encode($old_data, JSON_UNESCAPED_UNICODE);

    // Update roles
    $stmt_update = $conn->prepare("UPDATE tbl_vendor 
                                   SET name_vendor = ?, code_vendor = ?, alamat = ?, updated_by = ?, timestamp = ? 
                                   WHERE id_vendor = ?");
    $stmt_update->bind_param("sssssi", $name_vendor, $code_vendor, $alamat, $updated_by, $timestamp, $id_vendor);

    if ($stmt_update->execute()) {
        // Siapkan data baru untuk logging
        $new_data = [
            "name_vendor"   => $name_vendor,
            "code_vendor" => $code_vendor,
            "alamat" => $alamat
        ];
        $new_data_json = json_encode($new_data, JSON_UNESCAPED_UNICODE);

        // Insert log ke tlog_role
        $stmt_log = $conn->prepare("INSERT INTO tlog_vendor 
            (id_vendor, updated_by, action_type, old_data, new_data, created_at, updated_at) 
            VALUES (?, ?, 'UPDATE', ?, ?, NOW(), NOW())");
        $stmt_log->bind_param("isss", $id_vendor, $updated_by, $old_data_json, $new_data_json);
        $stmt_log->execute();

        $_SESSION['green_notif'] = "Data vendor berhasil diperbarui.";
    } else {
        $_SESSION['red_notif'] = "Vendor tidak berhasil diupdate.";
    }

    header("Location: /isubcont/pages/master-vendor.php");
    exit;
}

// REMOVE role (soft delete)
if (isset($_POST['remove-vendor'])) {
    $id_vendor = $_POST['id_vendor'];
    $username  = $_SESSION['username'] ?? 'SYSTEM';

    // 1. Ambil data role
    $stmt = $conn->prepare("SELECT * FROM tbl_vendor WHERE id_vendor = ? AND is_deleted = 0 LIMIT 1");
    $stmt->bind_param("i", $id_vendor);
    $stmt->execute();
    $result = $stmt->get_result();
    $role = $result->fetch_assoc();
    $stmt->close();

    if (!$role) {
        $_SESSION['red_notif'] = "Data vendor tidak ditemukan atau sudah dihapus.";
        header('Location: /isubcont/pages/master-vendor.php');
        exit;
    }

    $old_data_json = json_encode($role, JSON_UNESCAPED_UNICODE);

    // Simulasi data baru
    $role['is_deleted'] = 1;
    $new_data_json = json_encode($role, JSON_UNESCAPED_UNICODE);

    // 2. Update roles (soft delete)
    $stmt = $conn->prepare("UPDATE tbl_vendor SET is_deleted = 1, updated_by = ?, timestamp = NOW() WHERE id_vendor = ?");
    $stmt->bind_param("si", $username, $id_vendor);
    $success = $stmt->execute();
    $stmt->close();

    if ($success) {
        // 3. Log ke tlog_role
        $stmt = $conn->prepare("INSERT INTO tlog_vendor
            (id_vendor, updated_by, action_type, old_data, new_data, created_at, updated_at)
            VALUES (?, ?, 'REMOVE', ?, ?, NOW(), NOW())");
        $stmt->bind_param("isss", $id_vendor, $username, $old_data_json, $new_data_json);
        $stmt->execute();
        $stmt->close();

        $_SESSION['green_notif'] = "Data vendor berhasil dihapus.";
    } else {
        $_SESSION['red_notif'] = "Gagal menghapus data vendor.";
    }

    header('Location: /isubcont/pages/master-vendor.php');
    exit;
}

// RESTORE role
if (isset($_POST['restore-vendor'])) {
    $id_vendor = $_POST['id_vendor'];
    $username = $_SESSION['username'] ?? 'SYSTEM';

    $stmt = $conn->prepare("SELECT * FROM tbl_vendor WHERE id_vendor = ? LIMIT 1");
    $stmt->bind_param("i", $id_vendor);
    $stmt->execute();
    $result = $stmt->get_result();
    $role = $result->fetch_assoc();
    $stmt->close();

    if ($role && $role['is_deleted'] == 1) {
        $old_data_json = json_encode($role, JSON_UNESCAPED_UNICODE);

        // Update restore
        $stmt = $conn->prepare("UPDATE tbl_vendor SET is_deleted = 0, updated_by = ?, timestamp = NOW() WHERE id_vendor = ?");
        $stmt->bind_param("si", $username, $id_vendor);
        $success = $stmt->execute();
        $stmt->close();

        if ($success) {
            $role['is_deleted'] = 0;
            $new_data_json = json_encode($role, JSON_UNESCAPED_UNICODE);

            $stmt = $conn->prepare("INSERT INTO tlog_vendor 
                (id_vendor, updated_by, action_type, old_data, new_data, created_at, updated_at)
                VALUES (?, ?, 'RESTORE', ?, ?, NOW(), NOW())");
            $stmt->bind_param("isss", $id_vendor, $username, $old_data_json, $new_data_json);
            $stmt->execute();
            $stmt->close();

            $_SESSION['green_notif'] = "Data vendor berhasil direstore.";
        } else {
            $_SESSION['red_notif'] = "Data vendor gagal direstore.";
        }
    } else {
        $_SESSION['red_notif'] = "Data vendor tidak ditemukan atau belum dihapus.";
    }

    header("Location: /isubcont/pages/archive-vendor.php");
    exit();
}

// DELETE permanent role
if (isset($_POST['delete-vendor'])) {
    $id_vendor = $_POST['id_vendor'];
    $username = $_SESSION['username'] ?? 'SYSTEM';

    $stmt = $conn->prepare("SELECT * FROM tbl_vendor WHERE id_vendor = ? LIMIT 1");
    $stmt->bind_param("i", $id_vendor);
    $stmt->execute();
    $result = $stmt->get_result();
    $role = $result->fetch_assoc();
    $stmt->close();

    if ($role) {
        $old_data_json = json_encode($role, JSON_UNESCAPED_UNICODE);

        // DELETE permanen
        $stmt = $conn->prepare("DELETE FROM tbl_vendor WHERE id_vendor = ?");
        $stmt->bind_param("i", $id_vendor);
        $success = $stmt->execute();
        $stmt->close();

        if ($success) {
            $new_data = [
                "note" => "Vendor dihapus permanen oleh {$username} pada " . date('Y-m-d H:i:s')
            ];
            $new_data_json = json_encode($new_data, JSON_UNESCAPED_UNICODE);

            $stmt = $conn->prepare("INSERT INTO tlog_vendor 
                (id_vendor, updated_by, action_type, old_data, new_data, created_at, updated_at)
                VALUES (?, ?, 'DELETE', ?, ?, NOW(), NOW())");
            $stmt->bind_param("isss", $id_vendor, $username, $old_data_json, $new_data_json);
            $stmt->execute();
            $stmt->close();

            $_SESSION['green_notif'] = "Data vendor berhasil dihapus permanen.";
        } else {
            $_SESSION['red_notif'] = "Data vendor gagal dihapus permanen.";
        }
    } else {
        $_SESSION['red_notif'] = "Data vendor tidak ditemukan.";
    }

    header("Location: /isubcont/pages/archive-vendor.php");
    exit();
}

if (isset($_POST['submit-komponen'])) {

    date_default_timezone_set('Asia/Jakarta');
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $updated_by      = $_SESSION['username'] ?? 'unknown';
    $model_input     = trim($_POST['model']);
    $style_input     = trim($_POST['style']);
    $input_komponen  = $_POST['input_komponen'] ?? [];
    $output_komponen = trim($_POST['output_komponen']);
    $vendor_id       = !empty($_POST['vendor_id']) ? (int)$_POST['vendor_id'] : null;
    $main_index      = isset($_POST['main_komponen']) ? (int)$_POST['main_komponen'] : 0;

    try {

        $conn->begin_transaction();

        /* ===================================== */
        /* VALIDASI INPUT */
        /* ===================================== */

        $clean_input_komponen = [];

        foreach ($input_komponen as $i => $komponen_in) {

            $komponen_in = trim($komponen_in);

            if ($komponen_in === '') {
                continue;
            }

            $clean_input_komponen[$i] = $komponen_in;
        }

        if (empty($clean_input_komponen)) {
            throw new Exception('Input komponen belum diisi.');
        }

        if ($output_komponen === '') {
            throw new Exception('Output komponen belum diisi.');
        }

        if (!array_key_exists($main_index, $clean_input_komponen)) {

            $main_index = array_key_first($clean_input_komponen);
        }

        /* ===================================== */
        /* CARI MODEL MIRIP */
        /* ===================================== */

        $stmt = $conn->prepare("
            SELECT DISTINCT model 
            FROM tbl_master_data 
            WHERE model LIKE CONCAT('%', ?, '%')
        ");

        $stmt->bind_param(
            "s",
            $model_input
        );

        $stmt->execute();

        $result = $stmt->get_result();

        $models = [];

        while ($row = $result->fetch_assoc()) {
            $models[] = $row['model'];
        }

        if (empty($models)) {
            $models = [$model_input];
        }

        /* ===================================== */
        /* LOOP PER MODEL */
        /* ===================================== */

        foreach ($models as $similar_model) {

            /* ===================================== */
            /* INSERT OUTPUT */
            /* ===================================== */

            $stmt_output = $conn->prepare("
                INSERT INTO tbl_komponen 
                (
                    model,
                    style,
                    nama_komponen,
                    is_deleted,
                    updated_by,
                    timestamp
                ) 
                VALUES 
                (
                    ?,
                    ?,
                    ?,
                    0,
                    ?,
                    NOW()
                )
            ");

            $stmt_output->bind_param(
                "ssss",
                $similar_model,
                $style_input,
                $output_komponen,
                $updated_by
            );

            $stmt_output->execute();

            $id_output = $stmt_output->insert_id;

            /* ===================================== */
            /* INSERT INPUT FIRST */
            /* ===================================== */

            $inputRows = [];

            foreach ($clean_input_komponen as $i => $komponen_in) {

                $is_main =
                    ((int)$i === (int)$main_index)
                    ? 1
                    : 0;

                $stmt_input = $conn->prepare("
                    INSERT INTO tbl_komponen 
                    (
                        model,
                        style,
                        nama_komponen,
                        is_deleted,
                        updated_by,
                        timestamp
                    ) 
                    VALUES 
                    (
                        ?,
                        ?,
                        ?,
                        0,
                        ?,
                        NOW()
                    )
                ");

                $stmt_input->bind_param(
                    "ssss",
                    $similar_model,
                    $style_input,
                    $komponen_in,
                    $updated_by
                );

                $stmt_input->execute();

                $id_input = $stmt_input->insert_id;

                $inputRows[] = [

                    'index' =>
                    (int)$i,

                    'id_input' =>
                    (int)$id_input,

                    'nama_komponen' =>
                    $komponen_in,

                    'is_main' =>
                    (int)$is_main

                ];
            }

            /* ===================================== */
            /* GET ID GROUP FROM MAIN COMPONENT */
            /* ===================================== */

            $id_group = null;

            foreach ($inputRows as $rowInput) {

                if (
                    (int)$rowInput['is_main']
                    ===
                    1
                ) {

                    $id_group =
                        (int)$rowInput['id_input'];

                    break;
                }
            }

            if (empty($id_group)) {
                throw new Exception('Main komponen belum dipilih.');
            }

            /* ===================================== */
            /* INSERT RELATION */
            /* ===================================== */

            foreach ($inputRows as $rowInput) {

                $stmt_rel = $conn->prepare("
                    INSERT INTO tbl_komponen_proses 
                    (
                        id_group,
                        id_input,
                        id_output,
                        is_main
                    ) 
                    VALUES 
                    (
                        ?,
                        ?,
                        ?,
                        ?
                    )
                ");

                $stmt_rel->bind_param(
                    "iiii",
                    $id_group,
                    $rowInput['id_input'],
                    $id_output,
                    $rowInput['is_main']
                );

                $stmt_rel->execute();

                $id_proses =
                    $stmt_rel->insert_id;

                /* ===================================== */
                /* RELASI VENDOR */
                /* ===================================== */

                if ($vendor_id) {

                    $stmt_vendor = $conn->prepare("
                        INSERT INTO tbl_vendor_proses 
                        (
                            id_vendor,
                            id_proses,
                            created_at,
                            updated_at
                        ) 
                        VALUES 
                        (
                            ?,
                            ?,
                            NOW(),
                            NOW()
                        )
                    ");

                    $stmt_vendor->bind_param(
                        "ii",
                        $vendor_id,
                        $id_proses
                    );

                    $stmt_vendor->execute();
                }

                /* ===================================== */
                /* LOG */
                /* ===================================== */

                $new_data = [

                    "model" =>
                    $similar_model,

                    "style" =>
                    $style_input,

                    "id_group" =>
                    $id_group,

                    "input" =>
                    $rowInput['nama_komponen'],

                    "output" =>
                    $output_komponen,

                    "vendor" =>
                    $vendor_id,

                    "is_main" =>
                    $rowInput['is_main']

                ];

                $new_data_json =
                    json_encode(
                        $new_data,
                        JSON_UNESCAPED_UNICODE
                    );

                $stmt_log = $conn->prepare("
                    INSERT INTO tlog_komponen 
                    (
                        id_komponen,
                        updated_by,
                        action_type,
                        old_data,
                        new_data,
                        created_at,
                        timestamp
                    ) 
                    VALUES 
                    (
                        ?,
                        ?,
                        'INSERT',
                        NULL,
                        ?,
                        NOW(),
                        NOW()
                    )
                ");

                $stmt_log->bind_param(
                    "iss",
                    $rowInput['id_input'],
                    $updated_by,
                    $new_data_json
                );

                $stmt_log->execute();
            }
        }

        /* ===================================== */
        /* COMMIT */
        /* ===================================== */

        $conn->commit();

        $_SESSION['green_notif'] =
            "Data komponen berhasil disimpan.";

        header(
            "Location: /isubcont/pages/master-komponen.php"
        );

        exit;
    } catch (Exception $e) {

        $conn->rollback();

        die("Gagal insert komponen: " .
            $e->getMessage());
    }
}

if (isset($_POST['update-komponen'])) {
    $id_output       = $_POST['id_output'];
    $model           = trim($_POST['model']);
    $style           = trim($_POST['style']);
    $input_komponen  = $_POST['input_komponen']; // array
    $output_komponen = trim($_POST['output_komponen']);
    $vendor_id       = $_POST['vendor_id']; // single select
    $updated_by      = $_SESSION['username'];

    // === Ambil id_proses dari tbl_komponen_proses ===
    $stmt_proc = $conn->prepare("SELECT id_proses FROM tbl_komponen_proses WHERE id_output=? LIMIT 1");
    $stmt_proc->bind_param("i", $id_output);
    $stmt_proc->execute();
    $res_proc = $stmt_proc->get_result()->fetch_assoc();
    $id_proses = $res_proc['id_proses'] ?? null;

    if (!$id_proses) {
        $_SESSION['red_notif'] = "Proses terkait komponen tidak ditemukan.";
        header("Location: /isubcont/pages/master-komponen.php");
        exit;
    }

    // === Update output komponen ===
    $stmt = $conn->prepare("SELECT nama_komponen FROM tbl_komponen WHERE id_komponen=?");
    $stmt->bind_param("i", $id_output);
    $stmt->execute();
    $res_out = $stmt->get_result()->fetch_assoc();
    $old_output = $res_out['nama_komponen'];

    $stmt = $conn->prepare("UPDATE tbl_komponen 
                            SET nama_komponen=?, updated_by=?, timestamp=NOW() 
                            WHERE id_komponen=?");
    $stmt->bind_param("ssi", $output_komponen, $updated_by, $id_output);
    $stmt->execute();

    // Log perubahan output
    $old_data_json = json_encode(["model" => $model, "style" => $style, "output" => $old_output], JSON_UNESCAPED_UNICODE);
    $new_data_json = json_encode(["model" => $model, "style" => $style, "output" => $output_komponen], JSON_UNESCAPED_UNICODE);

    $stmt_log = $conn->prepare("INSERT INTO tlog_komponen 
        (id_komponen, updated_by, action_type, old_data, new_data, created_at, timestamp) 
        VALUES (?, ?, 'UPDATE', ?, ?, NOW(), NOW())");
    $stmt_log->bind_param("isss", $id_output, $updated_by, $old_data_json, $new_data_json);
    $stmt_log->execute();

    // === Update semua input komponen terkait ===
    $stmt = $conn->prepare("SELECT id_input FROM tbl_komponen_proses WHERE id_output=?");
    $stmt->bind_param("i", $id_output);
    $stmt->execute();
    $res = $stmt->get_result();

    $i = 0;
    while ($row = $res->fetch_assoc()) {
        if (!isset($input_komponen[$i])) continue;
        $id_input = $row['id_input'];
        $new_name = trim($input_komponen[$i]);

        // Ambil data lama
        $stmt_old = $conn->prepare("SELECT nama_komponen FROM tbl_komponen WHERE id_komponen=?");
        $stmt_old->bind_param("i", $id_input);
        $stmt_old->execute();
        $old_input = $stmt_old->get_result()->fetch_assoc()['nama_komponen'] ?? '';

        // Update input komponen
        $stmt_upd = $conn->prepare("UPDATE tbl_komponen 
                                    SET nama_komponen=?, updated_by=?, timestamp=NOW() 
                                    WHERE id_komponen=?");
        $stmt_upd->bind_param("ssi", $new_name, $updated_by, $id_input);
        $stmt_upd->execute();

        // Log perubahan input
        $old_data_json = json_encode(["model" => $model, "style" => $style, "input" => $old_input], JSON_UNESCAPED_UNICODE);
        $new_data_json = json_encode(["model" => $model, "style" => $style, "input" => $new_name], JSON_UNESCAPED_UNICODE);

        $stmt_log = $conn->prepare("INSERT INTO tlog_komponen 
            (id_komponen, updated_by, action_type, old_data, new_data, created_at, timestamp) 
            VALUES (?, ?, 'UPDATE', ?, ?, NOW(), NOW())");
        $stmt_log->bind_param("isss", $id_input, $updated_by, $old_data_json, $new_data_json);
        $stmt_log->execute();

        $i++;
    }

    // === Update vendor di tbl_vendor_proses ===
    // Hapus vendor lama terkait id_proses
    $stmt = $conn->prepare("DELETE FROM tbl_vendor_proses WHERE id_proses=?");
    $stmt->bind_param("i", $id_proses);
    $stmt->execute();

    // Insert vendor baru
    $stmt = $conn->prepare("INSERT INTO tbl_vendor_proses (id_proses, id_vendor) VALUES (?, ?)");
    $stmt->bind_param("ii", $id_proses, $vendor_id);
    $stmt->execute();

    // Log perubahan vendor
    $stmt_old = $conn->prepare("SELECT v.name_vendor 
                                FROM tbl_vendor v 
                                LEFT JOIN tbl_vendor_proses vp ON vp.id_vendor = v.id_vendor 
                                WHERE vp.id_proses = ?");
    $stmt_old->bind_param("i", $id_proses);
    $stmt_old->execute();
    $old_vendor_res = $stmt_old->get_result()->fetch_assoc();
    $old_vendor_name = $old_vendor_res['name_vendor'] ?? '';

    $stmt_vlog = $conn->prepare("INSERT INTO tlog_komponen 
        (id_komponen, updated_by, action_type, old_data, new_data, created_at, timestamp) 
        VALUES (?, ?, 'UPDATE', ?, ?, NOW(), NOW())");
    $old_data_json = json_encode(["vendor" => $old_vendor_name]);
    $new_data_json = json_encode(["vendor" => $vendor_id]);
    $stmt_vlog->bind_param("isss", $id_output, $updated_by, $old_data_json, $new_data_json);
    $stmt_vlog->execute();

    // Selesai
    $_SESSION['green_notif'] = "Komponen berhasil diupdate.";
    header("Location: /isubcont/pages/master-komponen.php");
    exit;
}

if (isset($_POST['remove-komponen'])) {
    $id_output  = $_POST['id_output']; // ambil dari form
    $updated_by = $_SESSION['username'];

    // Ambil semua input + output + id_proses
    $stmt = $conn->prepare("
        SELECT p.id_proses, p.id_input, p.id_output, 
               k_in.nama_komponen AS input_name, 
               k_out.nama_komponen AS output_name,
               k_in.model AS input_model, k_in.style AS input_style,
               k_out.model AS output_model, k_out.style AS output_style
        FROM tbl_komponen_proses p
        JOIN tbl_komponen k_in ON p.id_input = k_in.id_komponen
        JOIN tbl_komponen k_out ON p.id_output = k_out.id_komponen
        WHERE p.id_output = ?
    ");
    $stmt->bind_param("i", $id_output);
    $stmt->execute();
    $result = $stmt->get_result();

    $inputs = [];
    $outputData = null;
    $id_proses = null;

    while ($row = $result->fetch_assoc()) {
        $inputs[] = $row;
        $outputData = $row; // cukup satu untuk output
        $id_proses = $row['id_proses']; // ambil id_proses
    }

    // Ambil vendor terkait id_proses
    $stmt_vendor = $conn->prepare("
        SELECT v.name_vendor 
        FROM tbl_vendor v 
        JOIN tbl_vendor_proses vp ON vp.id_vendor = v.id_vendor
        WHERE vp.id_proses = ?
    ");
    $stmt_vendor->bind_param("i", $id_proses);
    $stmt_vendor->execute();
    $vendor_result = $stmt_vendor->get_result();
    $vendor_names = [];
    while ($v = $vendor_result->fetch_assoc()) {
        $vendor_names[] = $v['name_vendor'];
    }
    $vendor_names_str = implode(", ", $vendor_names);

    // Fungsi bantu untuk soft delete + logging (dengan vendor)
    function softDeleteAndLog($conn, $id_komponen, $old_data, $updated_by, $vendor_names)
    {
        // Update komponen
        $stmt_del = $conn->prepare("
            UPDATE tbl_komponen 
            SET is_deleted = 1, updated_by = ?, timestamp = NOW() 
            WHERE id_komponen = ?
        ");
        $stmt_del->bind_param("si", $updated_by, $id_komponen);
        $stmt_del->execute();

        // Prepare new_data
        $new_data_json = json_encode(array_merge($old_data, ['is_deleted' => 1, 'vendor' => $vendor_names]), JSON_UNESCAPED_UNICODE);

        // Logging
        $stmt_log = $conn->prepare("
            INSERT INTO tlog_komponen 
            (id_komponen, updated_by, action_type, old_data, new_data, created_at, timestamp) 
            VALUES (?, ?, 'REMOVE', ?, ?, NOW(), NOW())
        ");
        $old_data_json = json_encode(array_merge($old_data, ['vendor' => $vendor_names]), JSON_UNESCAPED_UNICODE);
        $stmt_log->bind_param("isss", $id_komponen, $updated_by, $old_data_json, $new_data_json);
        $stmt_log->execute();
    }

    // Soft delete semua input
    foreach ($inputs as $row) {
        $old_data = [
            "model" => $row['input_model'],
            "style" => $row['input_style'],
            "input" => $row['input_name']
        ];
        softDeleteAndLog($conn, $row['id_input'], $old_data, $updated_by, $vendor_names_str);
    }

    // Soft delete output
    if ($outputData) {
        $old_data = [
            "model"  => $outputData['output_model'],
            "style"  => $outputData['output_style'],
            "output" => $outputData['output_name']
        ];
        softDeleteAndLog($conn, $outputData['id_output'], $old_data, $updated_by, $vendor_names_str);
    }

    $_SESSION['green_notif'] = "Komponen berhasil dihapus.";
    header("Location: /isubcont/pages/master-komponen.php");
    exit;
}

if (isset($_POST['restore-komponen'])) {
    $id_output  = $_POST['id_output'];
    $updated_by = $_SESSION['username'];

    // Ambil id_proses dari tbl_komponen_proses
    $stmt_proc = $conn->prepare("SELECT id_proses FROM tbl_komponen_proses WHERE id_output=? LIMIT 1");
    $stmt_proc->bind_param("i", $id_output);
    $stmt_proc->execute();
    $res_proc = $stmt_proc->get_result()->fetch_assoc();
    $id_proses = $res_proc['id_proses'] ?? null;

    // Ambil vendor terkait id_proses
    $vendor_names_str = "";
    if ($id_proses) {
        $stmt_vendor = $conn->prepare("
            SELECT v.name_vendor 
            FROM tbl_vendor v 
            JOIN tbl_vendor_proses vp ON vp.id_vendor = v.id_vendor
            WHERE vp.id_proses = ?
        ");
        $stmt_vendor->bind_param("i", $id_proses);
        $stmt_vendor->execute();
        $vendor_result = $stmt_vendor->get_result();
        $vendor_names = [];
        while ($v = $vendor_result->fetch_assoc()) {
            $vendor_names[] = $v['name_vendor'];
        }
        $vendor_names_str = implode(", ", $vendor_names);
    }

    // Fungsi bantu restore + logging (dengan vendor)
    function restoreAndLog($conn, $id_komponen, $updated_by, $vendor_names)
    {
        // Ambil data lama sebelum restore
        $stmt_old = $conn->prepare("SELECT * FROM tbl_komponen WHERE id_komponen=?");
        $stmt_old->bind_param("i", $id_komponen);
        $stmt_old->execute();
        $res_old = $stmt_old->get_result()->fetch_assoc();

        if (!$res_old) return; // kalau ga ada data, skip

        $old_data_json = json_encode(array_merge($res_old, ['vendor' => $vendor_names]), JSON_UNESCAPED_UNICODE);

        // Restore komponen
        $stmt_upd = $conn->prepare("UPDATE tbl_komponen SET is_deleted=0, updated_by=?, timestamp=NOW() WHERE id_komponen=?");
        $stmt_upd->bind_param("si", $updated_by, $id_komponen);
        $stmt_upd->execute();

        // Ambil data baru setelah restore
        $stmt_new = $conn->prepare("SELECT * FROM tbl_komponen WHERE id_komponen=?");
        $stmt_new->bind_param("i", $id_komponen);
        $stmt_new->execute();
        $res_new = $stmt_new->get_result()->fetch_assoc();
        $new_data_json = json_encode(array_merge($res_new, ['vendor' => $vendor_names]), JSON_UNESCAPED_UNICODE);

        // Logging
        $stmt_log = $conn->prepare("
            INSERT INTO tlog_komponen 
            (id_komponen, updated_by, action_type, old_data, new_data, created_at, timestamp)
            VALUES (?, ?, 'RESTORE', ?, ?, NOW(), NOW())
        ");
        $stmt_log->bind_param("isss", $id_komponen, $updated_by, $old_data_json, $new_data_json);
        $stmt_log->execute();
    }

    // Restore output
    restoreAndLog($conn, $id_output, $updated_by, $vendor_names_str);

    // Restore semua input terkait output
    $stmt = $conn->prepare("SELECT id_input FROM tbl_komponen_proses WHERE id_output=?");
    $stmt->bind_param("i", $id_output);
    $stmt->execute();
    $res = $stmt->get_result();

    while ($row = $res->fetch_assoc()) {
        restoreAndLog($conn, $row['id_input'], $updated_by, $vendor_names_str);
    }

    $_SESSION['green_notif'] = "Komponen berhasil direstore.";
    header("Location: /isubcont/pages/archive-komponen.php");
    exit;
}

if (isset($_POST['delete-komponen'])) {
    $id_output  = $_POST['id_output'];
    $updated_by = $_SESSION['username'];

    // Ambil id_proses dari tbl_komponen_proses
    $stmt_proc = $conn->prepare("SELECT id_proses FROM tbl_komponen_proses WHERE id_output=? LIMIT 1");
    $stmt_proc->bind_param("i", $id_output);
    $stmt_proc->execute();
    $res_proc = $stmt_proc->get_result()->fetch_assoc();
    $id_proses = $res_proc['id_proses'] ?? null;

    // Ambil vendor terkait id_proses
    $vendor_names_str = "";
    if ($id_proses) {
        $stmt_vendor = $conn->prepare("
            SELECT v.name_vendor 
            FROM tbl_vendor v 
            JOIN tbl_vendor_proses vp ON vp.id_vendor = v.id_vendor
            WHERE vp.id_proses = ?
        ");
        $stmt_vendor->bind_param("i", $id_proses);
        $stmt_vendor->execute();
        $vendor_result = $stmt_vendor->get_result();
        $vendor_names = [];
        while ($v = $vendor_result->fetch_assoc()) {
            $vendor_names[] = $v['name_vendor'];
        }
        $vendor_names_str = implode(", ", $vendor_names);
    }

    // Fungsi bantu delete permanen + logging vendor
    function forceDeleteAndLog($conn, $id_komponen, $updated_by, $vendor_names, $action_type = 'DELETE')
    {
        // Ambil data lama sebelum dihapus
        $stmt_old = $conn->prepare("SELECT * FROM tbl_komponen WHERE id_komponen=?");
        $stmt_old->bind_param("i", $id_komponen);
        $stmt_old->execute();
        $res_old = $stmt_old->get_result()->fetch_assoc();

        if ($res_old) {
            $old_data_json = json_encode(array_merge($res_old, ['vendor' => $vendor_names]), JSON_UNESCAPED_UNICODE);
        } else {
            $old_data_json = json_encode(['vendor' => $vendor_names]);
        }

        // Hapus komponen
        $stmt_del = $conn->prepare("DELETE FROM tbl_komponen WHERE id_komponen=?");
        $stmt_del->bind_param("i", $id_komponen);
        $stmt_del->execute();

        // Buat new_data berisi info delete
        $new_data = [
            'deleted_by' => $updated_by,
            'deleted_at' => date('Y-m-d H:i:s'),
            'permanent'  => true,
            'vendor'     => $vendor_names
        ];
        $new_data_json = json_encode($new_data, JSON_UNESCAPED_UNICODE);

        // Logging
        $stmt_log = $conn->prepare("
            INSERT INTO tlog_komponen 
            (id_komponen, updated_by, action_type, old_data, new_data, created_at, timestamp)
            VALUES (?, ?, ?, ?, ?, NOW(), NOW())
        ");
        $stmt_log->bind_param("issss", $id_komponen, $updated_by, $action_type, $old_data_json, $new_data_json);
        $stmt_log->execute();
    }

    // Ambil semua input yg terkait output
    $stmt = $conn->prepare("SELECT id_input FROM tbl_komponen_proses WHERE id_output=?");
    $stmt->bind_param("i", $id_output);
    $stmt->execute();
    $res = $stmt->get_result();

    while ($row = $res->fetch_assoc()) {
        forceDeleteAndLog($conn, $row['id_input'], $updated_by, $vendor_names_str, 'DELETE');
    }

    // Delete output
    forceDeleteAndLog($conn, $id_output, $updated_by, $vendor_names_str, 'DELETE');

    // Delete relasi proses
    $stmt_rel = $conn->prepare("DELETE FROM tbl_komponen_proses WHERE id_output=?");
    $stmt_rel->bind_param("i", $id_output);
    $stmt_rel->execute();

    $_SESSION['green_notif'] = "Komponen berhasil dihapus permanen.";
    header("Location: /isubcont/pages/archive-komponen.php");
    exit;
}

// REGISTER role
if (isset($_POST['submit-ncvs'])) {
    date_default_timezone_set('Asia/Jakarta');

    // Ambil dan filter data
    $updated_by = mysqli_real_escape_string($conn, $_POST['updated_by']);
    $ncvs   = mysqli_real_escape_string($conn, $_POST['ncvs']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $timestamp  = date('Y-m-d H:i:s');

    // Cek apakah NIK sudah ada
    $check_role = mysqli_query($conn, "SELECT 1 FROM tbl_ncvs WHERE ncvs = '$ncvs'");
    if (mysqli_num_rows($check_role) > 0) {
        $_SESSION['red_notif'] = "NCVS sudah terdaftar, mohon ganti NCVS lain.";
        header("Location: /isubcont/pages/master-ncvs.php");
        exit();
    }

    // Simpan ke tbl_user
    $query_role = mysqli_query($conn, "INSERT INTO tbl_ncvs 
        (ncvs, description, is_deleted, updated_by, timestamp) 
        VALUES 
        ('$ncvs', '$description', '0', '$updated_by', '$timestamp')");

    if ($query_role) {
        $last_user_id = mysqli_insert_id($conn);

        // Siapkan log (hanya simpan data baru)
        $new_data = [
            "ncvs" => $ncvs,
            "description" => $description
        ];
        $new_data_json = mysqli_real_escape_string($conn, json_encode($new_data));

        $query_log = mysqli_query($conn, "INSERT INTO tlog_ncvs 
            (id_ncvs, updated_by, action_type, old_data, new_data, created_at, updated_at) 
            VALUES 
            ('$last_user_id', '$updated_by', 'INSERT', NULL, '$new_data_json', NOW(), NOW())");

        if ($query_log) {
            $_SESSION['green_notif'] = "NCVS berhasil didaftarkan.";
        } else {
            $_SESSION['red_notif'] = "NCVS berhasil didaftarkan, tapi log gagal.";
        }

        header("Location: /isubcont/pages/master-ncvs.php");
        exit();
    } else {
        $_SESSION['red_notif'] = "Role tidak berhasil didaftarkan.";
        header("Location: /isubcont/pages/master-ncvs.php");
        exit();
    }
}

if (isset($_POST['update-ncvs'])) {
    date_default_timezone_set('Asia/Jakarta');

    // Ambil data dan sanitasi
    $id_ncvs          = $_POST['id_ncvs'];
    $updated_by  = $_POST['updated_by'];
    $ncvs   = $_POST['ncvs'];
    $description = $_POST['description'];
    $timestamp   = date('Y-m-d H:i:s');

    // Ambil data lama untuk logging
    $stmt_old = $conn->prepare("SELECT ncvs, description FROM tbl_ncvs WHERE id_ncvs = ?");
    $stmt_old->bind_param("i", $id_ncvs);
    $stmt_old->execute();
    $old_data = $stmt_old->get_result()->fetch_assoc();
    $old_data_json = json_encode($old_data, JSON_UNESCAPED_UNICODE);

    // Update roles
    $stmt_update = $conn->prepare("UPDATE tbl_ncvs 
                                   SET ncvs = ?, description = ?, updated_by = ?, timestamp = ? 
                                   WHERE id_ncvs = ?");
    $stmt_update->bind_param("ssssi", $ncvs, $description, $updated_by, $timestamp, $id_ncvs);

    if ($stmt_update->execute()) {
        // Siapkan data baru untuk logging
        $new_data = [
            "ncvs"   => $ncvs,
            "description" => $description
        ];
        $new_data_json = json_encode($new_data, JSON_UNESCAPED_UNICODE);

        // Insert log ke tlog_role
        $stmt_log = $conn->prepare("INSERT INTO tlog_ncvs 
            (id_ncvs, updated_by, action_type, old_data, new_data, created_at, updated_at) 
            VALUES (?, ?, 'UPDATE', ?, ?, NOW(), NOW())");
        $stmt_log->bind_param("isss", $id_ncvs, $updated_by, $old_data_json, $new_data_json);
        $stmt_log->execute();

        $_SESSION['green_notif'] = "Data NCVS berhasil diperbarui.";
    } else {
        $_SESSION['red_notif'] = "NCVS tidak berhasil diupdate.";
    }

    header("Location: /isubcont/pages/master-ncvs.php");
    exit;
}

// REMOVE role (soft delete)
if (isset($_POST['remove-ncvs'])) {
    $id_ncvs   = $_POST['id_ncvs'];
    $username  = $_SESSION['username'] ?? 'SYSTEM';

    // 1. Ambil data role
    $stmt = $conn->prepare("SELECT * FROM tbl_ncvs WHERE id_ncvs = ? AND is_deleted = 0 LIMIT 1");
    $stmt->bind_param("i", $id_ncvs);
    $stmt->execute();
    $result = $stmt->get_result();
    $role = $result->fetch_assoc();
    $stmt->close();

    if (!$role) {
        $_SESSION['red_notif'] = "Data NCVS tidak ditemukan atau sudah dihapus.";
        header('Location: /isubcont/pages/master-ncvs.php');
        exit;
    }

    $old_data_json = json_encode($role, JSON_UNESCAPED_UNICODE);

    // Simulasi data baru
    $role['is_deleted'] = 1;
    $new_data_json = json_encode($role, JSON_UNESCAPED_UNICODE);

    // 2. Update roles (soft delete)
    $stmt = $conn->prepare("UPDATE tbl_ncvs SET is_deleted = 1, updated_by = ?, timestamp = NOW() WHERE id_ncvs = ?");
    $stmt->bind_param("si", $username, $id_ncvs);
    $success = $stmt->execute();
    $stmt->close();

    if ($success) {
        // 3. Log ke tlog_role
        $stmt = $conn->prepare("INSERT INTO tlog_ncvs
            (id_ncvs, updated_by, action_type, old_data, new_data, created_at, updated_at)
            VALUES (?, ?, 'REMOVE', ?, ?, NOW(), NOW())");
        $stmt->bind_param("isss", $id_ncvs, $username, $old_data_json, $new_data_json);
        $stmt->execute();
        $stmt->close();

        $_SESSION['green_notif'] = "Data NCVS berhasil dihapus.";
    } else {
        $_SESSION['red_notif'] = "Gagal menghapus data NCVS.";
    }

    header('Location: /isubcont/pages/master-ncvs.php');
    exit;
}

// RESTORE role
if (isset($_POST['restore-ncvs'])) {
    $id_ncvs  = $_POST['id_ncvs'];
    $username = $_SESSION['username'] ?? 'SYSTEM';

    $stmt = $conn->prepare("SELECT * FROM tbl_ncvs WHERE id_ncvs = ? LIMIT 1");
    $stmt->bind_param("i", $id_ncvs);
    $stmt->execute();
    $result = $stmt->get_result();
    $role = $result->fetch_assoc();
    $stmt->close();

    if ($role && $role['is_deleted'] == 1) {
        $old_data_json = json_encode($role, JSON_UNESCAPED_UNICODE);

        // Update restore
        $stmt = $conn->prepare("UPDATE tbl_ncvs SET is_deleted = 0, updated_by = ?, timestamp = NOW() WHERE id_ncvs = ?");
        $stmt->bind_param("si", $username, $id_ncvs);
        $success = $stmt->execute();
        $stmt->close();

        if ($success) {
            $role['is_deleted'] = 0;
            $new_data_json = json_encode($role, JSON_UNESCAPED_UNICODE);

            $stmt = $conn->prepare("INSERT INTO tlog_ncvs 
                (id_ncvs, updated_by, action_type, old_data, new_data, created_at, updated_at)
                VALUES (?, ?, 'RESTORE', ?, ?, NOW(), NOW())");
            $stmt->bind_param("isss", $id_ncvs, $username, $old_data_json, $new_data_json);
            $stmt->execute();
            $stmt->close();

            $_SESSION['green_notif'] = "Data NCVS berhasil direstore.";
        } else {
            $_SESSION['red_notif'] = "Data NCVS gagal direstore.";
        }
    } else {
        $_SESSION['red_notif'] = "Data NCVS tidak ditemukan atau belum dihapus.";
    }

    header("Location: /isubcont/pages/archive-ncvs.php");
    exit();
}

// DELETE permanent role
if (isset($_POST['delete-ncvs'])) {
    $id_ncvs  = $_POST['id_ncvs'];
    $username = $_SESSION['username'] ?? 'SYSTEM';

    $stmt = $conn->prepare("SELECT * FROM tbl_ncvs WHERE id_ncvs = ? LIMIT 1");
    $stmt->bind_param("i", $id_ncvs);
    $stmt->execute();
    $result = $stmt->get_result();
    $role = $result->fetch_assoc();
    $stmt->close();

    if ($role) {
        $old_data_json = json_encode($role, JSON_UNESCAPED_UNICODE);

        // DELETE permanen
        $stmt = $conn->prepare("DELETE FROM tbl_ncvs WHERE id_ncvs = ?");
        $stmt->bind_param("i", $id_ncvs);
        $success = $stmt->execute();
        $stmt->close();

        if ($success) {
            $new_data = [
                "note" => "NCVS dihapus permanen oleh {$username} pada " . date('Y-m-d H:i:s')
            ];
            $new_data_json = json_encode($new_data, JSON_UNESCAPED_UNICODE);

            $stmt = $conn->prepare("INSERT INTO tlog_ncvs 
                (id_ncvs, updated_by, action_type, old_data, new_data, created_at, updated_at)
                VALUES (?, ?, 'DELETE', ?, ?, NOW(), NOW())");
            $stmt->bind_param("isss", $id_ncvs, $username, $old_data_json, $new_data_json);
            $stmt->execute();
            $stmt->close();

            $_SESSION['green_notif'] = "Data NCVS berhasil dihapus permanen.";
        } else {
            $_SESSION['red_notif'] = "Data NCVS gagal dihapus permanen.";
        }
    } else {
        $_SESSION['red_notif'] = "Data NCVS tidak ditemukan.";
    }

    header("Location: /isubcont/pages/archive-ncvs.php");
    exit();
}

if (isset($_POST['submit-plan'])) {
    $updated_by = $_SESSION['username'];
    $start_date = $_POST['start_date'];
    $end_date   = $_POST['end_date'];
    $ncvs       = $_POST['ncvs'];        // array
    $planCycle  = $_POST['plan_cycle'];  // array

    // === VALIDASI: cek duplikat NCVS untuk tanggal dalam range ===
    foreach ($ncvs as $id_ncvs) {
        $sql_check = "
            SELECT 1
            FROM tbl_plan p
            JOIN tbl_plan_detail d ON p.id_cycle = d.id_cycle
            WHERE d.id_ncvs = ?
              AND (
                    (p.start_date <= ? AND p.end_date >= ?)
                 OR (p.start_date <= ? AND p.end_date >= ?)
                 OR (p.start_date >= ? AND p.end_date <= ?)
              )
            LIMIT 1
        ";
        $stmt_check = $conn->prepare($sql_check);
        $stmt_check->bind_param(
            "issssss",
            $id_ncvs,
            $end_date,
            $start_date,
            $start_date,
            $end_date,
            $start_date,
            $end_date
        );
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();

        if ($result_check->num_rows > 0) {
            $_SESSION['red_notif'] = "NCVS sudah ada dalam periode $start_date s/d $end_date. Tidak boleh duplikat.";
            header("Location: /isubcont/pages/master-plan.php");
            exit;
        }
    }

    // Kalau lolos validasi, baru lanjut insert
    $conn->begin_transaction();

    try {
        // 1. Insert ke tbl_plan (header)
        $stmt = $conn->prepare("
            INSERT INTO tbl_plan (start_date, end_date, created_by, created_at) 
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->bind_param("sss", $start_date, $end_date, $updated_by);
        $stmt->execute();
        $id_cycle = $conn->insert_id;

        // 2. Insert ke tbl_plan_detail (details, per tanggal)
        $stmt_detail = $conn->prepare("
            INSERT INTO tbl_plan_detail (id_cycle, plan_date, id_ncvs, plan_cycle, status, created_by, created_at) 
            VALUES (?, ?, ?, ?, 1, ?, NOW())
        ");

        $period = new DatePeriod(
            new DateTime($start_date),
            new DateInterval('P1D'),
            (new DateTime($end_date))->modify('+1 day') // agar end_date ikut masuk
        );

        foreach ($period as $date) {
            $plan_date = $date->format("Y-m-d");
            for ($i = 0; $i < count($ncvs); $i++) {
                $id_ncvs = $ncvs[$i];
                $cycle   = $planCycle[$i];
                $stmt_detail->bind_param("isiis", $id_cycle, $plan_date, $id_ncvs, $cycle, $updated_by);
                $stmt_detail->execute();
            }
        }

        // 3. Logging ke tlog_plan
        $new_data = [
            'start_date' => $start_date,
            'end_date'   => $end_date,
            'details'    => []
        ];

        foreach ($period as $date) {
            $plan_date = $date->format("Y-m-d");
            for ($i = 0; $i < count($ncvs); $i++) {
                $new_data['details'][] = [
                    'plan_date'  => $plan_date,
                    'id_ncvs'    => $ncvs[$i],
                    'plan_cycle' => $planCycle[$i]
                ];
            }
        }

        $json_new_data = json_encode($new_data);

        $stmt_log = $conn->prepare("
            INSERT INTO tlog_plan (id_cycle, updated_by, action_type, old_data, new_data, created_at, updated_at) 
            VALUES (?, ?, 'TOGGLE', NULL, ?, NOW(), NOW())
        ");
        $stmt_log->bind_param("iss", $id_cycle, $updated_by, $json_new_data);
        $stmt_log->execute();

        $conn->commit();

        $_SESSION['green_notif'] = "Plan cycle berhasil ditambahkan.";
        header("Location: /isubcont/pages/master-plan.php");
        exit;
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['red_notif'] = "Terjadi kesalahan: " . $e->getMessage();
        header("Location: /isubcont/pages/master-plan.php");
        exit;
    }
}

if (isset($_POST['toggle_plan_status'])) {
    $id_cycle_detail = $_POST['id_cycle_detail'];
    $status          = isset($_POST['status']) ? 1 : 0;
    $created_by      = $_SESSION['username']; // karena di tbl_plan_detail namanya created_by

    $conn->begin_transaction();
    try {
        // 1. Ambil data lama
        $sql_old = "SELECT * FROM tbl_plan_detail WHERE id_cycle_detail = ?";
        $stmt_old = $conn->prepare($sql_old);
        $stmt_old->bind_param("i", $id_cycle_detail);
        $stmt_old->execute();
        $old_data = $stmt_old->get_result()->fetch_assoc();

        if (!$old_data) {
            $_SESSION['red_notif'] = "Data plan tidak ditemukan.";
            header("Location: /isubcont/pages/master-plan.php");
            exit;
        }

        // 2. Update status + isi created_by & created_at sesuai struktur tabel
        $stmt = $conn->prepare("
            UPDATE tbl_plan_detail
            SET status = ?, created_by = ?, created_at = NOW()
            WHERE id_cycle_detail = ?
        ");
        $stmt->bind_param("isi", $status, $created_by, $id_cycle_detail);
        $stmt->execute();

        // 3. Logging perubahan ke tlog_plan (kolomnya updated_by & updated_at)
        $new_data = $old_data;
        $new_data['status'] = $status;

        $json_old = json_encode($old_data);
        $json_new = json_encode($new_data);

        $stmt_log = $conn->prepare("
            INSERT INTO tlog_plan (id_cycle, updated_by, action_type, old_data, new_data, created_at, updated_at)
            VALUES (?, ?, 'TOGGLE', ?, ?, NOW(), NOW())
        ");
        $stmt_log->bind_param("isss", $old_data['id_cycle'], $created_by, $json_old, $json_new);
        $stmt_log->execute();

        // 4. Commit
        $conn->commit();

        $_SESSION['green_notif'] = "Status plan berhasil diubah.";
        header("Location: /isubcont/pages/master-plan.php");
        exit;
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['red_notif'] = "Gagal mengubah status: " . $e->getMessage();
        header("Location: /isubcont/pages/master-plan.php");
        exit;
    }
}

if (isset($_POST['update-plan'])) {
    $id_cycle_detail = $_POST['id_cycle_detail']; // primary key tbl_plan_detail
    $updated_by      = $_POST['updated_by'];
    $plan_cycle      = $_POST['plan_cycle'];

    $conn->begin_transaction();

    try {
        // === Ambil data lama untuk logging ===
        $stmt_old = $conn->prepare("
            SELECT id_cycle, plan_date, id_ncvs, plan_cycle 
            FROM tbl_plan_detail 
            WHERE id_cycle_detail = ?
        ");
        $stmt_old->bind_param("i", $id_cycle_detail);
        $stmt_old->execute();
        $old_detail = $stmt_old->get_result()->fetch_assoc();

        if (!$old_detail) {
            throw new Exception("Detail plan tidak ditemukan.");
        }

        $old_data = [
            'id_cycle'  => $old_detail['id_cycle'],
            'plan_date' => $old_detail['plan_date'],
            'id_ncvs'   => $old_detail['id_ncvs'],
            'plan_cycle' => $old_detail['plan_cycle']
        ];
        $json_old_data = json_encode($old_data);

        // === Update plan_cycle ===
        $stmt_update = $conn->prepare("
            UPDATE tbl_plan_detail 
            SET plan_cycle = ?, created_by = ?, created_at = NOW()
            WHERE id_cycle_detail = ?
        ");
        // Sesuaikan bind_param: i = integer, s = string
        $stmt_update->bind_param("isi", $plan_cycle, $updated_by, $id_cycle_detail);
        $stmt_update->execute();

        // === Ambil data baru untuk logging ===
        $new_data = [
            'id_cycle'  => $old_detail['id_cycle'],
            'plan_date' => $old_detail['plan_date'],
            'id_ncvs'   => $old_detail['id_ncvs'],
            'plan_cycle' => $plan_cycle
        ];
        $json_new_data = json_encode($new_data);

        // === Insert ke tlog_plan ===
        $stmt_log = $conn->prepare("
            INSERT INTO tlog_plan (id_cycle, updated_by, action_type, old_data, new_data, created_at)
            VALUES (?, ?, 'UPDATE', ?, ?, NOW())
        ");
        $stmt_log->bind_param("isss", $old_detail['id_cycle'], $updated_by, $json_old_data, $json_new_data);
        $stmt_log->execute();

        $conn->commit();

        $_SESSION['green_notif'] = "Plan cycle berhasil diperbarui.";
        header("Location: /isubcont/pages/master-plan.php");
        exit;
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['red_notif'] = "Terjadi kesalahan: " . $e->getMessage();
        header("Location: /isubcont/pages/master-plan.php");
        exit;
    }
}

if (isset($_POST['submit-time'])) {
    $updated_by = $_POST['updated_by'];
    $start_date = $_POST['start_date'];
    $end_date   = $_POST['end_date'];
    $hours      = $_POST['hour'];       // array
    $start_hour = $_POST['start_hour']; // array
    $end_hour   = $_POST['end_hour'];   // array

    // === VALIDASI SEDERHANA: semua array sama panjang ===
    if (count($hours) !== count($start_hour) || count($hours) !== count($end_hour)) {
        $_SESSION['red_notif'] = "Jumlah row tidak konsisten.";
        header("Location: /isubcont/pages/master-time.php");
        exit;
    }

    $conn->begin_transaction();

    try {
        $stmt_insert = $conn->prepare("
            INSERT INTO tbl_time (date_plan, hour, start_hour, end_hour, status, updated_by, updated_at)
            VALUES (?, ?, ?, ?, 1, ?, NOW())
        ");

        $period = new DatePeriod(
            new DateTime($start_date),
            new DateInterval('P1D'),
            (new DateTime($end_date))->modify('+1 day') // include end_date
        );

        $new_data = ['start_date' => $start_date, 'end_date' => $end_date, 'details' => []];

        foreach ($period as $date) {
            $date_plan = $date->format("Y-m-d");
            for ($i = 0; $i < count($hours); $i++) {
                $stmt_insert->bind_param(
                    "sisss",
                    $date_plan,
                    $hours[$i],
                    $start_hour[$i],
                    $end_hour[$i],
                    $updated_by
                );
                $stmt_insert->execute();
                $id_time = $conn->insert_id;

                $new_data['details'][] = [
                    'id_time'     => $id_time,
                    'date_plan'   => $date_plan,
                    'hour'        => $hours[$i],
                    'start_hour'  => $start_hour[$i],
                    'end_hour'    => $end_hour[$i]
                ];
            }
        }

        // === Logging ke tlog_time ===
        $json_new_data = json_encode($new_data);

        $stmt_log = $conn->prepare("
            INSERT INTO tlog_time (id_time, updated_by, action_type, old_data, new_data, created_at, updated_at)
            VALUES (?, ?, 'INSERT', NULL, ?, NOW(), NOW())
        ");

        // Untuk logging, pakai id_time dari insert terakhir saja (bisa sesuaikan kalau mau log per row)
        $stmt_log->bind_param("iss", $id_time, $updated_by, $json_new_data);
        $stmt_log->execute();

        $conn->commit();

        $_SESSION['green_notif'] = "Time plan berhasil ditambahkan.";
        header("Location: /isubcont/pages/master-time.php");
        exit;
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['red_notif'] = "Terjadi kesalahan: " . $e->getMessage();
        header("Location: /isubcont/pages/master-time.php");
        exit;
    }
}

if (isset($_POST['toggle_time_status'])) {
    $id_time = $_POST['id_time'];
    $status  = isset($_POST['status']) ? 1 : 0;
    $updated_by = $_SESSION['username'];

    $conn->begin_transaction();
    try {
        // 1. Ambil data lama
        $sql_old = "SELECT * FROM tbl_time WHERE id_time = ?";
        $stmt_old = $conn->prepare($sql_old);
        $stmt_old->bind_param("i", $id_time);
        $stmt_old->execute();
        $old_data = $stmt_old->get_result()->fetch_assoc();

        if (!$old_data) {
            $_SESSION['red_notif'] = "Data time tidak ditemukan.";
            header("Location: /isubcont/pages/master-time.php");
            exit;
        }

        // 2. Update status + updated_by + updated_at
        $stmt = $conn->prepare("
            UPDATE tbl_time
            SET status = ?, updated_by = ?, updated_at = NOW()
            WHERE id_time = ?
        ");
        $stmt->bind_param("isi", $status, $updated_by, $id_time);
        $stmt->execute();

        // 3. Logging ke tlog_time
        $new_data = $old_data;
        $new_data['status'] = $status;
        $new_data['updated_by'] = $updated_by;
        $new_data['updated_at'] = date('Y-m-d H:i:s');

        $json_old = json_encode($old_data);
        $json_new = json_encode($new_data);

        $stmt_log = $conn->prepare("
            INSERT INTO tlog_time (id_time, updated_by, action_type, old_data, new_data, created_at, updated_at)
            VALUES (?, ?, 'TOGGLE', ?, ?, NOW(), NOW())
        ");
        $stmt_log->bind_param("isss", $id_time, $updated_by, $json_old, $json_new);
        $stmt_log->execute();

        // 4. Commit
        $conn->commit();

        $_SESSION['green_notif'] = "Status time berhasil diubah.";
        header("Location: /isubcont/pages/master-time.php");
        exit;
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['red_notif'] = "Gagal mengubah status: " . $e->getMessage();
        header("Location: /isubcont/pages/master-time.php");
        exit;
    }
}

if (isset($_POST['update-time'])) {
    $id_time     = $_POST['id_time'];      // primary key tbl_time
    $updated_by  = $_POST['updated_by'];
    $start_hour  = $_POST['start_hour'];   // format HH:MM
    $end_hour    = $_POST['end_hour'];     // format HH:MM

    $conn->begin_transaction();

    try {
        // === Ambil data lama untuk logging ===
        $stmt_old = $conn->prepare("
            SELECT start_hour, end_hour 
            FROM tbl_time 
            WHERE id_time = ?
        ");
        $stmt_old->bind_param("i", $id_time);
        $stmt_old->execute();
        $old_detail = $stmt_old->get_result()->fetch_assoc();

        if (!$old_detail) {
            throw new Exception("Data time tidak ditemukan.");
        }

        $old_data = [
            'start_hour' => $old_detail['start_hour'],
            'end_hour'   => $old_detail['end_hour']
        ];
        $json_old_data = json_encode($old_data);

        // === Update tbl_time ===
        $stmt_update = $conn->prepare("
            UPDATE tbl_time 
            SET start_hour = ?, end_hour = ?, updated_by = ?, updated_at = NOW()
            WHERE id_time = ?
        ");
        $stmt_update->bind_param("ssii", $start_hour, $end_hour, $updated_by, $id_time);
        $stmt_update->execute();

        // === Ambil data baru untuk logging ===
        $new_data = [
            'start_hour' => $start_hour,
            'end_hour'   => $end_hour
        ];
        $json_new_data = json_encode($new_data);

        // === Insert ke tlog_time ===
        $stmt_log = $conn->prepare("
            INSERT INTO tlog_time (id_time, updated_by, action_type, old_data, new_data, created_at, updated_at)
            VALUES (?, ?, 'UPDATE', ?, ?, NOW(), NOW())
        ");
        $stmt_log->bind_param("isss", $id_time, $updated_by, $json_old_data, $json_new_data);
        $stmt_log->execute();

        $conn->commit();

        $_SESSION['green_notif'] = "Time berhasil diperbarui.";
        header("Location: /isubcont/pages/master-time.php");
        exit;
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['red_notif'] = "Terjadi kesalahan: " . $e->getMessage();
        header("Location: /isubcont/pages/master-time.php");
        exit;
    }
}

if (isset($_POST['action']) && $_POST['action'] == 'create-barcode') {

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    mysqli_begin_transaction($conn);

    $data = json_decode($_POST['data'], true);

    if (!$data) {
        echo json_encode(['status' => 'error', 'message' => 'DATA KOSONG']);
        exit;
    }

    $global    = $data['global'];
    $detail    = $data['detail'];     // lot → size → qty
    $komponen  = $data['komponen'];  // list komponen

    $date = date('Ymd');
    $ncvs = $global['ncvs'];
    $transac_by = $_SESSION['username'] ?? 'unknown';

    $success = 0;
    $failed = 0;
    $failed_detail = [];
    $log_detail = [];

    // GENERATE BATCH
    $qBatch = mysqli_query($conn, "
        SELECT batch_transaksi 
        FROM tbl_transaksi
        WHERE ncvs = '$ncvs'
        AND DATE(created_at) = CURDATE()
        ORDER BY id_trans DESC
        LIMIT 1
    ");

    $batch_increment = 1;

    if ($row = mysqli_fetch_assoc($qBatch)) {

        // ambil 3 digit terakhir batch
        $lastIncrement = (int) substr($row['batch_transaksi'], -3);

        // increment
        $batch_increment = $lastIncrement + 1;
    }

    $batch_format = str_pad($batch_increment, 3, '0', STR_PAD_LEFT);
    $batch_transaksi = "B-{$ncvs}{$date}{$batch_format}";

    // GENERATE BARCODE START
    $qBarcode = mysqli_query($conn, "
            SELECT barcode 
            FROM tbl_transaksi
            WHERE ncvs = '$ncvs'
            AND DATE(created_at) = CURDATE()
            ORDER BY id_trans DESC
            LIMIT 1
        ");

    $increment = 1;

    if ($row = mysqli_fetch_assoc($qBarcode)) {

        // ambil 3 digit terakhir barcode
        $lastIncrement = (int) substr($row['barcode'], -3);

        // next increment
        $increment = $lastIncrement + 1;
    }

    // LOOP CORE 
    foreach ($detail as $lot => $sizes) {

        foreach ($komponen as $group) {

            foreach ($sizes as $size => $qty) {

                /* ===================================== */
                /* CHECK DUPLICATE PER GROUP */
                /* ===================================== */

                $check = mysqli_query($conn, "
                SELECT id_trans 
                FROM tbl_transaksi
                WHERE 
                    status_lot = '{$global['lot_code']}'
                    AND job_order = '{$global['job_order']}'
                    AND lot = '$lot'
                    AND size = '$size'
                    AND id_group = '{$group['id_group']}'
                    AND id_komponen_out = '{$group['id_output']}'
                LIMIT 1
            ");

                if (mysqli_num_rows($check) > 0) {

                    $failed++;

                    $failed_detail[] = [
                        'lot' => $lot,
                        'size' => $size,
                        'group' => $group['nm_group'],
                        'reason' => 'duplicate group'
                    ];

                    continue;
                }

                /* ===================================== */
                /* GENERATE BARCODE PER GROUP × LOT × SIZE */
                /* ===================================== */

                $barcode_format =
                    str_pad(
                        $increment,
                        3,
                        '0',
                        STR_PAD_LEFT
                    );

                $barcode =
                    "{$ncvs}{$date}{$barcode_format}";

                $increment++;

                /* ===================================== */
                /* DATA GROUP */
                /* ===================================== */

                $id_group =
                    $group['id_group'];

                $id_output =
                    $group['id_output'];

                $nm_output =
                    $group['nm_output'];

                $id_vendor =
                    !empty($group['id_vendor'])
                    ? "'{$group['id_vendor']}'"
                    : "NULL";

                $nm_vendor =
                    !empty($group['nm_vendor'])
                    ? "'{$group['nm_vendor']}'"
                    : "NULL";

                $id_input_list =
                    $group['id_input_list'] ?? [];

                $nm_input_list =
                    $group['nm_input_list'] ?? [];

                $is_main_list =
                    $group['is_main_list'] ?? [];

                if (
                    empty($id_group) ||
                    empty($id_output) ||
                    empty($id_input_list)
                ) {

                    $failed++;

                    $failed_detail[] = [
                        'lot' => $lot,
                        'size' => $size,
                        'group' => $group['nm_group'] ?? '-',
                        'reason' => 'data group tidak lengkap'
                    ];

                    continue;
                }

                /* ===================================== */
                /* INSERT PER KOMPONEN DALAM GROUP */
                /* ===================================== */

                foreach ($id_input_list as $idx => $id_input) {

                    $nm_input =
                        $nm_input_list[$idx] ?? '';

                    $is_main =
                        isset($is_main_list[$idx])
                        ? (int)$is_main_list[$idx]
                        : 0;

                    if (
                        empty($id_input) ||
                        empty($nm_input)
                    ) {

                        $failed++;

                        $failed_detail[] = [
                            'lot' => $lot,
                            'size' => $size,
                            'group' => $group['nm_group'] ?? '-',
                            'komponen' => $nm_input,
                            'reason' => 'komponen dalam group tidak valid'
                        ];

                        continue;
                    }

                    /* ===================================== */
                    /* INSERT TRANSAKSI */
                    /* ===================================== */

                    $insert = mysqli_query($conn, "
                    INSERT INTO tbl_transaksi SET
                    transac_by = '$transac_by',
                    status_lot = '{$global['lot_code']}',
                    job_order  = '{$global['job_order']}',
                    bucket     = '{$global['bucket']}',
                    ncvs       = '{$global['ncvs']}',
                    po_code    = '{$global['po_code']}',
                    po_item    = '{$global['po_item']}',
                    model      = '{$global['model']}',
                    style      = '{$global['style']}',
                    lot  = '$lot',
                    size = '$size',

                    id_group = '$id_group',

                    id_komponen_in  = '$id_input',
                    nm_komponen_in  = '$nm_input',
                    id_komponen_out = '$id_output',
                    nm_komponen_out = '$nm_output',
                    id_vendor = $id_vendor,
                    nm_vendor = $nm_vendor,
                    is_main_komponen = '$is_main',
                    qty_plan = '$qty',
                    qty_cut_to_smsubcont = '$qty',
                    last_gate = 'CUT_TO_SM_SUBCONT',
                    status = 'PENDING',
                    batch_transaksi = '$batch_transaksi',
                    barcode = '$barcode',
                    created_at = NOW()
                ");

                    if (!$insert) {

                        $failed++;

                        $failed_detail[] = [
                            'lot' => $lot,
                            'size' => $size,
                            'group' => $group['nm_group'] ?? '-',
                            'komponen' => $nm_input,
                            'reason' => mysqli_error($conn)
                        ];

                        continue;
                    }

                    $success++;

                    $id_trans =
                        mysqli_insert_id($conn);

                    /* ===================================== */
                    /* INSERT EVENT */
                    /* ===================================== */

                    $event = mysqli_query($conn, "
                    INSERT INTO tbl_transaksi_event SET
                        id_trans = '$id_trans',
                        batch_transaksi = '$batch_transaksi',
                        barcode = '$barcode',
                        id_komponen = '$id_input',
                        nm_komponen = '$nm_input',
                        id_group = '$id_group',
                        lot = '$lot',
                        size = '$size',
                        gate = 'CUT_TO_SM_SUBCONT',
                        flow_type = 'OUT',
                        qty = '$qty',
                        qty_before = '$qty',
                        qty_after = NULL,
                        transac_by = '$transac_by',
                        created_at = NOW()
                ");

                    if (!$event) {

                        $failed++;

                        $failed_detail[] = [
                            'lot' => $lot,
                            'size' => $size,
                            'group' => $group['nm_group'] ?? '-',
                            'komponen' => $nm_input,
                            'reason' => mysqli_error($conn)
                        ];

                        continue;
                    }

                    /* ===================================== */
                    /* LOG DETAIL */
                    /* ===================================== */

                    $log_detail[] = [
                        'job_order' => $global['job_order'],
                        'lot' => $lot,
                        'size' => $size,
                        'id_group' => $id_group,
                        'group' => $group['nm_group'] ?? '-',
                        'komponen' => $nm_input,
                        'qty' => $qty,
                        'barcode' => $barcode,
                        'is_main' => $is_main
                    ];
                }
            }
        }
    }

    // INSERT TLOG
    $new_data = json_encode([
        'batch' => $batch_transaksi,
        'ncvs' => $ncvs,
        'total_success' => $success,
        'total_failed' => $failed,
        'detail' => $log_detail
    ]);

    $logInsert = mysqli_query($conn, "
        INSERT INTO tlog_transaksi SET
            updated_by = '$transac_by',
            action_type = 'CUT_TO_SM_SUBCONT',
            old_data = NULL,
            new_data = '" . mysqli_real_escape_string($conn, $new_data) . "',
            created_at = NOW(),
            updated_at = NOW()
    ");

    if (!$logInsert) {
        mysqli_rollback($conn);
        echo json_encode([
            'status' => 'error',
            'message' => 'Gagal insert log'
        ]);
        exit;
    }

    // COMMIT
    mysqli_commit($conn);

    echo json_encode([
        'status' => 'done',
        'success' => $success,
        'failed' => $failed,
        'failed_detail' => $failed_detail
    ]);
}

// === Approve / Reject Transaksi FROM LEAD ===
if (isset($_POST['action-transaksi'])) {
    $id_trans   = $_POST['id_trans'] ?? null;
    $status     = $_POST['status'] ?? null; // "APPROVED" / "REJECTED"
    $validated_by = $_SESSION['username'] ?? 'unknown';

    if ($id_trans && in_array($status, ['APPROVED', 'REJECTED'])) {
        $conn->begin_transaction();
        try {
            // Ambil data lama untuk log
            $stmt_old = $conn->prepare("SELECT * FROM tbl_transaksi WHERE id_trans=?");
            $stmt_old->bind_param("i", $id_trans);
            $stmt_old->execute();
            $res_old = $stmt_old->get_result();
            $old_data = $res_old->fetch_assoc();
            $json_old_data = $old_data ? json_encode($old_data) : null;

            // Update status + validasi
            $stmt_upd = $conn->prepare("
                UPDATE tbl_transaksi
                SET status=?, validated_by=?, validated_at=NOW()
                WHERE id_trans=?
            ");
            $stmt_upd->bind_param("ssi", $status, $validated_by, $id_trans);
            $stmt_upd->execute();

            // Ambil data baru untuk log
            $stmt_new = $conn->prepare("SELECT * FROM tbl_transaksi WHERE id_trans=?");
            $stmt_new->bind_param("i", $id_trans);
            $stmt_new->execute();
            $res_new = $stmt_new->get_result();
            $new_data = $res_new->fetch_assoc();
            $json_new_data = $new_data ? json_encode($new_data) : null;

            // Logging
            $stmt_log = $conn->prepare("
                INSERT INTO tlog_transaksi 
                (id_trans, updated_by, action_type, old_data, new_data, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, NOW(), NOW())
            ");
            $action_type = strtoupper($status) === 'APPROVED' ? 'APPROVE' : 'REJECT';
            $stmt_log->bind_param("issss", $id_trans, $validated_by, $action_type, $json_old_data, $json_new_data);
            $stmt_log->execute();

            $conn->commit();

            $_SESSION['green_notif'] = "Transaksi #$id_trans berhasil di-$status";
            header("Location: /isubcont/pages/approval-lead.php");
            exit;
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['red_notif'] = "Gagal update transaksi: " . $e->getMessage();
            header("Location: /isubcont/pages/approval-lead.php");
            exit;
        }
    } else {
        $_SESSION['red_notif'] = "Data tidak valid untuk approve/reject.";
        header("Location: /isubcont/pages/approval-lead.php");
        exit;
    }
}

// SCAN IN SM SUBCONT FROM CUTTING
if (isset($_POST['action']) && $_POST['action'] == 'scan_sm_subcont_from_cut') {

    require_once 'helper_gate.php';

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $conn->begin_transaction();

    $barcode = trim($_POST['barcode'] ?? '');
    $scan_by = $_SESSION['username'] ?? 'unknown';

    if (!$barcode) {

        $_SESSION['red_notif'] = "Barcode tidak boleh kosong.";

        header("Location: /isubcont/pages/trans-scan-in-smsubcont.php");
        exit;
    }

    try {

        // AMBIL SEMUA DATA BARCODE
        $q = mysqli_query($conn, "
            SELECT * 
            FROM tbl_transaksi 
            WHERE barcode = '$barcode'
            ORDER BY id_trans ASC
        ");

        if (mysqli_num_rows($q) == 0) {
            throw new Exception("Barcode tidak ditemukan");
        }

        // VALIDASI SEMUA ROW
        $all_data = [];

        while ($data = mysqli_fetch_assoc($q)) {

            $all_data[] = $data;

            // VALIDASI MERGE BARCODE
            if (!empty($data['parent_barcode'])) {

                throw new Exception("
            Barcode ini merupakan bagian dari merge barcode.
            Silahkan gunakan barcode utama untuk proses selanjutnya.
            ");
            }

            // VALIDASI DUPLICATE SCAN
            $check = mysqli_query($conn, "
                SELECT id_event 
                FROM tbl_transaksi_event
                WHERE barcode = '{$data['barcode']}'
                AND gate = 'SM_SUBCONT_FROM_CUT'
                LIMIT 1
            ");

            if (mysqli_num_rows($check) > 0) {

                throw new Exception("
            Barcode sudah pernah di-scan pada proses ini sebelumnya.
            ");
            }

            // VALIDASI URUTAN GATE
            $expected_last_gate = 'CUT_TO_SM_SUBCONT';

            if ($data['last_gate'] !== $expected_last_gate) {

                $current_gate = $data['last_gate'];

                $current_label = $gate_label[$current_gate] ?? $current_gate;

                $next_gate = $next_gate_map[$current_gate] ?? null;

                $next_label = $next_gate
                    ? ($gate_label[$next_gate] ?? $next_gate)
                    : 'Unknown';

                throw new Exception("
            Barcode tidak sesuai untuk proses ini.
            Posisi terakhir:
            {$current_label}
            Silahkan lanjut scan di:
            {$next_label}
            ");
            }
        }

        // PROSES SEMUA DATA
        foreach ($all_data as $data) {

            // PREPARE OLD DATA UNTUK LOG
            $old_data = $data;

            // UPDATE TRANSAKSI
            $update = mysqli_query($conn, "
                UPDATE tbl_transaksi SET
                    last_gate = 'SM_SUBCONT_FROM_CUT',
                    qty_smsubcont_fr_cut = qty_cut_to_smsubcont,
                    updated_at = NOW()
                WHERE id_trans = '{$data['id_trans']}'
            ");

            if (!$update) {
                throw new Exception(mysqli_error($conn));
            }

            // PREPARE NEW DATA UNTUK LOG
            $new_data = $old_data;

            $new_data['last_gate'] = 'SM_SUBCONT_FROM_CUT';

            $new_data['qty_smsubcont_fr_cut'] =
                $old_data['qty_cut_to_smsubcont'];

            $new_data['updated_at'] = date('Y-m-d H:i:s');

            // INSERT EVENT
            $event = mysqli_query($conn, "
                INSERT INTO tbl_transaksi_event SET
                    id_trans = '{$data['id_trans']}',
                    barcode = '{$data['barcode']}',
                    batch_transaksi = '{$data['batch_transaksi']}',
                    id_komponen = '{$data['id_komponen_in']}',
                    nm_komponen = '{$data['nm_komponen_in']}',
                    id_group = '{$data['id_group']}',
                    lot = '{$data['lot']}',
                    size = '{$data['size']}',
                    gate = 'SM_SUBCONT_FROM_CUT',
                    flow_type = 'IN',
                    qty = '{$data['qty_cut_to_smsubcont']}',
                    qty_before = '{$data['qty_cut_to_smsubcont']}',
                    qty_after = NULL,
                    transac_by = '$scan_by',
                    created_at = NOW()
            ");

            if (!$event) {
                throw new Exception(mysqli_error($conn));
            }

            //INSERT LOG
            $old_json = mysqli_real_escape_string(
                $conn,
                json_encode($old_data, JSON_UNESCAPED_UNICODE)
            );

            $new_json = mysqli_real_escape_string(
                $conn,
                json_encode($new_data, JSON_UNESCAPED_UNICODE)
            );

            $log = mysqli_query($conn, "
                INSERT INTO tlog_transaksi SET
                    updated_by = '$scan_by',
                    action_type = 'SM_SUBCONT_FROM_CUT',
                    old_data = '$old_json',
                    new_data = '$new_json',
                    created_at = NOW(),
                    updated_at = NOW()
            ");

            if (!$log) {
                throw new Exception(mysqli_error($conn));
            }
        }

        // COMMIT PROSES
        mysqli_commit($conn);

        // NOTIFIKASI
        $_SESSION['green_notif'] =
            "Transaksi berhasil, barcode $barcode telah di-scan.";


        header("Location: /isubcont/pages/trans-scan-in-smsubcont.php?success=$barcode");
        exit;
    } catch (Exception $e) {

        mysqli_rollback($conn);

        $_SESSION['red_notif'] = "Gagal: " . $e->getMessage();

        header("Location: /isubcont/pages/trans-scan-in-smsubcont.php");
        exit;
    }
}

// SCAN SM SUBCONT TO WAREHOUSE SUBCONT
if (isset($_POST['action']) && $_POST['action'] == 'scan_sm_subcont_to_wh_subcont') {

    require_once 'helper_gate.php';

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $conn->begin_transaction();

    $barcode = trim($_POST['barcode'] ?? '');
    $scan_by = $_SESSION['username'] ?? 'unknown';

    if (!$barcode) {

        $_SESSION['red_notif'] = "Barcode tidak boleh kosong.";

        header("Location: /isubcont/pages/trans-scan-out-smsubcont.php");
        exit;
    }

    try {

        // AMBIL SEMUA DATA BARCODE
        $q = mysqli_query($conn, "
            SELECT * 
            FROM tbl_transaksi 
            WHERE barcode = '$barcode'
            ORDER BY id_trans ASC
        ");

        if (mysqli_num_rows($q) == 0) {
            throw new Exception("Barcode tidak ditemukan");
        }

        // VALIDASI SEMUA ROW
        $all_data = [];

        while ($data = mysqli_fetch_assoc($q)) {

            $all_data[] = $data;

            // VALIDASI MERGE BARCODE
            if (!empty($data['parent_barcode'])) {

                throw new Exception("
            Barcode ini merupakan bagian dari merge barcode.
            Silahkan gunakan barcode utama untuk proses selanjutnya.
            ");
            }

            // VALIDASI DUPLICATE SCAN
            $check = mysqli_query($conn, "
                SELECT id_event 
                FROM tbl_transaksi_event
                WHERE barcode = '{$data['barcode']}'
                AND gate = 'SM_SUBCONT_TO_WH_SUBCONT'
                LIMIT 1
            ");

            if (mysqli_num_rows($check) > 0) {

                throw new Exception("
            Barcode sudah pernah di-scan pada proses ini sebelumnya.
            ");
            }

            // VALIDASI URUTAN GATE
            $expected_last_gate = 'SM_SUBCONT_FROM_CUT';

            if ($data['last_gate'] !== $expected_last_gate) {

                $current_gate = $data['last_gate'];

                $current_label = $gate_label[$current_gate] ?? $current_gate;

                $next_gate = $next_gate_map[$current_gate] ?? null;

                $next_label = $next_gate
                    ? ($gate_label[$next_gate] ?? $next_gate)
                    : 'Unknown';

                throw new Exception("
            Barcode tidak sesuai untuk proses ini.
            Posisi terakhir:
            {$current_label}
            Silahkan lanjut scan di:
            {$next_label}
            ");
            }
        }

        // PROSES SEMUA DATA
        foreach ($all_data as $data) {

            // PREPARE OLD DATA UNTUK LOG
            $old_data = $data;

            // UPDATE TRANSAKSI
            $update = mysqli_query($conn, "
                UPDATE tbl_transaksi SET
                    last_gate = 'SM_SUBCONT_TO_WH_SUBCONT',
                    qty_smsubcont_to_whsubcont = qty_smsubcont_fr_cut,
                    updated_at = NOW()
                WHERE id_trans = '{$data['id_trans']}'
            ");

            if (!$update) {
                throw new Exception(mysqli_error($conn));
            }

            // PREPARE NEW DATA UNTUK LOG
            $new_data = $old_data;

            $new_data['last_gate'] = 'SM_SUBCONT_TO_WH_SUBCONT';

            $new_data['qty_smsubcont_to_whsubcont'] =
                $old_data['qty_smsubcont_fr_cut'];

            $new_data['updated_at'] = date('Y-m-d H:i:s');

            // INSERT EVENT
            $event = mysqli_query($conn, "
                INSERT INTO tbl_transaksi_event SET
                    id_trans = '{$data['id_trans']}',
                    barcode = '{$data['barcode']}',
                    batch_transaksi = '{$data['batch_transaksi']}',
                    id_komponen = '{$data['id_komponen_in']}',
                    nm_komponen = '{$data['nm_komponen_in']}',
                    id_group = '{$data['id_group']}',
                    lot = '{$data['lot']}',
                    size = '{$data['size']}',
                    gate = 'SM_SUBCONT_TO_WH_SUBCONT',
                    flow_type = 'OUT',
                    qty = '{$data['qty_smsubcont_fr_cut']}',
                    qty_before = '{$data['qty_smsubcont_fr_cut']}',
                    qty_after = NULL,
                    transac_by = '$scan_by',
                    created_at = NOW()
            ");

            if (!$event) {
                throw new Exception(mysqli_error($conn));
            }

            //INSERT LOG
            $old_json = mysqli_real_escape_string(
                $conn,
                json_encode($old_data, JSON_UNESCAPED_UNICODE)
            );

            $new_json = mysqli_real_escape_string(
                $conn,
                json_encode($new_data, JSON_UNESCAPED_UNICODE)
            );

            $log = mysqli_query($conn, "
                INSERT INTO tlog_transaksi SET
                    updated_by = '$scan_by',
                    action_type = 'SM_SUBCONT_TO_WH_SUBCONT',
                    old_data = '$old_json',
                    new_data = '$new_json',
                    created_at = NOW(),
                    updated_at = NOW()
            ");

            if (!$log) {
                throw new Exception(mysqli_error($conn));
            }
        }

        // COMMIT PROSES
        mysqli_commit($conn);

        // NOTIFIKASI
        $_SESSION['green_notif'] =
            "Transaksi berhasil, barcode $barcode telah di-scan.";


        header("Location: /isubcont/pages/trans-scan-out-smsubcont.php?success=$barcode");
        exit;
    } catch (Exception $e) {

        mysqli_rollback($conn);

        $_SESSION['red_notif'] = "Gagal: " . $e->getMessage();

        header("Location: /isubcont/pages/trans-scan-out-smsubcont.php");
        exit;
    }
}

// SCAN IN WAREHOUSE SUBCONT FROM SM SUBCONT
if (isset($_POST['action']) && $_POST['action'] == 'scan_in_wh_subcont') {

    require_once 'helper_gate.php';

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $conn->begin_transaction();

    $barcode = trim($_POST['barcode'] ?? '');
    $scan_by = $_SESSION['username'] ?? 'unknown';

    if (!$barcode) {

        $_SESSION['red_notif'] = "Barcode tidak boleh kosong.";

        header("Location: /isubcont/pages/trans-scan-in-wh.php");
        exit;
    }

    try {

        // AMBIL SEMUA DATA BARCODE
        $q = mysqli_query($conn, "
            SELECT * 
            FROM tbl_transaksi 
            WHERE barcode = '$barcode'
            ORDER BY id_trans ASC
        ");

        if (mysqli_num_rows($q) == 0) {
            throw new Exception("Barcode tidak ditemukan");
        }

        // VALIDASI SEMUA ROW
        $all_data = [];

        while ($data = mysqli_fetch_assoc($q)) {

            $all_data[] = $data;

            // VALIDASI MERGE BARCODE
            if (!empty($data['parent_barcode'])) {

                throw new Exception("
            Barcode ini merupakan bagian dari merge barcode.
            Silahkan gunakan barcode utama untuk proses selanjutnya.
            ");
            }

            // VALIDASI DUPLICATE SCAN
            $check = mysqli_query($conn, "
                SELECT id_event 
                FROM tbl_transaksi_event
                WHERE barcode = '{$data['barcode']}'
                AND gate = 'WH_SUBCONT_FROM_SM_SUBCONT'
                LIMIT 1
            ");

            if (mysqli_num_rows($check) > 0) {

                throw new Exception("
            Barcode sudah pernah di-scan pada proses ini sebelumnya.
            ");
            }

            // VALIDASI URUTAN GATE
            $expected_last_gate = 'SM_SUBCONT_TO_WH_SUBCONT';

            if ($data['last_gate'] !== $expected_last_gate) {

                $current_gate = $data['last_gate'];

                $current_label = $gate_label[$current_gate] ?? $current_gate;

                $next_gate = $next_gate_map[$current_gate] ?? null;

                $next_label = $next_gate
                    ? ($gate_label[$next_gate] ?? $next_gate)
                    : 'Unknown';

                throw new Exception("
            Barcode tidak sesuai untuk proses ini.
            Posisi terakhir:
            {$current_label}
            Silahkan lanjut scan di:
            {$next_label}
            ");
            }
        }

        // PROSES SEMUA DATA
        foreach ($all_data as $data) {

            // PREPARE OLD DATA UNTUK LOG
            $old_data = $data;

            // UPDATE TRANSAKSI
            $update = mysqli_query($conn, "
                UPDATE tbl_transaksi SET
                    last_gate = 'WH_SUBCONT_FROM_SM_SUBCONT',
                    qty_whsubcont_fr_smsubcont = qty_smsubcont_to_whsubcont,
                    updated_at = NOW()
                WHERE id_trans = '{$data['id_trans']}'
            ");

            if (!$update) {
                throw new Exception(mysqli_error($conn));
            }

            // PREPARE NEW DATA UNTUK LOG
            $new_data = $old_data;

            $new_data['last_gate'] = 'WH_SUBCONT_FROM_SM_SUBCONT';

            $new_data['qty_whsubcont_fr_smsubcont'] =
                $old_data['qty_smsubcont_to_whsubcont'];

            $new_data['updated_at'] = date('Y-m-d H:i:s');

            // INSERT EVENT
            $event = mysqli_query($conn, "
                INSERT INTO tbl_transaksi_event SET
                    id_trans = '{$data['id_trans']}',
                    barcode = '{$data['barcode']}',
                    batch_transaksi = '{$data['batch_transaksi']}',
                    id_komponen = '{$data['id_komponen_in']}',
                    nm_komponen = '{$data['nm_komponen_in']}',
                    id_group = '{$data['id_group']}',
                    lot = '{$data['lot']}',
                    size = '{$data['size']}',
                    gate = 'WH_SUBCONT_FROM_SM_SUBCONT',
                    flow_type = 'IN',
                    qty = '{$data['qty_smsubcont_to_whsubcont']}',
                    qty_before = '{$data['qty_smsubcont_to_whsubcont']}',
                    qty_after = NULL,
                    transac_by = '$scan_by',
                    created_at = NOW()
            ");

            if (!$event) {
                throw new Exception(mysqli_error($conn));
            }

            //INSERT LOG
            $old_json = mysqli_real_escape_string(
                $conn,
                json_encode($old_data, JSON_UNESCAPED_UNICODE)
            );

            $new_json = mysqli_real_escape_string(
                $conn,
                json_encode($new_data, JSON_UNESCAPED_UNICODE)
            );

            $log = mysqli_query($conn, "
                INSERT INTO tlog_transaksi SET
                    updated_by = '$scan_by',
                    action_type = 'WH_SUBCONT_FROM_SM_SUBCONT',
                    old_data = '$old_json',
                    new_data = '$new_json',
                    created_at = NOW(),
                    updated_at = NOW()
            ");

            if (!$log) {
                throw new Exception(mysqli_error($conn));
            }
        }

        // COMMIT PROSES
        mysqli_commit($conn);

        // NOTIFIKASI
        $_SESSION['green_notif'] =
            "Transaksi berhasil, barcode $barcode telah di-scan.";


        header("Location: /isubcont/pages/trans-scan-in-wh.php?success=$barcode");
        exit;
    } catch (Exception $e) {

        mysqli_rollback($conn);

        $_SESSION['red_notif'] = "Gagal: " . $e->getMessage();

        header("Location: /isubcont/pages/trans-scan-in-wh.php");
        exit;
    }
}

// SCAN OUT WAREHOUSE SUBCONT TO VENDOR
if (isset($_POST['action']) && $_POST['action'] == 'scan_out_to_vendor') {

    require_once 'helper_gate.php';

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $conn->begin_transaction();

    $barcode = trim($_POST['barcode'] ?? '');
    $scan_by = $_SESSION['username'] ?? 'unknown';

    if (!$barcode) {

        $_SESSION['red_notif'] = "Barcode tidak boleh kosong.";

        header("Location: /isubcont/pages/trans-scan-out-to-vendor.php");
        exit;
    }

    try {

        // AMBIL SEMUA DATA BARCODE
        $q = mysqli_query($conn, "
            SELECT * 
            FROM tbl_transaksi 
            WHERE barcode = '$barcode'
            ORDER BY id_trans ASC
        ");

        if (mysqli_num_rows($q) == 0) {
            throw new Exception("Barcode tidak ditemukan");
        }

        // VALIDASI SEMUA ROW
        $all_data = [];

        while ($data = mysqli_fetch_assoc($q)) {

            $all_data[] = $data;

            // VALIDASI MERGE BARCODE
            if (!empty($data['parent_barcode'])) {

                throw new Exception("
            Barcode ini merupakan bagian dari merge barcode.
            Silahkan gunakan barcode utama untuk proses selanjutnya.
            ");
            }

            // VALIDASI DUPLICATE SCAN
            $check = mysqli_query($conn, "
                SELECT id_event 
                FROM tbl_transaksi_event
                WHERE barcode = '{$data['barcode']}'
                AND gate = 'WH_SUBCONT_TO_VENDOR'
                LIMIT 1
            ");

            if (mysqli_num_rows($check) > 0) {

                throw new Exception("
            Barcode sudah pernah di-scan pada proses ini sebelumnya.
            ");
            }

            // VALIDASI URUTAN GATE
            $expected_last_gate = 'WH_SUBCONT_FROM_SM_SUBCONT';

            if ($data['last_gate'] !== $expected_last_gate) {

                $current_gate = $data['last_gate'];

                $current_label = $gate_label[$current_gate] ?? $current_gate;

                $next_gate = $next_gate_map[$current_gate] ?? null;

                $next_label = $next_gate
                    ? ($gate_label[$next_gate] ?? $next_gate)
                    : 'Unknown';

                throw new Exception("
            Barcode tidak sesuai untuk proses ini.
            Posisi terakhir:
            {$current_label}
            Silahkan lanjut scan di:
            {$next_label}
            ");
            }
        }

        // PROSES SEMUA DATA
        foreach ($all_data as $data) {

            // PREPARE OLD DATA UNTUK LOG
            $old_data = $data;

            // UPDATE TRANSAKSI
            $update = mysqli_query($conn, "
                UPDATE tbl_transaksi SET
                    last_gate = 'WH_SUBCONT_TO_VENDOR',
                    qty_whsubcont_to_vendor = qty_whsubcont_fr_smsubcont,
                    updated_at = NOW()
                WHERE id_trans = '{$data['id_trans']}'
            ");

            if (!$update) {
                throw new Exception(mysqli_error($conn));
            }

            // PREPARE NEW DATA UNTUK LOG
            $new_data = $old_data;

            $new_data['last_gate'] = 'WH_SUBCONT_TO_VENDOR';

            $new_data['qty_whsubcont_to_vendor'] =
                $old_data['qty_whsubcont_fr_smsubcont'];

            $new_data['updated_at'] = date('Y-m-d H:i:s');

            // INSERT EVENT
            $event = mysqli_query($conn, "
                INSERT INTO tbl_transaksi_event SET
                    id_trans = '{$data['id_trans']}',
                    barcode = '{$data['barcode']}',
                    batch_transaksi = '{$data['batch_transaksi']}',
                    id_komponen = '{$data['id_komponen_in']}',
                    nm_komponen = '{$data['nm_komponen_in']}',
                    id_group = '{$data['id_group']}',
                    lot = '{$data['lot']}',
                    size = '{$data['size']}',
                    gate = 'WH_SUBCONT_TO_VENDOR',
                    flow_type = 'OUT',
                    qty = '{$data['qty_whsubcont_fr_smsubcont']}',
                    qty_before = '{$data['qty_whsubcont_fr_smsubcont']}',
                    qty_after = NULL,
                    transac_by = '$scan_by',
                    created_at = NOW()
            ");

            if (!$event) {
                throw new Exception(mysqli_error($conn));
            }

            //INSERT LOG
            $old_json = mysqli_real_escape_string(
                $conn,
                json_encode($old_data, JSON_UNESCAPED_UNICODE)
            );

            $new_json = mysqli_real_escape_string(
                $conn,
                json_encode($new_data, JSON_UNESCAPED_UNICODE)
            );

            $log = mysqli_query($conn, "
                INSERT INTO tlog_transaksi SET
                    updated_by = '$scan_by',
                    action_type = 'WH_SUBCONT_TO_VENDOR',
                    old_data = '$old_json',
                    new_data = '$new_json',
                    created_at = NOW(),
                    updated_at = NOW()
            ");

            if (!$log) {
                throw new Exception(mysqli_error($conn));
            }
        }

        // COMMIT PROSES
        mysqli_commit($conn);

        // NOTIFIKASI
        $_SESSION['green_notif'] =
            "Transaksi berhasil, barcode $barcode telah di-scan.";


        header("Location: /isubcont/pages/trans-scan-out-to-vendor.php?success=$barcode");
        exit;
    } catch (Exception $e) {

        mysqli_rollback($conn);

        $_SESSION['red_notif'] = "Gagal: " . $e->getMessage();

        header("Location: /isubcont/pages/trans-scan-out-to-vendor.php");
        exit;
    }
}

// SCAN IN VENDOR FROM WAREHOUSE SUBCONT
if (isset($_POST['action']) && $_POST['action'] == 'scan_in_vendor') {

    require_once 'helper_gate.php';

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $conn->begin_transaction();

    $barcode = trim($_POST['barcode'] ?? '');
    $scan_by = $_SESSION['username'] ?? 'unknown';

    if (!$barcode) {

        $_SESSION['red_notif'] = "Barcode tidak boleh kosong.";

        header("Location: /isubcont/pages/trans-scan-in-vendor.php");
        exit;
    }

    try {

        // AMBIL SEMUA DATA BARCODE
        $q = mysqli_query($conn, "
            SELECT * 
            FROM tbl_transaksi 
            WHERE barcode = '$barcode'
            ORDER BY id_trans ASC
        ");

        if (mysqli_num_rows($q) == 0) {
            throw new Exception("Barcode tidak ditemukan");
        }

        // VALIDASI SEMUA ROW
        $all_data = [];

        while ($data = mysqli_fetch_assoc($q)) {

            $all_data[] = $data;

            // VALIDASI MERGE BARCODE
            if (!empty($data['parent_barcode'])) {

                throw new Exception("
            Barcode ini merupakan bagian dari merge barcode.
            Silahkan gunakan barcode utama untuk proses selanjutnya.
            ");
            }

            // VALIDASI DUPLICATE SCAN
            $check = mysqli_query($conn, "
                SELECT id_event 
                FROM tbl_transaksi_event
                WHERE barcode = '{$data['barcode']}'
                AND gate = 'VENDOR_FROM_WH_SUBCONT'
                LIMIT 1
            ");

            if (mysqli_num_rows($check) > 0) {

                throw new Exception("
            Barcode sudah pernah di-scan pada proses ini sebelumnya.
            ");
            }

            // VALIDASI URUTAN GATE
            $expected_last_gate = 'WH_SUBCONT_TO_VENDOR';

            if ($data['last_gate'] !== $expected_last_gate) {

                $current_gate = $data['last_gate'];

                $current_label = $gate_label[$current_gate] ?? $current_gate;

                $next_gate = $next_gate_map[$current_gate] ?? null;

                $next_label = $next_gate
                    ? ($gate_label[$next_gate] ?? $next_gate)
                    : 'Unknown';

                throw new Exception("
            Barcode tidak sesuai untuk proses ini.
            Posisi terakhir:
            {$current_label}
            Silahkan lanjut scan di:
            {$next_label}
            ");
            }
        }

        // PROSES SEMUA DATA
        foreach ($all_data as $data) {

            // PREPARE OLD DATA UNTUK LOG
            $old_data = $data;

            // UPDATE TRANSAKSI
            $update = mysqli_query($conn, "
                UPDATE tbl_transaksi SET
                    last_gate = 'VENDOR_FROM_WH_SUBCONT',
                    qty_vendor_fr_whsubcont = qty_whsubcont_to_vendor,
                    updated_at = NOW()
                WHERE id_trans = '{$data['id_trans']}'
            ");

            if (!$update) {
                throw new Exception(mysqli_error($conn));
            }

            // PREPARE NEW DATA UNTUK LOG
            $new_data = $old_data;

            $new_data['last_gate'] = 'VENDOR_FROM_WH_SUBCONT';

            $new_data['qty_vendor_fr_whsubcont'] =
                $old_data['qty_whsubcont_to_vendor'];

            $new_data['updated_at'] = date('Y-m-d H:i:s');

            // INSERT EVENT
            $event = mysqli_query($conn, "
                INSERT INTO tbl_transaksi_event SET
                    id_trans = '{$data['id_trans']}',
                    barcode = '{$data['barcode']}',
                    batch_transaksi = '{$data['batch_transaksi']}',
                    id_komponen = '{$data['id_komponen_in']}',
                    nm_komponen = '{$data['nm_komponen_in']}',
                    id_group = '{$data['id_group']}',
                    lot = '{$data['lot']}',
                    size = '{$data['size']}',
                    gate = 'VENDOR_FROM_WH_SUBCONT',
                    flow_type = 'IN',
                    qty = '{$data['qty_whsubcont_to_vendor']}',
                    qty_before = '{$data['qty_whsubcont_to_vendor']}',
                    qty_after = NULL,
                    transac_by = '$scan_by',
                    created_at = NOW()
            ");

            if (!$event) {
                throw new Exception(mysqli_error($conn));
            }

            //INSERT LOG
            $old_json = mysqli_real_escape_string(
                $conn,
                json_encode($old_data, JSON_UNESCAPED_UNICODE)
            );

            $new_json = mysqli_real_escape_string(
                $conn,
                json_encode($new_data, JSON_UNESCAPED_UNICODE)
            );

            $log = mysqli_query($conn, "
                INSERT INTO tlog_transaksi SET
                    updated_by = '$scan_by',
                    action_type = 'VENDOR_FROM_WH_SUBCONT',
                    old_data = '$old_json',
                    new_data = '$new_json',
                    created_at = NOW(),
                    updated_at = NOW()
            ");

            if (!$log) {
                throw new Exception(mysqli_error($conn));
            }
        }

        // COMMIT PROSES
        mysqli_commit($conn);

        // NOTIFIKASI
        $_SESSION['green_notif'] =
            "Transaksi berhasil, barcode $barcode telah di-scan.";


        header("Location: /isubcont/pages/trans-scan-in-vendor.php?success=$barcode");
        exit;
    } catch (Exception $e) {

        mysqli_rollback($conn);

        $_SESSION['red_notif'] = "Gagal: " . $e->getMessage();

        header("Location: /isubcont/pages/trans-scan-in-vendor.php");
        exit;
    }
}

// SCAN OUT VENDOR TO WAREHOUSE SUBCONT
if (isset($_POST['action']) && $_POST['action'] == 'scan_vendor_to_whsubcont') {

    require_once 'helper_gate.php';

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $conn->begin_transaction();

    $barcode = trim($_POST['barcode'] ?? '');
    $scan_by = $_SESSION['username'] ?? 'unknown';

    if (!$barcode) {

        $_SESSION['red_notif'] = "Barcode tidak boleh kosong.";

        header("Location: /isubcont/pages/trans-scan-out-vendor.php");
        exit;
    }

    try {

        // AMBIL DATA BARCODE
        $q = mysqli_query($conn, "
            SELECT *
            FROM tbl_transaksi
            WHERE barcode = '$barcode'
            ORDER BY id_trans ASC
        ");

        if (mysqli_num_rows($q) == 0) {
            throw new Exception("Barcode tidak ditemukan");
        }

        $all_data = [];

        while ($d = mysqli_fetch_assoc($q)) {
            $all_data[] = $d;
        }

        $first = $all_data[0];

        // VALIDASI FLOW
        if ($first['last_gate'] !== 'VENDOR_FROM_WH_SUBCONT') {

            $current_gate = $first['last_gate'];

            $current_label = $gate_label[$current_gate] ?? $current_gate;

            $next_gate = $next_gate_map[$current_gate] ?? null;

            $next_label = $next_gate
                ? ($gate_label[$next_gate] ?? $next_gate)
                : 'Unknown';

            throw new Exception("
            Barcode tidak sesuai untuk proses ini.
            Posisi terakhir:
            {$current_label}

            Silahkan lanjut scan di:
            {$next_label}
            ");
        }

        // VALIDASI STATUS
        foreach ($all_data as $d) {

            if ($d['barcode_status'] == 'MERGED') {

                throw new Exception("
        Barcode ini sudah di-merge. Silahkan gunakan barcode utama.
        ");
            }
        }

        // VALIDASI DOUBLE SCAN
        $check = mysqli_query($conn, "
            SELECT id_event
            FROM tbl_transaksi_event
            WHERE barcode = '$barcode'
            AND gate = 'VENDOR_TO_WH_SUBCONT'
            LIMIT 1
        ");

        if (mysqli_num_rows($check) > 0) {

            throw new Exception("
        Barcode sudah pernah di-scan pada proses ini sebelumnya.
        ");
        }

        // CEK TOTAL KOMPONEN
        $total_pair = mysqli_query($conn, "
        SELECT COUNT(DISTINCT id_group) AS total
        FROM tbl_transaksi
        WHERE
            job_order = '{$first['job_order']}'
            AND lot = '{$first['lot']}'
            AND size = '{$first['size']}'
            AND nm_komponen_out = '{$first['nm_komponen_out']}'
        ");

        $total_pair_data = mysqli_fetch_assoc($total_pair);
        $total_required = (int)$total_pair_data['total'];

        // UPDATE SEMUA ROW BARCODE
        foreach ($all_data as $data) {

            // OLD DATA
            $old_data = json_encode([
                "id_trans" => $data['id_trans'],
                "barcode" => $data['barcode'],
                "last_gate" => $data['last_gate'],
                "barcode_status" => $data['barcode_status'],
                "parent_barcode" => $data['parent_barcode'],
                "qty_vendor_to_whsubcont" => $data['qty_vendor_to_whsubcont']
            ], JSON_UNESCAPED_UNICODE);

            // UPDATE TRANSAKSI
            mysqli_query($conn, "
                UPDATE tbl_transaksi SET
                    last_gate = 'VENDOR_TO_WH_SUBCONT',
                    qty_vendor_to_whsubcont = qty_vendor_fr_whsubcont,
                    updated_at = NOW()
                WHERE id_trans = '{$data['id_trans']}'
            ");

            // NEW DATA
            $new_data = json_encode([
                "id_trans" => $data['id_trans'],
                "barcode" => $data['barcode'],
                "last_gate" => 'VENDOR_TO_WH_SUBCONT',
                "barcode_status" => $data['barcode_status'],
                "parent_barcode" => $data['parent_barcode'],
                "qty_vendor_to_whsubcont" => $data['qty_vendor_fr_whsubcont']
            ], JSON_UNESCAPED_UNICODE);

            // INSERT TLOG
            mysqli_query($conn, "
                INSERT INTO tlog_transaksi SET
                    action_type = 'VENDOR_TO_WH_SUBCONT',
                    old_data = '" . mysqli_real_escape_string($conn, $old_data) . "',
                    new_data = '" . mysqli_real_escape_string($conn, $new_data) . "',
                    updated_by = '$scan_by',
                    created_at = NOW(),
                    updated_at = NOW()
            ");

            // INSERT EVENT
            mysqli_query($conn, "
                INSERT INTO tbl_transaksi_event SET
                    id_trans = '{$data['id_trans']}',
                    barcode = '{$data['barcode']}',
                    batch_transaksi = '{$data['batch_transaksi']}',
                    id_komponen = '{$data['id_komponen_in']}',
                    nm_komponen = '{$data['nm_komponen_in']}',
                    id_group = '{$data['id_group']}',
                    lot = '{$data['lot']}',
                    size = '{$data['size']}',
                    gate = 'VENDOR_TO_WH_SUBCONT',
                    flow_type = 'OUT',
                    qty = '{$data['qty_vendor_fr_whsubcont']}',
                    qty_before = '{$data['qty_vendor_fr_whsubcont']}',
                    qty_after = NULL,
                    transac_by = '$scan_by',
                    created_at = NOW()
            ");
        }

        // CEK SEMUA PASANGAN READY
        $ready_pair = mysqli_query($conn, "
        SELECT DISTINCT barcode
        FROM tbl_transaksi
        WHERE
            job_order = '{$first['job_order']}'
            AND lot = '{$first['lot']}'
            AND size = '{$first['size']}'
            AND nm_komponen_out = '{$first['nm_komponen_out']}'
            AND last_gate = 'VENDOR_TO_WH_SUBCONT'
            AND barcode_status = 'ACTIVE'
        ");

        $ready_barcodes = [];

        while ($r = mysqli_fetch_assoc($ready_pair)) {
            $ready_barcodes[] = $r['barcode'];
        }
        $ready_total = count($ready_barcodes);

        // BELUM LENGKAP
        if ($ready_total < $total_required) {

            mysqli_commit($conn);

            header(
                "Location: /isubcont/pages/trans-scan-out-vendor.php" .
                    "?status=partial" .
                    "&barcode=" . urlencode($barcode)
            );

            exit;
        }

        // CARI MAIN BARCODE
        $qMain = mysqli_query($conn, "
        SELECT barcode
        FROM tbl_transaksi
        WHERE
            job_order = '{$first['job_order']}'
            AND lot = '{$first['lot']}'
            AND size = '{$first['size']}'
            AND nm_komponen_out = '{$first['nm_komponen_out']}'
        ORDER BY barcode ASC
        LIMIT 1
        ");

        $main = mysqli_fetch_assoc($qMain);

        if (!$main) {
            throw new Exception("Main barcode tidak ditemukan");
        }
        $main_barcode = $main['barcode'];

        // LOOP READY BARCODE
        foreach ($ready_barcodes as $merge_barcode) {

            $qData = mysqli_query($conn, "
            SELECT *
            FROM tbl_transaksi
            WHERE
                barcode = '$merge_barcode'
                AND job_order = '{$first['job_order']}'
                AND lot = '{$first['lot']}'
                AND size = '{$first['size']}'
                AND nm_komponen_out = '{$first['nm_komponen_out']}'
            ");

            while ($data = mysqli_fetch_assoc($qData)) {

                $status =
                    ($merge_barcode == $main_barcode)
                    ? 'ACTIVE'
                    : 'MERGED';

                $parent =
                    ($merge_barcode == $main_barcode)
                    ? NULL
                    : $main_barcode;

                // OLD MERGE
                $old_merge = json_encode([
                    "barcode_status" => $data['barcode_status'],
                    "parent_barcode" => $data['parent_barcode']
                ], JSON_UNESCAPED_UNICODE);

                // UPDATE MERGE
                mysqli_query($conn, "
                    UPDATE tbl_transaksi SET
                        barcode_status = '$status',
                        parent_barcode =
                            " . ($parent ? "'$parent'" : "NULL") . "
                    WHERE id_trans = '{$data['id_trans']}'
                ");

                // NEW MERGE
                $new_merge = json_encode([
                    "barcode_status" => $status,
                    "parent_barcode" => $parent
                ], JSON_UNESCAPED_UNICODE);

                // INSERT TLOG MERGE
                mysqli_query($conn, "
                INSERT INTO tlog_transaksi SET
                    action_type = 'MERGE',
                    old_data = '" . mysqli_real_escape_string($conn, $old_merge) . "',
                    new_data = '" . mysqli_real_escape_string($conn, $new_merge) . "',
                    updated_by = '$scan_by',
                    created_at = NOW(),
                    updated_at = NOW()
            ");
            }

            // INSERT MERGE TABLE
            if ($merge_barcode != $main_barcode) {
                $check_merge = mysqli_query($conn, "
                SELECT id
                FROM tbl_barcode_merge
                WHERE
                    barcode_parent = '$main_barcode'
                    AND barcode_child = '$merge_barcode'
                LIMIT 1
            ");

                if (mysqli_num_rows($check_merge) == 0) {

                    mysqli_query($conn, "
                INSERT INTO tbl_barcode_merge SET
                barcode_parent = '$main_barcode',
                barcode_child = '$merge_barcode',
                job_order = '{$first['job_order']}',
                lot = '{$first['lot']}',
                created_at = NOW(),
                created_by = '$scan_by'
        ");
                }
            }
        }

        mysqli_commit($conn);

        header(
            "Location: /isubcont/pages/trans-scan-out-vendor.php" .
                "?status=complete" .
                "&barcode=" . urlencode($main_barcode)
        );

        exit;
    } catch (Exception $e) {
        mysqli_rollback($conn);

        $_SESSION['red_notif'] =
            "Gagal: " . $e->getMessage();

        header("Location: /isubcont/pages/trans-scan-out-vendor.php");
        exit;
    }
}

// SCAN IN WH SUBCONT FROM VENDOR
if (
    isset($_POST['action']) && $_POST['action'] == 'scan_whsubcont_from_vendor'
) {

    require_once 'helper_gate.php';

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    mysqli_begin_transaction($conn);

    $barcode = trim($_POST['barcode'] ?? '');
    $scan_by = $_SESSION['username'] ?? 'unknown';

    // VALIDASI BARCODE
    if (!$barcode) {

        $_SESSION['red_notif'] =
            "Barcode tidak boleh kosong.";

        header("Location: /isubcont/pages/trans-scan-in-incoming.php");
        exit;
    }

    try {

        // AMBIL DATA ACTIVE
        $q = mysqli_query($conn, "
        SELECT *
        FROM tbl_transaksi
        WHERE barcode = '$barcode'
        ORDER BY id_trans ASC
        ");

        if (mysqli_num_rows($q) == 0) {
            throw new Exception("Barcode tidak ditemukan.");
        }

        // SIMPAN SEMUA ROW
        $all_data = [];
        while ($d = mysqli_fetch_assoc($q)) {
            $all_data[] = $d;
        }

        $first = $all_data[0];

        // VALIDASI FLOW
        foreach ($all_data as $d) {

            if (
                $d['last_gate'] != 'WH_SUBCONT_TO_VENDOR'
            ) {

                $current_gate = $d['last_gate'];

                $current_label =
                    $gate_label[$current_gate] ?? $current_gate;

                $next_gate =
                    $next_gate_map[$current_gate] ?? null;

                $next_label = $next_gate
                    ? ($gate_label[$next_gate] ?? $next_gate)
                    : 'Unknown';

                throw new Exception("
                Barcode tidak sesuai untuk proses ini.
                Posisi terakhir:
                {$current_label}
                Silahkan lanjut scan di:
                {$next_label}
                ");
            }
        }

        // ANTI DOUBLE SCAN
        $check = mysqli_query($conn, "
            SELECT id_event
            FROM tbl_transaksi_event
            WHERE
                barcode = '$barcode'
                AND gate = 'WH_SUBCONT_FROM_VENDOR'
            LIMIT 1
        ");

        if (mysqli_num_rows($check) > 0) {

            throw new Exception("
            Barcode sudah pernah di-scan pada proses ini sebelumnya.
            ");
        }

        // LOOP UPDATE SEMUA SIZE
        foreach ($all_data as $data) {

            // OLD DATA
            $old_data = json_encode([
                "id_trans" => $data['id_trans'],
                "barcode" => $data['barcode'],
                "size" => $data['size'],
                "last_gate" => $data['last_gate'],
                "qty_whsubcont_fr_vendor" =>
                $data['qty_whsubcont_fr_vendor']

            ], JSON_UNESCAPED_UNICODE);

            // UPDATE TRANSAKSI
            $update = mysqli_query($conn, "
                UPDATE tbl_transaksi SET
                    last_gate = 'WH_SUBCONT_FROM_VENDOR',
                    qty_whsubcont_fr_vendor = qty_whsubcont_to_vendor,
                    updated_at = NOW()
                WHERE id_trans = '{$data['id_trans']}'
            ");

            if (!$update) {
                throw new Exception(mysqli_error($conn));
            }

            // NEW DATA
            $new_data = json_encode([
                "id_trans" => $data['id_trans'],
                "barcode" => $data['barcode'],
                "size" => $data['size'],
                "last_gate" => 'WH_SUBCONT_FROM_VENDOR',
                "qty_whsubcont_fr_vendor" =>
                $data['qty_whsubcont_to_vendor']
            ], JSON_UNESCAPED_UNICODE);

            // INSERT EVENT
            $event = mysqli_query($conn, "
                INSERT INTO tbl_transaksi_event SET
                    id_trans = '{$data['id_trans']}',
                    barcode = '{$data['barcode']}',
                    batch_transaksi = '{$data['batch_transaksi']}',
                    id_komponen = '{$data['id_komponen_out']}',
                    nm_komponen = '{$data['nm_komponen_out']}',
                    id_group = '{$data['id_group']}',
                    lot = '{$data['lot']}',
                    size = '{$data['size']}',
                    gate = 'WH_SUBCONT_FROM_VENDOR',
                    flow_type = 'IN',
                    qty = '{$data['qty_whsubcont_to_vendor']}',
                    qty_before = '{$data['qty_whsubcont_to_vendor']}',
                    qty_after = NULL,
                    transac_by = '$scan_by',
                    created_at = NOW()
            ");

            if (!$event) {
                throw new Exception(mysqli_error($conn));
            }

            // INSERT TLOG
            $tlog = mysqli_query($conn, "
                INSERT INTO tlog_transaksi SET
                    action_type = 'WH_SUBCONT_FROM_VENDOR',
                    old_data = '" . mysqli_real_escape_string($conn, $old_data) . "',
                    new_data = '" . mysqli_real_escape_string($conn, $new_data) . "',
                    updated_by = '$scan_by',
                    created_at = NOW(),
                    updated_at = NOW()
            ");

            if (!$tlog) {
                throw new Exception(mysqli_error($conn));
            }
        }

        // COMMIT
        mysqli_commit($conn);

        // SUCCESS
        $_SESSION['green_notif'] =
            "Transaksi berhasil diproses.";
        header(
            "Location: /isubcont/pages/trans-scan-in-incoming.php" .
                "?success=1" .
                "&barcode=$barcode"
        );

        exit;
    } catch (Exception $e) {

        mysqli_rollback($conn);

        $_SESSION['red_notif'] =
            "Gagal: " . $e->getMessage();

        header("Location: /isubcont/pages/trans-scan-in-incoming.php");
        exit;
    }
}

// SCAN OUT WH SUBCONT TO SM SUBCONT
if (
    isset($_POST['action']) && $_POST['action'] == 'scan_out_whsubcont_to_prod'
) {

    require_once 'helper_gate.php';
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    mysqli_begin_transaction($conn);
    $barcode = trim($_POST['barcode'] ?? '');
    $scan_by = $_SESSION['username'] ?? 'unknown';

    // VALIDASI BARCODE
    if (!$barcode) {

        $_SESSION['red_notif'] =
            "Barcode tidak boleh kosong.";
        header("Location: /isubcont/pages/trans-scan-out-to-prod.php");
        exit;
    }

    try {

        // AMBIL DATA ACTIVE
        $q = mysqli_query($conn, "
            SELECT *
            FROM tbl_transaksi
            WHERE barcode = '$barcode'
            ORDER BY id_trans ASC
        ");

        if (mysqli_num_rows($q) == 0) {
            throw new Exception("Barcode tidak ditemukan.");
        }

        // SIMPAN SEMUA ROW
        $all_data = [];

        while ($d = mysqli_fetch_assoc($q)) {
            $all_data[] = $d;
        }

        $first = $all_data[0];

        // VALIDASI FLOW
        foreach ($all_data as $d) {

            if (
                $d['last_gate'] != 'WH_SUBCONT_FROM_VENDOR'
            ) {

                $current_gate = $d['last_gate'];

                $current_label =
                    $gate_label[$current_gate] ?? $current_gate;

                $next_gate =
                    $next_gate_map[$current_gate] ?? null;

                $next_label = $next_gate
                    ? ($gate_label[$next_gate] ?? $next_gate)
                    : 'Unknown';

                throw new Exception("
                Barcode tidak sesuai untuk proses ini.
                Posisi terakhir:
                {$current_label}
                Silahkan lanjut scan di:
                {$next_label}
                ");
            }
        }

        // ANTI DOUBLE SCAN
        $check = mysqli_query($conn, "
            SELECT id_event
            FROM tbl_transaksi_event
            WHERE
                barcode = '$barcode'
                AND gate = 'WH_SUBCONT_TO_SM_SUBCONT'
            LIMIT 1
        ");

        if (mysqli_num_rows($check) > 0) {

            throw new Exception("
            Barcode sudah pernah di-scan pada proses ini sebelumnya.
            ");
        }

        // LOOP UPDATE SEMUA SIZE
        foreach ($all_data as $data) {

            // OLD DATA
            $old_data = json_encode([
                "id_trans" => $data['id_trans'],
                "barcode" => $data['barcode'],
                "size" => $data['size'],
                "last_gate" => $data['last_gate'],
                "qty_whsubcont_to_smsubcont" =>
                $data['qty_whsubcont_to_smsubcont']

            ], JSON_UNESCAPED_UNICODE);

            // UPDATE TRANSAKSI
            $update = mysqli_query($conn, "
                UPDATE tbl_transaksi SET
                    last_gate = 'WH_SUBCONT_TO_SM_SUBCONT',
                    qty_whsubcont_to_smsubcont = qty_whsubcont_fr_vendor,
                    updated_at = NOW()
                WHERE id_trans = '{$data['id_trans']}'
            ");

            if (!$update) {
                throw new Exception(mysqli_error($conn));
            }

            // NEW DATA
            $new_data = json_encode([
                "id_trans" => $data['id_trans'],
                "barcode" => $data['barcode'],
                "size" => $data['size'],
                "last_gate" => 'WH_SUBCONT_TO_SM_SUBCONT',
                "qty_whsubcont_to_smsubcont" =>
                $data['qty_whsubcont_fr_vendor']

            ], JSON_UNESCAPED_UNICODE);

            // INSERT EVENT
            $event = mysqli_query($conn, "
                INSERT INTO tbl_transaksi_event SET
                    id_trans = '{$data['id_trans']}',
                    barcode = '{$data['barcode']}',
                    batch_transaksi = '{$data['batch_transaksi']}',
                    id_komponen = '{$data['id_komponen_out']}',
                    nm_komponen = '{$data['nm_komponen_out']}',
                    id_group = '{$data['id_group']}',
                    lot = '{$data['lot']}',
                    size = '{$data['size']}',
                    gate = 'WH_SUBCONT_TO_SM_SUBCONT',
                    flow_type = 'OUT',
                    qty = '{$data['qty_whsubcont_fr_vendor']}',
                    qty_before = '{$data['qty_whsubcont_fr_vendor']}',
                    qty_after = NULL,
                    transac_by = '$scan_by',
                    created_at = NOW()
            ");

            if (!$event) {
                throw new Exception(mysqli_error($conn));
            }

            // INSERT TLOG
            $tlog = mysqli_query($conn, "
                INSERT INTO tlog_transaksi SET
                    action_type = 'WH_SUBCONT_TO_SM_SUBCONT',
                    old_data = '" . mysqli_real_escape_string($conn, $old_data) . "',
                    new_data = '" . mysqli_real_escape_string($conn, $new_data) . "',
                    updated_by = '$scan_by',
                    created_at = NOW(),
                    updated_at = NOW()
            ");

            if (!$tlog) {
                throw new Exception(mysqli_error($conn));
            }
        }

        // COMMIT
        mysqli_commit($conn);

        // SUCCESS
        $_SESSION['green_notif'] =
            "Transaksi berhasil diproses.";

        header(
            "Location: /isubcont/pages/trans-scan-out-to-prod.php" .
                "?success=1" .
                "&barcode=$barcode"
        );

        exit;
    } catch (Exception $e) {

        mysqli_rollback($conn);

        $_SESSION['red_notif'] =
            "Gagal: " . $e->getMessage();

        header("Location: /isubcont/pages/trans-scan-out-to-prod.php");
        exit;
    }
}

// SCAN IN SM SUBCONT (PRODUCTION) FROM WH SUBCONT
if (
    isset($_POST['action']) && $_POST['action'] == 'scan_in_prod_from_whsubcont'
) {

    require_once 'helper_gate.php';

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    mysqli_begin_transaction($conn);
    $barcode = trim($_POST['barcode'] ?? '');
    $scan_by = $_SESSION['username'] ?? 'unknown';

    // VALIDASI BARCODE
    if (!$barcode) {
        $_SESSION['red_notif'] =
            "Barcode tidak boleh kosong.";
        header("Location: /isubcont/pages/trans-scan-in-prod-smsubcont.php");
        exit;
    }

    try {
        // AMBIL DATA ACTIVE
        $q = mysqli_query($conn, "
            SELECT *
            FROM tbl_transaksi
            WHERE barcode = '$barcode'
            ORDER BY id_trans ASC
        ");

        if (mysqli_num_rows($q) == 0) {
            throw new Exception("Barcode tidak ditemukan.");
        }

        // SIMPAN SEMUA ROW
        $all_data = [];
        while ($d = mysqli_fetch_assoc($q)) {
            $all_data[] = $d;
        }

        $first = $all_data[0];

        // VALIDASI FLOW
        foreach ($all_data as $d) {

            if (
                $d['last_gate'] != 'WH_SUBCONT_TO_SM_SUBCONT'
            ) {

                $current_gate = $d['last_gate'];

                $current_label =
                    $gate_label[$current_gate] ?? $current_gate;

                $next_gate =
                    $next_gate_map[$current_gate] ?? null;

                $next_label = $next_gate
                    ? ($gate_label[$next_gate] ?? $next_gate)
                    : 'Unknown';

                throw new Exception("
                Barcode tidak sesuai untuk proses ini.
                Posisi terakhir:
                {$current_label}
                Silahkan lanjut scan di:
                {$next_label}
                ");
            }
        }

        // ANTI DOUBLE SCAN
        $check = mysqli_query($conn, "
            SELECT id_event
            FROM tbl_transaksi_event
            WHERE
                barcode = '$barcode'
                AND gate = 'SM_SUBCONT_FROM_WH_SUBCONT'
            LIMIT 1
        ");

        if (mysqli_num_rows($check) > 0) {

            throw new Exception("
            Barcode sudah pernah di-scan pada proses ini sebelumnya.
            ");
        }

        // LOOP UPDATE SEMUA SIZE
        foreach ($all_data as $data) {

            // OLD DATA
            $old_data = json_encode([
                "id_trans" => $data['id_trans'],
                "barcode" => $data['barcode'],
                "size" => $data['size'],
                "last_gate" => $data['last_gate'],
                "qty_smsubcont_fr_whsubcont" =>
                $data['qty_smsubcont_fr_whsubcont']
            ], JSON_UNESCAPED_UNICODE);

            // UPDATE TRANSAKSI
            $update = mysqli_query($conn, "
                UPDATE tbl_transaksi SET
                    last_gate = 'SM_SUBCONT_FROM_WH_SUBCONT',
                    qty_smsubcont_fr_whsubcont = qty_whsubcont_to_smsubcont,
                    updated_at = NOW()
                WHERE id_trans = '{$data['id_trans']}'
            ");

            if (!$update) {
                throw new Exception(mysqli_error($conn));
            }

            // NEW DATA
            $new_data = json_encode([
                "id_trans" => $data['id_trans'],
                "barcode" => $data['barcode'],
                "size" => $data['size'],
                "last_gate" => 'SM_SUBCONT_FROM_WH_SUBCONT',
                "qty_smsubcont_fr_whsubcont" =>
                $data['qty_whsubcont_to_smsubcont']

            ], JSON_UNESCAPED_UNICODE);

            // INSERT EVENT
            $event = mysqli_query($conn, "
                INSERT INTO tbl_transaksi_event SET
                    id_trans = '{$data['id_trans']}',
                    barcode = '{$data['barcode']}',
                    batch_transaksi = '{$data['batch_transaksi']}',
                    id_komponen = '{$data['id_komponen_out']}',
                    nm_komponen = '{$data['nm_komponen_out']}',
                    id_group = '{$data['id_group']}',
                    lot = '{$data['lot']}',
                    size = '{$data['size']}',
                    gate = 'SM_SUBCONT_FROM_WH_SUBCONT',
                    flow_type = 'IN',
                    qty = '{$data['qty_whsubcont_to_smsubcont']}',
                    qty_before = '{$data['qty_whsubcont_to_smsubcont']}',
                    qty_after = NULL,
                    transac_by = '$scan_by',
                    created_at = NOW()
            ");

            if (!$event) {
                throw new Exception(mysqli_error($conn));
            }

            // INSERT TLOG
            $tlog = mysqli_query($conn, "
                INSERT INTO tlog_transaksi SET
                    action_type = 'SM_SUBCONT_FROM_WH_SUBCONT',
                    old_data = '" . mysqli_real_escape_string($conn, $old_data) . "',
                    new_data = '" . mysqli_real_escape_string($conn, $new_data) . "',
                    updated_by = '$scan_by',
                    created_at = NOW(),
                    updated_at = NOW()
            ");

            if (!$tlog) {
                throw new Exception(mysqli_error($conn));
            }
        }

        // COMMIT
        mysqli_commit($conn);

        // SUCCESS
        $_SESSION['green_notif'] =
            "Transaksi berhasil diproses.";

        header(
            "Location: /isubcont/pages/trans-scan-in-prod-smsubcont.php" .
                "?success=1" .
                "&barcode=$barcode"
        );

        exit;
    } catch (Exception $e) {

        mysqli_rollback($conn);

        $_SESSION['red_notif'] =
            "Gagal: " . $e->getMessage();

        header("Location: /isubcont/pages/trans-scan-in-prod-smsubcont.php");
        exit;
    }
}

// SCAN OUT SM SUBCONT TO PRODUCTION
if (
    isset($_POST['action']) && $_POST['action'] == 'scan_out_smsubcont_to_prod'
) {

    require_once 'helper_gate.php';

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    mysqli_begin_transaction($conn);

    $barcode = trim($_POST['barcode'] ?? '');
    $scan_by = $_SESSION['username'] ?? 'unknown';

    // PICKUP SESSION
    $pickup_nik = trim($_POST['pickup_nik'] ?? '');
    $pickup_name = trim($_POST['pickup_name'] ?? '');
    $pickup_ncvs = trim($_POST['pickup_ncvs'] ?? '');

    // VALIDASI BARCODE
    if (!$barcode) {
        $_SESSION['red_notif'] =
            "Barcode tidak boleh kosong.";
        header("
            Location:
            /isubcont/pages/trans-scan-out-smsubcont-to-prod.php
        ");
        exit;
    }

    // VALIDASI PICKUP
    if (
        !$pickup_nik ||
        !$pickup_name ||
        !$pickup_ncvs
    ) {

        $_SESSION['red_notif'] =
            "WS Preparation belum tap ID Card.";

        header("
            Location:
            /isubcont/pages/trans-scan-out-smsubcont-to-prod.php
        ");

        exit;
    }

    try {

        // AMBIL DATA ACTIVE
        $q = mysqli_query($conn, "
            SELECT *
            FROM tbl_transaksi
            WHERE barcode = '$barcode'
            ORDER BY id_trans ASC
        ");

        if (mysqli_num_rows($q) == 0) {
            throw new Exception("Barcode tidak ditemukan.");
        }

        // SIMPAN SEMUA ROW
        $all_data = [];

        while ($d = mysqli_fetch_assoc($q)) {
            $all_data[] = $d;
        }

        $first = $all_data[0];

        // VALIDASI FLOW
        foreach ($all_data as $d) {

            if (
                $d['last_gate']
                != 'SM_SUBCONT_FROM_WH_SUBCONT'
            ) {

                $current_gate =
                    $d['last_gate'];

                $current_label =
                    $gate_label[$current_gate]
                    ?? $current_gate;

                $next_gate =
                    $next_gate_map[$current_gate]
                    ?? null;

                $next_label = $next_gate
                    ? ($gate_label[$next_gate]
                        ?? $next_gate)
                    : 'Unknown';

                throw new Exception("
                Barcode tidak sesuai untuk proses ini.

                Posisi terakhir:
                {$current_label}

                Silahkan lanjut scan di:
                {$next_label}
                ");
            }
        }

        // VALIDASI NCVS
        // if (
        //     $pickup_ncvs !=
        //     $first['ncvs']
        // ) {

        //     throw new Exception("
        //     NCVS tidak sesuai.

        //     Barcode ini milik NCVS:
        //     {$first['ncvs']}
        //     ");
        // }

        // ANTI DOUBLE SCAN
        $check = mysqli_query($conn, "
            SELECT id_event
            FROM tbl_transaksi_event
            WHERE
                barcode = '$barcode'
                AND gate = 'SM_SUBCONT_TO_NCVS'
            LIMIT 1
        ");

        if (mysqli_num_rows($check) > 0) {

            throw new Exception("
            Barcode sudah pernah di-scan pada proses ini sebelumnya.
            ");
        }

        // LOOP UPDATE SEMUA SIZE
        foreach ($all_data as $data) {

            // OLD DATA
            $old_data = json_encode([

                "id_trans" => $data['id_trans'],
                "barcode" => $data['barcode'],
                "size" => $data['size'],
                "last_gate" => $data['last_gate'],
                "qty_smsubcont_to_prod" => $data['qty_smsubcont_to_prod']
            ], JSON_UNESCAPED_UNICODE);

            // UPDATE TRANSAKSI
            $update = mysqli_query($conn, "
                UPDATE tbl_transaksi SET
                    last_gate = 'SM_SUBCONT_TO_NCVS',
                    qty_smsubcont_to_prod = qty_smsubcont_fr_whsubcont,
                    updated_at = NOW()
                WHERE id_trans = '{$data['id_trans']}'
            ");

            if (!$update) {
                throw new Exception(
                    mysqli_error($conn)
                );
            }

            // NEW DATA
            $new_data = json_encode([
                "id_trans" => $data['id_trans'],
                "barcode" => $data['barcode'],
                "size" => $data['size'],
                "last_gate" => 'SM_SUBCONT_TO_NCVS',
                "qty_smsubcont_to_prod" => $data['qty_smsubcont_fr_whsubcont']

            ], JSON_UNESCAPED_UNICODE);

            // INSERT EVENT
            $event = mysqli_query($conn, "
                INSERT INTO tbl_transaksi_event SET
                    id_trans = '{$data['id_trans']}',
                    barcode = '{$data['barcode']}',
                    batch_transaksi = '{$data['batch_transaksi']}',
                    id_komponen = '{$data['id_komponen_out']}',
                    nm_komponen = '{$data['nm_komponen_out']}',
                    id_group = '{$data['id_group']}',
                    lot = '{$data['lot']}',
                    size = '{$data['size']}',
                    gate = 'SM_SUBCONT_TO_NCVS',
                    flow_type = 'OUT',
                    qty = '{$data['qty_smsubcont_fr_whsubcont']}',
                    qty_before = '{$data['qty_smsubcont_fr_whsubcont']}',
                    qty_after = NULL,
                    transac_by = '$scan_by',
                    pickup_nik = '$pickup_nik',
                    pickup_name = '$pickup_name',
                    pickup_ncvs = '$pickup_ncvs',
                    pickup_at = NOW(),
                    created_at = NOW()
            ");

            if (!$event) {

                throw new Exception(
                    mysqli_error($conn)
                );
            }

            // INSERT TLOG
            $tlog = mysqli_query($conn, "
                INSERT INTO tlog_transaksi SET
                    action_type = 'SM_SUBCONT_TO_NCVS',
                    old_data = '" . mysqli_real_escape_string($conn, $old_data) . "',
                    new_data = '" . mysqli_real_escape_string($conn, $new_data) . "',
                    updated_by = '$scan_by',
                    created_at = NOW(),
                    updated_at = NOW()
            ");

            if (!$tlog) {

                throw new Exception(
                    mysqli_error($conn)
                );
            }
        }

        // COMMIT
        mysqli_commit($conn);

        // SUCCESS
        $_SESSION['green_notif'] =
            "Transaksi berhasil diproses.";
        header(
            "Location: /isubcont/pages/trans-scan-out-smsubcont-to-prod.php" .
                "?success=1" .
                "&barcode=$barcode"
        );

        exit;
    } catch (Exception $e) {
        mysqli_rollback($conn);

        $_SESSION['red_notif'] =
            "Gagal: " . $e->getMessage();

        header("Location: /isubcont/pages/trans-scan-out-smsubcont-to-prod.php");
        exit;
    }
}

// REGISTER defect
if (isset($_POST['submit-defect'])) {
    date_default_timezone_set('Asia/Jakarta');

    // Ambil dan filter data
    $updated_by = mysqli_real_escape_string($conn, $_POST['updated_by']);
    $defect   = mysqli_real_escape_string($conn, $_POST['defect']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $timestamp  = date('Y-m-d H:i:s');

    // Cek apakah NIK sudah ada
    $check_role = mysqli_query($conn, "SELECT 1 FROM tbl_defect WHERE defect = '$defect'");
    if (mysqli_num_rows($check_role) > 0) {
        $_SESSION['red_notif'] = "Defect sudah terdaftar, mohon ganti ke defect lain.";
        header("Location: /isubcont/pages/master-defect.php");
        exit();
    }

    // Simpan ke tbl_defect
    $query_role = mysqli_query($conn, "INSERT INTO tbl_defect 
        (defect, description, is_deleted, updated_by, timestamp) 
        VALUES 
        ('$defect', '$description', '0', '$updated_by', '$timestamp')");

    if ($query_role) {
        $last_user_id = mysqli_insert_id($conn);

        // Siapkan log (hanya simpan data baru)
        $new_data = [
            "defect" => $defect,
            "description" => $description
        ];
        $new_data_json = mysqli_real_escape_string($conn, json_encode($new_data));

        $query_log = mysqli_query($conn, "INSERT INTO tlog_defect 
            (id_defect, updated_by, action_type, old_data, new_data, created_at, updated_at) 
            VALUES 
            ('$last_user_id', '$updated_by', 'INSERT', NULL, '$new_data_json', NOW(), NOW())");

        if ($query_log) {
            $_SESSION['green_notif'] = "Defect berhasil didaftarkan.";
        } else {
            $_SESSION['red_notif'] = "Defect berhasil didaftarkan, tapi log gagal.";
        }

        header("Location: /isubcont/pages/master-defect.php");
        exit();
    } else {
        $_SESSION['red_notif'] = "Defect tidak berhasil didaftarkan.";
        header("Location: /isubcont/pages/master-defect.php");
        exit();
    }
}

if (isset($_POST['update-defect'])) {
    date_default_timezone_set('Asia/Jakarta');

    // Ambil data dan sanitasi
    $id_defect = $_POST['id_defect'];
    $updated_by  = $_POST['updated_by'];
    $defect   = $_POST['defect'];
    $description = $_POST['description'];
    $timestamp   = date('Y-m-d H:i:s');

    // Ambil data lama untuk logging
    $stmt_old = $conn->prepare("SELECT defect, description FROM tbl_defect WHERE id_defect = ?");
    $stmt_old->bind_param("i", $id_defect);
    $stmt_old->execute();
    $old_data = $stmt_old->get_result()->fetch_assoc();
    $old_data_json = json_encode($old_data, JSON_UNESCAPED_UNICODE);

    // Update roles
    $stmt_update = $conn->prepare("UPDATE tbl_defect 
                                   SET defect = ?, description = ?, updated_by = ?, timestamp = ? 
                                   WHERE id_defect = ?");
    $stmt_update->bind_param("ssssi", $defect, $description, $updated_by, $timestamp, $id_defect);

    if ($stmt_update->execute()) {
        // Siapkan data baru untuk logging
        $new_data = [
            "defect"   => $defect,
            "description" => $description
        ];
        $new_data_json = json_encode($new_data, JSON_UNESCAPED_UNICODE);

        // Insert log ke tlog_role
        $stmt_log = $conn->prepare("INSERT INTO tlog_defect 
            (id_defect, updated_by, action_type, old_data, new_data, created_at, updated_at) 
            VALUES (?, ?, 'UPDATE', ?, ?, NOW(), NOW())");
        $stmt_log->bind_param("isss", $id_defect, $updated_by, $old_data_json, $new_data_json);
        $stmt_log->execute();

        $_SESSION['green_notif'] = "Data defect berhasil diperbarui.";
    } else {
        $_SESSION['red_notif'] = "Defect tidak berhasil diupdate.";
    }

    header("Location: /isubcont/pages/master-defect.php");
    exit;
}

// REMOVE role (soft delete) defect
if (isset($_POST['remove-defect'])) {
    $id_defect   = $_POST['id_defect'];
    $username  = $_SESSION['username'] ?? 'SYSTEM';

    // 1. Ambil data role
    $stmt = $conn->prepare("SELECT * FROM tbl_defect WHERE id_defect = ? AND is_deleted = 0 LIMIT 1");
    $stmt->bind_param("i", $id_defect);
    $stmt->execute();
    $result = $stmt->get_result();
    $role = $result->fetch_assoc();
    $stmt->close();

    if (!$role) {
        $_SESSION['red_notif'] = "Data defect tidak ditemukan atau sudah dihapus.";
        header('Location: /isubcont/pages/master-defect.php');
        exit;
    }

    $old_data_json = json_encode($role, JSON_UNESCAPED_UNICODE);

    // Simulasi data baru
    $role['is_deleted'] = 1;
    $new_data_json = json_encode($role, JSON_UNESCAPED_UNICODE);

    // 2. Update roles (soft delete)
    $stmt = $conn->prepare("UPDATE tbl_defect SET is_deleted = 1, updated_by = ?, timestamp = NOW() WHERE id_defect = ?");
    $stmt->bind_param("si", $username, $id_defect);
    $success = $stmt->execute();
    $stmt->close();

    if ($success) {
        // 3. Log ke tlog_role
        $stmt = $conn->prepare("INSERT INTO tlog_defect
            (id_defect, updated_by, action_type, old_data, new_data, created_at, updated_at)
            VALUES (?, ?, 'REMOVE', ?, ?, NOW(), NOW())");
        $stmt->bind_param("isss", $id_defect, $username, $old_data_json, $new_data_json);
        $stmt->execute();
        $stmt->close();

        $_SESSION['green_notif'] = "Data defect berhasil dihapus.";
    } else {
        $_SESSION['red_notif'] = "Gagal menghapus data defect.";
    }

    header('Location: /isubcont/pages/master-defect.php');
    exit;
}

// RESTORE deleted defect
if (isset($_POST['restore-defect'])) {
    $id_defect  = $_POST['id_defect'];
    $username = $_SESSION['username'] ?? 'SYSTEM';

    $stmt = $conn->prepare("SELECT * FROM tbl_defect WHERE id_defect = ? LIMIT 1");
    $stmt->bind_param("i", $id_defect);
    $stmt->execute();
    $result = $stmt->get_result();
    $role = $result->fetch_assoc();
    $stmt->close();

    if ($role && $role['is_deleted'] == 1) {
        $old_data_json = json_encode($role, JSON_UNESCAPED_UNICODE);

        // Update restore
        $stmt = $conn->prepare("UPDATE tbl_defect SET is_deleted = 0, updated_by = ?, timestamp = NOW() WHERE id_defect = ?");
        $stmt->bind_param("si", $username, $id_defect);
        $success = $stmt->execute();
        $stmt->close();

        if ($success) {
            $role['is_deleted'] = 0;
            $new_data_json = json_encode($role, JSON_UNESCAPED_UNICODE);

            $stmt = $conn->prepare("INSERT INTO tlog_defect 
                (id_defect, updated_by, action_type, old_data, new_data, created_at, updated_at)
                VALUES (?, ?, 'RESTORE', ?, ?, NOW(), NOW())");
            $stmt->bind_param("isss", $id_defect, $username, $old_data_json, $new_data_json);
            $stmt->execute();
            $stmt->close();

            $_SESSION['green_notif'] = "Data defect berhasil direstore.";
        } else {
            $_SESSION['red_notif'] = "Data defect gagal direstore.";
        }
    } else {
        $_SESSION['red_notif'] = "Data defect tidak ditemukan atau belum dihapus.";
    }

    header("Location: /isubcont/pages/archive-defect.php");
    exit();
}

// DELETE permanent defect
if (isset($_POST['delete-defect'])) {
    $id_defect  = $_POST['id_defect'];
    $username = $_SESSION['username'] ?? 'SYSTEM';

    $stmt = $conn->prepare("SELECT * FROM tbl_defect WHERE id_defect = ? LIMIT 1");
    $stmt->bind_param("i", $id_defect);
    $stmt->execute();
    $result = $stmt->get_result();
    $role = $result->fetch_assoc();
    $stmt->close();

    if ($role) {
        $old_data_json = json_encode($role, JSON_UNESCAPED_UNICODE);

        // DELETE permanen
        $stmt = $conn->prepare("DELETE FROM tbl_defect WHERE id_defect = ?");
        $stmt->bind_param("i", $id_defect);
        $success = $stmt->execute();
        $stmt->close();

        if ($success) {
            $new_data = [
                "note" => "Defect dihapus permanen oleh {$username} pada " . date('Y-m-d H:i:s')
            ];
            $new_data_json = json_encode($new_data, JSON_UNESCAPED_UNICODE);

            $stmt = $conn->prepare("INSERT INTO tlog_defect 
                (id_defect, updated_by, action_type, old_data, new_data, created_at, updated_at)
                VALUES (?, ?, 'DELETE', ?, ?, NOW(), NOW())");
            $stmt->bind_param("isss", $id_defect, $username, $old_data_json, $new_data_json);
            $stmt->execute();
            $stmt->close();

            $_SESSION['green_notif'] = "Data defect berhasil dihapus permanen.";
        } else {
            $_SESSION['red_notif'] = "Data defect gagal dihapus permanen.";
        }
    } else {
        $_SESSION['red_notif'] = "Data defect tidak ditemukan.";
    }

    header("Location: /isubcont/pages/archive-defect.php");
    exit();
}

// // === Confirm Check QC ===
// if (isset($_POST['confirm-qc']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
//     $barcode      = $_POST['barcode'] ?? null;
//     $post_qty     = $_POST['qty'] ?? [];
//     $post_defect  = $_POST['defect'] ?? [];
//     $post_def_qty = $_POST['defect_qty'] ?? [];
//     $scan_with    = $_SESSION['username'] ?? 'unknown';

//     if (empty($barcode)) {
//         $_SESSION['red_notif'] = "QR Code tidak boleh kosong.";
//         header("Location: /isubcont/pages/trans-scan-check-qc.php");
//         exit;
//     }

//     $conn->begin_transaction();
//     try {
//         // --- Ambil data transaksi lama ---
//         $stmt_old = $conn->prepare("SELECT * FROM tbl_transaksi WHERE barcode = ?");
//         $stmt_old->bind_param("s", $barcode);
//         $stmt_old->execute();
//         $res_old = $stmt_old->get_result();
//         $old_data = $res_old->fetch_assoc();
//         $stmt_old->close();

//         if (!$old_data) throw new Exception("QR Code $barcode tidak ditemukan.");

//         $id_trans_asal = (int)$old_data['id_trans'];
//         $job_order     = $old_data['job_order'] ?? '';
//         $json_old_data = json_encode($old_data, JSON_UNESCAPED_UNICODE);

//         // --- Ambil hour sekarang ---
//         $stmt_hour = $conn->prepare("
//             SELECT hour 
//             FROM tbl_time 
//             WHERE TIME(NOW()) BETWEEN start_hour AND end_hour
//             ORDER BY id_time LIMIT 1
//         ");
//         $stmt_hour->execute();
//         $res_hour = $stmt_hour->get_result();
//         $hour_row = $res_hour->fetch_assoc();
//         $hour = $hour_row['hour'] ?? null;
//         $stmt_hour->close();

//         // --- Validasi urutan scan ---
//         $scan_flow = ["SCAN_IN_WAREHOUSE", "SCAN_OUT_TO_VENDOR", "SCAN_IN_INCOMING", "SCAN_CHECK_QC", "SCAN_OUT_TO_PRODUCTION"];
//         $current_state = strtoupper($old_data['type_scan'] ?? '');
//         $current_index = array_search($current_state, $scan_flow);
//         if ($current_index === false) $current_index = -1;
//         $next_state = $scan_flow[$current_index + 1] ?? null;
//         if ($next_state !== "SCAN_CHECK_QC") {
//             throw new Exception("QR Code tidak bisa di-scan di tahap ini. Current: $current_state, Next: $next_state");
//         }

//         // --- Ambil qty_real dari log SCAN_IN_INCOMING ---
//         $qty_real_arr = [];
//         $stmt_real = $conn->prepare("
//             SELECT qty_real 
//             FROM tlog_transaksi 
//             WHERE id_trans = ? 
//               AND action_type = 'SCAN_IN_INCOMING'
//             ORDER BY id_log_trans DESC 
//             LIMIT 1
//         ");
//         $stmt_real->bind_param("i", $id_trans_asal);
//         $stmt_real->execute();
//         $res_real = $stmt_real->get_result();
//         $real_row = $res_real->fetch_assoc();
//         $stmt_real->close();

//         if ($real_row && !empty($real_row['qty_real'])) {
//             $decoded_real = json_decode($real_row['qty_real'], true);
//             if (is_array($decoded_real)) $qty_real_arr = $decoded_real;
//         } else {
//             $fallback = json_decode($old_data['komponen_qty'] ?? '[]', true);
//             if (is_array($fallback)) $qty_real_arr = $fallback;
//         }
//         $qty_real_json = json_encode($qty_real_arr, JSON_UNESCAPED_UNICODE);

//         // --- Build komponen map dari input qty ---
//         $komp_map = [];
//         if (!empty($post_qty) && is_array($post_qty)) {
//             foreach ($post_qty as $kid => $sizes) {
//                 foreach ($sizes as $sz => $q) {
//                     $komp_map[(int)$kid][(string)$sz] = (int)$q;
//                 }
//             }
//         } else {
//             $decoded_old = json_decode($old_data['komponen_qty'] ?? '[]', true);
//             foreach ($decoded_old as $it) {
//                 $kid = (int)($it['komponen'] ?? 0);
//                 $sz  = (string)($it['size'] ?? '-');
//                 $qt  = (int)($it['qty'] ?? 0);
//                 $komp_map[$kid][$sz] = $qt;
//             }
//         }

//         // --- Proses defect ---
//         $defect_arr = [];
//         if (!empty($post_defect) && is_array($post_defect)) {
//             foreach ($post_defect as $kid => $sizes) {
//                 foreach ($sizes as $sz => $def_list) {
//                     foreach ($def_list as $idx => $def_id) {
//                         $qty_def = isset($post_def_qty[$kid][$sz][$idx]) ? (int)$post_def_qty[$kid][$sz][$idx] : 0;
//                         if ($qty_def > 0) {
//                             $defect_arr[] = [
//                                 "komponen" => $kid,
//                                 "size" => $sz,
//                                 "defect" => $def_id,
//                                 "qty" => $qty_def
//                             ];
//                             if (isset($komp_map[$kid][$sz])) {
//                                 $komp_map[$kid][$sz] = max(0, $komp_map[$kid][$sz] - $qty_def);
//                             }
//                         }
//                     }
//                 }
//             }
//         }

//         // --- Build array final untuk new_data log ---
//         $komponen_arr = [];
//         foreach ($komp_map as $kid => $sizes) {
//             foreach ($sizes as $sz => $q) {
//                 $komponen_arr[] = ["komponen" => $kid, "size" => $sz, "qty" => $q];
//             }
//         }
//         $qty_json_final = json_encode($komponen_arr, JSON_UNESCAPED_UNICODE);
//         $defect_json    = json_encode($defect_arr, JSON_UNESCAPED_UNICODE);

//         // --- Ambil data tbl_transaksi tapi tidak rubah komponen_qty ---
//         $stmt_new = $conn->prepare("SELECT * FROM tbl_transaksi WHERE barcode=?");
//         $stmt_new->bind_param("s", $barcode);
//         $stmt_new->execute();
//         $res_new = $stmt_new->get_result();
//         $new_data = $res_new->fetch_assoc();
//         $stmt_new->close();

//         // --- Buat versi dimodifikasi untuk new_data log ---
//         $modified_new_data = $new_data;
//         $modified_new_data['komponen_qty'] = $komponen_arr; // hanya untuk log
//         $json_new_data = json_encode($modified_new_data, JSON_UNESCAPED_UNICODE);

//         // --- Update tbl_transaksi, tetap tidak ubah komponen_qty utama ---
//         $stmt_upd = $conn->prepare("
//             UPDATE tbl_transaksi
//             SET type_scan='SCAN_CHECK_QC',
//                 defect_qty=?,
//                 scan_with=?,
//                 scan_at=NOW(),
//                 hour=?
//             WHERE barcode=?
//         ");
//         $stmt_upd->bind_param("sssi", $defect_json, $scan_with, $hour, $barcode);
//         $stmt_upd->execute();
//         $stmt_upd->close();

//         // --- Hitung qty_kekurangan gabungan dari incoming + defect ---
//         $incoming_kurang = [];
//         $stmt_inc = $conn->prepare("
//             SELECT komponen_qty 
//             FROM tbl_transaksi_kekurangan 
//             WHERE id_trans_asal=? 
//               AND last_gate='SCAN_IN_INCOMING' 
//               AND status='pending'
//         ");
//         $stmt_inc->bind_param("i", $id_trans_asal);
//         $stmt_inc->execute();
//         $res_inc = $stmt_inc->get_result();
//         while ($row = $res_inc->fetch_assoc()) {
//             $inc = json_decode($row['komponen_qty'], true);
//             if (is_array($inc)) $incoming_kurang = array_merge($incoming_kurang, $inc);
//         }
//         $stmt_inc->close();

//         $qty_kekurangan_arr = $incoming_kurang;
//         foreach ($defect_arr as $d) {
//             $qty_kekurangan_arr[] = [
//                 "komponen" => $d['komponen'],
//                 "size" => $d['size'],
//                 "qty" => $d['qty']
//             ];
//         }

//         // --- Normalisasi format
//         foreach ($qty_kekurangan_arr as &$it) {
//             if (isset($it['qty']) && !isset($it['kekurangan'])) {
//                 $it['kekurangan'] = (int)$it['qty'];
//                 unset($it['qty']);
//             }
//         }
//         unset($it);

//         $is_empty = empty($qty_kekurangan_arr);
//         $status_kekurangan = $is_empty ? "CONFIRMED" : "PENDING";
//         $qty_kekurangan_json = $is_empty ? json_encode(0) : json_encode($qty_kekurangan_arr, JSON_UNESCAPED_UNICODE);

//         // --- Insert log ke tlog_transaksi ---
//         $stmt_log = $conn->prepare("
//             INSERT INTO tlog_transaksi
//             (id_trans, updated_by, action_type, old_data, new_data, qty_real, qty_kekurangan, status_kekurangan, created_at, updated_at)
//             VALUES (?, ?, 'SCAN_CHECK_QC', ?, ?, ?, ?, ?, NOW(), NOW())
//         ");
//         $stmt_log->bind_param(
//             "issssss",
//             $id_trans_asal,
//             $scan_with,
//             $json_old_data,
//             $json_new_data,
//             $qty_real_json,
//             $qty_kekurangan_json,
//             $status_kekurangan
//         );
//         $stmt_log->execute();
//         $stmt_log->close();

//         // --- Simpan ke tbl_transaksi_kekurangan jika ada kekurangan atau defect ---
//         $ada_kekurangan = !$is_empty;
//         $ada_defect     = !empty($defect_arr);

//         if ($ada_kekurangan || $ada_defect) {
//             $total_kekurangan = 0;
//             foreach ($qty_kekurangan_arr as $it) $total_kekurangan += (int)($it['kekurangan'] ?? 0);

//             $total_defect = 0;
//             foreach ($defect_arr as $d) $total_defect += (int)($d['qty'] ?? 0);

//             $komponen_json_kurang = json_encode($qty_kekurangan_arr, JSON_UNESCAPED_UNICODE);

//             $stmt_kurang = $conn->prepare("
//                 INSERT INTO tbl_transaksi_kekurangan
//                 (id_trans_asal, job_order, komponen_qty, defect_qty, total_kekurangan, status, last_gate, created_at, updated_at)
//                 VALUES (?, ?, ?, ?, ?, 'pending', 'SCAN_CHECK_QC', NOW(), NOW())
//             ");
//             $stmt_kurang->bind_param(
//                 "issii",
//                 $id_trans_asal,
//                 $job_order,
//                 $komponen_json_kurang,
//                 $total_defect,
//                 $total_kekurangan
//             );
//             $stmt_kurang->execute();
//             $stmt_kurang->close();
//         }

//         $conn->commit();
//         $_SESSION['green_notif'] = "QR Code berhasil di-scan (Scan Check QC).";
//         header("Location: /isubcont/pages/trans-scan-check-qc.php?success=" . urlencode($barcode));
//         exit;
//     } catch (Exception $e) {
//         $conn->rollback();
//         error_log("ERROR confirm-qc: " . $e->getMessage());
//         $_SESSION['red_notif'] = "Gagal confirm QC: " . $e->getMessage();
//         header("Location: /isubcont/pages/trans-scan-check-qc.php");
//         exit;
//     }
// }

// === Fungsi safeJson ===
function safeJson($json)
{
    if (is_array($json)) return $json;        // sudah array, return langsung
    if (empty($json)) return [];              // kosong, return array kosong
    $decoded = json_decode($json, true);
    return is_array($decoded) ? $decoded : [];
}

// === Scan Out to Production ===
if (isset($_POST['scan-out-production'])) {
    $barcode   = $_POST['barcode'] ?? null;
    $scan_with = $_SESSION['username'] ?? 'unknown';

    if ($barcode) {
        $conn->begin_transaction();
        try {
            $scan_flow = [
                "SCAN_IN_WAREHOUSE",
                "SCAN_OUT_TO_VENDOR",
                "SCAN_IN_INCOMING",
                "SCAN_OUT_TO_PRODUCTION"
            ];

            // --- Ambil data lama (old_data nanti diganti)
            $stmt_old = $conn->prepare("SELECT * FROM tbl_transaksi WHERE barcode = ?");
            $stmt_old->bind_param("s", $barcode);
            $stmt_old->execute();
            $res_old = $stmt_old->get_result();
            $old_data_raw = $res_old->fetch_assoc();
            $stmt_old->close();

            if (!$old_data_raw) {
                $_SESSION['red_notif'] = "Barcode \"$barcode\" tidak ditemukan.";
                header("Location: /isubcont/pages/trans-scan-out-to-prod.php");
                exit;
            }

            $id_trans = (int)$old_data_raw['id_trans'];

            // --- Ambil data terakhir SCAN_IN_INCOMING untuk dijadikan old_data
            $stmt_inc = $conn->prepare("
                SELECT new_data 
                FROM tlog_transaksi 
                WHERE id_trans = ? AND action_type = 'SCAN_IN_INCOMING'
                ORDER BY id_log_trans DESC LIMIT 1
            ");
            $stmt_inc->bind_param("i", $id_trans);
            $stmt_inc->execute();
            $res_inc = $stmt_inc->get_result();
            $inc_row = $res_inc->fetch_assoc();
            $stmt_inc->close();

            $old_data = safeJson($inc_row['new_data'] ?? []);
            $json_old_data = json_encode($old_data, JSON_UNESCAPED_UNICODE);

            // --- Validasi urutan scan
            $current_state = strtoupper($old_data_raw['type_scan'] ?? '');
            $current_index = array_search($current_state, $scan_flow);
            if ($current_index === false) $current_index = -1;
            $next_state = $scan_flow[$current_index + 1] ?? null;
            if ($next_state !== "SCAN_OUT_TO_PRODUCTION") {
                $_SESSION['red_notif'] = "Barcode tidak bisa di-scan di tahap ini. Transaksi selesai.";
                header("Location: /isubcont/pages/trans-scan-out-to-prod.php");
                exit;
            }

            // --- Ambil hour sesuai waktu aktual
            $stmt_hour = $conn->prepare("
                SELECT hour 
                FROM tbl_time 
                WHERE TIME(NOW()) BETWEEN start_hour AND end_hour
                ORDER BY id_time LIMIT 1
            ");
            $stmt_hour->execute();
            $res_hour = $stmt_hour->get_result();
            $hour_row = $res_hour->fetch_assoc();
            $hour = $hour_row['hour'] ?? null;
            $stmt_hour->close();

            // --- Ambil qty_real terakhir dari SCAN_IN_INCOMING
            $stmt_real = $conn->prepare("
                SELECT qty_real 
                FROM tlog_transaksi 
                WHERE id_trans = ? AND action_type = 'SCAN_IN_INCOMING'
                ORDER BY id_log_trans DESC LIMIT 1
            ");
            $stmt_real->bind_param("i", $id_trans);
            $stmt_real->execute();
            $res_real = $stmt_real->get_result();
            $real_row = $res_real->fetch_assoc();
            $stmt_real->close();

            $qty_real_json = $real_row['qty_real'] ?? json_encode([], JSON_UNESCAPED_UNICODE);

            // --- Update transaksi
            $stmt_upd = $conn->prepare("
                UPDATE tbl_transaksi
                SET type_scan = 'SCAN_OUT_TO_PRODUCTION',
                    scan_with = ?,
                    scan_at   = NOW(),
                    hour      = ?
                WHERE barcode = ?
            ");
            $stmt_upd->bind_param("sss", $scan_with, $hour, $barcode);
            $stmt_upd->execute();
            $stmt_upd->close();

            // --- Ambil data baru untuk new_data
            $stmt_new = $conn->prepare("SELECT * FROM tbl_transaksi WHERE barcode = ?");
            $stmt_new->bind_param("s", $barcode);
            $stmt_new->execute();
            $res_new = $stmt_new->get_result();
            $new_data_raw = $res_new->fetch_assoc();
            $stmt_new->close();

            $new_data = safeJson($new_data_raw);
            $new_data['type_scan'] = 'SCAN_OUT_TO_PRODUCTION';
            $json_new_data = json_encode($new_data, JSON_UNESCAPED_UNICODE);

            // --- Setup qty_kekurangan & status_kekurangan
            $qty_kekurangan_json = "0";
            $status_kekurangan = "CONFIRMED";

            // --- Insert log
            $stmt_log = $conn->prepare("
                INSERT INTO tlog_transaksi
                (id_trans, updated_by, action_type, old_data, new_data, qty_real, qty_kekurangan, status_kekurangan, created_at, updated_at)
                VALUES (?, ?, 'SCAN_OUT_TO_PRODUCTION', ?, ?, ?, ?, ?, NOW(), NOW())
            ");
            $stmt_log->bind_param(
                "issssss",
                $id_trans,
                $scan_with,
                $json_old_data,
                $json_new_data,
                $qty_real_json,
                $qty_kekurangan_json,
                $status_kekurangan
            );
            $stmt_log->execute();
            $stmt_log->close();

            $conn->commit();

            $_SESSION['green_notif'] = "Barcode berhasil di-scan (Scan Out to Production).";
            header("Location: /isubcont/pages/trans-scan-out-to-prod.php?success=$barcode");
            exit;
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['red_notif'] = "Gagal scan Barcode: " . $e->getMessage();
            header("Location: /isubcont/pages/trans-scan-out-to-prod.php");
            exit;
        }
    } else {
        $_SESSION['red_notif'] = "Barcode tidak boleh kosong.";
        header("Location: /isubcont/pages/trans-scan-out-to-prod.php");
        exit;
    }
}

// === Confirm Kekurangan ===
if (isset($_POST['action']) && $_POST['action'] === 'confirm_kekurangan') {

    function safe_redirect($type, $message, $redirect = '/isubcont/pages/trans-confrm-kekurangan.php')
    {
        $_SESSION[$type === 'success' ? 'green_notif' : 'red_notif'] = $message;
        header("Location: {$redirect}");
        exit;
    }

    try {
        $id_kekurangan = (int)($_POST['id_kekurangan'] ?? 0);
        $username      = $_SESSION['username'] ?? 'SYSTEM';

        if ($id_kekurangan <= 0) {
            safe_redirect('error', "ID kekurangan tidak valid.");
        }

        // 🔹 Ambil data kekurangan lama
        $stmt_old = $conn->prepare("SELECT * FROM tbl_transaksi_kekurangan WHERE id_kekurangan = ?");
        $stmt_old->bind_param("i", $id_kekurangan);
        $stmt_old->execute();
        $old_data = $stmt_old->get_result()->fetch_assoc();
        $stmt_old->close();

        if (!$old_data) safe_redirect('error', "Data kekurangan tidak ditemukan.");
        if (strtolower($old_data['status']) === 'confirmed') {
            safe_redirect('error', "Data ini sudah dikonfirmasi sebelumnya.");
        }

        $id_trans_asal = (int)($old_data['id_trans_asal'] ?? 0);
        $last_gate_existing = $old_data['last_gate'] ?? 'UNKNOWN'; // ← ini dipertahankan
        $lotArr = [];

        // 🔹 Ambil lot dari log asal (kalau ada)
        if ($id_trans_asal > 0) {
            $log_stmt = $conn->prepare("
                SELECT new_data 
                FROM tlog_transaksi 
                WHERE id_trans = ? 
                ORDER BY created_at DESC LIMIT 1
            ");
            $log_stmt->bind_param("i", $id_trans_asal);
            $log_stmt->execute();
            $log_res = $log_stmt->get_result()->fetch_assoc();
            $log_stmt->close();

            if ($log_res && $log_res['new_data']) {
                $log_json = json_decode($log_res['new_data'], true);
                if (isset($log_json['lot'])) {
                    $lotArr = is_array($log_json['lot']) ? $log_json['lot'] : json_decode($log_json['lot'], true);
                }
            }
        }

        // 🔹 Update status jadi confirmed — TAPI tidak ubah last_gate
        $stmt_upd = $conn->prepare("
            UPDATE tbl_transaksi_kekurangan 
            SET status = 'confirmed',
                updated_at = NOW()
            WHERE id_kekurangan = ? AND status = 'pending'
        ");
        $stmt_upd->bind_param("i", $id_kekurangan);
        $stmt_upd->execute();
        $stmt_upd->close();

        // 🔹 Update juga status_kekurangan di tlog_transaksi untuk gate yang sama
        if (!empty($id_trans_asal) && !empty($last_gate_existing)) {
            $stmt_upd_tlog = $conn->prepare("
                UPDATE tlog_transaksi
                SET status_kekurangan = 'confirmed',
                    updated_at = NOW()
                WHERE id_trans = ?
                AND action_type = ?
                AND (status_kekurangan = 'pending' OR status_kekurangan IS NULL)
            ");
            if ($stmt_upd_tlog) {
                $stmt_upd_tlog->bind_param("is", $id_trans_asal, $last_gate_existing);
                $stmt_upd_tlog->execute();
                $stmt_upd_tlog->close();
            }
        }

        // 🔹 Ambil ulang data setelah update
        $cek_stmt = $conn->prepare("SELECT * FROM tbl_transaksi_kekurangan WHERE id_kekurangan = ?");
        $cek_stmt->bind_param("i", $id_kekurangan);
        $cek_stmt->execute();
        $new_data = $cek_stmt->get_result()->fetch_assoc();
        $cek_stmt->close();

        if (!$new_data || strtolower($new_data['status']) !== 'confirmed') {
            safe_redirect('error', "Gagal mengupdate status (mungkin sudah dikonfirmasi).");
        }

        // 🔹 Tambahkan LOT ke komponen_qty untuk logging
        $kompArr = json_decode($new_data['komponen_qty'], true);
        if (!is_array($kompArr)) $kompArr = [];

        foreach ($kompArr as &$k) {
            if (!isset($k['lot'])) {
                $k['lot'] = $lotArr;
            }
        }
        unset($k);

        // Tetapkan last_gate tetap dari data existing
        $new_data['last_gate'] = $last_gate_existing;
        $new_data['komponen_qty'] = json_encode($kompArr, JSON_UNESCAPED_UNICODE);

        // 🔹 Simpan log konfirmasi (tlog_transaksi)
        $stmt_log = $conn->prepare("
            INSERT INTO tlog_transaksi
            (id_trans, updated_by, action_type, old_data, new_data, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, NOW(), NOW())
        ");
        if (!$stmt_log) safe_redirect('error', "Gagal prepare log: " . $conn->error);

        $action_type = 'CONFIRM_KEKURANGAN';
        $old_json = json_encode($old_data, JSON_UNESCAPED_UNICODE);
        $new_json = json_encode($new_data, JSON_UNESCAPED_UNICODE);

        $stmt_log->bind_param("issss", $id_trans_asal, $username, $action_type, $old_json, $new_json);
        $stmt_log->execute();
        $stmt_log->close();

        // ✅ Sukses
        safe_redirect('success', "Kekurangan berhasil dikonfirmasi.");
    } catch (Exception $e) {
        safe_redirect('error', "Terjadi kesalahan: " . $e->getMessage());
    }
}
