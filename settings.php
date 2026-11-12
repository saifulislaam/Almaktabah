<?php
require_once __DIR__ . '/config/config.php';
Auth::requireLogin('login.php');

$section = $_GET['section'] ?? 'profile';
$user = User::find(Auth::id());

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::verifyOrDie();
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $result = User::updateProfile(Auth::id(), $_POST['display_name'] ?? '', trim($_POST['bio'] ?? '') ?: null);
        if (!empty($_FILES['avatar']) && $_FILES['avatar']['error'] !== UPLOAD_ERR_NO_FILE) {
            $up = Upload::handleAvatar($_FILES['avatar']);
            if ($up['success']) {
                User::updateAvatar(Auth::id(), $up['filename']);
            } elseif ($up['error']) {
                $result['error'] = $up['error'];
                $result['success'] = false;
            }
        }
        flash_set($result['success'] ? 'success' : 'error', $result['success'] ? 'Profile updated.' : $result['error']);
        redirect('settings.php?section=profile');
    }

    if ($action === 'update_theme') {
        User::updateTheme(Auth::id(), $_POST['theme'] ?? 'auto');
        flash_set('success', 'Theme preference saved.');
        redirect('settings.php?section=preferences');
    }

    if ($action === 'toggle_category') {
        User::toggleFavoriteCategory(Auth::id(), (int) ($_POST['category_id'] ?? 0));
        redirect('settings.php?section=preferences');
    }

    if ($action === 'change_password') {
        $user = User::find(Auth::id());
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['new_password_confirm'] ?? '';

        if (!password_verify($current, $user['password'])) {
            flash_set('error', 'Your current password is incorrect.');
        } elseif ($new !== $confirm) {
            flash_set('error', 'New passwords do not match.');
        } else {
            $result = Auth::setPassword(Auth::id(), $new);
            flash_set($result['success'] ? 'success' : 'error', $result['success'] ? 'Password updated.' : $result['error']);
        }
        redirect('settings.php?section=account');
    }
}

$pageTitle = 'Settings - ' . SITE_NAME;
include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';

$categories = Category::all();
$favIds = User::favoriteCategoryIds(Auth::id());
?>

<nav class="breadcrumbs"><a href="<?= url('index.php') ?>">Home</a><span class="crumb-sep">✦</span><span>Settings</span></nav>
<h1 class="page-title" style="margin-bottom:1.5rem;">Settings</h1>

<div class="settings-grid">
    <nav class="settings-nav">
        <a href="?section=profile" class="<?= $section === 'profile' ? 'active' : '' ?>"><?= icon('user') ?> Profile</a>
        <a href="?section=preferences" class="<?= $section === 'preferences' ? 'active' : '' ?>"><?= icon('settings') ?> Preferences</a>
        <a href="?section=account" class="<?= $section === 'account' ? 'active' : '' ?>"><?= icon('shield') ?> Account</a>
    </nav>

    <div>
        <?php if ($section === 'profile'): ?>
            <div class="form-card">
                <h2 style="font-size:1.1rem;">Profile</h2>
                <form method="post" enctype="multipart/form-data">
                    <?= Csrf::field() ?>
                    <input type="hidden" name="action" value="update_profile">
                    <div class="flex items-center gap-2" style="margin-bottom:1.5rem;">
                        <?= avatar_html(array_merge($user, ['profile_image' => $user['profile_image']]), 72) ?>
                        <div>
                            <label class="btn btn-sm btn-outline" style="cursor:pointer;">
                                Change photo <input type="file" name="avatar" accept="image/jpeg,image/png,image/webp" style="display:none;" onchange="this.closest('form').submit()">
                            </label>
                            <div class="form-hint">JPG, PNG, or WEBP — up to <?= format_bytes(MAX_AVATAR_SIZE) ?>.</div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Display Name</label>
                        <input type="text" name="display_name" class="form-input" value="<?= e($user['display_name'] ?: $user['username']) ?>" maxlength="100">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Username</label>
                        <input type="text" class="form-input" value="<?= e($user['username']) ?>" disabled>
                        <div class="form-hint">Usernames can't be changed.</div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Bio</label>
                        <textarea name="bio" class="form-textarea" maxlength="500"><?= e($user['bio'] ?? '') ?></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </form>
            </div>

        <?php elseif ($section === 'preferences'): ?>
            <div class="form-card" style="margin-bottom:20px;">
                <h2 style="font-size:1.1rem;">Appearance</h2>
                <p class="text-muted" style="font-size:.85rem;">Choose light, dark, or match your device automatically.</p>
                <form method="post">
                    <?= Csrf::field() ?>
                    <input type="hidden" name="action" value="update_theme">
                    <input type="hidden" name="theme" id="theme-hidden-input" value="<?= e($user['theme_preference']) ?>">
                    <div class="theme-options">
                        <div class="theme-option <?= $user['theme_preference'] === 'light' ? 'selected' : '' ?>" onclick="document.getElementById('theme-hidden-input').value='light';this.closest('form').submit();"><?= icon('sun') ?><div>Light</div></div>
                        <div class="theme-option <?= $user['theme_preference'] === 'dark' ? 'selected' : '' ?>" onclick="document.getElementById('theme-hidden-input').value='dark';this.closest('form').submit();"><?= icon('moon') ?><div>Dark</div></div>
                        <div class="theme-option <?= $user['theme_preference'] === 'auto' ? 'selected' : '' ?>" onclick="document.getElementById('theme-hidden-input').value='auto';this.closest('form').submit();"><?= icon('settings') ?><div>Auto</div></div>
                    </div>
                </form>
            </div>
            <div class="form-card">
                <h2 style="font-size:1.1rem;">Favorite Categories</h2>
                <p class="text-muted" style="font-size:.85rem;">Follow categories to improve your recommendations and get notified about new books.</p>
                <div class="flex" style="flex-wrap:wrap;gap:.6rem;margin-top:1rem;">
                    <?php foreach ($categories as $cat): $isFav = in_array((int) $cat['id'], $favIds, true); ?>
                        <form method="post">
                            <?= Csrf::field() ?>
                            <input type="hidden" name="action" value="toggle_category">
                            <input type="hidden" name="category_id" value="<?= $cat['id'] ?>">
                            <button type="submit" class="btn btn-sm <?= $isFav ? 'btn-primary' : 'btn-outline' ?>"><?= e($cat['name']) ?></button>
                        </form>
                    <?php endforeach; ?>
                </div>
            </div>

        <?php else: ?>
            <div class="form-card">
                <h2 style="font-size:1.1rem;">Account</h2>
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="text" class="form-input" value="<?= e($user['email']) ?>" disabled>
                    <div class="form-hint">Contact support to change the email on your account.</div>
                </div>
            </div>
            <div class="form-card" style="margin-top:20px;">
                <h2 style="font-size:1.1rem;">Change Password</h2>
                <form method="post">
                    <?= Csrf::field() ?>
                    <input type="hidden" name="action" value="change_password">
                    <div class="form-group">
                        <label class="form-label">Current Password</label>
                        <input type="password" name="current_password" class="form-input" required autocomplete="current-password">
                    </div>
                    <div class="form-group">
                        <label class="form-label">New Password</label>
                        <input type="password" name="new_password" class="form-input" required minlength="8" autocomplete="new-password">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Confirm New Password</label>
                        <input type="password" name="new_password_confirm" class="form-input" required minlength="8" autocomplete="new-password">
                    </div>
                    <button type="submit" class="btn btn-primary">Update Password</button>
                </form>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
