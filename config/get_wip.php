<?php
require_once __DIR__ . '/function.php';

header('Content-Type: application/json');

$filter = $_GET['type'] ?? 'SCAN_IN_WAREHOUSE';

// =============== MODE DETAIL ==================
if (isset($_GET['detail']) && $_GET['detail'] == '1') {

    $ncvs = $_GET['ncvs'] ?? '';
    $type = $_GET['filter'] ?? '';

    if ($ncvs === '') {
        echo json_encode(["error" => "NCVS not provided"]);
        exit;
    }

    // Ambil semua transaksi terkait NCVS ini
    $sql = "
        SELECT 
            action_type,
            created_at,
            JSON_EXTRACT(new_data,'$.id_trans') AS id_trans,
            JSON_UNQUOTE(JSON_EXTRACT(new_data,'$.komponen_qty')) AS arr
        FROM tlog_transaksi
        WHERE JSON_UNQUOTE(JSON_EXTRACT(new_data,'$.ncvs')) = '$ncvs'
        ORDER BY created_at ASC
    ";

    $res = mysqli_query($conn, $sql);

    $details = [];
    $sumIn = 0;
    $sumOut = 0;

    while ($row = mysqli_fetch_assoc($res)) {

        // hitung total qty
        $arr = json_decode($row['arr'], true);
        $qty = 0;
        if (is_array($arr)) {
            foreach ($arr as $c) $qty += (int)($c['qty'] ?? 0);
        }

        // summary khusus (optional)
        if ($row['action_type'] === 'SCAN_IN_INCOMING') $sumIn += $qty;
        if ($row['action_type'] === 'SCAN_OUT_TO_PRODUCTION') $sumOut += $qty;

        $details[] = [
            "id_trans" => $row['id_trans'],
            "action_type" => $row['action_type'],
            "qty" => $qty,
            "tanggal" => $row['created_at']
        ];
    }

    $summary = [
        "total_in" => $sumIn,
        "total_out" => $sumOut,
        "wip" => $sumIn - $sumOut
    ];

    echo json_encode([
        "ncvs" => $ncvs,
        "summary" => $summary,
        "details" => $details
    ]);
    exit;
}

// ---------------- SCAN_IN_WAREHOUSE ----------------
if ($filter === 'SCAN_IN_WAREHOUSE') {

    $scanInWH = [];
    $scanOutVendor = [];

    $sqlWH = "SELECT JSON_UNQUOTE(JSON_EXTRACT(new_data,'$.ncvs')) AS ncvs,
                     JSON_UNQUOTE(JSON_EXTRACT(new_data,'$.komponen_qty')) AS arr,
                     action_type
              FROM tlog_transaksi
              WHERE action_type IN ('SCAN_IN_WAREHOUSE','SCAN_OUT_TO_VENDOR')";
    $resWH = mysqli_query($conn, $sqlWH);

    while ($row = mysqli_fetch_assoc($resWH)) {
        $arr = json_decode($row['arr'], true);
        $qtySum = 0;
        if (is_array($arr)) {
            foreach ($arr as $c) $qtySum += isset($c['qty']) ? (int)$c['qty'] : 0;
        }

        if ($row['action_type'] === 'SCAN_IN_WAREHOUSE') {
            $scanInWH[$row['ncvs']] = ($scanInWH[$row['ncvs']] ?? 0) + $qtySum;
        } else {
            $scanOutVendor[$row['ncvs']] = ($scanOutVendor[$row['ncvs']] ?? 0) + $qtySum;
        }
    }

    $allNcvs = array_unique(array_merge(array_keys($scanInWH), array_keys($scanOutVendor)));
    $data = [];
    foreach ($allNcvs as $ncvs) {
        $data[] = [
            'ncvs' => $ncvs,
            'wip_in_wh' => ($scanInWH[$ncvs] ?? 0) - ($scanOutVendor[$ncvs] ?? 0)
        ];
    }

    echo json_encode($data);
    exit;
}

