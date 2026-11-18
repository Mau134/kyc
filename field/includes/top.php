<?php
/**
 * Field app shell (top). Set before including:
 *   $fTitle      string  page title
 *   $fNav        'home'|'list'|'new'|'returned'|'account'
 *   $fHeader     'hero' (home) | 'page' (title bar with optional back) | 'none'
 *   $fBack       optional back URL for 'page' header
 *   $fSubtitle   optional
 *   $fHideNav    bool    hide bottom navigation (wizard)
 */
$fTitle    = $fTitle    ?? 'KYC Field';
$fNav      = $fNav      ?? '';
$fHeader   = $fHeader   ?? 'page';
$fBack     = $fBack     ?? '';
$fSubtitle = $fSubtitle ?? '';
$fHideNav  = $fHideNav  ?? false;
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#00164E">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="SB KYC">
    <meta name="csrf-token" content="<?= e(csrfToken()) ?>">
    <title><?= e($fTitle) ?> · Standard Bank KYC</title>
    <link rel="manifest" href="manifest.json">
    <link rel="icon" type="image/png" href="../assets/img/landing.png">
    <link rel="apple-touch-icon" href="assets/icon-192.png">
    <link rel="stylesheet" href="assets/field.css?v=1">
</head>
<body data-login="../login.php" class="<?= $fHideNav ? 'no-nav' : '' ?>">
<div class="top-progress" id="topProgress"></div>

<div class="f-app">
<?php if ($fHeader === 'page'): ?>
    <header class="f-bar">
        <?php if ($fBack): ?>
            <a class="f-bar-btn" href="<?= e($fBack) ?>" aria-label="Back"><?= icon('arrow-left', 20) ?></a>
        <?php else: ?>
            <img class="f-bar-logo" src="../assets/img/landing.png" alt="">
        <?php endif; ?>
        <div class="f-bar-title">
            <h1><?= e($fTitle) ?></h1>
            <?php if ($fSubtitle): ?><p><?= e($fSubtitle) ?></p><?php endif; ?>
        </div>
        <span class="f-bar-slot" id="barSlot"></span>
    </header>
<?php endif; ?>

<main class="f-main">
