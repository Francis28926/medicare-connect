<?php
require __DIR__ . '/../includes/bootstrap.php';
$u = require_role('staff', 'admin');
staff_handle_action();

$date = get('date', date('Y-m-d')); $status = get('status'); $qs = get('q');
$sql = "SELECT a.*, pu.full_name patient_name, pu.phone, du.full_name doctor_name, pay.status pay_status
    FROM appointments a JOIN patients p ON p.id=a.patient_id JOIN users pu ON pu.id=p.user_id
    JOIN doctors d ON d.id=a.doctor_id JOIN users du ON du.id=d.user_id LEFT JOIN payments pay ON pay.appointment_id=a.id WHERE a.appt_date=?";
$par = [$date];
if ($status !== '') { $sql .= ' AND a.status=?'; $par[] = $status; }
if ($qs !== '') { $sql .= ' AND pu.full_name LIKE ?'; $par[] = "%$qs%"; }
$rows = q_all($sql . ' ORDER BY a.appt_time', $par);
$page_title = 'Appointments';
require __DIR__ . '/../includes/header.php';
?>
<h1>Appointments</h1>
<form method="get" class="card row" role="search">
  <div><label for="date">Date</label><input id="date" type="date" name="date" value="<?= e($date) ?>"></div>
  <div><label for="status">Status</label><select id="status" name="status"><option value="">All</option>
    <?php foreach (['booked', 'checked_in', 'in_consultation', 'completed', 'cancelled', 'no_show'] as $s): ?><option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= e(ucfirst(str_replace('_', ' ', $s))) ?></option><?php endforeach; ?></select></div>
  <div><label for="q">Patient name</label><input id="q" name="q" value="<?= e($qs) ?>"></div>
  <div style="align-self:end"><button class="btn">Filter</button></div>
</form>
<div class="tablewrap"><table>
  <thead><tr><th>Time</th><th>Patient</th><th>Doctor</th><th>Reason</th><th>Status</th><th>Paid</th><th>Action</th></tr></thead><tbody>
  <?php foreach ($rows as $r): ?>
    <tr><td><?= e(fmt_time($r['appt_time'])) ?></td><td><?= e($r['patient_name']) ?><br><span class="muted small"><?= e($r['phone']) ?></span></td>
      <td><?= e($r['doctor_name']) ?></td><td><?= e($r['reason']) ?></td><td><?= status_badge($r['status']) ?></td>
      <td><?= $r['pay_status'] === 'paid' ? 'Yes' : 'No' ?></td>
      <td class="actions">
      <?php if ($r['status'] === 'booked' && $r['appt_date'] === date('Y-m-d')) echo action_form((int)$r['id'], 'checkin', 'Check in'); ?>
      <?php if (in_array($r['status'], ['booked', 'checked_in'], true)) echo action_form((int)$r['id'], 'cancel', 'Cancel', 'danger', 'Cancel this appointment?'); ?>
      </td></tr>
  <?php endforeach; if (!$rows): ?><tr><td colspan="7" class="muted">No appointments found.</td></tr><?php endif; ?>
</tbody></table></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