// ---------------- SCAN_OUT_TO_VENDOR ----------------
if ($filter === 'SCAN_OUT_TO_VENDOR') {

    $scanOutVendor = [];
    $scanInIncoming = [];
    $confirmVendor = [];

    // =======================
    // 1. Ambil OUT vendor + IN incoming
    // =======================
    $sqlOutVendor = "
        SELECT 
            JSON_UNQUOTE(JSON_EXTRACT(new_data,'$.ncvs')) AS ncvs,
            JSON_UNQUOTE(JSON_EXTRACT(new_data,'$.komponen_qty')) AS arr,
            action_type
        FROM tlog_transaksi
        WHERE action_type IN ('SCAN_OUT_TO_VENDOR','SCAN_IN_INCOMING')
    ";

    $resVen = mysqli_query($conn, $sqlOutVendor);

    while ($row = mysqli_fetch_assoc($resVen)) {

        $ncvs = $row['ncvs'];
        $arr = json_decode($row['arr'], true);

        $qtySum = 0;
        if (is_array($arr)) {
            foreach ($arr as $c) {
                $qtySum += isset($c['qty']) ? (int)$c['qty'] : 0;
            }
        }

        if ($row['action_type'] === 'SCAN_OUT_TO_VENDOR') {
            $scanOutVendor[$ncvs] = ($scanOutVendor[$ncvs] ?? 0) + $qtySum;
        } else {
            $scanInIncoming[$ncvs] = ($scanInIncoming[$ncvs] ?? 0) + $qtySum;
        }
    }

    // =======================
    // 2. Ambil CONFIRM_KEKURANGAN
    // =======================
    $sqlConf = "
        SELECT 
            JSON_EXTRACT(new_data,'$.id_trans_asal') AS id_trans_asal,
            JSON_EXTRACT(new_data,'$.total_kekurangan') AS total_kekurangan
        FROM tlog_transaksi
        WHERE action_type = 'CONFIRM_KEKURANGAN'
    ";
    $resConf = mysqli_query($conn, $sqlConf);

    while ($row = mysqli_fetch_assoc($resConf)) {

        $idTransAsal = (int)$row['id_trans_asal'];
        $totalKekurangan = (int)$row['total_kekurangan'];

        // cari NCVS dari id_trans_asal
        $sqlNcvs = "
            SELECT JSON_UNQUOTE(JSON_EXTRACT(new_data,'$.ncvs')) AS ncvs
            FROM tlog_transaksi
            WHERE action_type = 'SCAN_OUT_TO_VENDOR'
              AND JSON_EXTRACT(new_data,'$.id_trans') = $idTransAsal
            LIMIT 1
        ";

        $resNcvs = mysqli_query($conn, $sqlNcvs);
        if ($resNcvs && $r = mysqli_fetch_assoc($resNcvs)) {
            $ncvsKey = $r['ncvs'];
            $confirmVendor[$ncvsKey] = ($confirmVendor[$ncvsKey] ?? 0) + $totalKekurangan;
        }
    }

    // =======================
    // 3. Hitung final SCAN_OUT_TO_VENDOR
    // =======================
    $allNcvs = array_unique(array_merge(
        array_keys($scanOutVendor),
        array_keys($scanInIncoming),
        array_keys($confirmVendor)
    ));

    $data = [];

    foreach ($allNcvs as $ncvs) {
        $out = $scanOutVendor[$ncvs] ?? 0;
        $in  = $scanInIncoming[$ncvs] ?? 0;
        $conf = $confirmVendor[$ncvs] ?? 0;

        $wip = $out - ($in + $conf);

        $data[] = [
            'ncvs' => $ncvs,
            'wip_out_vendor' => $wip
        ];
    }

    echo json_encode($data);
    exit;
}

