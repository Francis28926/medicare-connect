<?php
require __DIR__ . '/../includes/bootstrap.php';
$u = require_role('doctor');
$doc = doctor_of($u['id']);
$days = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (post('action') === 'add') {
        $wd = (int)post('weekday'); $s = post('start'); $e = post('end'); $m = (int)post('minutes');
        if (!isset($days[$wd]) || !$s || !$e || $s >= $e || !in_array($m, [10, 15, 20, 30, 45, 60], true)) flash('error', 'Enter a valid day, start/end time and slot length.');
        elseif (q_val('SELECT id FROM doctor_schedules WHERE doctor_id=? AND weekday=? AND start_time<? AND end_time>?', [$doc['id'], $wd, $e, $s])) flash('error', 'That overlaps an existing session.');
        else { q('INSERT INTO doctor_schedules (doctor_id, weekday, start_time, end_time, slot_minutes) VALUES (?,?,?,?,?)', [$doc['id'], $wd, $s, $e, $m]); audit('schedule.add', $days[$wd] . " $s-$e"); flash('ok', 'Session added.'); }
    } elseif (post('action') === 'delete') {
        q('DELETE FROM doctor_schedules WHERE id=? AND doctor_id=?', [(int)post('id'), $doc['id']]); audit('schedule.delete', 'Session #' . (int)post('id')); flash('ok', 'Session removed.');
    }
    redirect('doctor/schedule.php');
}
$rows = q_all('SELECT * FROM doctor_schedules WHERE doctor_id=? ORDER BY weekday, start_time', [$doc['id']]);
$page_title = 'My schedule';
require __DIR__ . '/../includes/header.php';
?>
<h1>My weekly schedule</h1>
<div class="tablewrap"><table><thead><tr><th>Day</th><th>From</th><th>To</th><th>Slot length</th><th></th></tr></thead><tbody>
<?php foreach ($rows as $r): ?><tr><td><?= $days[$r['weekday']] ?></td><td><?= e(fmt_time($r['start_time'])) ?></td><td><?= e(fmt_time($r['end_time'])) ?></td><td><?= (int)$r['slot_minutes'] ?> min</td>
  <td><form method="post" data-confirm="Remove this session? Existing bookings are kept."><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $r['id'] ?>"><button class="btn sm danger">Remove</button></form></td></tr>
<?php endforeach; if (!$rows): ?><tr><td colspan="5" class="muted">No sessions yet.</td></tr><?php endif; ?></tbody></table></div>
<form method="post" class="card"><?= csrf_field() ?><input type="hidden" name="action" value="add"><h2>Add a session</h2>
  <div class="row">
    <div><label for="weekday">Day</label><select id="weekday" name="weekday"><?php foreach ($days as $k => $d): ?><option value="<?= $k ?>"><?= $d ?></option><?php endforeach; ?></select></div>
    <div><label for="start">From</label><input id="start" type="time" name="start" required></div>
    <div><label for="end">To</label><input id="end" type="time" name="end" required></div>
    <div><label for="minutes">Slot length</label><select id="minutes" name="minutes"><?php foreach ([10, 15, 20, 30, 45, 60] as $m): ?><option <?= $m === 30 ? 'selected' : '' ?> value="<?= $m ?>"><?= $m ?> min</option><?php endforeach; ?></select></div>
  </div><p><button class="btn">Add session</button></p></form>
<?php require __DIR__ . '/../includes/footer.php'; ?>
