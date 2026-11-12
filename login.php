<?php
require_once __DIR__ . '/config/config.php';

if (Auth::check()) {
    redirect('index.php');
}

$errors = [];
$oldEmail = '';
$returnTo = $_GET['return'] ?? ($_POST['return'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::verifyOrDie();
    $oldEmail = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = !empty($_POST['remember']);

    if ($oldEmail === '' || $password === '') {
        $errors[] = 'Please enter both your email and password.';
    } else {
        $result = Auth::attemptLogin($oldEmail, $password, $remember);
        if ($result['success']) {
            flash_set('success', 'Welcome back, ' . ($result['user']['display_name'] ?: $result['user']['username']) . '!');
            if ($returnTo && str_starts_with($returnTo, '/')) {
                redirect(ltrim($returnTo, '/'));
            }
            redirect('index.php');
        }
        if (!empty($result['needs_verification'])) {
            $_SESSION['pending_verification_email'] = $result['email'];
            redirect('verify-otp.php');
        }
        $errors[] = $result['error'];
    }
}

$pageTitle = 'Sign in - ' . SITE_NAME;
include __DIR__ . '/includes/head.php';
?>

<header class="site-header"><div class="container">
    <a href="<?= url('index.php') ?>" class="brand"><img src="<?= asset('images/logo.png') ?>" alt=""><span class="brand-text"><?= e(SITE_NAME) ?></span></a>
</div></header>

<div class="auth-shell">
    <div class="khatam-watermark" aria-hidden="true"></div>
    <div class="auth-card">
        <a href="<?= url('index.php') ?>" class="brand" style="justify-content:center;"><?= e(SITE_NAME) ?></a>
        <p>Sign in to upload books, download, save a wishlist, and get recommendations built around what you read.</p>

        <?php foreach ($errors as $err): ?><div class="alert alert-error" style="text-align:left;"><?= e($err) ?></div><?php endforeach; ?>

        <form method="post" style="text-align:left;">
            <?= Csrf::field() ?>
            <input type="hidden" name="return" value="<?= e($returnTo) ?>">
            <div class="form-group">
                <label class="form-label" for="login-email">Email</label>
                <input type="email" id="login-email" name="email" class="form-input" required autofocus value="<?= e($oldEmail) ?>" autocomplete="email">
            </div>
            <div class="form-group">
                <div class="flex items-center" style="justify-content:space-between;">
                    <label class="form-label" for="login-password" style="margin-bottom:0;">Password</label>
                    <a href="<?= url('forgot-password.php') ?>" style="font-size:.78rem;font-weight:600;">Forgot password?</a>
                </div>
                <input type="password" id="login-password" name="password" class="form-input" required autocomplete="current-password">
            </div>
            <label class="form-check" style="margin-bottom:1.25rem;">
                <input type="checkbox" name="remember"> Keep me signed in
            </label>
            <button type="submit" class="btn btn-primary btn-block">Sign In</button>
        </form>

        <p class="auth-fineprint">
            New to <?= e(SITE_NAME) ?>? <a href="<?= url('register.php') ?>" style="font-weight:700;text-decoration:underline;">Create an account</a>
        </p>
    </div>
</div>

<?php include __DIR__ . '/includes/footer-minimal.php'; ?>
