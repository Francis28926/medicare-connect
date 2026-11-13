<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/backup.php';
$u = require_role('admin');

if (get('download') !== '') {
    $f = q_one('SELECT * FROM backups WHERE filename=?', [get('download')]);
    $path = __DIR__ . '/../storage/backups/' . basename(get('download'));
    if ($f && is_file($path)) {
        audit('backup.download', $f['filename']);
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $f['filename'] . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path); exit;
    }
    flash('error', 'Backup file not found.'); redirect('admin/backup.php');
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = create_backup($u['id']);
    flash('ok', "Encrypted backup created: $name");
    redirect('admin/backup.php');
}
$rows = q_all('SELECT b.*, u.full_name FROM backups b LEFT JOIN users u ON u.id=b.created_by ORDER BY b.id DESC LIMIT 50');
$page_title = 'Backups';
require __DIR__ . '/../includes/header.php';
?>
<h1>Encrypted database backups</h1>
<div class="card">
  <p>Backups are encrypted with <b>AES-256-GCM</b> before being written to disk (<code>storage/backups/</code>). Schedule <code>cron/backup.php</code> daily with Windows Task Scheduler for automatic backups.</p>
  <form method="post"><?= csrf_field() ?><button class="btn">Create backup now</button></form>
</div>
<div class="tablewrap"><table><thead><tr><th>File</th><th>Created</th><th>By</th><th>Size</th><th></th></tr></thead><tbody>
<?php foreach ($rows as $b): ?><tr><td><?= e($b['filename']) ?></td><td><?= e(fmt_dt($b['created_at'])) ?></td><td><?= e($b['full_name'] ?? 'scheduled job') ?></td><td><?= e(number_format($b['size_bytes'] / 1024, 1)) ?> KB</td>
  <td><a class="btn sm sec" href="?download=<?= e(urlencode($b['filename'])) ?>">Download</a></td></tr>
<?php endforeach; if (!$rows): ?><tr><td colspan="5" class="muted">No backups yet.</td></tr><?php endif; ?></tbody></table></div>
<p class="muted small">To restore: <code>php cron/decrypt_backup.php storage/backups/FILE.sql.enc &gt; restore.sql</code> then import restore.sql in phpMyAdmin.</p>
<?php require __DIR__ . '/../includes/footer.php'; ?>
