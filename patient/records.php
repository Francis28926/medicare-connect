<?php
require __DIR__ . '/../includes/bootstrap.php';
$u = require_role('patient');
$p = patient_of($u['id']);
audit('records.view', 'Patient viewed own records');

$recs = q_all("SELECT r.*, a.appt_date, du.full_name doctor_name, s.name specialty, pr.id rx_id, pr.instructions
    FROM medical_records r JOIN appointments a ON a.id=r.appointment_id
    JOIN doctors d ON d.id=r.doctor_id JOIN users du ON du.id=d.user_id JOIN specialties s ON s.id=d.specialty_id
    LEFT JOIN prescriptions pr ON pr.appointment_id=r.appointment_id
    WHERE r.patient_id=? ORDER BY a.appt_date DESC, r.id DESC", [$p['id']]);
$page_title = 'My medical records';
require __DIR__ . '/../includes/header.php';
?>
<h1>My medical records &amp; prescriptions</h1>
<p><button class="btn sec sm" onclick="window.print()">Print</button></p>
<?php foreach ($recs as $r): ?>
<section class="card">
  <h2><?= e(fmt_date($r['appt_date'])) ?> &middot; <?= e($r['doctor_name']) ?> <span class="pill"><?= e($r['specialty']) ?></span></h2>
  <div class="row small">
    <div><b>Blood pressure</b><br><?= e($r['blood_pressure'] ?: '-') ?></div>
    <div><b>Temperature</b><br><?= $r['temperature'] !== null ? e($r['temperature']) . ' °C' : '-' ?></div>
    <div><b>Pulse</b><br><?= $r['pulse'] !== null ? e($r['pulse']) . ' bpm' : '-' ?></div>
    <div><b>Weight</b><br><?= $r['weight_kg'] !== null ? e($r['weight_kg']) . ' kg' : '-' ?></div>
  </div>
  <p><b>Symptoms:</b> <?= e($r['symptoms']) ?></p>
  <p><b>Diagnosis:</b> <?= e($r['diagnosis']) ?></p>
  <p><b>Doctor's notes:</b> <?= e($r['notes']) ?></p>
  <?php if ($r['rx_id']): $items = q_all('SELECT * FROM prescription_items WHERE prescription_id=?', [$r['rx_id']]); ?>
    <h3>E-Prescription #<?= (int)$r['rx_id'] ?></h3>
    <div class="tablewrap"><table><thead><tr><th>Medication</th><th>Dosage</th><th>Frequency</th><th>Duration</th></tr></thead><tbody>
    <?php foreach ($items as $i): ?><tr><td><?= e($i['medication']) ?></td><td><?= e($i['dosage']) ?></td><td><?= e($i['frequency']) ?></td><td><?= e($i['duration']) ?></td></tr><?php endforeach; ?>
    </tbody></table></div>
    <?php if ($r['instructions']): ?><p class="small"><b>Instructions:</b> <?= e($r['instructions']) ?></p><?php endif; ?>
  <?php endif; ?>
</section>
<?php endforeach; if (!$recs): ?><p class="muted">No consultation records yet. They appear here after a doctor completes your visit.</p><?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
