<?php
/**
 * Search — MySQL FULLTEXT-backed search across titles/descriptions,
 * plus author and category name matching. No external search service
 * required.
 */
class Search
{
    /**
     * @param array $filters keys: category_id, author_id, language, format
     * @param string $sort   relevance|newest|downloads|rating|title
     */
    public static function books(string $query, array $filters = [], string $sort = 'relevance', int $page = 1, int $perPage = 24): array
    {
        $query = trim($query);
        $where = ["b.status = 'approved'"];
        $selectParams = [];
        $whereParams = [];
        $selectScore = '0 AS relevance';

        if ($query !== '') {
            $selectScore = 'MATCH(b.title, b.description) AGAINST (? IN NATURAL LANGUAGE MODE) AS relevance';
            $selectParams[] = $query;
            $where[] = '(MATCH(b.title, b.description) AGAINST (? IN NATURAL LANGUAGE MODE) OR b.title LIKE ? OR a.name LIKE ?)';
            $whereParams[] = $query;
            $whereParams[] = '%' . $query . '%';
            $whereParams[] = '%' . $query . '%';
        }

        if (!empty($filters['category_id'])) { $where[] = 'b.category_id = ?'; $whereParams[] = $filters['category_id']; }
        if (!empty($filters['author_id']))   { $where[] = 'b.author_id = ?';   $whereParams[] = $filters['author_id']; }
        if (!empty($filters['language']))    { $where[] = 'b.language = ?';    $whereParams[] = $filters['language']; }
        if (!empty($filters['format']))      { $where[] = 'b.file_type = ?';  $whereParams[] = $filters['format']; }

        $orderBy = match ($sort) {
            'newest'    => 'b.created_at DESC',
            'downloads' => 'b.downloads_count DESC',
            'rating'    => 'b.avg_rating DESC',
            'title'     => 'b.title ASC',
            default     => $query !== '' ? 'relevance DESC, b.views_count DESC' : 'b.views_count DESC',
        };

        $whereSql = implode(' AND ', $where);
        $offset = max(0, ($page - 1) * $perPage);

        $rows = Database::fetchAll(
            "SELECT b.*, c.name AS category_name, a.name AS author_name, $selectScore
             FROM books b
             LEFT JOIN categories c ON c.id = b.category_id
             LEFT JOIN authors a ON a.id = b.author_id
             WHERE $whereSql
             ORDER BY $orderBy
             LIMIT ? OFFSET ?",
            [...$selectParams, ...$whereParams, $perPage, $offset]
        );

        $total = Database::fetchOne(
            "SELECT COUNT(*) AS c FROM books b LEFT JOIN authors a ON a.id = b.author_id WHERE $whereSql",
            $whereParams
        )['c'] ?? 0;

        return ['rows' => $rows, 'total' => (int) $total, 'page' => $page, 'pages' => (int) ceil($total / $perPage)];
    }

    /** Lightweight suggestions for the navbar's live search dropdown. */
    public static function suggest(string $query, int $limit = 8): array
    {
        $query = trim($query);
        if ($query === '') return ['books' => [], 'authors' => [], 'categories' => []];

        $books = Database::fetchAll(
            "SELECT b.id, b.title, b.slug, b.cover_image, a.name AS author_name
             FROM books b LEFT JOIN authors a ON a.id = b.author_id
             WHERE b.status = 'approved' AND b.title LIKE ? ORDER BY b.views_count DESC LIMIT ?",
            ['%' . $query . '%', $limit]
        );
        $authors = Database::fetchAll('SELECT id, name, slug FROM authors WHERE name LIKE ? LIMIT 5', ['%' . $query . '%']);
        $categories = Database::fetchAll('SELECT id, name, slug FROM categories WHERE name LIKE ? LIMIT 5', ['%' . $query . '%']);

        return ['books' => $books, 'authors' => $authors, 'categories' => $categories];
    }

    public static function distinctLanguages(): array
    {
        return array_column(Database::fetchAll("SELECT DISTINCT language FROM books WHERE status='approved' ORDER BY language"), 'language');
    }

    public static function distinctFormats(): array
    {
        return array_column(Database::fetchAll("SELECT DISTINCT file_type FROM books WHERE status='approved' AND file_type IS NOT NULL ORDER BY file_type"), 'file_type');
    }
}
