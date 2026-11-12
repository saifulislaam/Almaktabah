<?php
/**
 * Upload — validates and stores uploaded files safely.
 *
 * Security measures:
 *  - Real MIME type is checked with finfo (not just the client-supplied extension)
 *  - Stored filenames are randomized (no user-controlled path/filename ever reaches disk)
 *  - Uploads directories block script execution via .htaccess (see /uploads/.htaccess)
 */
class Upload
{
    public static function handleBookFile(array $file): array
    {
        return self::store($file, ALLOWED_BOOK_MIME, MAX_UPLOAD_SIZE, UPLOAD_PATH_BOOKS);
    }

    public static function handleCoverImage(array $file): array
    {
        return self::store($file, ALLOWED_IMAGE_MIME, MAX_COVER_SIZE, UPLOAD_PATH_COVERS, true);
    }

    public static function handleAvatar(array $file): array
    {
        return self::store($file, ALLOWED_IMAGE_MIME, MAX_AVATAR_SIZE, UPLOAD_PATH_AVATARS, true, 400);
    }

    /**
     * @param array $file       one entry from $_FILES
     * @param array $allowedMime map of mime => extension
     * @param bool  $resizeImage whether to normalize dimensions with GD
     * @param int   $maxDim      max width/height when resizing
     */
    private static function store(array $file, array $allowedMime, int $maxSize, string $destDir, bool $resizeImage = false, int $maxDim = 1200): array
    {
        if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
            return ['success' => false, 'error' => null]; // optional file, nothing submitted
        }
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'error' => 'Upload failed (error code ' . $file['error'] . ').'];
        }
        if ($file['size'] > $maxSize) {
            return ['success' => false, 'error' => 'File is too large. Maximum size is ' . format_bytes($maxSize) . '.'];
        }
        if (!is_uploaded_file($file['tmp_name'])) {
            return ['success' => false, 'error' => 'Invalid upload.'];
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!isset($allowedMime[$mime])) {
            return ['success' => false, 'error' => 'Unsupported file type (' . e($mime) . ').'];
        }

        $ext = $allowedMime[$mime];
        $filename = bin2hex(random_bytes(16)) . '.' . $ext;
        $destPath = rtrim($destDir, '/') . '/' . $filename;

        if (!is_dir($destDir) && !mkdir($destDir, 0755, true)) {
            return ['success' => false, 'error' => 'Server storage directory is not writable.'];
        }

        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            return ['success' => false, 'error' => 'Could not save the uploaded file.'];
        }
        @chmod($destPath, 0644);

        if ($resizeImage && in_array($ext, ['jpg', 'png', 'webp'], true)) {
            self::resizeImage($destPath, $ext, $maxDim);
        }

        return [
            'success'  => true,
            'filename' => $filename,
            'ext'      => $ext,
            'size'     => filesize($destPath),
            'mime'     => $mime,
        ];
    }

    /** Downscale large images with GD so covers/avatars stay a sane size on disk. */
    private static function resizeImage(string $path, string $ext, int $maxDim): void
    {
        if (!function_exists('imagecreatetruecolor')) {
            return; // GD not available — skip silently, original file is still valid
        }
        [$width, $height] = @getimagesize($path) ?: [0, 0];
        if (!$width || !$height || ($width <= $maxDim && $height <= $maxDim)) {
            return;
        }

        $src = match ($ext) {
            'jpg'   => @imagecreatefromjpeg($path),
            'png'   => @imagecreatefrompng($path),
            'webp'  => @imagecreatefromwebp($path),
            default => null,
        };
        if (!$src) return;

        $ratio = min($maxDim / $width, $maxDim / $height);
        $newW = (int) round($width * $ratio);
        $newH = (int) round($height * $ratio);

        $dst = imagecreatetruecolor($newW, $newH);
        if ($ext === 'png') {
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
        }
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $width, $height);

        match ($ext) {
            'jpg'   => imagejpeg($dst, $path, 85),
            'png'   => imagepng($dst, $path, 6),
            'webp'  => imagewebp($dst, $path, 85),
            default => null,
        };
        imagedestroy($src);
        imagedestroy($dst);
    }

    public static function deleteBookAssets(?string $filePath, ?string $coverImage): void
    {
        if ($filePath && is_file(UPLOAD_PATH_BOOKS . '/' . $filePath)) {
            @unlink(UPLOAD_PATH_BOOKS . '/' . $filePath);
        }
        if ($coverImage && is_file(UPLOAD_PATH_COVERS . '/' . $coverImage)) {
            @unlink(UPLOAD_PATH_COVERS . '/' . $coverImage);
        }
    }
}
