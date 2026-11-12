<?php
require_once __DIR__ . '/../config/config.php';

if (Auth::check() && Auth::isAdmin()) {
    redirect('admin/dashboard.php');
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::verifyOrDie();
    $result = Auth::attemptLogin(trim($_POST['identifier'] ?? ''), $_POST['password'] ?? '', false);
    if ($result['success'] && in_array($result['user']['role'], ['admin', 'subadmin'], true)) {
        Admin::log('login', 'Admin signed in');
        redirect('admin/dashboard.php');
    }
    $error = $result['success'] ? 'That account does not have admin access.' : $result['error'];
}

$pageTitle = 'Admin Sign In - ' . SITE_NAME;
$forceLtr = true; // the admin area is English-only (see I18n.php scope note) — never mirror it based on a public visitor's language cookie
include __DIR__ . '/../includes/head.php';
?>
<header class="site-header"><div class="container">
    <a href="<?= url('index.php') ?>" class="brand"><img src="<?= asset('images/logo.png') ?>" alt=""><span class="brand-text"><?= e(SITE_NAME) ?> Admin</span></a>
</div></header>

<div class="auth-shell">
    <div class="khatam-watermark" aria-hidden="true"></div>
    <div class="auth-card">
        <span class="brand" style="justify-content:center;"><?= icon('shield') ?> Admin Sign In</span>
        <p>Restricted to site administrators.</p>

        <?php if ($error): ?><div class="alert alert-error" style="text-align:left;"><?= e($error) ?></div><?php endif; ?>

        <form method="post" style="text-align:left;">
            <?= Csrf::field() ?>
            <div class="form-group">
                <label class="form-label">Username or Email</label>
                <input type="text" name="identifier" class="form-input" required autofocus>
            </div>
            <div class="form-group">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-input" required>
            </div>
            <button type="submit" class="btn btn-primary btn-block">Sign In</button>
        </form>
        <p class="auth-fineprint">Default seed account is <b>admin</b> / <b>admin123</b> — change this immediately from Settings after first login.</p>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer-minimal.php'; ?>
