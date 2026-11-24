<?php
declare(strict_types=1);
require dirname(__DIR__) . '/config/bootstrap.php';
require dirname(__DIR__) . '/includes/layout.php';

$user = require_role('admin');

$rows = db()->query(
    'SELECT a.*, u.full_name, u.role FROM audit_logs a LEFT JOIN users u ON u.id = a.user_id ORDER BY a.id DESC LIMIT 300'
)->fetchAll();

$layout = 'dash';
$pageTitle = 'Audit log';
require $ROOT . '/includes/header.php';
?>
<section class="rounded-xl border bg-white p-5">
  <div class="mb-3 flex items-center justify-between gap-3">
    <h2 class="font-semibold">Last 300 actions</h2>
    <input data-filter="#auditTable" class="<?= INPUT_CLASS ?> max-w-xs" placeholder="Search user or action">
  </div>
  <div class="overflow-x-auto">
  <table id="auditTable" class="w-full text-sm">
    <thead class="text-left text-slate-500"><tr><th class="py-2">When</th><th>User</th><th>Action</th><th>Detail</th><th>IP</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr class="border-t">
        <td class="py-2 whitespace-nowrap"><?= e($r['created_at']) ?></td>
        <td><?= e(($r['full_name'] ?? 'System') . ($r['role'] ? ' (' . $r['role'] . ')' : '')) ?></td>
        <td><?= e($r['action']) ?></td>
        <td><?= e($r['detail'] ?? '') ?></td>
        <td><?= e($r['ip'] ?? '') ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="5" class="py-4 text-slate-500">Nothing logged yet.</td></tr><?php endif; ?>
    </tbody>
  </table>
  </div>
</section>
<?php require $ROOT . '/includes/footer.php';