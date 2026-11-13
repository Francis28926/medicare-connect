<?php
require __DIR__ . '/../includes/bootstrap.php';
$u = require_role('patient');
$p = patient_of($u['id']);
$id = (int)get('id', post('id'));
$a = q_one("SELECT a.*, du.full_name doctor_name, d.consultation_fee FROM appointments a JOIN doctors d ON d.id=a.doctor_id JOIN users du ON du.id=d.user_id
    WHERE a.id=? AND a.patient_id=? AND a.status NOT IN ('cancelled','no_show')", [$id, $p['id']]);
if (!$a) { flash('error', 'Appointment not found.'); redirect('patient/appointments.php'); }
if (q_val('SELECT id FROM payments WHERE appointment_id=?', [$id])) { flash('info', 'This appointment is already paid.'); redirect('patient/appointments.php'); }

$err = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // SIMULATED gateway (Stripe/PayPal stand-in). Only the last 4 digits are kept; the full card number is never stored.
    $num = preg_replace('/\D/', '', post('card'));
    $exp = post('exp'); $cvc = preg_replace('/\D/', '', post('cvc'));
    $luhn = function ($n) { $s = 0; foreach (array_reverse(str_split($n)) as $i => $d) { $d = (int)$d; if ($i % 2) { $d *= 2; if ($d > 9) $d -= 9; } $s += $d; } return $s % 10 === 0; };
    if (strlen($num) < 13 || strlen($num) > 19 || !$luhn($num)) $err = 'Card number is not valid. For the demo use 4242 4242 4242 4242.';
    elseif (!preg_match('#^(0[1-9]|1[0-2])/\d{2}$#', $exp)) $err = 'Expiry must look like MM/YY.';
    elseif (strlen($cvc) < 3) $err = 'Enter the 3-digit security code.';
    else {
        $ref = 'PAY-' . strtoupper(bin2hex(random_bytes(4)));
        q("INSERT INTO payments (appointment_id, patient_id, amount, method, card_last4, reference) VALUES (?,?,?,?,?,?)",
          [$id, $p['id'], $a['consultation_fee'], 'Card (simulated)', substr($num, -4), $ref]);
        notify_user($u['id'], 'Payment received', 'We received ' . money($a['consultation_fee']) . ' for your appointment on ' . fmt_date($a['appt_date']) . '. Reference: ' . $ref, 'payment', $id);
        audit('payment.paid', "Appointment #$id $ref");
        flash('ok', 'Payment successful. Reference ' . $ref);
        redirect('patient/appointments.php');
    }
}
$page_title = 'Pay for appointment';
require __DIR__ . '/../includes/header.php';
?>
<div class="card formcard">
  <h1>Pay consultation fee</h1>
  <p>Appointment with <b><?= e($a['doctor_name']) ?></b><br><?= e(fmt_date($a['appt_date'])) ?>, <?= e(fmt_time($a['appt_time'])) ?></p>
  <p class="queue-now" style="font-size:1.8rem"><?= e(money($a['consultation_fee'])) ?></p>
  <div class="demo small">Demo payment gateway (no real money). Use test card <code style="font-size:.9rem">4242 4242 4242 4242</code>, any future expiry, any CVC.</div>
  <?php if ($err): ?><div class="alert a-error" role="alert"><?= e($err) ?></div><?php endif; ?>
  <form method="post" autocomplete="off">
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>">
    <label for="card">Card number</label><input id="card" name="card" inputmode="numeric" placeholder="4242 4242 4242 4242" required>
    <div class="row">
      <div><label for="exp">Expiry (MM/YY)</label><input id="exp" name="exp" placeholder="12/29" required></div>
      <div><label for="cvc">CVC</label><input id="cvc" name="cvc" inputmode="numeric" maxlength="4" required></div>
    </div>
    <p><button class="btn">Pay <?= e(money($a['consultation_fee'])) ?></button> <a class="btn sec" href="<?= e(url('patient/appointments.php')) ?>">Back</a></p>
  </form>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
