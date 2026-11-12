<?php
require_once __DIR__ . '/config/config.php';

if (Auth::check()) {
    redirect('index.php');
}

$errors = [];
$old = ['username' => '', 'email' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::verifyOrDie();
    $old['username'] = trim($_POST['username'] ?? '');
    $old['email'] = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['password_confirm'] ?? '';

    if ($password !== $confirm) {
        $errors[] = 'Passwords do not match.';
    } elseif (!empty($_POST['website'])) {
        // Honeypot field (hidden via CSS) — if it's filled, silently treat as a bot.
        $errors[] = 'Something went wrong. Please try again.';
    } else {
        $result = Auth::register($old['username'], $old['email'], $password);
        if (!$result['success']) {
            $errors[] = $result['error'];
        } else {
            $otpResult = Otp::generateAndSend($result['email'], 'verify_email');
            $_SESSION['pending_verification_email'] = $result['email'];
            if (!$otpResult['success']) {
                flash_set('error', 'Your account was created, but we couldn\'t send the verification email: ' . $otpResult['error'] . ' You can request a new code below.');
            } else {
                flash_set('success', 'Account created! Check ' . $result['email'] . ' for a 6-digit verification code.');
            }
            redirect('verify-otp.php');
        }
    }
}

$pageTitle = 'Create an Account - ' . SITE_NAME;
include __DIR__ . '/includes/head.php';
?>

<header class="site-header"><div class="container">
    <a href="<?= url('index.php') ?>" class="brand"><img src="<?= asset('images/logo.png') ?>" alt=""><span class="brand-text"><?= e(SITE_NAME) ?></span></a>
</div></header>

<div class="auth-shell">
    <div class="khatam-watermark" aria-hidden="true"></div>
    <div class="auth-card" style="max-width:440px;">
        <a href="<?= url('index.php') ?>" class="brand" style="justify-content:center;"><?= e(SITE_NAME) ?></a>
        <p>Create a free account to upload books, download, save a wishlist, and get personalized recommendations.</p>

        <?php foreach ($errors as $err): ?><div class="alert alert-error" style="text-align:left;"><?= e($err) ?></div><?php endforeach; ?>

        <form method="post" style="text-align:left;" novalidate>
            <?= Csrf::field() ?>
            <input type="text" name="website" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px;width:1px;height:1px;opacity:0;" aria-hidden="true">

            <div class="form-group">
                <label class="form-label" for="reg-username">Username</label>
                <input type="text" id="reg-username" name="username" class="form-input" required minlength="3" maxlength="50" pattern="[a-zA-Z0-9_.]+" value="<?= e($old['username']) ?>" autocomplete="username">
                <div class="form-hint">3-50 characters — letters, numbers, . and _ only.</div>
            </div>
            <div class="form-group">
                <label class="form-label" for="reg-email">Email</label>
                <input type="email" id="reg-email" name="email" class="form-input" required value="<?= e($old['email']) ?>" autocomplete="email">
            </div>
            <div class="form-group">
                <label class="form-label" for="reg-password">Password</label>
                <input type="password" id="reg-password" name="password" class="form-input" required minlength="8" autocomplete="new-password">
                <div class="form-hint">At least 8 characters.</div>
            </div>
            <div class="form-group">
                <label class="form-label" for="reg-password-confirm">Confirm Password</label>
                <input type="password" id="reg-password-confirm" name="password_confirm" class="form-input" required minlength="8" autocomplete="new-password">
            </div>
            <button type="submit" class="btn btn-primary btn-block">Create Account</button>
        </form>

        <p class="auth-fineprint">
            Already have an account? <a href="<?= url('login.php') ?>" style="font-weight:700;text-decoration:underline;">Sign in</a><br>
            By continuing you agree to <?= e(SITE_NAME) ?>'s <a href="<?= url('terms.php') ?>" style="text-decoration:underline;">Terms</a>
            and <a href="<?= url('privacy.php') ?>" style="text-decoration:underline;">Privacy Policy</a>.
        </p>
    </div>
</div>

<script>
document.querySelector('form').addEventListener('submit', function (e) {
    var pw = document.getElementById('reg-password').value;
    var confirm = document.getElementById('reg-password-confirm').value;
    if (pw !== confirm) {
        e.preventDefault();
        window.almaktabahToast ? window.almaktabahToast('Passwords do not match.') : alert('Passwords do not match.');
    }
});
</script>

<?php include __DIR__ . '/includes/footer-minimal.php'; ?>
