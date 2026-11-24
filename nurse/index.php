<?php
declare(strict_types=1);
require dirname(__DIR__) . '/config/bootstrap.php';
require dirname(__DIR__) . '/includes/layout.php';
require dirname(__DIR__) . '/includes/clinic.php';

$user = require_role('nurse', 'lab');
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'vitals') {
        $pid = post_int('patient_id');
        $ok = $pdo->prepare('SELECT 1 FROM patients WHERE id = ?');
        $ok->execute([$pid]);
        $temp = $_POST['temperature'] ?? '';
        $pulse = $_POST['pulse'] ?? '';
        $wt = $_POST['weight_kg'] ?? '';
        $bp = post_str('bp', 10);
        $err = null;
        if (!$ok->fetch()) $err = 'Choose a patient.';
        elseif ($bp !== '' && !preg_match('/^\d{2,3}\/\d{2,3}$/', $bp)) $err = 'Blood pressure should look like 120/80.';
        elseif ($temp !== '' && (!is_numeric($temp) || $temp < 30 || $temp > 45)) $err = 'Temperature must be between 30 and 45 °C.';
        elseif ($pulse !== '' && (!ctype_digit((string)$pulse) || $pulse < 20 || $pulse > 250)) $err = 'Pulse must be between 20 and 250.';
        elseif ($wt !== '' && (!is_numeric($wt) || $wt <= 0 || $wt > 400)) $err = 'Enter a valid weight in kg.';
        elseif ($bp === '' && $temp === '' && $pulse === '' && $wt === '') $err = 'Enter at least one reading.';
        if ($err) {
            flash('error', $err);
        } else {
            $pdo->prepare('INSERT INTO vitals (patient_id, recorded_by, bp, temperature, pulse, weight_kg, notes) VALUES (?,?,?,?,?,?,?)')
                ->execute([$pid, $user['id'], $bp ?: null, $temp !== '' ? $temp : null, $pulse !== '' ? $pulse : null, $wt !== '' ? $wt : null, post_str('notes') ?: null]);
            audit('vitals_recorded', "patient $pid");
            flash('success', 'Vitals saved.');
        }
    } elseif ($action === 'result') {
        $id = post_int('id');
        $res = post_str('result', 5000);
        if ($res === '') {
            flash('error', 'Enter the result.');
        } else {
            $st = $pdo->prepare("UPDATE lab_requests SET result = ?, status='completed', completed_by = ?, completed_at = NOW() WHERE id = ? AND status='pending'");
            $st->execute([$res, $user['id'], $id]);
            if ($st->rowCount()) { audit('lab_result_entered', "request $id"); flash('success', 'Result saved and released to the doctor and patient.'); }
            else flash('error', 'That request was already completed.');
        }
    }
    redirect('nurse/index.php');
}

$patients = patients_list();
$queue = $pdo->query(
    "SELECT a.*, p.full_name patient, p.patient_no, d.full_name doctor FROM appointments a
     JOIN patients p ON p.id = a.patient_id JOIN users d ON d.id = a.doctor_id
     WHERE DATE(a.scheduled_at) = CURDATE() AND a.status = 'checked_in' ORDER BY a.scheduled_at"
)->fetchAll();
$pending = $pdo->query(
    "SELECT l.*, p.full_name patient, p.patient_no, d.full_name doctor FROM lab_requests l
     JOIN patients p ON p.id = l.patient_id JOIN users d ON d.id = l.doctor_id
     WHERE l.status='pending' ORDER BY l.requested_at"
)->fetchAll();
$recent = $pdo->query(
    'SELECT v.*, p.full_name patient FROM vitals v JOIN patients p ON p.id = v.patient_id ORDER BY v.recorded_at DESC LIMIT 10'
)->fetchAll();

$layout = 'dash';
$pageTitle = 'Nurse & lab';
require $ROOT . '/includes/header.php';
?>
<div class="grid gap-4 sm:grid-cols-3">
  <?= stat_card('Checked in, waiting', count($queue)) ?>
  <?= stat_card('Pending lab tests', count($pending)) ?>
  <?= stat_card('Vitals recorded today', (int)$pdo->query('SELECT COUNT(*) FROM vitals WHERE DATE(recorded_at) = CURDATE()')->fetchColumn()) ?>
</div>

