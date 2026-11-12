<?php
/**
 * Book — CRUD, listings, filters, and view/download logging.
 * Public queries always filter to status = 'approved' unless viewing
 * one's own uploads or acting as an admin.
 */
class Book
{
    private const SELECT_BASE = "
        SELECT b.*, c.name AS category_name, c.slug AS category_slug,
               a.name AS author_name, a.slug AS author_slug,
               u.username AS uploader_username
        FROM books b
        LEFT JOIN categories c ON c.id = b.category_id
        LEFT JOIN authors a ON a.id = b.author_id
        LEFT JOIN users u ON u.id = b.uploader_id
    ";

    public static function find(int $id): ?array
    {
        return Database::fetchOne(self::SELECT_BASE . ' WHERE b.id = ?', [$id]);
    }

    public static function create(array $data): int
    {
        return Database::insert(
            'INSERT INTO books
                (title, slug, description, category_id, author_id, cover_image, file_path, file_type,
                 file_size, external_link, uploader_id, language, publication_date, publisher, isbn,
                 pages, edition, status)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [
                $data['title'], $data['slug'], $data['description'], $data['category_id'], $data['author_id'],
                $data['cover_image'], $data['file_path'], $data['file_type'], $data['file_size'],
                $data['external_link'], $data['uploader_id'], $data['language'], $data['publication_date'],
                $data['publisher'], $data['isbn'], $data['pages'], $data['edition'],
                $data['status'] ?? 'pending',
            ]
        );
    }

