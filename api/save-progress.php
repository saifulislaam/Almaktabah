<?php
require_once __DIR__ . '/../config/config.php';
header('Content-Type: application/json');

if (!Auth::check()) {
    echo json_encode(['success' => false]); // reading progress is optional for guests — fail quietly
    exit;
}
if (!Csrf::validate($_POST['csrf_token'] ?? '')) {
    http_response_code(419);
    echo json_encode(['success' => false]);
    exit;
}

$bookId = (int) ($_POST['book_id'] ?? 0);
$page = max(1, (int) ($_POST['page'] ?? 1));
$total = (int) ($_POST['total'] ?? 0) ?: null;

if ($bookId && Book::find($bookId)) {
    Book::updateReadingProgress(Auth::id(), $bookId, $page, $total);
}

echo json_encode(['success' => true]);
