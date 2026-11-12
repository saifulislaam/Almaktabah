<?php
require_once __DIR__ . '/../config/config.php';
Admin::requireAdmin();
Admin::requirePermission('manage_books');

$bookId = (int) ($_GET['id'] ?? 0);
$book = $bookId ? Book::find($bookId) : null;
if (!$book) { flash_set('error', 'Book not found.'); redirect('admin/books.php'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::verifyOrDie();
    $action = $_POST['action'] ?? '';

    if ($action === 'update') {
        $authorId = trim($_POST['author_name'] ?? '') !== '' ? Author::findOrCreate(trim($_POST['author_name'])) : null;
        Book::updateMetadata($bookId, [
            'title' => trim($_POST['title'] ?? ''), 'category_id' => (int) ($_POST['category_id'] ?? 0), 'author_id' => $authorId,
            'description' => trim($_POST['description'] ?? '') ?: null, 'language' => $_POST['language'] ?? 'Arabic',
            'publisher' => $_POST['publisher'] ?? null, 'isbn' => $_POST['isbn'] ?? null,
            'pages' => $_POST['pages'] ?? null, 'edition' => $_POST['edition'] ?? null,
        ]);
        if (!empty($_FILES['cover_image']) && $_FILES['cover_image']['error'] !== UPLOAD_ERR_NO_FILE) {
            $up = Upload::handleCoverImage($_FILES['cover_image']);
            if ($up['success']) Book::updateCover($bookId, $up['filename']);
        }
        Admin::log('edit_book', $book['title']);
        flash_set('success', 'Book updated.');
    } elseif ($action === 'approve') {
        Book::updateStatus($bookId, 'approved');
        if ($book['category_id']) Notification::notifyCategoryFollowers((int) $book['category_id'], $book['title'], $bookId);
        if ($book['uploader_id']) Notification::send((int) $book['uploader_id'], 'book_approved', 'Your book "' . $book['title'] . '" was approved and is now live.', 'book.php?id=' . $bookId);
        Admin::log('approve_book', $book['title']);
        flash_set('success', 'Book approved and published.');
    } elseif ($action === 'reject') {
        $reason = trim($_POST['reject_reason'] ?? '');
        Book::updateStatus($bookId, 'rejected', $reason ?: null);
        if ($book['uploader_id']) Notification::send((int) $book['uploader_id'], 'book_rejected', 'Your book "' . $book['title'] . '" was not approved.' . ($reason ? ' Reason: ' . $reason : ''), 'profile.php?u=' . urlencode($_SESSION['username'] ?? ''));
        Admin::log('reject_book', $book['title']);
        flash_set('success', 'Book rejected.');
    } elseif ($action === 'feature') {
        Book::toggleFeatured($bookId);
        Admin::log('toggle_featured', $book['title']);
        flash_set('success', 'Featured status updated.');
    } elseif ($action === 'delete') {
        Book::delete($bookId);
        Admin::log('delete_book', $book['title']);
        flash_set('success', 'Book deleted.');
        redirect('admin/books.php');
    }
    redirect('admin/book-edit.php?id=' . $bookId);
}

$pageTitle = 'Edit Book';
$activeNav = 'books';
include __DIR__ . '/includes/layout-start.php';

$book = Book::find($bookId);
$categories = Category::all();
?>

<div class="admin-panel">
    <div class="admin-panel-head">
        <h3><?= e($book['title']) ?> <span class="status-pill status-<?= e($book['status']) ?>"><?= e($book['status']) ?></span></h3>
        <div class="flex gap-1">
            <?php if ($book['status'] !== 'approved'): ?>
            <form method="post"><?= Csrf::field() ?><input type="hidden" name="action" value="approve"><button class="btn btn-sm btn-primary"><?= icon('check') ?> Approve</button></form>
            <?php endif; ?>
            <button class="btn btn-sm btn-outline" data-modal-open="#reject-modal"><?= icon('x-circle') ?> Reject</button>
            <form method="post"><?= Csrf::field() ?><input type="hidden" name="action" value="feature"><button class="btn btn-sm btn-outline"><?= icon('star') ?> <?= $book['featured'] ? 'Unfeature' : 'Feature' ?></button></form>
            <button class="btn btn-sm btn-danger-outline" data-modal-open="#delete-modal"><?= icon('trash') ?> Delete</button>
        </div>
    </div>

    <form method="post" enctype="multipart/form-data">
        <?= Csrf::field() ?>
        <input type="hidden" name="action" value="update">
        <div class="form-row">
            <div class="form-group"><label class="form-label">Title</label><input type="text" name="title" class="form-input" value="<?= e($book['title']) ?>" required></div>
            <div class="form-group"><label class="form-label">Author</label><input type="text" name="author_name" class="form-input" value="<?= e($book['author_name'] ?? '') ?>"></div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Category</label>
                <select name="category_id" class="form-select">
                    <?php foreach ($categories as $c): ?><option value="<?= $c['id'] ?>" <?= (int) $book['category_id'] === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Language</label>
                <select name="language" class="form-select">
                    <?php foreach (['Arabic', 'English', 'Urdu', 'French', 'Malay', 'Turkish', 'Other'] as $lang): ?><option value="<?= $lang ?>" <?= $book['language'] === $lang ? 'selected' : '' ?>><?= $lang ?></option><?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="form-group"><label class="form-label">Description</label><textarea name="description" class="form-textarea"><?= e($book['description'] ?? '') ?></textarea></div>
        <div class="form-row">
            <div class="form-group"><label class="form-label">Publisher</label><input type="text" name="publisher" class="form-input" value="<?= e($book['publisher'] ?? '') ?>"></div>
            <div class="form-group"><label class="form-label">ISBN</label><input type="text" name="isbn" class="form-input" value="<?= e($book['isbn'] ?? '') ?>"></div>
        </div>
        <div class="form-row">
            <div class="form-group"><label class="form-label">Pages</label><input type="number" name="pages" class="form-input" value="<?= e((string) $book['pages']) ?>"></div>
            <div class="form-group"><label class="form-label">Edition</label><input type="text" name="edition" class="form-input" value="<?= e($book['edition'] ?? '') ?>"></div>
        </div>
        <div class="form-group">
            <label class="form-label">Replace Cover Image</label>
            <div class="flex items-center gap-2">
                <?php if ($book['cover_image']): ?><img src="<?= e(UPLOAD_URL_COVERS . '/' . $book['cover_image']) ?>" style="width:60px;height:84px;object-fit:cover;border-radius:6px;"><?php endif; ?>
                <input type="file" name="cover_image" class="form-input" accept="image/jpeg,image/png,image/webp">
            </div>
        </div>
        <div class="form-group">
            <label class="form-label">File</label>
            <p class="text-muted" style="font-size:.85rem;">
                <?= $book['external_link'] ? 'External link: ' . e($book['external_link']) : ($book['file_path'] ? e(strtoupper($book['file_type'])) . ' — ' . format_bytes($book['file_size']) : 'No file') ?>
                &middot; Uploaded by <?= e($book['uploader_username'] ?? 'Unknown') ?> on <?= date('M j, Y', strtotime($book['created_at'])) ?>
            </p>
        </div>
        <button type="submit" class="btn btn-primary">Save Changes</button>
    </form>
</div>

<div class="modal-overlay" id="reject-modal">
    <div class="modal-box">
        <h3>Reject this book</h3>
        <form method="post">
            <?= Csrf::field() ?><input type="hidden" name="action" value="reject">
            <div class="form-group"><label class="form-label">Reason (shown to the uploader)</label><textarea name="reject_reason" class="form-textarea" placeholder="e.g. Duplicate of an existing title"></textarea></div>
            <div class="modal-actions"><button type="button" class="btn btn-ghost" data-modal-close>Cancel</button><button type="submit" class="btn btn-danger">Reject Book</button></div>
        </form>
    </div>
</div>
<div class="modal-overlay" id="delete-modal">
    <div class="modal-box">
        <h3>Delete this book?</h3>
        <p class="text-muted">This permanently removes the book, its file, and cover image. This cannot be undone.</p>
        <form method="post">
            <?= Csrf::field() ?><input type="hidden" name="action" value="delete">
            <div class="modal-actions"><button type="button" class="btn btn-ghost" data-modal-close>Cancel</button><button type="submit" class="btn btn-danger">Delete Permanently</button></div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/includes/layout-end.php'; ?>
