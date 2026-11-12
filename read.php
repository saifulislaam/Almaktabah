<?php
require_once __DIR__ . '/config/config.php';

$bookId = (int) ($_GET['id'] ?? 0);
$book = $bookId ? Book::find($bookId) : null;

if (!$book || $book['status'] !== 'approved' || $book['external_link'] || !$book['file_path']) {
    redirect('book.php?id=' . $bookId);
}
if (!Book::isReadableOnline($book)) {
    flash_set('info', 'This file format cannot be previewed online. Please download it to view.');
    redirect('book.php?id=' . $bookId);
}

$myProgress = Auth::check()
    ? Database::fetchOne('SELECT last_page FROM reading_history WHERE user_id = ? AND book_id = ?', [Auth::id(), $bookId])
    : null;

$pageTitle = 'Reading: ' . $book['title'] . ' - ' . SITE_NAME;
$extraHead = '<style>body{overflow:hidden;}</style>';
include __DIR__ . '/includes/head.php';

$fileUrl = e(UPLOAD_URL_BOOKS . '/' . $book['file_path']);
?>
<div class="reader-shell">
    <div class="reader-toolbar">
        <button class="btn btn-icon btn-ghost" data-reader-action="close" aria-label="Close reader"><?= icon('chevron-left') ?></button>
        <strong style="font-size:.88rem;max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= e($book['title']) ?></strong>

        <?php if ($book['file_type'] === 'pdf'): ?>
            <button class="btn btn-icon btn-ghost" data-reader-action="prev" aria-label="Previous page"><?= icon('chevron-left') ?></button>
            <input type="number" class="form-input reader-goto-input" placeholder="Go to…" style="width:80px;padding:.4rem .6rem;" min="1">
            <button class="btn btn-icon btn-ghost" data-reader-action="next" aria-label="Next page"><?= icon('chevron-right') ?></button>
            <button class="btn btn-icon btn-ghost" data-reader-action="zoom-out" aria-label="Zoom out">−</button>
            <button class="btn btn-icon btn-ghost" data-reader-action="zoom-in" aria-label="Zoom in">+</button>
        <?php elseif ($book['file_type'] === 'epub'): ?>
            <button class="btn btn-icon btn-ghost" data-reader-action="prev" aria-label="Previous page"><?= icon('chevron-left') ?></button>
            <button class="btn btn-icon btn-ghost" data-reader-action="next" aria-label="Next page"><?= icon('chevron-right') ?></button>
        <?php endif; ?>

        <?php if (in_array($book['file_type'], ['txt', 'epub'], true)): ?>
            <button class="btn btn-icon btn-ghost" data-reader-action="font-down" aria-label="Smaller text">A-</button>
            <button class="btn btn-icon btn-ghost" data-reader-action="font-up" aria-label="Larger text">A+</button>
        <?php endif; ?>

        <button class="btn btn-icon btn-ghost" data-theme-toggle aria-label="Toggle dark mode">
            <span class="icon-sun"><?= icon('sun') ?></span><span class="icon-moon"><?= icon('moon') ?></span>
        </button>
        <button class="btn btn-icon btn-ghost" data-reader-action="fullscreen" aria-label="Fullscreen"><?= icon('grid') ?></button>

        <span class="spacer"></span>
        <span class="page-indicator">Loading…</span>
        <a href="<?= url('download.php?id=' . $book['id']) ?>" class="btn btn-sm btn-outline"><?= icon('download') ?> Download</a>
    </div>

    <div class="reader-body">
        <?php if ($book['file_type'] === 'pdf'): ?>
            <div class="reader-canvas-wrap"><canvas id="pdf-canvas"></canvas></div>
        <?php elseif ($book['file_type'] === 'epub'): ?>
            <div id="epub-viewer" class="reader-epub"></div>
        <?php elseif ($book['file_type'] === 'txt'): ?>
            <div id="txt-viewer" class="reader-text"></div>
        <?php endif; ?>
    </div>
</div>

<script>
    window.READER_CONFIG = {
        bookId: <?= (int) $book['id'] ?>,
        fileType: <?= json_encode($book['file_type']) ?>,
        fileUrl: <?= json_encode($fileUrl) ?>,
        lastPage: <?= (int) ($myProgress['last_page'] ?? 1) ?>,
        loggedIn: <?= $currentUser ? 'true' : 'false' ?>,
        csrf: <?= json_encode(Csrf::token()) ?>,
        baseUrl: <?= json_encode(rtrim(SITE_URL, '/') . '/') ?>
    };
</script>
<script src="<?= asset('js/reader.js') ?>"></script>
</body>
</html>
