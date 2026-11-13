<?php
require __DIR__ . '/../includes/bootstrap.php';
$u = require_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'reminders') {
    $n = run_reminder_job();
    flash('ok', $n ? "$n reminder(s) dispatched (see Outbox)." : 'No appointments due for a reminder right now.');
    redirect('admin/index.php');
}
$s = [
    'Patients' => q_val("SELECT COUNT(*) FROM users WHERE role='patient'"),
    'Doctors' => q_val("SELECT COUNT(*) FROM users WHERE role='doctor'"),
    "Today's appointments" => q_val("SELECT COUNT(*) FROM appointments WHERE appt_date=CURDATE() AND status<>'cancelled'"),
    'Waiting in queue' => q_val("SELECT COUNT(*) FROM appointments WHERE appt_date=CURDATE() AND status='checked_in'"),
    'Revenue (paid)' => money(q_val("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='paid'")),
    'Reminders pending' => q_val("SELECT COUNT(*) FROM appointments WHERE status='booked' AND reminder_sent=0 AND TIMESTAMP(appt_date,appt_time) BETWEEN NOW() AND NOW() + INTERVAL 24 HOUR"),
];
$recent = q_all('SELECT l.*, u.full_name FROM audit_logs l LEFT JOIN users u ON u.id=l.user_id ORDER BY l.id DESC LIMIT 8');
$page_title = 'Admin overview';
require __DIR__ . '/../includes/header.php';
?>
<h1>Super Admin overview</h1>
<div class="grid g3" style="margin-bottom:18px">
<?php foreach ($s as $k => $v): ?><div class="stat"><b><?= e($v) ?></b><span><?= e($k) ?></span></div><?php endforeach; ?>
</div>
<div class="grid g2">
  <section class="card"><h2>Automated reminder dispatch</h2>
    <p class="muted">Sends SMS + email to patients whose consultation starts within 24 hours. In production this runs on a schedule via <code>cron/send_reminders.php</code>.</p>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="reminders"><button class="btn">Run reminder job now</button></form></section>
  <section class="card"><h2>Administration</h2>
    <p><a href="<?= e(url('admin/users.php')) ?>">Manage users &amp; roles (RBAC)</a><br><a href="<?= e(url('admin/reports.php')) ?>">Reports &amp; analytics</a><br>
    <a href="<?= e(url('admin/logs.php')) ?>">Audit log</a><br><a href="<?= e(url('admin/backup.php')) ?>">Encrypted backups</a></p></section>
</div>
<section class="card"><h2>Recent activity</h2>
  <div class="tablewrap" style="border:0;margin:0"><table><thead><tr><th>When</th><th>User</th><th>Action</th><th>Details</th></tr></thead><tbody>
  <?php foreach ($recent as $l): ?><tr><td><?= e(fmt_dt($l['created_at'])) ?></td><td><?= e($l['full_name'] ?? 'system') ?></td><td><?= e($l['action']) ?></td><td><?= e($l['details']) ?></td></tr><?php endforeach; ?>
  </tbody></table></div></section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
