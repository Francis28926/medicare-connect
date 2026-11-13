<?php
require __DIR__ . '/includes/bootstrap.php';
$uid = $_SESSION['mfa_user'] ?? null;
if (!$uid) redirect('login.php');
$u = q_one('SELECT * FROM users WHERE id=?', [$uid]);
if (!$u) { unset($_SESSION['mfa_user']); redirect('login.php'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = preg_replace('/\D/', '', post('code'));
    if ($u && $u['mfa_code_hash'] && strtotime($u['mfa_expires']) > time() && password_verify($code, $u['mfa_code_hash'])) {
        complete_login($u);
        redirect(ROLE_HOME[$u['role']]);
    }
    audit('mfa.failed', 'Wrong or expired code', (int)$uid);
    flash('error', 'That code is incorrect or has expired.');
}
$page_title = 'Verify login';
require __DIR__ . '/includes/header.php';
?>
<div class="card formcard">
  <h1>Enter your login code</h1>
  <p class="muted">We sent a 6-digit code to <b><?= e($u['email']) ?></b>. It expires in <?= (int)MFA_MINUTES ?> minutes.</p>
  <?php if (DEMO_MODE && !empty($_SESSION['demo_code'])): ?>
    <div class="demo">Demo mode: email is simulated. Your code is <code><?= e($_SESSION['demo_code']) ?></code></div>
  <?php endif; ?>
  <form method="post">
    <?= csrf_field() ?>
    <label for="code">6-digit code</label>
    <input id="code" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" required autofocus>
    <p><button class="btn">Verify and sign in</button> <a class="btn sec" href="<?= e(url('login.php')) ?>">Cancel</a></p>
  </form>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
