<?php
require_once __DIR__ . '/config/config.php';
if (!http_response_code()) { http_response_code(404); }

$pageTitle = 'Page Not Found - ' . SITE_NAME;
include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
?>
<div class="empty-state" style="padding:100px 20px;">
    <?= icon('khatam', 'icon') ?>
    <h3 class="page-title">Page Not Found</h3>
    <p>The page you're looking for doesn't exist or may have been removed.</p>
    <a href="<?= url('index.php') ?>" class="btn btn-primary" style="margin-top:1rem;">Back to Home</a>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
