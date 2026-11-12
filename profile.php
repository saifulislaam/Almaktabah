<?php
require_once __DIR__ . '/config/config.php';

$username = $_GET['u'] ?? '';
$profileUser = $username ? User::findByUsername($username) : null;
if (!$profileUser) { http_response_code(404); require __DIR__ . '/404.php'; exit; }

$isOwnProfile = Auth::check() && Auth::id() === (int) $profileUser['id'];

// -------- Handle "delete my own book" --------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_book') {
    Auth::requireLogin('login.php');
    Csrf::verifyOrDie();
    $bookId = (int) ($_POST['book_id'] ?? 0);
    if (Book::belongsToUser($bookId, Auth::id())) {
        Book::delete($bookId);
        flash_set('success', 'Your book was deleted.');
    } else {
        flash_set('error', 'You can only delete books you uploaded yourself.');
    }
    redirect('profile.php?u=' . urlencode($username) . '#uploads');
}

$pageTitle = ($profileUser['display_name'] ?: $profileUser['username']) . "'s Profile - " . SITE_NAME;
include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';

$uploads = User::uploadedBooks((int) $profileUser['id']);
$approvedUploads = array_filter($uploads, fn($b) => $b['status'] === 'approved');
$tab = $_GET['tab'] ?? 'uploads';
?>

<div class="profile-header">
    <?= avatar_html($profileUser, 96) ?>
    <div>
        <h1 class="page-title" style="margin-bottom:.2rem;"><?= e($profileUser['display_name'] ?: $profileUser['username']) ?></h1>
        <p class="text-muted" style="margin-bottom:.4rem;">@<?= e($profileUser['username']) ?> &middot; Joined <?= date('F Y', strtotime($profileUser['created_at'])) ?></p>
        <?php if ($profileUser['bio']): ?><p style="max-width:60ch;"><?= nl2br(e($profileUser['bio'])) ?></p><?php endif; ?>
        <div class="profile-stats">
            <div><b><?= count($approvedUploads) ?></b><span>Books Shared</span></div>
        </div>
    </div>
    <?php if ($isOwnProfile): ?><a href="<?= url('settings.php') ?>" class="btn btn-outline" style="margin-left:auto;align-self:flex-start;"><?= icon('settings') ?> Edit Profile</a><?php endif; ?>
</div>

<div class="tabs">
    <a href="#uploads" id="uploads" class="tab-link active">Uploaded Books (<?= count($uploads) ?>)</a>
</div>

<?php if (!$uploads): ?>
    <div class="empty-state"><?= icon('upload') ?><h3>No books uploaded yet</h3>
        <?php if ($isOwnProfile): ?><p><a href="<?= url('upload.php') ?>">Upload your first book</a> to share it with the community.</p><?php endif; ?>
    </div>
<?php else: foreach ($uploads as $b): ?>
    <div class="upload-list-row">
        <?php if (!empty($b['cover_image'])): ?><img src="<?= e(UPLOAD_URL_COVERS . '/' . $b['cover_image']) ?>" alt=""><?php else: ?><span class="cover-fallback"></span><?php endif; ?>
        <div style="flex:1;">
            <a href="<?= url('book.php?id=' . $b['id'] . '&slug=' . $b['slug']) ?>" class="row-title"><?= e($b['title']) ?></a>
            <div class="row-meta"><?= e($b['category_name'] ?? 'Uncategorized') ?> &middot; <?= time_ago($b['created_at']) ?> &middot; <?= format_count((int) $b['views_count']) ?> views</div>
        </div>
        <span class="status-pill status-<?= e($b['status']) ?>"><?= e($b['status']) ?></span>
        <?php if ($isOwnProfile): ?>
            <form method="post" onsubmit="return confirm('Delete this book permanently? This cannot be undone.');">
                <?= Csrf::field() ?>
                <input type="hidden" name="action" value="delete_book">
                <input type="hidden" name="book_id" value="<?= $b['id'] ?>">
                <button type="submit" class="btn btn-icon btn-ghost" style="color:var(--danger);" aria-label="Delete book"><?= icon('trash') ?></button>
            </form>
        <?php endif; ?>
    </div>
<?php endforeach; endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
