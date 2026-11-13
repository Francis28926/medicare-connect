<?php
require __DIR__ . '/../includes/bootstrap.php';
$u = require_role('doctor');
$doc = doctor_of($u['id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'call') {
    $id = (int)post('id');
    $a = q_one("SELECT a.*, p.user_id patient_user FROM appointments a JOIN patients p ON p.id=a.patient_id WHERE a.id=? AND a.doctor_id=? AND a.status='checked_in'", [$id, $doc['id']]);
    if ($a) {
        q("UPDATE appointments SET status='in_consultation', started_at=NOW() WHERE id=?", [$id]);
        notify_user((int)$a['patient_user'], "It's your turn", 'The doctor is ready to see you. Please go to ' . $doc['room'] . '.', 'queue', $id);
        audit('queue.call', "Appointment #$id");
        redirect('doctor/consult.php?id=' . $id);
    }
    flash('error', 'That patient is not waiting in the queue.');
    redirect('doctor/dashboard.php');
}

$rows = q_all("SELECT a.*, pu.full_name patient_name, p.dob, p.allergies FROM appointments a
    JOIN patients p ON p.id=a.patient_id JOIN users pu ON pu.id=p.user_id
    WHERE a.doctor_id=? AND a.appt_date=CURDATE() AND a.status<>'cancelled'
    ORDER BY FIELD(a.status,'in_consultation','checked_in','booked','completed','no_show'), a.queue_number, a.appt_time", [$doc['id']]);
$cnt = array_count_values(array_column($rows, 'status'));
$page_title = 'Doctor dashboard';
require __DIR__ . '/../includes/header.php';
?>
<h1><?= e($u['name']) ?> <span class="pill"><?= e($doc['specialty']) ?></span></h1>
<p class="muted">Today's consultations &middot; <?= e(fmt_date(date('Y-m-d'))) ?> &middot; <?= e($doc['room']) ?></p>
<div data-live="doc" aria-live="polite">
  <div class="grid g4" style="margin-bottom:18px">
    <div class="stat"><b><?= (int)($cnt['checked_in'] ?? 0) ?></b><span>Waiting now</span></div>
    <div class="stat"><b><?= (int)($cnt['booked'] ?? 0) ?></b><span>Not yet arrived</span></div>
    <div class="stat"><b><?= (int)($cnt['in_consultation'] ?? 0) ?></b><span>In consultation</span></div>
    <div class="stat"><b><?= (int)($cnt['completed'] ?? 0) ?></b><span>Completed today</span></div>
  </div>
  <div class="tablewrap"><table>
    <thead><tr><th>Queue</th><th>Time</th><th>Patient</th><th>Reason</th><th>Status</th><th>Action</th></tr></thead><tbody>
    <?php foreach ($rows as $r): ?>
      <tr><td><?= $r['queue_number'] ? '#' . (int)$r['queue_number'] : '-' ?></td><td><?= e(fmt_time($r['appt_time'])) ?></td>
        <td><?= e($r['patient_name']) ?><?php if ($r['allergies']): ?><br><span class="badge b-no_show" style="background:var(--err-bg);color:var(--err);border-color:#efb0b0">Allergy: <?= e($r['allergies']) ?></span><?php endif; ?></td>
        <td><?= e($r['reason']) ?></td><td><?= status_badge($r['status']) ?></td>
        <td class="actions">
        <?php if ($r['status'] === 'checked_in') echo action_form((int)$r['id'], 'call', 'Call in'); ?>
        <?php if ($r['status'] === 'in_consultation'): ?><a class="btn sm" href="<?= e(url('doctor/consult.php?id=' . $r['id'])) ?>">Continue consultation</a><?php endif; ?>
        <?php if ($r['status'] === 'completed'): ?><a class="btn sm sec" href="<?= e(url('doctor/consult.php?id=' . $r['id'])) ?>">View</a><?php endif; ?>
        </td></tr>
    <?php endforeach; if (!$rows): ?><tr><td colspan="6" class="muted">No consultations scheduled today.</td></tr><?php endif; ?>
    </tbody></table></div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
