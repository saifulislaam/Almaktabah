<?php
require_once __DIR__ . '/../config/config.php';
Admin::requireAdmin();
Admin::requirePermission('manage_settings');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::verifyOrDie();
    $action = $_POST['action'] ?? '';

    if ($action === 'update_site') {
        foreach (['site_name', 'site_tagline', 'site_description', 'contact_email', 'daily_upload_limit', 'max_upload_mb'] as $key) {
            Database::execute('INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)', [$key, trim($_POST[$key] ?? '')]);
        }
        foreach (['require_book_approval', 'maintenance_mode', 'allow_guest_reading'] as $flag) {
            $val = isset($_POST[$flag]) ? '1' : '0';
            Database::execute('INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)', [$flag, $val]);
        }
        Admin::log('update_settings', 'site settings');
        flash_set('success', 'Settings saved.');
    } elseif ($action === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $me = User::find(Auth::id());
        if (!password_verify($current, $me['password'] ?? '')) {
            flash_set('error', 'Current password is incorrect.');
        } elseif (strlen($new) < 8) {
            flash_set('error', 'New password must be at least 8 characters.');
        } else {
            Database::execute('UPDATE users SET password = ? WHERE id = ?', [password_hash($new, PASSWORD_BCRYPT), Auth::id()]);
            Admin::log('change_own_password', '');
            flash_set('success', 'Password changed.');
        }
    }
    redirect('admin/settings.php');
}

$pageTitle = 'Settings';
$activeNav = 'settings';
include __DIR__ . '/includes/layout-start.php';

$usingDefaultPassword = password_verify('admin123', $adminUser['password'] ?? '');
?>

<?php if ($usingDefaultPassword): ?>
<div class="alert alert-error">
    <strong>Security notice:</strong>&nbsp;This account is still using the default seed password. Please change it below right away.
</div>
<?php endif; ?>

<div class="admin-panel">
    <div class="admin-panel-head"><h3>Site Settings</h3></div>
    <form method="post">
        <?= Csrf::field() ?><input type="hidden" name="action" value="update_site">
        <div class="form-row">
            <div class="form-group"><label class="form-label">Site Name</label><input type="text" name="site_name" class="form-input" value="<?= e(setting('site_name')) ?>"></div>
            <div class="form-group"><label class="form-label">Tagline</label><input type="text" name="site_tagline" class="form-input" value="<?= e(setting('site_tagline')) ?>"></div>
        </div>
        <div class="form-group"><label class="form-label">Description (used in SEO meta tags)</label><textarea name="site_description" class="form-textarea"><?= e(setting('site_description')) ?></textarea></div>
        <div class="form-row">
            <div class="form-group"><label class="form-label">Contact Email</label><input type="email" name="contact_email" class="form-input" value="<?= e(setting('contact_email')) ?>"></div>
            <div class="form-group"><label class="form-label">Daily Upload Limit (per user)</label><input type="number" name="daily_upload_limit" class="form-input" value="<?= e(setting('daily_upload_limit')) ?>"></div>
        </div>
        <div class="form-group"><label class="form-label">Max File Size (MB)</label><input type="number" name="max_upload_mb" class="form-input" value="<?= e(setting('max_upload_mb')) ?>" style="max-width:200px;">
            <div class="form-hint">Also update <code>upload_max_filesize</code> / <code>post_max_size</code> in your server's php.ini to match.</div>
        </div>
        <div class="form-group">
            <label class="form-check"><input type="checkbox" name="require_book_approval" <?= setting('require_book_approval') === '1' ? 'checked' : '' ?>> Require admin approval before new uploads go live</label>
        </div>
        <div class="form-group">
            <label class="form-check"><input type="checkbox" name="allow_guest_reading" <?= setting('allow_guest_reading') === '1' ? 'checked' : '' ?>> Allow guests to read books online without an account</label>
        </div>
        <div class="form-group">
            <label class="form-check"><input type="checkbox" name="maintenance_mode" <?= setting('maintenance_mode') === '1' ? 'checked' : '' ?>> Maintenance mode (only admins can browse the site)</label>
        </div>
        <button type="submit" class="btn btn-primary">Save Settings</button>
    </form>
</div>

<div class="admin-panel">
    <div class="admin-panel-head"><h3>Change Your Password</h3></div>
    <form method="post" style="max-width:420px;">
        <?= Csrf::field() ?><input type="hidden" name="action" value="change_password">
        <div class="form-group"><label class="form-label">Current Password</label><input type="password" name="current_password" class="form-input" required></div>
        <div class="form-group"><label class="form-label">New Password</label><input type="password" name="new_password" class="form-input" required minlength="8"></div>
        <button type="submit" class="btn btn-primary">Change Password</button>
    </form>
</div>

<div class="admin-panel">
    <div class="admin-panel-head"><h3>Email (OTP) Status</h3></div>
    <p class="text-muted" style="font-size:.85rem;">
        Registration verification codes and password reset codes are sent via SMTP. Status:
        <?php if (SMTP_PASSWORD !== 'REPLACE_WITH_YOUR_16_CHARACTER_GMAIL_APP_PASSWORD'): ?><span class="status-pill status-approved">Configured</span>
        <?php else: ?><span class="status-pill status-pending">Not configured</span> — add a real Gmail App Password as <code>SMTP_PASSWORD</code> in <code>config/config.php</code> (generate one at <a href="https://myaccount.google.com/apppasswords" target="_blank" rel="noopener">myaccount.google.com/apppasswords</a>).<?php endif; ?>
    </p>
    <p class="text-muted" style="font-size:.8rem;">Sending from: <code><?= e(SMTP_USERNAME) ?></code> via <code><?= e(SMTP_HOST) ?>:<?= (int) SMTP_PORT ?></code></p>
</div>

<?php include __DIR__ . '/includes/layout-end.php'; ?>
