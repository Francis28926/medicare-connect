<?php
require __DIR__ . '/../includes/bootstrap.php';
$u = require_role('admin');
$strong = fn($p) => strlen($p) >= 8 && preg_match('/[A-Z]/', $p) && preg_match('/[a-z]/', $p) && preg_match('/\d/', $p);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = post('action'); $id = (int)post('id');
    if ($act === 'create') {
        $role = post('role'); $name = post('full_name'); $email = strtolower(post('email')); $pw = $_POST['password'] ?? '';
        if (!in_array($role, ['staff', 'doctor', 'admin'], true) || mb_strlen($name) < 3 || !filter_var($email, FILTER_VALIDATE_EMAIL) || !$strong($pw)) flash('error', 'Check the form: valid name, email, role and a strong password (8+ chars, upper, lower, number).');
        elseif (q_val('SELECT id FROM users WHERE email=?', [$email])) flash('error', 'Email already in use.');
        elseif ($role === 'doctor' && !(int)post('specialty_id')) flash('error', 'Choose a specialty for the doctor.');
        else {
            db()->beginTransaction();
            q('INSERT INTO users (role, full_name, email, phone, password_hash, email_verified) VALUES (?,?,?,?,?,1)', [$role, $name, $email, post('phone') ?: null, password_hash($pw, PASSWORD_BCRYPT)]);
            $nid = (int)db()->lastInsertId();
            if ($role === 'doctor') {
                q('INSERT INTO doctors (user_id, specialty_id, license_no, consultation_fee, room) VALUES (?,?,?,?,?)', [$nid, (int)post('specialty_id'), post('license_no') ?: null, max(0, (float)post('fee')), post('room') ?: null]);
                $did = (int)db()->lastInsertId();
                q("INSERT INTO doctor_schedules (doctor_id, weekday, start_time, end_time, slot_minutes) SELECT ?, n, '08:00:00', '12:00:00', 30 FROM (SELECT 1 n UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5) w", [$did]);
            }
            db()->commit();
            audit('user.create', "$role $email"); flash('ok', ucfirst($role) . ' account created.');
        }
    } elseif ($id === $u['id'] && in_array($act, ['toggle', 'role'], true)) {
        flash('error', 'You cannot change your own role or status.');
    } elseif ($act === 'toggle') {
        q('UPDATE users SET is_active=1-is_active WHERE id=?', [$id]); audit('user.toggle', "User #$id"); flash('ok', 'User status updated.');
    } elseif ($act === 'role') {
        $target = q_one('SELECT role FROM users WHERE id=?', [$id]);
        $new = post('role');
        if (!$target || $target['role'] === 'doctor' || $new === 'doctor' || !in_array($new, ['patient', 'staff', 'admin'], true)) flash('error', 'Doctor roles are created with the "Add user" form and cannot be switched here.');
        else { q('UPDATE users SET role=? WHERE id=?', [$new, $id]); audit('rbac.role_change', "User #$id to $new"); flash('ok', 'Role updated.'); }
    } elseif ($act === 'reset') {
        $tmp = 'Tmp@' . random_int(100000, 999999);
        q('UPDATE users SET password_hash=?, failed_attempts=0, locked_until=NULL WHERE id=?', [password_hash($tmp, PASSWORD_BCRYPT), $id]);
        audit('user.password_reset', "User #$id"); flash('warn', "Temporary password for user #$id: $tmp (tell the user to change it).");
    }
    redirect('admin/users.php');
}
$filter = get('role'); $search = get('q');
$sql = 'SELECT * FROM users WHERE 1=1'; $par = [];
if ($filter) { $sql .= ' AND role=?'; $par[] = $filter; }
if ($search !== '') { $sql .= ' AND (full_name LIKE ? OR email LIKE ?)'; $par[] = "%$search%"; $par[] = "%$search%"; }
$users = q_all($sql . ' ORDER BY role, full_name', $par);
$specs = q_all('SELECT * FROM specialties ORDER BY name');
$page_title = 'Users & roles';
require __DIR__ . '/../includes/header.php';
?>
<h1>Users &amp; role-based access</h1>
<form method="get" class="card row" role="search">
  <div><label for="q">Search</label><input id="q" name="q" value="<?= e($search) ?>"></div>
  <div><label for="role">Role</label><select id="role" name="role"><option value="">All</option><?php foreach (['patient', 'doctor', 'staff', 'admin'] as $r): ?><option <?= $filter === $r ? 'selected' : '' ?>><?= $r ?></option><?php endforeach; ?></select></div>
  <div style="align-self:end"><button class="btn">Filter</button></div>
