<?php
require __DIR__ . '/includes/bootstrap.php';
if (current_user()) redirect(ROLE_HOME[current_user()['role']]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    [$ok, $res] = attempt_login(post('email'), $_POST['password'] ?? '');
    if (!$ok) flash('error', $res);
    elseif ($res === 'mfa') redirect('mfa.php');
    else redirect(ROLE_HOME[current_user()['role']]);
}
$page_title = 'Sign in';
require __DIR__ . '/includes/header.php';
?>
<div class="card formcard">
  <h1>Sign in</h1>
  <form method="post" novalidate>
    <?= csrf_field() ?>
    <label for="email">Email address</label>
    <input id="email" name="email" type="email" autocomplete="username" required value="<?= e(post('email')) ?>">
    <label for="password">Password</label>
    <input id="password" name="password" type="password" autocomplete="current-password" required>
    <p><button class="btn">Continue</button></p>
    <p class="hint">A one-time code is sent to your email as a second step (multi-factor login).</p>
  </form>
  <p>New patient? <a href="<?= e(url('register.php')) ?>">Create an account</a></p>
  <?php if (DEMO_MODE): ?>
  <div class="demo small"><b>Demo accounts</b> (password <code style="font-size:.9rem">Password@123</code>)<br>
    Admin: admin@medicare.test<br>Reception: staff@medicare.test<br>Doctor: kwame@medicare.test<br>Patient: patient@medicare.test</div>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
