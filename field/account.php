<?php
require __DIR__ . '/includes/bootstrap.php';

$s = $pdo->prepare("SELECT COUNT(*) AS total,
                           SUM(LOWER(status) = 'approved') AS approved,
                           MIN(submitted_at) AS first_at
                    FROM kyc_submissions WHERE officer_id = ?");
$s->execute([(int)$me['id']]);
$st = $s->fetch() ?: ['total' => 0, 'approved' => 0, 'first_at' => null];

$fTitle = 'Account';
$fNav = 'account';
require __DIR__ . '/includes/top.php';
?>

<div class="acc-card">
    <span class="avatar"><?= e(initials($me['full_name'] ?? '')) ?></span>
    <h2><?= e($me['full_name'] ?? '') ?></h2>
    <p><?= e(roleLabel($me['role'] ?? '')) ?> · @<?= e($me['username'] ?? '') ?></p>
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;margin-top:18px">
        <div><strong style="font-size:20px"><?= (int)$st['total'] ?></strong><div class="muted" style="font-size:11.5px">Captured</div></div>
        <div><strong style="font-size:20px"><?= (int)$st['approved'] ?></strong><div class="muted" style="font-size:11.5px">Approved</div></div>
        <div><strong style="font-size:20px"><?= (int)$st['total'] ? round($st['approved'] / $st['total'] * 100) : 0 ?>%</strong><div class="muted" style="font-size:11.5px">Approval rate</div></div>
    </div>
</div>

<div class="f-list acc-menu">
    <a href="submissions.php"><span class="m-ic"><?= icon('folder', 18) ?></span>My submissions<span class="chev"><?= icon('chevron-right', 18) ?></span></a>
    <a href="submissions.php?status=rejected"><span class="m-ic"><?= icon('alert', 18) ?></span>Returned KYC<span class="chev"><?= icon('chevron-right', 18) ?></span></a>
    <button type="button" id="tipsBtn"><span class="m-ic"><?= icon('eye', 18) ?></span>Photo tips for clear documents<span class="chev"><?= icon('chevron-right', 18) ?></span></button>
</div>

<div class="f-list acc-menu">
    <a href="../logout.php" class="danger" id="logoutLink"><span class="m-ic"><?= icon('logout', 18) ?></span>Sign out</a>
</div>

<p class="muted" style="text-align:center;font-size:11.5px;margin:0">Standard Bank KYC Field · Authorised staff only<br>
    Never share your password. Sign out on shared phones.</p>

<div class="modal" id="tipsModal">
    <div class="modal-card">
        <div class="m-body">
            <div class="m-icon approve"><?= icon('eye', 22) ?></div>
            <h3>Getting photos approved first time</h3>
            <ul style="margin:10px 0 0;padding-left:18px;color:var(--ink-2);line-height:1.7">
                <li>Place the ID on a plain, dark surface in good light.</li>
                <li>Fill the frame — all four corners visible, no fingers over text.</li>
                <li>Avoid flash glare on laminated cards; tilt slightly if needed.</li>
                <li>Face photo: plain background, no hat or sunglasses, eyes open.</li>
                <li>Ask the customer to sign as they do on their ID.</li>
            </ul>
        </div>
        <div class="m-foot"><button class="btn btn-primary" data-close><span>Got it</span></button></div>
    </div>
</div>

<div class="modal" id="logoutModal">
    <div class="modal-card">
        <div class="m-body">
            <div class="m-icon reject"><?= icon('logout', 22) ?></div>
            <h3>Sign out?</h3>
            <p>Any KYC you haven't submitted stays saved as a draft on this phone.</p>
        </div>
        <div class="m-foot">
            <button class="btn btn-light" data-close><span>Cancel</span></button>
            <a class="btn btn-danger-solid" href="../logout.php" data-loading="Signing out…"><span>Sign out</span></a>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    function wire(id) {
        var m = document.getElementById(id);
        m.addEventListener('click', function (e) { if (e.target === m || e.target.closest('[data-close]')) m.classList.remove('show'); });
        return m;
    }
    var tips = wire('tipsModal'), out = wire('logoutModal');
    document.getElementById('tipsBtn').addEventListener('click', function () { tips.classList.add('show'); });
    document.getElementById('logoutLink').addEventListener('click', function (e) { e.preventDefault(); e.stopPropagation(); out.classList.add('show'); });
});
</script>

<?php require __DIR__ . '/includes/bottom.php'; ?>
