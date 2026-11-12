<?php
/**
 * Category — Islamic subject taxonomy, with optional sub-categories.
 */
class Category
{
    public static function all(): array
    {
        return Database::fetchAll('SELECT * FROM categories ORDER BY sort_order ASC, name ASC');
    }

    public static function topLevel(): array
    {
        return Database::fetchAll('SELECT * FROM categories WHERE parent_id IS NULL ORDER BY sort_order ASC, name ASC');
    }

    public static function find(int $id): ?array
    {
        return Database::fetchOne('SELECT * FROM categories WHERE id = ?', [$id]);
    }

    public static function findBySlug(string $slug): ?array
    {
        return Database::fetchOne('SELECT * FROM categories WHERE slug = ?', [$slug]);
    }

    public static function children(int $parentId): array
    {
        return Database::fetchAll('SELECT * FROM categories WHERE parent_id = ? ORDER BY name ASC', [$parentId]);
    }

    /** Approved book count per category, for badges/listings. */
    public static function withBookCounts(): array
    {
        return Database::fetchAll(
            "SELECT c.*, COUNT(b.id) AS book_count
             FROM categories c
             LEFT JOIN books b ON b.category_id = c.id AND b.status = 'approved'
             GROUP BY c.id
             ORDER BY c.sort_order ASC, c.name ASC"
        );
    }

    public static function create(string $name, ?string $description, string $icon, ?int $parentId = null): int
    {
        $slug = self::uniqueSlug(make_slug($name));
        return Database::insert(
            'INSERT INTO categories (parent_id, name, slug, description, icon) VALUES (?, ?, ?, ?, ?)',
            [$parentId ?: null, $name, $slug, $description, $icon]
        );
    }

    public static function update(int $id, string $name, ?string $description, string $icon, ?int $parentId = null): void
    {
        Database::execute(
            'UPDATE categories SET name = ?, description = ?, icon = ?, parent_id = ? WHERE id = ?',
            [$name, $description, $icon, $parentId ?: null, $id]
        );
    }

    public static function delete(int $id): void
    {
        Database::execute('DELETE FROM categories WHERE id = ?', [$id]);
    }

    private static function uniqueSlug(string $base): string
    {
        $slug = $base;
        $i = 1;
        while (self::findBySlug($slug)) {
            $slug = $base . '-' . (++$i);
        }
        return $slug;
    }
}
