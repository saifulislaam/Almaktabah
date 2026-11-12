<?php
require_once __DIR__ . '/../config/config.php';
header('Content-Type: application/json');

if (!Auth::check() || !Auth::isAdmin()) {
    http_response_code(403);
    echo json_encode(['error' => 'forbidden']);
    exit;
}

echo json_encode(['count' => Admin::activeVisitorCount()]);
