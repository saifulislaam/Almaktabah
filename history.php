<?php
require_once __DIR__ . '/config/config.php';
Auth::requireLogin('login.php');

$pageTitle = 'Reading History - ' . SITE_NAME;
include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';

$history = User::readingHistory(Auth::id(), 40);
$downloads = User::downloadHistory(Auth::id(), 40);
$tab = $_GET['tab'] ?? 'reading';
?>

<nav class="breadcrumbs"><a href="<?= url('index.php') ?>">Home</a><span class="crumb-sep">✦</span><span>History</span></nav>
<h1 class="page-title">History</h1>

<div class="tabs">
    <a href="?tab=reading" class="tab-link <?= $tab === 'reading' ? 'active' : '' ?>">Reading History</a>
    <a href="?tab=downloads" class="tab-link <?= $tab === 'downloads' ? 'active' : '' ?>">Download History</a>
</div>

<?php if ($tab === 'downloads'): ?>
    <?php if (!$downloads): ?>
        <div class="empty-state"><?= icon('download') ?><h3>No downloads yet</h3></div>
    <?php else: foreach ($downloads as $b): ?>
        <div class="upload-list-row">
            <?php if (!empty($b['cover_image'])): ?><img src="<?= e(UPLOAD_URL_COVERS . '/' . $b['cover_image']) ?>" alt=""><?php else: ?><span class="cover-fallback"></span><?php endif; ?>
            <div style="flex:1;">
                <a href="<?= url('book.php?id=' . $b['id'] . '&slug=' . $b['slug']) ?>" class="row-title"><?= e($b['title']) ?></a>
                <div class="row-meta"><?= e($b['author_name'] ?? '') ?> &middot; Downloaded <?= time_ago($b['downloaded_at']) ?></div>
            </div>
            <a href="<?= url('download.php?id=' . $b['id']) ?>" class="btn btn-sm btn-outline"><?= icon('download') ?> Again</a>
        </div>
    <?php endforeach; endif; ?>
<?php else: ?>
    <?php if (!$history): ?>
        <div class="empty-state"><?= icon('eye') ?><h3>No reading history yet</h3><p>Books you read online will show up here so you can pick up where you left off.</p></div>
    <?php else: foreach ($history as $b): ?>
        <div class="upload-list-row">
            <?php if (!empty($b['cover_image'])): ?><img src="<?= e(UPLOAD_URL_COVERS . '/' . $b['cover_image']) ?>" alt=""><?php else: ?><span class="cover-fallback"></span><?php endif; ?>
            <div style="flex:1;">
                <a href="<?= url('book.php?id=' . $b['id'] . '&slug=' . $b['slug']) ?>" class="row-title"><?= e($b['title']) ?></a>
                <div class="row-meta"><?= e($b['author_name'] ?? '') ?> &middot; Last read <?= time_ago($b['last_read_at']) ?><?= $b['read_percentage'] ? ' &middot; ' . round($b['read_percentage']) . '% complete' : '' ?></div>
            </div>
            <?php if (Book::isReadableOnline(['file_type' => $b['file_type']])): ?>
                <a href="<?= url('read.php?id=' . $b['id']) ?>" class="btn btn-sm btn-primary">Continue</a>
            <?php endif; ?>
        </div>
    <?php endforeach; endif; ?>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
