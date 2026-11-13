<?php
// Session hardening
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
    session_start();
}
csrf_check();

const ROLE_HOME = ['patient' => 'patient/dashboard.php', 'doctor' => 'doctor/dashboard.php', 'staff' => 'staff/queue.php', 'admin' => 'admin/index.php'];

function current_user(): ?array { return $_SESSION['user'] ?? null; }

function require_login(): array {
    $u = current_user();
    if (!$u) { flash('warn', 'Please sign in to continue.'); redirect('login.php'); }
    return $u;
}

/** Role-Based Access Control gate. */
function require_role(string ...$roles): array {
    $u = require_login();
    if (!in_array($u['role'], $roles, true)) {
        audit('rbac.denied', $_SERVER['REQUEST_URI'] ?? '');
        http_response_code(403);
        exit('<!doctype html><meta charset="utf-8"><title>Access denied</title><body style="font-family:sans-serif;padding:2rem"><h1>403 - Access denied</h1><p>Your role does not allow access to this page.</p><p><a href="' . e(url(ROLE_HOME[$u['role']])) . '">Back to my dashboard</a></p></body>');
    }
    return $u;
}

/** Step 1 of login: check password. Returns [ok, message]. */
function attempt_login(string $email, string $password): array {
    $u = q_one('SELECT * FROM users WHERE email=?', [strtolower($email)]);
    if (!$u) { audit('login.failed', 'Unknown email ' . $email); return [false, 'Invalid email or password.']; }
    if ($u['locked_until'] && strtotime($u['locked_until']) > time()) {
        return [false, 'Account temporarily locked after too many failed attempts. Try again in a few minutes.'];
    }
    if (!password_verify($password, $u['password_hash'])) {
        $n = $u['failed_attempts'] + 1;
        $lock = $n >= MAX_FAILED_LOGINS ? date('Y-m-d H:i:s', time() + LOCK_MINUTES * 60) : null;
        q('UPDATE users SET failed_attempts=?, locked_until=? WHERE id=?', [$lock ? 0 : $n, $lock, $u['id']]);
        audit('login.failed', 'Bad password', (int)$u['id']);
        return [false, 'Invalid email or password.'];
    }
    if (!$u['is_active']) return [false, 'This account has been deactivated. Contact the clinic.'];
    if (!$u['email_verified']) return [false, 'Please verify your email first. Check the verification link sent to you.'];
    q('UPDATE users SET failed_attempts=0, locked_until=NULL WHERE id=?', [$u['id']]);

    if ($u['mfa_enabled']) {
        $code = (string)random_int(100000, 999999);
        q('UPDATE users SET mfa_code_hash=?, mfa_expires=? WHERE id=?',
          [password_hash($code, PASSWORD_BCRYPT), date('Y-m-d H:i:s', time() + MFA_MINUTES * 60), $u['id']]);
        send_message($u, 'email', 'Your ' . APP_NAME . ' login code', "Your one-time login code is $code. It expires in " . MFA_MINUTES . ' minutes.', 'mfa');
        session_regenerate_id(true);
        $_SESSION['mfa_user'] = (int)$u['id'];
        if (DEMO_MODE) $_SESSION['demo_code'] = $code;
        return [true, 'mfa'];
    }
    complete_login($u);
    return [true, 'done'];
}

function complete_login(array $u): void {
    session_regenerate_id(true);
    unset($_SESSION['mfa_user'], $_SESSION['demo_code']);
    $_SESSION['user'] = ['id' => (int)$u['id'], 'name' => $u['full_name'], 'email' => $u['email'], 'role' => $u['role']];
    q('UPDATE users SET last_login=NOW(), mfa_code_hash=NULL WHERE id=?', [$u['id']]);
    audit('login.success', 'Role: ' . $u['role'], (int)$u['id']);
}

function logout(): void {
    audit('logout');
    $_SESSION = [];
    session_destroy();
}
