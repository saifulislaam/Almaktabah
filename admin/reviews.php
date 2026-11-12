<?php
require_once __DIR__ . '/../config/config.php';
Admin::requireAdmin();
Admin::requirePermission('manage_reviews');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::verifyOrDie();
    $reviewId = (int) ($_POST['review_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    if ($action === 'hide') { Review::setStatus($reviewId, 'hidden'); flash_set('success', 'Review hidden.'); }
    elseif ($action === 'approve') { Review::setStatus($reviewId, 'approved'); flash_set('success', 'Review restored.'); }
    elseif ($action === 'delete') { Review::delete($reviewId); flash_set('success', 'Review deleted.'); }
    Admin::log('moderate_review', $action . ' #' . $reviewId);
    redirect('admin/reviews.php');
}

$pageTitle = 'Reviews';
$activeNav = 'reviews';
include __DIR__ . '/includes/layout-start.php';

$reviews = Review::recent(50);
?>

<div class="admin-panel">
    <div class="admin-panel-head"><h3>Recent Reviews</h3></div>
    <div class="table-responsive">
        <table class="data-table">
            <thead><tr><th>Book</th><th>User</th><th>Rating</th><th>Comment</th><th>Date</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($reviews as $r): ?>
                <tr>
                    <td><a href="<?= url('book.php?id=' . $r['book_id']) ?>" target="_blank"><?= e($r['book_title']) ?></a></td>
                    <td><?= e($r['username']) ?></td>
                    <td><?= star_rating_html((float) $r['rating']) ?></td>
                    <td style="max-width:260px;"><?= e(excerpt($r['comment'] ?? '', 100)) ?></td>
                    <td><?= time_ago($r['created_at']) ?></td>
                    <td class="row-actions">
                        <?php if ($r['status'] === 'approved'): ?>
                        <form method="post"><?= Csrf::field() ?><input type="hidden" name="review_id" value="<?= $r['id'] ?>"><input type="hidden" name="action" value="hide"><button class="btn btn-sm btn-outline">Hide</button></form>
                        <?php else: ?>
                        <form method="post"><?= Csrf::field() ?><input type="hidden" name="review_id" value="<?= $r['id'] ?>"><input type="hidden" name="action" value="approve"><button class="btn btn-sm btn-outline">Restore</button></form>
                        <?php endif; ?>
                        <form method="post" data-confirm="Delete this review permanently?"><?= Csrf::field() ?><input type="hidden" name="review_id" value="<?= $r['id'] ?>"><input type="hidden" name="action" value="delete"><button class="btn btn-icon btn-ghost" style="color:var(--danger);" aria-label="Delete"><?= icon('trash') ?></button></form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$reviews): ?><tr><td colspan="6" class="text-center text-muted" style="padding:2rem;">No reviews yet.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/includes/layout-end.php'; ?>
