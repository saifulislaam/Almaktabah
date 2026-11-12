<?php
/**
 * Review — 5-star ratings + written reviews. One per user per book (editable).
 */
class Review
{
    public static function forBook(int $bookId, string $sort = 'latest'): array
    {
        $orderBy = match ($sort) {
            'highest' => 'r.rating DESC, r.created_at DESC',
            'lowest'  => 'r.rating ASC, r.created_at DESC',
            default   => 'r.created_at DESC',
        };
        return Database::fetchAll(
            "SELECT r.*, u.username, u.display_name, u.profile_image
             FROM reviews r JOIN users u ON u.id = r.user_id
             WHERE r.book_id = ? AND r.status = 'approved'
             ORDER BY $orderBy",
            [$bookId]
        );
    }

    public static function byUser(int $bookId, int $userId): ?array
    {
        return Database::fetchOne('SELECT * FROM reviews WHERE book_id = ? AND user_id = ?', [$bookId, $userId]);
    }

    public static function upsert(int $userId, int $bookId, int $rating, ?string $title, ?string $comment): void
    {
        $rating = max(1, min(5, $rating));
        Database::execute(
            'INSERT INTO reviews (user_id, book_id, rating, title, comment) VALUES (?,?,?,?,?)
             ON DUPLICATE KEY UPDATE rating = VALUES(rating), title = VALUES(title), comment = VALUES(comment), updated_at = NOW()',
            [$userId, $bookId, $rating, $title, $comment]
        );
        Book::recalcRating($bookId);
    }

    public static function delete(int $id): ?int
    {
        $row = Database::fetchOne('SELECT book_id FROM reviews WHERE id = ?', [$id]);
        if ($row) {
            Database::execute('DELETE FROM reviews WHERE id = ?', [$id]);
            Book::recalcRating((int) $row['book_id']);
            return (int) $row['book_id'];
        }
        return null;
    }

    public static function setStatus(int $id, string $status): ?int
    {
        $row = Database::fetchOne('SELECT book_id FROM reviews WHERE id = ?', [$id]);
        if ($row) {
            Database::execute('UPDATE reviews SET status = ? WHERE id = ?', [$status, $id]);
            Book::recalcRating((int) $row['book_id']);
            return (int) $row['book_id'];
        }
        return null;
    }

    public static function recent(int $limit = 30): array
    {
        return Database::fetchAll(
            "SELECT r.*, u.username, b.title AS book_title, b.id AS book_id
             FROM reviews r
             JOIN users u ON u.id = r.user_id
             JOIN books b ON b.id = r.book_id
             ORDER BY r.created_at DESC LIMIT ?",
            [$limit]
        );
    }
}
