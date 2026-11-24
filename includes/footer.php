<?php
declare(strict_types=1);

/** Shared page footer. Pair with includes/header.php (uses the same $layout value). */
$layout  ??= 'public';
$appName = e((string)env('APP_NAME', 'Annet Healthcare'));
?>
<?php if ($layout === 'public'): ?>
<footer id="contact" class="scroll-mt-20 bg-brand-900 py-10 text-sm text-white">
  <div class="mx-auto flex max-w-6xl flex-col justify-between gap-4 px-6 md:flex-row">
    <div>
      <div class="text-lg font-bold"><?= $appName ?></div>
      <p class="text-brand-100">Replace with your address, phone and opening hours.</p>
    </div>
    <div class="text-brand-100">&copy; <?= date('Y') ?> <?= $appName ?>. All rights reserved.</div>
  </div>
</footer>

<?php elseif ($layout === 'dash'): ?>
    </main>
    <footer class="border-t bg-white px-6 py-3 text-xs text-slate-500">&copy; <?= date('Y') ?> <?= $appName ?></footer>
  </div>
</div>
<?php endif; ?>
<script src="<?= e(url('assets/app.js')) ?>"></script>
</body>
</html>