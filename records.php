<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/helpers.php';
requireDashboardLogin();

$me = currentUser();
$isQueue = ($_GET['queue'] ?? '') === 'mine';

$pageTitle = $isQueue ? 'My Queue' : 'KYC Records';
$pageSubtitle = $isQueue
    ? ['supervisor' => 'Submissions waiting for your review',
       'compliance' => 'Reviewed records waiting for compliance sign-off',
       'admin'      => 'All records still in the approval workflow'][$me['role']] ?? ''
    : 'Search, filter and open any customer KYC submission';
$activeNav = $isQueue ? 'queue' : 'records';
$pageJs = 'records.js';
require __DIR__ . '/includes/header.php';

$initial = [
    'q'      => trim($_GET['q'] ?? ''),
    'status' => in_array($_GET['status'] ?? '', ['pending', 'compliance', 'approved', 'rejected'], true) ? $_GET['status'] : 'all',
    'type'   => in_array($_GET['type'] ?? '', ['personal', 'business'], true) ? $_GET['type'] : '',
    'queue'  => $isQueue ? 'mine' : '',
    'sort'   => ($_GET['sort'] ?? '') === 'oldest' ? 'oldest' : ($isQueue ? 'oldest' : 'newest'),
    'page'   => max(1, (int)($_GET['page'] ?? 1)),
];
?>

<section class="card" id="recordsCard" data-initial='<?= e(json_encode($initial)) ?>'>
    <div class="toolbar">
        <div class="tabs" id="statusTabs" role="tablist">
            <button class="tab" data-status="all">All <span class="count" data-c="all">–</span></button>
            <button class="tab" data-status="pending"><i class="dot pending" style="width:7px;height:7px;border-radius:50%"></i>Pending <span class="count" data-c="pending">–</span></button>
            <button class="tab" data-status="compliance"><i class="dot compliance" style="width:7px;height:7px;border-radius:50%"></i>Compliance <span class="count" data-c="compliance">–</span></button>
            <button class="tab" data-status="approved"><i class="dot approved" style="width:7px;height:7px;border-radius:50%"></i>Approved <span class="count" data-c="approved">–</span></button>
            <button class="tab" data-status="rejected"><i class="dot rejected" style="width:7px;height:7px;border-radius:50%"></i>Rejected <span class="count" data-c="rejected">–</span></button>
        </div>
        <div class="spacer"></div>
        <span class="live" id="liveBadge" title="Auto-refreshes every 30 seconds — click to refresh now"><i></i><span id="liveText">Live</span></span>
    </div>
    <div class="toolbar">
        <label class="field search" id="searchField">
            <?= icon('search', 16) ?>
            <input type="search" id="q" placeholder="Search by reference, customer, business, ID or phone number" value="<?= e($initial['q']) ?>">
            <span class="spinner spin-sm"></span>
        </label>
        <label class="field">
            <?= icon('users', 16) ?>
            <select id="type" aria-label="Account type">
                <option value="">All account types</option>
                <option value="personal">Personal</option>
                <option value="business">Business</option>
            </select>
        </label>
        <label class="field">
            <?= icon('clock', 16) ?>
            <select id="sort" aria-label="Sort order">
                <option value="newest">Newest first</option>
                <option value="oldest">Oldest first</option>
            </select>
        </label>
        <?php if ($isQueue): ?>
            <a class="btn btn-light" href="records.php"><span>Show all records</span></a>
        <?php endif; ?>
    </div>

    <div class="table-wrap">
        <table class="data">
            <thead>
                <tr>
                    <th>Customer / Business</th>
                    <th>Reference</th>
                    <th>Identification</th>
                    <th>Field officer</th>
                    <th>Status</th>
                    <th>Submitted</th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="rows">
                <?php for ($i = 0; $i < 8; $i++): ?>
                <tr class="skeleton-row">
                    <td><div class="cell-customer"><span class="skel" style="width:34px;height:34px;border-radius:10px"></span><div style="flex:1"><span class="skel line" style="width:<?= 55 + ($i * 9) % 35 ?>%"></span><span class="skel line" style="width:35%;height:10px;margin-top:6px"></span></div></div></td>
                    <td><span class="skel line" style="width:150px"></span></td>
                    <td><span class="skel line" style="width:110px"></span></td>
                    <td><span class="skel line" style="width:100px"></span></td>
                    <td><span class="skel line" style="width:90px;height:20px;border-radius:20px"></span></td>
                    <td><span class="skel line" style="width:80px"></span></td>
                    <td></td>
                </tr>
                <?php endfor; ?>
            </tbody>
        </table>
    </div>

    <div class="table-foot">
        <span id="summary"><span class="skel line" style="width:160px"></span></span>
        <div class="pager">
            <button class="btn btn-light btn-sm" id="prevBtn" disabled><?= icon('chevron-left', 14) ?><span>Prev</span></button>
            <span class="page-info" id="pageInfo"></span>
            <button class="btn btn-light btn-sm" id="nextBtn" disabled><span>Next</span><?= icon('chevron-right', 14) ?></button>
        </div>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
