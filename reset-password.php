<?php
require_once __DIR__ . '/config/config.php';
if (Auth::check()) redirect('index.php');

$email = $_SESSION['pending_reset_email'] ?? $_GET['email'] ?? '';
if (!$email) redirect('forgot-password.php');

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::verifyOrDie();
    $action = $_POST['action'] ?? 'reset';

    if ($action === 'resend') {
        $result = Otp::generateAndSend($email, 'reset_password');
        flash_set($result['success'] ? 'success' : 'error', $result['success'] ? 'A new code has been sent.' : $result['error']);
        redirect('reset-password.php');
    }

    if ($action === 'reset') {
        $code = trim($_POST['otp'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm = $_POST['password_confirm'] ?? '';

        if ($password !== $confirm) {
            $errors[] = 'Passwords do not match.';
        } else {
            $verify = Otp::verify($email, $code, 'reset_password');
            if (!$verify['success']) {
                $errors[] = $verify['error'];
            } else {
                $user = Database::fetchOne('SELECT id FROM users WHERE email = ?', [$email]);
                if (!$user) {
                    $errors[] = 'Something went wrong. Please start over.';
                } else {
                    $set = Auth::setPassword((int) $user['id'], $password);
                    if (!$set['success']) {
                        $errors[] = $set['error'];
                    } else {
                        unset($_SESSION['pending_reset_email']);
                        flash_set('success', 'Password updated — please sign in with your new password.');
                        redirect('login.php');
                    }
                }
            }
        }
    }
}

$cooldown = Otp::cooldownRemaining($email, 'reset_password');
$pageTitle = 'Reset Password - ' . SITE_NAME;
include __DIR__ . '/includes/head.php';
?>
<header class="site-header"><div class="container">
    <a href="<?= url('index.php') ?>" class="brand"><img src="<?= asset('images/logo.png') ?>" alt=""><span class="brand-text"><?= e(SITE_NAME) ?></span></a>
</div></header>

<div class="auth-shell">
    <div class="khatam-watermark" aria-hidden="true"></div>
    <div class="auth-card">
        <span class="brand" style="justify-content:center;"><?= icon('shield') ?> Reset Password</span>
        <p>Enter the code sent to <b><?= e($email) ?></b> and choose a new password.</p>

        <?php foreach ($errors as $err): ?><div class="alert alert-error" style="text-align:left;"><?= e($err) ?></div><?php endforeach; ?>

        <form method="post" style="text-align:left;">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="reset">
            <div class="form-group">
                <label class="form-label" for="rp-otp">Reset Code</label>
                <input type="text" id="rp-otp" name="otp" class="form-input" required inputmode="numeric" maxlength="6" autofocus style="letter-spacing:.5em;font-size:1.3rem;text-align:center;font-weight:700;">
            </div>
            <div class="form-group">
                <label class="form-label" for="rp-password">New Password</label>
                <input type="password" id="rp-password" name="password" class="form-input" required minlength="8" autocomplete="new-password">
            </div>
            <div class="form-group">
                <label class="form-label" for="rp-password-confirm">Confirm New Password</label>
                <input type="password" id="rp-password-confirm" name="password_confirm" class="form-input" required minlength="8" autocomplete="new-password">
            </div>
            <button type="submit" class="btn btn-primary btn-block">Reset Password</button>
        </form>

        <form method="post" style="margin-top:1rem;">
            <?= Csrf::field() ?><input type="hidden" name="action" value="resend">
            <button type="submit" class="btn btn-outline btn-block" id="resend-btn" <?= $cooldown > 0 ? 'disabled' : '' ?>>
                <span id="resend-label"><?= $cooldown > 0 ? "Resend code in {$cooldown}s" : 'Resend Code' ?></span>
            </button>
        </form>
    </div>
</div>

<script>
(function () {
    var remaining = <?= (int) $cooldown ?>;
    var btn = document.getElementById('resend-btn');
    var label = document.getElementById('resend-label');
    if (remaining <= 0) return;
    var timer = setInterval(function () {
        remaining--;
        if (remaining <= 0) { clearInterval(timer); btn.disabled = false; label.textContent = 'Resend Code'; }
        else { label.textContent = 'Resend code in ' + remaining + 's'; }
    }, 1000);
})();
</script>

<?php include __DIR__ . '/includes/footer-minimal.php'; ?>
