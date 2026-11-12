<?php
/**
 * Wishlist — "read later" list per user.
 */
class Wishlist
{
    public static function has(int $userId, int $bookId): bool
    {
        return Database::fetchOne('SELECT id FROM wishlist WHERE user_id = ? AND book_id = ?', [$userId, $bookId]) !== null;
    }

    /** Returns the new state (true = now on wishlist, false = removed). */
    public static function toggle(int $userId, int $bookId): bool
    {
        if (self::has($userId, $bookId)) {
            Database::execute('DELETE FROM wishlist WHERE user_id = ? AND book_id = ?', [$userId, $bookId]);
            return false;
        }
        Database::execute('INSERT INTO wishlist (user_id, book_id) VALUES (?, ?)', [$userId, $bookId]);
        return true;
    }

    public static function forUser(int $userId): array
    {
        return Database::fetchAll(
            "SELECT b.*, c.name AS category_name, a.name AS author_name
             FROM wishlist w
             JOIN books b ON b.id = w.book_id
             LEFT JOIN categories c ON c.id = b.category_id
             LEFT JOIN authors a ON a.id = b.author_id
             WHERE w.user_id = ? ORDER BY w.created_at DESC",
            [$userId]
        );
    }

    public static function bookIds(int $userId): array
    {
        $rows = Database::fetchAll('SELECT book_id FROM wishlist WHERE user_id = ?', [$userId]);
        return array_column($rows, 'book_id');
    }
}
