<?php
require __DIR__ . '/../includes/bootstrap.php';
$u = require_role('admin');
$from = get('from', date('Y-m-d', strtotime('-30 days'))); $to = get('to', date('Y-m-d', strtotime('+30 days')));

$byStatus = q_all('SELECT status, COUNT(*) c FROM appointments WHERE appt_date BETWEEN ? AND ? GROUP BY status', [$from, $to]);
$byDoctor = q_all("SELECT u.full_name, s.name specialty, COUNT(a.id) total,
      SUM(a.status='completed') completed, SUM(a.status='cancelled') cancelled, SUM(a.status='no_show') noshow,
      ROUND(AVG(CASE WHEN a.started_at IS NOT NULL AND a.checked_in_at IS NOT NULL THEN TIMESTAMPDIFF(MINUTE,a.checked_in_at,a.started_at) END),1) avg_wait,
      COALESCE(SUM(p.amount),0) revenue
    FROM doctors d JOIN users u ON u.id=d.user_id JOIN specialties s ON s.id=d.specialty_id
    LEFT JOIN appointments a ON a.doctor_id=d.id AND a.appt_date BETWEEN ? AND ?
    LEFT JOIN payments p ON p.appointment_id=a.id AND p.status='paid'
    GROUP BY d.id ORDER BY total DESC", [$from, $to]);
$total = array_sum(array_column($byStatus, 'c'));
$revenue = array_sum(array_column($byDoctor, 'revenue'));
$wait = q_val("SELECT ROUND(AVG(TIMESTAMPDIFF(MINUTE,checked_in_at,started_at)),1) FROM appointments WHERE appt_date BETWEEN ? AND ? AND started_at IS NOT NULL AND checked_in_at IS NOT NULL", [$from, $to]);
$daily = q_all("SELECT appt_date d, COUNT(*) c FROM appointments WHERE appt_date BETWEEN ? AND ? AND status<>'cancelled' GROUP BY appt_date ORDER BY appt_date", [$from, $to]);

if (get('export') === 'csv') {
    audit('report.export', "$from to $to");
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="medicare_report_' . $from . '_' . $to . '.csv"');
    $o = fopen('php://output', 'w');
    fputcsv($o, ['Doctor', 'Specialty', 'Total', 'Completed', 'Cancelled', 'No-show', 'Avg wait (min)', 'Revenue']);
    foreach ($byDoctor as $r) fputcsv($o, [$r['full_name'], $r['specialty'], $r['total'], $r['completed'], $r['cancelled'], $r['noshow'], $r['avg_wait'], $r['revenue']]);
    exit;
}
$max = max([1] + array_column($daily, 'c'));
$page_title = 'Reports';
require __DIR__ . '/../includes/header.php';
?>
<h1>Reports &amp; analytics</h1>
<form method="get" class="card row"><div><label for="from">From</label><input id="from" type="date" name="from" value="<?= e($from) ?>"></div>
  <div><label for="to">To</label><input id="to" type="date" name="to" value="<?= e($to) ?>"></div>
  <div style="align-self:end" class="actions"><button class="btn">Generate</button>
  <a class="btn sec" href="?<?= e(http_build_query(['from' => $from, 'to' => $to, 'export' => 'csv'])) ?>">Export CSV</a>
  <button type="button" class="btn sec" onclick="window.print()">Print</button></div></form>

<div class="grid g4" style="margin-bottom:18px">
  <div class="stat"><b><?= (int)$total ?></b><span>Appointments</span></div>
  <div class="stat"><b><?= $wait !== null ? e($wait) . ' min' : 'n/a' ?></b><span>Average wait (check-in to consultation)</span></div>
  <div class="stat"><b><?= e(money($revenue)) ?></b><span>Revenue collected</span></div>
  <div class="stat"><b><?= (int)array_sum(array_map(fn($r) => $r['status'] === 'no_show' ? $r['c'] : 0, $byStatus)) ?></b><span>No-shows</span></div>
</div>

<div class="grid g2">
<section class="card"><h2>By status</h2>
  <?php foreach ($byStatus as $r): ?><p><?= status_badge($r['status']) ?> <b><?= (int)$r['c'] ?></b></p><?php endforeach; if (!$byStatus) echo '<p class="muted">No data in range.</p>'; ?></section>
<section class="card"><h2>Appointments per day</h2>
  <?php foreach ($daily as $r): ?>
    <div style="display:flex;align-items:center;gap:8px;margin:3px 0" class="small"><span style="width:92px"><?= e(date('d M', strtotime($r['d']))) ?></span>
      <span style="background:var(--teal);height:14px;border-radius:4px;width:<?= max(4, round($r['c'] / $max * 100)) ?>%" role="img" aria-label="<?= (int)$r['c'] ?> appointments"></span><b><?= (int)$r['c'] ?></b></div>
  <?php endforeach; if (!$daily) echo '<p class="muted">No data in range.</p>'; ?></section>
</div>

<h2>Doctor performance</h2>
<div class="tablewrap"><table><thead><tr><th>Doctor</th><th>Specialty</th><th>Total</th><th>Completed</th><th>Cancelled</th><th>No-show</th><th>Avg wait</th><th>Revenue</th></tr></thead><tbody>
<?php foreach ($byDoctor as $r): ?><tr><td><?= e($r['full_name']) ?></td><td><?= e($r['specialty']) ?></td><td><?= (int)$r['total'] ?></td><td><?= (int)$r['completed'] ?></td><td><?= (int)$r['cancelled'] ?></td><td><?= (int)$r['noshow'] ?></td><td><?= $r['avg_wait'] !== null ? e($r['avg_wait']) . ' min' : '-' ?></td><td><?= e(money($r['revenue'])) ?></td></tr><?php endforeach; ?>
</tbody></table></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
