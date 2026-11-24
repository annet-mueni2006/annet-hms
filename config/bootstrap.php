<?php
declare(strict_types=1);

$ROOT = dirname(__DIR__);

// ---- .env loader (no external dependency) ----
function env(string $key, ?string $default = null): ?string
{
    static $vars = null;
    if ($vars === null) {
        $vars = [];
        $file = dirname(__DIR__) . '/.env';
        if (is_file($file)) {
            foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                $line = trim($line);
                if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) continue;
                [$k, $v] = explode('=', $line, 2);
                $vars[trim($k)] = trim(trim($v), "\"'");
            }
        }
    }
    return $vars[$key] ?? $default;
}

// ---- session hardening ----
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']),
    ]);
    session_start();
}

if (is_file($ROOT . '/vendor/autoload.php')) {
    require_once $ROOT . '/vendor/autoload.php';
}

// ---- database ----
function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . env('DB_HOST', '127.0.0.1') . ';dbname=' . env('DB_NAME', 'annet_hms') . ';charset=utf8mb4';
        $pdo = new PDO($dsn, env('DB_USER', 'root'), env('DB_PASS', ''), [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}

// ---- helpers ----
function e(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
/**
 * URL folder the app lives in, e.g. "/annet-hms" or "" at a domain root.
 * Uses APP_BASE from .env when the key exists; otherwise it is detected from the folder's position under the web root.
 */
function base(): string
{
    static $base = null;
    if ($base !== null) return $base;
    $set = env('APP_BASE');
    if ($set !== null) return $base = rtrim($set, '/');
    $doc  = realpath((string)($_SERVER['DOCUMENT_ROOT'] ?? ''));
    $root = realpath(dirname(__DIR__));
    if ($doc && $root && str_starts_with($root, $doc)) {
        return $base = rtrim(str_replace('\\', '/', substr($root, strlen($doc))), '/');
    }
    return $base = '';
}
function url(string $path): string { return base() . '/' . ltrim($path, '/'); }
function redirect(string $path): never { header('Location: ' . url($path)); exit; }

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . csrf_token() . '">'; }
function csrf_check(): void
{
    $ok = isset($_POST['csrf'], $_SESSION['csrf']) && hash_equals($_SESSION['csrf'], (string)$_POST['csrf']);
    if (!$ok) { http_response_code(419); exit('Session expired. Go back, refresh the page and try again.'); }
}

function flash(string $type, string $msg): void { $_SESSION['flash'][] = ['type' => $type, 'msg' => $msg]; }
function flashes(): array { $f = $_SESSION['flash'] ?? []; unset($_SESSION['flash']); return $f; }

function audit(string $action, string $detail = ''): void
{
    try {
        $st = db()->prepare('INSERT INTO audit_logs (user_id, action, detail, ip) VALUES (?,?,?,?)');
        $st->execute([$_SESSION['user']['id'] ?? null, $action, mb_substr($detail, 0, 255), $_SERVER['REMOTE_ADDR'] ?? null]);
    } catch (Throwable $e) { /* never break the page because of logging */ }
}

function role_home(string $role): string
{
    return match ($role) {
        'admin'        => 'admin/index.php',
        'receptionist' => 'receptionist/index.php',
        'doctor'       => 'doctor/index.php',
        'nurse', 'lab' => 'nurse/index.php',
        'pharmacist'   => 'pharmacy/index.php',
        default        => 'patient/index.php',
    };
}

function current_user(): ?array { return $_SESSION['user'] ?? null; }

function require_login(): array
{
    $u = current_user();
    if (!$u) redirect('login.php');
    return $u;
}

/** Allow only the listed roles; everyone else goes back to their own dashboard. */
function require_role(string ...$roles): array
{
    $u = require_login();
    if (!in_array($u['role'], $roles, true)) {
        flash('error', 'You do not have access to that page.');
        redirect(role_home($u['role']));
    }
    return $u;
}

function new_patient_no(): string
{
    do {
        $no = 'ANN-' . date('y') . '-' . str_pad((string)random_int(1, 99999), 5, '0', STR_PAD_LEFT);
        $st = db()->prepare('SELECT 1 FROM patients WHERE patient_no = ?');
        $st->execute([$no]);
    } while ($st->fetch());
    return $no;
}

function password_ok(string $p): bool
{
    return strlen($p) >= 8 && preg_match('/[A-Za-z]/', $p) && preg_match('/\d/', $p);
}