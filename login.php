<?php
declare(strict_types=1);
require __DIR__ . '/config/bootstrap.php';
require __DIR__ . '/includes/layout.php';

if (current_user()) redirect(role_home(current_user()['role']));

$email = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $pass  = (string)($_POST['password'] ?? '');

    $st = db()->prepare('SELECT * FROM users WHERE email = ?');
    $st->execute([$email]);
    $user = $st->fetch();

    if ($user && $user['locked_until'] && strtotime($user['locked_until']) > time()) {
        flash('error', 'Too many failed attempts. Try again in a few minutes.');
    } elseif (!$user || !password_verify($pass, $user['password_hash'])) {
        if ($user) {
            $fails = (int)$user['failed_logins'] + 1;
            $lock  = $fails >= 5 ? date('Y-m-d H:i:s', time() + 15 * 60) : null;
            db()->prepare('UPDATE users SET failed_logins = ?, locked_until = ? WHERE id = ?')
                ->execute([$lock ? 0 : $fails, $lock, $user['id']]);
        }
        flash('error', 'Wrong email or password.');
    } elseif (!$user['is_active']) {
        flash('error', 'This account has been disabled. Contact the administrator.');
    } elseif (!$user['email_verified_at']) {
        flash('error', 'Please verify your email first.');
    } else {
        session_regenerate_id(true);
        db()->prepare('UPDATE users SET failed_logins = 0, locked_until = NULL WHERE id = ?')->execute([$user['id']]);
        $_SESSION['user'] = ['id' => (int)$user['id'], 'name' => $user['full_name'], 'email' => $user['email'], 'role' => $user['role']];
        audit('login', $user['role']);
        redirect(role_home($user['role']));
    }
    redirect('login.php');
}

$layout = 'auth';
$pageTitle = 'Log in';
require $ROOT . '/includes/header.php';
?>
<div class="flex min-h-screen items-center justify-center bg-gradient-to-br from-brand-50 to-white px-4 py-10">
  <div class="w-full max-w-md rounded-2xl border bg-white p-8 shadow-lg">
    <a href="<?= e(url('index.php')) ?>" class="text-sm text-brand-700">&larr; Back to home</a>
    <h1 class="mt-3 text-2xl font-bold">Welcome back</h1>
    <p class="mb-6 mt-1 text-sm text-slate-500">Log in to your <?= e((string)env('APP_NAME', 'Annet Healthcare')) ?> dashboard.</p>
    <?php render_flashes(); ?>
    <form method="post" class="space-y-4">
      <?= csrf_field() ?>
      <div><label class="mb-1 block text-sm font-medium">Email</label>
        <input class="<?= INPUT_CLASS ?>" type="email" name="email" value="<?= e($email) ?>" required autofocus autocomplete="username"></div>
      <div><label class="mb-1 block text-sm font-medium">Password</label>
        <input class="<?= INPUT_CLASS ?>" type="password" name="password" required autocomplete="current-password"></div>
      <button class="<?= BTN_CLASS ?> w-full py-2.5">Log in</button>
    </form>
    <p class="mt-5 text-center text-sm text-slate-500">New patient? <a class="text-brand-700 font-medium" href="<?= e(url('register.php')) ?>">Create an account</a></p>
  </div>
</div>
<?php require $ROOT . '/includes/footer.php';