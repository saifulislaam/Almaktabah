<?php
/**
 * includes/scripts.php — shared JS bootstrap + flash-message toasts.
 * Included at the bottom of footer.php and footer-minimal.php.
 */
$flashes = flash_get();
?>
<div style="display:none">
    <?php foreach ($flashes as $f): ?>
        <span data-flash="<?= e($f['message']) ?>"></span>
    <?php endforeach; ?>
</div>
<script>
    window.ALMAKTABAH = {
        baseUrl: <?= json_encode(rtrim(SITE_URL, '/') . '/') ?>,
        coverUrl: <?= json_encode(UPLOAD_URL_COVERS) ?>,
        loggedIn: <?= $currentUser ? 'true' : 'false' ?>,
        csrf: <?= json_encode(Csrf::token()) ?>
    };
</script>
<script src="<?= asset('js/main.js') ?>" defer></script>
