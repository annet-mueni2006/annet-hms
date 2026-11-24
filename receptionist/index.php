<?php
declare(strict_types=1);
require dirname(__DIR__) . '/config/bootstrap.php';
require dirname(__DIR__) . '/includes/layout.php';
require dirname(__DIR__) . '/includes/clinic.php';

$user = require_role('receptionist', 'admin');
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'register_patient') {
        [$d, $err] = patient_input($_POST);
        if ($err) {
            foreach ($err as $m) flash('error', $m);
        } else {
            $id = create_patient_record($d);
            audit('patient_created', "id $id");
            flash('success', 'Patient registered.');
        }
    } elseif ($action === 'book') {
        $pid = post_int('patient_id');
        $did = post_int('doctor_id');
        $when = future_datetime((string)($_POST['scheduled_at'] ?? ''));
        $okP = $pdo->prepare('SELECT 1 FROM patients WHERE id = ?');
        $okP->execute([$pid]);
        $okD = $pdo->prepare("SELECT 1 FROM users WHERE id = ? AND role='doctor' AND is_active=1");
        $okD->execute([$did]);
        if (!$okP->fetch() || !$okD->fetch()) {
            flash('error', 'Choose a patient and a doctor.');
        } elseif (!$when) {
            flash('error', 'Choose a date and time in the future.');
        } else {
            $clash = $pdo->prepare("SELECT 1 FROM appointments WHERE doctor_id = ? AND scheduled_at = ? AND status <> 'cancelled'");
            $clash->execute([$did, $when]);
            if ($clash->fetch()) {
                flash('error', 'That doctor already has an appointment at that time.');
            } else {
                $pdo->prepare('INSERT INTO appointments (patient_id, doctor_id, scheduled_at, reason, created_by) VALUES (?,?,?,?,?)')
                    ->execute([$pid, $did, $when, post_str('reason') ?: null, $user['id']]);
                audit('appointment_booked', "patient $pid doctor $did $when");
                flash('success', 'Appointment booked.');
            }
        }
    } elseif ($action === 'checkin' || $action === 'cancel') {
        $id = post_int('id');
        $new = $action === 'checkin' ? 'checked_in' : 'cancelled';
        $st = $pdo->prepare("UPDATE appointments SET status = ? WHERE id = ? AND status IN ('booked','checked_in')");
        $st->execute([$new, $id]);
        if ($st->rowCount()) { audit('appointment_' . $new, "id $id"); flash('success', 'Appointment updated.'); }
        else flash('error', 'That appointment cannot be changed.');
    }
    redirect('receptionist/index.php');
}

$patients = patients_list();
$doctors = doctors_list();
$today = $pdo->query(
    "SELECT a.*, p.full_name patient, p.patient_no, d.full_name doctor
     FROM appointments a JOIN patients p ON p.id = a.patient_id JOIN users d ON d.id = a.doctor_id
     WHERE DATE(a.scheduled_at) = CURDATE() ORDER BY a.scheduled_at"
)->fetchAll();
$upcoming = $pdo->query(
    "SELECT a.*, p.full_name patient, d.full_name doctor
     FROM appointments a JOIN patients p ON p.id = a.patient_id JOIN users d ON d.id = a.doctor_id
     WHERE a.scheduled_at > CURDATE() + INTERVAL 1 DAY AND a.status = 'booked' ORDER BY a.scheduled_at LIMIT 20"
)->fetchAll();
$waiting = count(array_filter($today, fn($a) => $a['status'] === 'checked_in'));

$layout = 'dash';
$pageTitle = 'Front desk';
require $ROOT . '/includes/header.php';
?>
<div class="grid gap-4 sm:grid-cols-3">
  <?= stat_card('Appointments today', count($today)) ?>
  <?= stat_card('Checked in and waiting', $waiting) ?>
  <?= stat_card('Registered patients', count($patients)) ?>
</div>

