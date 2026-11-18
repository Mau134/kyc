<?php
require __DIR__ . '/includes/bootstrap.php';

$status = $_GET['status'] ?? 'all';
if (!in_array($status, ['all', 'review', 'approved', 'rejected'], true)) $status = 'all';

$fTitle = $status === 'rejected' ? 'Returned KYC' : 'My KYC';
$fSubtitle = $status === 'rejected' ? 'Rejected by the office — recapture these' : 'Everything you have captured';
$fNav = $status === 'rejected' ? 'returned' : 'list';
$fJs = ['list.js'];
require __DIR__ . '/includes/top.php';
?>

<label class="field search f-search" id="searchField">
    <?= icon('search', 18) ?>
    <input type="search" id="q" placeholder="Search name, reference, ID or phone" autocomplete="off" enterkeyhint="search">
    <span class="spinner spin-sm"></span>
</label>

<div class="f-filters" id="chips" data-status="<?= e($status) ?>">
    <button class="chip" data-s="all">All <span class="count" data-c="all">–</span></button>
    <button class="chip" data-s="review">In review <span class="count" data-c="review">–</span></button>
    <button class="chip" data-s="approved">Approved <span class="count" data-c="approved">–</span></button>
    <button class="chip" data-s="rejected">Returned <span class="count" data-c="rejected">–</span></button>
</div>

<div class="f-list" id="list">
    <?php for ($i = 0; $i < 6; $i++): ?>
        <div class="f-item"><span class="skel" style="width:38px;height:38px;border-radius:11px"></span>
            <div class="grow"><span class="skel line" style="width:<?= 65 - ($i * 7) % 30 ?>%"></span><span class="skel line" style="width:50%;height:10px;margin-top:6px"></span></div>
            <span class="skel line" style="width:70px;height:18px;border-radius:20px"></span></div>
    <?php endfor; ?>
</div>

<button class="btn btn-light load-more hidden" id="moreBtn"><span>Load more</span></button>

<?php require __DIR__ . '/includes/bottom.php'; ?>
