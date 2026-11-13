<?php
function e($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function url(string $path = ''): string { return BASE_URL . '/' . ltrim($path, '/'); }
function redirect(string $path): void { header('Location: ' . url($path)); exit; }
function post(string $k, $d = ''): string { return trim((string)($_POST[$k] ?? $d)); }
function get(string $k, $d = ''): string { return trim((string)($_GET[$k] ?? $d)); }
function client_ip(): string { return $_SERVER['REMOTE_ADDR'] ?? ''; }

function fmt_date(?string $d): string { return $d ? date('D, d M Y', strtotime($d)) : '-'; }
function fmt_time(?string $t): string { return $t ? date('g:i A', strtotime($t)) : '-'; }
function fmt_dt(?string $d): string { return $d ? date('d M Y, g:i A', strtotime($d)) : '-'; }
function money($n): string { return CURRENCY . ' ' . number_format((float)$n, 2); }

function flash(string $type, string $msg): void { $_SESSION['flash'][] = [$type, $msg]; }
function take_flashes(): array { $f = $_SESSION['flash'] ?? []; unset($_SESSION['flash']); return $f; }

// ---- CSRF ----
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">'; }
function csrf_check(): void {
    if (PHP_SAPI === 'cli') return;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $ok = isset($_POST['csrf']) && hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf']);
        if (!$ok) { http_response_code(419); exit('Session expired or invalid request. Go back, refresh the page and try again.'); }
    }
}

// ---- Audit log ----
function audit(string $action, string $details = '', ?int $user_id = null): void {
    $uid = $user_id ?? ($_SESSION['user']['id'] ?? null);
    q('INSERT INTO audit_logs (user_id, action, details, ip) VALUES (?,?,?,?)', [$uid, $action, mb_substr($details, 0, 250), client_ip()]);
}

function status_badge(string $s): string {
    $labels = ['booked'=>'Booked','checked_in'=>'Waiting','in_consultation'=>'In consultation','completed'=>'Completed','cancelled'=>'Cancelled','no_show'=>'No-show'];
    return '<span class="badge b-' . e($s) . '">' . e($labels[$s] ?? $s) . '</span>';
}

/** Patient record row for a logged-in patient user. */
function patient_of(int $user_id): ?array { return q_one('SELECT * FROM patients WHERE user_id=?', [$user_id]); }
function doctor_of(int $user_id): ?array { return q_one('SELECT d.*, s.name specialty FROM doctors d JOIN specialties s ON s.id=d.specialty_id WHERE d.user_id=?', [$user_id]); }

/**
 * Available slots for a doctor on a date (honours schedule, existing bookings and past times).
 * Returns array of 'HH:MM:SS' strings.
 */
function available_slots(int $doctor_id, string $date): array {
    $ts = strtotime($date);
    if (!$ts || $date < date('Y-m-d')) return [];
    $weekday = (int)date('N', $ts);
    $scheds = q_all('SELECT * FROM doctor_schedules WHERE doctor_id=? AND weekday=? ORDER BY start_time', [$doctor_id, $weekday]);
    $taken = array_column(q_all("SELECT appt_time FROM appointments WHERE doctor_id=? AND appt_date=? AND status<>'cancelled'", [$doctor_id, $date]), 'appt_time');
    $slots = [];
    foreach ($scheds as $s) {
        $t = strtotime($date . ' ' . $s['start_time']);
        $end = strtotime($date . ' ' . $s['end_time']);
        while ($t + $s['slot_minutes'] * 60 <= $end) {
            $hms = date('H:i:s', $t);
            if (!in_array($hms, $taken, true) && $t > time()) $slots[] = $hms;
            $t += $s['slot_minutes'] * 60;
        }
    }
    return $slots;
}

/** Books a slot safely (unique slot_key stops double booking). */
function book_slot(int $patient_id, int $doctor_id, string $date, string $time, string $reason): array {
    if (!in_array($time, available_slots($doctor_id, $date), true)) return [false, 'Sorry, that slot is no longer available.'];
    try {
        q("INSERT INTO appointments (patient_id, doctor_id, appt_date, appt_time, reason, slot_key) VALUES (?,?,?,?,?,?)",
          [$patient_id, $doctor_id, $date, $time, mb_substr($reason, 0, 250), "$doctor_id-$date-" . substr($time, 0, 5)]);
    } catch (PDOException $e) {
        return [false, 'Sorry, someone just booked that slot. Please pick another.'];
    }
    return [true, (int)db()->lastInsertId()];
}



/** Reception actions shared by the queue board and appointment list. */
function staff_handle_action(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
    $id = (int)post('id'); $act = post('action');
    $a = q_one('SELECT a.*, p.user_id patient_user FROM appointments a JOIN patients p ON p.id=a.patient_id WHERE a.id=?', [$id]);
    if (!$a) return;
    if ($act === 'checkin' && $a['status'] === 'booked') {
        db()->beginTransaction();
        $n = (int)q_val('SELECT COALESCE(MAX(queue_number),0)+1 FROM appointments WHERE doctor_id=? AND appt_date=? FOR UPDATE', [$a['doctor_id'], $a['appt_date']]);
        q("UPDATE appointments SET status='checked_in', queue_number=?, checked_in_at=NOW() WHERE id=?", [$n, $id]);
        db()->commit();
        notify_user((int)$a['patient_user'], 'You are checked in', "You are checked in. Your queue number is #$n. Follow your live position on your dashboard.", 'queue', $id);
        audit('queue.checkin', "Appointment #$id queue #$n");
        flash('ok', "Checked in. Queue number #$n.");
    } elseif ($act === 'noshow' && in_array($a['status'], ['booked', 'checked_in'], true)) {
        q("UPDATE appointments SET status='no_show', slot_key=NULL WHERE id=?", [$id]);
        audit('appointment.noshow', "Appointment #$id");
        flash('info', 'Marked as no-show.');
    } elseif ($act === 'cancel' && in_array($a['status'], ['booked', 'checked_in'], true)) {
        q("UPDATE appointments SET status='cancelled', slot_key=NULL WHERE id=?", [$id]);
        notify_user((int)$a['patient_user'], 'Appointment cancelled', 'Your appointment on ' . fmt_date($a['appt_date']) . ' at ' . fmt_time($a['appt_time']) . ' was cancelled by the clinic.', 'cancel', $id);
        audit('appointment.cancel', "Appointment #$id by staff");
        flash('info', 'Appointment cancelled.');
    }
    header('Location: ' . $_SERVER['REQUEST_URI']); exit;
}

function action_form(int $id, string $action, string $label, string $cls = '', string $confirm = ''): string {
    return '<form method="post"' . ($confirm ? ' data-confirm="' . e($confirm) . '"' : '') . '>' . csrf_field()
        . '<input type="hidden" name="id" value="' . $id . '"><input type="hidden" name="action" value="' . e($action) . '">'
        . '<button class="btn sm ' . $cls . '">' . e($label) . '</button></form>';
}
