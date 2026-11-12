<?php
require_once __DIR__ . '/../config/config.php';
Admin::requireAdmin();

$pageTitle = 'Dashboard';
$activeNav = 'dashboard';
include __DIR__ . '/includes/layout-start.php';

$overview = Admin::overview();
$topBooks = Admin::topBooks(8);
$recentLogs = Admin::recentLogs(8);

$chartsData = [
    'traffic'    => Admin::visitorTrafficSeries(30),
    'categories' => ['labels' => array_column(Admin::categoryDistribution(), 'name'), 'values' => array_map('intval', array_column(Admin::categoryDistribution(), 'book_count'))],
];
?>

<div class="stat-cards">
    <div class="stat-card">
        <div class="stat-label"><span class="stat-live-dot"></span> Visitors Online Now</div>
        <div class="stat-value" data-live-visitors><?= $overview['active_now'] ?></div>
        <div class="stat-sub">Live, updates every few seconds</div>
    </div>
    <div class="stat-card">
        <div class="stat-label"><?= icon('book') ?> Total Books</div>
        <div class="stat-value"><?= format_count((int) $overview['books']['total']) ?></div>
        <div class="stat-sub"><?= (int) $overview['books']['pending'] ?> pending approval</div>
    </div>
    <div class="stat-card">
        <div class="stat-label"><?= icon('upload') ?> Books Uploaded Today</div>
        <div class="stat-value"><?= (int) $overview['books']['today'] ?></div>
        <div class="stat-sub"><?= (int) $overview['books']['this_week'] ?> this week</div>
    </div>
    <div class="stat-card">
        <div class="stat-label"><?= icon('users') ?> Total Readers</div>
        <div class="stat-value"><?= format_count((int) $overview['users']['total']) ?></div>
        <div class="stat-sub"><?= (int) $overview['users']['today'] ?> joined today</div>
    </div>
</div>

<div class="admin-grid-2">
    <div class="chart-card">
        <h3>Visitor Traffic (30 days)</h3>
        <canvas id="chart-traffic"></canvas>
    </div>
    <div class="chart-card">
        <h3>Books by Category</h3>
        <canvas id="chart-categories"></canvas>
    </div>
</div>

<div class="admin-grid-2">
    <div class="admin-panel">
        <div class="admin-panel-head"><h3>Most Viewed Books</h3><a href="<?= url('admin/books.php?sort=popular') ?>" class="btn btn-sm btn-outline">Manage Books</a></div>
        <div class="table-responsive">
            <table class="data-table">
                <thead><tr><th>Title</th><th>Author</th><th>Views</th><th>Downloads</th></tr></thead>
                <tbody>
                <?php foreach ($topBooks as $b): ?>
                    <tr>
                        <td><a href="<?= url('admin/book-edit.php?id=' . $b['id']) ?>"><?= e($b['title']) ?></a></td>
                        <td><?= e($b['author_name'] ?? '—') ?></td>
                        <td><?= format_count((int) $b['views_count']) ?></td>
                        <td><?= format_count((int) $b['downloads_count']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$topBooks): ?><tr><td colspan="4" class="text-center text-muted">No books yet.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="admin-panel">
        <div class="admin-panel-head"><h3>Recent Admin Activity</h3><a href="<?= url('admin/logs.php') ?>" class="btn btn-sm btn-outline">View All</a></div>
        <?php if (!$recentLogs): ?><p class="text-muted" style="font-size:.85rem;">No activity logged yet.</p><?php endif; ?>
        <?php foreach ($recentLogs as $log): ?>
            <div style="padding:.6rem 0;border-bottom:1px solid var(--border);font-size:.84rem;">
                <b><?= e($log['admin_username'] ?? 'System') ?></b> <?= e($log['action']) ?>
                <?php if ($log['details']): ?><span class="text-muted"> — <?= e($log['details']) ?></span><?php endif; ?>
                <div class="text-muted" style="font-size:.72rem;"><?= time_ago($log['created_at']) ?></div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php include __DIR__ . '/includes/layout-end.php'; ?>
