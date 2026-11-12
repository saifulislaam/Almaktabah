<?php
/**
 * Author — scholar/writer records. Auto-created when an uploader types
 * a name that doesn't exist yet.
 */
class Author
{
    public static function find(int $id): ?array
    {
        return Database::fetchOne('SELECT * FROM authors WHERE id = ?', [$id]);
    }

    public static function findBySlug(string $slug): ?array
    {
        return Database::fetchOne('SELECT * FROM authors WHERE slug = ?', [$slug]);
    }

    public static function findByName(string $name): ?array
    {
        return Database::fetchOne('SELECT * FROM authors WHERE name = ?', [$name]);
    }

    public static function all(int $limit = 200, int $offset = 0): array
    {
        return Database::fetchAll(
            'SELECT a.*, COUNT(b.id) AS book_count
             FROM authors a
             LEFT JOIN books b ON b.author_id = a.id AND b.status = "approved"
             GROUP BY a.id ORDER BY a.name ASC LIMIT ? OFFSET ?',
            [$limit, $offset]
        );
    }

    public static function count(): int
    {
        return (int) (Database::fetchOne('SELECT COUNT(*) AS c FROM authors')['c'] ?? 0);
    }

    /** Get an existing author id by name, or create one on the fly during upload. */
    public static function findOrCreate(string $name): int
    {
        $name = trim($name);
        $existing = self::findByName($name);
        if ($existing) {
            return (int) $existing['id'];
        }
        $slug = make_slug($name);
        $base = $slug;
        $i = 1;
        while (self::findBySlug($slug)) {
            $slug = $base . '-' . (++$i);
        }
        return Database::insert('INSERT INTO authors (name, slug) VALUES (?, ?)', [$name, $slug]);
    }

    public static function update(int $id, string $name, ?string $bio, ?string $era = null): void
    {
        Database::execute('UPDATE authors SET name = ?, bio = ?, era = ? WHERE id = ?', [$name, $bio, $era, $id]);
    }

    public static function updateImage(int $id, string $filename): void
    {
        Database::execute('UPDATE authors SET image = ? WHERE id = ?', [$filename, $id]);
    }

    public static function delete(int $id): void
    {
        Database::execute('DELETE FROM authors WHERE id = ?', [$id]);
    }

    /** Simple name search used by the upload form's autocomplete. */
    public static function search(string $term, int $limit = 10): array
    {
        return Database::fetchAll(
            'SELECT id, name FROM authors WHERE name LIKE ? ORDER BY name ASC LIMIT ?',
            ['%' . $term . '%', $limit]
        );
    }
}