    public static function updateMetadata(int $id, array $data): void
    {
        $fields = [];
        $params = [];
        foreach (['title', 'slug', 'description', 'category_id', 'author_id', 'language', 'publisher', 'isbn', 'pages', 'edition', 'external_link', 'publication_date'] as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "$field = ?";
                $params[] = $data[$field];
            }
        }
        if (!$fields) return;
        $params[] = $id;
        Database::execute('UPDATE books SET ' . implode(', ', $fields) . ' WHERE id = ?', $params);
    }

    public static function updateCover(int $id, string $filename): void
    {
        Database::execute('UPDATE books SET cover_image = ? WHERE id = ?', [$filename, $id]);
    }

    public static function updateStatus(int $id, string $status, ?string $reason = null): void
    {
        Database::execute('UPDATE books SET status = ?, reject_reason = ? WHERE id = ?', [$status, $reason, $id]);
    }

    public static function toggleFeatured(int $id): void
    {
        Database::execute('UPDATE books SET featured = NOT featured WHERE id = ?', [$id]);
    }

    public static function delete(int $id): ?array
    {
        $book = self::find($id);
        if ($book) {
            Upload::deleteBookAssets($book['file_path'], $book['cover_image']);
            Database::execute('DELETE FROM books WHERE id = ?', [$id]);
        }
        return $book;
    }

    public static function belongsToUser(int $bookId, int $userId): bool
    {
        $row = Database::fetchOne('SELECT id FROM books WHERE id = ? AND uploader_id = ?', [$bookId, $userId]);
        return $row !== null;
    }

    // -----------------------------------------------------------------
    // Listings
    // -----------------------------------------------------------------

    /**
     * General-purpose filtered/paginated listing.
     * $filters keys: category_id, author_id, language, format, status, uploader_id, featured
     * $sort: newest|oldest|popular|downloads|rating|title
     */
    public static function listFiltered(array $filters = [], string $sort = 'newest', int $page = 1, int $perPage = 24): array
    {
        $where = [];
        $params = [];

        $where[] = 'b.status = ?';
        $params[] = $filters['status'] ?? 'approved';

        if (!empty($filters['category_id'])) { $where[] = 'b.category_id = ?'; $params[] = $filters['category_id']; }
        if (!empty($filters['author_id']))   { $where[] = 'b.author_id = ?';   $params[] = $filters['author_id']; }
        if (!empty($filters['uploader_id'])) { $where[] = 'b.uploader_id = ?'; $params[] = $filters['uploader_id']; }
        if (!empty($filters['language']))    { $where[] = 'b.language = ?';    $params[] = $filters['language']; }
        if (!empty($filters['format']))      { $where[] = 'b.file_type = ?';  $params[] = $filters['format']; }
        if (!empty($filters['featured']))    { $where[] = 'b.featured = 1'; }

        $orderBy = match ($sort) {
            'oldest'    => 'b.created_at ASC',
            'popular'   => 'b.views_count DESC',
            'downloads' => 'b.downloads_count DESC',
            'rating'    => 'b.avg_rating DESC, b.reviews_count DESC',
            'title'     => 'b.title ASC',
            default     => 'b.created_at DESC',
        };

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $offset = max(0, ($page - 1) * $perPage);

        $rows = Database::fetchAll(
            self::SELECT_BASE . " $whereSql ORDER BY $orderBy LIMIT ? OFFSET ?",
            [...$params, $perPage, $offset]
        );
        $total = Database::fetchOne("SELECT COUNT(*) AS c FROM books b $whereSql", $params)['c'] ?? 0;

        return ['rows' => $rows, 'total' => (int) $total, 'page' => $page, 'per_page' => $perPage, 'pages' => (int) ceil($total / $perPage)];
    }

    public static function trending(int $limit = 8, int $windowDays = 14): array
    {
        return Database::fetchAll(
            self::SELECT_BASE . "
             LEFT JOIN (
                SELECT book_id, COUNT(*) AS recent_views FROM book_views
                WHERE viewed_at >= DATE_SUB(NOW(), INTERVAL ? DAY) GROUP BY book_id
             ) rv ON rv.book_id = b.id
             WHERE b.status = 'approved'
             ORDER BY COALESCE(rv.recent_views, 0) DESC, b.downloads_count DESC, b.views_count DESC
             LIMIT ?",
            [$windowDays, $limit]
        );
    }

    public static function newArrivals(int $limit = 8): array
    {
        return Database::fetchAll(self::SELECT_BASE . " WHERE b.status = 'approved' ORDER BY b.created_at DESC LIMIT ?", [$limit]);
    }

    public static function featured(int $limit = 8): array
    {
        return Database::fetchAll(self::SELECT_BASE . " WHERE b.status = 'approved' AND b.featured = 1 ORDER BY b.created_at DESC LIMIT ?", [$limit]);
    }

    public static function topRated(int $limit = 8, int $minReviews = 1): array
    {
        return Database::fetchAll(
            self::SELECT_BASE . " WHERE b.status = 'approved' AND b.reviews_count >= ? ORDER BY b.avg_rating DESC, b.reviews_count DESC LIMIT ?",
            [$minReviews, $limit]
        );
    }

    public static function mostDownloaded(int $limit = 10): array
    {
        return Database::fetchAll(self::SELECT_BASE . " WHERE b.status = 'approved' ORDER BY b.downloads_count DESC LIMIT ?", [$limit]);
    }

    public static function pendingCount(): int
    {
        return (int) (Database::fetchOne("SELECT COUNT(*) AS c FROM books WHERE status = 'pending'")['c'] ?? 0);
    }

    // -----------------------------------------------------------------
    // Logging (also feeds analytics + the recommendation engine)
    // -----------------------------------------------------------------
    public static function logView(int $bookId, ?int $userId, string $ip): void
    {
        Database::insert('INSERT INTO book_views (book_id, user_id, ip_address) VALUES (?,?,?)', [$bookId, $userId, $ip]);
        Database::execute('UPDATE books SET views_count = views_count + 1 WHERE id = ?', [$bookId]);

        if ($userId) {
            Database::execute(
                'INSERT INTO reading_history (user_id, book_id, last_read_at) VALUES (?,?,NOW())
                 ON DUPLICATE KEY UPDATE last_read_at = NOW()',
                [$userId, $bookId]
            );
        }
    }

    public static function logDownload(int $bookId, ?int $userId, string $ip): void
    {
        Database::insert('INSERT INTO book_downloads (book_id, user_id, ip_address) VALUES (?,?,?)', [$bookId, $userId, $ip]);
        Database::execute('UPDATE books SET downloads_count = downloads_count + 1 WHERE id = ?', [$bookId]);
    }

    public static function updateReadingProgress(int $userId, int $bookId, int $lastPage, ?int $totalPages): void
    {
        $pct = $totalPages ? round(min(100, ($lastPage / max(1, $totalPages)) * 100), 2) : 0;
        Database::execute(
            'INSERT INTO reading_history (user_id, book_id, last_page, total_pages, read_percentage, last_read_at)
             VALUES (?,?,?,?,?,NOW())
             ON DUPLICATE KEY UPDATE last_page = VALUES(last_page), total_pages = VALUES(total_pages),
                                      read_percentage = VALUES(read_percentage), last_read_at = NOW()',
            [$userId, $bookId, $lastPage, $totalPages, $pct]
        );
    }

    /** Recalculate the cached avg_rating / reviews_count after a review changes. */
    public static function recalcRating(int $bookId): void
    {
        $row = Database::fetchOne(
            "SELECT COALESCE(AVG(rating),0) AS avg_r, COUNT(*) AS cnt FROM reviews WHERE book_id = ? AND status = 'approved'",
            [$bookId]
        );
        Database::execute('UPDATE books SET avg_rating = ?, reviews_count = ? WHERE id = ?', [round((float) $row['avg_r'], 2), (int) $row['cnt'], $bookId]);
    }

    public static function isReadableOnline(array $book): bool
    {
        return in_array($book['file_type'], ONLINE_READABLE_FORMATS, true);
    }
}
