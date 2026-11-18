<?php
/** The signed-in officer's own submissions (filter, search, paging). */
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/helpers.php';
requireFieldLogin();

$me = currentUser();
$statusExpr = statusCaseSql('k');

$q       = trim($_GET['q'] ?? '');
$status  = $_GET['status'] ?? 'all';
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;

$where = ['k.officer_id = ?'];
$params = [(int)$me['id']];

if ($q !== '') {
    $where[] = '(k.reference_no LIKE ? OR CONCAT_WS(" ", k.customer_first_name, k.customer_last_name) LIKE ?
                 OR k.business_name LIKE ? OR k.id_number LIKE ? OR k.phone_number LIKE ?)';
    $t = '%' . $q . '%';
    array_push($params, $t, $t, $t, $t, $t);
}
$baseSql = ' WHERE ' . implode(' AND ', $where);

try {
    $counts = ['all' => 0, 'review' => 0, 'approved' => 0, 'rejected' => 0];
    $c = $pdo->prepare("SELECT $statusExpr AS s, COUNT(*) AS c FROM kyc_submissions k $baseSql GROUP BY s");
    $c->execute($params);
    foreach ($c as $r) {
        $key = in_array($r['s'], ['pending', 'compliance'], true) ? 'review' : $r['s'];
        if ($key !== 'rejected') $counts[$key] += (int)$r['c'];
        $counts['all'] += (int)$r['c'];
    }
    $rc = $pdo->prepare("SELECT COUNT(*) FROM kyc_submissions k $baseSql AND ($statusExpr) = 'rejected' AND " . notRecapturedSql('k'));
    $rc->execute($params);
    $counts['rejected'] = (int)$rc->fetchColumn();

    $listWhere = $where;
    $listParams = $params;
    if ($status === 'review') {
        $listWhere[] = "($statusExpr) IN ('pending','compliance')";
    } elseif ($status === 'approved') {
        $listWhere[] = "($statusExpr) = 'approved'";
    } elseif ($status === 'rejected') {
        $listWhere[] = "($statusExpr) = 'rejected' AND " . notRecapturedSql('k');
    }
    $listSql = ' WHERE ' . implode(' AND ', $listWhere);

    $t = $pdo->prepare("SELECT COUNT(*) FROM kyc_submissions k $listSql");
    $t->execute($listParams);
    $total = (int)$t->fetchColumn();
    $offset = ($page - 1) * $perPage;

    $s = $pdo->prepare("SELECT k.id, k.reference_no, k.account_type, k.customer_first_name, k.customer_last_name,
                               k.business_name, k.submitted_at, k.rejection_reason, $statusExpr AS wf,
                               NOT (" . notRecapturedSql('k') . ") AS recaptured
                        FROM kyc_submissions k $listSql
                        ORDER BY k.submitted_at DESC, k.id DESC LIMIT $perPage OFFSET $offset");
    $s->execute($listParams);
    $rows = [];
    foreach ($s as $r) {
        $rows[] = [
            'id' => (int)$r['id'],
            'reference_no' => $r['reference_no'],
            'name' => customerName($r),
            'account_type' => strtolower($r['account_type'] ?: 'personal'),
            'submitted_at' => $r['submitted_at'],
            'status' => $r['wf'],
            'rejection_reason' => $r['rejection_reason'] ?? null,
            'recaptured' => (bool)$r['recaptured'],
        ];
    }

    jsonResponse([
        'success' => true,
        'rows' => $rows,
        'counts' => $counts,
        'total' => $total,
        'page' => $page,
        'has_more' => $offset + count($rows) < $total,
    ]);
} catch (Throwable $e) {
    jsonResponse(['success' => false, 'message' => 'Could not load your submissions.'], 500);
}
