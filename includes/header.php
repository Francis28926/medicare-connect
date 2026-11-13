<?php
$u = current_user();
$title = $page_title ?? APP_NAME;
$cur = basename($_SERVER['SCRIPT_NAME']);
function nav_link(string $path, string $label, string $cur): string {
    $active = basename($path) === $cur ? ' aria-current="page"' : '';
    return '<li><a href="' . e(url($path)) . '"' . $active . '>' . e($label) . '</a></li>';
}
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> | <?= e(APP_NAME) ?></title>
<link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
</head>
<body>
<a class="skip" href="#main">Skip to main content</a>
<header class="topbar">
  <div class="wrap bar">
    <a class="brand" href="<?= e(url($u ? ROLE_HOME[$u['role']] : 'index.php')) ?>"><span class="logo" aria-hidden="true">+</span> <?= e(APP_NAME) ?></a>
    <button class="menu-btn" aria-expanded="false" aria-controls="nav" onclick="var n=document.getElementById('nav');var o=n.classList.toggle('open');this.setAttribute('aria-expanded',o)">Menu</button>
    <nav id="nav" aria-label="Main navigation">
      <ul>
      <?php if (!$u): ?>
        <?= nav_link('index.php', 'Home', $cur) ?>
        <?= nav_link('login.php', 'Sign in', $cur) ?>
        <?= nav_link('register.php', 'Register', $cur) ?>
      <?php elseif ($u['role'] === 'patient'): $n = unread_count($u['id']); ?>
        <?= nav_link('patient/dashboard.php', 'Dashboard', $cur) ?>
        <?= nav_link('patient/book.php', 'Book', $cur) ?>
        <?= nav_link('patient/appointments.php', 'Appointments', $cur) ?>
        <?= nav_link('patient/records.php', 'Records', $cur) ?>
        <?= nav_link('patient/profile.php', 'Profile', $cur) ?>
        <?= nav_link('patient/notifications.php', 'Alerts' . ($n ? " ($n)" : ''), $cur) ?>
      <?php elseif ($u['role'] === 'doctor'): ?>
        <?= nav_link('doctor/dashboard.php', 'Today', $cur) ?>
        <?= nav_link('doctor/patients.php', 'Patients', $cur) ?>
        <?= nav_link('doctor/schedule.php', 'My schedule', $cur) ?>
      <?php elseif ($u['role'] === 'staff'): ?>
        <?= nav_link('staff/queue.php', 'Queue', $cur) ?>
        <?= nav_link('staff/appointments.php', 'Appointments', $cur) ?>
        <?= nav_link('staff/walkin.php', 'Walk-in booking', $cur) ?>
      <?php else: ?>
        <?= nav_link('admin/index.php', 'Overview', $cur) ?>
        <?= nav_link('staff/queue.php', 'Queue', $cur) ?>
        <?= nav_link('admin/users.php', 'Users & roles', $cur) ?>
        <?= nav_link('admin/reports.php', 'Reports', $cur) ?>
        <?= nav_link('admin/logs.php', 'Audit log', $cur) ?>
        <?= nav_link('admin/outbox.php', 'Outbox', $cur) ?>
        <?= nav_link('admin/backup.php', 'Backups', $cur) ?>
      <?php endif; ?>
      <?php if ($u): ?>
        <li><form method="post" action="<?= e(url('logout.php')) ?>" class="inline"><?= csrf_field() ?><button class="linklike">Sign out (<?= e(explode(' ', $u['name'])[0]) ?>)</button></form></li>
      <?php endif; ?>
      </ul>
    </nav>
  </div>
</header>
<main id="main" class="wrap">
<?php foreach (take_flashes() as [$t, $m]): ?>
  <div class="alert a-<?= e($t) ?>" role="<?= $t === 'error' ? 'alert' : 'status' ?>"><?= e($m) ?></div>
<?php endforeach; ?>