<section class="rounded-xl border bg-white p-5">
  <h2 class="mb-3 font-semibold">Today's queue</h2>
  <div class="overflow-x-auto">
  <table class="w-full text-sm">
    <thead class="text-left text-slate-500"><tr><th class="py-2">Time</th><th>Patient</th><th>Doctor</th><th>Reason</th><th>Status</th><th class="text-right">Actions</th></tr></thead>
    <tbody>
    <?php foreach ($today as $a): ?>
      <tr class="border-t">
        <td class="py-2"><?= e(substr($a['scheduled_at'], 11, 5)) ?></td>
        <td><?= e($a['patient']) ?> <span class="text-slate-400"><?= e($a['patient_no']) ?></span></td>
        <td><?= e($a['doctor']) ?></td>
        <td><?= e($a['reason'] ?? '') ?></td>
        <td><?= status_badge($a['status']) ?></td>
        <td class="text-right">
          <?php if (in_array($a['status'], ['booked', 'checked_in'], true)): ?>
          <div class="flex justify-end gap-2">
            <?php if ($a['status'] === 'booked'): ?>
            <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="checkin"><input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
              <button class="rounded border px-2 py-1 hover:bg-slate-50">Check in</button></form>
            <?php endif; ?>
            <form method="post" data-confirm="Cancel this appointment?"><?= csrf_field() ?><input type="hidden" name="action" value="cancel"><input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
              <button class="rounded border px-2 py-1 hover:bg-slate-50">Cancel</button></form>
          </div>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$today): ?><tr><td colspan="6" class="py-4 text-slate-500">No appointments today.</td></tr><?php endif; ?>
    </tbody>
  </table>
  </div>
</section>

<div class="grid gap-6 lg:grid-cols-2">
  <section class="rounded-xl border bg-white p-5">
    <h2 class="mb-4 font-semibold">Book an appointment</h2>
    <form method="post" class="space-y-3">
      <?= csrf_field() ?><input type="hidden" name="action" value="book">
      <div><label class="mb-1 block text-sm">Patient</label>
        <select class="<?= INPUT_CLASS ?>" name="patient_id" required><?= options(patient_options($patients)) ?></select></div>
      <div><label class="mb-1 block text-sm">Doctor</label>
        <select class="<?= INPUT_CLASS ?>" name="doctor_id" required><?= options(array_column($doctors, 'full_name', 'id')) ?></select></div>
      <div><label class="mb-1 block text-sm">Date and time</label>
        <input class="<?= INPUT_CLASS ?>" type="datetime-local" name="scheduled_at" min="<?= e(date('Y-m-d\TH:i')) ?>" required></div>
      <div><label class="mb-1 block text-sm">Reason</label><input class="<?= INPUT_CLASS ?>" name="reason" maxlength="255"></div>
      <button class="<?= BTN_CLASS ?>">Book appointment</button>
    </form>
  </section>

  <section class="rounded-xl border bg-white p-5">
    <h2 class="mb-4 font-semibold">Register a walk-in patient</h2>
    <form method="post" class="grid gap-3 sm:grid-cols-2">
      <?= csrf_field() ?><input type="hidden" name="action" value="register_patient">
      <?= patient_form_fields() ?>
      <div class="flex items-end"><button class="<?= BTN_CLASS ?>">Register patient</button></div>
    </form>
  </section>
</div>

<section class="rounded-xl border bg-white p-5">
  <h2 class="mb-3 font-semibold">Upcoming appointments</h2>
  <table class="w-full text-sm">
    <thead class="text-left text-slate-500"><tr><th class="py-2">When</th><th>Patient</th><th>Doctor</th><th>Reason</th></tr></thead>
    <tbody>
    <?php foreach ($upcoming as $a): ?>
      <tr class="border-t"><td class="py-2"><?= e(substr($a['scheduled_at'], 0, 16)) ?></td><td><?= e($a['patient']) ?></td><td><?= e($a['doctor']) ?></td><td><?= e($a['reason'] ?? '') ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$upcoming): ?><tr><td colspan="4" class="py-4 text-slate-500">Nothing booked beyond today.</td></tr><?php endif; ?>
    </tbody>
  </table>
</section>
<?php require $ROOT . '/includes/footer.php';