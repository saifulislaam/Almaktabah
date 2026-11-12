<?php
/**
 * Notification — lightweight in-app notifications (bell icon in the header).
 */
class Notification
{
    public static function send(int $userId, string $type, string $message, ?string $link = null): void
    {
        Database::insert('INSERT INTO notifications (user_id, type, message, link) VALUES (?,?,?,?)', [$userId, $type, $message, $link]);
    }

    public static function forUser(int $userId, int $limit = 20): array
    {
        return Database::fetchAll('SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ?', [$userId, $limit]);
    }

    public static function unreadCount(int $userId): int
    {
        return (int) (Database::fetchOne('SELECT COUNT(*) AS c FROM notifications WHERE user_id = ? AND is_read = 0', [$userId])['c'] ?? 0);
    }

    public static function markAllRead(int $userId): void
    {
        Database::execute('UPDATE notifications SET is_read = 1 WHERE user_id = ?', [$userId]);
    }

    /** Notify everyone following a category when a new approved book lands in it. */
    public static function notifyCategoryFollowers(int $categoryId, string $bookTitle, int $bookId): void
    {
        $followers = Database::fetchAll('SELECT user_id FROM favorite_categories WHERE category_id = ?', [$categoryId]);
        foreach ($followers as $f) {
            self::send((int) $f['user_id'], 'new_book', "A new book was added in a category you follow: \"$bookTitle\"", 'book.php?id=' . $bookId);
        }
    }
}
