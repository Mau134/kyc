<?php
require __DIR__ . '/includes/bootstrap.php';

$hour = (int)date('G');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
$firstName = explode(' ', trim($me['full_name'] ?? ''))[0] ?? '';

$fTitle = 'Home';
$fNav = 'home';
$fHeader = 'none';
$fJs = ['home.js'];
require __DIR__ . '/includes/top.php';
?>

<section class="f-hero">
    <div class="f-hero-top">
        <img src="../assets/img/landing.png" alt="Standard Bank">
        <span class="brand"><strong>Standard Bank</strong><small>KYC Field</small></span>
        <a class="avatar" href="account.php" aria-label="Account"><?= e(initials($me['full_name'] ?? '')) ?></a>
    </div>
    <h2><?= e($greeting) ?>, <?= e($firstName) ?></h2>
    <p><?= e(date('l, j F Y')) ?></p>
</section>

<div class="f-overlap">
    <div class="f-stats" id="stats">
        <?php for ($i = 0; $i < 4; $i++): ?>
            <div class="f-stat"><span class="skel line" style="width:60%"></span><span class="skel line lg" style="width:40%"></span></div>
        <?php endfor; ?>
    </div>
</div>

<a class="f-cta" href="new.php">
    <span class="ic-wrap"><?= icon('id', 24) ?></span>
    <span><strong>Start new KYC</strong><small>Capture details, ID, photo and signature</small></span>
    <span class="arrow"><?= icon('chevron-right', 20) ?></span>
</a>

<div class="f-stat wide" id="weekCard">
    <div class="grow">
        <span class="label">Your captures — last 7 days</span>
        <div id="weekBars" style="display:flex;align-items:flex-end;gap:6px;height:54px;margin-top:10px">
            <?php for ($i = 0; $i < 7; $i++): ?><span class="skel" style="flex:1;height:<?= 20 + ($i * 13) % 34 ?>px;border-radius:6px"></span><?php endfor; ?>
        </div>
        <div id="weekLabels" style="display:flex;gap:6px;margin-top:5px;font-size:10.5px;color:var(--muted)"></div>
    </div>
</div>

<div class="f-section-head">
    <h3>Recent submissions</h3>
    <a href="submissions.php">See all</a>
</div>
<div class="f-list" id="recent">
    <?php for ($i = 0; $i < 3; $i++): ?>
        <div class="f-item"><span class="skel" style="width:38px;height:38px;border-radius:11px"></span>
            <div class="grow"><span class="skel line" style="width:<?= 60 - $i * 10 ?>%"></span><span class="skel line" style="width:45%;height:10px;margin-top:6px"></span></div>
            <span class="skel line" style="width:64px;height:18px;border-radius:20px"></span></div>
    <?php endfor; ?>
</div>

<div class="f-tip" id="installTip">
    <?= icon('shield', 18) ?>
    <span><strong>Tip:</strong> add this app to your home screen from the browser menu (“Add to Home screen”) to open it like a normal app.</span>
</div>

<?php require __DIR__ . '/includes/bottom.php'; ?>
