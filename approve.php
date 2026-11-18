<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/helpers.php';
requireDashboardLogin();

/** Respond as JSON for AJAX calls, otherwise redirect back to the record. */
function finish(bool $ok, string $message, int $kycId = 0, int $code = 200): void {
    if (isAjax()) {
        jsonResponse(['success' => $ok, 'message' => $message], $ok ? 200 : $code);
    }
    $_SESSION['flash'] = ['ok' => $ok, 'message' => $message];
    header('Location: ' . ($kycId ? 'view.php?id=' . $kycId : 'records.php'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    finish(false, 'Method not allowed', 0, 405);
}

$user   = currentUser();
$role   = $user['role'] ?? '';
$kycId  = (int)($_POST['kyc_id'] ?? 0);
$action = $_POST['action'] ?? '';
$notes  = trim(mb_substr((string)($_POST['notes'] ?? ''), 0, 1000));

if (!verifyCsrf($_POST['csrf'] ?? null)) {
    finish(false, 'Security token expired. Refresh the page and try again.', $kycId, 419);
}
if (!$kycId || !in_array($action, ['approve', 'reject'], true)) {
    finish(false, 'Invalid request.', $kycId, 422);
}
if (!in_array($role, ['supervisor', 'compliance'], true)) {
    finish(false, 'Your role cannot make KYC decisions.', $kycId, 403);
}
if ($action === 'reject' && $notes === '') {
    finish(false, 'A reason is required when rejecting.', $kycId, 422);
}

try {
    $pdo->beginTransaction();

    // Lock the row so two people can't decide on the same record at once
    $stmt = $pdo->prepare('SELECT * FROM kyc_submissions WHERE id = ? FOR UPDATE');
    $stmt->execute([$kycId]);
    $kyc = $stmt->fetch();

    if (!$kyc) {
        $pdo->rollBack();
        finish(false, 'Record not found.', 0, 404);
    }

    $wf = workflowStatus($kyc);
    if (($role === 'supervisor' && $wf !== 'pending') || ($role === 'compliance' && $wf !== 'compliance')) {
        $pdo->rollBack();
        finish(false, 'This record is not at your review stage — someone may have just acted on it. Refresh to see the latest status.', $kycId, 409);
    }

    if ($role === 'supervisor') {
        if ($action === 'approve') {
            $pdo->prepare("UPDATE kyc_submissions SET review_stage = 'reviewed', reviewed_by = ?, reviewed_at = NOW() WHERE id = ?")
                ->execute([$user['id'], $kycId]);
            $msg = 'Approved and sent to Compliance';
        } else {
            $pdo->prepare("UPDATE kyc_submissions SET status = 'rejected', rejection_reason = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?")
                ->execute([$notes, $user['id'], $kycId]);
            $msg = 'KYC submission rejected';
        }
    } else { // compliance
        if ($action === 'approve') {
            $pdo->prepare("UPDATE kyc_submissions SET status = 'approved', compliance_by = ?, compliance_at = NOW() WHERE id = ?")
                ->execute([$user['id'], $kycId]);
            $msg = 'Final approval recorded — customer is KYC cleared';
        } else {
            $pdo->prepare("UPDATE kyc_submissions SET status = 'rejected', rejection_reason = ?, compliance_by = ?, compliance_at = NOW() WHERE id = ?")
                ->execute([$notes, $user['id'], $kycId]);
            $msg = 'KYC submission rejected by Compliance';
        }
    }

    $pdo->prepare('INSERT INTO kyc_audit_log (kyc_id, action, performed_by, role, notes) VALUES (?, ?, ?, ?, ?)')
        ->execute([$kycId, $action, $user['id'], $role, $notes]);

    $pdo->commit();
    finish(true, $msg, $kycId);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('KYC decision failed: ' . $e->getMessage());
    finish(false, 'Could not save the decision. Please try again.', $kycId, 500);
}
