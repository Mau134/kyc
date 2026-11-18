<?php
/**
 * Shared dashboard shell. Before including, set:
 *   $pageTitle (string), $pageSubtitle (string, optional), $activeNav ('overview'|'records'|'queue')
 */
require_once __DIR__ . '/icons.php';
$me = currentUser();
$pageTitle    = $pageTitle    ?? 'KYC Dashboard';
$pageSubtitle = $pageSubtitle ?? '';
$activeNav    = $activeNav    ?? '';

$queueCount = 0;
try {
    $queueCount = (int)$pdo->query('SELECT COUNT(*) FROM kyc_submissions k WHERE ' . queueSql($me['role'] ?? ''))->fetchColumn();
} catch (Throwable $e) {
    $queueCount = 0;
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e(csrfToken()) ?>">
    <title><?= e($pageTitle) ?> · Standard Bank KYC</title>
    <link rel="icon" type="image/png" href="assets/img/landing.png">
    <link rel="stylesheet" href="assets/css/app.css?v=2">
</head>
<body>
<div class="top-progress" id="topProgress"></div>

<div class="app">
    <aside class="sidebar" id="sidebar">
        <a class="sidebar-brand" href="index.php">
            <img src="assets/img/landing.png" alt="Standard Bank">
            <span>
                <strong>Standard Bank</strong>
                <small>KYC Operations</small>
            </span>
        </a>

        <nav class="nav">
            <span class="nav-label">Main</span>
            <a class="nav-link <?= $activeNav === 'overview' ? 'active' : '' ?>" href="index.php">
                <?= icon('grid') ?><span>Overview</span>
            </a>
            <a class="nav-link <?= $activeNav === 'queue' ? 'active' : '' ?>" href="records.php?queue=mine">
                <?= icon('inbox') ?><span>My Queue</span>
                <?php if ($queueCount > 0): ?><em class="nav-badge"><?= $queueCount ?></em><?php endif; ?>
            </a>
            <a class="nav-link <?= $activeNav === 'records' ? 'active' : '' ?>" href="records.php">
                <?= icon('folder') ?><span>All KYC Records</span>
            </a>

            <span class="nav-label">Quick filters</span>
            <a class="nav-link sub" href="records.php?status=pending"><i class="dot pending"></i><span>Pending Review</span></a>
            <a class="nav-link sub" href="records.php?status=compliance"><i class="dot compliance"></i><span>Awaiting Compliance</span></a>
            <a class="nav-link sub" href="records.php?status=approved"><i class="dot approved"></i><span>Approved</span></a>
            <a class="nav-link sub" href="records.php?status=rejected"><i class="dot rejected"></i><span>Rejected</span></a>
        </nav>

        <div class="sidebar-foot">
            <div class="me">
                <span class="avatar"><?= e(initials($me['full_name'] ?? '')) ?></span>
                <span class="me-text">
                    <strong><?= e($me['full_name'] ?? '') ?></strong>
                    <small><?= e(roleLabel($me['role'] ?? '')) ?></small>
                </span>
            </div>
            <a class="nav-link logout" href="logout.php" data-loading="Signing out…"><?= icon('logout') ?><span>Sign out</span></a>
        </div>
    </aside>
    <div class="sidebar-scrim" id="sidebarScrim"></div>

    <main class="main">
        <header class="topbar">
            <button class="icon-btn menu-btn" id="menuBtn" aria-label="Open menu"><?= icon('menu', 20) ?></button>
            <div class="topbar-title">
                <h1><?= e($pageTitle) ?></h1>
                <?php if ($pageSubtitle): ?><p><?= e($pageSubtitle) ?></p><?php endif; ?>
            </div>
            <form class="global-search" action="records.php" method="get" role="search">
                <?= icon('search', 16) ?>
                <input type="search" name="q" placeholder="Search reference, name, ID number…" autocomplete="off">
            </form>
            <div class="topbar-meta">
                <span class="today"><?= icon('calendar', 15) ?> <?= date('D, d M Y') ?></span>
            </div>
        </header>

        <div class="content">
