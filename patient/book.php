<?php
require __DIR__ . '/../includes/bootstrap.php';
$u = require_role('patient');
$p = patient_of($u['id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $doctor_id = (int)post('doctor_id'); $date = post('date'); $time = post('time');
    [$ok, $res] = book_slot((int)$p['id'], $doctor_id, $date, $time, post('reason'));
    if ($ok) {
        $d = q_one('SELECT u.full_name, d.consultation_fee FROM doctors d JOIN users u ON u.id=d.user_id WHERE d.id=?', [$doctor_id]);
        notify_user($u['id'], 'Appointment confirmed',
            'Your appointment with ' . $d['full_name'] . ' is confirmed for ' . fmt_date($date) . ' at ' . fmt_time($time) . '.', 'confirmation', $res);
        audit('appointment.book', "Appointment #$res");
        flash('ok', 'Appointment booked! A confirmation has been sent.');
        redirect('patient/appointments.php');
    }
    flash('error', $res);
}

$spec = (int)get('specialty'); $search = get('q');
$specs = q_all('SELECT * FROM specialties ORDER BY name');
$sql = 'SELECT d.id, d.consultation_fee, d.bio, d.room, u.full_name, s.name specialty FROM doctors d
        JOIN users u ON u.id=d.user_id AND u.is_active=1 JOIN specialties s ON s.id=d.specialty_id WHERE 1=1';
$par = [];
if ($spec) { $sql .= ' AND d.specialty_id=?'; $par[] = $spec; }
if ($search !== '') { $sql .= ' AND u.full_name LIKE ?'; $par[] = "%$search%"; }
$doctors = q_all($sql . ' ORDER BY u.full_name', $par);

$sel = (int)get('doctor'); $date = get('date');
$selDoc = $sel ? q_one('SELECT d.*, u.full_name, s.name specialty FROM doctors d JOIN users u ON u.id=d.user_id JOIN specialties s ON s.id=d.specialty_id WHERE d.id=?', [$sel]) : null;
$slots = ($selDoc && $date) ? available_slots($sel, $date) : [];

$page_title = 'Book appointment';
require __DIR__ . '/../includes/header.php';
?>
<h1>Book an appointment</h1>

<section class="card">
  <h2>1. Find a doctor</h2>
  <form method="get" class="row" role="search">
    <div><label for="specialty">Specialty</label>
      <select id="specialty" name="specialty"><option value="0">All specialties</option>
      <?php foreach ($specs as $s): ?><option value="<?= $s['id'] ?>" <?= $spec === (int)$s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option><?php endforeach; ?></select></div>
    <div><label for="q">Doctor name</label><input id="q" name="q" value="<?= e($search) ?>"></div>
    <div style="align-self:end"><button class="btn">Search</button></div>
  </form>
</section>

<div class="grid g3">
<?php foreach ($doctors as $d): ?>
  <div class="card" <?= $sel === (int)$d['id'] ? 'style="border:2px solid var(--teal)"' : '' ?>>
    <h3 style="margin-top:0"><?= e($d['full_name']) ?></h3>
    <span class="pill"><?= e($d['specialty']) ?></span>
    <p class="muted small"><?= e($d['bio']) ?></p>
    <p><b><?= e(money($d['consultation_fee'])) ?></b> &middot; <?= e($d['room']) ?></p>
    <a class="btn sec sm" href="?<?= e(http_build_query(['specialty' => $spec, 'q' => $search, 'doctor' => $d['id'], 'date' => $date ?: date('Y-m-d', strtotime('+1 day'))])) ?>#slots">View slots</a>
  </div>
<?php endforeach; if (!$doctors): ?><p class="muted">No doctors match your search.</p><?php endif; ?>
</div>

<?php if ($selDoc): ?>
<section class="card" id="slots">
  <h2>2. Choose a date &amp; time with <?= e($selDoc['full_name']) ?></h2>
  <form method="get" class="row">
    <input type="hidden" name="doctor" value="<?= $sel ?>"><input type="hidden" name="specialty" value="<?= $spec ?>"><input type="hidden" name="q" value="<?= e($search) ?>">
    <div><label for="date">Date</label><input id="date" type="date" name="date" min="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d', strtotime('+60 days')) ?>" value="<?= e($date) ?>"></div>
    <div style="align-self:end"><button class="btn sec">Show slots</button></div>
  </form>

  <?php if ($date && !$slots): ?>
    <p class="alert a-warn" style="margin-top:14px">No free slots on <?= e(fmt_date($date)) ?>. Try another day.</p>
  <?php elseif ($slots): ?>
  <form method="post" style="margin-top:14px">
    <?= csrf_field() ?>
    <input type="hidden" name="doctor_id" value="<?= $sel ?>"><input type="hidden" name="date" value="<?= e($date) ?>">
    <fieldset style="border:0;padding:0;margin:0"><legend><b><?= e(fmt_date($date)) ?></b> available times</legend>
      <div class="slots">
      <?php foreach ($slots as $i => $t): ?>
        <label class="slot" style="margin:0"><input type="radio" name="time" value="<?= e($t) ?>" required <?= $i === 0 ? 'checked' : '' ?>><span><?= e(fmt_time($t)) ?></span></label>
      <?php endforeach; ?></div>
    </fieldset>
    <label for="reason">Reason for visit</label>
    <input id="reason" name="reason" maxlength="250" placeholder="e.g. headache for 3 days">
    <p><button class="btn">3. Confirm booking (<?= e(money($selDoc['consultation_fee'])) ?>)</button></p>
  </form>
  <?php endif; ?>
</section>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
