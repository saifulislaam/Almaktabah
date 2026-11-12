<?php
require_once __DIR__ . '/../config/config.php';
Admin::requireAdmin();
Admin::requirePermission('manage_categories');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::verifyOrDie();
    $action = $_POST['action'] ?? '';

    if ($action === 'create_category') {
        Category::create(trim($_POST['name']), trim($_POST['description']) ?: null, trim($_POST['icon']) ?: 'book', (int) ($_POST['parent_id'] ?? 0) ?: null);
        flash_set('success', 'Category created.');
    } elseif ($action === 'update_category') {
        Category::update((int) $_POST['id'], trim($_POST['name']), trim($_POST['description']) ?: null, trim($_POST['icon']) ?: 'book', (int) ($_POST['parent_id'] ?? 0) ?: null);
        flash_set('success', 'Category updated.');
    } elseif ($action === 'delete_category') {
        Category::delete((int) $_POST['id']);
        flash_set('success', 'Category deleted. Its books are now uncategorized.');
    } elseif ($action === 'update_author') {
        Author::update((int) $_POST['id'], trim($_POST['name'] ?? ''), trim($_POST['bio'] ?? '') ?: null, trim($_POST['era'] ?? '') ?: null);
        if (!empty($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
            $up = Upload::handleAvatar($_FILES['image']);
            if ($up['success']) Author::updateImage((int) $_POST['id'], $up['filename']);
        }
        flash_set('success', 'Author updated.');
    } elseif ($action === 'delete_author') {
        Author::delete((int) $_POST['id']);
        flash_set('success', 'Author deleted. Their books are now unattributed.');
    }
    Admin::log($action, '');
    redirect('admin/categories.php?tab=' . ($_POST['tab'] ?? 'categories'));
}

$pageTitle = 'Categories & Authors';
$activeNav = 'categories';
include __DIR__ . '/includes/layout-start.php';

$tab = $_GET['tab'] ?? 'categories';
$categories = Category::withBookCounts();
$authorsPage = max(1, (int) ($_GET['page'] ?? 1));
$authors = Author::all(30, ($authorsPage - 1) * 30);
$authorTotal = Author::count();
?>

<div class="tabs">
    <a href="?tab=categories" class="tab-link <?= $tab === 'categories' ? 'active' : '' ?>">Categories</a>
    <a href="?tab=authors" class="tab-link <?= $tab === 'authors' ? 'active' : '' ?>">Authors</a>
</div>

<?php if ($tab === 'categories'): ?>
<div class="admin-panel">
    <div class="admin-panel-head"><h3>Add Category</h3></div>
    <form method="post" class="form-row">
        <?= Csrf::field() ?><input type="hidden" name="action" value="create_category"><input type="hidden" name="tab" value="categories">
        <div class="form-group"><label class="form-label">Name</label><input type="text" name="name" class="form-input" required></div>
        <div class="form-group"><label class="form-label">Icon key</label><input type="text" name="icon" class="form-input" placeholder="book, quran, hadith…"></div>
        <div class="form-group" style="grid-column:1/-1;"><label class="form-label">Description</label><input type="text" name="description" class="form-input"></div>
        <div style="grid-column:1/-1;"><button type="submit" class="btn btn-primary">Add Category</button></div>
    </form>
</div>

