<?php
declare(strict_types=1);
require dirname(__DIR__) . '/config/bootstrap.php';
require dirname(__DIR__) . '/includes/layout.php';

$user = require_role('admin');
$pdo = db();
const STAFF_ROLES = ['admin', 'doctor', 'nurse', 'lab', 'receptionist', 'pharmacist'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name  = trim((string)($_POST['full_name'] ?? ''));
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        $phone = trim((string)($_POST['phone'] ?? ''));
        $role  = (string)($_POST['role'] ?? '');
        $pass  = (string)($_POST['password'] ?? '');

        $err = null;
        if (mb_strlen($name) < 3) $err = 'Enter the full name.';
        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $err = 'Enter a valid email.';
        elseif (!in_array($role, STAFF_ROLES, true)) $err = 'Choose a valid role.';
        elseif (!password_ok($pass)) $err = 'Password needs 8+ characters with letters and numbers.';
        else {
            $c = $pdo->prepare('SELECT 1 FROM users WHERE email = ?');
            $c->execute([$email]);
            if ($c->fetch()) $err = 'That email is already in use.';
        }
        if ($err) {
            flash('error', $err);
        } else {
            $pdo->prepare('INSERT INTO users (full_name, email, phone, password_hash, role, email_verified_at) VALUES (?,?,?,?,?,NOW())')
                ->execute([$name, $email, $phone ?: null, password_hash($pass, PASSWORD_DEFAULT), $role]);
            audit('user_created', "$role: $email");
            flash('success', ucfirst($role) . ' account created for ' . $name . '. Share the password with them securely.');
        }
    } elseif ($action === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id === $user['id']) {
            flash('error', 'You cannot disable your own account.');
        } else {
            $pdo->prepare('UPDATE users SET is_active = 1 - is_active WHERE id = ?')->execute([$id]);
            audit('user_toggled', "id $id");
            flash('success', 'Account status updated.');
        }
    } elseif ($action === 'reset') {
        $id = (int)($_POST['id'] ?? 0);
        $pass = (string)($_POST['password'] ?? '');
        if (!password_ok($pass)) {
            flash('error', 'New password needs 8+ characters with letters and numbers.');
        } else {
            $pdo->prepare('UPDATE users SET password_hash = ?, failed_logins = 0, locked_until = NULL WHERE id = ?')
                ->execute([password_hash($pass, PASSWORD_DEFAULT), $id]);
            audit('password_reset', "id $id");
            flash('success', 'Password reset.');
        }
    }
    redirect('admin/users.php');
}

$users = $pdo->query("SELECT id, full_name, email, phone, role, is_active, created_at FROM users ORDER BY role = 'patient', role, full_name")->fetchAll();

$layout = 'dash';
$pageTitle = 'Staff & users';
require $ROOT . '/includes/header.php';
?>
<section class="rounded-xl border bg-white p-5">
  <h2 class="mb-4 font-semibold">Add a staff member</h2>
  <form method="post" class="grid gap-4 md:grid-cols-3">
    <?= csrf_field() ?><input type="hidden" name="action" value="add">
    <div><label class="mb-1 block text-sm">Full name</label><input class="<?= INPUT_CLASS ?>" name="full_name" required></div>
    <div><label class="mb-1 block text-sm">Email</label><input class="<?= INPUT_CLASS ?>" type="email" name="email" required></div>
    <div><label class="mb-1 block text-sm">Phone</label><input class="<?= INPUT_CLASS ?>" name="phone"></div>
    <div><label class="mb-1 block text-sm">Role</label>
      <select class="<?= INPUT_CLASS ?>" name="role" required>
        <?php foreach (STAFF_ROLES as $r): ?><option value="<?= e($r) ?>"><?= e(ucfirst($r)) ?></option><?php endforeach; ?>
      </select></div>
    <div><label class="mb-1 block text-sm">Temporary password</label><input class="<?= INPUT_CLASS ?>" type="text" name="password" required autocomplete="off"></div>
    <div class="flex items-end"><button class="<?= BTN_CLASS ?>">Create account</button></div>
  </form>
</section>

<section class="rounded-xl border bg-white p-5">
  <div class="mb-3 flex items-center justify-between gap-3">
    <h2 class="font-semibold">All users</h2>
    <input data-filter="#usersTable" class="<?= INPUT_CLASS ?> max-w-xs" placeholder="Search name, email, role">
  </div>
  <div class="overflow-x-auto">
  <table id="usersTable" class="w-full text-sm">
    <thead class="text-left text-slate-500"><tr><th class="py-2">Name</th><th>Email</th><th>Role</th><th>Status</th><th class="text-right">Actions</th></tr></thead>
    <tbody>
    <?php foreach ($users as $u): ?>
      <tr class="border-t align-top">
        <td class="py-2"><?= e($u['full_name']) ?></td>
        <td><?= e($u['email']) ?></td>
        <td><?= e(ucfirst($u['role'])) ?></td>
        <td><span class="rounded-full px-2 py-0.5 text-xs <?= $u['is_active'] ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-600' ?>"><?= $u['is_active'] ? 'Active' : 'Disabled' ?></span></td>
        <td class="text-right">
          <div class="flex flex-wrap justify-end gap-2">
            <form method="post" data-confirm="Change this account's status?">
              <?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
              <button class="rounded border px-2 py-1 hover:bg-slate-50"><?= $u['is_active'] ? 'Disable' : 'Enable' ?></button>
            </form>
            <form method="post" class="flex gap-1">
              <?= csrf_field() ?><input type="hidden" name="action" value="reset"><input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
              <input class="w-28 rounded border px-2 py-1" name="password" placeholder="New password" required>
              <button class="rounded border px-2 py-1 hover:bg-slate-50">Reset</button>
            </form>
          </div>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</section>
<?php require $ROOT . '/includes/footer.php';