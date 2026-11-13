<?php
// MediCare Connect - configuration (XAMPP defaults)
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'medicare_connect');
define('DB_USER', 'root');
define('DB_PASS', '');          // XAMPP default: empty password
define('DB_PORT', 3306);

define('APP_NAME', 'MediCare Connect');
define('CURRENCY', 'GHS');

// DEMO_MODE = true: because XAMPP cannot send real email/SMS, verification links and
// login (MFA) codes are shown on screen and every message is saved in the Outbox
// (Admin > Outbox). Set to false for production and plug in Twilio/SendGrid
// (see dispatch_email() / dispatch_sms() in includes/notify.php).
define('DEMO_MODE', true);

define('BACKUP_KEY', 'change-this-secret-key-before-real-use'); // AES-256 key source for backups
define('MFA_MINUTES', 10);
define('MAX_FAILED_LOGINS', 5);
define('LOCK_MINUTES', 5);

date_default_timezone_set('Africa/Accra');

// Auto-detect the URL path of the project (e.g. /medicare_connect)
$root = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: '');
$here = str_replace('\\', '/', dirname(__DIR__));
$base = ($root && strpos($here, $root) === 0) ? substr($here, strlen($root)) : '/medicare_connect';
define('BASE_URL', rtrim($base, '/'));
