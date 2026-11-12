<?php
require_once __DIR__ . '/config/config.php';

$bookId = (int) ($_GET['id'] ?? 0);
$book = $bookId ? Book::find($bookId) : null;

if (!$book || ($book['status'] !== 'approved' && (!Auth::check() || (Auth::id() !== (int) $book['uploader_id'] && !Auth::isAdmin())))) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

// -------- Handle review submission --------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'review') {
    Auth::requireLogin('login.php');
    Csrf::verifyOrDie();
    $rating = (int) ($_POST['rating'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $comment = trim($_POST['comment'] ?? '');
    if ($rating < 1 || $rating > 5) {
        flash_set('error', 'Please choose a star rating between 1 and 5.');
    } else {
        Review::upsert(Auth::id(), $bookId, $rating, $title ?: null, $comment ?: null);
        flash_set('success', 'Thanks — your review has been posted.');
    }
    redirect('book.php?id=' . $bookId . '&slug=' . $book['slug'] . '#reviews');
}

// -------- Log the view (counts toward trending + reading history) --------
Book::logView($bookId, Auth::id(), Auth::clientIp());
$book = Book::find($bookId); // refresh counters

$pageTitle = $book['title'] . ' by ' . ($book['author_name'] ?: 'Unknown') . ' - Read Online Free | ' . SITE_NAME;
$pageDescription = excerpt($book['description'], 155) ?: ('Read ' . $book['title'] . ' online free at ' . SITE_NAME . '.');
$ogImage = !empty($book['cover_image']) ? UPLOAD_URL_COVERS . '/' . $book['cover_image'] : asset('images/logo.png');

$schemaJsonLd = json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'Book',
    'name' => $book['title'],
    'author' => ['@type' => 'Person', 'name' => $book['author_name'] ?: 'Unknown'],
    'description' => excerpt($book['description'], 300),
    'image' => $ogImage,
    'inLanguage' => $book['language'],
    'aggregateRating' => $book['reviews_count'] > 0 ? [
        '@type' => 'AggregateRating', 'ratingValue' => $book['avg_rating'], 'reviewCount' => $book['reviews_count'],
    ] : null,
], JSON_UNESCAPED_SLASHES);

include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';

$reviews = Review::forBook($bookId, $_GET['review_sort'] ?? 'latest');
$myReview = $currentUser ? Review::byUser($bookId, $currentUser['id']) : null;
$similar = Recommendation::similarBooks($bookId, 6);
$onWishlist = $currentUser ? Wishlist::has($currentUser['id'], $bookId) : false;
$wishlistIds = $currentUser ? Wishlist::bookIds($currentUser['id']) : [];
$readableOnline = Book::isReadableOnline($book);
?>

<nav class="breadcrumbs">
    <a href="<?= url('index.php') ?>">Home</a>
    <span class="crumb-sep">✦</span>
    <?php if ($book['category_name']): ?>
        <a href="<?= url('category.php?id=' . $book['category_id'] . '&slug=' . $book['category_slug']) ?>"><?= e($book['category_name']) ?></a>
        <span class="crumb-sep">✦</span>
    <?php endif; ?>
    <span><?= e($book['title']) ?></span>
</nav>

