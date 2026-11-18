<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/helpers.php';
require_once __DIR__ . '/../../includes/icons.php';
requireFieldLogin();

$me = currentUser();

/** Counts used by the bottom navigation badge (records rejected and not yet recaptured). */
function fieldReturnedCount(PDO $pdo, int $officerId): int {
    try {
        $s = $pdo->prepare("SELECT COUNT(*) FROM kyc_submissions k WHERE k.officer_id = ? AND LOWER(k.status) = 'rejected' AND " . notRecapturedSql('k'));
        $s->execute([$officerId]);
        return (int)$s->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}
