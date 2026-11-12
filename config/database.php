<?php
/**
 * Database connection settings.
 * Update these four values to match your MySQL/MariaDB server.
 */
define('DB_HOST', 'localhost');
define('DB_NAME', 'almaktabah');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

require_once __DIR__ . '/../classes/Database.php';
