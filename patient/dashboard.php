<?php
require __DIR__ . '/../includes/bootstrap.php';
$u = require_role('patient');
$p = patient_of($u['id']);

$next = q_all("SELECT a.*, du.full_name doctor_name, s.name specialty, d.room
    FROM appointments a JOIN doctors d ON d.id=a.doctor_id JOIN users du ON du.id=d.user_id JOIN specialties s ON s.id=d.specialty_id
    WHERE a.patient_id=? AND a.status IN ('booked','checked_in','in_consultation') AND a.appt_date>=CURDATE()
    ORDER BY a.appt_date, a.appt_time LIMIT 5", [$p['id']]);
$visits = (int)q_val("SELECT COUNT(*) FROM appointments WHERE patient_id=? AND status='completed'", [$p['id']]);
$rx = (int)q_val('SELECT COUNT(*) FROM prescriptions WHERE patient_id=?', [$p['id']]);
$notes = q_all("SELECT * FROM notifications WHERE user_id=? AND channel='inapp' ORDER BY id DESC LIMIT 4", [$u['id']]);

function queue_info(array $a): ?array {
    if ($a['appt_date'] !== date('Y-m-d')) return null;
    if ($a['status'] === 'in_consultation') return ['now' => true, 'ahead' => 0, 'wait' => 0];
    if ($a['status'] !== 'checked_in') return null;
    $ahead = (int)q_val("SELECT COUNT(*) FROM appointments WHERE doctor_id=? AND appt_date=? AND
        (status='in_consultation' OR (status='checked_in' AND queue_number<?))", [$a['doctor_id'], $a['appt_date'], $a['queue_number']]);
    return ['now' => false, 'ahead' => $ahead, 'wait' => $ahead * 15];
}
$page_title = 'My dashboard';
require __DIR__ . '/../includes/header.php';
?>
<h1>Hello, <?= e(explode(' ', $u['name'])[0]) ?></h1>
<div class="grid g4" style="margin-bottom:18px">
  <div class="stat"><b><?= count($next) ?></b><span>Upcoming appointments</span></div>
  <div class="stat"><b><?= $visits ?></b><span>Completed visits</span></div>
  <div class="stat"><b><?= $rx ?></b><span>Prescriptions</span></div>
  <div class="stat"><b><?= unread_count($u['id']) ?></b><span>Unread alerts</span></div>
</div>

<div class="grid g2">
  <section class="card" data-live="queue" aria-live="polite">
    <h2>Live queue status</h2>
    <?php $shown = false; foreach ($next as $a): $qi = queue_info($a); if (!$qi) continue; $shown = true; ?>
      <p><b><?= e($a['doctor_name']) ?></b> <span class="muted">(<?= e($a['room']) ?>)</span></p>
      <?php if ($qi['now']): ?>
        <p class="queue-now">It's your turn</p><p>Please go to <?= e($a['room']) ?>.</p>
      <?php else: ?>
        <p class="queue-now"><?= $qi['ahead'] ?> <span style="font-size:1rem;font-weight:600">ahead of you</span></p>
        <p class="muted">Your queue number is <b>#<?= (int)$a['queue_number'] ?></b>. Estimated wait: about <?= $qi['wait'] ?> min. This updates automatically.</p>
      <?php endif; ?>
    <?php endforeach; if (!$shown): ?>
      <p class="muted">No active queue. When you check in at reception for today's appointment, your live position appears here.</p>
    <?php endif; ?>
  </section>

  <section class="card">
    <h2>Recent alerts</h2>
    <?php if (!$notes): ?><p class="muted">No alerts yet.</p><?php endif; ?>
    <?php foreach ($notes as $n): ?>
      <p class="<?= $n['read_at'] ? '' : 'unread' ?>"><?= e($n['subject']) ?><br><span class="muted small"><?= e(fmt_dt($n['created_at'])) ?></span></p>
    <?php endforeach; ?>
    <a href="<?= e(url('patient/notifications.php')) ?>">View all alerts</a>
  </section>
</div>

<section class="card">
  <h2>Upcoming appointments</h2>
  <?php if (!$next): ?>
    <p class="muted">You have nothing booked.</p>
  <?php else: ?>
  <div class="tablewrap" style="border:0;margin:0"><table>
    <thead><tr><th>Date</th><th>Time</th><th>Doctor</th><th>Status</th></tr></thead><tbody>
    <?php foreach ($next as $a): ?>
      <tr><td><?= e(fmt_date($a['appt_date'])) ?></td><td><?= e(fmt_time($a['appt_time'])) ?></td>
          <td><?= e($a['doctor_name']) ?><br><span class="muted small"><?= e($a['specialty']) ?></span></td><td><?= status_badge($a['status']) ?></td></tr>
    <?php endforeach; ?></tbody></table></div>
  <?php endif; ?>
  <p><a class="btn" href="<?= e(url('patient/book.php')) ?>">Book an appointment</a></p>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
