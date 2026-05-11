<?php
require_once __DIR__ . '/function.php';

header('Content-Type: application/json');

$filter = $_GET['type'] ?? 'SCAN_IN_WAREHOUSE';

// ---------------- SCAN_IN_WAREHOUSE (JOB ORDER BASED) ----------------
if ($filter === 'SCAN_IN_WAREHOUSE') {

    $debug = isset($_GET['debug']) && $_GET['debug'] == '1';

    $stock = [];   // stok per JOB ORDER + PO + BUCKET
    $log   = [];

    $sql = <<<SQL
SELECT 
    action_type,
    new_data,
    JSON_UNQUOTE(JSON_EXTRACT(new_data,'$.job_order')) AS job_order,
    JSON_UNQUOTE(JSON_EXTRACT(new_data,'$.bucket')) AS bucket,
    JSON_UNQUOTE(JSON_EXTRACT(new_data,'$.ncvs')) AS ncvs,
    JSON_UNQUOTE(JSON_EXTRACT(new_data,'$.po_code')) AS po_code,
    JSON_UNQUOTE(JSON_EXTRACT(new_data,'$.po_item')) AS po_item,
    JSON_UNQUOTE(JSON_EXTRACT(new_data,'$.style')) AS style,
    JSON_UNQUOTE(JSON_EXTRACT(new_data,'$.model')) AS model,
    JSON_UNQUOTE(JSON_EXTRACT(new_data,'$.komponen_qty')) AS komponen_qty,
    COALESCE(
      JSON_UNQUOTE(JSON_EXTRACT(new_data,'$.scan_at')),
      JSON_UNQUOTE(JSON_EXTRACT(new_data,'$.date_created'))
    ) AS scan_at
FROM tlog_transaksi
WHERE action_type IN ('SCAN_IN_WAREHOUSE','SCAN_OUT_TO_VENDOR')
ORDER BY scan_at ASC
SQL;

    $res = mysqli_query($conn, $sql);
    if (!$res) {
        echo json_encode(["error" => mysqli_error($conn)]);
        exit;
    }

    while ($row = mysqli_fetch_assoc($res)) {

        if (!$row['job_order'] || !$row['ncvs']) continue;

        // 🔑 COMPOSITE KEY (inti refactor)
        $key = implode('__', [
            $row['job_order'],
            $row['bucket'],
            $row['po_code'],
            $row['po_item'],
            $row['ncvs']
        ]);

        $komponen = json_decode($row['komponen_qty'] ?? '[]', true);
        if (!is_array($komponen)) $komponen = [];

        // INIT bucket stok
        if (!isset($stock[$key])) {
            $stock[$key] = [
                'job_order' => $row['job_order'],
                'bucket'    => $row['bucket'],
                'ncvs'      => $row['ncvs'],
                'po_code'   => $row['po_code'],
                'po_item'   => $row['po_item'],
                'style'     => $row['style'],
                'model'     => $row['model'],
                'size'      => []
            ];
        }

        foreach ($komponen as $c) {
            $size = $c['size'];
            $qty  = (int)$c['qty'];

            if (!isset($stock[$key]['size'][$size])) {
                $stock[$key]['size'][$size] = 0;
            }

            if ($row['action_type'] === 'SCAN_IN_WAREHOUSE') {
                $stock[$key]['size'][$size] += $qty;
            } else { // SCAN_OUT_TO_VENDOR
                $stock[$key]['size'][$size] -= $qty;
            }
        }

        if ($debug) {
            $log[] = [
                'key'    => $key,
                'action' => $row['action_type'],
                'size'   => $stock[$key]['size']
            ];
        }
    }

    // ===============================
    // SUMMARY PER NCVS
    // ===============================
    $summary = [];

    foreach ($stock as $row) {
        $ncvs  = $row['ncvs'];
        $total = array_sum($row['size']);

        if (!isset($summary[$ncvs])) {
            $summary[$ncvs] = [
                'ncvs'       => $ncvs,
                'total_qty' => 0,
                'detail'    => []
            ];
        }

        $summary[$ncvs]['total_qty'] += $total;

        $summary[$ncvs]['detail'][] = [
            'job_order' => $row['job_order'],
            'bucket'    => $row['bucket'],
            'po_code'   => $row['po_code'],
            'po_item'   => $row['po_item'],
            'style'     => $row['style'],
            'model'     => $row['model'],
            'size'      => $row['size'],
            'total'     => $total
        ];
    }

    echo json_encode(
        $debug
            ? ['log' => $log, 'result' => array_values($summary)]
            : array_values($summary)
    );
    exit;
}

