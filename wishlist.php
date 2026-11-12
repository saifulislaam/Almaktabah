<?php
require_once __DIR__ . '/config/config.php';
Auth::requireLogin('login.php');

$pageTitle = 'My Wishlist - ' . SITE_NAME;
include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';

$books = Wishlist::forUser(Auth::id());
$wishlistIds = array_column($books, 'id');
?>

<nav class="breadcrumbs"><a href="<?= url('index.php') ?>">Home</a><span class="crumb-sep">✦</span><span>My Wishlist</span></nav>
<h1 class="page-title" style="margin-bottom:1.25rem;">My Wishlist</h1>

<?php if (!$books): ?>
    <div class="empty-state"><?= icon('heart') ?><h3>Your wishlist is empty</h3><p>Tap the heart icon on any book to save it for later.</p></div>
<?php else: ?>
    <div class="book-grid"><?php foreach ($books as $b) render_book_card($b, $wishlistIds); ?></div>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
