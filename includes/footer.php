</main>
</div><!-- /.app-shell -->

<footer class="site-footer">
    <div class="khatam-watermark" aria-hidden="true"></div>
    <div class="container footer-grid">
        <div class="footer-brand">
            <a href="<?= url('index.php') ?>" class="brand" style="margin-bottom:.9rem;">
                <img src="<?= asset('images/logo.png') ?>" alt="<?= e(SITE_NAME) ?>" style="height:30px;">
                <span><?= e(SITE_NAME) ?></span>
            </a>
            <p><?= e(setting('site_description')) ?></p>
            <div class="footer-social">
                <a href="#" aria-label="Facebook"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M14 9h3V6h-3c-1.7 0-3 1.3-3 3v2H8v3h3v7h3v-7h3l1-3h-4V9c0-.6.4-1 1-1Z"/></svg></a>
                <a href="#" aria-label="Twitter/X"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m4 4 7.5 8.5L4.5 20h2l6-6.5L17 20h3l-8-9L19 4h-2l-5.5 6L7 4Z"/></svg></a>
                <a href="#" aria-label="YouTube"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="6" width="18" height="12" rx="3"/><path d="m10 10 5 2-5 2Z"/></svg></a>
            </div>
        </div>
        <div>
            <h4><?= e(t('footer.explore')) ?></h4>
            <a href="<?= url('categories.php') ?>"><?= e(t('footer.categories')) ?></a>
            <a href="<?= url('authors.php') ?>"><?= e(t('footer.authors')) ?></a>
            <a href="<?= url('search.php?sort=newest') ?>"><?= e(t('footer.new_arrivals')) ?></a>
            <a href="<?= url('search.php?sort=popular') ?>"><?= e(t('footer.trending')) ?></a>
        </div>
        <div>
            <h4><?= e(t('footer.account')) ?></h4>
            <a href="<?= url('login.php') ?>"><?= e(t('footer.sign_in')) ?></a>
            <a href="<?= url('upload.php') ?>"><?= e(t('footer.upload')) ?></a>
            <a href="<?= url('settings.php') ?>"><?= e(t('footer.settings')) ?></a>
        </div>
        <div>
            <h4><?= e(t('footer.site')) ?></h4>
            <a href="<?= url('about.php') ?>"><?= e(t('footer.about')) ?></a>
            <a href="<?= url('contact.php') ?>"><?= e(t('footer.contact')) ?></a>
            <a href="<?= url('privacy.php') ?>"><?= e(t('footer.privacy')) ?></a>
            <a href="<?= url('terms.php') ?>"><?= e(t('footer.terms')) ?></a>
        </div>
    </div>
    <div class="container footer-bottom">
        <span>&copy; <?= date('Y') ?> <?= e(SITE_NAME) ?>. <?= e(t('footer.rights')) ?></span>
        <span><?= e(SITE_TAGLINE) ?></span>
    </div>
</footer>

<?php include __DIR__ . '/scripts.php'; ?>
</body>
</html>