<div class="admin-panel">
    <div class="admin-panel-head"><h3><?= count($categories) ?> Categories</h3></div>
    <div class="table-responsive">
        <table class="data-table">
            <thead><tr><th>Icon</th><th>Name</th><th>Slug</th><th>Books</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($categories as $c): ?>
                <tr>
                    <td><?= icon($c['icon']) ?></td>
                    <td><?= e($c['name']) ?></td>
                    <td class="text-muted"><?= e($c['slug']) ?></td>
                    <td><?= (int) $c['book_count'] ?></td>
                    <td class="row-actions">
                        <button class="btn btn-icon btn-ghost" data-modal-open="#edit-cat-<?= $c['id'] ?>" aria-label="Edit"><?= icon('edit') ?></button>
                        <form method="post" data-confirm="Delete this category? Its books become uncategorized."><?= Csrf::field() ?><input type="hidden" name="action" value="delete_category"><input type="hidden" name="tab" value="categories"><input type="hidden" name="id" value="<?= $c['id'] ?>"><button class="btn btn-icon btn-ghost" style="color:var(--danger);" aria-label="Delete"><?= icon('trash') ?></button></form>
                    </td>
                </tr>
                <div class="modal-overlay" id="edit-cat-<?= $c['id'] ?>">
                    <div class="modal-box">
                        <h3>Edit Category</h3>
                        <form method="post">
                            <?= Csrf::field() ?><input type="hidden" name="action" value="update_category"><input type="hidden" name="tab" value="categories"><input type="hidden" name="id" value="<?= $c['id'] ?>">
                            <div class="form-group"><label class="form-label">Name</label><input type="text" name="name" class="form-input" value="<?= e($c['name']) ?>" required></div>
                            <div class="form-group"><label class="form-label">Icon key</label><input type="text" name="icon" class="form-input" value="<?= e($c['icon']) ?>"></div>
                            <div class="form-group"><label class="form-label">Description</label><textarea name="description" class="form-textarea"><?= e($c['description'] ?? '') ?></textarea></div>
                            <div class="modal-actions"><button type="button" class="btn btn-ghost" data-modal-close>Cancel</button><button type="submit" class="btn btn-primary">Save</button></div>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php else: ?>
<div class="admin-panel">
    <div class="admin-panel-head"><h3><?= $authorTotal ?> Authors</h3></div>
    <div class="table-responsive">
        <table class="data-table">
            <thead><tr><th>Photo</th><th>Name</th><th>Books</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($authors as $a): ?>
                <tr>
                    <td><?php if (!empty($a['image'])): ?><img class="cell-cover" style="border-radius:50%;" src="<?= e(UPLOAD_URL_AVATARS . '/' . $a['image']) ?>" alt=""><?php else: ?>—<?php endif; ?></td>
                    <td><?= e($a['name']) ?></td>
                    <td><?= (int) $a['book_count'] ?></td>
                    <td class="row-actions">
                        <button class="btn btn-icon btn-ghost" data-modal-open="#edit-auth-<?= $a['id'] ?>" aria-label="Edit"><?= icon('edit') ?></button>
                        <form method="post" data-confirm="Delete this author? Their books become unattributed."><?= Csrf::field() ?><input type="hidden" name="action" value="delete_author"><input type="hidden" name="tab" value="authors"><input type="hidden" name="id" value="<?= $a['id'] ?>"><button class="btn btn-icon btn-ghost" style="color:var(--danger);" aria-label="Delete"><?= icon('trash') ?></button></form>
                    </td>
                </tr>
                <div class="modal-overlay" id="edit-auth-<?= $a['id'] ?>">
                    <div class="modal-box">
                        <h3>Edit Author</h3>
                        <form method="post" enctype="multipart/form-data">
                            <?= Csrf::field() ?><input type="hidden" name="action" value="update_author"><input type="hidden" name="tab" value="authors"><input type="hidden" name="id" value="<?= $a['id'] ?>">
                            <div class="form-group"><label class="form-label">Name</label><input type="text" name="name" class="form-input" value="<?= e($a['name']) ?>" required></div>
                            <div class="form-group"><label class="form-label">Era</label><input type="text" name="era" class="form-input" value="<?= e($a['era'] ?? '') ?>" placeholder="e.g. Classical, Contemporary"></div>
                            <div class="form-group"><label class="form-label">Bio</label><textarea name="bio" class="form-textarea"><?= e($a['bio'] ?? '') ?></textarea></div>
                            <div class="form-group"><label class="form-label">Photo</label><input type="file" name="image" class="form-input" accept="image/jpeg,image/png,image/webp"></div>
                            <div class="modal-actions"><button type="button" class="btn btn-ghost" data-modal-close>Cancel</button><button type="submit" class="btn btn-primary">Save</button></div>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php render_pagination($authorsPage, (int) ceil($authorTotal / 30), ['tab' => 'authors'], 'admin/categories.php'); ?>
</div>
<?php endif; ?>

<?php include __DIR__ . '/includes/layout-end.php'; ?>
