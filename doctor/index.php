<?php
declare(strict_types=1);
require dirname(__DIR__) . '/config/bootstrap.php';
require dirname(__DIR__) . '/includes/layout.php';
require dirname(__DIR__) . '/includes/clinic.php';

$user = require_role('doctor');
$pdo = db();

$pid = (int)($_GET['patient'] ?? $_POST['patient_id'] ?? 0);
$patient = null;
if ($pid) {
    $st = $pdo->prepare('SELECT * FROM patients WHERE id = ?');
    $st->execute([$pid]);
    $patient = $st->fetch() ?: null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (!$patient) { flash('error', 'Choose a patient first.'); redirect('doctor/index.php'); }
    $action = $_POST['action'] ?? '';
    $back = 'doctor/index.php?patient=' . $patient['id'];

    if ($action === 'consult') {
        $dx = post_str('diagnosis');
        if ($dx === '') {
            flash('error', 'Enter a diagnosis.');
        } else {
            $apt = post_int('appointment_id') ?: null;
            $pdo->prepare('INSERT INTO consultations (patient_id, doctor_id, appointment_id, diagnosis, notes) VALUES (?,?,?,?,?)')
                ->execute([$patient['id'], $user['id'], $apt, $dx, post_str('notes', 5000) ?: null]);
            if ($apt) {
                $pdo->prepare("UPDATE appointments SET status='completed' WHERE id = ? AND doctor_id = ? AND patient_id = ?")
                    ->execute([$apt, $user['id'], $patient['id']]);
            }
            audit('consultation_added', 'patient ' . $patient['id']);
            flash('success', 'Consultation saved.');
        }
    } elseif ($action === 'prescribe') {
        $drug = post_str('drug_name', 120);
        $dose = post_str('dosage', 150);
        $qty = max(1, post_int('quantity'));
        if ($drug === '' || $dose === '') {
            flash('error', 'Enter the drug and dosage.');
        } else {
            $pdo->prepare('INSERT INTO prescriptions (patient_id, doctor_id, drug_name, dosage, quantity) VALUES (?,?,?,?,?)')
                ->execute([$patient['id'], $user['id'], $drug, $dose, $qty]);
            audit('prescription_added', 'patient ' . $patient['id']);
            flash('success', 'Prescription sent to the pharmacy.');
        }
    } elseif ($action === 'lab') {
        $test = post_str('test_name', 120);
        if ($test === '') {
            flash('error', 'Enter the test to request.');
        } else {
            $pdo->prepare('INSERT INTO lab_requests (patient_id, doctor_id, test_name) VALUES (?,?,?)')
                ->execute([$patient['id'], $user['id'], $test]);
            audit('lab_requested', 'patient ' . $patient['id']);
            flash('success', 'Lab test requested.');
        }
    }
    redirect($back);
}

$queue = $pdo->prepare(
    "SELECT a.*, p.full_name patient, p.patient_no FROM appointments a JOIN patients p ON p.id = a.patient_id
     WHERE a.doctor_id = ? AND DATE(a.scheduled_at) = CURDATE() AND a.status IN ('booked','checked_in')
     ORDER BY a.status = 'checked_in' DESC, a.scheduled_at"
);
$queue->execute([$user['id']]);
$queue = $queue->fetchAll();

$q = trim((string)($_GET['q'] ?? ''));
$found = [];
if ($q !== '') {
    $s = $pdo->prepare('SELECT id, patient_no, full_name, phone FROM patients WHERE full_name LIKE ? OR patient_no LIKE ? OR phone LIKE ? ORDER BY full_name LIMIT 15');
    $like = '%' . addcslashes($q, '%_\\') . '%';
    $s->execute([$like, $like, $like]);
    $found = $s->fetchAll();
}

if ($patient) {
    $get = function (string $sql) use ($pdo, $patient) { $s = $pdo->prepare($sql); $s->execute([$patient['id']]); return $s->fetchAll(); };
    $vitals = $get('SELECT * FROM vitals WHERE patient_id = ? ORDER BY recorded_at DESC LIMIT 3');
    $history = $get('SELECT c.*, d.full_name doctor FROM consultations c JOIN users d ON d.id = c.doctor_id WHERE c.patient_id = ? ORDER BY c.created_at DESC LIMIT 10');
    $labs = $get('SELECT * FROM lab_requests WHERE patient_id = ? ORDER BY requested_at DESC LIMIT 10');
    $rx = $get('SELECT * FROM prescriptions WHERE patient_id = ? ORDER BY created_at DESC LIMIT 10');
    $openApt = $pdo->prepare("SELECT id, scheduled_at FROM appointments WHERE patient_id = ? AND doctor_id = ? AND status IN ('booked','checked_in') ORDER BY scheduled_at LIMIT 5");
    $openApt->execute([$patient['id'], $user['id']]);
    $openApt = $openApt->fetchAll();
}

