<?php
/**
 * Automated Backup & Encryption (SRS 3.3).
 * Dumps the whole database to SQL in pure PHP (no mysqldump needed) and encrypts it with AES-256-GCM.
 * File layout: "MCB1" | iv (12 bytes) | tag (16 bytes) | ciphertext
 */
function backup_key(): string { return hash('sha256', BACKUP_KEY, true); }

function dump_database(): string {
    $pdo = db();
    $out = "-- MediCare Connect backup " . date('c') . "\nSET FOREIGN_KEY_CHECKS=0;\n";
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $t) {
        $create = $pdo->query("SHOW CREATE TABLE `$t`")->fetch(PDO::FETCH_NUM)[1];
        $out .= "\nDROP TABLE IF EXISTS `$t`;\n$create;\n";
        $st = $pdo->query("SELECT * FROM `$t`");
        while ($row = $st->fetch(PDO::FETCH_NUM)) {
            $vals = array_map(fn($v) => $v === null ? 'NULL' : $pdo->quote((string)$v), $row);
            $out .= "INSERT INTO `$t` VALUES (" . implode(',', $vals) . ");\n";
        }
    }
    return $out . "\nSET FOREIGN_KEY_CHECKS=1;\n";
}

function encrypt_backup(string $plain): string {
    $iv = random_bytes(12);
    $cipher = openssl_encrypt($plain, 'aes-256-gcm', backup_key(), OPENSSL_RAW_DATA, $iv, $tag);
    return 'MCB1' . $iv . $tag . $cipher;
}

function decrypt_backup(string $blob): ?string {
    if (substr($blob, 0, 4) !== 'MCB1') return null;
    $iv = substr($blob, 4, 12); $tag = substr($blob, 16, 16); $cipher = substr($blob, 32);
    $plain = openssl_decrypt($cipher, 'aes-256-gcm', backup_key(), OPENSSL_RAW_DATA, $iv, $tag);
    return $plain === false ? null : $plain;
}

function create_backup(?int $user_id = null): string {
    $dir = __DIR__ . '/../storage/backups';
    if (!is_dir($dir)) mkdir($dir, 0775, true);
    $name = 'medicare_' . date('Ymd_His') . '.sql.enc';
    $blob = encrypt_backup(dump_database());
    file_put_contents("$dir/$name", $blob);
    q('INSERT INTO backups (filename, size_bytes, created_by) VALUES (?,?,?)', [$name, strlen($blob), $user_id]);
    audit('backup.create', $name, $user_id);
    return $name;
}
