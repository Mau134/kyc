<?php
require __DIR__ . '/includes/bootstrap.php';

$id = (int)($_GET['id'] ?? 0);
$s = $pdo->prepare('SELECT * FROM kyc_submissions WHERE id = ? AND officer_id = ? LIMIT 1');
$s->execute([$id, (int)$me['id']]);
$row = $s->fetch();

$fTitle = 'KYC details';
$fBack = 'submissions.php';
$fNav = 'list';

if (!$row) {
    http_response_code(404);
    require __DIR__ . '/includes/top.php'; ?>
    <div class="f-list"><div class="f-empty"><div class="empty-icon"><?= icon('alert', 24) ?></div>
        <strong>Record not found</strong>It may belong to another officer or the link is wrong.</div></div>
    <?php require __DIR__ . '/includes/bottom.php';
    exit;
}

$wf = workflowStatus($row);
$recapturedNote = $wf === 'rejected' ? isRecaptured($pdo, $id) : null;
$isBiz = strtolower($row['account_type'] ?? '') === 'business';
$name = customerName($row);
$fSubtitle = $row['reference_no'];

$logs = [];
try {
    $l = $pdo->prepare('SELECT l.*, COALESCE(u.full_name, "Office") AS actor FROM kyc_audit_log l
                        LEFT JOIN users u ON u.id = l.performed_by WHERE l.kyc_id = ? ORDER BY l.created_at DESC, l.id DESC');
    $l->execute([$id]);
    $logs = $l->fetchAll();
} catch (Throwable $e) {
}

$statusText = [
    'pending'    => ['warn', 'clock', 'Waiting for supervisor review.'],
    'compliance' => ['', 'shield', 'Supervisor approved. Waiting for compliance sign-off.'],
    'approved'   => ['ok', 'check-circle', 'Fully approved — the customer is KYC cleared.'],
    'rejected'   => ['bad', 'x-circle', 'Returned by the office. Recapture this customer with the issue fixed.'],
][$wf];

function rv(string $label, $value): string {
    $v = trim((string)($value ?? ''));
    return '<div class="rv-row"><span>' . e($label) . '</span><b class="' . ($v === '' ? 'missing' : '') . '">' . ($v === '' ? '—' : e($v)) . '</b></div>';
}
function doc(string $label, ?string $path, bool $sig = false): string {
    if (!$path) {
        return '<figure><div class="thumb">' . icon('file', 20) . '</div>' . e($label) . '</figure>';
    }
    $url = '../' . ltrim($path, '/');
    if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'pdf') {
        return '<figure><a class="thumb" href="' . e($url) . '" target="_blank">' . icon('file', 22) . '</a>' . e($label) . '</figure>';
    }
    return '<figure><div class="thumb' . ($sig ? ' sig' : '') . '" data-lightbox="' . e($url) . '" data-title="' . e($label) . '"><img src="' . e($url) . '" alt="" loading="lazy"></div>' . e($label) . '</figure>';
}

require __DIR__ . '/includes/top.php';
?>

<div class="f-detail-head">
    <div class="record-avatar">
        <?= e(initials($name)) ?>
        <?php if (!empty($row['face_photo_path'])): ?><img data-load src="../<?= e(ltrim($row['face_photo_path'], '/')) ?>" alt=""><?php endif; ?>
    </div>
    <div style="min-width:0;flex:1">
        <h2><?= e($name) ?></h2>
        <div class="mono"><?= e($row['reference_no']) ?></div>
        <div style="margin-top:6px;display:flex;gap:6px;flex-wrap:wrap">
            <span class="tag <?= $isBiz ? 'business' : 'personal' ?>"><?= $isBiz ? 'Business' : 'Personal' ?></span>
            <span class="tag"><?= e(formatDateTime($row['submitted_at'] ?? null)) ?></span>
        </div>
    </div>
</div>

<div class="notice <?= $statusText[0] ?>"><?= icon($statusText[1], 18) ?>
    <span><?= e($statusText[2]) ?>
        <?php if ($wf === 'rejected' && !empty($row['rejection_reason'])): ?><br><strong>Reason:</strong> <?= e($row['rejection_reason']) ?><?php endif; ?>
    </span>
</div>

<?php if ($wf === 'rejected' && $recapturedNote): ?>
    <div class="notice"><?= icon('refresh', 18) ?><span><?= e($recapturedNote) ?>. Follow the new record in My KYC.</span></div>
