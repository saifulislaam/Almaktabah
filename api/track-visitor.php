<?php
require_once __DIR__ . '/../config/config.php';
header('Content-Type: application/json');

if (empty($_COOKIE['visitor_token'])) {
    $token = bin2hex(random_bytes(20));
    setcookie('visitor_token', $token, time() + 86400 * 30, '/', '', false, true);
} else {
    $token = $_COOKIE['visitor_token'];
}

$page = substr($_POST['page'] ?? '/', 0, 255);
Admin::recordHeartbeat($token, Auth::id(), Auth::clientIp(), $page, $_SERVER['HTTP_USER_AGENT'] ?? '');

echo json_encode(['ok' => true]);
