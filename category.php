<?php
require_once __DIR__ . '/config/config.php';

$categoryId = (int) ($_GET['id'] ?? 0);
$category = $categoryId ? Category::find($categoryId) : null;
if (!$category) { http_response_code(404); require __DIR__ . '/404.php'; exit; }

$page = max(1, (int) ($_GET['page'] ?? 1));
$sort = $_GET['sort'] ?? 'newest';
$result = Book::listFiltered(['category_id' => $categoryId], $sort, $page, 24);

$pageTitle = $category['name'] . ' Books - Free Online Reading | ' . SITE_NAME;
$pageDescription = 'Browse ' . $category['name'] . ' books online free at ' . SITE_NAME . '. Read and download in PDF, EPUB, and more.';
$schemaJsonLd = json_encode([
    '@context' => 'https://schema.org', '@type' => 'BreadcrumbList',
    'itemListElement' => [
        ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => SITE_URL],
        ['@type' => 'ListItem', 'position' => 2, 'name' => $category['name'], 'item' => url('category.php?id=' . $category['id'])],
    ],
], JSON_UNESCAPED_SLASHES);

include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
$wishlistIds = $currentUser ? Wishlist::bookIds($currentUser['id']) : [];
$following = $currentUser ? in_array($categoryId, User::favoriteCategoryIds($currentUser['id']), true) : false;
$subcats = Category::children($categoryId);
?>

<nav class="breadcrumbs"><a href="<?= url('index.php') ?>">Home</a><span class="crumb-sep">✦</span><a href="<?= url('categories.php') ?>">Categories</a><span class="crumb-sep">✦</span><span><?= e($category['name']) ?></span></nav>

<div class="section-head">
    <div>
        <h1 class="page-title" style="display:flex;align-items:center;gap:.6rem;"><?= icon($category['icon']) ?> <?= e($category['name']) ?></h1>
        <?php if ($category['description']): ?><p class="text-muted" style="max-width:60ch;"><?= e($category['description']) ?></p><?php endif; ?>
    </div>
    <button class="btn <?= $following ? 'btn-primary' : 'btn-outline' ?>" data-follow-category="<?= $categoryId ?>"><?= $following ? 'Following' : 'Follow' ?></button>
</div>

<?php if ($subcats): ?>
<div class="filter-bar">
    <?php foreach ($subcats as $sc): ?>
        <a href="<?= url('category.php?id=' . $sc['id'] . '&slug=' . $sc['slug']) ?>" class="btn btn-sm btn-outline"><?= e($sc['name']) ?></a>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="filter-bar">
    <span class="text-muted" style="font-size:.85rem;"><?= $result['total'] ?> books</span>
    <form method="get" style="margin-left:auto;">
        <input type="hidden" name="id" value="<?= $categoryId ?>"><input type="hidden" name="slug" value="<?= e($category['slug']) ?>">
        <select name="sort" onchange="this.form.submit()">
            <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest</option>
            <option value="popular" <?= $sort === 'popular' ? 'selected' : '' ?>>Most Viewed</option>
            <option value="downloads" <?= $sort === 'downloads' ? 'selected' : '' ?>>Most Downloaded</option>
            <option value="rating" <?= $sort === 'rating' ? 'selected' : '' ?>>Highest Rated</option>
            <option value="title" <?= $sort === 'title' ? 'selected' : '' ?>>Title A-Z</option>
        </select>
    </form>
</div>

<?php if (!$result['rows']): ?>
    <div class="empty-state"><?= icon('book') ?><h3>No books in this category yet</h3><p>Be the first to <a href="<?= url('upload.php') ?>">upload one</a>.</p></div>
<?php else: ?>
    <div class="book-grid">
        <?php foreach ($result['rows'] as $b) render_book_card($b, $wishlistIds); ?>
    </div>
    <?php render_pagination($result['page'], $result['pages'], ['id' => $categoryId, 'slug' => $category['slug'], 'sort' => $sort]); ?>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
