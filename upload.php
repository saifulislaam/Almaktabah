<?php
require_once __DIR__ . '/config/config.php';
Auth::requireLogin('login.php');

$errors = [];
$old = ['title' => '', 'description' => '', 'category_id' => '', 'author_name' => '', 'external_link' => '',
        'language' => 'Arabic', 'publisher' => '', 'isbn' => '', 'pages' => '', 'edition' => '', 'publication_date' => ''];

$uploadsToday = User::uploadsToday(Auth::id());
$limit = (int) setting('daily_upload_limit', DAILY_UPLOAD_LIMIT);
$limitReached = $uploadsToday >= $limit;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$limitReached) {
    Csrf::verifyOrDie();
    $old = array_merge($old, array_intersect_key($_POST, $old));

    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $categoryId = (int) ($_POST['category_id'] ?? 0);
    $authorName = trim($_POST['author_name'] ?? '');
    $externalLink = trim($_POST['external_link'] ?? '');
    $useExternalLink = ($_POST['source_type'] ?? 'file') === 'link';

    if ($title === '' || mb_strlen($title) > 255) $errors[] = 'Please enter a title (up to 255 characters).';
    if (!$categoryId || !Category::find($categoryId)) $errors[] = 'Please choose a category.';
    if ($useExternalLink && !filter_var($externalLink, FILTER_VALIDATE_URL)) $errors[] = 'Please enter a valid external link.';
    if (!$useExternalLink && (!isset($_FILES['book_file']) || $_FILES['book_file']['error'] === UPLOAD_ERR_NO_FILE)) {
        $errors[] = 'Please choose a book file to upload, or switch to "External link".';
    }

    $coverResult = null;
    if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] !== UPLOAD_ERR_NO_FILE) {
        $coverResult = Upload::handleCoverImage($_FILES['cover_image']);
        if (!$coverResult['success'] && $coverResult['error']) $errors[] = $coverResult['error'];
    }

    $fileResult = null;
    if (!$useExternalLink && !$errors) {
        $fileResult = Upload::handleBookFile($_FILES['book_file']);
        if (!$fileResult['success']) $errors[] = $fileResult['error'] ?? 'Could not upload the book file.';
    }

    if (!$errors) {
        $authorId = $authorName !== '' ? Author::findOrCreate($authorName) : null;
        $slugBase = make_slug($title);

        $bookId = Book::create([
            'title' => $title,
            'slug' => $slugBase,
            'description' => $description ?: null,
            'category_id' => $categoryId,
            'author_id' => $authorId,
            'cover_image' => $coverResult['filename'] ?? null,
            'file_path' => $fileResult['filename'] ?? null,
            'file_type' => $fileResult['ext'] ?? null,
            'file_size' => $fileResult['size'] ?? null,
            'external_link' => $useExternalLink ? $externalLink : null,
            'uploader_id' => Auth::id(),
            'language' => $_POST['language'] ?? 'Arabic',
            'publication_date' => $_POST['publication_date'] ?? null,
            'publisher' => $_POST['publisher'] ?? null,
            'isbn' => $_POST['isbn'] ?? null,
            'pages' => $_POST['pages'] ?? null,
            'edition' => $_POST['edition'] ?? null,
            'status' => (bool) setting('require_book_approval', true) ? 'pending' : 'approved',
        ]);
        Book::updateMetadata($bookId, ['slug' => make_slug($title, $bookId)]);

        flash_set('success', 'Your book was submitted' . ((bool) setting('require_book_approval', true) ? ' and is awaiting admin approval.' : '.'));
        redirect('profile.php?u=' . urlencode($_SESSION['username']) . '#uploads');
    }
}

$pageTitle = 'Upload a Book - ' . SITE_NAME;
include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
$categories = Category::all();
?>

<nav class="breadcrumbs"><a href="<?= url('index.php') ?>">Home</a><span class="crumb-sep">✦</span><span>Upload a Book</span></nav>

