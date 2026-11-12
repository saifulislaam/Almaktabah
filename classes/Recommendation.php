<?php
/**
 * Recommendation — a hybrid, multi-signal recommender for the homepage
 * and book-detail "similar books" rail.
 *
 * Honest note on scope: the brief described a YouTube-style two-stage
 * neural architecture (embeddings, learned ranking, reinforcement
 * learning, an LLM-driven "self-evolving" agent). That stack is real
 * infrastructure — GPU training, an offline model registry, a Python/TF
 * serving layer — and faking it inside plain PHP would just be
 * decoration, not a working system. What this class implements instead
 * is the practical equivalent, built from real, inspectable signals:
 *
 *   - Content-based filtering : matches on category + author, weighted
 *                                by how much the user has engaged with each
 *   - Item-based collaborative
 *     filtering                : "readers who read what you read also
 *                                 read this" via co-occurrence in
 *                                 reading_history, normalized 0-1
 *   - Popularity / trending    : recent views + downloads, log-scaled
 *                                so one viral title doesn't dominate
 *   - Recency                  : exponential decay so new uploads get a
 *                                fair chance against older, more-viewed ones
 *   - Quality                  : average star rating
 *
 * Every candidate gets a weighted composite score from these signals and
 * the list is sorted by it. If you outgrow this (large catalog, need for
 * true embeddings), the natural next step is a small Python microservice
 * called from PHP — the schema here (reading_history, book_views,
 * book_downloads) already has what that service would need to train on.
 */
class Recommendation
{
    private const WEIGHT_CATEGORY          = 3.0;
    private const WEIGHT_AUTHOR             = 2.5;
    private const WEIGHT_FAVORITE_CATEGORY  = 2.0;
    private const WEIGHT_COLLABORATIVE      = 2.5;
    private const WEIGHT_RATING             = 0.4;
    private const WEIGHT_POPULARITY         = 0.3;
    private const WEIGHT_RECENCY            = 1.0;

    /** Personalized homepage feed. Falls back to cold-start for guests/new users. */
    public static function forUser(?int $userId, int $limit = 12): array
    {
        if (!$userId) {
            return self::coldStart($limit);
        }

        $engagement = self::userEngagement($userId);
        if (empty($engagement['book_ids']) && empty($engagement['category_weights']) && empty($engagement['favorite_categories'])) {
            return self::coldStart($limit);
        }

        $excludeIds = $engagement['book_ids'];
        $excludeIds[] = 0;

        $candidates = self::gatherCandidates($engagement, $excludeIds, $limit * 6);
        if (!$candidates) {
            return self::coldStart($limit);
        }

        $collabScores = self::collaborativeScores($userId, $engagement['book_ids'], array_column($candidates, 'id'));
        $now = time();

        foreach ($candidates as &$book) {
            $score = 0.0;
            if (!empty($book['category_id']) && isset($engagement['category_weights'][$book['category_id']])) {
                $score += self::WEIGHT_CATEGORY * min(3, $engagement['category_weights'][$book['category_id']]);
            }
            if (!empty($book['author_id']) && isset($engagement['author_weights'][$book['author_id']])) {
                $score += self::WEIGHT_AUTHOR * min(3, $engagement['author_weights'][$book['author_id']]);
            }
            if (!empty($book['category_id']) && in_array((int) $book['category_id'], $engagement['favorite_categories'], true)) {
                $score += self::WEIGHT_FAVORITE_CATEGORY;
            }
            $score += self::WEIGHT_COLLABORATIVE * ($collabScores[$book['id']] ?? 0);
            $score += self::WEIGHT_RATING * (float) $book['avg_rating'];
            $score += self::WEIGHT_POPULARITY * log(1 + (int) $book['views_count'] + (int) $book['downloads_count']);
            $ageDays = max(0, ($now - strtotime($book['created_at'])) / 86400);
            $score += self::WEIGHT_RECENCY * exp(-$ageDays / 30);
            $book['_score'] = $score;
            $book['_reason'] = self::explain($book, $engagement, $collabScores);
        }
        unset($book);

        usort($candidates, fn($a, $b) => $b['_score'] <=> $a['_score']);
        return self::hydrate(array_slice($candidates, 0, $limit));
    }

