<?php
require_once __DIR__ . '/../config/config.php';
header('Content-Type: application/json');

if (!Auth::check() || !Csrf::validate($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false]);
    exit;
}

$theme = $_POST['theme'] ?? 'auto';
User::updateTheme(Auth::id(), $theme);
echo json_encode(['success' => true]);