<div class="form-card" style="max-width:720px;margin:0 auto;">
    <h1 class="page-title">Share a Book with the Ummah</h1>
    <p class="text-muted">Upload any Islamic book in PDF, DOCX, PPTX, TXT, EPUB, MOBI, ODT, or RTF — or simply link to it elsewhere. New uploads are reviewed before they appear publicly.</p>

    <?php if ($limitReached): ?>
        <div class="alert alert-error">You've reached today's upload limit (<?= $limit ?> books/day). Please try again tomorrow.</div>
    <?php endif; ?>
    <?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>

    <form method="post" enctype="multipart/form-data" style="<?= $limitReached ? 'opacity:.5;pointer-events:none;' : '' ?>">
        <?= Csrf::field() ?>

        <div class="form-group">
            <label class="form-label">Book Title *</label>
            <input type="text" name="title" class="form-input" required maxlength="255" value="<?= e($old['title']) ?>">
        </div>

        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Category *</label>
                <select name="category_id" class="form-select" required>
                    <option value="">Choose a category…</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= (string) $old['category_id'] === (string) $cat['id'] ? 'selected' : '' ?>><?= e($cat['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Author</label>
                <input type="text" name="author_name" class="form-input" placeholder="e.g. Ibn Kathir" value="<?= e($old['author_name']) ?>">
                <div class="form-hint">New author names are added automatically.</div>
            </div>
        </div>

        <div class="form-group">
            <label class="form-label">Description</label>
            <textarea name="description" class="form-textarea" placeholder="What is this book about?"><?= e($old['description']) ?></textarea>
        </div>

        <div class="form-group">
            <label class="form-label">Cover Image</label>
            <input type="file" name="cover_image" class="form-input" accept="image/jpeg,image/png,image/webp">
            <div class="form-hint">JPG, PNG, or WEBP — up to <?= format_bytes(MAX_COVER_SIZE) ?>.</div>
        </div>

        <div class="form-group">
            <label class="form-label">Book Source *</label>
            <div class="theme-options">
                <label class="theme-option selected" style="cursor:pointer;">
                    <input type="radio" name="source_type" value="file" checked onclick="document.getElementById('file-src').style.display='block';document.getElementById('link-src').style.display='none';">
                    Upload a file
                </label>
                <label class="theme-option" style="cursor:pointer;">
                    <input type="radio" name="source_type" value="link" onclick="document.getElementById('file-src').style.display='none';document.getElementById('link-src').style.display='block';">
                    Link to another site
                </label>
            </div>
        </div>

        <div id="file-src" class="form-group">
            <input type="file" name="book_file" class="form-input" accept=".pdf,.doc,.docx,.ppt,.pptx,.txt,.epub,.mobi,.odt,.rtf">
            <div class="form-hint">PDF, DOC/DOCX, PPT/PPTX, TXT, EPUB, MOBI, ODT, RTF — up to <?= format_bytes(MAX_UPLOAD_SIZE) ?>. Only PDF, EPUB, and TXT can be read online; other formats are download-only.</div>
        </div>
        <div id="link-src" class="form-group" style="display:none;">
            <input type="url" name="external_link" class="form-input" placeholder="https://example.com/book" value="<?= e($old['external_link']) ?>">
            <div class="form-hint">Readers will be redirected to this link to read or download.</div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Language</label>
                <select name="language" class="form-select">
                    <?php foreach (['Arabic', 'English', 'Urdu', 'French', 'Malay', 'Turkish', 'Other'] as $lang): ?>
                        <option value="<?= $lang ?>" <?= $old['language'] === $lang ? 'selected' : '' ?>><?= $lang ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Publisher</label>
                <input type="text" name="publisher" class="form-input" value="<?= e($old['publisher']) ?>">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Pages</label>
                <input type="number" name="pages" class="form-input" min="1" value="<?= e((string) $old['pages']) ?>">
            </div>
            <div class="form-group">
                <label class="form-label">ISBN</label>
                <input type="text" name="isbn" class="form-input" value="<?= e($old['isbn']) ?>">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Edition</label>
                <input type="text" name="edition" class="form-input" value="<?= e($old['edition']) ?>">
            </div>
            <div class="form-group">
                <label class="form-label">Publication Date</label>
                <input type="date" name="publication_date" class="form-input" value="<?= e($old['publication_date']) ?>">
            </div>
        </div>

        <button type="submit" class="btn btn-primary btn-block btn-lg">Submit Book</button>
        <p class="form-hint text-center" style="margin-top:1rem;">You've uploaded <?= $uploadsToday ?> of <?= $limit ?> books today.</p>
    </form>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
