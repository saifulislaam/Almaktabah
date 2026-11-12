<?php
require_once __DIR__ . '/config/config.php';
if (Auth::check()) redirect('index.php');

$errors = [];
$submitted = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::verifyOrDie();
    $email = trim($_POST['email'] ?? '');
    $user = $email ? Database::fetchOne('SELECT id FROM users WHERE email = ? AND role = "user"', [strtolower($email)]) : null;

    // Always show the same success state whether or not the email exists,
    // so this form can't be used to discover which addresses are registered.
    if ($user) {
        Otp::generateAndSend(strtolower($email), 'reset_password');
        $_SESSION['pending_reset_email'] = strtolower($email);
    }
    $submitted = true;
}

$pageTitle = 'Forgot Password - ' . SITE_NAME;
include __DIR__ . '/includes/head.php';
?>
<header class="site-header"><div class="container">
    <a href="<?= url('index.php') ?>" class="brand"><img src="<?= asset('images/logo.png') ?>" alt=""><span class="brand-text"><?= e(SITE_NAME) ?></span></a>
</div></header>

<div class="auth-shell">
    <div class="khatam-watermark" aria-hidden="true"></div>
    <div class="auth-card">
        <span class="brand" style="justify-content:center;"><?= icon('shield') ?> Forgot Password</span>

        <?php if ($submitted): ?>
            <p>If an account exists for that email, we've sent a 6-digit code to reset the password.</p>
            <a href="<?= url('reset-password.php') ?>" class="btn btn-primary btn-block">Enter Reset Code</a>
        <?php else: ?>
            <p>Enter your account email and we'll send you a code to reset your password.</p>
            <?php foreach ($errors as $err): ?><div class="alert alert-error" style="text-align:left;"><?= e($err) ?></div><?php endforeach; ?>
            <form method="post" style="text-align:left;">
                <?= Csrf::field() ?>
                <div class="form-group">
                    <label class="form-label" for="fp-email">Email</label>
                    <input type="email" id="fp-email" name="email" class="form-input" required autofocus>
                </div>
                <button type="submit" class="btn btn-primary btn-block">Send Reset Code</button>
            </form>
        <?php endif; ?>

        <p class="auth-fineprint"><a href="<?= url('login.php') ?>" style="text-decoration:underline;">Back to sign in</a></p>
    </div>
</div>

<?php include __DIR__ . '/includes/footer-minimal.php'; ?>
