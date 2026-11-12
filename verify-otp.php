<?php
require_once __DIR__ . '/config/config.php';

if (Auth::check()) {
    redirect('index.php');
}

$email = $_SESSION['pending_verification_email'] ?? $_GET['email'] ?? '';
if (!$email) {
    redirect('register.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::verifyOrDie();
    $action = $_POST['action'] ?? 'verify';

    if ($action === 'resend') {
        $result = Otp::generateAndSend($email, 'verify_email');
        flash_set($result['success'] ? 'success' : 'error', $result['success'] ? 'A new code has been sent to ' . $email . '.' : $result['error']);
        redirect('verify-otp.php');
    }

    if ($action === 'verify') {
        $code = trim($_POST['otp'] ?? '');
        $result = Otp::verify($email, $code, 'verify_email');
        if ($result['success']) {
            Auth::markEmailVerified($email);
            $user = Database::fetchOne('SELECT * FROM users WHERE email = ?', [$email]);
            unset($_SESSION['pending_verification_email']);
            Auth::startSession($user);
            Database::execute('UPDATE users SET last_login = NOW() WHERE id = ?', [$user['id']]);
            flash_set('success', 'Email verified! Welcome to ' . SITE_NAME . '.');
            redirect('index.php');
        }
        $errors[] = $result['error'];
    }
}

$cooldown = Otp::cooldownRemaining($email, 'verify_email');
$pageTitle = 'Verify Your Email - ' . SITE_NAME;
include __DIR__ . '/includes/head.php';
?>

<header class="site-header"><div class="container">
    <a href="<?= url('index.php') ?>" class="brand"><img src="<?= asset('images/logo.png') ?>" alt=""><span class="brand-text"><?= e(SITE_NAME) ?></span></a>
</div></header>

<div class="auth-shell">
    <div class="khatam-watermark" aria-hidden="true"></div>
    <div class="auth-card">
        <span class="brand" style="justify-content:center;"><?= icon('check') ?> Verify Your Email</span>
        <p>We sent a 6-digit code to <b><?= e($email) ?></b>. Enter it below to activate your account.</p>

        <?php foreach ($errors as $err): ?><div class="alert alert-error" style="text-align:left;"><?= e($err) ?></div><?php endforeach; ?>

        <form method="post" style="text-align:left;">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="verify">
            <div class="form-group">
                <label class="form-label" for="otp-input">Verification Code</label>
                <input type="text" id="otp-input" name="otp" class="form-input" required inputmode="numeric" pattern="[0-9]*" maxlength="6" autocomplete="one-time-code" autofocus style="letter-spacing:.5em;font-size:1.3rem;text-align:center;font-weight:700;">
            </div>
            <button type="submit" class="btn btn-primary btn-block">Verify & Continue</button>
        </form>

        <form method="post" style="margin-top:1rem;">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="resend">
            <button type="submit" class="btn btn-outline btn-block" id="resend-btn" <?= $cooldown > 0 ? 'disabled' : '' ?>>
                <span id="resend-label"><?= $cooldown > 0 ? "Resend code in {$cooldown}s" : 'Resend Code' ?></span>
            </button>
        </form>

        <p class="auth-fineprint">Wrong email? <a href="<?= url('register.php') ?>" style="text-decoration:underline;">Start over</a></p>
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
        if (remaining <= 0) {
            clearInterval(timer);
            btn.disabled = false;
            label.textContent = 'Resend Code';
        } else {
            label.textContent = 'Resend code in ' + remaining + 's';
        }
    }, 1000);
})();
</script>

<?php include __DIR__ . '/includes/footer-minimal.php'; ?>
