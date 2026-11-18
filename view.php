<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/helpers.php';
requireDashboardLogin();

$me = currentUser();
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

try {
    $stmt = $pdo->prepare('SELECT k.*, u.full_name AS officer_name, u.username AS officer_username,
                                  rv.full_name AS reviewer_name, co.full_name AS compliance_name
                           FROM kyc_submissions k
                           LEFT JOIN users u  ON u.id  = k.officer_id
                           LEFT JOIN users rv ON rv.id = k.reviewed_by
                           LEFT JOIN users co ON co.id = k.compliance_by
                           WHERE k.id = ? LIMIT 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
} catch (Throwable $e) {
    // Older schema without reviewed_by / compliance_by columns
    $stmt = $pdo->prepare('SELECT k.*, u.full_name AS officer_name, u.username AS officer_username
                           FROM kyc_submissions k LEFT JOIN users u ON u.id = k.officer_id
                           WHERE k.id = ? LIMIT 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
}

$pageTitle = 'KYC Record';
$activeNav = 'records';
$pageJs = 'view.js';

if (!$row) {
    http_response_code(404);
    $pageSubtitle = 'Record not found';
    require __DIR__ . '/includes/header.php'; ?>
    <section class="card"><div class="empty-state">
        <div class="empty-icon"><?= icon('alert', 24) ?></div>
        <strong>We couldn't find that KYC record</strong>
        It may have been removed, or the link is incorrect.
        <div style="margin-top:16px"><a class="btn btn-primary" href="records.php"><?= icon('arrow-left', 15) ?><span>Back to records</span></a></div>
    </div></section>
    <?php require __DIR__ . '/includes/footer.php';
    exit;
}

$wf       = workflowStatus($row);
$meta     = statusMeta($wf);
$isBiz    = strtolower($row['account_type'] ?? '') === 'business';
$name     = customerName($row);
$stage    = strtolower($row['review_stage'] ?? 'submitted');
$role     = $me['role'] ?? '';
$canAct   = ($role === 'supervisor' && $wf === 'pending') || ($role === 'compliance' && $wf === 'compliance');
$pageSubtitle = $row['reference_no'];

// Audit trail (table may be missing on older installs)
$logs = [];
try {
    $l = $pdo->prepare('SELECT l.*, COALESCE(u.full_name, CONCAT("User #", l.performed_by)) AS actor
                        FROM kyc_audit_log l LEFT JOIN users u ON u.id = l.performed_by
                        WHERE l.kyc_id = ? ORDER BY l.created_at DESC, l.id DESC');
    $l->execute([$row['id']]);
    $logs = $l->fetchAll();
} catch (Throwable $e) {
    $logs = [];
}

function field(string $label, $value): string {
    $v = trim((string)($value ?? ''));
    return '<div class="detail"><div class="k">' . e($label) . '</div><div class="v' . ($v === '' ? ' empty' : '') . '">' . ($v === '' ? 'Not provided' : e($v)) . '</div></div>';
}

function docTile(string $title, ?string $path, bool $signature = false): string {
    if (empty($path)) return '';
    $url = e(ltrim($path, '/'));
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $isPdf = $ext === 'pdf';
    $thumb = $isPdf
        ? '<a class="doc-thumb" href="' . $url . '" target="_blank" rel="noopener" style="cursor:pointer;flex-direction:column;gap:6px">' . icon('file', 34) . '<span style="font-size:12px">PDF document</span></a>'
        : '<div class="doc-thumb' . ($signature ? ' signature' : '') . '" data-lightbox="' . $url . '" data-title="' . e($title) . '">'
            . '<span class="skel"></span>'
            . '<img data-load src="' . $url . '" alt="' . e($title) . '" loading="lazy">'
            . '<div class="img-error">' . icon('alert', 22) . '<span>File missing on server</span></div>'
            . '<span class="zoom-hint">' . icon('zoom', 16) . '</span></div>';
    return '<div class="doc">' . $thumb
        . '<div class="doc-info"><strong>' . e($title) . '</strong><small>' . e(strtoupper($ext ?: 'file')) . '</small>'
        . '<a class="icon-btn" style="width:30px;height:30px" href="' . $url . '" download title="Download">' . icon('download', 15) . '</a>'
        . '<a class="icon-btn" style="width:30px;height:30px" href="' . $url . '" target="_blank" rel="noopener" title="Open in new tab">' . icon('external', 15) . '</a>'
        . '</div></div>';
}

// Stepper
$steps = [
    ['Submitted', formatDateTime($row['submitted_at'] ?? null, 'd M, H:i'), 'done'],
    ['Supervisor review', '', ''],
    ['Compliance', '', ''],
    [$wf === 'rejected' ? 'Rejected' : 'Approved', '', ''],
];
if ($wf === 'pending') {
    $steps[1][2] = 'current'; $steps[1][1] = 'In progress';
    $steps[2][1] = 'Waiting'; $steps[3][1] = 'Waiting';
} elseif ($wf === 'compliance') {
    $steps[1][2] = 'done'; $steps[1][1] = formatDateTime($row['reviewed_at'] ?? null, 'd M, H:i');
    $steps[2][2] = 'current'; $steps[2][1] = 'In progress'; $steps[3][1] = 'Waiting';
} elseif ($wf === 'approved') {
    $steps[1][2] = 'done'; $steps[1][1] = formatDateTime($row['reviewed_at'] ?? null, 'd M, H:i');
    $steps[2][2] = 'done'; $steps[2][1] = formatDateTime($row['compliance_at'] ?? null, 'd M, H:i');
    $steps[3][2] = 'done'; $steps[3][1] = 'Account cleared';
} else { // rejected
    if ($stage === 'reviewed') {
        $steps[1][2] = 'done'; $steps[1][1] = formatDateTime($row['reviewed_at'] ?? null, 'd M, H:i');
        $steps[2][2] = 'failed'; $steps[2][1] = 'Rejected';
    } else {
        $steps[1][2] = 'failed'; $steps[1][1] = 'Rejected';
        $steps[2][1] = 'Skipped';
    }
    $steps[3][2] = 'failed'; $steps[3][1] = 'Not onboarded';
}

require __DIR__ . '/includes/header.php';
?>

<div class="no-print" style="margin-bottom:14px; display:flex; gap:10px; align-items:center">
    <a class="btn btn-light btn-sm" href="records.php" id="backLink"><?= icon('arrow-left', 15) ?><span>Back to records</span></a>
</div>

<section class="card" style="margin-bottom:18px">
    <div class="record-head">
        <div class="record-avatar">
            <?= e(initials($name)) ?>
            <?php if (!empty($row['face_photo_path'])): ?>
                <img data-load src="<?= e(ltrim($row['face_photo_path'], '/')) ?>" alt="">
            <?php endif; ?>
        </div>
        <div class="record-title">
            <h2><?= e($name) ?></h2>
            <div class="meta">
                <span class="mono"><?= icon('file', 14) ?><?= e($row['reference_no']) ?></span>
                <span><?= icon($isBiz ? 'briefcase' : 'user', 14) ?><?= $isBiz ? 'Business account' : 'Personal account' ?></span>
                <span><?= icon('user', 14) ?>Captured by <?= e($row['officer_name'] ?: 'Unknown officer') ?><?= !empty($row['officer_username']) ? ' (' . e($row['officer_username']) . ')' : '' ?></span>
                <span><?= icon('clock', 14) ?><?= e(formatDateTime($row['submitted_at'] ?? null)) ?></span>
            </div>
        </div>
        <div class="actions-inline">
            <span class="pill lg <?= e($meta['class']) ?>"><?= e($meta['label']) ?></span>
            <button class="btn btn-light btn-sm no-print" onclick="window.print()"><?= icon('printer', 15) ?><span>Print</span></button>
        </div>
    </div>
    <div class="stepper">
        <?php foreach ($steps as $i => [$label, $sub, $state]): ?>
            <div class="step <?= e($state) ?>">
                <span class="bubble">
                    <?php if ($state === 'done'): ?><?= icon('check', 16) ?>
                    <?php elseif ($state === 'failed'): ?><?= icon('x', 16) ?>
                    <?php else: ?><?= $i + 1 ?><?php endif; ?>
                </span>
                <strong><?= e($label) ?></strong>
                <small><?= e($sub ?: '—') ?></small>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<div class="grid grid-2-1">
    <div class="stack">
        <section class="card">
            <div class="card-head"><div><h3>Customer details</h3><p>As captured in the field by the officer</p></div></div>

            <?php if ($isBiz): ?>
            <h4 class="section-title"><?= icon('briefcase', 15) ?>Business</h4>
            <div class="details">
                <?= field('Business name', $row['business_name'] ?? '') ?>
                <?= field('Trading name', $row['trading_name'] ?? '') ?>
                <?= field('Registration no.', $row['business_registration_no'] ?? '') ?>
                <?= field('Tax PIN (TPIN)', $row['tax_pin'] ?? '') ?>
                <?= field('Contact person', $row['contact_person_name'] ?? '') ?>
                <?= field('Contact phone', $row['contact_person_phone'] ?? '') ?>
            </div>
            <?php endif; ?>

            <h4 class="section-title"><?= icon('id', 15) ?><?= $isBiz ? 'Principal / signatory' : 'Personal & identity' ?></h4>
            <div class="details">
                <?= field('First name', $row['customer_first_name'] ?? '') ?>
                <?= field('Last name', $row['customer_last_name'] ?? '') ?>
                <?= field('Date of birth', $row['date_of_birth'] ?? '') ?>
                <?= field('Gender', $row['gender'] ?? '') ?>
                <?= field('Nationality', $row['nationality'] ?? '') ?>
                <?= field('ID type', $row['id_type'] ?? '') ?>
                <?= field('ID number', $row['id_number'] ?? '') ?>
                <?= field('Phone', $row['phone_number'] ?? '') ?>
                <?= field('Email', $row['email'] ?? '') ?>
            </div>

            <h4 class="section-title"><?= icon('home', 15) ?>Residence</h4>
            <div class="details">
                <?= field('Current address', $row['address_line'] ?? '') ?>
                <?= field('District', $row['district'] ?? '') ?>
                <?= field('Country', $row['country'] ?? '') ?>
                <?= field('Home village', $row['home_village'] ?? '') ?>
                <?= field('Traditional authority', $row['home_ta'] ?? '') ?>
                <?= field('Home district', $row['home_district'] ?? '') ?>
            </div>

            <h4 class="section-title"><?= icon('wallet', 15) ?>Employment & income</h4>
            <div class="details" style="margin-bottom:6px">
                <?= field('Employer', $row['employer_name'] ?? '') ?>
                <?= field('Monthly income', ($row['monthly_income'] ?? '') !== '' && is_numeric($row['monthly_income']) ? 'MWK ' . number_format((float)$row['monthly_income'], 2) : ($row['monthly_income'] ?? '')) ?>
            </div>
        </section>

        <section class="card">
            <div class="card-head"><div><h3>Documents</h3><p>Click a document to inspect it full-size</p></div></div>
            <div class="card-body">
                <div class="docs">
                    <?= docTile('ID document', $row['id_document_path'] ?? null) ?>
                    <?= docTile('Face photo', $row['face_photo_path'] ?? null) ?>
                    <?= docTile('Income document', $row['income_document_path'] ?? null) ?>
                    <?= docTile('Customer signature', $row['signature_path'] ?? null, true) ?>
                </div>
                <?php if (empty($row['id_document_path']) && empty($row['face_photo_path']) && empty($row['income_document_path']) && empty($row['signature_path'])): ?>
                    <p class="muted" style="margin:0">No documents were uploaded with this submission.</p>
                <?php endif; ?>
            </div>
        </section>
    </div>

    <div class="stack side-sticky">
        <section class="card decision no-print">
            <div class="card-head"><div><h3>Decision</h3><p><?= e(roleLabel($role)) ?></p></div></div>
            <div class="card-body">
                <?php if ($canAct): ?>
                    <div class="notice" style="margin-bottom:14px">
                        <?= icon('shield', 18) ?>
                        <span><?= $role === 'supervisor'
                            ? 'Check the details against the documents. Approving sends this record to Compliance for final sign-off.'
                            : 'Final compliance sign-off. Approving clears this customer for account opening.' ?></span>
                    </div>
                    <form id="decisionForm" data-id="<?= (int)$row['id'] ?>" data-role="<?= e($role) ?>">
                        <label for="notes">Notes <span class="muted" style="font-weight:400">(required when rejecting)</span></label>
                        <textarea id="notes" name="notes" maxlength="1000" placeholder="<?= $role === 'supervisor' ? 'e.g. ID verified against original, photo matches' : 'e.g. Sanctions & PEP screening clear' ?>"></textarea>
                        <div class="btns">
                            <button type="button" class="btn btn-danger" data-action="reject"><?= icon('x-circle', 16) ?><span>Reject</span></button>
                            <button type="button" class="btn btn-success" data-action="approve"><?= icon('check-circle', 16) ?><span><?= $role === 'supervisor' ? 'Approve' : 'Final approve' ?></span></button>
                        </div>
                    </form>
                <?php elseif ($wf === 'approved'): ?>
                    <div class="notice ok"><?= icon('check-circle', 18) ?><span>Fully approved<?= !empty($row['compliance_name']) ? ' by ' . e($row['compliance_name']) : '' ?><?= !empty($row['compliance_at']) ? ' on ' . e(formatDateTime($row['compliance_at'])) : '' ?>.</span></div>
                <?php elseif ($wf === 'rejected'): ?>
                    <div class="notice bad"><?= icon('x-circle', 18) ?>
                        <span><strong>Rejected.</strong><?php if (!empty($row['rejection_reason'])): ?><br>Reason: <?= e($row['rejection_reason']) ?><?php endif; ?></span>
                    </div>
                <?php elseif ($wf === 'compliance'): ?>
                    <div class="notice warn"><?= icon('clock', 18) ?><span>Reviewed<?= !empty($row['reviewer_name']) ? ' by ' . e($row['reviewer_name']) : '' ?>. Waiting for compliance sign-off.</span></div>
                <?php else: ?>
                    <div class="notice warn"><?= icon('clock', 18) ?><span>Waiting for supervisor review.</span></div>
                <?php endif; ?>

                <?php if (!$canAct && $role === 'admin' && in_array($wf, ['pending', 'compliance'], true)): ?>
                    <p class="muted" style="margin:12px 0 0;font-size:12.5px">Operations admins have read-only access. Decisions are made by supervisors and compliance officers.</p>
                <?php endif; ?>
            </div>
        </section>

        <section class="card">
            <div class="card-head"><div><h3>Audit trail</h3><p>Every action on this record</p></div></div>
            <div class="card-body">
                <ul class="timeline">
                    <?php foreach ($logs as $log):
                        $act = strtolower($log['action'] ?? '');
                        $cls = $act === 'approve' ? 'approve' : ($act === 'reject' ? 'reject' : '');
                        $verb = $act === 'approve'
                            ? (($log['role'] ?? '') === 'compliance' ? 'Final approval' : 'Reviewed & approved')
                            : ($act === 'reject' ? 'Rejected' : ($act === 'resubmit' ? 'Recaptured by field officer' : ($act === 'recaptured' ? 'Replaced by a recapture' : ucfirst($act))));
                    ?>
                        <li>
                            <span class="t-dot <?= $cls ?>"><?= icon($cls === 'reject' ? 'x' : 'check', 12) ?></span>
                            <strong><?= e($verb) ?></strong>
                            <div class="t-meta"><?= e($log['actor']) ?> · <?= e(roleLabel($log['role'] ?? '')) ?> · <?= e(formatDateTime($log['created_at'] ?? null)) ?></div>
                            <?php if (trim((string)($log['notes'] ?? '')) !== ''): ?>
                                <div class="t-note"><?= nl2br(e($log['notes'])) ?></div>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                    <li>
                        <span class="t-dot submit"><?= icon('arrow-right', 12) ?></span>
                        <strong>Submitted from the field</strong>
                        <div class="t-meta"><?= e($row['officer_name'] ?: 'Unknown officer') ?> · Field Officer · <?= e(formatDateTime($row['submitted_at'] ?? null)) ?></div>
                    </li>
                </ul>
            </div>
        </section>
    </div>
</div>

<?php if ($canAct): ?>
<div class="modal" id="confirmModal" role="dialog" aria-modal="true" aria-labelledby="mTitle">
    <div class="modal-card">
        <div class="m-body">
            <div class="m-icon" id="mIcon"></div>
            <h3 id="mTitle"></h3>
            <p id="mText"></p>
        </div>
        <div class="m-foot">
            <button type="button" class="btn btn-light" data-close><span>Cancel</span></button>
            <button type="button" class="btn" id="mConfirm"><span id="mConfirmLabel">Confirm</span></button>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
