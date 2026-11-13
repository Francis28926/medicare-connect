<?php
require __DIR__ . '/../includes/bootstrap.php';
$u = require_role('admin');
$action = get('action'); $page = max(1, (int)get('page', 1)); $per = 25;
$where = ' WHERE 1=1'; $par = [];
if ($action !== '') { $where .= ' AND l.action LIKE ?'; $par[] = "$action%"; }
$total = (int)q_val('SELECT COUNT(*) FROM audit_logs l' . $where, $par);
$rows = q_all('SELECT l.*, u.full_name, u.role FROM audit_logs l LEFT JOIN users u ON u.id=l.user_id' . $where . ' ORDER BY l.id DESC LIMIT ' . $per . ' OFFSET ' . (($page - 1) * $per), $par);
$pages = max(1, (int)ceil($total / $per));
$page_title = 'Audit log';
require __DIR__ . '/../includes/header.php';
?>
<h1>Audit log</h1>
<form method="get" class="card row"><div><label for="action">Action starts with</label><input id="action" name="action" value="<?= e($action) ?>" placeholder="login, records, rbac..."></div><div style="align-self:end"><button class="btn">Filter</button></div></form>
<div class="tablewrap"><table><thead><tr><th>When</th><th>User</th><th>Action</th><th>Details</th><th>IP</th></tr></thead><tbody>
<?php foreach ($rows as $l): ?><tr><td><?= e(fmt_dt($l['created_at'])) ?></td><td><?= e($l['full_name'] ? $l['full_name'] . ' (' . $l['role'] . ')' : 'system / anonymous') ?></td><td><?= e($l['action']) ?></td><td><?= e($l['details']) ?></td><td><?= e($l['ip']) ?></td></tr><?php endforeach; ?>
</tbody></table></div>
<p class="actions"><?php if ($page > 1): ?><a class="btn sm sec" href="?<?= e(http_build_query(['action' => $action, 'page' => $page - 1])) ?>">Previous</a><?php endif; ?>
 <span class="muted">Page <?= $page ?> of <?= $pages ?> (<?= $total ?> entries)</span>
 <?php if ($page < $pages): ?><a class="btn sm sec" href="?<?= e(http_build_query(['action' => $action, 'page' => $page + 1])) ?>">Next</a><?php endif; ?></p>
<?php require __DIR__ . '/../includes/footer.php'; ?>
