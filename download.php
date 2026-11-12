<?php
require_once __DIR__ . '/config/config.php';

Auth::requireLogin('login.php');

$bookId = (int) ($_GET['id'] ?? 0);
$book = $bookId ? Book::find($bookId) : null;

if (!$book || $book['status'] !== 'approved') {
    http_response_code(404);
    die('Book not found.');
}
if ($book['external_link']) {
    redirect($book['external_link']);
}
if (!$book['file_path'] || !is_file(UPLOAD_PATH_BOOKS . '/' . $book['file_path'])) {
    http_response_code(404);
    die('The file for this book is missing. Please contact the site administrator.');
}

Book::logDownload($bookId, Auth::id(), Auth::clientIp());

$filePath = UPLOAD_PATH_BOOKS . '/' . $book['file_path'];
$niceName = make_slug($book['title']) . '.' . $book['file_type'];

header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $niceName . '"');
header('Content-Transfer-Encoding: binary');
header('Content-Length: ' . filesize($filePath));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-cache, must-revalidate');
flush();
readfile($filePath);
exit;
