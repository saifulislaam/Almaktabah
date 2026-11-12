<?php
require_once __DIR__ . '/config/config.php';
$pageTitle = 'Terms of Service - ' . SITE_NAME;
include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
?>
<nav class="breadcrumbs"><a href="<?= url('index.php') ?>">Home</a><span class="crumb-sep">✦</span><span>Terms of Service</span></nav>
<div class="form-card" style="max-width:760px;">
    <h1 class="page-title">Terms of Service</h1>
    <p class="text-muted">Replace this placeholder with your own reviewed terms before going live — this page is a starting structure, not legal advice.</p>
    <h3>Uploading content</h3>
    <p>Only upload books you have the right to share. Uploads are reviewed before publishing, and we remove content on valid copyright or policy grounds.</p>
    <h3>Acceptable use</h3>
    <p>Don't upload content that is unrelated to the library's Islamic-books focus, infringing, or harmful. Accounts that violate this may be suspended or banned.</p>
    <h3>Availability</h3>
    <p>The library is provided as-is; we aim for reliable access but don't guarantee uninterrupted availability.</p>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
