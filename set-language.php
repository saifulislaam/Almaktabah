<?php
require_once __DIR__ . '/config/config.php';

$lang = $_GET['lang'] ?? $_POST['lang'] ?? 'en';
I18n::setLanguage($lang);

$back = $_SERVER['HTTP_REFERER'] ?? url('index.php');
redirect(str_starts_with($back, rtrim(SITE_URL, '/')) ? $back : 'index.php');
