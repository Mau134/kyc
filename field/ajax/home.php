<?php
/** Field officer home data: personal counts + latest submissions. */
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/helpers.php';
requireFieldLogin();

$me = currentUser();
$oid = (int)$me['id'];
$status = statusCaseSql('k');

try {
    $counts = ['pending' => 0, 'compliance' => 0, 'approved' => 0, 'rejected' => 0];
    $s = $pdo->prepare("SELECT $status AS s, COUNT(*) AS c FROM kyc_submissions k WHERE k.officer_id = ? GROUP BY s");
    $s->execute([$oid]);
    foreach ($s as $r) {
        $counts[$r['s']] = (int)$r['c'];
    }

    $rt = $pdo->prepare("SELECT COUNT(*) FROM kyc_submissions k WHERE k.officer_id = ? AND LOWER(k.status) = 'rejected' AND " . notRecapturedSql('k'));
    $rt->execute([$oid]);
    $returned = (int)$rt->fetchColumn();

    $q = $pdo->prepare('SELECT
            SUM(DATE(submitted_at) = CURDATE()) AS today,
            SUM(submitted_at >= CURDATE() - INTERVAL 6 DAY) AS week,
            SUM(YEAR(submitted_at) = YEAR(CURDATE()) AND MONTH(submitted_at) = MONTH(CURDATE())) AS month
        FROM kyc_submissions WHERE officer_id = ?');
    $q->execute([$oid]);
    $p = $q->fetch() ?: [];

    // Last 7 days for the mini chart
    $days = [];
    for ($i = 6; $i >= 0; $i--) {
        $days[date('Y-m-d', strtotime("-$i day"))] = 0;
    }
    $d = $pdo->prepare('SELECT DATE(submitted_at) AS d, COUNT(*) AS c FROM kyc_submissions
                        WHERE officer_id = ? AND submitted_at >= CURDATE() - INTERVAL 6 DAY GROUP BY d');
    $d->execute([$oid]);
    foreach ($d as $r) {
        if (isset($days[$r['d']])) $days[$r['d']] = (int)$r['c'];
    }

    $r = $pdo->prepare("SELECT k.id, k.reference_no, k.account_type, k.customer_first_name, k.customer_last_name,
                               k.business_name, k.submitted_at, $status AS wf
                        FROM kyc_submissions k WHERE k.officer_id = ?
                        ORDER BY k.submitted_at DESC, k.id DESC LIMIT 5");
    $r->execute([$oid]);
    $recent = [];
    foreach ($r as $row) {
        $recent[] = [
            'id' => (int)$row['id'],
            'reference_no' => $row['reference_no'],
            'name' => customerName($row),
            'account_type' => strtolower($row['account_type'] ?: 'personal'),
            'submitted_at' => $row['submitted_at'],
            'status' => $row['wf'],
        ];
    }

    jsonResponse([
        'success' => true,
        'counts' => $counts,
        'total' => array_sum($counts),
        'returned' => $returned,
        'today' => (int)($p['today'] ?? 0),
        'week' => (int)($p['week'] ?? 0),
        'month' => (int)($p['month'] ?? 0),
        'days' => array_map(fn($k, $v) => ['date' => $k, 'count' => $v], array_keys($days), $days),
        'recent' => $recent,
    ]);
} catch (Throwable $e) {
    jsonResponse(['success' => false, 'message' => 'Could not load your dashboard.'], 500);
}
