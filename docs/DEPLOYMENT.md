# Hostinger deployment and recovery

This checklist deploys BluJay Interview Operations at `https://blujaytech.com/interviews`. Production needs PHP 8.2+, MySQL or MariaDB, Apache rewrite support, and the extensions listed in `.env.example`. It does not need Node.js, Docker, Redis, a queue worker, or any external service.

## A. Before deployment

1. Run every test against a disposable database whose name contains `test`.
2. Run PHP and JavaScript syntax checks, build the CSS, and run `php bin/console.php check` against a production-shaped staging configuration.
3. Review release migrations. Never edit a migration already recorded in `schema_migrations`.
4. Record the current application version and maintenance window. Keep the previous files until smoke testing finishes.

## B. Build frontend assets

Run `npm ci`, `npm run build:css`, and `node --check public/assets/app.js` on a development machine. Upload the generated files in `public/assets`. Production does not run npm and `node_modules` should not be uploaded.

## C. Back up the existing site

1. Export the application database from hPanel/phpMyAdmin as SQL, including structure, data, and foreign keys. Store it outside `public_html` and verify it has nonzero size.
2. Archive the current `public_html/interviews` files, including its private `.env`, outside the web root.
3. Back up `storage/uploads` and `storage/resumes` separately. Database rows reference these private profile and resume filenames, so the database and both directories form one recovery set.
4. Retain the backup for the agreed company retention period.

Restore the database and matching uploads from the same point. Restoring only one can produce missing photos or references to newer records. Sessions and rate-limit buckets do not need restoration.

## D. Upload the application

Upload the release to `public_html/interviews`. Include both `.htaccess` files and compiled assets. Exclude `.env`, local logs, `node_modules`, test data, editor files, and source-control metadata. Create the production `.env` directly on the server.

## E. Configure `/interviews`

The safest arrangement points the `/interviews` document root at `public/`. When hPanel cannot do that, the root `.htaccess` forwards public requests to `public/` and denies application code, migrations, storage, tests, documentation, dotfiles, and build files.

Keep `APP_URL=https://blujaytech.com/interviews` without a trailing slash. Asset URLs, forms, redirects, fetch calls, cookie paths, and profile photos derive from it. Confirm these return 403 or 404:

- `/interviews/.env`
- `/interviews/app/bootstrap.php`
- `/interviews/database/migrations/001_foundation.sql`
- `/interviews/storage/logs/php.log`
- `/interviews/tests/unit.php`

## F. Create and configure MySQL

Create a `utf8mb4` database and a dedicated user in hPanel. Grant access only to this database. Put credentials only in the private `.env`; never place them in documentation, shell history, or the web root.

## G. Configure `.env`

```dotenv
APP_ENV=production
APP_URL=https://blujaytech.com/interviews
APP_KEY=<64 random hexadecimal characters>
DB_HOST=<Hostinger database host>
DB_PORT=3306
DB_DATABASE=<database name>
DB_USERNAME=<database user>
DB_PASSWORD=<database password>
SESSION_IDLE_MINUTES=30
SESSION_SAVE_PATH=storage/sessions
```

Generate a fresh key with `php bin/console.php key:generate`. Do not reuse a test key or publish it.

## H. Run migrations

Run `php bin/console.php migrate` from the project root over SSH, then run it again to verify idempotency. The command takes a database advisory lock, records checksums, and skips applied migrations.

## I. Run seeds on first deployment

For a new empty database only, run `php bin/console.php seed`. Seeds install business hours, settings, resources, support people, and the scheduling mutex. They create no technologies, candidates, interviews, or administrator.

## J. Create the first administrator

If needed, set `BLUJAY_ADMIN_PASSWORD` only for the process and run `php bin/console.php admin:create --name="Operations Admin" --username=operations.admin`. Follow the secure environment-variable examples in the README and clear the variable immediately.

## K. Verify permissions

PHP must write `storage/logs`, `storage/sessions`, `storage/uploads`, and `storage/resumes`. Start with `0750`; use `0770` only when the Hostinger PHP group requires it. Never use `0777`. Application code, `.env`, and migrations should not be writable by the web process.

Errors go to `storage/logs/php.log`. The application records an opaque reference, exception class, and source location. It does not intentionally log passwords, password hashes, cookies, CSRF tokens, SQL arguments, or form data. Monthly, archive and compress old logs outside the web root, preserve a writable current file, delete archives under company retention policy, and recheck permissions.

## L. Run the application check

Run `php bin/console.php check`. It checks PHP 8.2+, extensions, writable private directories, key format, the production HTTPS `/interviews` URL, disabled displayed errors, database connectivity, `Asia/Kolkata`, and migration checksums. It never prints secrets.

## M. Verify HTTPS

Confirm the certificate and hosting-level HTTPS redirect. On HTTPS the application makes session cookies `Secure`, as well as `HttpOnly` and `SameSite=Lax`, and sends HSTS. Confirm login, logout, assets, fetch requests, and profile images stay under `/interviews`.

## N. Smoke tests

Use disposable acceptance records and remove them only through an approved maintenance process.

1. Open `/interviews`; confirm redirect to login.
2. Register a candidate using a configured technology.
3. Log out and sign in as that candidate.
4. Book a Normal interview; confirm its details and resource assignment.
5. Sign in as an administrator.
6. Confirm the booking on the dashboard.
7. Confirm it on the calendar at the correct IST time.
8. Open it and verify its resource.
9. Attempt overlapping Tool bookings with one support person; confirm the second is rejected.
10. Reschedule the acceptance booking and verify calendar placement.
11. Cancel it and verify it leaves active schedules.
12. Open Reports for its date and confirm totals update; inspect the filtered CSV.
13. Open Audit log and confirm readable create, reschedule, and cancellation events.
14. Log out and confirm the session is invalidated.
15. While logged out, request the Reports, Audit, and Search admin routes; confirm redirect to login. Confirm a candidate account receives 403.

## O. Rollback strategy

1. Restrict `/interviews` with Hostinger maintenance controls.
2. Preserve failed-release logs and note the failure time without copying secrets.
3. Restore the prior application archive.
4. If data changed incompatibly, restore the pre-deployment SQL export and matching `storage/uploads` and `storage/resumes`. Do not improvise a destructive down migration.
5. Restore the prior private `.env` if configuration changed.
6. Run `php bin/console.php check`, repeat smoke tests, and reopen access.

The Phase 4 migration adds indexes only, but a database backup remains mandatory.
