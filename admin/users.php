<?php
require_once __DIR__ . '/../config/config.php';
Admin::requireAdmin();
Admin::requirePermission('manage_users');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::verifyOrDie();
    $userId = (int) ($_POST['user_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $target = User::find($userId);

    if ($target && in_array($target['role'], ['admin'], true) && $userId !== Auth::id()) {
        flash_set('error', 'You cannot modify another main admin account here — use Admin Accounts.');
    } elseif ($target) {
        if ($action === 'suspend') { User::setStatus($userId, 'suspended'); Admin::log('suspend_user', $target['username']); }
        elseif ($action === 'ban') { User::setStatus($userId, 'banned'); Admin::log('ban_user', $target['username']); }
        elseif ($action === 'activate') { User::setStatus($userId, 'active'); Admin::log('activate_user', $target['username']); }
        elseif ($action === 'delete') { User::delete($userId); Admin::log('delete_user', $target['username']); }
        flash_set('success', 'User updated.');
    }
    redirect('admin/users.php?' . http_build_query(array_diff_key($_GET, [])));
}

$pageTitle = 'Manage Users';
$activeNav = 'users';
include __DIR__ . '/includes/layout-start.php';

$page = max(1, (int) ($_GET['page'] ?? 1));
$filter = $_GET['filter'] ?? '';
$listing = User::listAll($filter, $page, 30);
?>

<div class="admin-panel">
    <div class="admin-panel-head"><h3><?= $listing['total'] ?> Users</h3></div>
    <form method="get" class="admin-filters">
        <select name="filter" onchange="this.form.submit()">
            <option value="">All Users</option>
            <option value="active" <?= $filter === 'active' ? 'selected' : '' ?>>Active</option>
            <option value="suspended" <?= $filter === 'suspended' ? 'selected' : '' ?>>Suspended</option>
            <option value="banned" <?= $filter === 'banned' ? 'selected' : '' ?>>Banned</option>
        </select>
    </form>

    <div class="table-responsive">
        <table class="data-table">
            <thead><tr><th>User</th><th>Email</th><th>Uploads</th><th>Joined</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($listing['rows'] as $u): ?>
                <tr>
                    <td class="flex items-center gap-1"><?= avatar_html($u, 30) ?> <?= e($u['display_name'] ?: $u['username']) ?></td>
                    <td><?= e($u['email']) ?></td>
                    <td><?= (int) $u['upload_count'] ?></td>
                    <td><?= date('M j, Y', strtotime($u['created_at'])) ?></td>
                    <td><span class="status-pill status-<?= $u['status'] === 'active' ? 'approved' : 'rejected' ?>"><?= e($u['status']) ?></span></td>
                    <td class="row-actions">
                        <a class="btn btn-icon btn-ghost" href="<?= url('profile.php?u=' . urlencode($u['username'])) ?>" target="_blank" aria-label="View profile"><?= icon('eye') ?></a>
                        <?php if ($u['status'] !== 'active'): ?>
                        <form method="post"><?= Csrf::field() ?><input type="hidden" name="user_id" value="<?= $u['id'] ?>"><input type="hidden" name="action" value="activate"><button class="btn btn-sm btn-outline">Reactivate</button></form>
                        <?php else: ?>
                        <form method="post"><?= Csrf::field() ?><input type="hidden" name="user_id" value="<?= $u['id'] ?>"><input type="hidden" name="action" value="suspend"><button class="btn btn-sm btn-outline">Suspend</button></form>
                        <form method="post"><?= Csrf::field() ?><input type="hidden" name="user_id" value="<?= $u['id'] ?>"><input type="hidden" name="action" value="ban"><button class="btn btn-sm btn-danger-outline">Ban</button></form>
                        <?php endif; ?>
                        <form method="post" data-confirm="Permanently remove this user and their account? This cannot be undone."><?= Csrf::field() ?><input type="hidden" name="user_id" value="<?= $u['id'] ?>"><input type="hidden" name="action" value="delete"><button class="btn btn-icon btn-ghost" style="color:var(--danger);" aria-label="Delete"><?= icon('trash') ?></button></form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$listing['rows']): ?><tr><td colspan="6" class="text-center text-muted" style="padding:2rem;">No users match this filter.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php render_pagination($listing['page'], $listing['pages'], array_filter(['filter' => $filter]), 'admin/users.php'); ?>
</div>

<?php include __DIR__ . '/includes/layout-end.php'; ?>
