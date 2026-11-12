<?php
require_once __DIR__ . '/../config/config.php';
Admin::requireAdmin();
if (Auth::role() !== 'admin') { http_response_code(403); die('Only the main administrator can view activity logs.'); }

$pageTitle = 'Activity Logs';
$activeNav = 'logs';
include __DIR__ . '/includes/layout-start.php';

$logs = Admin::recentLogs(200);
?>

<div class="admin-panel">
    <div class="admin-panel-head"><h3>Recent Activity</h3></div>
    <div class="table-responsive">
        <table class="data-table">
            <thead><tr><th>Admin</th><th>Action</th><th>Details</th><th>IP</th><th>When</th></tr></thead>
            <tbody>
            <?php foreach ($logs as $log): ?>
                <tr>
                    <td><?= e($log['admin_username'] ?? 'System') ?></td>
                    <td><?= e(str_replace('_', ' ', $log['action'])) ?></td>
                    <td class="text-muted"><?= e($log['details'] ?? '') ?></td>
                    <td class="text-muted"><?= e($log['ip_address'] ?? '') ?></td>
                    <td><?= time_ago($log['created_at']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$logs): ?><tr><td colspan="5" class="text-center text-muted" style="padding:2rem;">No activity yet.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/includes/layout-end.php'; ?>
