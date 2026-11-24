<?php
declare(strict_types=1);
require dirname(__DIR__) . '/config/bootstrap.php';
require dirname(__DIR__) . '/includes/layout.php';

$user = require_role('admin');
$pdo = db();

$count = fn(string $sql) => (int)$pdo->query($sql)->fetchColumn();
$byRole = $pdo->query("SELECT role, COUNT(*) c FROM users WHERE is_active = 1 GROUP BY role")->fetchAll(PDO::FETCH_KEY_PAIR);
$recent = $pdo->query('SELECT a.*, u.full_name FROM audit_logs a LEFT JOIN users u ON u.id = a.user_id ORDER BY a.id DESC LIMIT 8')->fetchAll();

$layout = 'dash';
$pageTitle = 'Admin overview';
require $ROOT . '/includes/header.php';
?>
<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
  <?= stat_card('Patients', $count('SELECT COUNT(*) FROM patients')) ?>
  <?= stat_card('Staff accounts', $count("SELECT COUNT(*) FROM users WHERE role NOT IN ('patient')")) ?>
  <?= stat_card('Appointments today', $count('SELECT COUNT(*) FROM appointments WHERE DATE(scheduled_at) = CURDATE()')) ?>
  <?= stat_card('Pending prescriptions', $count("SELECT COUNT(*) FROM prescriptions WHERE status='pending'")) ?>
</div>

<div class="grid gap-6 lg:grid-cols-3">
  <section class="rounded-xl border bg-white p-5 lg:col-span-1">
    <h2 class="mb-3 font-semibold">Active users by role</h2>
    <ul class="space-y-2 text-sm">
      <?php foreach (['admin','doctor','nurse','lab','receptionist','pharmacist','patient'] as $r): ?>
        <li class="flex justify-between"><span><?= e(ucfirst($r)) ?></span><span class="font-medium"><?= (int)($byRole[$r] ?? 0) ?></span></li>
      <?php endforeach; ?>
    </ul>
    <a href="<?= e(url('admin/users.php')) ?>" class="<?= BTN_CLASS ?> mt-5 inline-block">Add staff member</a>
  </section>
  <section class="rounded-xl border bg-white p-5 lg:col-span-2">
    <h2 class="mb-3 font-semibold">Recent activity</h2>
    <table class="w-full text-sm">
      <thead class="text-left text-slate-500"><tr><th class="py-1">When</th><th>User</th><th>Action</th></tr></thead>
      <tbody>
      <?php foreach ($recent as $r): ?>
        <tr class="border-t"><td class="py-2"><?= e($r['created_at']) ?></td><td><?= e($r['full_name'] ?? 'System') ?></td><td><?= e($r['action'] . ($r['detail'] ? ': ' . $r['detail'] : '')) ?></td></tr>
      <?php endforeach; ?>
      <?php if (!$recent): ?><tr><td colspan="3" class="py-4 text-slate-500">No activity yet.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </section>
</div>
<?php require $ROOT . '/includes/footer.php';