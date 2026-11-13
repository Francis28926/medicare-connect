<?php
require __DIR__ . '/../includes/bootstrap.php';
$u = require_role('patient');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    q("UPDATE notifications SET read_at=NOW() WHERE user_id=? AND channel='inapp' AND read_at IS NULL", [$u['id']]);
    redirect('patient/notifications.php');
}
$rows = q_all("SELECT * FROM notifications WHERE user_id=? AND channel='inapp' ORDER BY id DESC LIMIT 100", [$u['id']]);
$page_title = 'Alerts';
require __DIR__ . '/../includes/header.php';
?>
<h1>Alerts &amp; notifications</h1>
<p class="muted">Confirmations and reminders are also sent by email and SMS.</p>
<form method="post"><?= csrf_field() ?><button class="btn sec sm">Mark all as read</button></form><br>
<?php foreach ($rows as $n): ?>
  <div class="card <?= $n['read_at'] ? '' : 'unread' ?>" style="margin-bottom:10px">
    <b><?= e($n['subject']) ?></b> <span class="pill"><?= e($n['kind']) ?></span>
    <p style="margin:.3em 0;font-weight:400"><?= e($n['body']) ?></p>
    <span class="muted small"><?= e(fmt_dt($n['created_at'])) ?></span>
  </div>
<?php endforeach; if (!$rows): ?><p class="muted">No notifications yet.</p><?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
