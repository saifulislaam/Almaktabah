<?php
/**
 * admin/includes/layout-start.php
 * Expects $pageTitle and $activeNav set before including. Call
 * Admin::requireAdmin() (and requirePermission() where relevant) in the
 * calling page BEFORE this file, so unauthorized requests never render.
 */
$adminUser = Database::fetchOne('SELECT * FROM users WHERE id = ?', [Auth::id()]);
$pendingCount = Book::pendingCount();
$isMainAdmin = Auth::role() === 'admin';
$perms = $isMainAdmin ? null : (json_decode($adminUser['permissions'] ?? '[]', true) ?: []);
function admin_can(?array $perms, bool $isMainAdmin, string $perm): bool {
    return $isMainAdmin || in_array($perm, $perms ?? [], true);
}
?><!doctype html>
<html lang="en" data-theme="auto">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle ?? 'Admin') ?> - <?= e(SITE_NAME) ?> Admin</title>
<link rel="icon" href="<?= asset('images/logo.png') ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="<?= asset('css/style.css') ?>">
<link rel="stylesheet" href="<?= asset('css/admin.css') ?>">
<script>(function(){var t=null;try{t=localStorage.getItem('almaktabah_theme');}catch(e){}document.documentElement.setAttribute('data-theme',t||'auto');})();</script>
</head>
<body>
<div class="admin-shell">
    <aside class="admin-sidebar">
        <div class="admin-brand">
            <img src="<?= asset('images/logo.png') ?>" alt="">
            <span><?= e(SITE_NAME) ?> Admin</span>
        </div>

        <div class="admin-nav-label">Overview</div>
        <a href="<?= url('admin/dashboard.php') ?>" class="admin-link <?= ($activeNav ?? '') === 'dashboard' ? 'active' : '' ?>"><?= icon('grid') ?> Dashboard</a>
        <?php if (admin_can($perms, $isMainAdmin, 'view_analytics')): ?>
        <a href="<?= url('admin/analytics.php') ?>" class="admin-link <?= ($activeNav ?? '') === 'analytics' ? 'active' : '' ?>"><?= icon('chart') ?> Analytics</a>
        <?php endif; ?>

        <?php if (admin_can($perms, $isMainAdmin, 'manage_books')): ?>
        <div class="admin-nav-label">Content</div>
        <a href="<?= url('admin/books.php') ?>" class="admin-link <?= ($activeNav ?? '') === 'books' ? 'active' : '' ?>">
            <?= icon('book') ?> Books
            <?php if ($pendingCount > 0): ?><span class="count-badge"><?= $pendingCount ?></span><?php endif; ?>
        </a>
        <a href="<?= url('admin/categories.php') ?>" class="admin-link <?= ($activeNav ?? '') === 'categories' ? 'active' : '' ?>"><?= icon('grid') ?> Categories & Authors</a>
        <?php endif; ?>
        <?php if (admin_can($perms, $isMainAdmin, 'manage_reviews')): ?>
        <a href="<?= url('admin/reviews.php') ?>" class="admin-link <?= ($activeNav ?? '') === 'reviews' ? 'active' : '' ?>"><?= icon('star') ?> Reviews</a>
        <?php endif; ?>

        <?php if (admin_can($perms, $isMainAdmin, 'manage_users')): ?>
        <div class="admin-nav-label">People</div>
        <a href="<?= url('admin/users.php') ?>" class="admin-link <?= ($activeNav ?? '') === 'users' ? 'active' : '' ?>"><?= icon('users') ?> Users</a>
        <?php endif; ?>
        <?php if ($isMainAdmin): ?>
        <a href="<?= url('admin/admins.php') ?>" class="admin-link <?= ($activeNav ?? '') === 'admins' ? 'active' : '' ?>"><?= icon('shield') ?> Admin Accounts</a>
        <?php endif; ?>

        <div class="admin-nav-label">System</div>
        <?php if (admin_can($perms, $isMainAdmin, 'manage_settings')): ?>
        <a href="<?= url('admin/settings.php') ?>" class="admin-link <?= ($activeNav ?? '') === 'settings' ? 'active' : '' ?>"><?= icon('settings') ?> Settings</a>
        <?php endif; ?>
        <?php if ($isMainAdmin): ?>
        <a href="<?= url('admin/logs.php') ?>" class="admin-link <?= ($activeNav ?? '') === 'logs' ? 'active' : '' ?>"><?= icon('eye') ?> Activity Logs</a>
        <?php endif; ?>
        <a href="<?= url('index.php') ?>" class="admin-link"><?= icon('home') ?> View Site</a>
        <form action="<?= url('admin/logout.php') ?>" method="post"><?= Csrf::field() ?>
            <button type="submit" class="admin-link" style="width:100%;border:none;background:none;font:inherit;text-align:left;"><?= icon('logout') ?> Sign out</button>
        </form>
    </aside>

    <div class="admin-main">
        <div class="admin-topbar">
            <div class="flex items-center gap-1">
                <button class="admin-menu-toggle" data-admin-menu-toggle aria-label="Menu"><?= icon('menu') ?></button>
                <h1><?= e($pageTitle ?? 'Dashboard') ?></h1>
            </div>
            <div class="flex items-center gap-1">
                <button class="theme-toggle-btn" data-theme-toggle aria-label="Toggle dark mode">
                    <span class="icon-sun"><?= icon('sun') ?></span><span class="icon-moon"><?= icon('moon') ?></span>
                </button>
                <span class="text-muted" style="font-size:.85rem;"><?= e($adminUser['display_name'] ?: $adminUser['username']) ?> &middot; <?= e(ucfirst(Auth::role())) ?></span>
                <?= avatar_html($adminUser, 32) ?>
            </div>
        </div>
        <div class="admin-content">
        <?php foreach (flash_get() as $f): ?>
            <div class="alert alert-<?= $f['type'] === 'error' ? 'error' : ($f['type'] === 'success' ? 'success' : 'info') ?>"><?= e($f['message']) ?></div>
        <?php endforeach; ?>
