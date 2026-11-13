<?php
require __DIR__ . '/../includes/bootstrap.php';
$u = require_role('patient');
$p = patient_of($u['id']);
$usr = q_one('SELECT * FROM users WHERE id=?', [$u['id']]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form = post('form');
    if ($form === 'profile') {
        $phone = post('phone');
        if (!preg_match('/^\+?[0-9 ]{9,18}$/', $phone)) { flash('error', 'Enter a valid phone number.'); redirect('patient/profile.php'); }
        $dob = post('dob') ?: null;
        $gender = in_array(post('gender'), ['female', 'male', 'other'], true) ? post('gender') : null;
        q('UPDATE users SET full_name=?, phone=? WHERE id=?', [post('full_name'), $phone, $u['id']]);
        q('UPDATE patients SET dob=?, gender=?, blood_group=?, address=?,
            allergies=?, chronic_conditions=?, current_medications=?, past_surgeries=?, family_history=?, smoker=?,
            emergency_name=?, emergency_relation=?, emergency_phone=? WHERE id=?',
          [$dob, $gender, post('blood_group') ?: null, post('address') ?: null,
           post('allergies') ?: null, post('chronic_conditions') ?: null, post('current_medications') ?: null,
           post('past_surgeries') ?: null, post('family_history') ?: null,
           in_array(post('smoker'), ['no', 'yes', 'former'], true) ? post('smoker') : null,
           post('emergency_name') ?: null, post('emergency_relation') ?: null, post('emergency_phone') ?: null, $p['id']]);
        $_SESSION['user']['name'] = post('full_name');
        audit('profile.update', 'Patient profile and medical history');
        flash('ok', 'Profile saved.');
    } elseif ($form === 'password') {
        $new = $_POST['new'] ?? '';
        if (!password_verify($_POST['current'] ?? '', $usr['password_hash'])) flash('error', 'Current password is incorrect.');
        elseif (strlen($new) < 8 || !preg_match('/[A-Z]/', $new) || !preg_match('/[a-z]/', $new) || !preg_match('/\d/', $new)) flash('error', 'New password needs 8+ characters with upper-case, lower-case and a number.');
        else { q('UPDATE users SET password_hash=? WHERE id=?', [password_hash($new, PASSWORD_BCRYPT), $u['id']]); audit('password.change'); flash('ok', 'Password changed.'); }
    } elseif ($form === 'mfa') {
        q('UPDATE users SET mfa_enabled=? WHERE id=?', [post('mfa') === '1' ? 1 : 0, $u['id']]);
        audit('mfa.toggle', post('mfa') === '1' ? 'enabled' : 'disabled');
        flash('ok', 'Login security updated.');
    }
    redirect('patient/profile.php');
}
$page_title = 'My profile';
require __DIR__ . '/../includes/header.php';
$v = fn($k) => e($p[$k] ?? '');
?>
<h1>My profile &amp; medical history</h1>
<form method="post" class="card">
  <?= csrf_field() ?><input type="hidden" name="form" value="profile">
  <h2>Personal details</h2>
  <div class="row">
    <div><label for="full_name">Full name</label><input id="full_name" name="full_name" required value="<?= e($usr['full_name']) ?>"></div>
    <div><label>Email</label><input value="<?= e($usr['email']) ?>" disabled></div>
    <div><label for="phone">Phone</label><input id="phone" name="phone" value="<?= e($usr['phone']) ?>"></div>
  </div>
  <div class="row">
    <div><label for="dob">Date of birth</label><input id="dob" type="date" name="dob" value="<?= $v('dob') ?>"></div>
    <div><label for="gender">Gender</label><select id="gender" name="gender"><option value="">-</option>
      <?php foreach (['female', 'male', 'other'] as $g): ?><option value="<?= $g ?>" <?= ($p['gender'] ?? '') === $g ? 'selected' : '' ?>><?= ucfirst($g) ?></option><?php endforeach; ?></select></div>
    <div><label for="blood_group">Blood group</label><select id="blood_group" name="blood_group"><option value="">-</option>
      <?php foreach (['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'] as $g): ?><option <?= ($p['blood_group'] ?? '') === $g ? 'selected' : '' ?>><?= $g ?></option><?php endforeach; ?></select></div>
  </div>
  <label for="address">Address</label><input id="address" name="address" value="<?= $v('address') ?>">

  <h2 style="margin-top:22px">Medical history questionnaire</h2>
  <div class="row">
    <div><label for="allergies">Allergies</label><textarea id="allergies" name="allergies"><?= $v('allergies') ?></textarea></div>
    <div><label for="chronic_conditions">Chronic conditions</label><textarea id="chronic_conditions" name="chronic_conditions"><?= $v('chronic_conditions') ?></textarea></div>
    <div><label for="current_medications">Current medications</label><textarea id="current_medications" name="current_medications"><?= $v('current_medications') ?></textarea></div>
  </div>
  <div class="row">
    <div><label for="past_surgeries">Past surgeries</label><textarea id="past_surgeries" name="past_surgeries"><?= $v('past_surgeries') ?></textarea></div>
    <div><label for="family_history">Family medical history</label><textarea id="family_history" name="family_history"><?= $v('family_history') ?></textarea></div>
    <div><label for="smoker">Do you smoke?</label><select id="smoker" name="smoker"><option value="">-</option>
      <?php foreach (['no' => 'No', 'yes' => 'Yes', 'former' => 'Former smoker'] as $k => $l): ?><option value="<?= $k ?>" <?= ($p['smoker'] ?? '') === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div>
  </div>

  <h2 style="margin-top:22px">Emergency contact</h2>
  <div class="row">
    <div><label for="emergency_name">Name</label><input id="emergency_name" name="emergency_name" value="<?= $v('emergency_name') ?>"></div>
    <div><label for="emergency_relation">Relationship</label><input id="emergency_relation" name="emergency_relation" value="<?= $v('emergency_relation') ?>"></div>
    <div><label for="emergency_phone">Phone</label><input id="emergency_phone" name="emergency_phone" value="<?= $v('emergency_phone') ?>"></div>
  </div>
  <p><button class="btn">Save profile</button></p>
</form>

<div class="grid g2">
  <form method="post" class="card"><?= csrf_field() ?><input type="hidden" name="form" value="password">
    <h2>Change password</h2>
    <label for="current">Current password</label><input id="current" type="password" name="current" required autocomplete="current-password">
    <label for="new">New password</label><input id="new" type="password" name="new" required autocomplete="new-password">
    <p><button class="btn sec">Update password</button></p>
  </form>
  <form method="post" class="card"><?= csrf_field() ?><input type="hidden" name="form" value="mfa">
    <h2>Multi-factor login</h2>
    <p class="muted">Require an emailed one-time code every time you sign in. Recommended.</p>
    <label><input type="checkbox" name="mfa" value="1" <?= $usr['mfa_enabled'] ? 'checked' : '' ?>> Enable email code at login</label>
    <p><button class="btn sec">Save</button></p>
  </form>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
