<?php
declare(strict_types=1);
require dirname(__DIR__) . '/config/bootstrap.php';
require dirname(__DIR__) . '/includes/layout.php';
require dirname(__DIR__) . '/includes/clinic.php';

$user = require_role('patient');
$pdo = db();
$patient = patient_for_user($user['id']);
if (!$patient) {
    flash('error', 'Your patient record could not be found. Please contact the front desk.');
    redirect('logout.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'profile') {
        [$d, $err] = patient_input(['full_name' => $patient['full_name']] + $_POST);
        if ($err) {
            foreach ($err as $m) flash('error', $m);
        } else {
            $pdo->prepare('UPDATE patients SET phone=?, dob=?, gender=?, address=?, next_of_kin=? WHERE id=?')
                ->execute([$d['phone'], $d['dob'], $d['gender'], $d['address'], $d['next_of_kin'], $patient['id']]);
            audit('profile_updated');
            flash('success', 'Profile updated.');
        }
    } elseif ($action === 'book') {
        $did = post_int('doctor_id');
        $when = future_datetime((string)($_POST['scheduled_at'] ?? ''));
        $okD = $pdo->prepare("SELECT 1 FROM users WHERE id = ? AND role='doctor' AND is_active=1");
        $okD->execute([$did]);
        if (!$okD->fetch()) {
            flash('error', 'Choose a doctor.');
        } elseif (!$when) {
            flash('error', 'Choose a date and time in the future.');
        } else {
            $clash = $pdo->prepare("SELECT 1 FROM appointments WHERE doctor_id = ? AND scheduled_at = ? AND status <> 'cancelled'");
            $clash->execute([$did, $when]);
            if ($clash->fetch()) {
                flash('error', 'That time is taken. Please choose another.');
            } else {
                $pdo->prepare('INSERT INTO appointments (patient_id, doctor_id, scheduled_at, reason, created_by) VALUES (?,?,?,?,?)')
                    ->execute([$patient['id'], $did, $when, post_str('reason') ?: null, $user['id']]);
                audit('appointment_booked', "doctor $did $when");
                flash('success', 'Appointment booked. Please arrive 10 minutes early.');
            }
        }
    } elseif ($action === 'cancel') {
        $st = $pdo->prepare("UPDATE appointments SET status='cancelled' WHERE id = ? AND patient_id = ? AND status='booked'");
        $st->execute([post_int('id'), $patient['id']]);
        flash($st->rowCount() ? 'success' : 'error', $st->rowCount() ? 'Appointment cancelled.' : 'That appointment cannot be cancelled.');
    }
    redirect('patient/index.php');
}

$q = function (string $sql) use ($pdo, $patient) { $s = $pdo->prepare($sql); $s->execute([$patient['id']]); return $s->fetchAll(); };
$appointments = $q("SELECT a.*, d.full_name doctor FROM appointments a JOIN users d ON d.id = a.doctor_id WHERE a.patient_id = ? ORDER BY a.scheduled_at DESC LIMIT 15");
$visits = $q('SELECT c.*, d.full_name doctor FROM consultations c JOIN users d ON d.id = c.doctor_id WHERE c.patient_id = ? ORDER BY c.created_at DESC LIMIT 10');
$rx = $q('SELECT * FROM prescriptions WHERE patient_id = ? ORDER BY created_at DESC LIMIT 10');
$labs = $q('SELECT * FROM lab_requests WHERE patient_id = ? ORDER BY requested_at DESC LIMIT 10');
$doctors = doctors_list();
$next = null;
foreach (array_reverse($appointments) as $a) {
    if ($a['status'] === 'booked' && $a['scheduled_at'] > date('Y-m-d H:i:s')) { $next = $a; break; }
}
$i = INPUT_CLASS;

$layout = 'dash';
$pageTitle = 'My health';
require $ROOT . '/includes/header.php';
?>
<section class="rounded-xl bg-brand-700 p-6 text-white">
  <div class="text-sm text-brand-100">Patient number <?= e($patient['patient_no']) ?></div>
  <h2 class="mt-1 text-2xl font-semibold">Hello, <?= e(explode(' ', $patient['full_name'])[0]) ?></h2>
  <p class="mt-1 text-sm text-brand-100"><?= $next ? 'Your next appointment is ' . e(substr($next['scheduled_at'], 0, 16)) . ' with ' . e($next['doctor']) . '.' : 'You have no upcoming appointments.' ?></p>
</section>

