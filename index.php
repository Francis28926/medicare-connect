<?php
require __DIR__ . '/includes/bootstrap.php';
$page_title = 'Home';
$u = current_user();
$docs = q_all('SELECT u.full_name, s.name specialty, d.consultation_fee FROM doctors d JOIN users u ON u.id=d.user_id JOIN specialties s ON s.id=d.specialty_id WHERE u.is_active=1 ORDER BY u.full_name');
require __DIR__ . '/includes/header.php';
?>
<section class="hero">
  <h1>Book your clinic visit online. Spend less time waiting.</h1>
  <p>MediCare Connect lets you find a doctor, book a slot, get reminders, follow the live queue and keep your medical records and prescriptions in one secure place.</p>
  <p class="actions">
  <?php if ($u): ?>
    <a class="btn" href="<?= e(url(ROLE_HOME[$u['role']])) ?>">Go to my dashboard</a>
  <?php else: ?>
    <a class="btn" href="<?= e(url('register.php')) ?>">Create patient account</a>
    <a class="btn sec" href="<?= e(url('login.php')) ?>">Sign in</a>
  <?php endif; ?>
  </p>
</section>

<div class="grid g3">
  <div class="card"><h2>Book in 3 clicks</h2><p class="muted">Search by specialty, pick an available slot, confirm. You get an instant confirmation and a reminder 24 hours before.</p></div>
  <div class="card"><h2>Live queue</h2><p class="muted">Check in at reception and watch your position in the queue update in real time.</p></div>
  <div class="card"><h2>Secure records</h2><p class="muted">Role-based access, hashed passwords, audit logging and encrypted backups protect your health data.</p></div>
</div>

<h2>Our doctors</h2>
<div class="tablewrap"><table>
  <thead><tr><th>Doctor</th><th>Specialty</th><th>Consultation fee</th></tr></thead>
  <tbody>
  <?php foreach ($docs as $d): ?>
    <tr><td><?= e($d['full_name']) ?></td><td><?= e($d['specialty']) ?></td><td><?= e(money($d['consultation_fee'])) ?></td></tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
<?php require __DIR__ . '/includes/footer.php'; ?>
