<?php
/**
 * includes/header.php — top navigation. Include includes/head.php first.
 * Opens <div class="app-shell">; pair with includes/sidebar.php next,
 * then includes/footer.php to close everything out.
 */
$unreadCount = $currentUser ? Notification::unreadCount($currentUser['id']) : 0;
$currentLang = I18n::current();
?>
<header class="site-header">
    <div class="container">
        <button class="menu-toggle" data-menu-toggle aria-label="Open menu"><?= icon('menu') ?></button>

        <a href="<?= url('index.php') ?>" class="brand">
            <img src="<?= asset('images/logo.png') ?>" alt="<?= e(SITE_NAME) ?>">
            <span class="brand-text"><?= e(SITE_NAME) ?></span>
        </a>

        <div class="nav-search">
            <form action="<?= url('search.php') ?>" method="get" role="search" autocomplete="off">
                <?= icon('search') ?>
                <input type="text" name="q" placeholder="<?= e(t('nav.search_placeholder')) ?>" value="<?= e($_GET['q'] ?? '') ?>" data-live-search aria-label="<?= e(t('sidebar.search')) ?>">
                <button type="submit" aria-label="<?= e(t('sidebar.search')) ?>"><?= icon('chevron-right') ?></button>
            </form>
            <div class="search-suggestions"></div>
        </div>

        <div class="nav-actions">
            <button class="mobile-search-toggle" data-mobile-search-toggle aria-label="<?= e(t('sidebar.search')) ?>"><?= icon('search') ?></button>

            <div class="lang-wrap">
                <button class="lang-btn" data-dropdown-trigger=".lang-panel" aria-label="<?= e(t('nav.language')) ?>">
                    <?= icon('globe') ?><span class="lang-code"><?= e(strtoupper($currentLang)) ?></span>
                </button>
                <div class="lang-panel">
                    <?php foreach (I18n::SUPPORTED as $code => $meta): ?>
                        <a href="<?= url('set-language.php?lang=' . $code) ?>" class="lang-option <?= $code === $currentLang ? 'active' : '' ?>" hreflang="<?= $code ?>" lang="<?= $code ?>">
                            <span class="lang-native"><?= e($meta['native']) ?></span>
                            <span class="lang-label"><?= e($meta['label']) ?></span>
                            <?php if ($code === $currentLang): ?><?= icon('check') ?><?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <button class="theme-toggle-btn" data-theme-toggle aria-label="Toggle dark mode">
                <span class="icon-sun"><?= icon('sun') ?></span>
                <span class="icon-moon"><?= icon('moon') ?></span>
            </button>

            <?php if ($currentUser): ?>
                <a href="<?= url('upload.php') ?>" class="btn btn-outline btn-sm upload-nav-btn"><?= icon('upload') ?> <?= e(t('nav.upload')) ?></a>

                <div class="notif-wrap">
                    <button class="notif-btn" data-dropdown-trigger=".notif-panel" aria-label="<?= e(t('nav.notifications')) ?>">
                        <?= icon('bell') ?>
                        <?php if ($unreadCount > 0): ?><span class="notif-dot"></span><?php endif; ?>
                    </button>
                    <div class="notif-panel">
                        <div class="notif-panel-head">
                            <span><?= e(t('nav.notifications')) ?></span>
                            <?php if ($unreadCount > 0): ?><a href="<?= url('api/mark-notifications-read.php') ?>" style="font-size:.75rem;font-weight:600;"><?= e(t('nav.mark_all_read')) ?></a><?php endif; ?>
                        </div>
                        <?php $notifs = Notification::forUser($currentUser['id'], 8); ?>
                        <?php if (!$notifs): ?>
                            <div class="notif-empty"><?= e(t('nav.all_caught_up')) ?></div>
                        <?php else: foreach ($notifs as $n): ?>
                            <a class="notif-item <?= !$n['is_read'] ? 'unread' : '' ?>" href="<?= url($n['link'] ?: '#') ?>">
                                <?= e($n['message']) ?>
                                <div class="notif-time"><?= time_ago($n['created_at']) ?></div>
                            </a>
                        <?php endforeach; endif; ?>
                    </div>
                </div>

                <div class="user-menu-wrap">
                    <button class="user-menu-trigger" data-dropdown-trigger=".user-dropdown">
                        <?= avatar_html($currentUser, 34) ?>
                    </button>
                    <div class="user-dropdown">
                        <a href="<?= url('profile.php?u=' . urlencode($currentUser['username'])) ?>"><?= icon('user') ?> <?= e(t('nav.my_profile')) ?></a>
                        <a href="<?= url('upload.php') ?>"><?= icon('upload') ?> <?= e(t('nav.upload_book')) ?></a>
                        <a href="<?= url('settings.php') ?>"><?= icon('settings') ?> <?= e(t('nav.settings')) ?></a>
                        <?php if (Auth::isAdmin()): ?><a href="<?= url('admin/dashboard.php') ?>"><?= icon('shield') ?> <?= e(t('nav.admin_dashboard')) ?></a><?php endif; ?>
                        <hr>
                        <form action="<?= url('logout.php') ?>" method="post">
                            <?= Csrf::field() ?>
                            <button type="submit"><?= icon('logout') ?> <?= e(t('nav.sign_out')) ?></button>
                        </form>
                    </div>
                </div>
            <?php else: ?>
                <a href="<?= url('login.php') ?>" class="btn btn-primary btn-sm"><?= icon('user') ?> <?= e(t('nav.sign_in')) ?></a>
            <?php endif; ?>
        </div>
    </div>

    <div class="mobile-search-bar">
        <form action="<?= url('search.php') ?>" method="get" autocomplete="off">
            <?= icon('search') ?>
            <input type="text" name="q" placeholder="<?= e(t('nav.search_placeholder')) ?>" value="<?= e($_GET['q'] ?? '') ?>">
            <button type="button" class="btn btn-icon btn-ghost" data-mobile-search-close aria-label="Close search"><?= icon('close') ?></button>
        </form>
    </div>
</header>
<div class="app-shell">
