<?php
/**
 * AlMaktabah — Site Configuration
 * Edit the values below to match your server before deploying.
 */

// ---- Environment -----------------------------------------------------
error_reporting(E_ALL);
ini_set('display_errors', '0');      // set to '1' only while debugging locally
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../storage_errors.log');

// ---- Site identity -----------------------------------------------------
define('SITE_NAME', 'AlMaktabah');
define('SITE_TAGLINE', 'Islamic Digital Library');
define('SITE_URL', 'http://localhost/almaktabah'); // no trailing slash — change to your domain in production

// ---- Paths -----------------------------------------------------
define('ROOT_PATH', dirname(__DIR__));
define('UPLOAD_PATH_BOOKS', ROOT_PATH . '/uploads/books');
define('UPLOAD_PATH_COVERS', ROOT_PATH . '/uploads/covers');
define('UPLOAD_PATH_AVATARS', ROOT_PATH . '/uploads/avatars');
define('UPLOAD_URL_BOOKS', SITE_URL . '/uploads/books');
define('UPLOAD_URL_COVERS', SITE_URL . '/uploads/covers');
define('UPLOAD_URL_AVATARS', SITE_URL . '/uploads/avatars');

// ---- Upload limits -----------------------------------------------------
define('MAX_UPLOAD_SIZE', 100 * 1024 * 1024); // 100MB — keep in sync with php.ini upload_max_filesize/post_max_size
define('MAX_COVER_SIZE', 5 * 1024 * 1024);
define('MAX_AVATAR_SIZE', 2 * 1024 * 1024);
define('DAILY_UPLOAD_LIMIT', 10);

define('ALLOWED_BOOK_MIME', [
    'application/pdf'                                                          => 'pdf',
    'application/msword'                                                       => 'doc',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document'  => 'docx',
    'application/vnd.ms-powerpoint'                                            => 'ppt',
    'application/vnd.openxmlformats-officedocument.presentationml.presentation'=> 'pptx',
    'text/plain'                                                               => 'txt',
    'application/epub+zip'                                                     => 'epub',
    'application/x-mobipocket-ebook'                                           => 'mobi',
    'application/vnd.oasis.opendocument.text'                                  => 'odt',
    'application/rtf'                                                          => 'rtf',
    'text/rtf'                                                                 => 'rtf',
]);

define('ALLOWED_IMAGE_MIME', [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
]);

// Formats that can genuinely be rendered in-browser; everything else falls
// back to "download to view" per the product spec.
define('ONLINE_READABLE_FORMATS', ['pdf', 'epub', 'txt']);

// ---- Session -----------------------------------------------------
define('SESSION_NAME', 'almaktabah_session');
define('REMEMBER_ME_DAYS', 30);

// ---- Email (PHPMailer via Gmail SMTP) — used for OTP verification & password reset ----
// Generate a Gmail "App Password" at https://myaccount.google.com/apppasswords
// (requires 2-Step Verification enabled on the Gmail account). Do NOT use the
// normal Gmail login password here — Google blocks that for SMTP.
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_ENCRYPTION', 'tls');
define('SMTP_USERNAME', 'almaktabahislamia@gmail.com');
define('SMTP_PASSWORD', 'REPLACE_WITH_YOUR_16_CHARACTER_GMAIL_APP_PASSWORD');
define('SMTP_FROM_NAME', SITE_NAME);

// ---- OTP verification -----------------------------------------------------
define('OTP_LENGTH', 6);
define('OTP_EXPIRY_MINUTES', 10);
define('OTP_RESEND_COOLDOWN_SECONDS', 60);
define('OTP_MAX_ATTEMPTS', 5);

// ---- Security -----------------------------------------------------
define('LOGIN_MAX_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_MINUTES', 15);
define('VISITOR_ACTIVE_WINDOW_SECONDS', 120); // window used to count "visitors online now"

// ---- Autoloader -----------------------------------------------------
// Maps class name -> classes/{ClassName}.php. Keeps the project
// dependency-free (no Composer needed) while still loading classes
// on demand instead of a long manual require_once list.
spl_autoload_register(function (string $class) {
    $path = __DIR__ . '/../classes/' . $class . '.php';
    if (is_file($path)) {
        require_once $path;
    }
});

// ---- Timezone -----------------------------------------------------
date_default_timezone_set('Asia/Karachi');

// ---- Session bootstrap -----------------------------------------------------
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'samesite' => 'Lax',
    ]);
    session_name(SESSION_NAME);
    session_start();
}

require_once __DIR__ . '/database.php';
require_once ROOT_PATH . '/includes/functions.php';
I18n::init();

// ---- Maintenance mode -----------------------------------------------------
// Blocks public pages when enabled from Admin -> Settings; admins/subadmins
// and the admin panel itself stay accessible so it can be turned back off.
$__script = basename($_SERVER['SCRIPT_NAME'] ?? '');
$__isAdminArea = str_contains($_SERVER['SCRIPT_NAME'] ?? '', '/admin/');
if (setting('maintenance_mode') === '1' && !$__isAdminArea && !(Auth::check() && Auth::isAdmin())) {
    http_response_code(503);
    header('Retry-After: 3600');
    $siteNameForMaintenance = setting('site_name', 'AlMaktabah');
    echo '<!doctype html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
       . '<title>' . e($siteNameForMaintenance) . ' - Under Maintenance</title>'
       . '<style>body{font-family:sans-serif;background:#F6F1E4;color:#23313A;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;text-align:center;padding:20px;}'
       . 'div{max-width:420px;}h1{color:#1B4A5A;}</style></head><body><div>'
       . '<h1>' . e($siteNameForMaintenance) . '</h1><p>We\'re making some improvements and will be back shortly. Please check back soon.</p>'
       . '</div></body></html>';
    exit;
}
