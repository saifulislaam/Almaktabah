<?php
require_once __DIR__ . '/../config/config.php';
header('Content-Type: application/json');

if (!Auth::check()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Please sign in.']);
    exit;
}
if (!Csrf::validate($_POST['csrf_token'] ?? '')) {
    http_response_code(419);
    echo json_encode(['success' => false, 'error' => 'Session expired, please refresh.']);
    exit;
}

$bookId = (int) ($_POST['book_id'] ?? 0);
if (!$bookId || !Book::find($bookId)) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Book not found.']);
    exit;
}

$onWishlist = Wishlist::toggle(Auth::id(), $bookId);
echo json_encode(['success' => true, 'on_wishlist' => $onWishlist]);