<div class="book-detail">
    <div class="book-detail-cover">
        <?php if (!empty($book['cover_image'])): ?>
            <img src="<?= e(UPLOAD_URL_COVERS . '/' . $book['cover_image']) ?>" alt="<?= e($book['title']) ?> book cover - AlMaktabah">
        <?php else: ?>
            <div class="no-cover" style="width:100%;height:100%;"><?= icon('book') ?></div>
        <?php endif; ?>
    </div>
    <div class="book-detail-info">
        <?php if ($book['category_name']): ?><span class="category-tag"><?= e($book['category_name']) ?></span><?php endif; ?>
        <?php if ($book['status'] !== 'approved'): ?><span class="status-pill status-<?= e($book['status']) ?>" style="margin-left:8px;"><?= e($book['status']) ?></span><?php endif; ?>
        <h1><?= e($book['title']) ?></h1>
        <?php if ($book['author_name']): ?>
            <p class="by-author">by <a href="<?= url('author.php?id=' . $book['author_id'] . '&slug=' . $book['author_slug']) ?>"><?= e($book['author_name']) ?></a></p>
        <?php endif; ?>

        <div class="rating-summary">
            <?= star_rating_html((float) $book['avg_rating']) ?>
            <span><?= number_format((float) $book['avg_rating'], 1) ?> (<?= (int) $book['reviews_count'] ?> reviews)</span>
            <span>&middot; <?= format_count((int) $book['views_count']) ?> views</span>
            <span>&middot; <?= format_count((int) $book['downloads_count']) ?> downloads</span>
        </div>

        <p><?= nl2br(e($book['description'] ?: 'No description provided yet.')) ?></p>

        <div class="book-actions">
            <?php if ($book['external_link']): ?>
                <a href="<?= e($book['external_link']) ?>" target="_blank" rel="noopener nofollow" class="btn btn-primary"><?= icon('link') ?> Read at source</a>
            <?php else: ?>
                <?php if ($readableOnline): ?>
                    <a href="<?= url('read.php?id=' . $book['id']) ?>" class="btn btn-primary"><?= icon('eye') ?> Read Online</a>
                <?php endif; ?>
                <?php if ($currentUser): ?>
                    <a href="<?= url('download.php?id=' . $book['id']) ?>" class="btn btn-outline"><?= icon('download') ?> Download</a>
                <?php else: ?>
                    <a href="<?= url('login.php?return=' . urlencode('/download.php?id=' . $book['id'])) ?>" class="btn btn-outline"><?= icon('download') ?> Sign in to Download</a>
                <?php endif; ?>
            <?php endif; ?>
            <button class="btn btn-ghost <?= $onWishlist ? 'active' : '' ?>" data-wishlist-toggle="<?= $book['id'] ?>" style="border:1px solid var(--border-strong);">
                <?= icon('heart') ?> <?= $onWishlist ? 'On Wishlist' : 'Add to Wishlist' ?>
            </button>
        </div>

        <?php if (!$readableOnline && !$book['external_link']): ?>
            <div class="alert alert-info">This file format cannot be previewed online. Please download it to view.</div>
        <?php endif; ?>

        <div class="book-meta-list">
            <?php if ($book['language']): ?><span><b>Language:</b> <?= e($book['language']) ?></span><?php endif; ?>
            <?php if ($book['pages']): ?><span><b>Pages:</b> <?= (int) $book['pages'] ?></span><?php endif; ?>
            <?php if ($book['publisher']): ?><span><b>Publisher:</b> <?= e($book['publisher']) ?></span><?php endif; ?>
            <?php if ($book['publication_date']): ?><span><b>Published:</b> <?= date('Y', strtotime($book['publication_date'])) ?></span><?php endif; ?>
            <?php if ($book['isbn']): ?><span><b>ISBN:</b> <?= e($book['isbn']) ?></span><?php endif; ?>
            <?php if ($book['file_type']): ?><span><b>Format:</b> <?= e(strtoupper($book['file_type'])) ?> (<?= format_bytes($book['file_size']) ?>)</span><?php endif; ?>
        </div>

        <div class="share-row">
            <span class="text-muted" style="font-size:.8rem;">Share:</span>
            <?php $shareUrl = urlencode(url('book.php?id=' . $book['id'] . '&slug=' . $book['slug'])); $shareText = urlencode($book['title']); ?>
            <a class="share-btn" target="_blank" rel="noopener" href="https://wa.me/?text=<?= $shareText ?>%20<?= $shareUrl ?>" aria-label="Share on WhatsApp"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 20l1.4-4.2A8 8 0 1 1 9 19l-5 1Z"/></svg></a>
            <a class="share-btn" target="_blank" rel="noopener" href="https://www.facebook.com/sharer/sharer.php?u=<?= $shareUrl ?>" aria-label="Share on Facebook"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M14 9h3V6h-3c-1.7 0-3 1.3-3 3v2H8v3h3v7h3v-7h3l1-3h-4V9c0-.6.4-1 1-1Z"/></svg></a>
            <a class="share-btn" target="_blank" rel="noopener" href="https://twitter.com/intent/tweet?text=<?= $shareText ?>&url=<?= $shareUrl ?>" aria-label="Share on X"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m4 4 7.5 8.5L4.5 20h2l6-6.5L17 20h3l-8-9L19 4h-2l-5.5 6L7 4Z"/></svg></a>
            <a class="share-btn" target="_blank" rel="noopener" href="https://t.me/share/url?url=<?= $shareUrl ?>&text=<?= $shareText ?>" aria-label="Share on Telegram"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m21 4-3 16-6-5-3 3-1-6 12-9Z"/></svg></a>
        </div>
    </div>
