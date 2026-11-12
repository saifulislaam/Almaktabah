<?php
require_once __DIR__ . '/config/config.php';

$pageTitle = SITE_NAME . ' - ' . SITE_TAGLINE . ' | Read Islamic Books Online Free';
$pageDescription = setting('site_description');
$schemaJsonLd = json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'WebSite',
    'name' => SITE_NAME,
    'url' => SITE_URL,
    'potentialAction' => [
        '@type' => 'SearchAction',
        'target' => SITE_URL . '/search.php?q={query}',
        'query-input' => 'required name=query',
    ],
], JSON_UNESCAPED_SLASHES);

include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';

$recommended = Recommendation::forUser($currentUser['id'] ?? null, 12);
$trending    = Book::trending(10);
$newArrivals = Book::newArrivals(10);
$topRated    = Book::topRated(8);
$categories  = Category::withBookCounts();
$totalBooks  = Database::fetchOne("SELECT COUNT(*) AS c FROM books WHERE status='approved'")['c'] ?? 0;
$totalReaders= Database::fetchOne("SELECT COUNT(*) AS c FROM users WHERE role='user'")['c'] ?? 0;
$stackCovers = array_slice(array_filter(array_merge($trending, $newArrivals), fn($b) => !empty($b['cover_image'])), 0, 3);

$wishlistIds = $currentUser ? Wishlist::bookIds($currentUser['id']) : [];
?>

<section class="hero">
    <div class="khatam-watermark" aria-hidden="true"></div>
    <div class="container">
        <div>
            <div class="hero-eyebrow"><?= icon('khatam', 'icon') ?> <?= e(t('hero.eyebrow')) ?></div>
            <h1><?= e(t('hero.title_pre')) ?> <span class="accent-word"><?= e(t('hero.title_accent')) ?></span>,<br><?= e(t('hero.title_post')) ?></h1>
            <p class="lede"><?= e(t('hero.lede')) ?></p>
            <div class="hero-search">
                <form action="<?= url('search.php') ?>" method="get">
                    <?= icon('search') ?>
                    <input type="text" name="q" placeholder="<?= e(t('hero.search_placeholder')) ?>">
                    <button type="submit" class="btn btn-primary"><?= e(t('hero.search_button')) ?></button>
                </form>
            </div>
            <div class="hero-stats">
                <div class="hero-stat"><b><?= format_count((int) $totalBooks) ?></b><span><?= e(t('hero.stat_books')) ?></span></div>
                <div class="hero-stat"><b><?= format_count((int) $totalReaders) ?></b><span><?= e(t('hero.stat_readers')) ?></span></div>
                <div class="hero-stat"><b><?= count($categories) ?></b><span><?= e(t('hero.stat_categories')) ?></span></div>
            </div>
        </div>
        <div class="hero-stack" aria-hidden="true">
            <?php foreach ($stackCovers as $i => $b): ?>
                <div class="stack-card card-<?= $i + 1 ?>"><img src="<?= e(UPLOAD_URL_COVERS . '/' . $b['cover_image']) ?>" alt=""></div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php if ($recommended): ?>
<section class="section">
    <div class="section-head">
        <h2><?= $currentUser ? e(t('section.recommended')) : e(t('section.popular_now')) ?></h2>
        <a href="<?= url('search.php?sort=popular') ?>" class="see-all"><?= e(t('section.see_all')) ?> <?= icon('chevron-right') ?></a>
    </div>
    <div class="book-grid">
        <?php foreach ($recommended as $b) render_book_card($b, $wishlistIds); ?>
    </div>
</section>
<?php endif; ?>

<section class="section">
    <div class="section-head">
        <h2><?= e(t('section.trending')) ?></h2>
        <a href="<?= url('search.php?sort=popular') ?>" class="see-all"><?= e(t('section.see_all')) ?> <?= icon('chevron-right') ?></a>
    </div>
    <?php if ($trending): ?>
    <div class="book-grid">
        <?php foreach ($trending as $b) render_book_card($b, $wishlistIds); ?>
    </div>
    <?php else: ?>
    <div class="empty-state" style="padding:40px 20px;"><?= icon('chart') ?><h3><?= e(t('empty.no_trending_title')) ?></h3><p><?= e(t('empty.no_trending_body')) ?></p></div>
    <?php endif; ?>
</section>

<section class="section">
    <div class="section-head">
        <h2><?= e(t('section.browse_category')) ?></h2>
        <a href="<?= url('categories.php') ?>" class="see-all"><?= e(t('section.all_categories')) ?> <?= icon('chevron-right') ?></a>
    </div>
    <div class="category-chip-grid">
        <?php foreach (array_slice($categories, 0, 8) as $cat): ?>
            <a class="category-chip" href="<?= url('category.php?id=' . $cat['id'] . '&slug=' . $cat['slug']) ?>">
                <span class="chip-icon"><?= icon($cat['icon']) ?></span>
                <span>
                    <h3><?= e($cat['name']) ?></h3>
                    <span class="chip-count"><?= (int) $cat['book_count'] ?> books</span>
                </span>
            </a>
        <?php endforeach; ?>
    </div>
</section>

<section class="section">
    <div class="section-head">
        <h2><?= e(t('section.new_arrivals')) ?></h2>
        <a href="<?= url('search.php?sort=newest') ?>" class="see-all"><?= e(t('section.see_all')) ?> <?= icon('chevron-right') ?></a>
    </div>
    <?php if ($newArrivals): ?>
    <div class="book-grid">
        <?php foreach ($newArrivals as $b) render_book_card($b, $wishlistIds); ?>
    </div>
    <?php else: ?>
    <div class="empty-state" style="padding:40px 20px;"><?= icon('book') ?><h3><?= e(t('empty.no_books_title')) ?></h3><p>This library is just getting started — <a href="<?= url('upload.php') ?>">upload the first book</a>.</p></div>
    <?php endif; ?>
</section>

<?php if ($topRated): ?>
<section class="section">
    <div class="section-head">
        <h2><?= e(t('section.highest_rated')) ?></h2>
        <a href="<?= url('search.php?sort=rating') ?>" class="see-all"><?= e(t('section.see_all')) ?> <?= icon('chevron-right') ?></a>
    </div>
    <div class="book-grid">
        <?php foreach ($topRated as $b) render_book_card($b, $wishlistIds); ?>
    </div>
</section>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
