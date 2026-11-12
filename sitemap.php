<?php
require_once __DIR__ . '/config/config.php';
header('Content-Type: application/xml; charset=utf-8');

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <url><loc><?= e(url('index.php')) ?></loc><changefreq>daily</changefreq><priority>1.0</priority></url>
    <url><loc><?= e(url('categories.php')) ?></loc><changefreq>weekly</changefreq><priority>0.8</priority></url>
    <url><loc><?= e(url('authors.php')) ?></loc><changefreq>weekly</changefreq><priority>0.7</priority></url>
    <url><loc><?= e(url('search.php')) ?></loc><changefreq>daily</changefreq><priority>0.6</priority></url>

    <?php foreach (Category::all() as $cat): ?>
    <url><loc><?= e(url('category.php?id=' . $cat['id'] . '&slug=' . $cat['slug'])) ?></loc><changefreq>weekly</changefreq><priority>0.8</priority></url>
    <?php endforeach; ?>

    <?php foreach (Author::all(500) as $a): ?>
    <url><loc><?= e(url('author.php?id=' . $a['id'] . '&slug=' . $a['slug'])) ?></loc><changefreq>monthly</changefreq><priority>0.7</priority></url>
    <?php endforeach; ?>

    <?php
    $page = 1;
    do {
        $result = Book::listFiltered(['status' => 'approved'], 'newest', $page, 500);
        foreach ($result['rows'] as $b): ?>
    <url>
        <loc><?= e(url('book.php?id=' . $b['id'] . '&slug=' . $b['slug'])) ?></loc>
        <lastmod><?= date('Y-m-d', strtotime($b['updated_at'])) ?></lastmod>
        <changefreq>monthly</changefreq>
        <priority>0.9</priority>
    </url>
    <?php endforeach;
        $page++;
    } while ($page <= $result['pages']);
    ?>
</urlset>
