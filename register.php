<?php
declare(strict_types=1);
require __DIR__ . '/config/bootstrap.php';
require __DIR__ . '/config/mailer.php';
require __DIR__ . '/includes/layout.php';

if (current_user()) redirect(role_home(current_user()['role']));

$old = ['full_name' => '', 'email' => '', 'phone' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $name  = trim((string)($_POST['full_name'] ?? ''));
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $phone = trim((string)($_POST['phone'] ?? ''));
    $pass  = (string)($_POST['password'] ?? '');
    $conf  = (string)($_POST['password_confirm'] ?? '');
    $old   = ['full_name' => $name, 'email' => $email, 'phone' => $phone];
    $errors = [];

    if (mb_strlen($name) < 3)                          $errors[] = 'Enter your full name.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))    $errors[] = 'Enter a valid email address.';
    if ($phone !== '' && !preg_match('/^[0-9+\-\s]{7,20}$/', $phone)) $errors[] = 'Enter a valid phone number.';
    if (!password_ok($pass))                           $errors[] = 'Password must be at least 8 characters with letters and numbers.';
    if ($pass !== $conf)                               $errors[] = 'Passwords do not match.';

    if (!$errors) {
        $st = db()->prepare('SELECT 1 FROM users WHERE email = ?');
        $st->execute([$email]);
        if ($st->fetch()) $errors[] = 'An account with this email already exists. Please log in.';
    }

    if (!$errors) {
        $otp  = (string)random_int(100000, 999999);
        $ttl  = (int)env('OTP_TTL_MINUTES', '10');
        $st = db()->prepare(
            'INSERT INTO pending_registrations (email, full_name, phone, password_hash, otp_hash, otp_expires_at, last_sent_at)
             VALUES (?,?,?,?,?, DATE_ADD(NOW(), INTERVAL ? MINUTE), NOW())
             ON DUPLICATE KEY UPDATE full_name=VALUES(full_name), phone=VALUES(phone), password_hash=VALUES(password_hash),
               otp_hash=VALUES(otp_hash), otp_expires_at=VALUES(otp_expires_at), attempts=0, last_sent_at=NOW()'
        );
        $st->execute([$email, $name, $phone ?: null, password_hash($pass, PASSWORD_DEFAULT), password_hash($otp, PASSWORD_DEFAULT), $ttl]);

        if (send_otp_email($email, $name, $otp)) {
            $_SESSION['verify_email'] = $email;
            flash('success', 'We sent a 6-digit code to ' . $email . '. Enter it below to finish registration.');
            redirect('verify_otp.php');
        }
        $errors[] = 'We could not send the verification email. Check the mail settings and try again.';
    }
    foreach ($errors as $er) flash('error', $er);
}

$layout = 'auth';
$pageTitle = 'Create account';
require $ROOT . '/includes/header.php';
?>
<div class="flex min-h-screen items-center justify-center bg-gradient-to-br from-brand-50 to-white px-4 py-10">
  <div class="w-full max-w-md rounded-2xl border bg-white p-8 shadow-lg">
    <a href="<?= e(url('index.php')) ?>" class="text-sm text-brand-700">&larr; Back to home</a>
    <h1 class="mt-3 text-2xl font-bold">Create your patient account</h1>
    <p class="mb-6 mt-1 text-sm text-slate-500">We will email you a verification code to confirm your address.</p>
    <?php render_flashes(); ?>
    <form method="post" class="space-y-4" novalidate>
      <?= csrf_field() ?>
      <div><label class="mb-1 block text-sm font-medium">Full name</label>
        <input class="<?= INPUT_CLASS ?>" name="full_name" value="<?= e($old['full_name']) ?>" required autocomplete="name"></div>
      <div><label class="mb-1 block text-sm font-medium">Email</label>
        <input class="<?= INPUT_CLASS ?>" type="email" name="email" value="<?= e($old['email']) ?>" required autocomplete="email"></div>
      <div><label class="mb-1 block text-sm font-medium">Phone (optional)</label>
        <input class="<?= INPUT_CLASS ?>" name="phone" value="<?= e($old['phone']) ?>" autocomplete="tel"></div>
      <div><label class="mb-1 block text-sm font-medium">Password</label>
        <input class="<?= INPUT_CLASS ?>" type="password" name="password" required autocomplete="new-password"></div>
      <div><label class="mb-1 block text-sm font-medium">Confirm password</label>
        <input class="<?= INPUT_CLASS ?>" type="password" name="password_confirm" required autocomplete="new-password"></div>
      <button class="<?= BTN_CLASS ?> w-full py-2.5">Send verification code</button>
    </form>
    <p class="mt-5 text-center text-sm text-slate-500">Already registered? <a class="text-brand-700 font-medium" href="<?= e(url('login.php')) ?>">Log in</a></p>
  </div>
</div>
<?php require $ROOT . '/includes/footer.php';