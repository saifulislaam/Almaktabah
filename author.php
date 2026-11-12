<?php
require_once __DIR__ . '/config/config.php';

$authorId = (int) ($_GET['id'] ?? 0);
$author = $authorId ? Author::find($authorId) : null;
if (!$author) { http_response_code(404); require __DIR__ . '/404.php'; exit; }

$page = max(1, (int) ($_GET['page'] ?? 1));
$sort = $_GET['sort'] ?? 'newest';
$result = Book::listFiltered(['author_id' => $authorId], $sort, $page, 24);

$pageTitle = $author['name'] . ' - Complete Book Collection | ' . SITE_NAME;
$pageDescription = 'Discover all books by ' . $author['name'] . ' at ' . SITE_NAME . '. Read online free or download.';
include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
$wishlistIds = $currentUser ? Wishlist::bookIds($currentUser['id']) : [];
?>

<nav class="breadcrumbs"><a href="<?= url('index.php') ?>">Home</a><span class="crumb-sep">✦</span><a href="<?= url('authors.php') ?>">Authors</a><span class="crumb-sep">✦</span><span><?= e($author['name']) ?></span></nav>

<div class="profile-header">
    <div class="author-avatar" style="width:96px;height:96px;">
        <?php if (!empty($author['image'])): ?><img src="<?= e(UPLOAD_URL_AVATARS . '/' . $author['image']) ?>" alt="<?= e($author['name']) ?>"><?php else: ?><?= icon('user') ?><?php endif; ?>
    </div>
    <div>
        <h1 class="page-title" style="margin-bottom:.2rem;"><?= e($author['name']) ?></h1>
        <?php if ($author['era']): ?><p class="text-muted" style="margin-bottom:.4rem;"><?= e($author['era']) ?></p><?php endif; ?>
        <?php if ($author['bio']): ?><p style="max-width:70ch;"><?= nl2br(e($author['bio'])) ?></p><?php endif; ?>
        <div class="profile-stats"><div><b><?= $result['total'] ?></b><span>Books</span></div></div>
    </div>
</div>

<div class="filter-bar">
    <span class="text-muted" style="font-size:.85rem;"><?= $result['total'] ?> books</span>
    <form method="get" style="margin-left:auto;">
        <input type="hidden" name="id" value="<?= $authorId ?>"><input type="hidden" name="slug" value="<?= e($author['slug']) ?>">
        <select name="sort" onchange="this.form.submit()">
            <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest</option>
            <option value="popular" <?= $sort === 'popular' ? 'selected' : '' ?>>Most Viewed</option>
            <option value="rating" <?= $sort === 'rating' ? 'selected' : '' ?>>Highest Rated</option>
            <option value="title" <?= $sort === 'title' ? 'selected' : '' ?>>Title A-Z</option>
        </select>
    </form>
</div>

<?php if (!$result['rows']): ?>
    <div class="empty-state"><?= icon('book') ?><h3>No books yet</h3><p>Nothing has been uploaded for this author yet.</p></div>
<?php else: ?>
    <div class="book-grid"><?php foreach ($result['rows'] as $b) render_book_card($b, $wishlistIds); ?></div>
    <?php render_pagination($result['page'], $result['pages'], ['id' => $authorId, 'slug' => $author['slug'], 'sort' => $sort]); ?>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
