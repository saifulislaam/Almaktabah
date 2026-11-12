<?php
/**
 * User — profile lookups and account settings.
 * Registration/login itself lives in Auth (password + OTP verification).
 */
class User
{
    public static function find(int $id): ?array
    {
        return Database::fetchOne('SELECT * FROM users WHERE id = ?', [$id]);
    }

    public static function findByUsername(string $username): ?array
    {
        return Database::fetchOne('SELECT * FROM users WHERE username = ?', [$username]);
    }

    public static function updateProfile(int $id, string $displayName, ?string $bio): array
    {
        $displayName = trim($displayName);
        if ($displayName === '' || mb_strlen($displayName) > 100) {
            return ['success' => false, 'error' => 'Display name must be 1-100 characters.'];
        }
        Database::execute('UPDATE users SET display_name = ?, bio = ? WHERE id = ?', [$displayName, $bio, $id]);
        $_SESSION['display_name'] = $displayName;
        return ['success' => true];
    }

    public static function updateAvatar(int $id, string $filename): void
    {
        Database::execute('UPDATE users SET profile_image = ? WHERE id = ?', [$filename, $id]);
        $_SESSION['profile_image'] = $filename;
    }

    public static function updateTheme(int $id, string $theme): void
    {
        if (!in_array($theme, ['light', 'dark', 'auto'], true)) return;
        Database::execute('UPDATE users SET theme_preference = ? WHERE id = ?', [$theme, $id]);
        $_SESSION['theme'] = $theme;
    }

    public static function uploadsToday(int $userId): int
    {
        return (int) (Database::fetchOne(
            'SELECT COUNT(*) AS c FROM books WHERE uploader_id = ? AND DATE(created_at) = CURDATE()',
            [$userId]
        )['c'] ?? 0);
    }

    public static function uploadedBooks(int $userId): array
    {
        return Database::fetchAll(
            "SELECT b.*, c.name AS category_name FROM books b LEFT JOIN categories c ON c.id = b.category_id
             WHERE b.uploader_id = ? ORDER BY b.created_at DESC",
            [$userId]
        );
    }

    public static function readingHistory(int $userId, int $limit = 20): array
    {
        return Database::fetchAll(
            "SELECT rh.*, b.title, b.slug, b.cover_image, b.file_type, a.name AS author_name
             FROM reading_history rh JOIN books b ON b.id = rh.book_id
             LEFT JOIN authors a ON a.id = b.author_id
             WHERE rh.user_id = ? ORDER BY rh.last_read_at DESC LIMIT ?",
            [$userId, $limit]
        );
    }

    public static function downloadHistory(int $userId, int $limit = 20): array
    {
        return Database::fetchAll(
            "SELECT d.downloaded_at, b.id, b.title, b.slug, b.cover_image, a.name AS author_name
             FROM book_downloads d JOIN books b ON b.id = d.book_id
             LEFT JOIN authors a ON a.id = b.author_id
             WHERE d.user_id = ? ORDER BY d.downloaded_at DESC LIMIT ?",
            [$userId, $limit]
        );
    }

    public static function toggleFavoriteCategory(int $userId, int $categoryId): bool
    {
        $exists = Database::fetchOne('SELECT id FROM favorite_categories WHERE user_id = ? AND category_id = ?', [$userId, $categoryId]);
        if ($exists) {
            Database::execute('DELETE FROM favorite_categories WHERE user_id = ? AND category_id = ?', [$userId, $categoryId]);
            return false;
        }
        Database::execute('INSERT INTO favorite_categories (user_id, category_id) VALUES (?, ?)', [$userId, $categoryId]);
        return true;
    }

    public static function favoriteCategoryIds(int $userId): array
    {
        return array_map('intval', array_column(
            Database::fetchAll('SELECT category_id FROM favorite_categories WHERE user_id = ?', [$userId]), 'category_id'
        ));
    }

    // ---------------- Admin-side user management ----------------

    public static function listAll(string $filter = '', int $page = 1, int $perPage = 25): array
    {
        $where = [];
        $params = [];
        if ($filter === 'active' || $filter === 'suspended' || $filter === 'banned') {
            $where[] = 'status = ?';
            $params[] = $filter;
        } elseif ($filter === 'admin' || $filter === 'subadmin' || $filter === 'user') {
            $where[] = 'role = ?';
            $params[] = $filter;
        }
        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $offset = max(0, ($page - 1) * $perPage);

        $rows = Database::fetchAll(
            "SELECT u.*, (SELECT COUNT(*) FROM books b WHERE b.uploader_id = u.id) AS upload_count
             FROM users u $whereSql ORDER BY u.created_at DESC LIMIT ? OFFSET ?",
            [...$params, $perPage, $offset]
        );
        $total = Database::fetchOne("SELECT COUNT(*) AS c FROM users $whereSql", $params)['c'] ?? 0;
        return ['rows' => $rows, 'total' => (int) $total, 'pages' => (int) ceil($total / $perPage), 'page' => $page];
    }

    public static function setStatus(int $id, string $status): void
    {
        Database::execute('UPDATE users SET status = ? WHERE id = ?', [$status, $id]);
    }

    public static function delete(int $id): void
    {
        Database::execute('DELETE FROM users WHERE id = ?', [$id]);
    }

    public static function counts(): array
    {
        return Database::fetchOne(
            "SELECT COUNT(*) AS total,
                    SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) AS today,
                    SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) AS this_week,
                    SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) AS this_month
             FROM users WHERE role = 'user'"
        );
    }
}
