<?php
declare(strict_types=1);

/**
 * Shared page header. Include it from any page AFTER bootstrap.php and layout.php:
 *
 *   $layout    = 'public';   // 'public' = site menu + footer, 'auth' = bare page (login, register), 'dash' = sidebar dashboard
 *   $pageTitle = 'Page name';
 *   require $ROOT . '/includes/header.php';
 *   ... page content ...
 *   require $ROOT . '/includes/footer.php';
 *
 * Optional: $bodyClass to change the page background. Dashboards need $user (from require_role()).
 */
$layout    ??= 'public';
$pageTitle ??= '';
$bodyClass ??= $layout === 'public' ? 'bg-white' : 'bg-slate-50';
$appName   = e((string)env('APP_NAME', 'Annet Healthcare'));

// Compiled Tailwind when built (npm run build), otherwise the Tailwind CDN for quick development.
if (is_file(dirname(__DIR__) . '/assets/app.css')) {
    $cssTag = '<link rel="stylesheet" href="' . e(url('assets/app.css')) . '?v=' . filemtime(dirname(__DIR__) . '/assets/app.css') . '">';
} else {
    $cssTag = '<script src="https://cdn.tailwindcss.com"></script>'
            . '<script>tailwind.config={theme:{extend:{colors:{brand:{50:"#f0fdfa",100:"#ccfbf1",500:"#14b8a6",600:"#0d9488",700:"#0f766e",900:"#134e4a"}}}}}</script>';
}
?>
<!doctype html>
<html lang="en" class="scroll-smooth">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= e($pageTitle) ?> | <?= $appName ?></title>
  <?= $cssTag ?>
</head>
<body class="<?= e($bodyClass) ?> text-slate-800 antialiased">
<?php if ($layout === 'public'): $visitor = current_user(); ?>
<header class="sticky top-0 z-10 border-b bg-white/90 backdrop-blur">
  <div class="mx-auto flex max-w-6xl items-center justify-between px-6 py-4">
    <a href="<?= e(url('index.php')) ?>" class="text-xl font-bold text-brand-700"><?= $appName ?></a>
    <nav class="hidden gap-6 text-sm md:flex">
      <a href="<?= e(url('index.php#about')) ?>" class="hover:text-brand-700">About</a>
      <a href="<?= e(url('index.php#services')) ?>" class="hover:text-brand-700">Services</a>
      <a href="<?= e(url('index.php#how')) ?>" class="hover:text-brand-700">How it works</a>
      <a href="<?= e(url('index.php#contact')) ?>" class="hover:text-brand-700">Contact</a>
    </nav>
    <div class="flex gap-2 text-sm">
      <?php if ($visitor): ?>
        <a class="<?= BTN_CLASS ?>" href="<?= e(url(role_home($visitor['role']))) ?>">My dashboard</a>
      <?php else: ?>
        <a class="rounded-lg border px-4 py-2 hover:bg-slate-50" href="<?= e(url('login.php')) ?>">Log in</a>
        <a class="<?= BTN_CLASS ?>" href="<?= e(url('register.php')) ?>">Register</a>
      <?php endif; ?>
    </div>
  </div>
</header>

<?php elseif ($layout === 'dash'): $here = basename(dirname($_SERVER['SCRIPT_NAME'])) . '/' . basename($_SERVER['SCRIPT_NAME']); ?>
<div class="min-h-screen md:flex">
  <aside class="flex items-center justify-between bg-brand-900 p-4 text-white md:block md:min-h-screen md:w-60">
    <a href="<?= e(url('index.php')) ?>" class="text-lg font-bold tracking-tight"><?= $appName ?></a>
    <nav class="mt-8 hidden space-y-1 md:block" id="nav">
      <?php foreach (nav_for($user['role']) as [$label, $path]): ?>
        <a class="block rounded-lg px-3 py-2 text-sm <?= str_ends_with($path, $here) ? 'bg-brand-700' : 'hover:bg-brand-700/60' ?>" href="<?= e(url($path)) ?>"><?= e($label) ?></a>
      <?php endforeach; ?>
    </nav>
    <button id="navToggle" class="rounded bg-brand-700 px-3 py-1 text-sm md:hidden">Menu</button>
  </aside>
  <div class="min-w-0 flex-1">
    <header class="flex items-center justify-between border-b bg-white px-6 py-3">
      <h1 class="text-lg font-semibold"><?= e($pageTitle) ?></h1>
      <div class="flex items-center gap-3 text-sm">
        <span class="hidden text-slate-500 sm:inline"><?= e($user['name']) ?> · <?= e(ucfirst($user['role'])) ?></span>
        <a class="rounded-lg border px-3 py-1 hover:bg-slate-100" href="<?= e(url('logout.php')) ?>">Log out</a>
      </div>
    </header>
    <main class="space-y-6 p-6">
      <?php render_flashes(); ?>
<?php endif; ?>