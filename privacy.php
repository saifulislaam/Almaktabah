<?php
require_once __DIR__ . '/config/config.php';
$pageTitle = 'Privacy Policy - ' . SITE_NAME;
include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
?>
<nav class="breadcrumbs"><a href="<?= url('index.php') ?>">Home</a><span class="crumb-sep">✦</span><span>Privacy Policy</span></nav>
<div class="form-card" style="max-width:760px;">
    <h1 class="page-title">Privacy Policy</h1>
    <p class="text-muted">Replace this placeholder with your own reviewed privacy policy before going live — this page is a starting structure, not legal advice.</p>
    <h3>What we collect</h3>
    <p>When you create an account, we collect your username, email address, and a securely hashed password (we never store your password in plain text). We also record your reading history, downloads, wishlist, and reviews to power recommendations and your profile.</p>
    <h3>How we use it</h3>
    <p>Your email is used to verify your account via a one-time code and to send password reset codes — we don't use it for marketing without your consent. Your data is used to run your account, personalize recommendations, and maintain the library. We do not sell personal data to third parties.</p>
    <h3>Your choices</h3>
    <p>You can edit your profile, change your password, or remove uploaded books at any time from your account. Contact us to request full account deletion.</p>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
