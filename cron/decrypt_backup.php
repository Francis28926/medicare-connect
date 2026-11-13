<?php
// Usage: php cron/decrypt_backup.php storage/backups/FILE.sql.enc > restore.sql
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
require __DIR__ . '/../config/config.php';
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/backup.php';
$f = $argv[1] ?? '';
if (!is_file($f)) { fwrite(STDERR, "Usage: php decrypt_backup.php <file.sql.enc>\n"); exit(1); }
$plain = decrypt_backup(file_get_contents($f));
if ($plain === null) { fwrite(STDERR, "Decryption failed (wrong key or corrupted file).\n"); exit(2); }
echo $plain;
