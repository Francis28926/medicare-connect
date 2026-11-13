<?php
// Daily encrypted backup. Windows Task Scheduler:
//   C:\xampp\php\php.exe C:\xampp\htdocs\medicare_connect\cron\backup.php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/backup.php';
echo 'Created ' . create_backup(null) . "\n";
