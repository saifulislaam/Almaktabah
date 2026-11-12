<?php
/**
 * Global helper functions.
 */

/** Escape for HTML output. */
function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/** Translation lookup shorthand — e.g. t('nav.home', 'Home'). See I18n class. */
function t(string $key, ?string $default = null): string
{
    return I18n::t($key, $default);
}

/** Build a full site URL from a relative path. */
function url(string $path = ''): string
{
    $path = ltrim($path, '/');
    return rtrim(SITE_URL, '/') . '/' . $path;
}

/** Build an asset URL (CSS/JS/images), cache-busted by the file's own mtime so
 *  edits are picked up immediately instead of being served from a stale browser
 *  cache under an unchanging ?v= — this was previously hardcoded to a constant
 *  that never changed, which is why fixes could look like they "didn't apply". */
function asset(string $path): string
{
    $path = ltrim($path, '/');
    $fsPath = ROOT_PATH . '/assets/' . $path;
    $version = is_file($fsPath) ? filemtime($fsPath) : time();
    return url('assets/' . $path) . '?v=' . $version;
}

function redirect(string $path): void
{
    header('Location: ' . (str_starts_with($path, 'http') ? $path : url($path)));
    exit;
}

/** Flash a one-time message to the session, read by flash_get() on next page. */
function flash_set(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function flash_get(): array
{
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashes;
}

/** URL-safe slug, transliterating where possible and falling back gracefully for non-Latin titles. */
function make_slug(string $text, ?int $fallbackId = null): string
{
    $slug = strtolower(trim($text));
    $slug = preg_replace('/[^a-z0-9]+/u', '-', $slug);
    $slug = trim($slug, '-');
    if ($slug === '' || $slug === null) {
        $slug = 'item' . ($fallbackId ? '-' . $fallbackId : '-' . substr(md5($text), 0, 8));
    }
    return substr($slug, 0, 180);
}

/** Human-friendly relative time, e.g. "3 days ago". */
function time_ago(string $datetime): string
{
    $diff = time() - strtotime($datetime);
    if ($diff < 60) return 'just now';
    $units = [31536000 => 'year', 2592000 => 'month', 604800 => 'week', 86400 => 'day', 3600 => 'hour', 60 => 'minute'];
    foreach ($units as $secs => $label) {
        $count = floor($diff / $secs);
        if ($count >= 1) return $count . ' ' . $label . ($count > 1 ? 's' : '') . ' ago';
    }
    return 'just now';
}

/** Human-friendly file size, e.g. "4.2 MB". */
function format_bytes(?int $bytes): string
{
    if (!$bytes) return '—';
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    $value = $bytes;
    while ($value >= 1024 && $i < count($units) - 1) {
        $value /= 1024;
        $i++;
    }
    return round($value, 1) . ' ' . $units[$i];
}

/** Compact number formatting, e.g. 12400 -> "12.4K". */
function format_count(int $n): string
{
    if ($n >= 1000000) return round($n / 1000000, 1) . 'M';
    if ($n >= 1000) return round($n / 1000, 1) . 'K';
    return (string) $n;
}

/** Truncate plain text to a word boundary near $limit characters. */
function excerpt(?string $text, int $limit = 150): string
{
    $text = trim(strip_tags((string) $text));
    if (mb_strlen($text) <= $limit) return $text;
    $cut = mb_substr($text, 0, $limit);
    $cut = mb_substr($cut, 0, mb_strrpos($cut, ' ') ?: $limit);
    return $cut . '…';
}

/** Renders a user's avatar: their own uploaded photo, else generated initials. */
function avatar_html(array $user, int $size = 40): string
{
    if (!empty($user['profile_image'])) {
        $src = e(UPLOAD_URL_AVATARS . '/' . $user['profile_image']);
        return '<img src="' . $src . '" alt="' . e($user['display_name'] ?? $user['username']) . '" class="avatar-img" style="width:' . $size . 'px;height:' . $size . 'px;">';
    }
    $name = $user['display_name'] ?? $user['username'] ?? '?';
    $initial = mb_strtoupper(mb_substr(trim($name), 0, 1));
    $palette = ['#1B4A5A', '#C89B5C', '#1E5C4F', '#7A4B3A', '#2D6577', '#8A5A2B'];
    $hash = crc32($user['username'] ?? $name);
    $color = $palette[$hash % count($palette)];
    return '<span class="avatar-initial" style="width:' . $size . 'px;height:' . $size . 'px;line-height:' . $size . 'px;font-size:' . round($size * 0.42) . 'px;background:' . $color . '">' . e($initial) . '</span>';
}

/** Small inline icon set — a single 8-point khatam star plus UI glyphs, kept dependency-free. */
function icon(string $name, string $class = ''): string
{
    $icons = [
        'search'   => '<path d="M11 19a8 8 0 1 1 0-16 8 8 0 0 1 0 16Zm10 2-5.6-5.6"/>',
        'menu'     => '<path d="M3 6h18M3 12h18M3 18h18"/>',
        'close'    => '<path d="M18 6 6 18M6 6l12 12"/>',
        'sun'      => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>',
        'moon'     => '<path d="M20.8 14.5A8.5 8.5 0 1 1 9.5 3.2a7 7 0 0 0 11.3 11.3Z"/>',
        'user'     => '<circle cx="12" cy="8" r="4"/><path d="M4 21c1.5-4 5-6 8-6s6.5 2 8 6"/>',
        'upload'   => '<path d="M12 16V4M7 9l5-5 5 5"/><path d="M4 16v3a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-3"/>',
        'bell'     => '<path d="M6 9a6 6 0 1 1 12 0c0 5 2 6 2 6H4s2-1 2-6Z"/><path d="M10 20a2 2 0 0 0 4 0"/>',
        'bookmark' => '<path d="M6 3h12v18l-6-4-6 4Z"/>',
        'heart'    => '<path d="M12 20s-7-4.4-9.5-9A5.5 5.5 0 0 1 12 6a5.5 5.5 0 0 1 9.5 5c-2.5 4.6-9.5 9-9.5 9Z"/>',
        'download' => '<path d="M12 4v12M7 11l5 5 5-5"/><path d="M4 20h16"/>',
        'book'     => '<path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H20v16H6.5A2.5 2.5 0 0 0 4 21Z"/><path d="M4 5.5V21"/>',
        'star'     => '<path d="M12 3.5 14.6 9l6 .9-4.3 4.2 1 6-5.3-2.8-5.3 2.8 1-6L3.4 9.9l6-.9Z"/>',
        'chevron-right' => '<path d="m9 6 6 6-6 6"/>',
        'chevron-left'  => '<path d="m15 6-6 6 6 6"/>',
        'chevron-down'  => '<path d="m6 9 6 6 6-6"/>',
        'trash'    => '<path d="M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13"/>',
        'edit'     => '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/>',
        'eye'      => '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/>',
        'link'     => '<path d="M9 15 15 9"/><path d="M10 6l1-1a4 4 0 1 1 6 6l-1 1"/><path d="M14 18l-1 1a4 4 0 1 1-6-6l1-1"/>',
        'check'    => '<path d="m4 12 6 6L20 6"/>',
        'x-circle' => '<circle cx="12" cy="12" r="9"/><path d="m9 9 6 6M15 9l-6 6"/>',
        'users'    => '<circle cx="9" cy="8" r="3"/><path d="M2 20c1-3.5 3.5-5 7-5s6 1.5 7 5"/><circle cx="17" cy="9" r="2.5"/><path d="M16 12c2.6.3 4 1.7 4.7 4"/>',
        'chart'    => '<path d="M4 20V10M11 20V4M18 20v-7"/><path d="M2 20h20"/>',
        'settings' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1-1.6 1.7 1.7 0 0 0-1.9.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.9 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.6-1 1.7 1.7 0 0 0-.3-1.9l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.9.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.9-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.9V9c.2.6.7 1 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1Z"/>',
        'logout'   => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/>',
        'grid'     => '<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/>',
        'shield'   => '<path d="M12 3l8 3v6c0 4.5-3.4 7.7-8 9-4.6-1.3-8-4.5-8-9V6Z"/>',
        'plus'     => '<path d="M12 5v14M5 12h14"/>',
        'khatam'   => '<path d="M12 2 14 8 20 6 17 11 22 13 16 15 18 21 12 17 6 21 8 15 2 13 7 11 4 6 10 8Z"/>',
        'flag'     => '<path d="M5 21V4M5 4h13l-3 4 3 4H5"/>',
        'home'     => '<path d="M4 11 12 4l8 7"/><path d="M6 10v10h12V10"/>',
        'globe'    => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.5 2.5 3.8 5.8 3.8 9s-1.3 6.5-3.8 9c-2.5-2.5-3.8-5.8-3.8-9S9.5 5.5 12 3Z"/>',
    ];
    $path = $icons[$name] ?? $icons['book'];
    return '<svg class="icon ' . e($class) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $path . '</svg>';
}

/**
 * Renders a pagination bar. $extraParams are merged into the query string
 * for each page link (category id/slug, sort, filters, etc.).
 */
function render_pagination(int $current, int $totalPages, array $extraParams = [], string $script = ''): void
{
    if ($totalPages <= 1) return;
    $script = $script ?: basename($_SERVER['SCRIPT_NAME']);
    $buildUrl = function (int $p) use ($script, $extraParams) {
        return url($script . '?' . http_build_query(array_merge($extraParams, ['page' => $p])));
    };
    echo '<nav class="pagination" aria-label="Pagination">';
    echo $current > 1 ? '<a href="' . e($buildUrl($current - 1)) . '">' . icon('chevron-left') . '</a>' : '<span class="disabled">' . icon('chevron-left') . '</span>';

    $start = max(1, $current - 2);
    $end = min($totalPages, $current + 2);
    if ($start > 1) { echo '<a href="' . e($buildUrl(1)) . '">1</a>'; if ($start > 2) echo '<span>…</span>'; }
    for ($i = $start; $i <= $end; $i++) {
        echo $i === $current ? '<span class="current">' . $i . '</span>' : '<a href="' . e($buildUrl($i)) . '">' . $i . '</a>';
    }
    if ($end < $totalPages) { if ($end < $totalPages - 1) echo '<span>…</span>'; echo '<a href="' . e($buildUrl($totalPages)) . '">' . $totalPages . '</a>'; }

    echo $current < $totalPages ? '<a href="' . e($buildUrl($current + 1)) . '">' . icon('chevron-right') . '</a>' : '<span class="disabled">' . icon('chevron-right') . '</span>';
    echo '</nav>';
}
function setting(string $key, $default = null)
{
    static $cache = null;
    if ($cache === null) {
        $rows = Database::fetchAll('SELECT setting_key, setting_value FROM site_settings');
        $cache = [];
        foreach ($rows as $row) {
            $cache[$row['setting_key']] = $row['setting_value'];
        }
    }
    return $cache[$key] ?? $default;
}

/** Star rating as inline SVG icons (filled/empty), used on cards and review forms. */
function star_rating_html(float $rating, int $max = 5): string
{
    $html = '<span class="stars" aria-label="' . $rating . ' out of ' . $max . ' stars">';
    for ($i = 1; $i <= $max; $i++) {
        $filled = $rating >= $i - 0.25;
        $html .= '<svg class="star ' . ($filled ? 'star-filled' : 'star-empty') . '" viewBox="0 0 24 24" width="14" height="14"><path fill="currentColor" d="M12 2.5 14.5 9l6.5.6-4.9 4.3 1.5 6.4L12 17l-5.6 3.3 1.5-6.4L3 9.6 9.5 9Z"/></svg>';
    }
    return $html . '</span>';
}

/**
 * Renders one book card. Shared by index.php, book.php (similar books),
 * category.php, author.php, search.php, wishlist.php, and profile.php so
 * the grid looks and behaves identically everywhere.
 */
function render_book_card(array $book, array $wishlistIds = []): void
{
    $onWishlist = in_array((int) $book['id'], $wishlistIds, true);
    ?>
    <div class="book-card">
        <a href="<?= url('book.php?id=' . $book['id'] . '&slug=' . $book['slug']) ?>" class="book-card-cover">
            <?php if (!empty($book['cover_image'])): ?>
                <img src="<?= e(UPLOAD_URL_COVERS . '/' . $book['cover_image']) ?>" alt="<?= e($book['title']) ?> book cover - AlMaktabah" loading="lazy">
            <?php else: ?>
                <div class="no-cover"><?= icon('book') ?></div>
            <?php endif; ?>
            <?php if (!empty($book['file_type'])): ?><span class="format-badge"><?= e($book['file_type']) ?></span><?php endif; ?>
            <button class="wishlist-toggle <?= $onWishlist ? 'active' : '' ?>" data-wishlist-toggle="<?= $book['id'] ?>" aria-label="Toggle wishlist"><?= icon('heart') ?></button>
        </a>
        <a href="<?= url('book.php?id=' . $book['id'] . '&slug=' . $book['slug']) ?>">
            <div class="book-card-title"><?= e($book['title']) ?></div>
        </a>
        <?php if (!empty($book['author_name'])): ?>
            <div class="book-card-author"><?= e($book['author_name']) ?></div>
        <?php endif; ?>
        <div class="book-card-meta">
            <?= star_rating_html((float) $book['avg_rating']) ?>
            <span><?= format_count((int) $book['views_count']) ?> views</span>
        </div>
        <?php if (!empty($book['_reason'])): ?><div class="book-card-reason"><?= e($book['_reason']) ?></div><?php endif; ?>
    </div>
    <?php
}
