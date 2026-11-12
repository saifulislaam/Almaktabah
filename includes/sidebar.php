<?php
/**
 * includes/sidebar.php — left navigation, becomes an off-canvas drawer on
 * mobile/tablet. Include right after includes/header.php. Opens
 * <main class="app-main">; includes/footer.php closes it.
 */
$currentScript = basename($_SERVER['SCRIPT_NAME'] ?? '');
$activeCategoryId = isset($_GET['id']) && $currentScript === 'category.php' ? (int) $_GET['id'] : null;
$topCategories = Category::topLevel();
?>
<div class="drawer-overlay"></div>
<aside class="app-sidebar">
    <div class="sidebar-section">
        <button type="button" class="sidebar-link sidebar-search-link" data-mobile-search-toggle>
            <?= icon('search') ?> <?= e(t('sidebar.search')) ?>
        </button>
        <a class="sidebar-link <?= $currentScript === 'index.php' ? 'active' : '' ?>" href="<?= url('index.php') ?>"><?= icon('home') ?> <?= e(t('sidebar.home')) ?></a>
        <a class="sidebar-link <?= $currentScript === 'search.php' && ($_GET['sort'] ?? '') === 'popular' ? 'active' : '' ?>" href="<?= url('search.php?sort=popular') ?>"><?= icon('chart') ?> <?= e(t('sidebar.trending')) ?></a>
        <?php if ($currentUser): ?>
        <a class="sidebar-link <?= $currentScript === 'wishlist.php' ? 'active' : '' ?>" href="<?= url('wishlist.php') ?>"><?= icon('bookmark') ?> <?= e(t('sidebar.wishlist')) ?></a>
        <a class="sidebar-link <?= $currentScript === 'history.php' ? 'active' : '' ?>" href="<?= url('history.php') ?>"><?= icon('eye') ?> <?= e(t('sidebar.history')) ?></a>
        <?php endif; ?>
        <a class="sidebar-link <?= $currentScript === 'authors.php' ? 'active' : '' ?>" href="<?= url('authors.php') ?>"><?= icon('users') ?> <?= e(t('sidebar.authors')) ?></a>
    </div>

    <div class="sidebar-section">
        <div class="sidebar-label"><?= e(t('sidebar.categories')) ?></div>
        <?php foreach ($topCategories as $cat): ?>
            <a class="sidebar-link <?= $activeCategoryId === (int) $cat['id'] ? 'active' : '' ?>" href="<?= url('category.php?id=' . $cat['id'] . '&slug=' . $cat['slug']) ?>">
                <?= icon($cat['icon']) ?> <?= e($cat['name']) ?>
            </a>
        <?php endforeach; ?>
        <a class="sidebar-link" href="<?= url('categories.php') ?>"><?= icon('grid') ?> <?= e(t('sidebar.all_categories')) ?></a>
    </div>

    <div class="sidebar-section">
        <div class="sidebar-label"><?= e(t('sidebar.account')) ?></div>
        <?php if ($currentUser): ?>
        <a class="sidebar-link <?= $currentScript === 'upload.php' ? 'active' : '' ?>" href="<?= url('upload.php') ?>"><?= icon('upload') ?> <?= e(t('sidebar.upload')) ?></a>
        <a class="sidebar-link <?= $currentScript === 'settings.php' ? 'active' : '' ?>" href="<?= url('settings.php') ?>"><?= icon('settings') ?> <?= e(t('sidebar.settings')) ?></a>
        <?php if (Auth::isAdmin()): ?><a class="sidebar-link" href="<?= url('admin/dashboard.php') ?>"><?= icon('shield') ?> <?= e(t('sidebar.admin')) ?></a><?php endif; ?>
        <?php else: ?>
        <a class="sidebar-link" href="<?= url('login.php') ?>"><?= icon('user') ?> <?= e(t('sidebar.sign_in')) ?></a>
        <?php endif; ?>
        <button type="button" class="sidebar-link" data-theme-toggle>
            <?= icon('moon') ?> <?= e(t('sidebar.dark_mode')) ?>
        </button>
    </div>
</aside>
<main class="app-main" id="main-content">
