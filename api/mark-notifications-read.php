<?php
require_once __DIR__ . '/../config/config.php';

if (Auth::check()) {
    Notification::markAllRead(Auth::id());
}
$back = $_SERVER['HTTP_REFERER'] ?? url('index.php');
redirect(str_starts_with($back, SITE_URL) ? $back : 'index.php');
