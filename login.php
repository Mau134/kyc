<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/helpers.php';
require_once __DIR__ . '/includes/icons.php';

// Already signed in → straight to the right app
$allowedRoles = array_merge(DASHBOARD_ROLES, FIELD_ROLES);
if (!empty($_SESSION['user']) && in_array($_SESSION['user']['role'] ?? '', $allowedRoles, true)) {
    header('Location: ' . homeFor($_SESSION['user']['role']));
    exit;
}

$error = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = (string)($_POST['password'] ?? '');

    // Simple brute-force brake: 5 failures → 60s cool-down (per session)
    $lockUntil = $_SESSION['login_lock_until'] ?? 0;
    if ($lockUntil > time()) {
        $error = 'Too many attempts. Please wait ' . ($lockUntil - time()) . ' seconds and try again.';
    } elseif (!verifyCsrf($_POST['csrf'] ?? null)) {
        $error = 'Your session timed out. Please try again.';
    } else {
        $stmt = $pdo->prepare('SELECT id, username, password, full_name, role FROM users WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && verifyPassword($password, (string)$user['password'])) {
            if (!in_array($user['role'], $allowedRoles, true)) {
                $error = 'This account does not have access to the KYC system.';
            } else {
                session_regenerate_id(true);
                unset($_SESSION['login_fails'], $_SESSION['login_lock_until']);
                $_SESSION['user'] = [
                    'id'        => (int)$user['id'],
                    'username'  => $user['username'],
                    'full_name' => $user['full_name'],
                    'role'      => $user['role'],
                ];
                header('Location: ' . homeFor($user['role']));
                exit;
            }
        } else {
            $_SESSION['login_fails'] = ($_SESSION['login_fails'] ?? 0) + 1;
            if ($_SESSION['login_fails'] >= 5) {
                $_SESSION['login_lock_until'] = time() + 60;
                $_SESSION['login_fails'] = 0;
            }
            $error = 'Incorrect username or password.';
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#00164E">
    <link rel="manifest" href="field/manifest.json">
    <link rel="apple-touch-icon" href="field/assets/icon-192.png">
    <title>Sign in · Standard Bank KYC</title>
    <link rel="icon" type="image/png" href="assets/img/landing.png">
    <link rel="stylesheet" href="assets/css/app.css?v=2">
</head>
<body>
<div class="top-progress" id="topProgress"></div>

<div class="login-page">
    <section class="login-brand">
        <div class="lb-top">
            <img src="assets/img/landing.png" alt="Standard Bank">
            <div>
                <strong>Standard Bank</strong>
                <small>KYC Operations</small>
            </div>
        </div>

        <div>
            <h1>Customer onboarding, verified with confidence.</h1>
            <p>Field officers capture customers on their phones. Supervisors and compliance review, verify documents and approve, all in one secure web system.</p>
            <div class="login-points">
                <div><span><?= icon('id', 17) ?></span> Mobile capture with camera, ID and signature</div>
                <div><span><?= icon('shield', 17) ?></span> Supervisor review and compliance sign-off</div>
                <div><span><?= icon('activity', 17) ?></span> Full audit trail on every decision</div>
            </div>
        </div>

        <div class="lb-foot">© <?= date('Y') ?> Standard Bank Malawi · Authorised staff only</div>
    </section>

    <section class="login-form-side">
        <div class="login-card">
            <img class="mobile-logo" src="assets/img/landing.png" alt="Standard Bank">
            <h2>Welcome back</h2>
            <p>Sign in to Standard Bank KYC. Field officers and office staff use the same sign-in.</p>

            <?php if ($error): ?>
                <div class="alert-error"><?= icon('alert', 18) ?><span><?= e($error) ?></span></div>
            <?php endif; ?>

            <form method="post" data-loading autocomplete="on" novalidate id="loginForm">
                <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
                <div class="form-group">
                    <label for="username">Username</label>
                    <div class="input-icon">
                        <?= icon('user', 18) ?>
                        <input type="text" id="username" name="username" value="<?= e($username) ?>" placeholder="e.g. j.banda" required autofocus>
                    </div>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="input-icon">
                        <?= icon('lock', 18) ?>
                        <input type="password" id="password" name="password" placeholder="Enter your password" required>
                        <button type="button" class="toggle-pw" aria-label="Show password" id="togglePw"><?= icon('eye', 18) ?></button>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary btn-block"><span>Sign in securely</span></button>
            </form>

            <div class="login-help"><?= icon('shield', 15) ?> Authorised Standard Bank staff only. Activity is logged.</div>
        </div>
    </section>
</div>

<div class="overlay-loader" id="overlayLoader" aria-hidden="true">
    <div class="overlay-card">
        <div class="shield-loader"><img src="assets/img/landing.png" alt=""><span class="ring"></span></div>
        <strong id="overlayTitle">Signing you in…</strong>
        <small id="overlayText">Verifying your credentials</small>
    </div>
</div>
<div class="toast-stack" id="toastStack"></div>

<script src="assets/js/app.js?v=2"></script>
<script>
(function () {
    var form = document.getElementById('loginForm');
    document.getElementById('togglePw').addEventListener('click', function () {
        var pw = document.getElementById('password');
        pw.type = pw.type === 'password' ? 'text' : 'password';
        this.setAttribute('aria-label', pw.type === 'password' ? 'Show password' : 'Hide password');
    });
    form.addEventListener('submit', function (e) {
        var u = form.username.value.trim(), p = form.password.value;
        if (!u || !p) {
            e.preventDefault();
            SB.toast('error', 'Missing details', 'Enter both your username and password.');
            (u ? form.password : form.username).focus();
            return;
        }
        SB.overlay.show('Signing you in…', 'Verifying your credentials');
    });
})();
</script>
</body>
</html>
