<?php
require __DIR__ . '/../includes/bootstrap.php';
$u = require_role('admin');
$ch = get('channel');
$sql = "SELECT n.*, u.full_name, u.email, u.phone FROM notifications n JOIN users u ON u.id=n.user_id WHERE n.channel IN ('email','sms')";
$par = [];
if (in_array($ch, ['email', 'sms'], true)) { $sql .= ' AND n.channel=?'; $par[] = $ch; }
$rows = q_all($sql . ' ORDER BY n.id DESC LIMIT 100', $par);
$page_title = 'Outbox';
require __DIR__ . '/../includes/header.php';
?>
<h1>Email &amp; SMS outbox</h1>
<p class="muted">Messages are simulated locally (SendGrid / Twilio stand-ins). Every message the system would send is recorded here.</p>
<p class="actions"><a class="btn sm sec" href="?">All</a><a class="btn sm sec" href="?channel=email">Email</a><a class="btn sm sec" href="?channel=sms">SMS</a></p>
<div class="tablewrap"><table><thead><tr><th>When</th><th>Channel</th><th>To</th><th>Message</th><th>Status</th></tr></thead><tbody>
<?php foreach ($rows as $n): ?><tr><td><?= e(fmt_dt($n['created_at'])) ?></td><td><span class="pill"><?= e($n['channel']) ?></span> <span class="muted small"><?= e($n['kind']) ?></span></td>
  <td><?= e($n['full_name']) ?><br><span class="muted small"><?= e($n['channel'] === 'sms' ? $n['phone'] : $n['email']) ?></span></td>
  <td><b><?= e($n['subject']) ?></b><br><span class="small"><?= e($n['body']) ?></span></td><td><?= e($n['status']) ?></td></tr>
<?php endforeach; if (!$rows): ?><tr><td colspan="5" class="muted">Nothing sent yet.</td></tr><?php endif; ?></tbody></table></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
