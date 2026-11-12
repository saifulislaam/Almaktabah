<?php
require_once __DIR__ . '/../config/config.php';
Admin::requireAdmin();
Admin::requirePermission('view_analytics');

if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="almaktabah-top-books.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Title', 'Author', 'Category', 'Views', 'Downloads', 'Avg Rating', 'Uploaded']);
    foreach (Admin::topBooks(200) as $b) {
        fputcsv($out, [$b['title'], $b['author_name'], '', $b['views_count'], $b['downloads_count'], '', '']);
    }
    fclose($out);
    exit;
}

$pageTitle = 'Analytics';
$activeNav = 'analytics';
include __DIR__ . '/includes/layout-start.php';

$overview = Admin::overview();
$chartsData = [
    'traffic'    => Admin::visitorTrafficSeries(30),
    'uploads'    => Admin::uploadTrendSeries(30),
    'growth'     => Admin::userGrowthSeries(30),
    'categories' => ['labels' => array_column(Admin::categoryDistribution(), 'name'), 'values' => array_map('intval', array_column(Admin::categoryDistribution(), 'book_count'))],
];
$topBooks = Admin::topBooks(15);
?>

<div class="stat-cards">
    <div class="stat-card"><div class="stat-label">Downloads (30 days)</div><div class="stat-value"><?= format_count((int) $overview['downloads_30d']) ?></div></div>
    <div class="stat-card"><div class="stat-label">New Readers (30 days)</div><div class="stat-value"><?= (int) $overview['users']['this_month'] ?></div></div>
    <div class="stat-card"><div class="stat-label">Books Uploaded (30 days)</div><div class="stat-value"><?= (int) $overview['books']['this_month'] ?></div></div>
    <div class="stat-card"><div class="stat-label">Pending Approval</div><div class="stat-value"><?= (int) $overview['books']['pending'] ?></div></div>
</div>

<div class="admin-grid-2">
    <div class="chart-card"><h3>Visitor Traffic</h3><canvas id="chart-traffic"></canvas></div>
    <div class="chart-card"><h3>Books by Category</h3><canvas id="chart-categories"></canvas></div>
</div>
<div class="admin-grid-2">
    <div class="chart-card"><h3>Upload Trend</h3><canvas id="chart-uploads"></canvas></div>
    <div class="chart-card"><h3>Reader Growth</h3><canvas id="chart-growth"></canvas></div>
</div>

<div class="admin-panel">
    <div class="admin-panel-head"><h3>Top Books</h3><a href="?export=csv" class="btn btn-sm btn-outline"><?= icon('download') ?> Export CSV</a></div>
    <div class="table-responsive">
        <table class="data-table">
            <thead><tr><th>#</th><th>Title</th><th>Author</th><th>Views</th><th>Downloads</th></tr></thead>
            <tbody>
            <?php foreach ($topBooks as $i => $b): ?>
                <tr><td><?= $i + 1 ?></td><td><?= e($b['title']) ?></td><td><?= e($b['author_name'] ?? '—') ?></td><td><?= format_count((int) $b['views_count']) ?></td><td><?= format_count((int) $b['downloads_count']) ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/includes/layout-end.php'; ?>
