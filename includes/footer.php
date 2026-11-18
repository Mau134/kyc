        </div><!-- /.content -->
        <footer class="app-footer">
            <span>© <?= date('Y') ?> Standard Bank Malawi · KYC Operations Dashboard</span>
            <span>Confidential — for authorised staff only</span>
        </footer>
    </main>
</div>

<!-- Global processing overlay (critical actions) -->
<div class="overlay-loader" id="overlayLoader" aria-hidden="true">
    <div class="overlay-card">
        <div class="shield-loader">
            <img src="assets/img/landing.png" alt="">
            <span class="ring"></span>
        </div>
        <strong id="overlayTitle">Processing…</strong>
        <small id="overlayText">Please don't close this window.</small>
    </div>
</div>

<div class="toast-stack" id="toastStack" aria-live="polite"></div>

<script src="assets/js/app.js?v=2"></script>
<?php if (!empty($_SESSION['flash'])): $flash = $_SESSION['flash']; unset($_SESSION['flash']); ?>
<script>SB.toast(<?= json_encode($flash['ok'] ? 'success' : 'error') ?>, <?= json_encode($flash['message']) ?>);</script>
<?php endif; ?>
<?php if (!empty($pageJs)): ?>
<script src="assets/js/<?= e($pageJs) ?>?v=2"></script>
<?php endif; ?>
</body>
</html>
