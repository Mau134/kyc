<?php
/** Overview data for index.php (session-protected, dashboard users only). */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';
requireDashboardLogin();

$me = currentUser();
$status = statusCaseSql('k');

try {
    // Status counts
    $counts = ['pending' => 0, 'compliance' => 0, 'approved' => 0, 'rejected' => 0];
    foreach ($pdo->query("SELECT $status AS s, COUNT(*) AS c FROM kyc_submissions k GROUP BY s") as $r) {
        $counts[$r['s']] = (int)$r['c'];
    }
    $total = array_sum($counts);

    $today = (int)$pdo->query('SELECT COUNT(*) FROM kyc_submissions WHERE DATE(submitted_at) = CURDATE()')->fetchColumn();
    $yesterday = (int)$pdo->query('SELECT COUNT(*) FROM kyc_submissions WHERE DATE(submitted_at) = CURDATE() - INTERVAL 1 DAY')->fetchColumn();
    $week = (int)$pdo->query('SELECT COUNT(*) FROM kyc_submissions WHERE submitted_at >= CURDATE() - INTERVAL 6 DAY')->fetchColumn();

    // 14-day trend split by current status
    $days = 14;
    $trend = [];
    for ($i = $days - 1; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-$i day"));
        $trend[$d] = ['date' => $d, 'total' => 0, 'pending' => 0, 'compliance' => 0, 'approved' => 0, 'rejected' => 0];
    }
    $stmt = $pdo->prepare("SELECT DATE(k.submitted_at) AS d, $status AS s, COUNT(*) AS c
                           FROM kyc_submissions k
                           WHERE k.submitted_at >= CURDATE() - INTERVAL " . ($days - 1) . " DAY
                           GROUP BY d, s");
    $stmt->execute();
    foreach ($stmt as $r) {
        if (isset($trend[$r['d']])) {
            $trend[$r['d']][$r['s']] += (int)$r['c'];
            $trend[$r['d']]['total'] += (int)$r['c'];
        }
    }

    // Account type split
    $types = ['personal' => 0, 'business' => 0];
    foreach ($pdo->query("SELECT LOWER(COALESCE(account_type,'personal')) AS t, COUNT(*) AS c FROM kyc_submissions GROUP BY t") as $r) {
        $types[$r['t']] = (int)$r['c'];
    }

    // Officer leaderboard
    $officers = $pdo->query("SELECT COALESCE(u.full_name, 'Unknown officer') AS name, COUNT(*) AS c,
                                    SUM(CASE WHEN LOWER(k.status) = 'approved' THEN 1 ELSE 0 END) AS approved
                             FROM kyc_submissions k
                             LEFT JOIN users u ON u.id = k.officer_id
                             GROUP BY k.officer_id, u.full_name
                             ORDER BY c DESC LIMIT 5")->fetchAll();

    // My queue (oldest first — those need attention)
    $queueStmt = $pdo->query("SELECT k.id, k.reference_no, k.account_type, k.customer_first_name, k.customer_last_name,
                                     k.business_name, k.submitted_at, $status AS wf, u.full_name AS officer_name
                              FROM kyc_submissions k
                              LEFT JOIN users u ON u.id = k.officer_id
                              WHERE " . queueSql($me['role']) . "
                              ORDER BY k.submitted_at ASC LIMIT 7");
    $queue = [];
    foreach ($queueStmt as $r) {
        $queue[] = [
            'id' => (int)$r['id'],
            'reference_no' => $r['reference_no'],
            'name' => customerName($r),
            'account_type' => $r['account_type'],
            'submitted_at' => $r['submitted_at'],
            'status' => $r['wf'],
            'officer_name' => $r['officer_name'] ?: 'Unknown officer',
        ];
    }
    $queueTotal = (int)$pdo->query('SELECT COUNT(*) FROM kyc_submissions k WHERE ' . queueSql($me['role']))->fetchColumn();

    // Recent decisions from the audit log (table may not exist on older installs)
    $activity = [];
    try {
        $act = $pdo->query("SELECT l.action, l.role, l.notes, l.created_at, l.kyc_id, k.reference_no,
                                   COALESCE(u.full_name, CONCAT('User #', l.performed_by)) AS actor
                            FROM kyc_audit_log l
                            LEFT JOIN users u ON u.id = l.performed_by
                            LEFT JOIN kyc_submissions k ON k.id = l.kyc_id
                            WHERE l.action IN ('approve', 'reject')
                            ORDER BY l.created_at DESC LIMIT 5");
        foreach ($act as $r) {
            $activity[] = $r;
        }
    } catch (Throwable $e) {
        $activity = [];
    }

    jsonResponse([
        'success'  => true,
        'generated_at' => date('Y-m-d H:i:s'),
        'kpis' => [
            'total' => $total,
            'today' => $today,
            'yesterday' => $yesterday,
            'week' => $week,
            'counts' => $counts,
        ],
        'trend' => array_values($trend),
        'types' => $types,
        'officers' => array_map(fn($o) => ['name' => $o['name'], 'count' => (int)$o['c'], 'approved' => (int)$o['approved']], $officers),
        'queue' => $queue,
        'queue_total' => $queueTotal,
        'activity' => $activity,
    ]);
} catch (Throwable $e) {
    jsonResponse(['success' => false, 'message' => 'Could not load dashboard data.'], 500);
}
