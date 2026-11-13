<?php
require __DIR__ . '/../includes/bootstrap.php';
$u = require_role('patient');
$p = patient_of($u['id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'cancel') {
    $id = (int)post('id');
    $a = q_one("SELECT * FROM appointments WHERE id=? AND patient_id=? AND status='booked'", [$id, $p['id']]);
    if ($a && strtotime($a['appt_date'] . ' ' . $a['appt_time']) > time() + 3600) {
        q("UPDATE appointments SET status='cancelled', slot_key=NULL WHERE id=?", [$id]);
        if (q_val('SELECT id FROM payments WHERE appointment_id=?', [$id])) q("UPDATE payments SET status='refunded' WHERE appointment_id=?", [$id]);
        notify_user($u['id'], 'Appointment cancelled', 'Your appointment on ' . fmt_date($a['appt_date']) . ' at ' . fmt_time($a['appt_time']) . ' was cancelled.', 'cancel', $id);
        audit('appointment.cancel', "Appointment #$id");
        flash('ok', 'Appointment cancelled.');
    } else flash('error', 'This appointment can no longer be cancelled online (must be at least 1 hour before the visit).');
    redirect('patient/appointments.php');
}

$rows = q_all("SELECT a.*, du.full_name doctor_name, s.name specialty, d.consultation_fee, d.room, pay.status pay_status, pay.reference
    FROM appointments a JOIN doctors d ON d.id=a.doctor_id JOIN users du ON du.id=d.user_id JOIN specialties s ON s.id=d.specialty_id
    LEFT JOIN payments pay ON pay.appointment_id=a.id
    WHERE a.patient_id=? ORDER BY a.appt_date DESC, a.appt_time DESC", [$p['id']]);
$page_title = 'My appointments';
require __DIR__ . '/../includes/header.php';
?>
<h1>My appointments</h1>
<p><a class="btn" href="<?= e(url('patient/book.php')) ?>">Book new appointment</a></p>
<div class="tablewrap"><table>
  <thead><tr><th>Date &amp; time</th><th>Doctor</th><th>Reason</th><th>Status</th><th>Payment</th><th>Actions</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $a): $future = $a['status'] === 'booked' && strtotime($a['appt_date'] . ' ' . $a['appt_time']) > time(); ?>
    <tr>
      <td><?= e(fmt_date($a['appt_date'])) ?><br><?= e(fmt_time($a['appt_time'])) ?></td>
      <td><?= e($a['doctor_name']) ?><br><span class="muted small"><?= e($a['specialty']) ?></span></td>
      <td><?= e($a['reason']) ?></td>
      <td><?= status_badge($a['status']) ?></td>
      <td><?php if ($a['pay_status'] === 'paid'): ?><span class="badge b-completed">Paid</span><br><span class="small muted"><?= e($a['reference']) ?></span>
          <?php elseif ($a['pay_status'] === 'refunded'): ?><span class="badge b-cancelled">Refunded</span>
          <?php elseif ($a['status'] !== 'cancelled'): ?><?= e(money($a['consultation_fee'])) ?> due<?php else: ?>-<?php endif; ?></td>
      <td class="actions">
        <?php if (!$a['pay_status'] && !in_array($a['status'], ['cancelled', 'no_show'], true)): ?>
          <a class="btn sm" href="<?= e(url('patient/pay.php?id=' . $a['id'])) ?>">Pay now</a>
        <?php endif; ?>
        <?php if ($future): ?>
          <form method="post" data-confirm="Cancel this appointment?"><?= csrf_field() ?><input type="hidden" name="action" value="cancel"><input type="hidden" name="id" value="<?= $a['id'] ?>"><button class="btn sm danger">Cancel</button></form>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; if (!$rows): ?><tr><td colspan="6" class="muted">No appointments yet.</td></tr><?php endif; ?>
  </tbody>
</table></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
