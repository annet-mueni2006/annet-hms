<?php
declare(strict_types=1);
require dirname(__DIR__) . '/config/bootstrap.php';
require dirname(__DIR__) . '/includes/layout.php';
require dirname(__DIR__) . '/includes/clinic.php';

$user = require_role('pharmacist');
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'dispense') {
        $id = post_int('id');
        try {
            $pdo->beginTransaction();
            $st = $pdo->prepare("SELECT * FROM prescriptions WHERE id = ? AND status='pending' FOR UPDATE");
            $st->execute([$id]);
            $rx = $st->fetch();
            if (!$rx) throw new RuntimeException('That prescription was already dispensed.');

            $ds = $pdo->prepare('SELECT * FROM drugs WHERE name = ? FOR UPDATE');
            $ds->execute([$rx['drug_name']]);
            $drug = $ds->fetch();
            if (!$drug) throw new RuntimeException('"' . $rx['drug_name'] . '" is not in the stock list. Add it under Stock first.');
            if ((int)$drug['quantity'] < (int)$rx['quantity']) {
                throw new RuntimeException('Only ' . (int)$drug['quantity'] . ' of ' . $drug['name'] . ' in stock, ' . (int)$rx['quantity'] . ' needed.');
            }
            $pdo->prepare('UPDATE drugs SET quantity = quantity - ? WHERE id = ?')->execute([$rx['quantity'], $drug['id']]);
            $pdo->prepare("UPDATE prescriptions SET status='dispensed', dispensed_by = ?, dispensed_at = NOW() WHERE id = ?")->execute([$user['id'], $id]);
            $pdo->commit();
            audit('dispensed', "prescription $id");
            flash('success', 'Dispensed ' . $rx['quantity'] . ' × ' . $rx['drug_name'] . '.');
        } catch (RuntimeException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            flash('error', $e->getMessage());
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            flash('error', 'Could not dispense. Please try again.');
        }
    } elseif ($action === 'drug') {
        $name = post_str('name', 120);
        $qty = post_int('quantity');
        $price = $_POST['unit_price'] ?? '';
        $reorder = post_int('reorder_level');
        $exp = post_str('expiry_date', 10);
        $err = null;
        if ($name === '') $err = 'Enter the drug name.';
        elseif ($qty < 0) $err = 'Quantity cannot be negative.';
        elseif (!is_numeric($price) || $price < 0) $err = 'Enter a valid unit price.';
        elseif ($exp !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $exp)) $err = 'Enter a valid expiry date.';
        if ($err) {
            flash('error', $err);
        } else {
            // New drug, or add stock to an existing one (price, reorder level and expiry are updated).
            $pdo->prepare(
                'INSERT INTO drugs (name, quantity, unit_price, reorder_level, expiry_date) VALUES (?,?,?,?,?)
                 ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity), unit_price = VALUES(unit_price),
                   reorder_level = VALUES(reorder_level), expiry_date = COALESCE(VALUES(expiry_date), expiry_date)'
            )->execute([$name, $qty, $price, $reorder ?: 10, $exp ?: null]);
            audit('stock_updated', $name . ' +' . $qty);
            flash('success', 'Stock saved for ' . $name . '.');
        }
    }
    redirect('pharmacy/index.php');
}

$pending = $pdo->query(
    "SELECT r.*, p.full_name patient, p.patient_no, d.full_name doctor, g.quantity in_stock
     FROM prescriptions r JOIN patients p ON p.id = r.patient_id JOIN users d ON d.id = r.doctor_id
     LEFT JOIN drugs g ON g.name = r.drug_name WHERE r.status='pending' ORDER BY r.created_at"
)->fetchAll();
$drugs = $pdo->query('SELECT * FROM drugs ORDER BY name')->fetchAll();
$low = count(array_filter($drugs, fn($d) => $d['quantity'] <= $d['reorder_level']));
$expiring = count(array_filter($drugs, fn($d) => $d['expiry_date'] && $d['expiry_date'] <= date('Y-m-d', strtotime('+90 days'))));

$layout = 'dash';
$pageTitle = 'Pharmacy';
require $ROOT . '/includes/header.php';
?>
<div class="grid gap-4 sm:grid-cols-3">
  <?= stat_card('Prescriptions to dispense', count($pending)) ?>
  <?= stat_card('Low stock items', $low) ?>
  <?= stat_card('Expiring within 90 days', $expiring) ?>