<?php elseif ($wf === 'rejected'): ?>
    <a class="btn btn-primary btn-lg btn-block" href="new.php?from=<?= (int)$row['id'] ?>"><?= icon('refresh', 18) ?><span>Recapture this customer</span></a>
<?php endif; ?>

<div class="f-timeline">
    <h4 style="margin:0 0 14px;font-size:12px;text-transform:uppercase;letter-spacing:.1em;color:var(--sb-blue)">Progress</h4>
    <ul class="timeline">
        <?php foreach ($logs as $log):
            $act = strtolower($log['action'] ?? '');
            $cls = $act === 'approve' ? 'approve' : ($act === 'reject' ? 'reject' : 'submit');
            $label = $act === 'approve' ? (($log['role'] ?? '') === 'compliance' ? 'Final approval by compliance' : 'Approved by supervisor')
                   : ($act === 'reject' ? 'Returned by ' . (($log['role'] ?? '') === 'compliance' ? 'compliance' : 'supervisor')
                   : ($act === 'resubmit' ? 'Recaptured from a returned KYC' : ($act === 'recaptured' ? 'You recaptured this customer' : ucfirst($act)))); ?>
            <li>
                <span class="t-dot <?= $cls ?>"><?= icon($cls === 'reject' ? 'x' : ($cls === 'submit' ? 'refresh' : 'check'), 12) ?></span>
                <strong><?= e($label) ?></strong>
                <div class="t-meta"><?= e($log['actor']) ?> · <?= e(formatDateTime($log['created_at'] ?? null)) ?></div>
                <?php if (trim((string)($log['notes'] ?? '')) !== ''): ?><div class="t-note"><?= e($log['notes']) ?></div><?php endif; ?>
            </li>
        <?php endforeach; ?>
        <li>
            <span class="t-dot submit"><?= icon('arrow-right', 12) ?></span>
            <strong>Submitted by you</strong>
            <div class="t-meta"><?= e(formatDateTime($row['submitted_at'] ?? null)) ?></div>
        </li>
    </ul>
</div>

<div>
    <?php if ($isBiz): ?>
    <div class="rv-section">
        <div class="rv-head"><h4>Business</h4></div>
        <?= rv('Business name', $row['business_name'] ?? '') ?>
        <?= rv('Trading name', $row['trading_name'] ?? '') ?>
        <?= rv('Registration no.', $row['business_registration_no'] ?? '') ?>
        <?= rv('TPIN', $row['tax_pin'] ?? '') ?>
        <?= rv('Contact person', $row['contact_person_name'] ?? '') ?>
        <?= rv('Contact phone', $row['contact_person_phone'] ?? '') ?>
    </div>
    <?php endif; ?>
    <div class="rv-section">
        <div class="rv-head"><h4><?= $isBiz ? 'Signatory' : 'Customer' ?></h4></div>
        <?= rv('Name', trim(($row['customer_first_name'] ?? '') . ' ' . ($row['customer_last_name'] ?? ''))) ?>
        <?= rv('Date of birth', $row['date_of_birth'] ?? '') ?>
        <?= rv('Gender', $row['gender'] ?? '') ?>
        <?= rv($row['id_type'] ?: 'ID', $row['id_number'] ?? '') ?>
        <?= rv('Phone', $row['phone_number'] ?? '') ?>
        <?= rv('Email', $row['email'] ?? '') ?>
    </div>
    <div class="rv-section">
        <div class="rv-head"><h4>Address & income</h4></div>
        <?= rv('Address', $row['address_line'] ?? '') ?>
        <?= rv('District', $row['district'] ?? '') ?>
        <?= rv('Home village', trim(($row['home_village'] ?? '') . (($row['home_ta'] ?? '') ? ', TA ' . $row['home_ta'] : ''))) ?>
        <?= rv('Employer', $row['employer_name'] ?? '') ?>
        <?= rv('Monthly income', is_numeric($row['monthly_income'] ?? null) ? 'MWK ' . number_format((float)$row['monthly_income']) : ($row['monthly_income'] ?? '')) ?>
    </div>
    <div class="rv-section">
        <div class="rv-head"><h4>Documents</h4></div>
        <div class="rv-docs">
            <?= doc('ID', $row['id_document_path'] ?? null) ?>
            <?= doc('Photo', $row['face_photo_path'] ?? null) ?>
            <?= doc('Income', $row['income_document_path'] ?? null) ?>
            <?= doc('Signature', $row['signature_path'] ?? null, true) ?>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/bottom.php'; ?>
