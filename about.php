<?php
require_once __DIR__ . '/config/config.php';
$pageTitle = 'About - ' . SITE_NAME;
$pageDescription = 'Learn about ' . SITE_NAME . ', a free Islamic digital library.';
include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
?>
<nav class="breadcrumbs"><a href="<?= url('index.php') ?>">Home</a><span class="crumb-sep">✦</span><span>About</span></nav>
<div class="form-card" style="max-width:760px;">
    <h1 class="page-title">About <?= e(SITE_NAME) ?></h1>
    <p><?= e(SITE_NAME) ?> is a free Islamic digital library. Anyone can read online without an account; creating a free account lets you upload books, download for offline reading, save a wishlist, and get recommendations shaped by what you actually read.</p>
    <p>Our library spans Quran and Tafsir, Hadith sciences, Fiqh, Aqeedah, Seerah, Islamic history, and more — contributed by readers around the world and reviewed before publishing.</p>
    <p>Have a book to add? <a href="<?= url('upload.php') ?>">Upload it here</a>.</p>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