<div class="grid gap-6 lg:grid-cols-2">
  <section class="rounded-xl border bg-white p-5">
    <h2 class="mb-4 font-semibold">Book an appointment</h2>
    <form method="post" class="space-y-3">
      <?= csrf_field() ?><input type="hidden" name="action" value="book">
      <div><label class="mb-1 block text-sm">Doctor</label><select class="<?= $i ?>" name="doctor_id" required><?= options(array_column($doctors, 'full_name', 'id')) ?></select></div>
      <div><label class="mb-1 block text-sm">Date and time</label><input class="<?= $i ?>" type="datetime-local" name="scheduled_at" min="<?= e(date('Y-m-d\TH:i')) ?>" required></div>
      <div><label class="mb-1 block text-sm">Reason for visit</label><input class="<?= $i ?>" name="reason" maxlength="255"></div>
      <button class="<?= BTN_CLASS ?>">Book appointment</button>
    </form>
  </section>

  <section class="rounded-xl border bg-white p-5">
    <h2 class="mb-4 font-semibold">My details</h2>
    <form method="post" class="grid gap-3 sm:grid-cols-2">
      <?= csrf_field() ?><input type="hidden" name="action" value="profile">
      <div><label class="mb-1 block text-sm">Name</label><input class="<?= $i ?> bg-slate-100" value="<?= e($patient['full_name']) ?>" disabled></div>
      <div><label class="mb-1 block text-sm">Phone</label><input class="<?= $i ?>" name="phone" value="<?= e($patient['phone'] ?? '') ?>"></div>
      <div><label class="mb-1 block text-sm">Date of birth</label><input class="<?= $i ?>" type="date" name="dob" max="<?= e(date('Y-m-d')) ?>" value="<?= e($patient['dob'] ?? '') ?>"></div>
      <div><label class="mb-1 block text-sm">Gender</label>
        <select class="<?= $i ?>" name="gender"><?= options(['female' => 'Female', 'male' => 'Male', 'other' => 'Other'], $patient['gender'], 'Select...') ?></select></div>
      <div><label class="mb-1 block text-sm">Address</label><input class="<?= $i ?>" name="address" value="<?= e($patient['address'] ?? '') ?>"></div>
      <div><label class="mb-1 block text-sm">Next of kin</label><input class="<?= $i ?>" name="next_of_kin" value="<?= e($patient['next_of_kin'] ?? '') ?>"></div>
      <div><button class="<?= BTN_CLASS ?>">Save details</button></div>
    </form>
  </section>
</div>

<section class="rounded-xl border bg-white p-5">
  <h2 class="mb-3 font-semibold">My appointments</h2>
  <table class="w-full text-sm">
    <thead class="text-left text-slate-500"><tr><th class="py-2">When</th><th>Doctor</th><th>Reason</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($appointments as $a): ?>
      <tr class="border-t"><td class="py-2"><?= e(substr($a['scheduled_at'], 0, 16)) ?></td><td><?= e($a['doctor']) ?></td><td><?= e($a['reason'] ?? '') ?></td><td><?= status_badge($a['status']) ?></td>
        <td class="text-right"><?php if ($a['status'] === 'booked'): ?>
          <form method="post" data-confirm="Cancel this appointment?"><?= csrf_field() ?><input type="hidden" name="action" value="cancel"><input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
            <button class="rounded border px-2 py-1 hover:bg-slate-50">Cancel</button></form><?php endif; ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$appointments): ?><tr><td colspan="5" class="py-3 text-slate-500">No appointments yet.</td></tr><?php endif; ?>
    </tbody>
  </table>
</section>

<div class="grid gap-6 lg:grid-cols-3">
  <section class="rounded-xl border bg-white p-5 text-sm">
    <h2 class="mb-3 font-semibold">Visit history</h2>
    <?php foreach ($visits as $v): ?>
      <div class="border-t py-2"><div class="text-slate-500"><?= e(substr($v['created_at'], 0, 10)) ?> · <?= e($v['doctor']) ?></div><div class="font-medium"><?= e($v['diagnosis']) ?></div></div>
    <?php endforeach; ?>
    <?php if (!$visits): ?><p class="text-slate-500">No visits recorded.</p><?php endif; ?>
  </section>
  <section class="rounded-xl border bg-white p-5 text-sm">
    <h2 class="mb-3 font-semibold">Prescriptions</h2>
    <?php foreach ($rx as $r): ?>
      <div class="border-t py-2"><div class="flex justify-between"><span class="font-medium"><?= e($r['drug_name']) ?> × <?= (int)$r['quantity'] ?></span><?= status_badge($r['status']) ?></div><div><?= e($r['dosage']) ?></div></div>
    <?php endforeach; ?>
    <?php if (!$rx): ?><p class="text-slate-500">None yet.</p><?php endif; ?>
  </section>
  <section class="rounded-xl border bg-white p-5 text-sm">
    <h2 class="mb-3 font-semibold">Lab results</h2>
    <?php foreach ($labs as $l): ?>
      <div class="border-t py-2"><div class="flex justify-between"><span class="font-medium"><?= e($l['test_name']) ?></span><?= status_badge($l['status']) ?></div>
        <div class="whitespace-pre-line"><?= $l['status'] === 'completed' ? e($l['result'] ?? '') : '<span class="text-slate-500">Result not ready yet.</span>' ?></div></div>
    <?php endforeach; ?>
    <?php if (!$labs): ?><p class="text-slate-500">No tests.</p><?php endif; ?>
  </section>
</div>
<?php require $ROOT . '/includes/footer.php';