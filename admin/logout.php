<?php
require_once __DIR__ . '/../config/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::verifyOrDie();
    if (Auth::check() && Auth::isAdmin()) {
        Admin::log('logout', 'Admin signed out');
    }
    Auth::logout();
}
redirect('admin/index.php');
