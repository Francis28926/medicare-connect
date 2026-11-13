<?php
require __DIR__ . '/../includes/bootstrap.php';
$u = require_role('staff', 'admin');
staff_handle_action();

$rows = q_all("SELECT a.*, pu.full_name patient_name, du.full_name doctor_name, d.room, d.id did
    FROM appointments a JOIN patients p ON p.id=a.patient_id JOIN users pu ON pu.id=p.user_id
    JOIN doctors d ON d.id=a.doctor_id JOIN users du ON du.id=d.user_id
    WHERE a.appt_date=CURDATE() AND a.status<>'cancelled' ORDER BY d.id, FIELD(a.status,'in_consultation','checked_in','booked','completed','no_show'), a.queue_number, a.appt_time");
$by = [];
foreach ($rows as $r) $by[$r['doctor_name'] . ' (' . $r['room'] . ')'][] = $r;
$cnt = array_count_values(array_column($rows, 'status'));
$page_title = 'Live queue';
require __DIR__ . '/../includes/header.php';
?>
<h1>Today's queue <span class="muted" style="font-size:1rem"><?= e(fmt_date(date('Y-m-d'))) ?></span></h1>
<div data-live="board" aria-live="polite">
  <div class="grid g4" style="margin-bottom:18px">
    <div class="stat"><b><?= (int)($cnt['booked'] ?? 0) ?></b><span>Expected</span></div>
    <div class="stat"><b><?= (int)($cnt['checked_in'] ?? 0) ?></b><span>Waiting</span></div>
    <div class="stat"><b><?= (int)($cnt['in_consultation'] ?? 0) ?></b><span>In consultation</span></div>
    <div class="stat"><b><?= (int)($cnt['completed'] ?? 0) ?></b><span>Completed</span></div>
  </div>
  <?php foreach ($by as $doc => $list): ?>
  <h2><?= e($doc) ?></h2>
  <div class="tablewrap"><table>
    <thead><tr><th>Queue #</th><th>Time</th><th>Patient</th><th>Reason</th><th>Status</th><th>Action</th></tr></thead><tbody>
    <?php foreach ($list as $r): ?>
      <tr><td><?= $r['queue_number'] ? '#' . (int)$r['queue_number'] : '-' ?></td><td><?= e(fmt_time($r['appt_time'])) ?></td>
        <td><?= e($r['patient_name']) ?></td><td><?= e($r['reason']) ?></td><td><?= status_badge($r['status']) ?></td>
        <td class="actions">
        <?php if ($r['status'] === 'booked'): ?>
          <?= action_form((int)$r['id'], 'checkin', 'Check in') ?>
          <?= action_form((int)$r['id'], 'noshow', 'No-show', 'danger', 'Mark this patient as a no-show?') ?>
        <?php elseif ($r['status'] === 'checked_in'): ?>
          <?= action_form((int)$r['id'], 'noshow', 'No-show', 'danger', 'Mark this patient as a no-show?') ?>
        <?php endif; ?></td></tr>
    <?php endforeach; ?></tbody></table></div>
  <?php endforeach; if (!$by): ?><p class="muted">No appointments scheduled for today.</p><?php endif; ?>
</div>
<p class="muted small">This board refreshes automatically every 10 seconds.</p>
<?php require __DIR__ . '/../includes/footer.php'; ?>
