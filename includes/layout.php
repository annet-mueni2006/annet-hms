<?php
declare(strict_types=1);

/**
 * Small view helpers shared by the pages.
 * The page shell itself lives in includes/header.php and includes/footer.php.
 */

function render_flashes(): void
{
    foreach (flashes() as $f) {
        $c = $f['type'] === 'error' ? 'bg-red-50 text-red-800 border-red-200' : 'bg-emerald-50 text-emerald-800 border-emerald-200';
        echo '<div data-flash class="mb-4 rounded-lg border px-4 py-3 text-sm ' . $c . '">' . e($f['msg']) . '</div>';
    }
}

/** Navigation items per role: [label, path]. */
function nav_for(string $role): array
{
    return match ($role) {
        'admin' => [['Overview', 'admin/index.php'], ['Staff & users', 'admin/users.php'], ['Patients', 'admin/patients.php'], ['Audit log', 'admin/audit.php']],
        'receptionist' => [['Front desk', 'receptionist/index.php']],
        'doctor' => [['My day', 'doctor/index.php']],
        'nurse', 'lab' => [['Nurse & lab', 'nurse/index.php']],
        'pharmacist' => [['Pharmacy', 'pharmacy/index.php']],
        default => [['My health', 'patient/index.php']],
    };
}

function stat_card(string $label, string|int $value): string
{
    return '<div class="rounded-xl bg-white p-5 shadow-sm border"><div class="text-sm text-slate-500">' . e($label)
         . '</div><div class="mt-1 text-3xl font-semibold text-brand-700">' . e((string)$value) . '</div></div>';
}

const INPUT_CLASS = 'w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-600 focus:outline-none focus:ring-1 focus:ring-brand-600';
const BTN_CLASS   = 'rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700';