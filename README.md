# MediCare Connect (PHP + MySQL, XAMPP)

E-clinic appointment and patient-record system built from the SRS "MediCare Connect v1.0.0".

## 1. Run it on XAMPP (5 minutes)

1. Install and open **XAMPP**. Start **Apache** and **MySQL** in the control panel.
2. Copy the whole `medicare_connect` folder into `C:\xampp\htdocs\`.
3. Open **http://localhost/phpmyadmin** > **Import** > choose `database/medicare_connect.sql` > **Go**.
   (This creates the `medicare_connect` database, all tables and the demo data.)
4. Open **http://localhost/medicare_connect/**

Default XAMPP settings are already in `config/config.php` (user `root`, empty password). Change them there if yours differ.

## 2. Demo accounts (password for all: `Password@123`)

| Role | Email | Lands on |
|---|---|---|
| Super Admin | admin@medicare.test | Overview, users/RBAC, reports, audit log, outbox, backups |
| Reception staff | staff@medicare.test | Live queue, appointments, walk-in booking |
| Doctor | kwame@medicare.test (also efua@, selorm@) | Today's queue, consultation, e-prescriptions |
| Patient | patient@medicare.test (also kofi@, yaw@, akosua@) | Booking, live queue position, records |

**Multi-factor login:** after the password, a 6-digit code is "emailed". Because XAMPP cannot send email, **demo mode shows the code on screen** (and in Admin > Outbox). The same applies to the email-verification link on registration.

## 3. Suggested presentation flow

1. **Patient** registers > clicks the on-screen verification link > signs in with the code.
2. Fills profile (medical history questionnaire, emergency contact) > **Book**: pick specialty > doctor > date > slot > confirm (3 clicks).
3. **Pay** the fee with test card `4242 4242 4242 4242`.
4. **Reception** (second browser/incognito) > **Queue** > **Check in** the patient. The patient dashboard shows "N ahead of you" and refreshes itself every 10 s.
5. **Doctor** > **Call in** > record vitals, diagnosis, notes, add medications > **Complete consultation**.
6. **Patient** > **Records** shows the visit and the e-prescription (Print button).
7. **Admin** > **Run reminder job now** (24-hour reminders; see Outbox), **Reports** (average wait, revenue, CSV export), **Audit log**, **Users & roles**, **Create backup** (AES-256 encrypted).

Seed data already contains today's queue (2 patients waiting), a booking for tomorrow (reminder demo) and two past visits with prescriptions.

## 4. SRS requirement > where it is implemented

| SRS item | Implementation |
|---|---|
| 3.1 Secure registration, email verification, MFA login | `register.php`, `verify.php`, `login.php`, `mfa.php`, `includes/auth.php` |
| 3.1 Profile: medical history, emergency contacts | `patient/profile.php` |
| 3.1 Search doctors by specialty, view slots, book | `patient/book.php`, `available_slots()` / `book_slot()` in `includes/helpers.php` (unique `slot_key` prevents double booking) |
| 3.1 Confirmations & reminders | `includes/notify.php` (in-app + email + SMS), `patient/notifications.php` |
| 3.2 Doctor dashboard, e-prescriptions | `doctor/dashboard.php`, `doctor/consult.php`, `doctor/patients.php`, `doctor/schedule.php` |
| 3.2 Real-time queue & status tracking | `staff/queue.php`, patient + doctor dashboards (auto-refresh via `assets/js/app.js`) |
| 3.2 RBAC, user logs, reports | `require_role()` in `includes/auth.php`, `admin/users.php`, `admin/logs.php`, `admin/reports.php` |
| 3.3 Reminder dispatch 24 h before | `run_reminder_job()`; button in Admin, or `cron/send_reminders.php` via Task Scheduler |
| 3.3 Daily encrypted backups | `includes/backup.php` (AES-256-GCM), `admin/backup.php`, `cron/backup.php`, `cron/decrypt_backup.php` |
| 4.2 Bcrypt, RBAC, privacy practices | `password_hash(PASSWORD_BCRYPT)`, CSRF tokens, prepared statements (PDO), output escaping, login lockout, audit trail of record access |
| 4.3 WCAG 2.1 AA, <=3 clicks | Skip link, labels, focus outlines, contrast-checked colours, responsive layout, keyboard-operable slot picker |
| Objective: reduce wait time | Reports show **average wait** (check-in to start of consultation) |
| 5.3 Billing | `patient/pay.php` (simulated Stripe/PayPal checkout; only last 4 digits stored) |

## 5. What is simulated locally (and how to go live)

| SRS says | In this XAMPP build | To go to production |
|---|---|---|
| Twilio / SendGrid | Messages saved to the Outbox table, codes shown on screen (`DEMO_MODE`) | Put API calls in `dispatch_email()` / `dispatch_sms()` (`includes/notify.php`), set `DEMO_MODE` to `false` |
| Stripe / PayPal | Fake card form (Luhn check, test card) | Replace the block in `patient/pay.php` with the gateway SDK |
| React.js frontend | Server-rendered PHP + plain CSS/JS | Out of scope for a PHP/XAMPP demo |
| AWS, Docker, CI/CD, failover, TLS 1.3 | Local Apache + MySQL | Deployment concerns; terminate TLS on the web server/load balancer |
| 500+ concurrent users, 99.9% uptime | Not load-tested | Needs production infrastructure |

Change `BACKUP_KEY` in `config/config.php` before storing real backups.

## 6. Folder map

```
config/config.php        settings (DB, demo mode, keys)
database/*.sql           schema + demo data
includes/                db, auth/RBAC, helpers, notifications, backup, layout
patient/ doctor/ staff/ admin/   role modules
cron/                    reminder, backup, decrypt scripts
assets/                  CSS and JS
storage/backups/         encrypted backups (web access denied)
```

## 7. Scheduling the automatic jobs (optional)

Windows Task Scheduler, action "Start a program":
`C:\xampp\php\php.exe C:\xampp\htdocs\medicare_connect\cron\send_reminders.php` (every 30 min)
`C:\xampp\php\php.exe C:\xampp\htdocs\medicare_connect\cron\backup.php` (daily)