// ---------------- SCAN_IN_INCOMING ----------------
if ($filter === 'SCAN_IN_INCOMING') {

    // Semua transaksi dipetakan per NCVS per transaksi
    $trx = []; // $trx[ncvs][] = ['in'=>, 'out'=>, 'conf'=>, 'ven'=>]

    // =============================
    // 1. SCAN_IN_INCOMING
    // =============================
    $sqlIn = "SELECT 
                JSON_UNQUOTE(JSON_EXTRACT(new_data,'$.ncvs')) AS ncvs,
                JSON_EXTRACT(new_data,'$.id_trans') AS id_trans,
                JSON_UNQUOTE(JSON_EXTRACT(new_data,'$.komponen_qty')) AS arr
              FROM tlog_transaksi
              WHERE action_type = 'SCAN_IN_INCOMING'";

    $resIn = mysqli_query($conn, $sqlIn);
    while ($row = mysqli_fetch_assoc($resIn)) {

        $arr = json_decode($row['arr'], true);
        $qty = 0;
        foreach ($arr as $c) $qty += (int)($c['qty'] ?? 0);

        $trx[$row['ncvs']][$row['id_trans']]['in'] =
            ($trx[$row['ncvs']][$row['id_trans']]['in'] ?? 0) + $qty;
    }

    // =============================
    // 2. SCAN_OUT_TO_PRODUCTION
    // =============================
    $sqlOut = "SELECT 
                JSON_UNQUOTE(JSON_EXTRACT(new_data,'$.ncvs')) AS ncvs,
                JSON_EXTRACT(new_data,'$.id_trans') AS id_trans,
                JSON_UNQUOTE(JSON_EXTRACT(new_data,'$.komponen_qty')) AS arr
              FROM tlog_transaksi
              WHERE action_type = 'SCAN_OUT_TO_PRODUCTION'";

    $resOut = mysqli_query($conn, $sqlOut);
    while ($row = mysqli_fetch_assoc($resOut)) {

        $arr = json_decode($row['arr'], true);
        $qty = 0;
        foreach ($arr as $c) $qty += (int)($c['qty'] ?? 0);

        $trx[$row['ncvs']][$row['id_trans']]['out'] =
            ($trx[$row['ncvs']][$row['id_trans']]['out'] ?? 0) + $qty;
    }

    // =============================
    // 3. CONFIRM_KEKURANGAN
    // =============================
    $sqlConf = "SELECT 
                    JSON_EXTRACT(new_data,'$.id_trans_asal') AS id_trans_asal,
                    JSON_EXTRACT(new_data,'$.total_kekurangan') AS total_kekurangan
                FROM tlog_transaksi
                WHERE action_type = 'CONFIRM_KEKURANGAN'";

    $resConf = mysqli_query($conn, $sqlConf);
    while ($row = mysqli_fetch_assoc($resConf)) {

        $idAsal = (int)$row['id_trans_asal'];
        $qtyConf = (int)$row['total_kekurangan'];

        // cari NCVS dari id_trans
        $sqlNcvs = "SELECT 
                        JSON_UNQUOTE(JSON_EXTRACT(new_data,'$.ncvs')) AS ncvs
                    FROM tlog_transaksi
                    WHERE JSON_EXTRACT(new_data,'$.id_trans') = {$idAsal}
                    LIMIT 1";

        $resN = mysqli_query($conn, $sqlNcvs);
        if ($resN && $r = mysqli_fetch_assoc($resN)) {
            $ncvs = $r['ncvs'];
            $trx[$ncvs][$idAsal]['conf'] =
                ($trx[$ncvs][$idAsal]['conf'] ?? 0) + $qtyConf;
        }
    }

    // =============================
    // 4. OUT_VENDOR
    // =============================
    $sqlVen = "SELECT 
                    JSON_UNQUOTE(JSON_EXTRACT(new_data,'$.ncvs')) AS ncvs,
                    JSON_EXTRACT(new_data,'$.id_trans') AS id_trans,
                    JSON_UNQUOTE(JSON_EXTRACT(new_data,'$.komponen_qty')) AS arr
               FROM tlog_transaksi
               WHERE action_type = 'SCAN_OUT_TO_VENDOR'";

    $resVen = mysqli_query($conn, $sqlVen);
    while ($row = mysqli_fetch_assoc($resVen)) {
        $arr = json_decode($row['arr'], true);
        $qty = 0;
        foreach ($arr as $c) $qty += (int)($c['qty'] ?? 0);

        $trx[$row['ncvs']][$row['id_trans']]['ven'] =
            ($trx[$row['ncvs']][$row['id_trans']]['ven'] ?? 0) + $qty;
    }

    // =============================
    // 5. HITUNG FINAL PER NCVS
    // =============================
    $data = [];

    foreach ($trx as $ncvs => $rows) {

        $totalIn = 0;
        $totalOutVendor = 0;
        $totalEffectiveIn = 0;
        $totalEffectiveOut = 0;

        foreach ($rows as $tid => $t) {

            $in   = $t['in']   ?? 0;
            $out  = $t['out']  ?? 0;
            $conf = $t['conf'] ?? 0;
            $ven  = $t['ven']  ?? 0;

            // accumulate vendor
            $totalIn += $in;
            $totalOutVendor += $ven;

            // effective incoming per transaction
            $effIn = $in + $conf;
            $totalEffectiveIn += $effIn;

            // effective out production per transaction
            $effOut = min($out, $effIn);
            $totalEffectiveOut += $effOut;
        }

        // FINAL WIP
        $wipVendor = max(0, $totalOutVendor - $totalIn - array_sum(array_column($rows, 'conf')));
        $wipIncoming = $totalEffectiveIn - $totalEffectiveOut;

        $data[] = [
            'ncvs' => $ncvs,
            'effective_out_prod' => $totalEffectiveOut,
            'effective_in' => $totalEffectiveIn,
            'wip_vendor' => $wipVendor,
            'wip_in_incoming' => $wipIncoming,
        ];
    }

    usort($data, fn($a, $b) => intval($a['ncvs']) <=> intval($b['ncvs']));

    echo json_encode($data);
    exit;
}

