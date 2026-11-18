<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    jsonResponse(['success' => false, 'message' => 'Invalid ID'], 422);
}

$stmt = $pdo->prepare('SELECT k.*, u.full_name AS officer_name, u.username AS officer_username
                       FROM kyc_submissions k
                       JOIN users u ON u.id = k.officer_id
                       WHERE k.id = ? LIMIT 1');
$stmt->execute([$id]);
$row = $stmt->fetch();

if (!$row) {
    jsonResponse(['success' => false, 'message' => 'Record not found'], 404);
}

jsonResponse(['success' => true, 'data' => $row]);
