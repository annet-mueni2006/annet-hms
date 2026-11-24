<?php
declare(strict_types=1);

/**
 * Creates the first administrator. Run once from the command line:
 *   php database/create_admin.php
 * The password is typed at the prompt, so it is never stored in a file or in Git.
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Run this from the command line.'); }

require dirname(__DIR__) . '/config/bootstrap.php';

function ask(string $label, bool $hidden = false): string
{
    echo $label . ': ';
    if ($hidden && stripos(PHP_OS, 'WIN') !== 0) system('stty -echo');
    $v = trim((string)fgets(STDIN));
    if ($hidden && stripos(PHP_OS, 'WIN') !== 0) { system('stty echo'); echo PHP_EOL; }
    return $v;
}

$name  = ask('Full name');
$email = strtolower(ask('Email'));
$pass  = ask('Password (8+ characters, letters and numbers)', true);

if (mb_strlen($name) < 3 || !filter_var($email, FILTER_VALIDATE_EMAIL) || !password_ok($pass)) {
    fwrite(STDERR, "Invalid input. Nothing was created.\n");
    exit(1);
}

$st = db()->prepare('SELECT 1 FROM users WHERE email = ?');
$st->execute([$email]);
if ($st->fetch()) { fwrite(STDERR, "That email already exists.\n"); exit(1); }

db()->prepare("INSERT INTO users (full_name, email, password_hash, role, email_verified_at) VALUES (?,?,?,'admin',NOW())")
    ->execute([$name, $email, password_hash($pass, PASSWORD_DEFAULT)]);
echo "Administrator created. Log in at /login.php\n";