// ---------------- SCAN_OUT_TO_PRODUCTION (PER-TRANSACTION, SAFE) ----------------
if ($filter === 'SCAN_OUT_TO_PRODUCTION') {

    // map per ncvs -> id_trans -> values
    $trx = []; // $trx[ncvs][id_trans] = ['in'=>, 'out'=>, 'conf'=>]

    // 1) Ambil SCAN_IN_INCOMING (incoming per transaction)
    $sqlIn = "SELECT 
                JSON_UNQUOTE(JSON_EXTRACT(new_data,'$.ncvs')) AS ncvs,
                JSON_EXTRACT(new_data,'$.id_trans') AS id_trans,
                JSON_UNQUOTE(JSON_EXTRACT(new_data,'$.komponen_qty')) AS arr
              FROM tlog_transaksi
              WHERE action_type = 'SCAN_IN_INCOMING'";
    $resIn = mysqli_query($conn, $sqlIn);
    while ($row = mysqli_fetch_assoc($resIn)) {
        $arr = json_decode($row['arr'], true);
        $sum = 0;
        if (is_array($arr)) foreach ($arr as $c) $sum += (int)($c['qty'] ?? 0);

        $id = $row['id_trans'];
        $ncvs = $row['ncvs'];
        $trx[$ncvs][$id]['in'] = ($trx[$ncvs][$id]['in'] ?? 0) + $sum;
    }

    // 2) Ambil SCAN_OUT_TO_PRODUCTION (per transaksi)
    $sqlOut = "SELECT 
                JSON_UNQUOTE(JSON_EXTRACT(new_data,'$.ncvs')) AS ncvs,
                JSON_EXTRACT(JSON_UNQUOTE(JSON_EXTRACT(new_data,'$.id_trans')),'$') AS id_trans,
                JSON_UNQUOTE(JSON_EXTRACT(new_data,'$.komponen_qty')) AS arr
               FROM tlog_transaksi
               WHERE action_type = 'SCAN_OUT_TO_PRODUCTION'";
    // NOTE: some DBs store id_trans as JSON number — adjust JSON_EXTRACT usage if needed.
    $resOut = mysqli_query($conn, $sqlOut);
    while ($row = mysqli_fetch_assoc($resOut)) {
        $arr = json_decode($row['arr'], true);
        $sum = 0;
        if (is_array($arr)) foreach ($arr as $c) $sum += (int)($c['qty'] ?? 0);

        $id = $row['id_trans'];
        $ncvs = $row['ncvs'];
        $trx[$ncvs][$id]['out'] = ($trx[$ncvs][$id]['out'] ?? 0) + $sum;
    }

    // 3) Ambil CONFIRM_KEKURANGAN, map ke id_trans_asal (trans asal = incoming)
    $sqlConf = "
        SELECT 
            JSON_EXTRACT(new_data,'$.id_trans_asal') AS id_trans_asal,
            JSON_EXTRACT(new_data,'$.total_kekurangan') AS total_kekurangan
        FROM tlog_transaksi
        WHERE action_type = 'CONFIRM_KEKURANGAN'";
    $resConf = mysqli_query($conn, $sqlConf);
    while ($row = mysqli_fetch_assoc($resConf)) {
        $idAsal = (int)$row['id_trans_asal'];
        $tot = (int)$row['total_kekurangan'];

        // cari NCVS dari transaksi incoming asal
        $sqlNcvs = "
            SELECT JSON_UNQUOTE(JSON_EXTRACT(new_data,'$.ncvs')) AS ncvs
            FROM tlog_transaksi
            WHERE action_type = 'SCAN_IN_INCOMING'
              AND JSON_EXTRACT(new_data,'$.id_trans') = {$idAsal}
            LIMIT 1
        ";
        $resNcvs = mysqli_query($conn, $sqlNcvs);
        if ($resNcvs && $r = mysqli_fetch_assoc($resNcvs)) {
            $ncvsKey = $r['ncvs'];
            $trx[$ncvsKey][$idAsal]['conf'] = ($trx[$ncvsKey][$idAsal]['conf'] ?? 0) + $tot;
        }
    }

    // 4) Hitung per transaksi → effective out to production
    $data = [];
    foreach ($trx as $ncvs => $transList) {
        $totalEffOut = 0;
        $totalIn = 0;
        $totalConf = 0;

        foreach ($transList as $tid => $vals) {
            $in = $vals['in'] ?? 0;
            $out = $vals['out'] ?? 0;
            $conf = $vals['conf'] ?? 0;

            $effIn = $in + $conf;                // incoming yang bisa dipakai dari transaksi ini
            $effOut = min($out, $effIn);         // out to prod tidak boleh melebihi effIn
            $totalEffOut += $effOut;

            $totalIn += $in;
            $totalConf += $conf;
        }

        // jika kamu ingin menampilkan juga available incoming (opsional)
        // $effectiveIncoming = $totalIn + $totalConf;

        // final wip_out_prod = jumlah effective out to production (safe)
        if ($totalEffOut > 0) {
            $data[] = [
                'ncvs' => $ncvs,
                'wip_out_prod' => $totalEffOut
            ];
        }
    }

    usort($data, fn($a, $b) => intval($a['ncvs']) <=> intval($b['ncvs']));

    echo json_encode($data);
    exit;
}

// Jika type tidak dikenal
echo json_encode([]);
