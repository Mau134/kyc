<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';

$q = trim($_GET['q'] ?? '');
$sql = 'SELECT k.id, k.reference_no, k.customer_first_name, k.customer_last_name,
               k.phone_number, k.id_type, k.id_number, k.status, k.submitted_at,
               u.full_name AS officer_name
        FROM kyc_submissions k
        JOIN users u ON u.id = k.officer_id';
$params = [];

if ($q !== '') {
    $sql .= ' WHERE k.reference_no LIKE ? OR k.customer_first_name LIKE ? OR k.customer_last_name LIKE ? OR k.id_number LIKE ?';
    $term = "%$q%";
    $params = [$term, $term, $term, $term];
}

$sql .= ' ORDER BY k.submitted_at DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

jsonResponse(['success' => true, 'data' => $rows]);
