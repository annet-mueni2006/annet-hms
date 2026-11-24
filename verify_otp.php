<?php
declare(strict_types=1);
require __DIR__ . '/config/bootstrap.php';
require __DIR__ . '/config/mailer.php';
require __DIR__ . '/includes/layout.php';

if (current_user()) redirect(role_home(current_user()['role']));

$email = $_SESSION['verify_email'] ?? '';
if ($email === '') {
    flash('error', 'Start by registering your account.');
    redirect('register.php');
}

const MAX_ATTEMPTS = 5;
const RESEND_COOLDOWN = 60; // seconds

$stmt = db()->prepare('SELECT * FROM pending_registrations WHERE email = ?');
$stmt->execute([$email]);
$pending = $stmt->fetch();
if (!$pending) {
    unset($_SESSION['verify_email']);
    flash('error', 'No pending registration found. Please register again.');
    redirect('register.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? 'verify';

    if ($action === 'resend') {
        $since = time() - strtotime($pending['last_sent_at']);
        if ($since < RESEND_COOLDOWN) {
            flash('error', 'Please wait ' . (RESEND_COOLDOWN - $since) . ' seconds before asking for a new code.');
        } else {
            $otp = (string)random_int(100000, 999999);
            $ttl = (int)env('OTP_TTL_MINUTES', '10');
            $up = db()->prepare('UPDATE pending_registrations SET otp_hash=?, otp_expires_at=DATE_ADD(NOW(), INTERVAL ? MINUTE), attempts=0, last_sent_at=NOW() WHERE id=?');
            $up->execute([password_hash($otp, PASSWORD_DEFAULT), $ttl, $pending['id']]);
            $sent = send_otp_email($email, $pending['full_name'], $otp);
            flash($sent ? 'success' : 'error', $sent ? 'A new code was sent.' : 'Could not send the email. Try again shortly.');
        }
        redirect('verify_otp.php');
    }

    $code = preg_replace('/\D/', '', (string)($_POST['otp'] ?? ''));

    if ($pending['attempts'] >= MAX_ATTEMPTS) {
        flash('error', 'Too many wrong attempts. Request a new code.');
    } elseif (strtotime($pending['otp_expires_at']) < time()) {
        flash('error', 'That code has expired. Request a new one.');
    } elseif (!password_verify($code, $pending['otp_hash'])) {
        db()->prepare('UPDATE pending_registrations SET attempts = attempts + 1 WHERE id = ?')->execute([$pending['id']]);
        flash('error', 'Incorrect code. Please check your email and try again.');
    } else {
        // Code is correct: create the real account inside one transaction.
        $pdo = db();
        try {
            $pdo->beginTransaction();
            $pdo->prepare('INSERT INTO users (full_name, email, phone, password_hash, role, email_verified_at) VALUES (?,?,?,?,?,NOW())')
                ->execute([$pending['full_name'], $pending['email'], $pending['phone'], $pending['password_hash'], 'patient']);
            $userId = (int)$pdo->lastInsertId();
            $pdo->prepare('INSERT INTO patients (user_id, patient_no, full_name, phone) VALUES (?,?,?,?)')
                ->execute([$userId, new_patient_no(), $pending['full_name'], $pending['phone']]);
            $pdo->prepare('DELETE FROM pending_registrations WHERE id = ?')->execute([$pending['id']]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            flash('error', 'Could not finish registration. If you already have an account, please log in.');
            redirect('login.php');
        }
        unset($_SESSION['verify_email']);
        flash('success', 'Email verified. Your account is ready, please log in.');
        redirect('login.php');
    }
    redirect('verify_otp.php');
}

$wait = max(0, RESEND_COOLDOWN - (time() - strtotime($pending['last_sent_at'])));
$layout = 'auth';
$pageTitle = 'Verify your email';
require $ROOT . '/includes/header.php';
?>
<div class="flex min-h-screen items-center justify-center bg-gradient-to-br from-brand-50 to-white px-4 py-10">
  <div class="w-full max-w-md rounded-2xl border bg-white p-8 shadow-lg">
    <h1 class="text-2xl font-bold">Verify your email</h1>
    <p class="mb-6 mt-1 text-sm text-slate-500">Enter the 6-digit code sent to <strong><?= e($email) ?></strong>.</p>
    <?php render_flashes(); ?>
    <form method="post" class="space-y-4">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="verify">
      <input id="otp" name="otp" inputmode="numeric" maxlength="6" autocomplete="one-time-code" autofocus
             class="<?= INPUT_CLASS ?> text-center text-2xl tracking-[0.6em]" placeholder="------" required>
      <button class="<?= BTN_CLASS ?> w-full py-2.5">Verify and continue</button>
    </form>
    <form method="post" class="mt-4 text-center">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="resend">
      <button id="resendBtn" data-wait="<?= $wait ?>" class="text-sm text-brand-700 disabled:text-slate-400">Resend code</button>
    </form>
    <p class="mt-4 text-center text-sm"><a class="text-slate-500" href="<?= e(url('register.php')) ?>">Use a different email</a></p>
  </div>
</div>
<?php require $ROOT . '/includes/footer.php';