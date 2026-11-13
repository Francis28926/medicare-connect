<?php
require __DIR__ . '/includes/bootstrap.php';
if (current_user()) redirect(ROLE_HOME[current_user()['role']]);

$err = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = post('full_name'); $email = strtolower(post('email')); $phone = post('phone');
    $pw = $_POST['password'] ?? ''; $pw2 = $_POST['password2'] ?? '';
    $dob = post('dob'); $gender = post('gender');

    if (mb_strlen($name) < 3) $err[] = 'Please enter your full name.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $err[] = 'Enter a valid email address.';
    if (!preg_match('/^\+?[0-9 ]{9,18}$/', $phone)) $err[] = 'Enter a valid phone number (for SMS reminders).';
    if (strlen($pw) < 8 || !preg_match('/[A-Z]/', $pw) || !preg_match('/[a-z]/', $pw) || !preg_match('/\d/', $pw))
        $err[] = 'Password must be at least 8 characters with upper-case, lower-case and a number.';
    if ($pw !== $pw2) $err[] = 'Passwords do not match.';
    if ($dob && ($dob > date('Y-m-d') || !strtotime($dob))) $err[] = 'Date of birth is invalid.';
    if (!in_array($gender, ['female', 'male', 'other', ''], true)) $err[] = 'Invalid gender.';
    if (!$err && q_val('SELECT id FROM users WHERE email=?', [$email])) $err[] = 'An account with this email already exists.';

    if (!$err) {
        $token = bin2hex(random_bytes(24));
        db()->beginTransaction();
        q('INSERT INTO users (role, full_name, email, phone, password_hash, verify_token) VALUES (?,?,?,?,?,?)',
          ['patient', $name, $email, $phone, password_hash($pw, PASSWORD_BCRYPT), $token]);
        $uid = (int)db()->lastInsertId();
        q('INSERT INTO patients (user_id, dob, gender) VALUES (?,?,?)', [$uid, $dob ?: null, $gender ?: null]);
        db()->commit();

        $link = 'http://' . $_SERVER['HTTP_HOST'] . url('verify.php?token=' . $token);
        send_message(['id' => $uid, 'phone' => $phone], 'email', 'Verify your ' . APP_NAME . ' account',
            "Welcome, $name! Confirm your email address by opening this link: $link", 'verify');
        audit('register', 'New patient ' . $email, $uid);
        if (DEMO_MODE) $_SESSION['demo_verify_link'] = $link;
        redirect('verify.php');
    }
}
$page_title = 'Create account';
require __DIR__ . '/includes/header.php';
?>
<div class="card formcard" style="max-width:640px">
  <h1>Create a patient account</h1>
  <?php foreach ($err as $m): ?><div class="alert a-error" role="alert"><?= e($m) ?></div><?php endforeach; ?>
  <form method="post" novalidate>
    <?= csrf_field() ?>
    <label for="full_name">Full name</label>
    <input id="full_name" name="full_name" required autocomplete="name" value="<?= e(post('full_name')) ?>">
    <div class="row">
      <div><label for="email">Email</label><input id="email" name="email" type="email" required autocomplete="email" value="<?= e(post('email')) ?>"></div>
      <div><label for="phone">Phone (SMS)</label><input id="phone" name="phone" type="tel" required autocomplete="tel" placeholder="+233 24 000 0000" value="<?= e(post('phone')) ?>"></div>
    </div>
    <div class="row">
      <div><label for="dob">Date of birth</label><input id="dob" name="dob" type="date" max="<?= date('Y-m-d') ?>" value="<?= e(post('dob')) ?>"></div>
      <div><label for="gender">Gender</label>
        <select id="gender" name="gender"><option value="">Prefer not to say</option>
        <?php foreach (['female' => 'Female', 'male' => 'Male', 'other' => 'Other'] as $k => $v): ?>
          <option value="<?= $k ?>" <?= post('gender') === $k ? 'selected' : '' ?>><?= $v ?></option>
        <?php endforeach; ?></select></div>
    </div>
    <div class="row">
      <div><label for="password">Password</label><input id="password" name="password" type="password" required autocomplete="new-password"><div class="hint">8+ characters, upper &amp; lower case, a number.</div></div>
      <div><label for="password2">Confirm password</label><input id="password2" name="password2" type="password" required autocomplete="new-password"></div>
    </div>
    <p><button class="btn">Register</button></p>
  </form>
  <p>Already registered? <a href="<?= e(url('login.php')) ?>">Sign in</a></p>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