    /** "More like this" rail on a book's own page — pure content-based match. */
    public static function similarBooks(int $bookId, int $limit = 6): array
    {
        $book = Book::find($bookId);
        if (!$book) return [];

        $rows = Database::fetchAll(
            "SELECT b.*, c.name AS category_name, a.name AS author_name,
                    (CASE WHEN b.author_id = ? AND b.author_id IS NOT NULL THEN 2 ELSE 0 END +
                     CASE WHEN b.category_id = ? AND b.category_id IS NOT NULL THEN 1 ELSE 0 END) AS match_score
             FROM books b
             LEFT JOIN categories c ON c.id = b.category_id
             LEFT JOIN authors a ON a.id = b.author_id
             WHERE b.status = 'approved' AND b.id != ?
               AND ((b.author_id = ? AND b.author_id IS NOT NULL) OR (b.category_id = ? AND b.category_id IS NOT NULL))
             ORDER BY match_score DESC, b.avg_rating DESC, b.views_count DESC
             LIMIT ?",
            [$book['author_id'], $book['category_id'], $bookId, $book['author_id'], $book['category_id'], $limit]
        );

        if (count($rows) < $limit) {
            $haveIds = array_column($rows, 'id');
            $haveIds[] = $bookId;
            $placeholders = implode(',', array_fill(0, count($haveIds), '?'));
            $more = Database::fetchAll(
                "SELECT b.*, c.name AS category_name, a.name AS author_name
                 FROM books b
                 LEFT JOIN categories c ON c.id = b.category_id
                 LEFT JOIN authors a ON a.id = b.author_id
                 WHERE b.status = 'approved' AND b.id NOT IN ($placeholders)
                 ORDER BY b.views_count DESC LIMIT ?",
                [...$haveIds, $limit - count($rows)]
            );
            $rows = array_merge($rows, $more);
        }
        return $rows;
    }

    public static function coldStart(int $limit = 12): array
    {
        return Database::fetchAll(
            "SELECT b.*, c.name AS category_name, a.name AS author_name
             FROM books b
             LEFT JOIN categories c ON c.id = b.category_id
             LEFT JOIN authors a ON a.id = b.author_id
             WHERE b.status = 'approved'
             ORDER BY (b.views_count + b.downloads_count * 2 + b.featured * 50) DESC, b.created_at DESC
             LIMIT ?",
            [$limit]
        );
    }

    // -----------------------------------------------------------------
    // Internals
    // -----------------------------------------------------------------

    private static function userEngagement(int $userId): array
    {
        $rows = Database::fetchAll(
            "SELECT b.id, b.category_id, b.author_id FROM reading_history rh JOIN books b ON b.id = rh.book_id WHERE rh.user_id = ?
             UNION
             SELECT b.id, b.category_id, b.author_id FROM wishlist w JOIN books b ON b.id = w.book_id WHERE w.user_id = ?
             UNION
             SELECT b.id, b.category_id, b.author_id FROM book_downloads d JOIN books b ON b.id = d.book_id WHERE d.user_id = ?",
            [$userId, $userId, $userId]
        );

        $bookIds = [];
        $catWeights = [];
        $authWeights = [];
        foreach ($rows as $r) {
            $bookIds[] = (int) $r['id'];
            if ($r['category_id']) $catWeights[$r['category_id']] = ($catWeights[$r['category_id']] ?? 0) + 1;
            if ($r['author_id'])   $authWeights[$r['author_id']] = ($authWeights[$r['author_id']] ?? 0) + 1;
        }
        $favCats = array_map('intval', array_column(
            Database::fetchAll('SELECT category_id FROM favorite_categories WHERE user_id = ?', [$userId]), 'category_id'
        ));

        return [
            'book_ids'            => array_values(array_unique($bookIds)),
            'category_weights'    => $catWeights,
            'author_weights'      => $authWeights,
            'favorite_categories' => $favCats,
        ];
    }

