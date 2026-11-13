<?php
/**
 * Notification layer.
 * Every email/SMS is saved to the `notifications` table (the "Outbox") and handed to a
 * dispatcher. In this local build the dispatchers are simulated; replace the bodies of
 * dispatch_email() / dispatch_sms() with SendGrid / Twilio API calls for production.
 */
function dispatch_email(array $user, string $subject, string $body): string {
    // TODO production: SendGrid API call here.
    return 'simulated';
}
function dispatch_sms(array $user, string $body): string {
    // TODO production: Twilio API call here.
    return 'simulated';
}

/** Send one message on one channel (email | sms | inapp). */
function send_message(array $user, string $channel, string $subject, string $body, string $kind = 'general', ?int $appt_id = null): void {
    $status = 'sent';
    if ($channel === 'email') $status = dispatch_email($user, $subject, $body);
    if ($channel === 'sms') {
        if (empty($user['phone'])) return;
        $status = dispatch_sms($user, $body);
    }
    q('INSERT INTO notifications (user_id, channel, kind, subject, body, appointment_id, status) VALUES (?,?,?,?,?,?,?)',
      [$user['id'], $channel, $kind, $subject, $body, $appt_id, $status]);
}

/** In-app + email + SMS together. */
function notify_user(int $user_id, string $subject, string $body, string $kind = 'general', ?int $appt_id = null): void {
    $u = q_one('SELECT id, full_name, email, phone FROM users WHERE id=?', [$user_id]);
    if (!$u) return;
    foreach (['inapp', 'email', 'sms'] as $ch) send_message($u, $ch, $subject, $body, $kind, $appt_id);
}

function unread_count(int $user_id): int {
    return (int)q_val("SELECT COUNT(*) FROM notifications WHERE user_id=? AND channel='inapp' AND read_at IS NULL", [$user_id]);
}

/**
 * Reminder job (SRS 3.3): notify patients whose consultation starts within the next 24 hours.
 * Run from the browser (Admin > Dashboard), or from CLI / Windows Task Scheduler:
 *   C:\xampp\php\php.exe C:\xampp\htdocs\medicare_connect\cron\send_reminders.php
 */
function run_reminder_job(): int {
    $rows = q_all("SELECT a.id, a.appt_date, a.appt_time, p.user_id patient_user, du.full_name doctor_name
        FROM appointments a
        JOIN patients p ON p.id=a.patient_id
        JOIN doctors d ON d.id=a.doctor_id JOIN users du ON du.id=d.user_id
        WHERE a.status='booked' AND a.reminder_sent=0
          AND TIMESTAMP(a.appt_date, a.appt_time) > NOW()
          AND TIMESTAMP(a.appt_date, a.appt_time) <= (NOW() + INTERVAL 24 HOUR)");
    foreach ($rows as $r) {
        notify_user((int)$r['patient_user'], 'Appointment reminder',
            'Reminder: you have an appointment with ' . $r['doctor_name'] . ' on ' . fmt_date($r['appt_date']) . ' at ' . fmt_time($r['appt_time']) . '.',
            'reminder', (int)$r['id']);
        q('UPDATE appointments SET reminder_sent=1 WHERE id=?', [$r['id']]);
    }
    if ($rows) audit('reminders.sent', count($rows) . ' reminder(s) dispatched');
    return count($rows);
}
