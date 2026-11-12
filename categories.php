<?php
require_once __DIR__ . '/config/config.php';

$pageTitle = 'Browse Islamic Book Categories | ' . SITE_NAME;
$pageDescription = 'Explore Quran, Hadith, Fiqh, Seerah, and more Islamic book categories at ' . SITE_NAME . '.';
include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
$categories = Category::withBookCounts();
?>

<nav class="breadcrumbs"><a href="<?= url('index.php') ?>">Home</a><span class="crumb-sep">✦</span><span>Categories</span></nav>
<h1 class="page-title">All Categories</h1>
<p class="text-muted" style="margin-bottom:1.5rem;">Browse the full Islamic library by subject.</p>

<div class="category-chip-grid">
    <?php foreach ($categories as $cat): ?>
        <a class="category-chip" href="<?= url('category.php?id=' . $cat['id'] . '&slug=' . $cat['slug']) ?>">
            <span class="chip-icon"><?= icon($cat['icon']) ?></span>
            <span>
                <h3><?= e($cat['name']) ?></h3>
                <span class="chip-count"><?= (int) $cat['book_count'] ?> books</span>
            </span>
        </a>
    <?php endforeach; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
