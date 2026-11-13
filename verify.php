<?php
require __DIR__ . '/includes/bootstrap.php';
$token = get('token');
$state = 'wait';
if ($token !== '') {
    $u = q_one('SELECT id FROM users WHERE verify_token=? AND email_verified=0', [$token]);
    if ($u) {
        q('UPDATE users SET email_verified=1, verify_token=NULL WHERE id=?', [$u['id']]);
        audit('email.verified', '', (int)$u['id']);
        unset($_SESSION['demo_verify_link']);
        $state = 'ok';
    } else $state = 'bad';
}
$page_title = 'Verify email';
require __DIR__ . '/includes/header.php';
?>
<div class="card formcard">
<?php if ($state === 'ok'): ?>
  <h1>Email verified</h1><p>Your account is active. You can now sign in.</p>
  <p><a class="btn" href="<?= e(url('login.php')) ?>">Sign in</a></p>
<?php elseif ($state === 'bad'): ?>
  <h1>Link not valid</h1><p>This verification link is invalid or has already been used.</p>
  <p><a class="btn sec" href="<?= e(url('login.php')) ?>">Go to sign in</a></p>
<?php else: ?>
  <h1>Check your email</h1>
  <p>We sent a verification link to your email address. Open it to activate your account.</p>
  <?php if (DEMO_MODE && !empty($_SESSION['demo_verify_link'])): ?>
    <div class="demo">Demo mode: email is simulated. <a href="<?= e($_SESSION['demo_verify_link']) ?>"><b>Click here to verify now</b></a></div>
  <?php endif; ?>
<?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
