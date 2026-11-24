<?php
declare(strict_types=1);
require dirname(__DIR__) . '/config/bootstrap.php';
require dirname(__DIR__) . '/includes/layout.php';
require dirname(__DIR__) . '/includes/clinic.php';

$user = require_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    [$d, $err] = patient_input($_POST);
    if ($err) {
        foreach ($err as $m) flash('error', $m);
    } else {
        $id = create_patient_record($d);
        audit('patient_created', "id $id");
        flash('success', 'Patient record created.');
    }
    redirect('admin/patients.php');
}

$rows = db()->query(
    'SELECT p.*, u.email, u.is_active FROM patients p LEFT JOIN users u ON u.id = p.user_id ORDER BY p.created_at DESC'
)->fetchAll();

$layout = 'dash';
$pageTitle = 'Patients';
require $ROOT . '/includes/header.php';
?>
<section class="rounded-xl border bg-white p-5">
  <h2 class="mb-4 font-semibold">Register a patient</h2>
  <form method="post" class="grid gap-4 md:grid-cols-3">
    <?= csrf_field() ?>
    <?= patient_form_fields() ?>
    <div class="flex items-end"><button class="<?= BTN_CLASS ?>">Save patient</button></div>
  </form>
</section>

<section class="rounded-xl border bg-white p-5">
  <div class="mb-3 flex items-center justify-between gap-3">
    <h2 class="font-semibold">All patients (<?= count($rows) ?>)</h2>
    <input data-filter="#patientsTable" class="<?= INPUT_CLASS ?> max-w-xs" placeholder="Search name, number, phone">
  </div>
  <div class="overflow-x-auto">
  <table id="patientsTable" class="w-full text-sm">
    <thead class="text-left text-slate-500"><tr><th class="py-2">No.</th><th>Name</th><th>Phone</th><th>Account</th><th>Registered</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $p): ?>
      <tr class="border-t">
        <td class="py-2"><?= e($p['patient_no']) ?></td>
        <td><?= e($p['full_name']) ?></td>
        <td><?= e($p['phone'] ?? '') ?></td>
        <td><?= $p['email'] ? e($p['email']) : '<span class="text-slate-400">No login (walk-in)</span>' ?></td>
        <td><?= e(substr($p['created_at'], 0, 10)) ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="5" class="py-4 text-slate-500">No patients yet.</td></tr><?php endif; ?>
    </tbody>
  </table>
  </div>
</section>
<?php require $ROOT . '/includes/footer.php';