</div>

<section class="rounded-xl border bg-white p-5">
  <h2 class="mb-3 font-semibold">Pending prescriptions</h2>
  <div class="overflow-x-auto">
  <table class="w-full text-sm">
    <thead class="text-left text-slate-500"><tr><th class="py-2">Patient</th><th>Drug</th><th>Qty</th><th>Dosage</th><th>Doctor</th><th>In stock</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($pending as $r): ?>
      <tr class="border-t">
        <td class="py-2"><?= e($r['patient']) ?> <span class="text-slate-400"><?= e($r['patient_no']) ?></span></td>
        <td><?= e($r['drug_name']) ?></td><td><?= (int)$r['quantity'] ?></td><td><?= e($r['dosage']) ?></td><td><?= e($r['doctor']) ?></td>
        <td><?= $r['in_stock'] === null ? '<span class="text-red-600">Not listed</span>' : (int)$r['in_stock'] ?></td>
        <td class="text-right"><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="dispense"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
          <button class="<?= BTN_CLASS ?>">Dispense</button></form></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$pending): ?><tr><td colspan="7" class="py-4 text-slate-500">Nothing waiting.</td></tr><?php endif; ?>
    </tbody>
  </table>
  </div>
</section>

<section class="rounded-xl border bg-white p-5">
  <h2 class="mb-4 font-semibold">Add a drug or restock</h2>
  <form method="post" class="grid gap-3 md:grid-cols-5">
    <?= csrf_field() ?><input type="hidden" name="action" value="drug">
    <div class="md:col-span-2"><label class="mb-1 block text-sm">Drug name</label><input class="<?= INPUT_CLASS ?>" name="name" maxlength="120" required></div>
    <div><label class="mb-1 block text-sm">Quantity added</label><input class="<?= INPUT_CLASS ?>" type="number" name="quantity" min="0" value="0"></div>
    <div><label class="mb-1 block text-sm">Unit price</label><input class="<?= INPUT_CLASS ?>" type="number" step="0.01" min="0" name="unit_price" value="0"></div>
    <div><label class="mb-1 block text-sm">Reorder level</label><input class="<?= INPUT_CLASS ?>" type="number" min="0" name="reorder_level" value="10"></div>
    <div><label class="mb-1 block text-sm">Expiry date</label><input class="<?= INPUT_CLASS ?>" type="date" name="expiry_date"></div>
    <div class="flex items-end"><button class="<?= BTN_CLASS ?>">Save stock</button></div>
  </form>
</section>

<section class="rounded-xl border bg-white p-5">
  <div class="mb-3 flex items-center justify-between gap-3">
    <h2 class="font-semibold">Stock</h2>
    <input data-filter="#stockTable" class="<?= INPUT_CLASS ?> max-w-xs" placeholder="Search drugs">
  </div>
  <div class="overflow-x-auto">
  <table id="stockTable" class="w-full text-sm">
    <thead class="text-left text-slate-500"><tr><th class="py-2">Drug</th><th>Quantity</th><th>Unit price</th><th>Expiry</th><th>Alert</th></tr></thead>
    <tbody>
    <?php foreach ($drugs as $d):
        $isLow = $d['quantity'] <= $d['reorder_level'];
        $isExp = $d['expiry_date'] && $d['expiry_date'] <= date('Y-m-d', strtotime('+90 days')); ?>
      <tr class="border-t">
        <td class="py-2"><?= e($d['name']) ?></td><td><?= (int)$d['quantity'] ?></td><td><?= e(number_format((float)$d['unit_price'], 2)) ?></td><td><?= e($d['expiry_date'] ?? '') ?></td>
        <td><?= $isLow ? '<span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs text-amber-800">Low stock</span> ' : '' ?><?= $isExp ? '<span class="rounded-full bg-red-100 px-2 py-0.5 text-xs text-red-700">Expiring</span>' : '' ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$drugs): ?><tr><td colspan="5" class="py-4 text-slate-500">No drugs added yet.</td></tr><?php endif; ?>
    </tbody>
  </table>
  </div>
</section>
<?php require $ROOT . '/includes/footer.php';