$layout = 'dash';
$pageTitle = $patient ? 'Patient: ' . $patient['full_name'] : 'My day';
require $ROOT . '/includes/header.php';
?>
<?php if (!$patient): ?>
<div class="grid gap-6 lg:grid-cols-2">
  <section class="rounded-xl border bg-white p-5">
    <h2 class="mb-3 font-semibold">Today's patients (<?= count($queue) ?>)</h2>
    <ul class="divide-y text-sm">
    <?php foreach ($queue as $a): ?>
      <li class="flex items-center justify-between py-2">
        <div><span class="font-medium"><?= e(substr($a['scheduled_at'], 11, 5)) ?></span> · <?= e($a['patient']) ?>
          <span class="text-slate-400"><?= e($a['patient_no']) ?></span> <?= status_badge($a['status']) ?></div>
        <a class="rounded border px-2 py-1 hover:bg-slate-50" href="<?= e(url('doctor/index.php?patient=' . (int)$a['patient_id'])) ?>">Open</a>
      </li>
    <?php endforeach; ?>
    <?php if (!$queue): ?><li class="py-3 text-slate-500">No patients waiting.</li><?php endif; ?>
    </ul>
  </section>

  <section class="rounded-xl border bg-white p-5">
    <h2 class="mb-3 font-semibold">Find a patient</h2>
    <form method="get" class="flex gap-2">
      <input class="<?= INPUT_CLASS ?>" name="q" value="<?= e($q) ?>" placeholder="Name, patient number or phone">
      <button class="<?= BTN_CLASS ?>">Search</button>
    </form>
    <ul class="mt-3 divide-y text-sm">
    <?php foreach ($found as $p): ?>
      <li class="flex items-center justify-between py-2"><span><?= e($p['full_name']) ?> <span class="text-slate-400"><?= e($p['patient_no']) ?></span></span>
        <a class="rounded border px-2 py-1 hover:bg-slate-50" href="<?= e(url('doctor/index.php?patient=' . (int)$p['id'])) ?>">Open</a></li>
    <?php endforeach; ?>
    <?php if ($q !== '' && !$found): ?><li class="py-3 text-slate-500">No match.</li><?php endif; ?>
    </ul>
  </section>
</div>

<?php else: ?>
<a href="<?= e(url('doctor/index.php')) ?>" class="text-sm text-brand-700">&larr; Back to my day</a>
<section class="grid gap-4 rounded-xl border bg-white p-5 text-sm sm:grid-cols-4">
  <div><div class="text-slate-500">Patient no.</div><div class="font-medium"><?= e($patient['patient_no']) ?></div></div>
  <div><div class="text-slate-500">Date of birth</div><div class="font-medium"><?= e($patient['dob'] ?? 'Not given') ?></div></div>
  <div><div class="text-slate-500">Gender</div><div class="font-medium"><?= e(ucfirst((string)($patient['gender'] ?? 'Not given'))) ?></div></div>
  <div><div class="text-slate-500">Phone</div><div class="font-medium"><?= e($patient['phone'] ?? 'Not given') ?></div></div>
</section>

<section class="rounded-xl border bg-white p-5">
  <h2 class="mb-3 font-semibold">Latest vitals</h2>
  <table class="w-full text-sm">
    <thead class="text-left text-slate-500"><tr><th class="py-1">When</th><th>BP</th><th>Temp °C</th><th>Pulse</th><th>Weight kg</th><th>Notes</th></tr></thead>
    <tbody>
    <?php foreach ($vitals as $v): ?>
      <tr class="border-t"><td class="py-2"><?= e(substr($v['recorded_at'], 0, 16)) ?></td><td><?= e($v['bp'] ?? '') ?></td><td><?= e((string)($v['temperature'] ?? '')) ?></td><td><?= e((string)($v['pulse'] ?? '')) ?></td><td><?= e((string)($v['weight_kg'] ?? '')) ?></td><td><?= e($v['notes'] ?? '') ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$vitals): ?><tr><td colspan="6" class="py-3 text-slate-500">No vitals recorded.</td></tr><?php endif; ?>
    </tbody>
  </table>
</section>

