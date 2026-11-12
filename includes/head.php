<?php
/**
 * includes/head.php — shared <head> + opening <body>.
 * Expects (all optional) before include: $pageTitle, $pageDescription,
 * $canonicalPath, $bodyClass, $schemaJsonLd (raw JSON-LD string), $ogImage.
 */
$pageTitle       = $pageTitle ?? SITE_NAME . ' - ' . SITE_TAGLINE;
$pageDescription = $pageDescription ?? setting('site_description', 'A free digital library of Islamic books.');
$canonicalPath   = $canonicalPath ?? ($_SERVER['REQUEST_URI'] ?? '');
$bodyClass       = $bodyClass ?? '';
$ogImage         = $ogImage ?? asset('images/logo.png');
$currentUser     = Auth::check() ? ['id' => Auth::id(), 'username' => $_SESSION['username'] ?? '', 'display_name' => $_SESSION['display_name'] ?? '', 'profile_image' => $_SESSION['profile_image'] ?? null] : null;
$forceLtr = $forceLtr ?? false;
$htmlLang = $forceLtr ? 'en' : I18n::current();
$htmlDir  = $forceLtr ? 'ltr' : I18n::dir();
?><!doctype html>
<html lang="<?= e($htmlLang) ?>" dir="<?= e($htmlDir) ?>" data-theme="auto">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?></title>
<meta name="description" content="<?= e($pageDescription) ?>">
<link rel="canonical" href="<?= e(url(ltrim($canonicalPath, '/'))) ?>">
<meta property="og:type" content="website">
<meta property="og:site_name" content="<?= e(SITE_NAME) ?>">
<meta property="og:title" content="<?= e($pageTitle) ?>">
<meta property="og:description" content="<?= e($pageDescription) ?>">
<meta property="og:image" content="<?= e($ogImage) ?>">
<meta name="twitter:card" content="summary_large_image">
<link rel="icon" href="<?= asset('images/logo.png') ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="<?= asset('css/style.css') ?>">
<?php if (!empty($extraHead)) echo $extraHead; ?>
<?php if (!empty($schemaJsonLd)): ?>
<script type="application/ld+json"><?= $schemaJsonLd ?></script>
<?php endif; ?>
<script>
// Applied before paint to avoid a flash of the wrong theme.
(function () {
    var saved = null;
    try { saved = localStorage.getItem('almaktabah_theme'); } catch (e) {}
    document.documentElement.setAttribute('data-theme', saved || '<?= e($_SESSION['theme'] ?? 'auto') ?>');
})();
</script>
</head>
<body class="<?= e($bodyClass) ?>">
<a href="#main-content" class="skip-link">Skip to main content</a>
