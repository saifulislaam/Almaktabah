<?php
require_once __DIR__ . '/config/config.php';

$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 40;
$authors = Author::all($perPage, ($page - 1) * $perPage);
$total = Author::count();
$pages = (int) ceil($total / $perPage);

$pageTitle = 'Islamic Authors & Scholars | ' . SITE_NAME;
$pageDescription = 'Browse Islamic scholars and authors, and discover their complete works at ' . SITE_NAME . '.';
include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
?>

<nav class="breadcrumbs"><a href="<?= url('index.php') ?>">Home</a><span class="crumb-sep">✦</span><span>Authors</span></nav>
<h1 class="page-title">Authors & Scholars</h1>
<p class="text-muted" style="margin-bottom:1.5rem;"><?= $total ?> authors on <?= e(SITE_NAME) ?>.</p>

<?php if (!$authors): ?>
    <div class="empty-state"><?= icon('users') ?><h3>No authors yet</h3><p>Authors appear automatically as books are uploaded.</p></div>
<?php else: ?>
    <div class="author-grid">
        <?php foreach ($authors as $a): ?>
            <a class="author-card" href="<?= url('author.php?id=' . $a['id'] . '&slug=' . $a['slug']) ?>">
                <div class="author-avatar">
                    <?php if (!empty($a['image'])): ?><img src="<?= e(UPLOAD_URL_AVATARS . '/' . $a['image']) ?>" alt="<?= e($a['name']) ?>"><?php else: ?><?= icon('user') ?><?php endif; ?>
                </div>
                <h3><?= e($a['name']) ?></h3>
                <div class="author-count"><?= (int) $a['book_count'] ?> books</div>
            </a>
        <?php endforeach; ?>
    </div>
    <?php render_pagination($page, $pages, [], 'authors.php'); ?>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
