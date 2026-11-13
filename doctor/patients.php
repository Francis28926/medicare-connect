<?php
require __DIR__ . '/../includes/bootstrap.php';
$u = require_role('doctor');
$doc = doctor_of($u['id']);
$pid = (int)get('id');
$search = get('q');

if ($pid) {
    // Only patients this doctor has treated (least-privilege access to records)
    $p = q_one("SELECT p.*, pu.full_name, pu.phone, pu.email FROM patients p JOIN users pu ON pu.id=p.user_id
        WHERE p.id=? AND EXISTS (SELECT 1 FROM appointments a WHERE a.patient_id=p.id AND a.doctor_id=?)", [$pid, $doc['id']]);
    if (!$p) { flash('error', 'Patient not found in your list.'); redirect('doctor/patients.php'); }
    audit('records.view', 'Doctor viewed history of patient #' . $pid);
    $recs = q_all("SELECT r.*, a.appt_date, du.full_name doctor_name, pr.id rx_id FROM medical_records r JOIN appointments a ON a.id=r.appointment_id
        JOIN doctors d ON d.id=r.doctor_id JOIN users du ON du.id=d.user_id LEFT JOIN prescriptions pr ON pr.appointment_id=a.id
        WHERE r.patient_id=? ORDER BY a.appt_date DESC", [$pid]);
} else {
    $sql = "SELECT DISTINCT p.id, pu.full_name, pu.phone, p.dob FROM patients p JOIN users pu ON pu.id=p.user_id
        JOIN appointments a ON a.patient_id=p.id WHERE a.doctor_id=?";
    $par = [$doc['id']];
    if ($search !== '') { $sql .= ' AND pu.full_name LIKE ?'; $par[] = "%$search%"; }
    $list = q_all($sql . ' ORDER BY pu.full_name', $par);
}
$page_title = 'My patients';
require __DIR__ . '/../includes/header.php';
?>
<?php if ($pid): ?>
  <h1><?= e($p['full_name']) ?></h1>
  <p><a href="<?= e(url('doctor/patients.php')) ?>">&larr; All patients</a></p>
  <div class="card"><div class="row small">
    <div><b>Phone</b><br><?= e($p['phone']) ?></div><div><b>DOB</b><br><?= e($p['dob'] ?: '-') ?></div><div><b>Blood group</b><br><?= e($p['blood_group'] ?: '-') ?></div>
    <div><b>Allergies</b><br><?= e($p['allergies'] ?: 'None recorded') ?></div><div><b>Chronic</b><br><?= e($p['chronic_conditions'] ?: '-') ?></div>
    <div><b>Emergency contact</b><br><?= e($p['emergency_name'] ?: '-') ?> <?= e($p['emergency_phone']) ?></div></div></div>
  <h2>Visit history</h2>
  <?php foreach ($recs as $r): ?>
    <div class="card"><b><?= e(fmt_date($r['appt_date'])) ?></b> with <?= e($r['doctor_name']) ?><br>
      BP <?= e($r['blood_pressure'] ?: '-') ?> &middot; Temp <?= e($r['temperature'] ?? '-') ?> &middot; Pulse <?= e($r['pulse'] ?? '-') ?><br>
      <b>Diagnosis:</b> <?= e($r['diagnosis']) ?><br><b>Notes:</b> <?= e($r['notes']) ?>
      <?php if ($r['rx_id']): foreach (q_all('SELECT * FROM prescription_items WHERE prescription_id=?', [$r['rx_id']]) as $i): ?>
        <br><span class="pill"><?= e($i['medication']) ?> <?= e($i['dosage']) ?> - <?= e($i['frequency']) ?> - <?= e($i['duration']) ?></span>
      <?php endforeach; endif; ?></div>
  <?php endforeach; if (!$recs) echo '<p class="muted">No records yet.</p>'; ?>
<?php else: ?>
  <h1>My patients</h1>
  <form method="get" class="card row" role="search"><div><label for="q">Search by name</label><input id="q" name="q" value="<?= e($search) ?>"></div><div style="align-self:end"><button class="btn">Search</button></div></form>
  <div class="tablewrap"><table><thead><tr><th>Name</th><th>Phone</th><th>Date of birth</th><th></th></tr></thead><tbody>
  <?php foreach ($list as $r): ?><tr><td><?= e($r['full_name']) ?></td><td><?= e($r['phone']) ?></td><td><?= e($r['dob'] ?: '-') ?></td>
    <td><a class="btn sm sec" href="?id=<?= $r['id'] ?>">Open record</a></td></tr><?php endforeach;
  if (!$list): ?><tr><td colspan="4" class="muted">No patients found.</td></tr><?php endif; ?></tbody></table></div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