<div class="grid gap-6 lg:grid-cols-3">
  <section class="rounded-xl border bg-white p-5">
    <h2 class="mb-3 font-semibold">New consultation</h2>
    <form method="post" class="space-y-3">
      <?= csrf_field() ?><input type="hidden" name="action" value="consult"><input type="hidden" name="patient_id" value="<?= (int)$patient['id'] ?>">
      <div><label class="mb-1 block text-sm">Appointment</label>
        <select class="<?= INPUT_CLASS ?>" name="appointment_id">
          <option value="">Not linked</option>
          <?php foreach ($openApt as $o): ?><option value="<?= (int)$o['id'] ?>"><?= e(substr($o['scheduled_at'], 0, 16)) ?></option><?php endforeach; ?>
        </select></div>
      <div><label class="mb-1 block text-sm">Diagnosis</label><input class="<?= INPUT_CLASS ?>" name="diagnosis" maxlength="255" required></div>
      <div><label class="mb-1 block text-sm">Notes</label><textarea class="<?= INPUT_CLASS ?>" name="notes" rows="4"></textarea></div>
      <button class="<?= BTN_CLASS ?>">Save consultation</button>
    </form>
  </section>
  <section class="rounded-xl border bg-white p-5">
    <h2 class="mb-3 font-semibold">Prescribe</h2>
    <form method="post" class="space-y-3">
      <?= csrf_field() ?><input type="hidden" name="action" value="prescribe"><input type="hidden" name="patient_id" value="<?= (int)$patient['id'] ?>">
      <div><label class="mb-1 block text-sm">Drug</label><input class="<?= INPUT_CLASS ?>" name="drug_name" list="druglist" maxlength="120" required></div>
      <datalist id="druglist"><?php foreach ($pdo->query('SELECT name FROM drugs ORDER BY name') as $d): ?><option value="<?= e($d['name']) ?>"><?php endforeach; ?></datalist>
      <div><label class="mb-1 block text-sm">Dosage and instructions</label><input class="<?= INPUT_CLASS ?>" name="dosage" maxlength="150" placeholder="1 tablet 3 times a day for 5 days" required></div>
      <div><label class="mb-1 block text-sm">Quantity</label><input class="<?= INPUT_CLASS ?>" type="number" name="quantity" min="1" value="1"></div>
      <button class="<?= BTN_CLASS ?>">Send to pharmacy</button>
    </form>
  </section>
  <section class="rounded-xl border bg-white p-5">
    <h2 class="mb-3 font-semibold">Request a lab test</h2>
    <form method="post" class="space-y-3">
      <?= csrf_field() ?><input type="hidden" name="action" value="lab"><input type="hidden" name="patient_id" value="<?= (int)$patient['id'] ?>">
      <div><label class="mb-1 block text-sm">Test</label><input class="<?= INPUT_CLASS ?>" name="test_name" maxlength="120" placeholder="Full blood count" required></div>
      <button class="<?= BTN_CLASS ?>">Request test</button>
    </form>
  </section>
</div>

<div class="grid gap-6 lg:grid-cols-3">
  <section class="rounded-xl border bg-white p-5 text-sm">
    <h2 class="mb-3 font-semibold">Past consultations</h2>
    <?php foreach ($history as $h): ?>
      <div class="border-t py-2"><div class="text-slate-500"><?= e(substr($h['created_at'], 0, 10)) ?> · <?= e($h['doctor']) ?></div>
        <div class="font-medium"><?= e($h['diagnosis']) ?></div><div class="whitespace-pre-line"><?= e($h['notes'] ?? '') ?></div></div>
    <?php endforeach; ?>
    <?php if (!$history): ?><p class="text-slate-500">None yet.</p><?php endif; ?>
  </section>
  <section class="rounded-xl border bg-white p-5 text-sm">
    <h2 class="mb-3 font-semibold">Lab tests</h2>
    <?php foreach ($labs as $l): ?>
      <div class="border-t py-2"><div class="flex justify-between"><span class="font-medium"><?= e($l['test_name']) ?></span><?= status_badge($l['status']) ?></div>
        <div class="whitespace-pre-line"><?= e($l['result'] ?? '') ?></div></div>
    <?php endforeach; ?>
    <?php if (!$labs): ?><p class="text-slate-500">None requested.</p><?php endif; ?>
  </section>
  <section class="rounded-xl border bg-white p-5 text-sm">
    <h2 class="mb-3 font-semibold">Prescriptions</h2>
    <?php foreach ($rx as $r): ?>
      <div class="border-t py-2"><div class="flex justify-between"><span class="font-medium"><?= e($r['drug_name']) ?> × <?= (int)$r['quantity'] ?></span><?= status_badge($r['status']) ?></div>
        <div><?= e($r['dosage']) ?></div></div>
    <?php endforeach; ?>
    <?php if (!$rx): ?><p class="text-slate-500">None yet.</p><?php endif; ?>
  </section>
</div>
<?php endif; ?>
<?php require $ROOT . '/includes/footer.php';