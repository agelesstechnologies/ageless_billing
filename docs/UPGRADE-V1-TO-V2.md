# Safe update: the supplied Ageless Park Billing v1 → v2

**This is an update plan, not evidence that a live site has been updated.** No host, production database, accounts, SSL settings or reader configuration were changed during this task.

## Identify the application first

Use this plan only for the earlier `Ageless_Park_Billing_Laravel_ESP32.zip` application with its original migrations and tables. V2 keeps the original base migration and adds `2026_10_06_000002_add_site_attendance.php`.

For a different Laravel website/CRM, do not overwrite its project or database. A developer must reconcile its authentication, users table, middleware, migrations, route names, assets and layout. This release has root `/staff`, `/crm`, `/sites` and `/users` routes and cannot simply be embedded in an iframe (the app denies framing). A separate subdomain/new database is an isolation option, but does not provide single sign-on or shared user data automatically.

## Backup and stage

Stop scheduled jobs and device/operator writes during a consistent backup. Capture the database, `.env`, existing APP_KEY, uploaded files in `storage/app/public`, source and reviewed `composer.lock`. Protect backups from web access. Test a restore on an isolated copy. Do not copy an active SQLite WAL database file without using a consistent backup procedure.

Make a staging copy with its own database and test credentials, unreachable by real ride devices. Preserve environment/database paths carefully; never point staging to live. Install this source in staging, merge any local customizations, then run the migration and acceptance suites. Dependency versions remain compatible declarations from v1; a reviewed existing lockfile should be retained where valid, not silently replaced.

The migration adds employee columns, work sites, sessions, punches and correction requests. It does not change balances, bills, packages, rides or machine records. It creates one **unconfigured Main park**, leaves existing accounts' roles untouched and sets their attendance-enabled field false. Configure site/employee accounts explicitly after update. No location or existing attendance history is invented.

## Maintenance-window deployment

These commands are for the matching application after a successful staging rehearsal. Use a release/rollback procedure suitable for your host. Keep a terminal open and review every command result; do not continue after failure.

1. Arrange a maintenance window. Stop billing/ESP requests and staff punching. Ensure no ride transaction is unresolved without an operator plan. Take the tested consistent backup.
2. In the existing application, enter maintenance mode:

```bash
php artisan down
```

3. Deploy the reviewed V2 source, preserving the live `.env`, APP_KEY, DB data, uploads, maintenance state and reviewed dependency lockfile. Do not copy the ZIP's environment example over production configuration. Do not run demo installers on production. Use an atomic release mechanism or keep all traffic stopped while copying.
4. Install the reviewed dependency versions and refresh the application:

```bash
composer install --no-dev --prefer-dist --optimize-autoloader
php artisan migrate --force
php artisan optimize:clear
php artisan storage:link
php artisan optimize
```

An already-existing valid storage link need not be recreated. Configure write permissions without making `.env` or private directories public. If a command fails, keep maintenance enabled and investigate; do not blindly force the update or restore only part of a database.
5. Set the actual HTTPS `APP_URL`, `APP_ENV=production`, `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`, trusted certificate, correct reverse-proxy settings and server clock. Serve only `public/`. Rerun the configuration/cache commands after `.env` changes.
6. With administrator checks ready, exit maintenance and perform controlled smoke tests before general access:

```bash
php artisan up
```

7. Confirm original admin/cashier login, old receipt reprint, existing QR balance, test reader authentication and a controlled redemption/acknowledgement sequence. Configure Main park, enable one test staff member, and test HTTPS location + punch-in/out + administrator review on a real phone. Complete `ATTENDANCE-GO-LIVE.md` and `GO-LIVE.md` before allowing routine use.

If the host URL changed, update ESP `SERVER_URL` and the verified CA certificate used by its HTTPS client. Reader firmware is unchanged; there is no automatic remote firmware/configuration rollout.

## Rollback is not a database reset

Never run `migrate:fresh`, `db:wipe`, `migrate:reset` or casually regenerate `APP_KEY` on a used system. The new migration's `down` removes attendance tables: it is destructive and is not a routine repair command.

If rollback is required, stop all writes and restore a **consistent matching source/database/configuration snapshot** under a documented recovery procedure. New transactions after the backup need reconciliation; do not lose sales or punch records by an unreviewed restore. Do not run V1 after creating Staff-role accounts: V1 did not include the new role's billing-access boundary. Disable access until a qualified maintainer has reconciled roles and data.
