# BluJay Interview Operations

BluJay Technologies' internal interview support scheduling and candidate management system. It is a server-rendered PHP application designed for MySQL/MariaDB and Hostinger shared hosting. The production web server does not require Node.js, Docker, Redis, an external database, or a paid authentication service.

The application includes the complete Phase 1–4 product: secure authentication and profiles, transactional booking and deterministic resource assignment, administrator calendar and operations controls, reports and CSV export, audit review, global admin search, and production deployment checks.

## Requirements

- PHP 8.2 or later
- MySQL 8.x or MariaDB 10.4 or later, using InnoDB
- PHP extensions: `pdo_mysql`, `mbstring`, `fileinfo`, `gd`, and `session`
- Apache with `mod_rewrite` and `.htaccess` overrides for the documented Hostinger layout
- Writable `storage/logs`, `storage/sessions`, `storage/uploads`, and `storage/resumes`
- Node.js 20+ only when rebuilding CSS during development

The compiled stylesheet is committed at `public/assets/app.css`; production does not install npm packages.

## Local setup

1. Create a database with a UTF-8 character set:

   ```sql
   CREATE DATABASE blujay_interviews
     CHARACTER SET utf8mb4
     COLLATE utf8mb4_unicode_ci;
   ```

2. Copy `.env.example` to `.env`. Generate an application key and paste its output into `APP_KEY`:

   ```bash
   php bin/console.php key:generate
   ```

3. Configure `APP_URL` and the database values in `.env`. For PHP's local server use `APP_URL=http://localhost:8000`. Keep `SESSION_SAVE_PATH=storage/sessions` unless your host supplies a private, writable session directory.

4. Create the tables and install the production seed data:

   ```bash
   php bin/console.php migrate
   php bin/console.php seed
   ```

   Each migration/seed is checksummed and recorded in `schema_migrations`. Re-running these commands is safe. Never edit an applied SQL file; create a new numbered migration instead.

5. Provision the first administrator without placing a password in shell history. On Linux/macOS:

   ```bash
   read -s BLUJAY_ADMIN_PASSWORD
   export BLUJAY_ADMIN_PASSWORD
   php bin/console.php admin:create --name="Operations Admin" --username=operations.admin
   unset BLUJAY_ADMIN_PASSWORD
   ```

   In PowerShell:

   ```powershell
   $secure = Read-Host 'Admin password' -AsSecureString
   $pointer = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($secure)
   try {
       $env:BLUJAY_ADMIN_PASSWORD = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($pointer)
       php bin/console.php admin:create --name="Operations Admin" --username=operations.admin
   } finally {
       [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($pointer)
       Remove-Item Env:BLUJAY_ADMIN_PASSWORD
   }
   ```

   Passwords must contain 12–72 bytes. The command stores only a `password_hash()` result and installs no default credentials.

6. Verify the runtime, then start the development server:

   ```bash
   php bin/console.php check
   php -S localhost:8000 -t public public/router.php
   ```

7. Sign in as the administrator and add at least one active technology. Candidate registration deliberately stays closed until a real technology exists.

## Development workflow

Install and rebuild the local Tailwind asset after changing `resources/css/app.css` or view classes:

```bash
npm install
npm run build:css
```

Use `npm run watch:css` during UI work. Before committing, run:

```bash
php tests/unit.php
php tests/phase2.php
php tests/phase3.php
php tests/phase4.php
php bin/console.php check
find app public routes bin tests -name '*.php' -print0 | xargs -0 -n1 php -l
node --check public/assets/app.js
npm run build:css
```

The Phase 2–4 suites are database integration tests. Phase 2 includes multi-process concurrency races. Run each suite from a clean, disposable database with `APP_ENV=testing` and a database name containing `test`; the suites deliberately create fixtures and refuse any other environment.

On Windows, replace the `find` command with:

```powershell
Get-ChildItem app,public,routes,bin,tests -Recurse -Filter *.php |
    ForEach-Object { php -l $_.FullName }
```

Expired login-rate buckets can be removed from a scheduled task:

```bash
php bin/console.php maintenance:prune
```

Run that command daily. No background worker is required.

## Hostinger deployment at `/interviews`

1. In hPanel, create a MySQL/MariaDB database and user. Grant that user access only to this database.
2. Upload this project so its root is `public_html/interviews`. Upload dotfiles, including both `.htaccess` files. Do not upload `node_modules`, `.env`, test database files, or local logs.
3. Create `.env` on the server from `.env.example` and set `APP_ENV=production`, `APP_URL=https://blujaytech.com/interviews`, a newly generated `APP_KEY`, and the hPanel database credentials. The root `.htaccess` blocks direct access to `.env` and all private application directories.
4. Select PHP 8.2+ in hPanel and enable `pdo_mysql`, `mbstring`, `fileinfo`, `gd`, and `session`.
5. Make `storage/logs`, `storage/sessions`, `storage/uploads`, and `storage/resumes` writable by PHP. Start with directory mode `0750`; use `0770` only if the host's PHP group needs it. Never use `0777`.
6. Through Hostinger SSH, run `php bin/console.php migrate`, `php bin/console.php seed`, `php bin/console.php admin:create ...`, and `php bin/console.php check` from the project root.
7. Open `/interviews/login`, sign in, create the real technology list, register a disposable acceptance-test candidate, verify its profile and booking flow, then remove that test record directly before production onboarding. Candidate deletion is intentionally outside the current UI.
8. Confirm HTTPS is active before real use. Secure session cookies and HSTS activate automatically because the production `APP_URL` uses HTTPS.
9. Configure an hPanel cron job to run `php /absolute/path/public_html/interviews/bin/console.php maintenance:prune` once daily.

