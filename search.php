<?php
require_once __DIR__ . '/config/config.php';

$query = trim($_GET['q'] ?? '');
$page = max(1, (int) ($_GET['page'] ?? 1));
$sort = $_GET['sort'] ?? 'relevance';
$filters = [
    'category_id' => (int) ($_GET['category_id'] ?? 0) ?: null,
    'language'    => $_GET['language'] ?? null,
    'format'      => $_GET['format'] ?? null,
];
$result = Search::books($query, $filters, $sort, $page, 24);

$pageTitle = ($query !== '' ? '"' . $query . '" search results' : 'Browse Books') . ' | ' . SITE_NAME;
include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';

$wishlistIds = $currentUser ? Wishlist::bookIds($currentUser['id']) : [];
$categories = Category::all();
$languages = Search::distinctLanguages();
$formats = Search::distinctFormats();
?>

<nav class="breadcrumbs"><a href="<?= url('index.php') ?>">Home</a><span class="crumb-sep">✦</span><span><?= $query !== '' ? 'Search: ' . e($query) : 'Browse Books' ?></span></nav>
<h1 class="page-title"><?= $query !== '' ? 'Results for "' . e($query) . '"' : 'Browse All Books' ?></h1>
<p class="text-muted" style="margin-bottom:1.25rem;"><?= $result['total'] ?> books found</p>

<form method="get" class="filter-bar">
    <input type="hidden" name="q" value="<?= e($query) ?>">
    <select name="category_id" onchange="this.form.submit()">
        <option value="">All Categories</option>
        <?php foreach ($categories as $c): ?><option value="<?= $c['id'] ?>" <?= (string) $c['id'] === (string) ($filters['category_id'] ?? '') ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
    </select>
    <select name="language" onchange="this.form.submit()">
        <option value="">All Languages</option>
        <?php foreach ($languages as $l): ?><option value="<?= e($l) ?>" <?= $l === $filters['language'] ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
    </select>
    <select name="format" onchange="this.form.submit()">
        <option value="">All Formats</option>
        <?php foreach ($formats as $f): ?><option value="<?= e($f) ?>" <?= $f === $filters['format'] ? 'selected' : '' ?>><?= strtoupper(e($f)) ?></option><?php endforeach; ?>
    </select>
    <select name="sort" onchange="this.form.submit()">
        <option value="relevance" <?= $sort === 'relevance' ? 'selected' : '' ?>>Most Relevant</option>
        <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest</option>
        <option value="downloads" <?= $sort === 'downloads' ? 'selected' : '' ?>>Most Downloaded</option>
        <option value="rating" <?= $sort === 'rating' ? 'selected' : '' ?>>Highest Rated</option>
        <option value="title" <?= $sort === 'title' ? 'selected' : '' ?>>Title A-Z</option>
    </select>
</form>

<?php if (!$result['rows']): ?>
    <div class="empty-state"><?= icon('search') ?><h3>No books found</h3><p>Try a different search term or clear your filters.</p></div>
<?php else: ?>
    <div class="book-grid"><?php foreach ($result['rows'] as $b) render_book_card($b, $wishlistIds); ?></div>
    <?php render_pagination($result['page'], $result['pages'], array_merge(['q' => $query, 'sort' => $sort], array_filter($filters))); ?>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
