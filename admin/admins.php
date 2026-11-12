<?php
require_once __DIR__ . '/../config/config.php';
Admin::requireAdmin();

if (Auth::role() !== 'admin') {
    http_response_code(403);
    die('Only the main administrator can manage admin accounts.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::verifyOrDie();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $permissions = array_values(array_intersect($_POST['permissions'] ?? [], array_keys(Admin::allPermissions())));

        if (strlen($password) < 8) {
            flash_set('error', 'Password must be at least 8 characters.');
        } else {
            $result = Auth::register($username, $email, $password, true);
            if ($result['success']) {
                Database::execute('UPDATE users SET role = "subadmin", permissions = ? WHERE id = ?', [json_encode($permissions), $result['user_id']]);
                Admin::log('create_subadmin', $username);
                flash_set('success', 'Sub-admin account created.');
            } else {
                flash_set('error', $result['error']);
            }
        }
    } elseif ($action === 'update_permissions') {
        $targetId = (int) ($_POST['user_id'] ?? 0);
        $permissions = array_values(array_intersect($_POST['permissions'] ?? [], array_keys(Admin::allPermissions())));
        Database::execute('UPDATE users SET permissions = ? WHERE id = ? AND role = "subadmin"', [json_encode($permissions), $targetId]);
        Admin::log('update_subadmin_perms', 'user #' . $targetId);
        flash_set('success', 'Permissions updated.');
    } elseif ($action === 'remove') {
        $targetId = (int) ($_POST['user_id'] ?? 0);
        Database::execute('DELETE FROM users WHERE id = ? AND role = "subadmin"', [$targetId]);
        Admin::log('remove_subadmin', 'user #' . $targetId);
        flash_set('success', 'Sub-admin removed.');
    }
    redirect('admin/admins.php');
}

$pageTitle = 'Admin Accounts';
$activeNav = 'admins';
include __DIR__ . '/includes/layout-start.php';

$subadmins = Database::fetchAll("SELECT * FROM users WHERE role = 'subadmin' ORDER BY created_at DESC");
$allPerms = Admin::allPermissions();
?>

<div class="admin-panel">
    <div class="admin-panel-head"><h3>Create Sub-Admin</h3></div>
    <p class="text-muted" style="font-size:.85rem;">Sub-admins sign in with a username and password at <code><?= e(url('admin/index.php')) ?></code> and can only access the sections you grant below.</p>
    <form method="post">
        <?= Csrf::field() ?><input type="hidden" name="action" value="create">
        <div class="form-row">
            <div class="form-group"><label class="form-label">Username</label><input type="text" name="username" class="form-input" required></div>
            <div class="form-group"><label class="form-label">Email</label><input type="email" name="email" class="form-input" required></div>
        </div>
        <div class="form-group"><label class="form-label">Password</label><input type="password" name="password" class="form-input" required minlength="8"></div>
        <div class="form-group">
            <label class="form-label">Permissions</label>
            <div class="perm-checklist">
                <?php foreach ($allPerms as $key => $label): ?>
                    <label><input type="checkbox" name="permissions[]" value="<?= e($key) ?>"> <?= e($label) ?></label>
                <?php endforeach; ?>
            </div>
        </div>
        <button type="submit" class="btn btn-primary">Create Sub-Admin</button>
    </form>
</div>

<div class="admin-panel">
    <div class="admin-panel-head"><h3>Existing Sub-Admins (<?= count($subadmins) ?>)</h3></div>
    <?php if (!$subadmins): ?><p class="text-muted">No sub-admins yet.</p><?php endif; ?>
    <?php foreach ($subadmins as $sa): $saPerms = json_decode($sa['permissions'] ?? '[]', true) ?: []; ?>
        <details style="border:1px solid var(--border);border-radius:var(--radius-md);padding:14px 18px;margin-bottom:10px;">
            <summary style="cursor:pointer;font-weight:700;display:flex;justify-content:space-between;align-items:center;">
                <span><?= e($sa['username']) ?> <span class="text-muted" style="font-weight:400;">(<?= e($sa['email']) ?>)</span></span>
                <span class="status-pill status-<?= $sa['status'] === 'active' ? 'approved' : 'rejected' ?>"><?= e($sa['status']) ?></span>
            </summary>
            <form method="post" style="margin-top:14px;">
                <?= Csrf::field() ?><input type="hidden" name="action" value="update_permissions"><input type="hidden" name="user_id" value="<?= $sa['id'] ?>">
                <div class="perm-checklist">
                    <?php foreach ($allPerms as $key => $label): ?>
                        <label><input type="checkbox" name="permissions[]" value="<?= e($key) ?>" <?= in_array($key, $saPerms, true) ? 'checked' : '' ?>> <?= e($label) ?></label>
                    <?php endforeach; ?>
                </div>
                <div class="flex gap-1" style="margin-top:12px;">
                    <button type="submit" class="btn btn-sm btn-primary">Save Permissions</button>
                </div>
            </form>
            <form method="post" data-confirm="Remove this sub-admin account permanently?" style="margin-top:8px;">
                <?= Csrf::field() ?><input type="hidden" name="action" value="remove"><input type="hidden" name="user_id" value="<?= $sa['id'] ?>">
                <button type="submit" class="btn btn-sm btn-danger-outline">Remove Sub-Admin</button>
            </form>
        </details>
    <?php endforeach; ?>
</div>

<?php include __DIR__ . '/includes/layout-end.php'; ?>