<div class="grid gap-6 lg:grid-cols-2">
  <section class="rounded-xl border bg-white p-5">
    <h2 class="mb-4 font-semibold">Record vitals</h2>
    <form method="post" class="grid gap-3 sm:grid-cols-2">
      <?= csrf_field() ?><input type="hidden" name="action" value="vitals">
      <div class="sm:col-span-2"><label class="mb-1 block text-sm">Patient</label>
        <select class="<?= INPUT_CLASS ?>" name="patient_id" required><?= options(patient_options($patients)) ?></select></div>
      <div><label class="mb-1 block text-sm">Blood pressure</label><input class="<?= INPUT_CLASS ?>" name="bp" placeholder="120/80"></div>
      <div><label class="mb-1 block text-sm">Temperature °C</label><input class="<?= INPUT_CLASS ?>" type="number" step="0.1" name="temperature"></div>
      <div><label class="mb-1 block text-sm">Pulse (bpm)</label><input class="<?= INPUT_CLASS ?>" type="number" name="pulse"></div>
      <div><label class="mb-1 block text-sm">Weight kg</label><input class="<?= INPUT_CLASS ?>" type="number" step="0.1" name="weight_kg"></div>
      <div class="sm:col-span-2"><label class="mb-1 block text-sm">Notes</label><input class="<?= INPUT_CLASS ?>" name="notes" maxlength="255"></div>
      <div><button class="<?= BTN_CLASS ?>">Save vitals</button></div>
    </form>
  </section>

  <section class="rounded-xl border bg-white p-5">
    <h2 class="mb-3 font-semibold">Waiting patients</h2>
    <ul class="divide-y text-sm">
    <?php foreach ($queue as $a): ?>
      <li class="py-2"><span class="font-medium"><?= e(substr($a['scheduled_at'], 11, 5)) ?></span> · <?= e($a['patient']) ?>
        <span class="text-slate-400"><?= e($a['patient_no']) ?></span> → <?= e($a['doctor']) ?></li>
    <?php endforeach; ?>
    <?php if (!$queue): ?><li class="py-3 text-slate-500">Nobody is waiting.</li><?php endif; ?>
    </ul>
  </section>
</div>

<section class="rounded-xl border bg-white p-5">
  <h2 class="mb-3 font-semibold">Pending lab tests</h2>
  <?php foreach ($pending as $l): ?>
    <form method="post" class="grid gap-3 border-t py-3 text-sm md:grid-cols-[1fr_2fr_auto] md:items-start">
      <?= csrf_field() ?><input type="hidden" name="action" value="result"><input type="hidden" name="id" value="<?= (int)$l['id'] ?>">
      <div><div class="font-medium"><?= e($l['test_name']) ?></div>
        <div class="text-slate-500"><?= e($l['patient']) ?> (<?= e($l['patient_no']) ?>)<br>Requested by <?= e($l['doctor']) ?>, <?= e(substr($l['requested_at'], 0, 16)) ?></div></div>
      <textarea class="<?= INPUT_CLASS ?>" name="result" rows="2" placeholder="Result and reference range" required></textarea>
      <button class="<?= BTN_CLASS ?>">Release result</button>
    </form>
  <?php endforeach; ?>
  <?php if (!$pending): ?><p class="text-sm text-slate-500">No tests waiting.</p><?php endif; ?>
</section>

<section class="rounded-xl border bg-white p-5">
  <h2 class="mb-3 font-semibold">Recent vitals</h2>
  <table class="w-full text-sm">
    <thead class="text-left text-slate-500"><tr><th class="py-1">When</th><th>Patient</th><th>BP</th><th>Temp</th><th>Pulse</th><th>Weight</th></tr></thead>
    <tbody>
    <?php foreach ($recent as $v): ?>
      <tr class="border-t"><td class="py-2"><?= e(substr($v['recorded_at'], 0, 16)) ?></td><td><?= e($v['patient']) ?></td><td><?= e($v['bp'] ?? '') ?></td><td><?= e((string)($v['temperature'] ?? '')) ?></td><td><?= e((string)($v['pulse'] ?? '')) ?></td><td><?= e((string)($v['weight_kg'] ?? '')) ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$recent): ?><tr><td colspan="6" class="py-3 text-slate-500">Nothing recorded yet.</td></tr><?php endif; ?>
    </tbody>
  </table>
</section>
<?php require $ROOT . '/includes/footer.php';