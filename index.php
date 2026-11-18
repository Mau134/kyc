<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/helpers.php';
requireDashboardLogin();

$me = currentUser();
$hour = (int)date('G');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
$firstName = explode(' ', trim($me['full_name'] ?? ''))[0] ?? '';

$queueHint = [
    'supervisor' => 'Submissions waiting for your supervisor review',
    'compliance' => 'Reviewed records waiting for compliance sign-off',
    'admin'      => 'All records still in the approval workflow',
][$me['role']] ?? '';

$pageTitle = 'Overview';
$pageSubtitle = 'KYC onboarding performance and work queue';
$activeNav = 'overview';
$pageJs = 'overview.js';
require __DIR__ . '/includes/header.php';

function skelKpi(): string {
    return '<div class="card kpi"><div class="kpi-top"><span class="skel line" style="width:55%"></span><span class="skel" style="width:34px;height:34px;border-radius:10px"></span></div>'
         . '<span class="skel line lg" style="width:40%"></span><span class="skel line" style="width:70%"></span></div>';
}
function skelRows(int $n): string {
    $o = '';
    for ($i = 0; $i < $n; $i++) {
        $o .= '<li><span class="skel" style="width:34px;height:34px;border-radius:10px"></span><div class="grow"><span class="skel line" style="width:' . (50 + ($i * 7) % 35) . '%"></span><span class="skel line" style="width:30%;margin-top:7px;height:10px"></span></div><span class="skel line" style="width:70px"></span></li>';
    }
    return $o;
}
?>

<section class="hero">
    <div>
        <h2><?= e($greeting) ?>, <?= e($firstName) ?></h2>
        <p id="heroText"><span class="skel line" style="width:280px;background-color:rgba(255,255,255,.2);opacity:.4"></span></p>
    </div>
    <div class="hero-actions">
        <a class="btn btn-white" href="records.php?queue=mine"><?= icon('inbox', 16) ?><span>Open my queue</span></a>
        <a class="btn btn-ghost-white" href="records.php"><?= icon('folder', 16) ?><span>All records</span></a>
    </div>
</section>

<div class="grid grid-kpi" id="kpis" style="margin-bottom:18px">
    <?= str_repeat(skelKpi(), 5) ?>
</div>

<div class="grid grid-2-1" style="margin-bottom:18px">
    <section class="card">
        <div class="card-head">
            <div>
                <h3>Submissions — last 14 days</h3>
                <p>Daily KYC captures from field officers, coloured by current status</p>
            </div>
            <span class="live" id="liveBadge" title="Auto-refreshes every 30 seconds"><i></i><span id="liveText">Live</span></span>
        </div>
        <div class="card-body">
            <div class="chart-wrap" id="trendChart"><span class="skel block"></span></div>
            <div class="legend" style="margin-top:12px">
                <span><i style="background:#F59E0B"></i>Pending review</span>
                <span><i style="background:#1E9BE9"></i>Awaiting compliance</span>
                <span><i style="background:#10B981"></i>Approved</span>
                <span><i style="background:#EF4444"></i>Rejected</span>
            </div>
        </div>
    </section>

    <section class="card">
        <div class="card-head">
            <div>
                <h3>Workflow status</h3>
                <p>Where every record currently sits</p>
            </div>
        </div>
        <div class="card-body" id="donutBox">
            <div class="donut-wrap">
                <div class="donut"><span class="skel circle block"></span></div>
                <div class="donut-legend">
                    <?php for ($i = 0; $i < 4; $i++): ?><span class="skel line" style="width:<?= 90 - $i * 8 ?>%"></span><?php endfor; ?>
                </div>
            </div>
            <div id="typeSplit" style="margin-top:20px"></div>
        </div>
    </section>
</div>

<div class="grid grid-2-1">
    <section class="card">
        <div class="card-head">
            <div>
                <h3>My queue <span class="muted" id="queueCount" style="font-weight:500"></span></h3>
                <p><?= e($queueHint) ?> · oldest first</p>
            </div>
            <a class="btn btn-light btn-sm" href="records.php?queue=mine"><span>View all</span><?= icon('arrow-right', 14) ?></a>
        </div>
        <div class="card-body" style="padding-top:4px;padding-bottom:4px">
            <ul class="list" id="queueList"><?= skelRows(5) ?></ul>
        </div>
    </section>

    <div class="stack">
        <section class="card">
            <div class="card-head"><div><h3>Top field officers</h3><p>By total submissions</p></div></div>
            <div class="card-body" style="padding-top:4px;padding-bottom:4px">
                <ul class="list" id="officerList"><?= skelRows(4) ?></ul>
            </div>
        </section>
        <section class="card">
            <div class="card-head"><div><h3>Recent decisions</h3><p>Latest supervisor and compliance actions</p></div></div>
            <div class="card-body" id="activityBox">
                <ul class="list"><?= skelRows(3) ?></ul>
            </div>
        </section>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
