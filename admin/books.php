<?php
require_once __DIR__ . '/../config/config.php';
Admin::requireAdmin();
Admin::requirePermission('manage_books');

// -------- Bulk actions --------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::verifyOrDie();
    $ids = array_filter(array_map('intval', explode(',', $_POST['ids'] ?? '')));
    $bulkAction = $_POST['bulk_action'] ?? '';
    if ($ids && $bulkAction) {
        foreach ($ids as $id) {
            match ($bulkAction) {
                'approve' => Book::updateStatus($id, 'approved'),
                'reject'  => Book::updateStatus($id, 'rejected'),
                'feature' => Book::toggleFeatured($id),
                'delete'  => Book::delete($id),
                default   => null,
            };
        }
        Admin::log('bulk_' . $bulkAction, count($ids) . ' book(s)');
        flash_set('success', 'Bulk action applied to ' . count($ids) . ' book(s).');
    }
    redirect('admin/books.php?' . http_build_query($_GET));
}

$pageTitle = 'Manage Books';
$activeNav = 'books';
include __DIR__ . '/includes/layout-start.php';

$page = max(1, (int) ($_GET['page'] ?? 1));
$sort = $_GET['sort'] ?? 'newest';
$statusFilter = $_GET['status'] ?? '';
$categoryFilter = (int) ($_GET['category_id'] ?? 0);

$filters = ['status' => $statusFilter ?: null];
if ($categoryFilter) $filters['category_id'] = $categoryFilter;
if (!$statusFilter) {
    // Admin view without an explicit status filter should show everything, not just approved.
    $result = Database::fetchAll(
        "SELECT b.*, c.name AS category_name, a.name AS author_name, u.username AS uploader_username
         FROM books b LEFT JOIN categories c ON c.id=b.category_id LEFT JOIN authors a ON a.id=b.author_id LEFT JOIN users u ON u.id=b.uploader_id
         " . ($categoryFilter ? 'WHERE b.category_id = ' . (int) $categoryFilter : '') . "
         ORDER BY b.created_at DESC LIMIT 200"
    );
    $books = $result;
    $total = count($result);
    $pages = 1;
} else {
    $listing = Book::listFiltered($filters, $sort, $page, 30);
    $books = $listing['rows'];
    $total = $listing['total'];
    $pages = $listing['pages'];
}

$categories = Category::all();
?>

<div class="admin-panel">
    <div class="admin-panel-head"><h3><?= $total ?> Books</h3></div>
    <form method="get" class="admin-filters">
        <select name="status" onchange="this.form.submit()">
            <option value="">All Statuses</option>
            <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
            <option value="approved" <?= $statusFilter === 'approved' ? 'selected' : '' ?>>Approved</option>
            <option value="rejected" <?= $statusFilter === 'rejected' ? 'selected' : '' ?>>Rejected</option>
        </select>
        <select name="category_id" onchange="this.form.submit()">
            <option value="">All Categories</option>
            <?php foreach ($categories as $c): ?><option value="<?= $c['id'] ?>" <?= $categoryFilter === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
        </select>
        <select name="sort" onchange="this.form.submit()">
            <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest</option>
            <option value="popular" <?= $sort === 'popular' ? 'selected' : '' ?>>Most Viewed</option>
            <option value="downloads" <?= $sort === 'downloads' ? 'selected' : '' ?>>Most Downloaded</option>
        </select>
    </form>

    <form method="post" data-bulk-form="#books-table">
        <?= Csrf::field() ?>
        <input type="hidden" name="ids" value="">
        <?php foreach ($_GET as $k => $v): ?><input type="hidden" name="<?= e($k) ?>" value="<?= e($v) ?>"><?php endforeach; ?>
        <div class="bulk-bar" style="display:none;">
            <span><span data-bulk-count>0</span> selected</span>
            <button class="btn btn-sm btn-outline" name="bulk_action" value="approve">Approve</button>
            <button class="btn btn-sm btn-outline" name="bulk_action" value="reject">Reject</button>
            <button class="btn btn-sm btn-outline" name="bulk_action" value="feature">Feature/Unfeature</button>
            <button class="btn btn-sm btn-danger-outline" name="bulk_action" value="delete" data-confirm="Delete all selected books? This cannot be undone.">Delete</button>
        </div>

        <div class="table-responsive">
            <table class="data-table" id="books-table">
                <thead><tr>
                    <th><input type="checkbox" data-select-all></th>
                    <th>Cover</th><th>Title</th><th>Author</th><th>Category</th><th>Uploader</th><th>Views</th><th>Status</th><th>Uploaded</th><th>Actions</th>
                </tr></thead>
                <tbody>
                <?php foreach ($books as $b): ?>
                    <tr>
                        <td><input type="checkbox" data-row-select value="<?= $b['id'] ?>"></td>
                        <td><?php if (!empty($b['cover_image'])): ?><img class="cell-cover" src="<?= e(UPLOAD_URL_COVERS . '/' . $b['cover_image']) ?>" alt=""><?php else: ?>—<?php endif; ?></td>
                        <td><a href="<?= url('admin/book-edit.php?id=' . $b['id']) ?>"><?= e($b['title']) ?></a></td>
                        <td><?= e($b['author_name'] ?? '—') ?></td>
                        <td><?= e($b['category_name'] ?? '—') ?></td>
                        <td><?= e($b['uploader_username'] ?? '—') ?></td>
                        <td><?= format_count((int) $b['views_count']) ?></td>
                        <td><span class="status-pill status-<?= e($b['status']) ?>"><?= e($b['status']) ?></span></td>
                        <td><?= date('M j, Y', strtotime($b['created_at'])) ?></td>
                        <td class="row-actions">
                            <a class="btn btn-icon btn-ghost" href="<?= url('admin/book-edit.php?id=' . $b['id']) ?>" aria-label="Edit"><?= icon('edit') ?></a>
                            <a class="btn btn-icon btn-ghost" href="<?= url('book.php?id=' . $b['id'] . '&slug=' . $b['slug']) ?>" target="_blank" aria-label="View"><?= icon('eye') ?></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$books): ?><tr><td colspan="10" class="text-center text-muted" style="padding:2rem;">No books match these filters.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </form>
    <?php if ($statusFilter) render_pagination($page, $pages, array_filter(['status' => $statusFilter, 'category_id' => $categoryFilter ?: null, 'sort' => $sort]), 'admin/books.php'); ?>
</div>

<?php include __DIR__ . '/includes/layout-end.php'; ?>
