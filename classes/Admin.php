<?php
/**
 * Admin — dashboard statistics, activity logging, sub-admin permissions,
 * and the polling-based "visitors online now" tracker.
 *
 * Note on "real-time": true push (WebSockets) needs a persistent Node/PHP
 * process outside normal Apache/PHP-FPM request handling, which most
 * shared hosting can't run. The heartbeat + polling approach here (see
 * api/track-visitor.php and api/visitor-count.php) is the practical
 * equivalent — a few seconds of latency instead of true push — and works
 * on any standard PHP host.
 */
class Admin
{
    private const ALL_PERMISSIONS = [
        'manage_books'      => 'Manage books (edit, approve, delete)',
        'manage_users'      => 'Manage users (suspend, ban, remove)',
        'manage_categories' => 'Manage categories & authors',
        'manage_reviews'    => 'Moderate reviews',
        'view_analytics'    => 'View analytics',
        'manage_settings'   => 'Change site settings',
    ];

    public static function allPermissions(): array
    {
        return self::ALL_PERMISSIONS;
    }

    public static function requireAdmin(): void
    {
        if (!Auth::check() || !Auth::isAdmin()) {
            redirect('admin/index.php');
        }
    }

    public static function requirePermission(string $permission): void
    {
        self::requireAdmin();
        if (Auth::role() === 'admin') return; // main admin can do everything
        $perms = json_decode($_SESSION['permissions'] ?? '[]', true) ?: [];
        if (!in_array($permission, $perms, true)) {
            http_response_code(403);
            die('You do not have permission to access this section. Contact the main administrator.');
        }
    }

    public static function log(string $action, string $details = ''): void
    {
        Database::insert(
            'INSERT INTO admin_logs (admin_id, action, details, ip_address) VALUES (?, ?, ?, ?)',
            [Auth::id(), $action, $details, Auth::clientIp()]
        );
    }

    public static function recentLogs(int $limit = 50): array
    {
        return Database::fetchAll(
            "SELECT al.*, u.username AS admin_username FROM admin_logs al
             LEFT JOIN users u ON u.id = al.admin_id
             ORDER BY al.created_at DESC LIMIT ?",
            [$limit]
        );
    }

    // -----------------------------------------------------------------
    // Dashboard stat cards + charts
    // -----------------------------------------------------------------
    public static function overview(): array
    {
        $books = Database::fetchOne(
            "SELECT COUNT(*) AS total,
                    SUM(CASE WHEN status='pending' THEN 1 ELSE 0 END) AS pending,
                    SUM(CASE WHEN DATE(created_at)=CURDATE() THEN 1 ELSE 0 END) AS today,
                    SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) AS this_week,
                    SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) AS this_month
             FROM books"
        );
        $users = User::counts();
        $downloads = Database::fetchOne("SELECT COUNT(*) AS total FROM book_downloads WHERE downloaded_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $activeNow = self::activeVisitorCount();

        return [
            'books' => $books,
            'users' => $users,
            'downloads_30d' => (int) ($downloads['total'] ?? 0),
            'active_now' => $activeNow,
        ];
    }

    public static function visitorTrafficSeries(int $days = 30): array
    {
        $rows = Database::fetchAll(
            "SELECT DATE(viewed_at) AS d, COUNT(DISTINCT ip_address) AS visitors
             FROM book_views WHERE viewed_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
             GROUP BY DATE(viewed_at) ORDER BY d ASC",
            [$days]
        );
        return self::fillDateSeries($rows, $days, 'visitors');
    }

    public static function uploadTrendSeries(int $days = 30): array
    {
        $rows = Database::fetchAll(
            "SELECT DATE(created_at) AS d, COUNT(*) AS uploads
             FROM books WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
             GROUP BY DATE(created_at) ORDER BY d ASC",
            [$days]
        );
        return self::fillDateSeries($rows, $days, 'uploads');
    }

    public static function userGrowthSeries(int $days = 30): array
    {
        $rows = Database::fetchAll(
            "SELECT DATE(created_at) AS d, COUNT(*) AS new_users
             FROM users WHERE role='user' AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
             GROUP BY DATE(created_at) ORDER BY d ASC",
            [$days]
        );
        return self::fillDateSeries($rows, $days, 'new_users');
    }

    private static function fillDateSeries(array $rows, int $days, string $valueKey): array
    {
        $byDate = [];
        foreach ($rows as $r) $byDate[$r['d']] = (int) $r[$valueKey];
        $labels = [];
        $values = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime("-$i days"));
            $labels[] = date('M j', strtotime($d));
            $values[] = $byDate[$d] ?? 0;
        }
        return ['labels' => $labels, 'values' => $values];
    }

    public static function categoryDistribution(): array
    {
        return Database::fetchAll(
            "SELECT c.name, COUNT(b.id) AS book_count
             FROM categories c LEFT JOIN books b ON b.category_id = c.id AND b.status='approved'
             GROUP BY c.id HAVING book_count > 0 ORDER BY book_count DESC"
        );
    }

    public static function topBooks(int $limit = 10): array
    {
        return Database::fetchAll(
            "SELECT b.id, b.title, b.views_count, b.downloads_count, a.name AS author_name
             FROM books b LEFT JOIN authors a ON a.id = b.author_id
             WHERE b.status='approved' ORDER BY b.views_count DESC LIMIT ?",
            [$limit]
        );
    }

    // -----------------------------------------------------------------
    // Visitor heartbeat (see api/track-visitor.php + api/visitor-count.php)
    // -----------------------------------------------------------------
    public static function recordHeartbeat(string $sessionToken, ?int $userId, string $ip, string $page, string $userAgent): void
    {
        Database::execute(
            'INSERT INTO visitor_sessions (session_token, user_id, ip_address, current_page, user_agent)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE current_page = VALUES(current_page), last_seen = NOW()',
            [$sessionToken, $userId, $ip, $page, substr($userAgent, 0, 255)]
        );
    }

    public static function activeVisitorCount(): int
    {
        return (int) (Database::fetchOne(
            'SELECT COUNT(*) AS c FROM visitor_sessions WHERE last_seen >= DATE_SUB(NOW(), INTERVAL ? SECOND)',
            [VISITOR_ACTIVE_WINDOW_SECONDS]
        )['c'] ?? 0);
    }
}