</div>

<section class="section" id="reviews" style="padding-top:0;">
    <div class="section-head">
        <h2>Reader Reviews (<?= (int) $book['reviews_count'] ?>)</h2>
        <?php if ($reviews): ?>
        <form method="get" style="display:flex;gap:.5rem;align-items:center;">
            <input type="hidden" name="id" value="<?= $book['id'] ?>"><input type="hidden" name="slug" value="<?= e($book['slug']) ?>">
            <select name="review_sort" class="form-select" onchange="this.form.submit()" style="width:auto;">
                <option value="latest" <?= ($_GET['review_sort'] ?? '') === 'latest' ? 'selected' : '' ?>>Latest</option>
                <option value="highest" <?= ($_GET['review_sort'] ?? '') === 'highest' ? 'selected' : '' ?>>Highest rated</option>
                <option value="lowest" <?= ($_GET['review_sort'] ?? '') === 'lowest' ? 'selected' : '' ?>>Lowest rated</option>
            </select>
        </form>
        <?php endif; ?>
    </div>

    <?php if ($currentUser): ?>
    <form class="review-form" method="post">
        <?= Csrf::field() ?>
        <input type="hidden" name="action" value="review">
        <label class="form-label">Your rating</label>
        <div class="star-input">
            <?php for ($i = 1; $i <= 5; $i++): ?>
                <button type="button" class="<?= $myReview && $myReview['rating'] >= $i ? 'active' : '' ?>"><?= icon('star') ?></button>
            <?php endfor; ?>
            <input type="hidden" name="rating" value="<?= $myReview['rating'] ?? 0 ?>">
        </div>
        <div class="form-group">
            <input type="text" name="title" class="form-input" placeholder="Review title (optional)" value="<?= e($myReview['title'] ?? '') ?>">
        </div>
        <div class="form-group">
            <textarea name="comment" class="form-textarea" placeholder="Share your thoughts on this book…"><?= e($myReview['comment'] ?? '') ?></textarea>
        </div>
        <button type="submit" class="btn btn-primary"><?= $myReview ? 'Update Review' : 'Post Review' ?></button>
    </form>
    <?php else: ?>
        <div class="alert alert-info"><a href="<?= url('login.php') ?>" style="font-weight:700;text-decoration:underline;">Sign in</a> to leave a review.</div>
    <?php endif; ?>

    <?php if (!$reviews): ?>
        <div class="empty-state"><?= icon('star') ?><h3>No reviews yet</h3><p>Be the first to share your thoughts on this book.</p></div>
    <?php else: foreach ($reviews as $r): ?>
        <div class="review-item">
            <?= avatar_html($r, 44) ?>
            <div class="review-body">
                <div class="review-head">
                    <span class="review-author"><?= e($r['display_name'] ?: $r['username']) ?></span>
                    <?= star_rating_html((float) $r['rating']) ?>
                    <span class="review-date"><?= time_ago($r['created_at']) ?></span>
                </div>
                <?php if ($r['title']): ?><div class="review-title"><?= e($r['title']) ?></div><?php endif; ?>
                <?php if ($r['comment']): ?><p><?= nl2br(e($r['comment'])) ?></p><?php endif; ?>
            </div>
        </div>
    <?php endforeach; endif; ?>
</section>

<?php if ($similar): ?>
<section class="section">
    <div class="section-head"><h2>Similar to This Book</h2></div>
    <div class="book-grid">
        <?php foreach ($similar as $b) render_book_card($b, $wishlistIds); ?>
    </div>
</section>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
