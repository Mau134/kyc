<?php
/** Paged, filterable KYC list for records.php (session-protected). */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';
requireDashboardLogin();

$me = currentUser();
$statusExpr = statusCaseSql('k');

$q       = trim($_GET['q'] ?? '');
$status  = $_GET['status'] ?? 'all';
$type    = $_GET['type'] ?? '';
$queue   = ($_GET['queue'] ?? '') === 'mine';
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = min(50, max(5, (int)($_GET['per_page'] ?? 12)));
$sort    = $_GET['sort'] ?? 'newest';

$where = [];
$params = [];

if ($q !== '') {
    $where[] = '(k.reference_no LIKE ? OR k.customer_first_name LIKE ? OR k.customer_last_name LIKE ?
                 OR CONCAT_WS(" ", k.customer_first_name, k.customer_last_name) LIKE ?
                 OR k.business_name LIKE ? OR k.id_number LIKE ? OR k.phone_number LIKE ?)';
    $term = '%' . $q . '%';
    array_push($params, $term, $term, $term, $term, $term, $term, $term);
}
if (in_array($type, ['personal', 'business'], true)) {
    $where[] = 'LOWER(k.account_type) = ?';
    $params[] = $type;
}
if ($queue) {
    $where[] = queueSql($me['role']);
}

$baseWhere = $where ? ' WHERE ' . implode(' AND ', $where) : '';

try {
    // Tab counts respect search/type/queue but not the status tab itself
    $counts = ['all' => 0, 'pending' => 0, 'compliance' => 0, 'approved' => 0, 'rejected' => 0];
    $cStmt = $pdo->prepare("SELECT $statusExpr AS s, COUNT(*) AS c FROM kyc_submissions k $baseWhere GROUP BY s");
    $cStmt->execute($params);
    foreach ($cStmt as $r) {
        $counts[$r['s']] = (int)$r['c'];
        $counts['all'] += (int)$r['c'];
    }

    $listWhere = $where;
    $listParams = $params;
    if (in_array($status, ['pending', 'compliance', 'approved', 'rejected'], true)) {
        $listWhere[] = "($statusExpr) = ?";
        $listParams[] = $status;
    }
    $listWhereSql = $listWhere ? ' WHERE ' . implode(' AND ', $listWhere) : '';

    $total = (int)(function () use ($pdo, $listWhereSql, $listParams) {
        $s = $pdo->prepare("SELECT COUNT(*) FROM kyc_submissions k $listWhereSql");
        $s->execute($listParams);
        return $s->fetchColumn();
    })();

    $pages = max(1, (int)ceil($total / $perPage));
    $page = min($page, $pages);
    $offset = ($page - 1) * $perPage;

    $order = $sort === 'oldest' ? 'k.submitted_at ASC' : 'k.submitted_at DESC';

    $sql = "SELECT k.id, k.reference_no, k.account_type, k.customer_first_name, k.customer_last_name,
                   k.business_name, k.phone_number, k.id_type, k.id_number, k.status, k.review_stage,
                   k.submitted_at, $statusExpr AS wf, u.full_name AS officer_name
            FROM kyc_submissions k
            LEFT JOIN users u ON u.id = k.officer_id
            $listWhereSql
            ORDER BY $order, k.id DESC
            LIMIT $perPage OFFSET $offset";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($listParams);

    $rows = [];
    foreach ($stmt as $r) {
        $rows[] = [
            'id'           => (int)$r['id'],
            'reference_no' => $r['reference_no'],
            'account_type' => strtolower($r['account_type'] ?: 'personal'),
            'name'         => customerName($r),
            'phone_number' => $r['phone_number'],
            'id_type'      => $r['id_type'],
            'id_number'    => $r['id_number'],
            'officer_name' => $r['officer_name'] ?: 'Unknown officer',
            'submitted_at' => $r['submitted_at'],
            'status'       => $r['wf'],
        ];
    }

    jsonResponse([
        'success'  => true,
        'rows'     => $rows,
        'total'    => $total,
        'page'     => $page,
        'pages'    => $pages,
        'per_page' => $perPage,
        'counts'   => $counts,
    ]);
} catch (Throwable $e) {
    jsonResponse(['success' => false, 'message' => 'Could not load KYC records.'], 500);
}
