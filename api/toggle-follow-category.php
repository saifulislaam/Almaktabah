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

$categoryId = (int) ($_POST['category_id'] ?? 0);
if (!$categoryId || !Category::find($categoryId)) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Category not found.']);
    exit;
}

$following = User::toggleFavoriteCategory(Auth::id(), $categoryId);
echo json_encode(['success' => true, 'following' => $following]);
