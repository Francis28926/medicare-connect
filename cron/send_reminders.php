<?php
// Run from the command line / Windows Task Scheduler (e.g. every 30 minutes):
//   C:\xampp\php\php.exe C:\xampp\htdocs\medicare_connect\cron\send_reminders.php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
require __DIR__ . '/../includes/bootstrap.php';
echo run_reminder_job() . " reminder(s) sent\n";
