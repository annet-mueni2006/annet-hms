<?php
declare(strict_types=1);

/** Shared data helpers used by several dashboards. */

/** Validate patient form input. Returns [cleanData, errors]. */
function patient_input(array $in): array
{
    $d = [
        'full_name'   => trim((string)($in['full_name'] ?? '')),
        'phone'       => trim((string)($in['phone'] ?? '')),
        'dob'         => trim((string)($in['dob'] ?? '')),
        'gender'      => trim((string)($in['gender'] ?? '')),
        'address'     => trim((string)($in['address'] ?? '')),
        'next_of_kin' => trim((string)($in['next_of_kin'] ?? '')),
    ];
    $err = [];
    if (mb_strlen($d['full_name']) < 3) $err[] = 'Enter the patient\'s full name.';
    if ($d['phone'] !== '' && !preg_match('/^[0-9+\-\s]{7,20}$/', $d['phone'])) $err[] = 'Enter a valid phone number.';
    if ($d['dob'] !== '') {
        $dt = DateTime::createFromFormat('Y-m-d', $d['dob']);
        if (!$dt || $dt->format('Y-m-d') !== $d['dob'] || $dt > new DateTime('today')) $err[] = 'Enter a valid date of birth.';
    }
    if ($d['gender'] !== '' && !in_array($d['gender'], ['female', 'male', 'other'], true)) $err[] = 'Choose a valid gender.';
    foreach (['dob', 'gender', 'address', 'next_of_kin', 'phone'] as $k) if ($d[$k] === '') $d[$k] = null;
    return [$d, $err];
}

/** Insert a patient record that has no login account (walk-in). Returns the new patient id. */
function create_patient_record(array $d): int
{
    db()->prepare('INSERT INTO patients (patient_no, full_name, phone, dob, gender, address, next_of_kin) VALUES (?,?,?,?,?,?,?)')
        ->execute([new_patient_no(), $d['full_name'], $d['phone'], $d['dob'], $d['gender'], $d['address'], $d['next_of_kin']]);
    return (int)db()->lastInsertId();
}

function patient_for_user(int $userId): ?array
{
    $st = db()->prepare('SELECT * FROM patients WHERE user_id = ?');
    $st->execute([$userId]);
    return $st->fetch() ?: null;
}

function doctors_list(): array
{
    return db()->query("SELECT id, full_name FROM users WHERE role='doctor' AND is_active=1 ORDER BY full_name")->fetchAll();
}

function patients_list(): array
{
    return db()->query('SELECT id, patient_no, full_name, phone FROM patients ORDER BY full_name')->fetchAll();
}

/** Parse a datetime-local value ("2026-10-24T09:30") and require a future time. Returns "Y-m-d H:i:s" or null. */
function future_datetime(string $v): ?string
{
    $dt = DateTime::createFromFormat('Y-m-d\TH:i', $v);
    if (!$dt || $dt <= new DateTime()) return null;
    return $dt->format('Y-m-d H:i:s');
}

function post_int(string $key): int { return (int)($_POST[$key] ?? 0); }
function post_str(string $key, int $max = 255): string { return mb_substr(trim((string)($_POST[$key] ?? '')), 0, $max); }

/** <option> list for a select. $rows: [id => label]. */
function options(array $rows, $selected = null, string $placeholder = 'Select...'): string
{
    $h = '<option value="">' . e($placeholder) . '</option>';
    foreach ($rows as $id => $label) {
        $h .= '<option value="' . e((string)$id) . '"' . ((string)$id === (string)$selected ? ' selected' : '') . '>' . e((string)$label) . '</option>';
    }
    return $h;
}

function patient_options(array $patients): array
{
    $o = [];
    foreach ($patients as $p) $o[$p['id']] = $p['full_name'] . ' (' . $p['patient_no'] . ')';
    return $o;
}

function status_badge(string $s): string
{
    $c = match ($s) {
        'completed', 'dispensed', 'checked_in' => 'bg-emerald-100 text-emerald-700',
        'cancelled'                            => 'bg-slate-200 text-slate-600',
        'pending'                              => 'bg-amber-100 text-amber-800',
        default                                => 'bg-sky-100 text-sky-700',
    };
    return '<span class="rounded-full px-2 py-0.5 text-xs ' . $c . '">' . e(str_replace('_', ' ', $s)) . '</span>';
}

/** Reusable patient registration form fields. */
function patient_form_fields(): string
{
    $i = INPUT_CLASS;
    $today = date('Y-m-d');
    return <<<HTML
    <div><label class="mb-1 block text-sm">Full name</label><input class="$i" name="full_name" required></div>
    <div><label class="mb-1 block text-sm">Phone</label><input class="$i" name="phone"></div>
    <div><label class="mb-1 block text-sm">Date of birth</label><input class="$i" type="date" name="dob" max="$today"></div>
    <div><label class="mb-1 block text-sm">Gender</label>
      <select class="$i" name="gender"><option value="">Select...</option><option value="female">Female</option><option value="male">Male</option><option value="other">Other</option></select></div>
    <div><label class="mb-1 block text-sm">Address</label><input class="$i" name="address"></div>
    <div><label class="mb-1 block text-sm">Next of kin</label><input class="$i" name="next_of_kin"></div>
HTML;
}