# Annet Healthcare Management System

PHP 8.1+, MySQL/MariaDB, Tailwind CSS, vanilla JavaScript, PHPMailer.

## Setup

1. **Database.** Import `database/schema.sql` (creates the `annet_hms` database and all tables).
2. **Packages.** `composer install` (installs PHPMailer).
3. **Settings.** Copy `.env.example` to `.env` and fill in database and mail values.
   - `APP_BASE` is optional. Leave it commented out and the app detects its own folder (for example `/annet-hms` under `htdocs`). Set it only if links go to the wrong place: `APP_BASE=/annet-hms`, or `APP_BASE=` (empty) at a domain root.
   - Gmail: turn on 2-Step Verification, create an App Password, and put it in `MAIL_PASS`. Never commit `.env`.
4. **First admin.** `php database/create_admin.php` and type the name, email and password at the prompt.
5. **Styles.** The pages load the Tailwind CDN until you build the stylesheet. For production run:
   ```
   npm install
   npm run build      # writes assets/app.css; pages switch to it automatically
   ```
6. Open `/index.php`.

## Reusable header and footer

Every page uses `includes/header.php` and `includes/footer.php`. Set two variables, include the header, write the page, include the footer:

```php
$layout    = 'dash';          // 'public' = site menu + footer, 'auth' = bare page, 'dash' = sidebar dashboard
$pageTitle = 'Front desk';
require $ROOT . '/includes/header.php';
// ... page content ...
require $ROOT . '/includes/footer.php';
```

Change the menu, sidebar or footer once in those two files and every page updates. Dashboard menus per role are defined in `nav_for()` in `includes/layout.php`.

## Who sees what

| Role | Entry page | Can do |
| --- | --- | --- |
| Admin | `admin/index.php` | Everything: add and disable staff, reset passwords, register patients, view the audit log |
| Receptionist | `receptionist/index.php` | Register walk-ins, book, check in and cancel appointments |
| Doctor | `doctor/index.php` | Today's queue, patient search, consultations, prescriptions, lab requests |
| Nurse / Lab | `nurse/index.php` | Record vitals, enter lab results |
| Pharmacist | `pharmacy/index.php` | Dispense prescriptions (reduces stock), manage drugs and expiry |
| Patient | `patient/index.php` | Own details, book and cancel appointments, visit history, prescriptions, lab results |

## Registration and login flow

`register.php` → saves a pending registration and emails a 6-digit OTP → `verify_otp.php` → on a correct code the patient account is created → `login.php` → role dashboard.

- OTP is stored hashed, expires after `OTP_TTL_MINUTES` (10), allows 5 wrong attempts and one resend per 60 seconds.
- Staff accounts are created only by the admin (`admin/users.php`).
- Five wrong passwords lock the account for 15 minutes.

## Security notes

- Secrets live only in `.env` (ignored by Git). If a password was ever pasted into chat or committed, revoke it and create a new one.
- All queries use PDO prepared statements; output is escaped with `e()`; every form carries a CSRF token; every dashboard calls `require_role()`.
- Serve over HTTPS in production and block direct access to `.env`, `config/`, `includes/`, `database/` and `storage/` in the web server config.