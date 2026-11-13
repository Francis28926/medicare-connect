<?php
require __DIR__ . '/../includes/bootstrap.php';
$u = require_role('doctor');
$doc = doctor_of($u['id']);
$id = (int)get('id', post('id'));
$a = q_one("SELECT a.*, pu.full_name patient_name, pu.id patient_user, p.dob, p.gender, p.blood_group, p.allergies, p.chronic_conditions,
    p.current_medications, p.past_surgeries, p.family_history, p.smoker
    FROM appointments a JOIN patients p ON p.id=a.patient_id JOIN users pu ON pu.id=p.user_id
    WHERE a.id=? AND a.doctor_id=?", [$id, $doc['id']]);
if (!$a) { flash('error', 'Appointment not found.'); redirect('doctor/dashboard.php'); }
$rec = q_one('SELECT * FROM medical_records WHERE appointment_id=?', [$id]);
$rx = q_one('SELECT * FROM prescriptions WHERE appointment_id=?', [$id]);
$items = $rx ? q_all('SELECT * FROM prescription_items WHERE prescription_id=?', [$rx['id']]) : [];
$editable = $a['status'] === 'in_consultation';
audit('records.view', 'Doctor opened appointment #' . $id . ' (patient ' . $a['patient_name'] . ')');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $editable) {
    $num = fn($k) => post($k) === '' ? null : post($k);
    db()->beginTransaction();
    q('INSERT INTO medical_records (appointment_id, patient_id, doctor_id, blood_pressure, temperature, pulse, weight_kg, symptoms, diagnosis, notes)
       VALUES (?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE blood_pressure=VALUES(blood_pressure), temperature=VALUES(temperature), pulse=VALUES(pulse),
       weight_kg=VALUES(weight_kg), symptoms=VALUES(symptoms), diagnosis=VALUES(diagnosis), notes=VALUES(notes)',
      [$id, $a['patient_id'], $doc['id'], $num('bp'), $num('temp'), $num('pulse'), $num('weight'), post('symptoms'), post('diagnosis'), post('notes')]);

    $meds = array_filter(array_map('trim', $_POST['med'] ?? []));
    if ($meds) {
        if (!$rx) { q('INSERT INTO prescriptions (appointment_id, patient_id, doctor_id, instructions) VALUES (?,?,?,?)', [$id, $a['patient_id'], $doc['id'], post('instructions')]); $rxid = (int)db()->lastInsertId(); }
        else { $rxid = (int)$rx['id']; q('UPDATE prescriptions SET instructions=? WHERE id=?', [post('instructions'), $rxid]); q('DELETE FROM prescription_items WHERE prescription_id=?', [$rxid]); }
        foreach ($_POST['med'] as $i => $m) {
            if (trim($m) === '') continue;
            q('INSERT INTO prescription_items (prescription_id, medication, dosage, frequency, duration) VALUES (?,?,?,?,?)',
              [$rxid, trim($m), trim($_POST['dose'][$i] ?? ''), trim($_POST['freq'][$i] ?? ''), trim($_POST['dur'][$i] ?? '')]);
        }
        audit('prescription.issue', "Appointment #$id");
    }
    if (post('action') === 'complete') {
        q("UPDATE appointments SET status='completed', completed_at=NOW() WHERE id=?", [$id]);
        notify_user((int)$a['patient_user'], 'Consultation complete', 'Your visit record' . ($meds ? ' and e-prescription are' : ' is') . ' now available under Records.', 'record', $id);
        audit('consultation.complete', "Appointment #$id");
        db()->commit();
        flash('ok', 'Consultation completed and record saved.');
        redirect('doctor/dashboard.php');
    }
    db()->commit();
    flash('ok', 'Draft saved.');
    redirect('doctor/consult.php?id=' . $id);
}
$page_title = 'Consultation';
require __DIR__ . '/../includes/header.php';
$v = fn($k) => e($rec[$k] ?? '');
$dis = $editable ? '' : 'disabled';
$age = $a['dob'] ? (int)((time() - strtotime($a['dob'])) / 31557600) : null;
?>
<h1>Consultation: <?= e($a['patient_name']) ?></h1>
<p><?= status_badge($a['status']) ?> <?= e(fmt_date($a['appt_date'])) ?>, <?= e(fmt_time($a['appt_time'])) ?> &middot; Reason: <?= e($a['reason']) ?></p>

<section class="card">
  <h2>Patient summary</h2>
  <div class="row small">
    <div><b>Age / Gender</b><br><?= $age !== null ? $age . ' yrs' : '-' ?> / <?= e($a['gender'] ?: '-') ?></div>
    <div><b>Blood group</b><br><?= e($a['blood_group'] ?: '-') ?></div>
    <div><b>Allergies</b><br><?= e($a['allergies'] ?: 'None recorded') ?></div>
    <div><b>Chronic conditions</b><br><?= e($a['chronic_conditions'] ?: '-') ?></div>
    <div><b>Current medication</b><br><?= e($a['current_medications'] ?: '-') ?></div>
    <div><b>Past surgeries</b><br><?= e($a['past_surgeries'] ?: '-') ?></div>
    <div><b>Family history</b><br><?= e($a['family_history'] ?: '-') ?></div>
    <div><b>Smoker</b><br><?= e($a['smoker'] ?: '-') ?></div>
  </div>
</section>

<form method="post" class="card">
  <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>">
  <h2>Electronic medical record</h2>
  <div class="row">
    <div><label for="bp">Blood pressure</label><input id="bp" name="bp" placeholder="120/80" value="<?= $v('blood_pressure') ?>" <?= $dis ?>></div>
    <div><label for="temp">Temperature (°C)</label><input id="temp" name="temp" type="number" step="0.1" min="30" max="45" value="<?= $v('temperature') ?>" <?= $dis ?>></div>
    <div><label for="pulse">Pulse (bpm)</label><input id="pulse" name="pulse" type="number" min="20" max="250" value="<?= $v('pulse') ?>" <?= $dis ?>></div>
    <div><label for="weight">Weight (kg)</label><input id="weight" name="weight" type="number" step="0.1" min="1" max="400" value="<?= $v('weight_kg') ?>" <?= $dis ?>></div>
  </div>
  <label for="symptoms">Symptoms</label><textarea id="symptoms" name="symptoms" <?= $dis ?>><?= $v('symptoms') ?></textarea>
  <label for="diagnosis">Diagnosis</label><textarea id="diagnosis" name="diagnosis" <?= $dis ?>><?= $v('diagnosis') ?></textarea>
  <label for="notes">Doctor's notes</label><textarea id="notes" name="notes" <?= $dis ?>><?= $v('notes') ?></textarea>

  <h2 style="margin-top:22px">E-Prescription</h2>
  <div id="med-rows">
  <?php $lines = $items ?: [['medication' => '', 'dosage' => '', 'frequency' => '', 'duration' => '']];
  foreach ($lines as $it): ?>
    <div class="row med-row" style="margin-bottom:8px">
      <div><label>Medication</label><input name="med[]" value="<?= e($it['medication']) ?>" <?= $dis ?>></div>
      <div><label>Dosage</label><input name="dose[]" value="<?= e($it['dosage']) ?>" placeholder="500 mg" <?= $dis ?>></div>
      <div><label>Frequency</label><input name="freq[]" value="<?= e($it['frequency']) ?>" placeholder="3 times daily" <?= $dis ?>></div>
      <div><label>Duration</label><input name="dur[]" value="<?= e($it['duration']) ?>" placeholder="5 days" <?= $dis ?>></div>
    </div>
  <?php endforeach; ?>
  </div>
  <?php if ($editable): ?><button type="button" class="btn sec sm" id="add-med">+ Add medication</button><?php endif; ?>
  <label for="instructions">Instructions to patient</label><textarea id="instructions" name="instructions" <?= $dis ?>><?= e($rx['instructions'] ?? '') ?></textarea>

  <p style="margin-top:18px" class="actions">
  <?php if ($editable): ?>
    <button class="btn sec" name="action" value="save">Save draft</button>
    <button class="btn" name="action" value="complete">Complete consultation</button>
  <?php endif; ?>
    <a class="btn sec" href="<?= e(url('doctor/dashboard.php')) ?>">Back</a>
  </p>
</form>
<?php require __DIR__ . '/../includes/footer.php'; ?>
