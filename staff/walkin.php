<?php
require __DIR__ . '/../includes/bootstrap.php';
$u = require_role('staff', 'admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pid = (int)post('patient_id'); $did = (int)post('doctor_id'); $date = post('date'); $time = post('time');
    $pat = q_one('SELECT p.id, p.user_id FROM patients p WHERE p.id=?', [$pid]);
    if (!$pat) flash('error', 'Choose a patient.');
    else {
        [$ok, $res] = book_slot($pid, $did, $date, $time, post('reason'));
        if ($ok) {
            notify_user((int)$pat['user_id'], 'Appointment confirmed', 'Reception booked you for ' . fmt_date($date) . ' at ' . fmt_time($time) . '.', 'confirmation', $res);
            audit('appointment.book', "Appointment #$res by staff");
            flash('ok', 'Appointment booked for the patient.');
            redirect('staff/appointments.php?date=' . $date);
        }
        flash('error', $res);
    }
}
$patients = q_all("SELECT p.id, u.full_name, u.phone FROM patients p JOIN users u ON u.id=p.user_id WHERE u.is_active=1 ORDER BY u.full_name");
$doctors = q_all('SELECT d.id, u.full_name, s.name specialty FROM doctors d JOIN users u ON u.id=d.user_id JOIN specialties s ON s.id=d.specialty_id ORDER BY u.full_name');
$did = (int)get('doctor_id'); $date = get('date');
$slots = ($did && $date) ? available_slots($did, $date) : [];
$page_title = 'Walk-in booking';
require __DIR__ . '/../includes/header.php';
?>
<h1>Book on behalf of a patient</h1>
<form method="get" class="card row">
  <div><label for="doctor_id">Doctor</label><select id="doctor_id" name="doctor_id" required><option value="">Choose...</option>
    <?php foreach ($doctors as $d): ?><option value="<?= $d['id'] ?>" <?= $did === (int)$d['id'] ? 'selected' : '' ?>><?= e($d['full_name']) ?> - <?= e($d['specialty']) ?></option><?php endforeach; ?></select></div>
  <div><label for="date">Date</label><input id="date" type="date" name="date" min="<?= date('Y-m-d') ?>" value="<?= e($date ?: date('Y-m-d')) ?>" required></div>
  <div style="align-self:end"><button class="btn sec">Show slots</button></div>
</form>
<?php if ($did && $date): ?>
<form method="post" class="card">
  <?= csrf_field() ?><input type="hidden" name="doctor_id" value="<?= $did ?>"><input type="hidden" name="date" value="<?= e($date) ?>">
  <?php if (!$slots): ?><p class="alert a-warn">No free slots for that doctor on <?= e(fmt_date($date)) ?>.</p>
  <?php else: ?>
  <label for="patient_id">Patient</label>
  <select id="patient_id" name="patient_id" required><option value="">Choose patient...</option>
    <?php foreach ($patients as $p): ?><option value="<?= $p['id'] ?>"><?= e($p['full_name']) ?> (<?= e($p['phone']) ?>)</option><?php endforeach; ?></select>
  <p class="hint">Patient has no account? Ask them to register first at the front desk kiosk or on their phone.</p>
  <fieldset style="border:0;padding:0"><legend><b>Time</b></legend><div class="slots">
    <?php foreach ($slots as $i => $t): ?><label class="slot" style="margin:0"><input type="radio" name="time" value="<?= e($t) ?>" required <?= $i === 0 ? 'checked' : '' ?>><span><?= e(fmt_time($t)) ?></span></label><?php endforeach; ?></div></fieldset>
  <label for="reason">Reason</label><input id="reason" name="reason" maxlength="250">
  <p><button class="btn">Book appointment</button></p>
  <?php endif; ?>
</form>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
