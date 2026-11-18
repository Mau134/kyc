</main>

<?php if (!$fHideNav):
    $returned = fieldReturnedCount($pdo, (int)$me['id']); ?>
<nav class="f-nav" aria-label="Main">
    <a href="index.php" class="<?= $fNav === 'home' ? 'active' : '' ?>"><?= icon('home', 22) ?><span>Home</span></a>
    <a href="submissions.php" class="<?= $fNav === 'list' ? 'active' : '' ?>"><?= icon('folder', 22) ?><span>My KYC</span></a>
    <a href="new.php" class="f-fab" aria-label="New KYC">
        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
    </a>
    <a href="submissions.php?status=rejected" class="<?= $fNav === 'returned' ? 'active' : '' ?>">
        <?= icon('alert', 22) ?><span>Returned</span>
        <?php if ($returned > 0): ?><em class="f-badge"><?= $returned ?></em><?php endif; ?>
    </a>
    <a href="account.php" class="<?= $fNav === 'account' ? 'active' : '' ?>"><?= icon('user', 22) ?><span>Account</span></a>
</nav>
<?php endif; ?>
</div><!-- /.f-app -->

<div class="overlay-loader" id="overlayLoader" aria-hidden="true">
    <div class="overlay-card">
        <div class="shield-loader" id="overlaySpinner">
            <img src="../assets/img/landing.png" alt="">
            <span class="ring"></span>
        </div>
        <div class="upload-ring hidden" id="uploadRing">
            <svg viewBox="0 0 80 80"><circle cx="40" cy="40" r="34" class="track"/><circle cx="40" cy="40" r="34" class="bar" id="uploadBar"/></svg>
            <span id="uploadPct">0%</span>
        </div>
        <strong id="overlayTitle">Processing…</strong>
        <small id="overlayText">Please keep this screen open.</small>
    </div>
</div>
<div class="toast-stack" id="toastStack" aria-live="polite"></div>

<script src="../assets/js/app.js?v=3"></script>
<script src="assets/field.js?v=1"></script>
<?php if (!empty($_SESSION['flash'])): $flash = $_SESSION['flash']; unset($_SESSION['flash']); ?>
<script>SB.toast(<?= json_encode($flash['ok'] ? 'success' : 'error') ?>, <?= json_encode($flash['message']) ?>);</script>
<?php endif; ?>
<?php foreach (($fJs ?? []) as $js): ?>
<script src="assets/<?= e($js) ?>?v=1"></script>
<?php endforeach; ?>
</body>
</html>
