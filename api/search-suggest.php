<?php
require_once __DIR__ . '/../config/config.php';
header('Content-Type: application/json');

$query = trim($_GET['q'] ?? '');
if ($query === '' || mb_strlen($query) > 100) {
    echo json_encode(['books' => [], 'authors' => [], 'categories' => []]);
    exit;
}

echo json_encode(Search::suggest($query, 6));
