<?php
require_once __DIR__ . '/config/config.php';
$pageTitle = 'Contact - ' . SITE_NAME;
include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
?>
<nav class="breadcrumbs"><a href="<?= url('index.php') ?>">Home</a><span class="crumb-sep">✦</span><span>Contact</span></nav>
<div class="form-card" style="max-width:600px;">
    <h1 class="page-title">Contact Us</h1>
    <p>Questions, feedback, or a copyright concern about a book on the site? Reach out and we'll get back to you.</p>
    <p style="margin-top:1.5rem;"><b>Email:</b> <a href="mailto:<?= e(setting('contact_email')) ?>"><?= e(setting('contact_email')) ?></a></p>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
