<?php
/**
 * admin/includes/layout-end.php — closes the shell opened by
 * layout-start.php. Set $chartsData (array) before including on pages
 * that render Chart.js graphs; leave unset otherwise.
 */
?>
        </div>
    </div>
</div>

<script>
    window.ALMAKTABAH = {
        baseUrl: <?= json_encode(rtrim(SITE_URL, '/') . '/') ?>,
        coverUrl: <?= json_encode(UPLOAD_URL_COVERS) ?>,
        loggedIn: true,
        csrf: <?= json_encode(Csrf::token()) ?>
    };
    <?php if (!empty($chartsData)): ?>
    window.ADMIN_CHARTS = <?= json_encode($chartsData) ?>;
    <?php endif; ?>
</script>
<script src="<?= asset('js/main.js') ?>" defer></script>
<script src="<?= asset('js/admin.js') ?>" defer></script>
</body>
</html>