// ---------------- SCAN_OUT_TO_VENDOR (FINAL & STABLE) ----------------
if ($filter === 'SCAN_OUT_TO_VENDOR') {

    $stock = [];           // detail fisik (PO + size)
    $confirmByNcvs = [];   // confirm hanya total

    // ===============================
    // 1. AMBIL TRANSAKSI FISIK
    // ===============================
    $sql = <<<SQL
SELECT 
    action_type,
    new_data,
    JSON_UNQUOTE(JSON_EXTRACT(new_data,'$.job_order')) AS job_order,
    JSON_UNQUOTE(JSON_EXTRACT(new_data,'$.bucket')) AS bucket,
    JSON_UNQUOTE(JSON_EXTRACT(new_data,'$.ncvs')) AS ncvs,
    JSON_UNQUOTE(JSON_EXTRACT(new_data,'$.po_code')) AS po_code,
    JSON_UNQUOTE(JSON_EXTRACT(new_data,'$.po_item')) AS po_item,
    JSON_UNQUOTE(JSON_EXTRACT(new_data,'$.style')) AS style,
    JSON_UNQUOTE(JSON_EXTRACT(new_data,'$.model')) AS model,
    JSON_UNQUOTE(JSON_EXTRACT(new_data,'$.komponen_qty')) AS komponen_qty,
    COALESCE(
        JSON_UNQUOTE(JSON_EXTRACT(new_data,'$.scan_at')),
        JSON_UNQUOTE(JSON_EXTRACT(new_data,'$.date_created'))
    ) AS scan_at
FROM tlog_transaksi
WHERE action_type IN ('SCAN_OUT_TO_VENDOR','SCAN_IN_INCOMING')
ORDER BY scan_at ASC
SQL;

    $res = mysqli_query($conn, $sql);

    while ($row = mysqli_fetch_assoc($res)) {

        if (!$row['job_order'] || !$row['ncvs']) continue;

        // 🔑 COMPOSITE KEY IDENTIK DENGAN IN WH
        $key = implode('__', [
            $row['job_order'],
            $row['bucket'],
            $row['po_code'],
            $row['po_item'],
            $row['ncvs']
        ]);

        if (!isset($stock[$key])) {
            $stock[$key] = [
                'job_order' => $row['job_order'],
                'bucket'    => $row['bucket'],
                'ncvs'      => $row['ncvs'],
                'po_code'   => $row['po_code'],
                'po_item'   => $row['po_item'],
                'style'     => $row['style'],
                'model'     => $row['model'],
                'size'      => []
            ];
        }

        $komponen = json_decode($row['komponen_qty'] ?? '[]', true);
        if (!is_array($komponen)) $komponen = [];

        foreach ($komponen as $c) {
            $size = $c['size'];
            $qty  = (int)$c['qty'];

            if (!isset($stock[$key]['size'][$size])) {
                $stock[$key]['size'][$size] = 0;
            }

            if ($row['action_type'] === 'SCAN_OUT_TO_VENDOR') {
                $stock[$key]['size'][$size] += $qty;
            }

            if ($row['action_type'] === 'SCAN_IN_INCOMING') {
                $stock[$key]['size'][$size] -= $qty;
            }
        }
    }

    // ===============================
    // 2. CONFIRM_KEKURANGAN (TOTAL ONLY)
    // ===============================
    $sqlConf = "
        SELECT 
            JSON_EXTRACT(new_data,'$.id_trans_asal') AS id_trans_asal,
            JSON_EXTRACT(new_data,'$.total_kekurangan') AS total_kekurangan
        FROM tlog_transaksi
        WHERE action_type = 'CONFIRM_KEKURANGAN'
    ";

    $resConf = mysqli_query($conn, $sqlConf);

    while ($row = mysqli_fetch_assoc($resConf)) {

        $idAsal = (int)$row['id_trans_asal'];
        $qtyConf = (int)$row['total_kekurangan'];

        // cari NCVS dari transaksi asal
        $sqlNcvs = "
            SELECT JSON_UNQUOTE(JSON_EXTRACT(new_data,'$.ncvs')) AS ncvs
            FROM tlog_transaksi
            WHERE JSON_EXTRACT(new_data,'$.id_trans') = {$idAsal}
            LIMIT 1
        ";

        $resN = mysqli_query($conn, $sqlNcvs);
        if ($resN && $r = mysqli_fetch_assoc($resN)) {
            $ncvs = $r['ncvs'];
            $confirmByNcvs[$ncvs] = ($confirmByNcvs[$ncvs] ?? 0) + $qtyConf;
        }
    }

    // ===============================
    // 3. SUMMARY PER NCVS
    // ===============================
    $summary = [];

    foreach ($stock as $row) {

        $rowTotal = array_sum($row['size']);
        if ($rowTotal <= 0) continue;

        $ncvs = $row['ncvs'];

        if (!isset($summary[$ncvs])) {
            $summary[$ncvs] = [
                'ncvs' => $ncvs,
                'wip_out_vendor' => 0,
                'detail' => []
            ];
        }

        $summary[$ncvs]['wip_out_vendor'] += $rowTotal;
        $summary[$ncvs]['detail'][] = array_merge($row, [
            'total' => $rowTotal
        ]);
    }

    // ===============================
    // 4. APPLY CONFIRM (FINAL STEP)
    // ===============================
    foreach ($summary as $ncvs => &$s) {
        $s['wip_out_vendor'] =
            max(0, $s['wip_out_vendor'] - ($confirmByNcvs[$ncvs] ?? 0));
    }

    echo json_encode(array_values($summary));
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
