<?php
declare(strict_types=1);
require __DIR__ . '/config/bootstrap.php';
require __DIR__ . '/includes/layout.php';

$layout = 'public';
$pageTitle = 'Caring for you, every day';
$bodyClass = 'bg-white';
require $ROOT . '/includes/header.php';
$app = e((string)env('APP_NAME', 'Annet Healthcare'));
?>
<section class="bg-gradient-to-br from-brand-50 to-white">
  <div class="mx-auto grid max-w-6xl items-center gap-10 px-6 py-20 md:grid-cols-2">
    <div>
      <p class="mb-3 inline-block rounded-full bg-brand-100 px-3 py-1 text-xs font-semibold text-brand-700">Modern, connected healthcare</p>
      <h1 class="text-4xl font-bold leading-tight tracking-tight md:text-5xl">Your health records, appointments and care team in one place.</h1>
      <p class="mt-5 text-lg text-slate-600"><?= $app ?> brings patients, doctors, nurses, the laboratory and the pharmacy together, so you spend less time waiting and more time getting better.</p>
      <div class="mt-8 flex flex-wrap gap-3">
        <a class="<?= BTN_CLASS ?> px-6 py-3" href="<?= e(url('register.php')) ?>">Create patient account</a>
        <a class="rounded-lg border px-6 py-3 text-sm hover:bg-slate-50" href="#about">Learn more</a>
      </div>
    </div>
    <div class="rounded-2xl bg-white p-6 shadow-lg border">
      <div class="grid grid-cols-2 gap-4 text-center">
        <div class="rounded-xl bg-brand-50 p-5"><div class="text-3xl font-bold text-brand-700">24/7</div><div class="text-sm text-slate-600">Online access to your records</div></div>
        <div class="rounded-xl bg-brand-50 p-5"><div class="text-3xl font-bold text-brand-700">1 login</div><div class="text-sm text-slate-600">For appointments, results, prescriptions</div></div>
        <div class="rounded-xl bg-brand-50 p-5"><div class="text-3xl font-bold text-brand-700">Secure</div><div class="text-sm text-slate-600">Email-verified accounts</div></div>
        <div class="rounded-xl bg-brand-50 p-5"><div class="text-3xl font-bold text-brand-700">Fast</div><div class="text-sm text-slate-600">Shorter queues at the front desk</div></div>
      </div>
    </div>
  </div>
</section>

<section id="about" class="scroll-mt-20 mx-auto max-w-6xl px-6 py-16">
  <h2 class="text-3xl font-bold">About <?= $app ?></h2>
  <p class="mt-4 max-w-3xl text-slate-600">We are a healthcare facility built around one idea: every person who touches your care should see the same, up-to-date information. From the moment you register, your details follow you through triage, consultation, laboratory tests and the pharmacy, with privacy protected at every step.</p>
</section>

<section id="services" class="scroll-mt-20 bg-slate-50 py-16">
  <div class="mx-auto max-w-6xl px-6">
    <h2 class="text-3xl font-bold">Our services</h2>
    <div class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
      <?php
      $services = [
        ['Outpatient consultations', 'See a doctor with a booked time, so you are not left waiting.'],
        ['Nursing and triage', 'Vital signs and first assessment recorded the moment you arrive.'],
        ['Laboratory', 'Tests requested by your doctor, with results available online.'],
        ['Pharmacy', 'Prescriptions sent straight to the pharmacy and dispensed with stock checks.'],
        ['Patient portal', 'Book appointments and view your history, results and prescriptions.'],
        ['Reception and billing', 'Quick registration and clear records of every visit.'],
      ];
      foreach ($services as [$t, $d]): ?>
        <div class="rounded-xl border bg-white p-6 shadow-sm">
          <h3 class="font-semibold text-brand-700"><?= e($t) ?></h3>
          <p class="mt-2 text-sm text-slate-600"><?= e($d) ?></p>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section id="how" class="scroll-mt-20 mx-auto max-w-6xl px-6 py-16">
  <h2 class="text-3xl font-bold">How it works</h2>
  <ol class="mt-8 grid gap-6 md:grid-cols-4">
    <?php foreach (['Register with your email', 'Enter the 6-digit code we email you', 'Log in to your dashboard', 'Book, visit and follow your care online'] as $i => $s): ?>
      <li class="rounded-xl border p-5"><div class="mb-2 flex h-8 w-8 items-center justify-center rounded-full bg-brand-600 text-sm font-bold text-white"><?= $i + 1 ?></div><p class="text-sm"><?= e($s) ?></p></li>
    <?php endforeach; ?>
  </ol>
</section>

<?php require $ROOT . '/includes/footer.php';