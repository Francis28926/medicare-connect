<?php
require __DIR__ . '/includes/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && current_user()) logout();
header('Location: ' . url('login.php'));
exit;