The root `.htaccess` internally exposes `public/` at the requested `/interviews` URL and denies application code, storage, database files, documentation, build files, and dotfiles. `public/.htaccess` routes application requests through the front controller and permits only `index.php` as a directly executable PHP file. If the host offers a dedicated document-root setting for this path, point it directly at `public/`; the application also supports that safer layout.

After deployment, verify that these URLs return `403` or `404`: `/interviews/.env`, `/interviews/app/bootstrap.php`, `/interviews/database/migrations/001_foundation.sql`, and `/interviews/storage/logs/php.log`.

## Security and data behavior

- The database connection and application timestamps use UTC. Business timezone configuration is seeded as `Asia/Kolkata`.
- Candidate phone numbers are canonical international numbers. Indian 10-digit mobile numbers receive the `+91` prefix.
- Candidate profiles store up to 20 skills as a normalized Tech Stack. Each interview independently selects one primary Interview Technology for scheduling and reporting.
- Authentication regenerates session IDs, enforces idle and absolute expiry, verifies CSRF tokens on every POST, reloads roles from the database, and rate-limits login and registration attempts with atomic InnoDB updates.
- Uploaded JPEG/PNG photos are MIME-checked, size/dimension limited, cropped, re-encoded by GD, assigned random names, and stored outside the public directory. Only the signed-in candidate can retrieve their photo.
- Error pages expose an opaque reference. Logs omit exception messages and submitted authentication/profile data.
- Production seeds create only business hours, configuration, physical resources, and the named support team. They create no technology, candidate, interview, or default administrator records.
- Candidate availability uses 15-minute starts and returns only slots that fit business hours, the configured advance window, candidate schedule, compatible resources, and the chosen Tool support person.
- Creation, rescheduling, and cancellation acquire the InnoDB `scheduling_mutex` row. All authoritative checks and the resulting write occur inside that transaction.
- Resource assignment chooses the available compatible resource with the fewest booked interviews on that local business date, breaking ties by resource ID.
- Candidates can access interview records only through queries scoped to their authenticated user ID. Physical resource assignments remain administrator-only.

See [the architecture notes](docs/ARCHITECTURE.md) for trust boundaries and [the scheduling specification](docs/SCHEDULING.md) for the implemented booking rules and transaction sequence.

## Booking routes

- `GET|POST /candidate/book` — booking workflow and atomic creation
- `GET /candidate/availability` — authenticated advisory JSON availability
- `GET /candidate/interviews` — upcoming, past, and cancelled records
- `GET /candidate/interview?id=…` — ownership-scoped candidate details
- `GET|POST /candidate/interview/reschedule` — atomic rescheduling
- `POST /candidate/interview/cancel` — soft cancellation
- `GET /admin/interviews` — filtered administrator booking directory
- `GET /admin/interview?id=…` — administrator details, including internal assignment

## Phase 3 administrator operations

- `GET /admin` — live daily metrics, today agenda, and now/next focus
- `GET /admin/calendar` — resource-lane day calendar and weekly agenda; accepts `date=YYYY-MM-DD` and `view=day|week`
- `GET|POST /admin/interview/new` — manual booking for an existing candidate
- `GET|POST /admin/interview/reschedule` and `POST /admin/interview/cancel` — administrator lifecycle controls
- `GET|POST /admin/resources` and `/admin/support` — scheduling capacity directories with future-booking safeguards
- `GET /admin/candidates` and `/admin/candidate?id=…` — searchable candidate directory and history
- `GET|POST /admin/settings` — notice, cutoff, and weekly business-hour configuration

The calendar loads one bounded, joined query for the selected day or week. Desktop uses dynamic resource lanes and 15-minute rows. Mobile switches to an agenda so bookings remain readable. Administrator time-cutoff overrides reuse the same InnoDB scheduling mutex and never bypass business hours, active-record checks, or candidate, resource, and support conflicts.

Run the Phase 3 integration suite against a fresh disposable test database after Phase 2:

```bash
php tests/phase3.php
php tests/phase4.php
```

See [admin operations](docs/ADMIN-OPERATIONS.md) for safeguards and operating behavior and [deployment](docs/DEPLOYMENT.md) for the production checklist, backups, smoke tests, and rollback.

## Phase 4 reporting and production operations

- `GET /admin/reports` — date/filter-aware reporting with booked-only workload and utilization measures
- `GET /admin/reports/export.csv` — filtered UTF-8 CSV with spreadsheet-formula neutralization
- `GET /admin/audit` and `/admin/audit/detail?id=…` — paginated audit review with secret redaction
- `GET /admin/search?q=…` — bounded administrator search used by the debounced header control

Phase 4 adds no notifications, external calendars, PDF generation, or infrastructure services. The production runtime remains PHP and MySQL/MariaDB.
