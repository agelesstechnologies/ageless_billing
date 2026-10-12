# Start here — Laravel PHP + MySQL

This release uses **Laravel 12 / PHP 8.2+ / MySQL**. The staff app, administrator logins, employees, attendance, billing, QR wallets and ESP32 ride points use this same configured MySQL database. The existing camera firmware/API remain compatible.

The MySQL server must be available and you must provide its real account details. No database password has been guessed. The original SQLite file is retained as a source/backup; it is not the active application database in this release.

## 1. Create or select the MySQL database

Start your local MySQL service, or use the database supplied by your hosting panel. Create `ageless_park` with `utf8mb4_unicode_ci`, or use your existing ERP database name. With a MySQL administrator you can run `database/create-mysql-database.sql`; it creates only the database. Give your application MySQL user access to that database.

Enable `pdo_mysql` in the PHP used by both the command line and the web server. To import SQLite data, temporarily enable `pdo_sqlite` too. Check with `php -m`. The runtime application remains MySQL.

## 2. Set .env

Keep your existing APP_KEY and all other installation settings. Edit the DB section:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=ageless_park
DB_USERNAME=your_mysql_username
DB_PASSWORD="your_mysql_password"
```

The sample username/password above are placeholders. Use your actual values; a hosting database may have a prefixed name. `DB_HOST=127.0.0.1` applies when PHP and MySQL run on the same PC/server. Remove an old DB_URL if it points to SQLite or another database, as Laravel lets it override individual DB fields. Quote passwords containing spaces or `#`.

The Android app and ESP32 need the **ERP HTTP/HTTPS address**, not the MySQL host, port or password. Application login uses an admin/staff email and application password, not DB_USERNAME/DB_PASSWORD.

## 3. Choose the correct setup path

### A. Already using MySQL

Preserve your live .env, APP_KEY, database and uploads. Copy the updated project source, then:

```bash
php artisan config:clear
php artisan route:clear
php artisan migrate --force
php artisan park:database-check
```

Do not import the bundled SQLite file into an existing MySQL ERP. Continue using your current accounts and data.

### B. Move your existing SQLite installation to MySQL

1. Back up the complete current project, .env and current SQLite database, including any live WAL data. Stop the old installation's billing, punches and readers so no new records are written during the transfer. Use your latest actual SQLite file, which may be newer than the one included in this ZIP.
2. Preserve APP_KEY. Select a **new empty MySQL database** in .env. Before opening the app again, clear cached configuration and create the schema:

```bash
php artisan config:clear
php artisan route:clear
php artisan migrate --force
```

3. Preview the source data. This does not change records:

```bash
php artisan park:import-sqlite "database/database.sqlite"
```

Replace the path with your actual backed-up source path if different. The command requires this release's source tables; update/check an older source installation first if its schema is incomplete.

4. Keep the target app in maintenance mode and execute the copy:

```bash
php artisan down
php artisan park:import-sqlite "database/database.sqlite" --execute
php artisan park:database-check
php artisan up
```

Run each command in order and stop on an error. If import fails, row changes roll back and maintenance mode stays enabled until you resolve the error. Use `php artisan up` when ready. Import refuses existing destination ERP records or configured sites; it replaces only the untouched site placeholder created by migration. It does not merge two populated databases.

The copy preserves original IDs, password hashes, reader token hashes, bills, QR balances, point history, rides and staff attendance. It reads SQLite in read-only mode, copies data with MySQL foreign keys enabled, uses an InnoDB transaction and verifies row counts. MySQL migrations/bookkeeping remain the target's own. Repeating the import into the populated target is refused. Never use migrate:fresh, db:wipe or TRUNCATE on live data.

5. Verify an existing admin login, an employee login/punch, one bill/QR and one reader after the transfer. Keep the SQLite backup until you have verified the new installation. If a password was already unknown, use `park:admin-password` from your trusted server console; importing does not invent/reset it.

### C. Brand-new installation

After setting the real MySQL credentials:

```bash
php tools/prepare.php
php tools/key.php
php artisan config:clear
php artisan migrate --force
php artisan park:install
php artisan park:database-check
```

The supplied vendor dependencies are included; use Composer as needed for your environment. `tools/key.php` preserves an existing key. For an existing installation, keep your original key instead of generating a replacement. Do not create a new administrator before importing old records.

Windows: `01-setup-windows.bat` prepares the schema and checks the selected database. Import your old data using path B, or create the first admin using path C. Linux: `setup-linux.sh` has the same preparation flow. Start local testing with `02-start-windows.bat` or:

```bash
php artisan serve --host=0.0.0.0 --port=8000
```

## 4. Points, readers and attendance

Admin → **Rides & attractions → Set up 25 camera rides**: set actual names and points, then download unique reader configurations. The same bill QR wallet is charged at the assigned ride's server rate. MySQL handles balances/ledger transactions. Reader firmware does not connect directly to the database. See `docs/ESP32-CAMERA-MALAYALAM.md`.

Admin → **Staff Management / Add employee**: create the staff login and assign a real work site. The existing Ageless Staff APK connects to the same ERP address and saves punches in MySQL. No APK or firmware reinstallation is required solely because the backend database driver changed.

## If connection/login fails

| Problem | Action |
| --- | --- |
| could not find driver | Enable pdo_mysql for the PHP binary/web server you actually use. |
| Connection refused | Start MySQL; verify DB_HOST/DB_PORT and network access. |
| Access denied | Check the real MySQL username, password and database permissions. |
| Unknown database | Create the database or correct DB_DATABASE. |
| App has no old users after switching | Confirm the correct MySQL database; import the latest SQLite source into an empty target before creating replacement users. |
| Changed .env but app still uses old settings | Run php artisan config:clear; remove stale DB_URL if necessary. |
| Email/password incorrect | Use the application's admin email/password; run park:database-check, then the console password reset if needed. |

Local development uses LAN HTTP if explicitly configured; public deployment must serve only public/ over trusted HTTPS. Preserve APP_KEY/data/uploads and set production/debug/session options appropriately.

മലയാളം: `.env`ൽ യഥാർത്ഥ MySQL database name, username, password നൽകണം. പഴയ SQLite users/bills/points നിലനിർത്താൻ പുതിയ empty MySQL databaseൽ migrate ചെയ്ത ശേഷം `park:import-sqlite` ഉപയോഗിക്കുക. പഴയ database delete/reset ചെയ്യരുത്. എല്ലാ apps/readersഉം ERP വഴി ഇതേ MySQL database ആണ് ഉപയോഗിക്കുന്നത്.

Verification: `docs/MYSQL-TEST-REPORT.md`. Your live MySQL credentials, server deployment and physical phone/camera/GPIO have not been tested here.

Official references: [Laravel database configuration](https://laravel.com/docs/12.x/database), [Laravel migrations](https://laravel.com/docs/12.x/migrations), [PDO SQLite](https://www.php.net/manual/en/ref.pdo-sqlite.php).