    private static function gatherCandidates(array $engagement, array $excludeIds, int $poolSize): array
    {
        $excludePh = implode(',', array_fill(0, count($excludeIds), '?'));
        $pool = [];

        $catIds = array_unique(array_merge(array_keys($engagement['category_weights']), $engagement['favorite_categories']));
        if ($catIds) {
            $catPh = implode(',', array_fill(0, count($catIds), '?'));
            $rows = Database::fetchAll(
                "SELECT id, category_id, author_id, avg_rating, views_count, downloads_count, created_at
                 FROM books WHERE status='approved' AND category_id IN ($catPh) AND id NOT IN ($excludePh)
                 ORDER BY created_at DESC LIMIT ?",
                [...$catIds, ...$excludeIds, $poolSize]
            );
            foreach ($rows as $r) $pool[$r['id']] = $r;
        }

        $authIds = array_keys($engagement['author_weights']);
        if ($authIds) {
            $authPh = implode(',', array_fill(0, count($authIds), '?'));
            $rows = Database::fetchAll(
                "SELECT id, category_id, author_id, avg_rating, views_count, downloads_count, created_at
                 FROM books WHERE status='approved' AND author_id IN ($authPh) AND id NOT IN ($excludePh)
                 LIMIT ?",
                [...$authIds, ...$excludeIds, $poolSize]
            );
            foreach ($rows as $r) $pool[$r['id']] = $r;
        }

        if (count($pool) < $poolSize) {
            $rows = Database::fetchAll(
                "SELECT id, category_id, author_id, avg_rating, views_count, downloads_count, created_at
                 FROM books WHERE status='approved' AND id NOT IN ($excludePh)
                 ORDER BY (views_count + downloads_count * 2) DESC LIMIT ?",
                [...$excludeIds, $poolSize - count($pool)]
            );
            foreach ($rows as $r) $pool[$r['id']] = $r;
        }

        return array_values($pool);
    }

    /** Item-based collaborative filtering: co-occurrence of candidate books with the user's read books, across other users. */
    private static function collaborativeScores(int $userId, array $userBookIds, array $candidateIds): array
    {
        if (!$userBookIds || !$candidateIds) return [];
        $bookPh = implode(',', array_fill(0, count($userBookIds), '?'));
        $candPh = implode(',', array_fill(0, count($candidateIds), '?'));

        $rows = Database::fetchAll(
            "SELECT rh2.book_id, COUNT(DISTINCT rh2.user_id) AS cnt
             FROM reading_history rh1
             JOIN reading_history rh2 ON rh2.user_id = rh1.user_id AND rh2.user_id != ?
             WHERE rh1.book_id IN ($bookPh) AND rh2.book_id IN ($candPh)
             GROUP BY rh2.book_id",
            [$userId, ...$userBookIds, ...$candidateIds]
        );

        $scores = [];
        $max = 1;
        foreach ($rows as $r) {
            $scores[(int) $r['book_id']] = (int) $r['cnt'];
            $max = max($max, (int) $r['cnt']);
        }
        foreach ($scores as $k => $v) {
            $scores[$k] = $v / $max;
        }
        return $scores;
    }

    /** Short human-readable reason shown under a recommendation card, e.g. "Because you read Al-Bidayah wan-Nihayah". */
    private static function explain(array $book, array $engagement, array $collabScores): string
    {
        if (!empty($collabScores[$book['id']])) return 'Readers with similar taste enjoyed this';
        if (!empty($book['category_id']) && isset($engagement['category_weights'][$book['category_id']])) return 'Because of your recent reading';
        if (!empty($book['author_id']) && isset($engagement['author_weights'][$book['author_id']])) return 'More from an author you read';
        if (!empty($book['category_id']) && in_array((int) $book['category_id'], $engagement['favorite_categories'], true)) return 'From a category you follow';
        return 'Trending on AlMaktabah';
    }

    /** Re-fetch full rows (with category/author names) for the final sorted id list, preserving order and the _reason. */
    private static function hydrate(array $scored): array
    {
        if (!$scored) return [];
        $ids = array_column($scored, 'id');
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $full = Database::fetchAll(
            "SELECT b.*, c.name AS category_name, a.name AS author_name
             FROM books b
             LEFT JOIN categories c ON c.id = b.category_id
             LEFT JOIN authors a ON a.id = b.author_id
             WHERE b.id IN ($placeholders)",
            $ids
        );
        $byId = [];
        foreach ($full as $row) $byId[$row['id']] = $row;

        $result = [];
        foreach ($scored as $s) {
            if (isset($byId[$s['id']])) {
                $row = $byId[$s['id']];
                $row['_reason'] = $s['_reason'];
                $result[] = $row;
            }
        }
        return $result;
    }
}