</form>
<div class="tablewrap"><table><thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Last login</th><th>Actions</th></tr></thead><tbody>
<?php foreach ($users as $r): ?><tr>
  <td><?= e($r['full_name']) ?></td><td><?= e($r['email']) ?></td>
  <td><?php if ($r['role'] === 'doctor' || $r['id'] == $u['id']): ?><span class="pill"><?= e($r['role']) ?></span>
    <?php else: ?><form method="post" class="actions"><?= csrf_field() ?><input type="hidden" name="action" value="role"><input type="hidden" name="id" value="<?= $r['id'] ?>">
      <select name="role" aria-label="Role for <?= e($r['full_name']) ?>" style="width:auto"><?php foreach (['patient', 'staff', 'admin'] as $x): ?><option <?= $r['role'] === $x ? 'selected' : '' ?>><?= $x ?></option><?php endforeach; ?></select>
      <button class="btn sm sec">Set</button></form><?php endif; ?></td>
  <td><?= $r['is_active'] ? '<span class="badge b-completed">Active</span>' : '<span class="badge b-cancelled">Disabled</span>' ?></td>
  <td><?= e($r['last_login'] ? fmt_dt($r['last_login']) : 'Never') ?></td>
  <td class="actions"><?php if ($r['id'] != $u['id']): ?>
    <?= action_form((int)$r['id'], 'toggle', $r['is_active'] ? 'Disable' : 'Enable', 'danger') ?>
    <?= action_form((int)$r['id'], 'reset', 'Reset password', 'sec', 'Generate a temporary password for this user?') ?><?php endif; ?></td>
</tr><?php endforeach; ?></tbody></table></div>

<form method="post" class="card"><?= csrf_field() ?><input type="hidden" name="action" value="create"><h2>Add staff, doctor or admin</h2>
  <div class="row">
    <div><label for="n_name">Full name</label><input id="n_name" name="full_name" required></div>
    <div><label for="n_email">Email</label><input id="n_email" name="email" type="email" required></div>
    <div><label for="n_phone">Phone</label><input id="n_phone" name="phone"></div>
    <div><label for="n_role">Role</label><select id="n_role" name="role"><option>staff</option><option>doctor</option><option>admin</option></select></div>
    <div><label for="n_pw">Temporary password</label><input id="n_pw" name="password" type="text" required></div>
  </div>
  <h3>Doctor details (only if role is doctor)</h3>
  <div class="row">
    <div><label for="n_spec">Specialty</label><select id="n_spec" name="specialty_id"><option value="">-</option><?php foreach ($specs as $s): ?><option value="<?= $s['id'] ?>"><?= e($s['name']) ?></option><?php endforeach; ?></select></div>
    <div><label for="n_lic">License no.</label><input id="n_lic" name="license_no"></div>
    <div><label for="n_fee">Consultation fee (<?= CURRENCY ?>)</label><input id="n_fee" name="fee" type="number" min="0" step="0.01" value="100"></div>
    <div><label for="n_room">Room</label><input id="n_room" name="room"></div>
  </div>
  <p><button class="btn">Create user</button></p>
</form>
<?php require __DIR__ . '/../includes/footer.php'; ?>
