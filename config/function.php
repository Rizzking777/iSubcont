<?php
session_start(); // ready to go!

//Koneksi ke DBMS
$conn = mysqli_connect("localhost", "root", "", "db_subcont");
date_default_timezone_set('Asia/Jakarta');

// REGISTER USERS
if (isset($_POST['submit-user'])) {
    date_default_timezone_set('Asia/Jakarta');

    // Ambil dan filter data
    $updated_by = mysqli_real_escape_string($conn, $_POST['updated_by']);
    $username   = mysqli_real_escape_string($conn, $_POST['username']);
    $nik_user   = mysqli_real_escape_string($conn, $_POST['nik_user']);
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

    // Simpan ke tbl_user
    $query_user = mysqli_query($conn, "INSERT INTO tbl_user 
        (username, nik_user, pass_user, pass_plain, role_id, is_deleted, updated_by, timestamp) 
        VALUES 
        ('$username', '$nik_user', '$hashed_password', '$password', '$role_id', '0', '$updated_by', '$timestamp')");

    if ($query_user) {
        $last_user_id = mysqli_insert_id($conn);

        // Siapkan log (hanya simpan data baru)
        $new_data = [
            "username" => $username,
            "nik_user" => $nik_user,
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
    $expectedHeader = ['job_order', 'bucket', 'po_code', 'po_item', 'style', 'model', 'ncvs', 'qr_code', 'lot', 'size', 'qty'];
    $header = array_map('strtolower', $rows[0] ?? []);

    if ($header !== $expectedHeader) {
        $_SESSION['red_notif'] = "Upload gagal. Struktur file tidak sesuai template.";
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
            $job_order,
            $bucket,
            $po_code,
            $po_item,
            $style,
            $model,
            $ncvs,
            $qr_code,
            $lot,
            $size,
            $qty
        ) = $row;

        if (empty($job_order) || empty($po_code) || !is_numeric($qty)) {
            $failedRows++;
            continue;
        }

        $insertData[] = "('$job_order','$bucket','$po_code','$po_item',
                          '$style','$model','$ncvs','$qr_code', '$lot',
                          '$size','$qty')";
        $successRows++;
    }

    // --- Insert ke tabel utama (per batch 1000) ---
    if (!empty($insertData)) {
        $chunks = array_chunk($insertData, 1000);
        foreach ($chunks as $chunk) {
            $values = implode(',', $chunk);
            $sql = "INSERT INTO tbl_master_data 
                    (job_order, bucket, po_code, po_item, style, model, ncvs, qr_code, lot, size, qty) 
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
    $role_name   = mysqli_real_escape_string($conn, $_POST['role_name']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $timestamp  = date('Y-m-d H:i:s');

    // Cek apakah NIK sudah ada
    $check_role = mysqli_query($conn, "SELECT 1 FROM roles WHERE role_name = '$role_name'");
    if (mysqli_num_rows($check_role) > 0) {
        $_SESSION['red_notif'] = "Role sudah terdaftar, mohon ganti role lain.";
        header("Location: /isubcont/pages/master-role.php");
        exit();
    }

    // Simpan ke tbl_user
    $query_role = mysqli_query($conn, "INSERT INTO roles 
        (role_name, description, is_deleted, updated_by, timestamp) 
        VALUES 
        ('$role_name', '$description', '0', '$updated_by', '$timestamp')");

    if ($query_role) {
        $last_user_id = mysqli_insert_id($conn);

        // Siapkan log (hanya simpan data baru)
        $new_data = [
            "role_name" => $role_name,
            "description" => $description
        ];
        $new_data_json = mysqli_real_escape_string($conn, json_encode($new_data));

        $query_log = mysqli_query($conn, "INSERT INTO tlog_roles 
            (id, updated_by, action_type, old_data, new_data, created_at, updated_at) 
            VALUES 
            ('$last_user_id', '$updated_by', 'INSERT', NULL, '$new_data_json', NOW(), NOW())");

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

    // Ambil data lama untuk logging
    $stmt_old = $conn->prepare("SELECT role_name, description FROM roles WHERE id = ?");
    $stmt_old->bind_param("i", $id);
    $stmt_old->execute();
    $old_data = $stmt_old->get_result()->fetch_assoc();
    $old_data_json = json_encode($old_data, JSON_UNESCAPED_UNICODE);

    // Update roles
    $stmt_update = $conn->prepare("UPDATE roles 
                                   SET role_name = ?, description = ?, updated_by = ?, timestamp = ? 
                                   WHERE id = ?");
    $stmt_update->bind_param("ssssi", $role_name, $description, $updated_by, $timestamp, $id);

    if ($stmt_update->execute()) {
        // Siapkan data baru untuk logging
        $new_data = [
            "role_name"   => $role_name,
            "description" => $description
        ];
        $new_data_json = json_encode($new_data, JSON_UNESCAPED_UNICODE);

        // Insert log ke tlog_role
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
    $id        = $_POST['id'];
    $username  = $_SESSION['username'] ?? 'SYSTEM';

    // 1. Ambil data role
    $stmt = $conn->prepare("SELECT * FROM roles WHERE id = ? AND is_deleted = 0 LIMIT 1");
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

    $old_data_json = json_encode($role, JSON_UNESCAPED_UNICODE);

    // Simulasi data baru
    $role['is_deleted'] = 1;
    $new_data_json = json_encode($role, JSON_UNESCAPED_UNICODE);

    // 2. Update roles (soft delete)
    $stmt = $conn->prepare("UPDATE roles SET is_deleted = 1, updated_by = ?, timestamp = NOW() WHERE id = ?");
    $stmt->bind_param("si", $username, $id);
    $success = $stmt->execute();
    $stmt->close();

    if ($success) {
        // 3. Log ke tlog_role
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
    $id       = $_POST['id'];
    $username = $_SESSION['username'] ?? 'SYSTEM';

    $stmt = $conn->prepare("SELECT * FROM roles WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $role = $result->fetch_assoc();
    $stmt->close();

    if ($role && $role['is_deleted'] == 1) {
        $old_data_json = json_encode($role, JSON_UNESCAPED_UNICODE);

        // Update restore
        $stmt = $conn->prepare("UPDATE roles SET is_deleted = 0, updated_by = ?, timestamp = NOW() WHERE id = ?");
        $stmt->bind_param("si", $username, $id);
        $success = $stmt->execute();
        $stmt->close();

        if ($success) {
            $role['is_deleted'] = 0;
            $new_data_json = json_encode($role, JSON_UNESCAPED_UNICODE);

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
    $id       = $_POST['id'];
    $username = $_SESSION['username'] ?? 'SYSTEM';

    $stmt = $conn->prepare("SELECT * FROM roles WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $role = $result->fetch_assoc();
    $stmt->close();

    if ($role) {
        $old_data_json = json_encode($role, JSON_UNESCAPED_UNICODE);

        // DELETE permanen
        $stmt = $conn->prepare("DELETE FROM roles WHERE id = ?");
        $stmt->bind_param("i", $id);
        $success = $stmt->execute();
        $stmt->close();

        if ($success) {
            $new_data = [
                "note" => "Role dihapus permanen oleh {$username} pada " . date('Y-m-d H:i:s')
            ];
            $new_data_json = json_encode($new_data, JSON_UNESCAPED_UNICODE);

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

    // Aktifkan mysqli error reporting supaya mudah debug
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    $updated_by      = $_SESSION['username'];
    $model_input     = trim($_POST['model']);
    $style_input     = trim($_POST['style']);
    $input_komponen  = $_POST['input_komponen']; // array
    $output_komponen = trim($_POST['output_komponen']);
    $vendor_id       = !empty($_POST['vendor_id']) ? (int)$_POST['vendor_id'] : null; // vendor tunggal

    try {
        // Mulai transaksi
        $conn->begin_transaction();

        // Cari semua model mirip
        $stmt = $conn->prepare("SELECT DISTINCT model FROM tbl_master_data WHERE model LIKE CONCAT('%', ?, '%')");
        $stmt->bind_param("s", $model_input);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows == 0) {
            $models = [$model_input];
        } else {
            $models = [];
            while ($row = $result->fetch_assoc()) {
                $models[] = $row['model'];
            }
        }

        foreach ($models as $similar_model) {
            // Insert output komponen sekali saja
            $stmt_insert_output = $conn->prepare("
                INSERT INTO tbl_komponen (model, style, nama_komponen, is_deleted, updated_by, timestamp) 
                VALUES (?, ?, ?, 0, ?, NOW())
            ");
            $stmt_insert_output->bind_param("ssss", $similar_model, $style_input, $output_komponen, $updated_by);
            $stmt_insert_output->execute();
            $id_output = $stmt_insert_output->insert_id;

            foreach ($input_komponen as $komponen_in) {
                $komponen_in = trim($komponen_in);
                if (empty($komponen_in)) continue; // skip input kosong

                // Insert input komponen
                $stmt_insert_input = $conn->prepare("
                    INSERT INTO tbl_komponen (model, style, nama_komponen, is_deleted, updated_by, timestamp) 
                    VALUES (?, ?, ?, 0, ?, NOW())
                ");
                $stmt_insert_input->bind_param("ssss", $similar_model, $style_input, $komponen_in, $updated_by);
                $stmt_insert_input->execute();
                $id_input = $stmt_insert_input->insert_id;

                // Relasi input -> output (tbl_komponen_proses)
                $stmt_rel = $conn->prepare("
                    INSERT INTO tbl_komponen_proses (id_input, id_output) VALUES (?, ?)
                ");
                $stmt_rel->bind_param("ii", $id_input, $id_output);
                $stmt_rel->execute();
                $id_proses = $stmt_rel->insert_id; // untuk FK ke tbl_vendor_proses

                // Relasi vendor -> proses
                if ($vendor_id) {
                    $stmt_vendor = $conn->prepare("
                        INSERT INTO tbl_vendor_proses (id_vendor, id_proses, created_at, updated_at) 
                        VALUES (?, ?, NOW(), NOW())
                    ");
                    $stmt_vendor->bind_param("ii", $vendor_id, $id_proses);
                    $stmt_vendor->execute();
                }

                // Log per input
                $new_data = [
                    "model"    => $similar_model,
                    "style"    => $style_input,
                    "input"    => $komponen_in,
                    "output"   => $output_komponen,
                    "vendor"   => $vendor_id
                ];
                $new_data_json = json_encode($new_data, JSON_UNESCAPED_UNICODE);

                $stmt_log = $conn->prepare("
                    INSERT INTO tlog_komponen (id_komponen, updated_by, action_type, old_data, new_data, created_at, timestamp) 
                    VALUES (?, ?, 'INSERT', NULL, ?, NOW(), NOW())
                ");
                $stmt_log->bind_param("iss", $id_input, $updated_by, $new_data_json);
                $stmt_log->execute();
            }
        }

        // Commit transaksi jika semua berhasil
        $conn->commit();

        $_SESSION['green_notif'] = "Komponen, proses & vendor berhasil ditambahkan untuk semua model mirip.";
        header("Location: /isubcont/pages/master-komponen.php");
        exit;
    } catch (Exception $e) {
        $conn->rollback(); // rollback semua jika gagal
        die("Gagal insert komponen: " . $e->getMessage());
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
    function softDeleteAndLog($conn, $id_komponen, $old_data, $updated_by, $vendor_names) {
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
    function restoreAndLog($conn, $id_komponen, $updated_by, $vendor_names) {
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
    function forceDeleteAndLog($conn, $id_komponen, $updated_by, $vendor_names, $action_type = 'DELETE') {
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

if (isset($_POST['submit-transaksi'])) {
    $created_by = $_SESSION['username'] ?? 'unknown';

    $job_order  = $_POST['job_order'];
    $bucket     = $_POST['bucket'];
    $po_code    = $_POST['po_code'];
    $po_item    = $_POST['po_item'];
    $model      = $_POST['model'];
    $style      = $_POST['style'];
    $ncvs       = $_POST['ncvs'];
    $lot_input  = $_POST['lot'];

    $komponen   = $_POST['komponen']; // array
    $size       = $_POST['size'];     // array
    $qty        = $_POST['qty'];      // array

    // === Validasi panjang array ===
    if (!(count($komponen) === count($size) && count($size) === count($qty))) {
        $_SESSION['red_notif'] = "Jumlah komponen, size, dan qty tidak konsisten.";
        header("Location: /isubcont/pages/trans-barcode.php");
        exit;
    }

    // === Parsing LOT input ===
    function parseLotInput($input)
    {
        $lots = [];
        $parts = explode(",", $input);
        foreach ($parts as $part) {
            $part = trim($part);
            if (strpos($part, "-") !== false) {
                [$start, $end] = array_map('intval', explode("-", $part));
                for ($i = $start; $i <= $end; $i++) {
                    $lots[] = $i;
                }
            } elseif ($part !== '') {
                $lots[] = (int)$part;
            }
        }
        return array_values(array_unique($lots));
    }

    $lots = parseLotInput($lot_input);
    $lot_json = json_encode($lots);

    // === Validasi LOT dari master_data ===
    $sql_valid_lot = "
        SELECT DISTINCT lot 
        FROM tbl_master_data 
        WHERE job_order = ? AND bucket = ? AND po_code = ? 
          AND po_item = ? AND model = ? AND style = ?
    ";
    $stmt_valid_lot = $conn->prepare($sql_valid_lot);
    $stmt_valid_lot->bind_param("ssssss", $job_order, $bucket, $po_code, $po_item, $model, $style);
    $stmt_valid_lot->execute();
    $res_valid_lot = $stmt_valid_lot->get_result();

    $valid_lots = [];
    while ($row = $res_valid_lot->fetch_assoc()) {
        $valid_lots[] = (int)$row['lot'];
    }

    foreach ($lots as $lt) {
        if (!in_array($lt, $valid_lots, true)) {
            $_SESSION['red_notif'] = "Lot $lt tidak valid untuk Job Order $job_order.";
            header("Location: /isubcont/pages/trans-barcode.php");
            exit;
        }
    }

    // === Susun komponen + size + qty jadi JSON ===
    $komponen_qty = [];
    for ($i = 0; $i < count($komponen); $i++) {
        $komponen_qty[] = [
            'komponen' => $komponen[$i],
            'size'     => $size[$i],
            'qty'      => (int) $qty[$i],
        ];
    }
    $komponen_qty_json = json_encode($komponen_qty);

    // === Validasi Duplicate Transaksi (pakai nama komponen + size) ===
    for ($i = 0; $i < count($komponen); $i++) {
        $idKomponen = $komponen[$i];
        $sz         = $size[$i];

        // ambil nama komponen dari tabel master
        $stmt_k = $conn->prepare("SELECT nama_komponen FROM tbl_komponen WHERE id_komponen = ?");
        $stmt_k->bind_param("i", $idKomponen);
        $stmt_k->execute();
        $row_k = $stmt_k->get_result()->fetch_assoc();
        $namaKomponen = $row_k['nama_komponen'] ?? "Unknown";

        $sql_dup = "
        SELECT COUNT(*) as cnt
        FROM tbl_transaksi
        WHERE job_order = ?
          AND bucket = ?
          AND po_code = ?
          AND po_item = ?
          AND model = ?
          AND style = ?
          AND lot = ?
          AND komponen_qty LIKE ?
    ";

        $stmt_dup = $conn->prepare($sql_dup);
        $likePattern = '%"komponen":"' . $idKomponen . '","size":"' . $sz . '"%';
        $stmt_dup->bind_param("ssssssss", $job_order, $bucket, $po_code, $po_item, $model, $style, $lot_json, $likePattern);
        $stmt_dup->execute();
        $res_dup = $stmt_dup->get_result()->fetch_assoc();

        if ($res_dup['cnt'] > 0) {
            $_SESSION['red_notif'] = "Transaksi ditolak. <br>
            Job Order: <b>$job_order</b><br>
            Komponen: <b>$namaKomponen</b><br>
            Size: <b>$sz</b><br>
            Lot: <b>$lot_input</b> <br>
            sudah pernah dibuat QR sebelumnya.";
            header("Location: /isubcont/pages/trans-barcode.php");
            exit;
        }
    }

    // Default field
    $type_scan = "CREATE_BARCODE";
    $status    = "PENDING";

    // === Ambil total_order per size dari master_data ===
    $placeholders = implode(",", array_fill(0, count($lots), "?"));
    $types = str_repeat("i", count($lots));
    $sql_total = "
        SELECT size, SUM(qty) as total_order
        FROM tbl_master_data
        WHERE job_order = ?
          AND bucket = ?
          AND po_code = ?
          AND po_item = ?
          AND model = ?
          AND style = ?
          AND lot IN ($placeholders)
        GROUP BY size
    ";

    $stmt_total = $conn->prepare($sql_total);
    $bind_types = "ssssss" . $types;
    $bind_params = [$job_order, $bucket, $po_code, $po_item, $model, $style];
    $bind_params = array_merge($bind_params, $lots);
    $stmt_total->bind_param($bind_types, ...$bind_params);
    $stmt_total->execute();
    $res_total = $stmt_total->get_result();

    $total_order_per_size = [];
    while ($row = $res_total->fetch_assoc()) {
        $total_order_per_size[$row['size']] = (int)$row['total_order'];
    }

    // === Hitung total_used per size dari transaksi sebelumnya ===
    $sql_used = "
        SELECT komponen_qty
        FROM tbl_transaksi
        WHERE job_order = ?
          AND bucket = ?
          AND po_code = ?
          AND po_item = ?
          AND model = ?
          AND style = ?
          AND lot = ?
    ";
    $stmt_used = $conn->prepare($sql_used);
    $stmt_used->bind_param("sssssss", $job_order, $bucket, $po_code, $po_item, $model, $style, $lot_json);
    $stmt_used->execute();
    $res_used = $stmt_used->get_result();

    $total_used_per_size = [];
    if ($res_used && $res_used->num_rows > 0) {
        while ($row_used = $res_used->fetch_assoc()) {
            $arr_used = json_decode($row_used['komponen_qty'], true);
            if ($arr_used && is_array($arr_used)) {
                foreach ($arr_used as $u) {
                    $sz = $u['size'];
                    $total_used_per_size[$sz] = ($total_used_per_size[$sz] ?? 0) + (int)$u['qty'];
                }
            }
        }
    }

    // === Hitung total input baru per size ===
    $new_input_per_size = [];
    for ($i = 0; $i < count($size); $i++) {
        $sz = $size[$i];
        $new_input_per_size[$sz] = ($new_input_per_size[$sz] ?? 0) + (int)$qty[$i];
    }

    // === Validasi per-size ===
    foreach ($new_input_per_size as $sz => $val) {
        $allowed = $total_order_per_size[$sz] ?? 0;
        $used    = $total_used_per_size[$sz] ?? 0;
        if (($used + $val) > $allowed) {
            $_SESSION['red_notif'] = "Transaksi ditolak. Size $sz melebihi kapasitas. (used $used + new $val > total $allowed)";
            header("Location: /isubcont/pages/trans-barcode.php");
            exit;
        }
    }

    // === Cari hour sesuai waktu aktual ===
    $stmt_hour = $conn->prepare("
        SELECT id_time, hour 
        FROM tbl_time 
        WHERE TIME(NOW()) BETWEEN start_hour AND end_hour
        ORDER BY id_time LIMIT 1
    ");
    $stmt_hour->execute();
    $res_hour = $stmt_hour->get_result();
    $hour_row = $res_hour->fetch_assoc();
    $hour = $hour_row['hour'] ?? null;

    $conn->begin_transaction();
    try {
        // === Insert transaksi ===
        $stmt_insert = $conn->prepare("
            INSERT INTO tbl_transaksi
            (job_order, bucket, po_code, po_item, model, style, ncvs,
             lot, komponen_qty, type_scan, created_by, date_created, status, hour)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?)
        ");
        $stmt_insert->bind_param(
            "sssssssssssss",
            $job_order,
            $bucket,
            $po_code,
            $po_item,
            $model,
            $style,
            $ncvs,
            $lot_json,
            $komponen_qty_json,
            $type_scan,
            $created_by,
            $status,
            $hour
        );
        $stmt_insert->execute();
        $id_trans = $conn->insert_id;

        // === Generate barcode unik ===
        $barcode = "{$po_code}-{$po_item}-{$ncvs}-" . date('YmdHis') . "-{$id_trans}";
        $stmt_update = $conn->prepare("UPDATE tbl_transaksi SET barcode = ? WHERE id_trans = ?");
        $stmt_update->bind_param("si", $barcode, $id_trans);
        $stmt_update->execute();

        // === Logging ===
        $new_data = [
            'id_trans'     => $id_trans,
            'job_order'    => $job_order,
            'bucket'       => $bucket,
            'po_code'      => $po_code,
            'po_item'      => $po_item,
            'model'        => $model,
            'style'        => $style,
            'ncvs'         => $ncvs,
            'lot'          => $lots,
            'komponen_qty' => $komponen_qty,
            'barcode'      => $barcode,
            'status'       => $status,
            'hour'         => $hour
        ];
        $json_new_data = json_encode($new_data);

        $stmt_log = $conn->prepare("
            INSERT INTO tlog_transaksi 
            (id_trans, updated_by, action_type, old_data, new_data, created_at, updated_at)
            VALUES (?, ?, 'INSERT', NULL, ?, NOW(), NOW())
        ");
        $stmt_log->bind_param("iss", $id_trans, $created_by, $json_new_data);
        $stmt_log->execute();

        $conn->commit();
        $_SESSION['green_notif'] = "Transaksi berhasil ditambahkan (QR Code: $barcode)";
        header("Location: /isubcont/pages/trans-barcode.php");
        exit;
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['red_notif'] = "Terjadi kesalahan: " . $e->getMessage();
        header("Location: /isubcont/pages/trans-barcode.php");
        exit;
    }
}

// === Approve / Reject Transaksi ===
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

// === Scan In Warehouse ===
if (isset($_POST['scan-barcode'])) {
    $barcode   = $_POST['barcode'] ?? null;
    $scan_with = $_SESSION['username'] ?? 'unknown';

    if ($barcode) {
        $conn->begin_transaction();
        try {
            // --- Flow urutan scan
            $scan_flow = [
                "SCAN_IN_WAREHOUSE",
                "SCAN_OUT_TO_VENDOR",
                "SCAN_IN_VENDOR",
                "SCAN_OUT_VENDOR",
                "SCAN_IN_INCOMING",
                "SCAN_CHECK_QC",
                "SCAN_OUT_TO_PRODUCTION"
            ];

            // --- Ambil data lama
            $stmt_old = $conn->prepare("SELECT * FROM tbl_transaksi WHERE barcode = ?");
            $stmt_old->bind_param("s", $barcode);
            $stmt_old->execute();
            $res_old = $stmt_old->get_result();
            $old_data = $res_old->fetch_assoc();

            if (!$old_data) {
                $_SESSION['red_notif'] = "QR Code $barcode tidak ditemukan.";
                header("Location: /isubcont/pages/trans-scan-in-wh.php");
                exit;
            }

            $json_old_data = json_encode($old_data, JSON_UNESCAPED_UNICODE);

            // --- Validasi urutan scan
            $current_state = strtoupper($old_data['type_scan'] ?? '');
            $current_index = array_search($current_state, $scan_flow);

            // Kalau belum pernah scan (NULL), posisikan index = -1
            if ($current_index === false) {
                $current_index = -1;
            }

            $next_state = $scan_flow[$current_index + 1] ?? null;

            if ($next_state !== "SCAN_IN_WAREHOUSE") {
                $_SESSION['red_notif'] = "QR Code tidak bisa di-scan di tahap ini. 
                    Current: $current_state, Next workflow: $next_state";
                header("Location: /isubcont/pages/trans-scan-in-wh.php");
                exit;
            }

            // --- Cari hour sesuai waktu aktual
            $stmt_hour = $conn->prepare("
                SELECT id_time, hour 
                FROM tbl_time 
                WHERE TIME(NOW()) BETWEEN start_hour AND end_hour
                ORDER BY id_time LIMIT 1
            ");
            $stmt_hour->execute();
            $res_hour = $stmt_hour->get_result();
            $hour_row = $res_hour->fetch_assoc();
            $hour = $hour_row['hour'] ?? null;

            // --- Update transaksi
            $stmt_upd = $conn->prepare("
                UPDATE tbl_transaksi
                SET type_scan = 'SCAN_IN_WAREHOUSE',
                    scan_with = ?,
                    scan_at   = NOW(),
                    hour      = ?
                WHERE barcode = ?
            ");
            $stmt_upd->bind_param("sis", $scan_with, $hour, $barcode);
            $stmt_upd->execute();

            // --- Ambil data baru
            $stmt_new = $conn->prepare("SELECT * FROM tbl_transaksi WHERE barcode = ?");
            $stmt_new->bind_param("s", $barcode);
            $stmt_new->execute();
            $res_new = $stmt_new->get_result();
            $new_data = $res_new->fetch_assoc();
            $json_new_data = $new_data ? json_encode($new_data, JSON_UNESCAPED_UNICODE) : null;

            // --- Insert log
            $stmt_log = $conn->prepare("
                INSERT INTO tlog_transaksi
                (id_trans, updated_by, action_type, old_data, new_data, created_at, updated_at)
                VALUES (?, ?, 'SCAN_IN_WAREHOUSE', ?, ?, NOW(), NOW())
            ");
            $id_trans = (int)$old_data['id_trans'];
            $stmt_log->bind_param("isss", $id_trans, $scan_with, $json_old_data, $json_new_data);
            $stmt_log->execute();

            $conn->commit();

            $_SESSION['green_notif'] = "QR Code berhasil di-scan (Scan In Warehouse).";
            header("Location: /isubcont/pages/trans-scan-in-wh.php?success=$barcode");
            exit;
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['red_notif'] = "Gagal scan QR Code: " . $e->getMessage();
            header("Location: /isubcont/pages/trans-scan-in-wh.php");
            exit;
        }
    } else {
        $_SESSION['red_notif'] = "QR Code tidak boleh kosong.";
        header("Location: /isubcont/pages/trans-scan-in-wh.php");
        exit;
    }
}

// === Scan Out to Vendor ===
if (isset($_POST['scan-out-to-vendor'])) {
    $barcode   = $_POST['barcode'] ?? null;
    $scan_with = $_SESSION['username'] ?? 'unknown';

    if ($barcode) {
        $conn->begin_transaction();
        try {
            // --- Flow urutan scan
            $scan_flow = [
                "SCAN_IN_WAREHOUSE",
                "SCAN_OUT_TO_VENDOR",
                "SCAN_IN_VENDOR",
                "SCAN_OUT_VENDOR",
                "SCAN_IN_INCOMING",
                "SCAN_CHECK_QC",
                "SCAN_OUT_TO_PRODUCTION"
            ];

            // --- Ambil data lama
            $stmt_old = $conn->prepare("SELECT * FROM tbl_transaksi WHERE barcode = ?");
            $stmt_old->bind_param("s", $barcode);
            $stmt_old->execute();
            $res_old = $stmt_old->get_result();
            $old_data = $res_old->fetch_assoc();

            if (!$old_data) {
                $_SESSION['red_notif'] = "QR Code $barcode tidak ditemukan.";
                header("Location: /isubcont/pages/trans-scan-out-to-vendor.php");
                exit;
            }

            $json_old_data = json_encode($old_data, JSON_UNESCAPED_UNICODE);

            // --- Validasi urutan scan
            $current_state = strtoupper($old_data['type_scan'] ?? '');
            $current_index = array_search($current_state, $scan_flow);

            // Kalau belum pernah scan (NULL), posisikan index = -1
            if ($current_index === false) {
                $current_index = -1;
            }

            $next_state = $scan_flow[$current_index + 1] ?? null;

            if ($next_state !== "SCAN_OUT_TO_VENDOR") {
                $_SESSION['red_notif'] = "QR Code tidak bisa di-scan di tahap ini. 
                    Current: $current_state, Next workflow: $next_state";
                header("Location: /isubcont/pages/trans-scan-out-to-vendor.php");
                exit;
            }

            // --- Cari hour sesuai waktu aktual
            $stmt_hour = $conn->prepare("
                SELECT id_time, hour 
                FROM tbl_time 
                WHERE TIME(NOW()) BETWEEN start_hour AND end_hour
                ORDER BY id_time LIMIT 1
            ");
            $stmt_hour->execute();
            $res_hour = $stmt_hour->get_result();
            $hour_row = $res_hour->fetch_assoc();
            $hour = $hour_row['hour'] ?? null;

            // --- Update transaksi
            $stmt_upd = $conn->prepare("
                UPDATE tbl_transaksi
                SET type_scan = 'SCAN_OUT_TO_VENDOR',
                    scan_with = ?,
                    scan_at   = NOW(),
                    hour      = ?
                WHERE barcode = ?
            ");
            $stmt_upd->bind_param("sis", $scan_with, $hour, $barcode);
            $stmt_upd->execute();

            // --- Ambil data baru
            $stmt_new = $conn->prepare("SELECT * FROM tbl_transaksi WHERE barcode = ?");
            $stmt_new->bind_param("s", $barcode);
            $stmt_new->execute();
            $res_new = $stmt_new->get_result();
            $new_data = $res_new->fetch_assoc();
            $json_new_data = $new_data ? json_encode($new_data, JSON_UNESCAPED_UNICODE) : null;

            // --- Insert log
            $stmt_log = $conn->prepare("
                INSERT INTO tlog_transaksi
                (id_trans, updated_by, action_type, old_data, new_data, created_at, updated_at)
                VALUES (?, ?, 'SCAN_OUT_TO_VENDOR', ?, ?, NOW(), NOW())
            ");
            $id_trans = (int)$old_data['id_trans'];
            $stmt_log->bind_param("isss", $id_trans, $scan_with, $json_old_data, $json_new_data);
            $stmt_log->execute();

            $conn->commit();

            $_SESSION['green_notif'] = "QR Code berhasil di-scan (Scan Out to Vendor).";
            header("Location: /isubcont/pages/trans-scan-out-to-vendor.php?success=$barcode");
            exit;
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['red_notif'] = "Gagal scan QR Code: " . $e->getMessage();
            header("Location: /isubcont/pages/trans-scan-out-to-vendor.php");
            exit;
        }
    } else {
        $_SESSION['red_notif'] = "QR Code tidak boleh kosong.";
        header("Location: /isubcont/pages/trans-scan-out-to-vendor.php");
        exit;
    }
}

// === Confirm In Vendor ===
if (isset($_POST['confirm-in-vendor'])) {
    $barcode   = $_POST['barcode'] ?? null;
    $qty_data = $_POST['qty'] ?? []; // qty[id_komponen][size] = qty

    $qty_array = [];
    foreach ($qty_data as $id_komponen => $sizes) {
        foreach ($sizes as $size => $qty) {
            $qty_array[] = [
                "komponen" => $id_komponen,
                "size"     => $size,
                "qty"      => (int)$qty
            ];
        }
    }

    $qty_json = json_encode($qty_array, JSON_UNESCAPED_UNICODE);

    $scan_with = $_SESSION['username'] ?? 'unknown';

    if ($barcode) {
        $conn->begin_transaction();
        try {
            // --- Flow urutan scan
            $scan_flow = [
                "SCAN_IN_WAREHOUSE",
                "SCAN_OUT_TO_VENDOR",
                "SCAN_IN_VENDOR",
                "SCAN_OUT_VENDOR",
                "SCAN_IN_INCOMING",
                "SCAN_CHECK_QC",
                "SCAN_OUT_TO_PRODUCTION"
            ];

            // --- Ambil data lama
            $stmt_old = $conn->prepare("SELECT * FROM tbl_transaksi WHERE barcode = ?");
            $stmt_old->bind_param("s", $barcode);
            $stmt_old->execute();
            $res_old = $stmt_old->get_result();
            $old_data = $res_old->fetch_assoc();

            if (!$old_data) {
                $_SESSION['red_notif'] = "QR Code $barcode tidak ditemukan.";
                header("Location: /isubcont/pages/trans-scan-in-vendor.php");
                exit;
            }

            $json_old_data = json_encode($old_data, JSON_UNESCAPED_UNICODE);

            // --- Validasi urutan scan
            $current_state = strtoupper($old_data['type_scan'] ?? '');
            $current_index = array_search($current_state, $scan_flow);

            if ($current_index === false) {
                $current_index = -1;
            }

            $next_state = $scan_flow[$current_index + 1] ?? null;

            if ($next_state !== "SCAN_IN_VENDOR") {
                $_SESSION['red_notif'] = "QR Code tidak bisa di-scan di tahap ini. 
                    Current: $current_state, Next workflow: $next_state";
                header("Location: /isubcont/pages/trans-scan-in-vendor.php");
                exit;
            }

            // --- Cari hour sesuai waktu aktual
            $stmt_hour = $conn->prepare("
                SELECT id_time, hour 
                FROM tbl_time 
                WHERE TIME(NOW()) BETWEEN start_hour AND end_hour
                ORDER BY id_time LIMIT 1
            ");
            $stmt_hour->execute();
            $res_hour = $stmt_hour->get_result();
            $hour_row = $res_hour->fetch_assoc();
            $hour = $hour_row['hour'] ?? null;

            // --- Update transaksi
            $stmt_upd = $conn->prepare("
                UPDATE tbl_transaksi
                SET type_scan    = 'SCAN_IN_VENDOR',
                    komponen_qty = ?,
                    scan_with    = ?,
                    scan_at      = NOW(),
                    hour         = ?
                WHERE barcode = ?
");
            $stmt_upd->bind_param("ssis", $qty_json, $scan_with, $hour, $barcode);

            $stmt_upd->execute();

            // --- Ambil data baru
            $stmt_new = $conn->prepare("SELECT * FROM tbl_transaksi WHERE barcode = ?");
            $stmt_new->bind_param("s", $barcode);
            $stmt_new->execute();
            $res_new = $stmt_new->get_result();
            $new_data = $res_new->fetch_assoc();
            $json_new_data = $new_data ? json_encode($new_data, JSON_UNESCAPED_UNICODE) : null;

            // --- Insert log
            $stmt_log = $conn->prepare("
                INSERT INTO tlog_transaksi
                (id_trans, updated_by, action_type, old_data, new_data, created_at, updated_at)
                VALUES (?, ?, 'SCAN_IN_VENDOR', ?, ?, NOW(), NOW())
            ");
            $id_trans = (int)$old_data['id_trans'];
            $stmt_log->bind_param("isss", $id_trans, $scan_with, $json_old_data, $json_new_data);
            $stmt_log->execute();

            $conn->commit();

            $_SESSION['green_notif'] = "QR Code berhasil di-scan (Scan In Vendor).";
            header("Location: /isubcont/pages/trans-scan-in-vendor.php?success=$barcode");
            exit;
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['red_notif'] = "Gagal confirm: " . $e->getMessage();
            header("Location: /isubcont/pages/trans-scan-in-vendor.php");
            exit;
        }
    } else {
        $_SESSION['red_notif'] = "QR Code tidak boleh kosong.";
        header("Location: /isubcont/pages/trans-scan-in-vendor.php");
        exit;
    }
}

// === Pending In Vendor ===
if (isset($_POST['pending-in-vendor'])) {
    $barcode     = $_POST['barcode'] ?? null;
    $qty_data    = $_POST['qty'] ?? []; // array komponen => qty
    $keterangan  = $_POST['keterangan'] ?? null;
    $scan_with   = $_SESSION['username'] ?? 'unknown';

    if ($barcode) {
        $conn->begin_transaction();
        try {
            // --- Flow urutan scan
            $scan_flow = [
                "SCAN_IN_WAREHOUSE",
                "SCAN_OUT_TO_VENDOR",
                "SCAN_IN_VENDOR",
                "SCAN_OUT_VENDOR",
                "SCAN_IN_INCOMING",
                "SCAN_CHECK_QC",
                "SCAN_OUT_TO_PRODUCTION"
            ];

            // --- Ambil data lama
            $stmt_old = $conn->prepare("SELECT * FROM tbl_transaksi WHERE barcode = ?");
            $stmt_old->bind_param("s", $barcode);
            $stmt_old->execute();
            $res_old = $stmt_old->get_result();
            $old_data = $res_old->fetch_assoc();
            $stmt_old->close();

            if (!$old_data) {
                $_SESSION['red_notif'] = "QR Code $barcode tidak ditemukan.";
                header("Location: /isubcont/pages/trans-scan-in-vendor.php");
                exit;
            }

            $json_old_data = json_encode($old_data, JSON_UNESCAPED_UNICODE);

            // --- Validasi urutan scan
            $current_state = strtoupper($old_data['type_scan'] ?? '');
            $current_index = array_search($current_state, $scan_flow);
            if ($current_index === false) $current_index = -1;

            $next_state = $scan_flow[$current_index + 1] ?? null;
            if ($next_state !== "SCAN_IN_VENDOR") {
                $_SESSION['red_notif'] = "QR Code tidak bisa di-scan di tahap ini. 
                    Current: $current_state, Next workflow: $next_state";
                header("Location: /isubcont/pages/trans-scan-in-vendor.php");
                exit;
            }

            // --- Decode komponen lama untuk validasi
            $old_kq_raw = $old_data['komponen_qty'] ?? '[]';
            $old_kq = is_string($old_kq_raw) ? json_decode($old_kq_raw, true) : (is_array($old_kq_raw) ? $old_kq_raw : []);
            if (!is_array($old_kq)) $old_kq = [];

            // Buat map qty lama dengan key = komponen|size
            $map_old_qty = [];
            foreach ($old_kq as $it) {
                $k = (int)($it['komponen'] ?? 0) . '|' . ($it['size'] ?? '');
                $map_old_qty[$k] = (int)($it['qty'] ?? 0);
            }

            // --- Validasi: qty input tidak boleh melebihi qty lama per komponen+size
            foreach ($qty_data as $id_komponen => $sizes) {
                foreach ($sizes as $size => $new_qty) {
                    $key = (int)$id_komponen . '|' . $size;
                    $old_qty = $map_old_qty[$key] ?? 0;
                    $new_qty = (int)$new_qty;

                    if ($new_qty > $old_qty) {
                        $conn->rollback();
                        $_SESSION['red_notif'] = "Qty (size $size) melebihi jumlah aslinya ($old_qty).";
                        header("Location: /isubcont/pages/trans-scan-in-vendor.php");
                        exit;
                    }
                }
            }

            // --- Build JSON untuk simpan (dengan komponen + size + qty)
            $qty_array = [];
            foreach ($qty_data as $id_komponen => $sizes) {
                foreach ($sizes as $size => $qty) {
                    $qty_array[] = [
                        "komponen" => (int)$id_komponen,
                        "size"     => $size,
                        "qty"      => (int)$qty
                    ];
                }
            }
            $qty_json = json_encode($qty_array, JSON_UNESCAPED_UNICODE);

            // --- Cari hour sesuai waktu aktual
            $stmt_hour = $conn->prepare("
                SELECT id_time, hour 
                FROM tbl_time 
                WHERE TIME(NOW()) BETWEEN start_hour AND end_hour
                ORDER BY id_time LIMIT 1
            ");
            $stmt_hour->execute();
            $res_hour = $stmt_hour->get_result();
            $hour_row = $res_hour->fetch_assoc();
            $hour = $hour_row['hour'] ?? null;

            // --- Update transaksi jadi Pending dengan type_scan SCAN_IN_VENDOR
            $stmt_upd = $conn->prepare("
                UPDATE tbl_transaksi
                SET type_scan    = 'SCAN_IN_VENDOR',
                    status       = 'QTY_TIDAK_SESUAI',
                    komponen_qty = ?,
                    keterangan   = ?,
                    scan_with    = ?,
                    scan_at      = NOW(),
                    hour         = ?
                WHERE barcode = ?
            ");
            $stmt_upd->bind_param("sssis", $qty_json, $keterangan, $scan_with, $hour, $barcode);
            $stmt_upd->execute();

            // --- Ambil data baru
            $stmt_new = $conn->prepare("SELECT * FROM tbl_transaksi WHERE barcode = ?");
            $stmt_new->bind_param("s", $barcode);
            $stmt_new->execute();
            $res_new = $stmt_new->get_result();
            $new_data = $res_new->fetch_assoc();
            $json_new_data = $new_data ? json_encode($new_data, JSON_UNESCAPED_UNICODE) : null;

            // --- Insert log
            $stmt_log = $conn->prepare("
                INSERT INTO tlog_transaksi
                (id_trans, updated_by, action_type, old_data, new_data, created_at, updated_at)
                VALUES (?, ?, 'QTY_TIDAK_SESUAI', ?, ?, NOW(), NOW())
            ");
            $id_trans = (int)$old_data['id_trans'];
            $stmt_log->bind_param("isss", $id_trans, $scan_with, $json_old_data, $json_new_data);
            $stmt_log->execute();

            $conn->commit();

            $_SESSION['green_notif'] = "QR Code berhasil di-scan (Scan In Vendor). Status : Quantity tidak sesuai";
            header("Location: /isubcont/pages/trans-scan-in-vendor.php?success=$barcode");
            exit;
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['red_notif'] = "Gagal simpan pending: " . $e->getMessage();
            header("Location: /isubcont/pages/trans-scan-in-vendor.php");
            exit;
        }
    } else {
        $_SESSION['red_notif'] = "QR Code tidak boleh kosong.";
        header("Location: /isubcont/pages/trans-scan-in-vendor.php");
        exit;
    }
}

// === Confirm Out Vendor ===
if (isset($_POST['confirm-out-vendor'])) {
    $barcode   = $_POST['barcode'] ?? null;
    $qty_data = $_POST['qty'] ?? []; // array komponen => qty
    $qty_array = [];
    foreach ($qty_data as $id_komponen => $sizes) {
        foreach ($sizes as $size => $qty) {
            $qty_array[] = [
                "komponen" => $id_komponen,
                "size"     => $size,
                "qty"      => (int)$qty
            ];
        }
    }

    $qty_json = json_encode($qty_array, JSON_UNESCAPED_UNICODE);

    $scan_with = $_SESSION['username'] ?? 'unknown';

    if ($barcode) {
        $conn->begin_transaction();
        try {
            // --- Flow urutan scan
            $scan_flow = [
                "SCAN_IN_WAREHOUSE",
                "SCAN_OUT_TO_VENDOR",
                "SCAN_IN_VENDOR",
                "SCAN_OUT_VENDOR",
                "SCAN_IN_INCOMING",
                "SCAN_CHECK_QC",
                "SCAN_OUT_TO_PRODUCTION"
            ];

            // --- Ambil data lama
            $stmt_old = $conn->prepare("SELECT * FROM tbl_transaksi WHERE barcode = ?");
            $stmt_old->bind_param("s", $barcode);
            $stmt_old->execute();
            $res_old = $stmt_old->get_result();
            $old_data = $res_old->fetch_assoc();

            if (!$old_data) {
                $_SESSION['red_notif'] = "QR Code $barcode tidak ditemukan.";
                header("Location: /isubcont/pages/trans-scan-out-vendor.php");
                exit;
            }

            $json_old_data = json_encode($old_data, JSON_UNESCAPED_UNICODE);

            // --- Validasi urutan scan
            $current_state = strtoupper($old_data['type_scan'] ?? '');
            $current_index = array_search($current_state, $scan_flow);
            if ($current_index === false) $current_index = -1;

            $next_state = $scan_flow[$current_index + 1] ?? null;

            if ($next_state !== "SCAN_OUT_VENDOR") {
                $_SESSION['red_notif'] = "QR Code tidak bisa di-scan di tahap ini. 
                    Current: $current_state, Next workflow: $next_state";
                header("Location: /isubcont/pages/trans-scan-out-vendor.php");
                exit;
            }

            // --- Cari hour sesuai waktu aktual
            $stmt_hour = $conn->prepare("
                SELECT id_time, hour 
                FROM tbl_time 
                WHERE TIME(NOW()) BETWEEN start_hour AND end_hour
                ORDER BY id_time LIMIT 1
            ");
            $stmt_hour->execute();
            $res_hour = $stmt_hour->get_result();
            $hour_row = $res_hour->fetch_assoc();
            $hour = $hour_row['hour'] ?? null;

            // --- Update transaksi
            $stmt_upd = $conn->prepare("
                UPDATE tbl_transaksi
                SET type_scan    = 'SCAN_OUT_VENDOR',
                    komponen_qty = ?,
                    scan_with    = ?,
                    scan_at      = NOW(),
                    hour         = ?
                WHERE barcode = ?
            ");
            $stmt_upd->bind_param("ssis", $qty_json, $scan_with, $hour, $barcode);
            $stmt_upd->execute();

            // --- Ambil data baru
            $stmt_new = $conn->prepare("SELECT * FROM tbl_transaksi WHERE barcode = ?");
            $stmt_new->bind_param("s", $barcode);
            $stmt_new->execute();
            $res_new = $stmt_new->get_result();
            $new_data = $res_new->fetch_assoc();
            $json_new_data = $new_data ? json_encode($new_data, JSON_UNESCAPED_UNICODE) : null;

            // --- Insert log
            $stmt_log = $conn->prepare("
                INSERT INTO tlog_transaksi
                (id_trans, updated_by, action_type, old_data, new_data, created_at, updated_at)
                VALUES (?, ?, 'SCAN_OUT_VENDOR', ?, ?, NOW(), NOW())
            ");
            $id_trans = (int)$old_data['id_trans'];
            $stmt_log->bind_param("isss", $id_trans, $scan_with, $json_old_data, $json_new_data);
            $stmt_log->execute();

            $conn->commit();

            $_SESSION['green_notif'] = "QR Code berhasil di-scan (Scan Out Vendor).";
            header("Location: /isubcont/pages/trans-scan-out-vendor.php?success=$barcode");
            exit;
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['red_notif'] = "Gagal confirm: " . $e->getMessage();
            header("Location: /isubcont/pages/trans-scan-out-vendor.php");
            exit;
        }
    } else {
        $_SESSION['red_notif'] = "QR Code tidak boleh kosong.";
        header("Location: /isubcont/pages/trans-scan-out-vendor.php");
        exit;
    }
}

// === Pending Out Vendor ===
if (isset($_POST['pending-out-vendor'])) {
    $barcode     = $_POST['barcode'] ?? null;
    $qty_data    = $_POST['qty'] ?? [];
    $keterangan  = $_POST['keterangan'] ?? null;
    $scan_with   = $_SESSION['username'] ?? 'unknown';

    if ($barcode) {
        $conn->begin_transaction();
        try {
            $scan_flow = [
                "SCAN_IN_WAREHOUSE",
                "SCAN_OUT_TO_VENDOR",
                "SCAN_IN_VENDOR",
                "SCAN_OUT_VENDOR",
                "SCAN_IN_INCOMING",
                "SCAN_CHECK_QC",
                "SCAN_OUT_TO_PRODUCTION"
            ];

            // --- Ambil data lama
            $stmt_old = $conn->prepare("SELECT * FROM tbl_transaksi WHERE barcode = ?");
            $stmt_old->bind_param("s", $barcode);
            $stmt_old->execute();
            $res_old = $stmt_old->get_result();
            $old_data = $res_old->fetch_assoc();
            $stmt_old->close();

            if (!$old_data) {
                $_SESSION['red_notif'] = "QR Code $barcode tidak ditemukan.";
                header("Location: /isubcont/pages/trans-scan-out-vendor.php");
                exit;
            }

            $json_old_data = json_encode($old_data, JSON_UNESCAPED_UNICODE);

            // --- Validasi urutan scan
            $current_state = strtoupper($old_data['type_scan'] ?? '');
            $current_index = array_search($current_state, $scan_flow);
            if ($current_index === false) $current_index = -1;

            $next_state = $scan_flow[$current_index + 1] ?? null;
            if ($next_state !== "SCAN_OUT_VENDOR") {
                $_SESSION['red_notif'] = "QR Code tidak bisa di-scan di tahap ini. 
                    Current: $current_state, Next workflow: $next_state";
                header("Location: /isubcont/pages/trans-scan-out-vendor.php");
                exit;
            }

            // --- Decode qty lama
            $old_kq_raw = $old_data['komponen_qty'] ?? '[]';
            $old_kq = is_string($old_kq_raw) ? json_decode($old_kq_raw, true) : (is_array($old_kq_raw) ? $old_kq_raw : []);
            if (!is_array($old_kq)) $old_kq = [];

            // buat map lama [komponen][size] => qty
            $map_old_qty = [];
            foreach ($old_kq as $it) {
                $id_k   = (int)($it['komponen'] ?? 0);
                $size   = (string)($it['size'] ?? "-");
                $qty    = (int)($it['qty'] ?? 0);
                $map_old_qty[$id_k][$size] = $qty;
            }

            // --- Validasi qty input tidak boleh melebihi qty lama
            foreach ($qty_data as $komponen => $sizes) {
                foreach ($sizes as $size => $new_qty) {
                    $komponen = (int)$komponen;
                    $size     = (string)$size;
                    $new_qty  = (int)$new_qty;

                    $old_qty = $map_old_qty[$komponen][$size] ?? 0;

                    if ($new_qty > $old_qty) {
                        $conn->rollback();
                        $_SESSION['red_notif'] = "Qty komponen $komponen size $size melebihi jumlah aslinya ($old_qty).";
                        header("Location: /isubcont/pages/trans-scan-out-vendor.php");
                        exit;
                    }
                }
            }

            // --- Build JSON untuk simpan (komponen + size + qty)
            $new_qty_arr = [];
            foreach ($qty_data as $komponen => $sizes) {
                foreach ($sizes as $size => $qty) {
                    $new_qty_arr[] = [
                        "komponen" => (int)$komponen,
                        "size"     => (string)$size,
                        "qty"      => (int)$qty
                    ];
                }
            }
            $qty_json = json_encode($new_qty_arr, JSON_UNESCAPED_UNICODE);

            // --- Cari hour sesuai waktu aktual
            $stmt_hour = $conn->prepare("
                SELECT id_time, hour 
                FROM tbl_time 
                WHERE TIME(NOW()) BETWEEN start_hour AND end_hour
                ORDER BY id_time LIMIT 1
            ");
            $stmt_hour->execute();
            $res_hour = $stmt_hour->get_result();
            $hour_row = $res_hour->fetch_assoc();
            $hour = $hour_row['hour'] ?? null;

            // --- Update transaksi jadi Pending dengan type_scan SCAN_OUT_VENDOR
            $stmt_upd = $conn->prepare("
                UPDATE tbl_transaksi
                SET type_scan    = 'SCAN_OUT_VENDOR',
                    status       = 'QTY_TIDAK_SESUAI',
                    komponen_qty = ?,
                    keterangan   = ?,
                    scan_with    = ?,
                    scan_at      = NOW(),
                    hour         = ?
                WHERE barcode = ?
            ");
            $stmt_upd->bind_param("sssis", $qty_json, $keterangan, $scan_with, $hour, $barcode);
            $stmt_upd->execute();

            // --- Ambil data baru
            $stmt_new = $conn->prepare("SELECT * FROM tbl_transaksi WHERE barcode = ?");
            $stmt_new->bind_param("s", $barcode);
            $stmt_new->execute();
            $res_new = $stmt_new->get_result();
            $new_data = $res_new->fetch_assoc();
            $json_new_data = $new_data ? json_encode($new_data, JSON_UNESCAPED_UNICODE) : null;

            // --- Insert log
            $stmt_log = $conn->prepare("
                INSERT INTO tlog_transaksi
                (id_trans, updated_by, action_type, old_data, new_data, created_at, updated_at)
                VALUES (?, ?, 'QTY_TIDAK_SESUAI', ?, ?, NOW(), NOW())
            ");
            $id_trans = (int)$old_data['id_trans'];
            $stmt_log->bind_param("isss", $id_trans, $scan_with, $json_old_data, $json_new_data);
            $stmt_log->execute();

            $conn->commit();

            $_SESSION['green_notif'] = "QR Code berhasil di-scan (Scan Out Vendor). Status : Quantity tidak sesuai";
            header("Location: /isubcont/pages/trans-scan-out-vendor.php?success=$barcode");
            exit;
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['red_notif'] = "Gagal simpan pending: " . $e->getMessage();
            header("Location: /isubcont/pages/trans-scan-out-vendor.php");
            exit;
        }
    } else {
        $_SESSION['red_notif'] = "QR Code tidak boleh kosong.";
        header("Location: /isubcont/pages/trans-scan-out-vendor.php");
        exit;
    }
}

// === Confirm In Incoming ===
if (isset($_POST['confirm-in-incoming'])) {
    $barcode   = $_POST['barcode'] ?? null;
    $qty_data  = $_POST['qty'] ?? []; // array komponen => [size => qty]
    $qty_array = [];

    // susun array qty dengan size
    foreach ($qty_data as $id_komponen => $sizes) {
        foreach ($sizes as $size => $qty) {
            $qty_array[] = [
                "komponen" => (int)$id_komponen,
                "size"     => (string)$size,
                "qty"      => (int)$qty
            ];
        }
    }

    // encode JSON untuk simpan
    $qty_json = json_encode($qty_array, JSON_UNESCAPED_UNICODE);

    $scan_with = $_SESSION['username'] ?? 'unknown';

    if ($barcode) {
        $conn->begin_transaction();
        try {
            // --- Flow urutan scan
            $scan_flow = [
                "SCAN_IN_WAREHOUSE",
                "SCAN_OUT_TO_VENDOR",
                "SCAN_IN_VENDOR",
                "SCAN_OUT_VENDOR",
                "SCAN_IN_INCOMING",
                "SCAN_CHECK_QC",
                "SCAN_OUT_TO_PRODUCTION"
            ];

            // --- Ambil data lama
            $stmt_old = $conn->prepare("SELECT * FROM tbl_transaksi WHERE barcode = ?");
            $stmt_old->bind_param("s", $barcode);
            $stmt_old->execute();
            $res_old = $stmt_old->get_result();
            $old_data = $res_old->fetch_assoc();

            if (!$old_data) {
                $_SESSION['red_notif'] = "QR Code $barcode tidak ditemukan.";
                header("Location: /isubcont/pages/trans-scan-in-incoming.php");
                exit;
            }

            $json_old_data = json_encode($old_data, JSON_UNESCAPED_UNICODE);

            // --- Validasi urutan scan
            $current_state = strtoupper($old_data['type_scan'] ?? '');
            $current_index = array_search($current_state, $scan_flow);
            if ($current_index === false) $current_index = -1;

            $next_state = $scan_flow[$current_index + 1] ?? null;

            if ($next_state !== "SCAN_IN_INCOMING") {
                $_SESSION['red_notif'] = "QR Code tidak bisa di-scan di tahap ini. 
                    Current: $current_state, Next workflow: $next_state";
                header("Location: /isubcont/pages/trans-scan-in-incoming.php");
                exit;
            }

            // --- Cari hour sesuai waktu aktual
            $stmt_hour = $conn->prepare("
                SELECT id_time, hour 
                FROM tbl_time 
                WHERE TIME(NOW()) BETWEEN start_hour AND end_hour
                ORDER BY id_time LIMIT 1
            ");
            $stmt_hour->execute();
            $res_hour = $stmt_hour->get_result();
            $hour_row = $res_hour->fetch_assoc();
            $hour = $hour_row['hour'] ?? null;

            // --- Update transaksi
            $stmt_upd = $conn->prepare("
                UPDATE tbl_transaksi
                SET type_scan    = 'SCAN_IN_INCOMING',
                    komponen_qty = ?,
                    scan_with    = ?,
                    scan_at      = NOW(),
                    hour         = ?
                WHERE barcode = ?
            ");
            $stmt_upd->bind_param("ssis", $qty_json, $scan_with, $hour, $barcode);
            $stmt_upd->execute();

            // --- Ambil data baru
            $stmt_new = $conn->prepare("SELECT * FROM tbl_transaksi WHERE barcode = ?");
            $stmt_new->bind_param("s", $barcode);
            $stmt_new->execute();
            $res_new = $stmt_new->get_result();
            $new_data = $res_new->fetch_assoc();
            $json_new_data = $new_data ? json_encode($new_data, JSON_UNESCAPED_UNICODE) : null;

            // --- Insert log
            $stmt_log = $conn->prepare("
                INSERT INTO tlog_transaksi
                (id_trans, updated_by, action_type, old_data, new_data, created_at, updated_at)
                VALUES (?, ?, 'SCAN_IN_INCOMING', ?, ?, NOW(), NOW())
            ");
            $id_trans = (int)$old_data['id_trans'];
            $stmt_log->bind_param("isss", $id_trans, $scan_with, $json_old_data, $json_new_data);
            $stmt_log->execute();

            $conn->commit();

            $_SESSION['green_notif'] = "QR Code berhasil di-scan (Scan In Incoming).";
            header("Location: /isubcont/pages/trans-scan-in-incoming.php?success=$barcode");
            exit;
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['red_notif'] = "Gagal confirm: " . $e->getMessage();
            header("Location: /isubcont/pages/trans-scan-in-incoming.php");
            exit;
        }
    } else {
        $_SESSION['red_notif'] = "QR Code tidak boleh kosong.";
        header("Location: /isubcont/pages/trans-scan-in-incoming.php");
        exit;
    }
}

// === Pending In Incoming ===
if (isset($_POST['pending-in-incoming'])) {
    $barcode     = $_POST['barcode'] ?? null;
    $qty_data    = $_POST['qty'] ?? []; // array: qty[komponen][size] = qty
    $keterangan  = $_POST['keterangan'] ?? null;
    $scan_with   = $_SESSION['username'] ?? 'unknown';

    if ($barcode) {
        $conn->begin_transaction();
        try {
            $scan_flow = [
                "SCAN_IN_WAREHOUSE",
                "SCAN_OUT_TO_VENDOR",
                "SCAN_IN_VENDOR",
                "SCAN_OUT_VENDOR",
                "SCAN_IN_INCOMING",
                "SCAN_CHECK_QC",
                "SCAN_OUT_TO_PRODUCTION"
            ];

            // --- Ambil data lama
            $stmt_old = $conn->prepare("SELECT * FROM tbl_transaksi WHERE barcode = ?");
            $stmt_old->bind_param("s", $barcode);
            $stmt_old->execute();
            $res_old = $stmt_old->get_result();
            $old_data = $res_old->fetch_assoc();
            $stmt_old->close();

            if (!$old_data) {
                $_SESSION['red_notif'] = "QR Code $barcode tidak ditemukan.";
                header("Location: /isubcont/pages/trans-scan-in-incoming.php");
                exit;
            }

            $json_old_data = json_encode($old_data, JSON_UNESCAPED_UNICODE);

            // --- Validasi urutan scan
            $current_state = strtoupper($old_data['type_scan'] ?? '');
            $current_index = array_search($current_state, $scan_flow);
            if ($current_index === false) $current_index = -1;

            $next_state = $scan_flow[$current_index + 1] ?? null;
            if ($next_state !== "SCAN_IN_INCOMING") {
                $_SESSION['red_notif'] = "QR Code tidak bisa di-scan di tahap ini. 
                    Current: $current_state, Next workflow: $next_state";
                header("Location: /isubcont/pages/trans-scan-in-incoming.php");
                exit;
            }

            // --- Decode qty lama
            $old_kq_raw = $old_data['komponen_qty'] ?? '[]';
            $old_kq = is_string($old_kq_raw) ? json_decode($old_kq_raw, true) : (is_array($old_kq_raw) ? $old_kq_raw : []);
            if (!is_array($old_kq)) $old_kq = [];

            // buat map lama [komponen][size] => qty
            $map_old_qty = [];
            foreach ($old_kq as $it) {
                $id_k   = (int)($it['komponen'] ?? 0);
                $size   = (string)($it['size'] ?? "-");
                $qty    = (int)($it['qty'] ?? 0);
                $map_old_qty[$id_k][$size] = $qty;
            }

            // --- Validasi qty input tidak boleh melebihi qty lama
            foreach ($qty_data as $komponen => $sizes) {
                foreach ($sizes as $size => $new_qty) {
                    $komponen = (int)$komponen;
                    $size     = (string)$size;
                    $new_qty  = (int)$new_qty;

                    $old_qty = $map_old_qty[$komponen][$size] ?? 0;

                    if ($new_qty > $old_qty) {
                        $conn->rollback();
                        $_SESSION['red_notif'] = "Qty komponen $komponen (size $size) melebihi jumlah aslinya ($old_qty).";
                        header("Location: /isubcont/pages/trans-scan-in-incoming.php");
                        exit;
                    }
                }
            }

            // --- Build JSON untuk simpan (komponen + size + qty)
            $new_qty_arr = [];
            foreach ($qty_data as $komponen => $sizes) {
                foreach ($sizes as $size => $qty) {
                    $new_qty_arr[] = [
                        "komponen" => (int)$komponen,
                        "size"     => (string)$size,
                        "qty"      => (int)$qty
                    ];
                }
            }
            $qty_json = json_encode($new_qty_arr, JSON_UNESCAPED_UNICODE);

            // --- Cari hour sesuai waktu aktual
            $stmt_hour = $conn->prepare("
                SELECT id_time, hour 
                FROM tbl_time 
                WHERE TIME(NOW()) BETWEEN start_hour AND end_hour
                ORDER BY id_time LIMIT 1
            ");
            $stmt_hour->execute();
            $res_hour = $stmt_hour->get_result();
            $hour_row = $res_hour->fetch_assoc();
            $hour = $hour_row['hour'] ?? null;

            // --- Update transaksi jadi Pending SCAN_IN_INCOMING
            $stmt_upd = $conn->prepare("
                UPDATE tbl_transaksi
                SET type_scan    = 'SCAN_IN_INCOMING',
                    status       = 'QTY_TIDAK_SESUAI',
                    komponen_qty = ?,
                    keterangan   = ?,
                    scan_with    = ?,
                    scan_at      = NOW(),
                    hour         = ?
                WHERE barcode = ?
            ");
            $stmt_upd->bind_param("sssis", $qty_json, $keterangan, $scan_with, $hour, $barcode);
            $stmt_upd->execute();

            // --- Ambil data baru
            $stmt_new = $conn->prepare("SELECT * FROM tbl_transaksi WHERE barcode = ?");
            $stmt_new->bind_param("s", $barcode);
            $stmt_new->execute();
            $res_new = $stmt_new->get_result();
            $new_data = $res_new->fetch_assoc();
            $json_new_data = $new_data ? json_encode($new_data, JSON_UNESCAPED_UNICODE) : null;

            // --- Insert log
            $stmt_log = $conn->prepare("
                INSERT INTO tlog_transaksi
                (id_trans, updated_by, action_type, old_data, new_data, created_at, updated_at)
                VALUES (?, ?, 'QTY_TIDAK_SESUAI', ?, ?, NOW(), NOW())
            ");
            $id_trans = (int)$old_data['id_trans'];
            $stmt_log->bind_param("isss", $id_trans, $scan_with, $json_old_data, $json_new_data);
            $stmt_log->execute();

            $conn->commit();

            $_SESSION['green_notif'] = "QR Code berhasil di-scan (Scan In Incoming). Status : Quantity tidak sesuai";
            header("Location: /isubcont/pages/trans-scan-in-incoming.php?success=$barcode");
            exit;
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['red_notif'] = "Gagal simpan pending: " . $e->getMessage();
            header("Location: /isubcont/pages/trans-scan-in-incoming.php");
            exit;
        }
    } else {
        $_SESSION['red_notif'] = "QR Code tidak boleh kosong.";
        header("Location: /isubcont/pages/trans-scan-in-incoming.php");
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

// REMOVE role (soft delete)
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

// RESTORE deleted
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

// DELETE permanent role
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

// === Confirm Check QC ===
if (isset($_POST['confirm-qc'])) {
    $barcode      = $_POST['barcode'] ?? null;
    $post_qty     = $_POST['qty'] ?? [];           // array komponen => qty (optional)
    $post_defect  = $_POST['defect'] ?? [];        // array defect[komponen_id][] (optional)
    $post_def_qty = $_POST['defect_qty'] ?? [];    // array defect_qty[komponen_id][] (optional)
    $scan_with    = $_SESSION['username'] ?? 'unknown';

    if (!$barcode) {
        $_SESSION['red_notif'] = "QR Code tidak boleh kosong.";
        header("Location: /isubcont/pages/trans-scan-check-qc.php");
        exit;
    }

    $conn->begin_transaction();
    try {
        // ambil data lama
        $stmt_old = $conn->prepare("SELECT * FROM tbl_transaksi WHERE barcode = ?");
        $stmt_old->bind_param("s", $barcode);
        $stmt_old->execute();
        $res_old = $stmt_old->get_result();
        $old_data = $res_old->fetch_assoc();
        $stmt_old->close();

        if (!$old_data) {
            $conn->rollback();
            $_SESSION['red_notif'] = "QR Code $barcode tidak ditemukan.";
            header("Location: /isubcont/pages/trans-scan-check-qc.php");
            exit;
        }

        $json_old_data = json_encode($old_data, JSON_UNESCAPED_UNICODE);

        // validasi urutan workflow (sama gaya sebelumnya)
        $scan_flow = [
            "SCAN_IN_WAREHOUSE",
            "SCAN_OUT_TO_VENDOR",
            "SCAN_IN_VENDOR",
            "SCAN_OUT_VENDOR",
            "SCAN_IN_INCOMING",
            "SCAN_CHECK_QC",
            "SCAN_OUT_TO_PRODUCTION"
        ];
        $current_state = strtoupper($old_data['type_scan'] ?? '');
        $current_index = array_search($current_state, $scan_flow);
        if ($current_index === false) $current_index = -1;
        $next_state = $scan_flow[$current_index + 1] ?? null;
        if ($next_state !== "SCAN_CHECK_QC") {
            $conn->rollback();
            $_SESSION['red_notif'] = "QR Code tidak bisa di-scan di tahap ini. Current: $current_state, Next workflow: $next_state";
            header("Location: /isubcont/pages/trans-scan-check-qc.php");
            exit;
        }

        // cari hour sesuai NOW()
        $stmt_hour = $conn->prepare("
            SELECT id_time, hour
            FROM tbl_time
            WHERE TIME(NOW()) BETWEEN start_hour AND end_hour
            ORDER BY id_time LIMIT 1
        ");
        $stmt_hour->execute();
        $res_hour = $stmt_hour->get_result();
        $hour_row = $res_hour->fetch_assoc();
        $hour = $hour_row['hour'] ?? null;
        $hour_int = (int)$hour;

        // === Build komponen_qty final (dengan size) ===
        if (!empty($post_qty) && is_array($post_qty)) {
            $komponen_arr = [];
            $komp_map = []; // map untuk validasi defect

            foreach ($post_qty as $komponen_id => $size_list) {
                if (!is_array($size_list)) continue;

                foreach ($size_list as $size_val => $q) {
                    $q_int = (int)$q;
                    $komponen_arr[] = [
                        "komponen" => (int)$komponen_id,
                        "size"     => (string)$size_val,
                        "qty"      => $q_int
                    ];
                    $komp_map[$komponen_id][$size_val] = $q_int;
                }
            }

            $qty_json_final = json_encode($komponen_arr, JSON_UNESCAPED_UNICODE);
        } else {
            // fallback pakai data lama
            $old_kq_raw = $old_data['komponen_qty'] ?? '[]';
            $decoded = is_string($old_kq_raw) ? json_decode($old_kq_raw, true) : (array)$old_kq_raw;
            if (!is_array($decoded)) $decoded = [];

            $qty_json_final = json_encode($decoded, JSON_UNESCAPED_UNICODE);

            // rebuild komp_map dari data lama
            $komp_map = [];
            foreach ($decoded as $it) {
                $komp = (int)($it['komponen'] ?? 0);
                $size = (string)($it['size'] ?? "-");
                $komp_map[$komp][$size] = (int)($it['qty'] ?? 0);
            }
        }

        // === Process defect input (optional, dengan size) ===
        if (!empty($post_defect) && is_array($post_defect)) {
            $defect_arr = [];

            foreach ($post_defect as $komponen_id => $size_group) {
                $komponen_id = (int)$komponen_id;
                if (!is_array($size_group)) continue;

                foreach ($size_group as $size_val => $defect_list) {
                    $size_val = (string)$size_val;
                    if (!is_array($defect_list)) continue;

                    $total_defect_for_size = 0;

                    foreach ($defect_list as $idx => $defect_id) {
                        $defect_id = (int)$defect_id;
                        $qty_val   = isset($post_def_qty[$komponen_id][$size_val][$idx]) ? (int)$post_def_qty[$komponen_id][$size_val][$idx] : 0;
                        if ($defect_id === 0 || $qty_val <= 0) continue;

                        $total_defect_for_size += $qty_val;

                        // validasi: defect tidak boleh > qty size ini
                        $available = $komp_map[$komponen_id][$size_val] ?? 0;
                        if ($total_defect_for_size > $available) {
                            $conn->rollback();
                            $_SESSION['red_notif'] = "Qty defect untuk komponen $komponen_id size $size_val melebihi qty aslinya ($available).";
                            header("Location: /isubcont/pages/trans-scan-check-qc.php");
                            exit;
                        }

                        $defect_arr[] = [
                            "komponen" => $komponen_id,
                            "size"     => $size_val,
                            "defect"   => $defect_id,
                            "qty"      => $qty_val
                        ];
                    }
                }
            }
            $defect_json = json_encode($defect_arr, JSON_UNESCAPED_UNICODE);
        } else {
            // fallback ambil defect lama
            $old_def_raw = $old_data['defect_qty'] ?? '[]';
            $decoded = is_string($old_def_raw) ? json_decode($old_def_raw, true) : (array)$old_def_raw;
            if (!is_array($decoded)) $decoded = [];
            $defect_json = json_encode($decoded, JSON_UNESCAPED_UNICODE);
        }

        // === Update transaksi ===
        $stmt_upd = $conn->prepare("
            UPDATE tbl_transaksi
            SET type_scan    = 'SCAN_CHECK_QC',
                komponen_qty = ?,
                defect_qty   = ?,
                scan_with    = ?,
                scan_at      = NOW(),
                hour         = ?
            WHERE barcode = ?
        ");
        // types: s (qty_json), s (defect_json), s (scan_with), i (hour_int), s (barcode)
        $stmt_upd->bind_param("sssis", $qty_json_final, $defect_json, $scan_with, $hour_int, $barcode);
        $stmt_upd->execute();

        // ambil data baru untuk log
        $stmt_new = $conn->prepare("SELECT * FROM tbl_transaksi WHERE barcode = ?");
        $stmt_new->bind_param("s", $barcode);
        $stmt_new->execute();
        $res_new = $stmt_new->get_result();
        $new_data = $res_new->fetch_assoc();
        $stmt_new->close();
        $json_new_data = $new_data ? json_encode($new_data, JSON_UNESCAPED_UNICODE) : null;

        // insert log
        $stmt_log = $conn->prepare("
            INSERT INTO tlog_transaksi
            (id_trans, updated_by, action_type, old_data, new_data, created_at, updated_at)
            VALUES (?, ?, 'SCAN_CHECK_QC', ?, ?, NOW(), NOW())
        ");
        $id_trans = (int)$old_data['id_trans'];
        $stmt_log->bind_param("isss", $id_trans, $scan_with, $json_old_data, $json_new_data);
        $stmt_log->execute();

        $conn->commit();
        $_SESSION['green_notif'] = "QR Code berhasil di-scan (Scan Check QC).";
        header("Location: /isubcont/pages/trans-scan-check-qc.php?success=" . urlencode($barcode));
        exit;
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['red_notif'] = "Gagal confirm QC: " . $e->getMessage();
        header("Location: /isubcont/pages/trans-scan-check-qc.php");
        exit;
    }
}

// === Scan Out to Production ===
if (isset($_POST['scan-out-production'])) {
    $barcode   = $_POST['barcode'] ?? null;
    $scan_with = $_SESSION['username'] ?? 'unknown';

    if ($barcode) {
        $conn->begin_transaction();
        try {
            // --- Flow urutan scan
            $scan_flow = [
                "SCAN_IN_WAREHOUSE",
                "SCAN_OUT_TO_VENDOR",
                "SCAN_IN_VENDOR",
                "SCAN_OUT_VENDOR",
                "SCAN_IN_INCOMING",
                "SCAN_CHECK_QC",
                "SCAN_OUT_TO_PRODUCTION"
            ];

            // --- Ambil data lama
            $stmt_old = $conn->prepare("SELECT * FROM tbl_transaksi WHERE barcode = ?");
            $stmt_old->bind_param("s", $barcode);
            $stmt_old->execute();
            $res_old = $stmt_old->get_result();
            $old_data = $res_old->fetch_assoc();

            if (!$old_data) {
                $_SESSION['red_notif'] = "QR Code $barcode tidak ditemukan.";
                header("Location: /isubcont/pages/trans-scan-out-to-prod.php");
                exit;
            }

            $json_old_data = json_encode($old_data, JSON_UNESCAPED_UNICODE);

            // --- Validasi urutan scan
            $current_state = strtoupper($old_data['type_scan'] ?? '');
            $current_index = array_search($current_state, $scan_flow);

            // Kalau belum pernah scan (NULL), posisikan index = -1
            if ($current_index === false) {
                $current_index = -1;
            }

            $next_state = $scan_flow[$current_index + 1] ?? null;

            if ($next_state !== "SCAN_OUT_TO_PRODUCTION") {
                $_SESSION['red_notif'] = "QR Code tidak bisa di-scan di tahap ini. 
                    Current: $current_state, Next workflow: $next_state";
                header("Location: /isubcont/pages/trans-scan-out-to-prod.php");
                exit;
            }

            // --- Cari hour sesuai waktu aktual
            $stmt_hour = $conn->prepare("
                SELECT id_time, hour 
                FROM tbl_time 
                WHERE TIME(NOW()) BETWEEN start_hour AND end_hour
                ORDER BY id_time LIMIT 1
            ");
            $stmt_hour->execute();
            $res_hour = $stmt_hour->get_result();
            $hour_row = $res_hour->fetch_assoc();
            $hour = $hour_row['hour'] ?? null;

            // --- Update transaksi
            $stmt_upd = $conn->prepare("
                UPDATE tbl_transaksi
                SET type_scan = 'SCAN_OUT_TO_PRODUCTION',
                    scan_with = ?,
                    scan_at   = NOW(),
                    hour      = ?
                WHERE barcode = ?
            ");
            $stmt_upd->bind_param("sis", $scan_with, $hour, $barcode);
            $stmt_upd->execute();

            // --- Ambil data baru
            $stmt_new = $conn->prepare("SELECT * FROM tbl_transaksi WHERE barcode = ?");
            $stmt_new->bind_param("s", $barcode);
            $stmt_new->execute();
            $res_new = $stmt_new->get_result();
            $new_data = $res_new->fetch_assoc();
            $json_new_data = $new_data ? json_encode($new_data, JSON_UNESCAPED_UNICODE) : null;

            // --- Insert log
            $stmt_log = $conn->prepare("
                INSERT INTO tlog_transaksi
                (id_trans, updated_by, action_type, old_data, new_data, created_at, updated_at)
                VALUES (?, ?, 'SCAN_OUT_TO_PRODUCTION', ?, ?, NOW(), NOW())
            ");
            $id_trans = (int)$old_data['id_trans'];
            $stmt_log->bind_param("isss", $id_trans, $scan_with, $json_old_data, $json_new_data);
            $stmt_log->execute();

            $conn->commit();

            $_SESSION['green_notif'] = "QR Code berhasil di-scan (Scan Out to Production).";
            header("Location: /isubcont/pages/trans-scan-out-to-prod.php?success=$barcode");
            exit;
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['red_notif'] = "Gagal scan QR Code: " . $e->getMessage();
            header("Location: /isubcont/pages/trans-scan-out-to-prod.php");
            exit;
        }
    } else {
        $_SESSION['red_notif'] = "QR Code tidak boleh kosong.";
        header("Location: /isubcont/pages/trans-scan-out-to-prod.php");
        exit;
    }
}
