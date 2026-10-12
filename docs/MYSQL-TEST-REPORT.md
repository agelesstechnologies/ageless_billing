# Laravel PHP / MySQL release — verification

9 October 2026. Existing Laravel 12/PHP application; MySQL is now the default connection. SQLite remains available explicitly for old-data import and isolated feature tests.

| Check | Result |
| --- | --- |
| Laravel feature suite, in-memory SQLite | **102 tests / 579 assertions passed** |
| Real MySQL engine | **MySQL 8.0.46 / InnoDB**, private disposable datadir |
| Migrations on MySQL | All three application migrations completed; tables/foreign keys created |
| Import and protection checks | **9 checks passed**: preview, maintenance guard, all-table copy/value comparison, repeat refusal, missing source, foreign-key rollback, configured-site protection and preserved synthetic admin hash |
| Supplied source data | All 15 application tables copied into private MySQL and compared field-by-field; JSON compared semantically and numeric values by value |
| Supplied data retained | 2 users, 6 packages, 6 rides, 1 reader, 1 QR wallet, 1 bill, 1 bill item, 2 point entries, 7 audit rows, 1 settings row and 1 work site; original attendance/redemption tables empty |
| HTTP app flows on MySQL | **13 scenarios passed**: admin database login, actual 25-ride form/ZIP, distinct reader configs, explicit enablement, POS QR, native QR decoding, deductions/replays, wallet/history, concurrent spending, staff creation, staff login/punches and admin attendance register |
| 25 reader deductions | One synthetic 10,000-point wallet ended at **6,750**; 25 debits totaling **3,250** |
| Concurrent final 10 points | One approval, one insufficient-points denial; balance 0 with one debit |
| Staff attendance | Admin created a hashed-password staff login; repeated punch-in stored one event; punch-out completed one shift with exactly two raw events; staff admin access returned 403 |
| Source SQLite file after tests | Byte-identical to the supplied archive's database |
| Original APP_KEY | Preserved; DB configuration changed without regenerating the key |
| Live server / actual user MySQL credentials | **Not connected, migrated or deployed** |
| Physical GPS / ESP32 camera / GPIO | **Not exercised**; test location data and HTTP pulse ACKs simulated |

The import opened the supplied SQLite source read-only and copied its real stored records only into a private MySQL test database. A separate synthetic SQLite source was imported to another MySQL fixture for browser/POS/staff testing, proving that its stored administrator hash still authenticated without rehashing or reset. The original source was not used for guest charging or password resets.

The negative import deliberately referenced a nonexistent user from a copied point entry. MySQL rejected the foreign key and rolled back all inserted tables, restoring the migration site placeholder. Existing destination records and configured sites were refused. Table creation/import used InnoDB with foreign keys enabled. Target migration bookkeeping was not overwritten. Test databases, credentials, SQL server runtime, sessions, private reader ZIPs and build caches are excluded from the deliverable.

The test server listened on loopback TCP only and was shut down after testing. Its MySQL version does not prove compatibility with every old server/version; configure and verify the actual target installation using START-HERE-MYSQL.md. The app is not a database server installer and does not guess real credentials. The import is for an empty destination, not merging two active ERPs.

Camera firmware is unchanged by the MySQL driver switch. Earlier verified bench/enabled-output builds and 643 native latch/approval checks remain described in ESP32-CAMERA-TEST-REPORT.md. The current HTTP script adds database-driver detection and staff scenarios; the earlier SQLite report describes the prior 10-scenario camera run.

Reproducible sources: tests/mysql_transition_test.py and firmware/AgelessCameraReader/tests/camera_erp_integration.cjs. The native test requires a separately initialized, explicitly marked disposable MySQL datadir, a compatible PHP with pdo_mysql/pdo_sqlite, a new fixture output directory, Playwright/Chromium and the previously built native QR decoder. Never run the fixture harness against a live server or reuse a live directory.

Detailed result summary: mysql-transition-checks.json. Official references: [Laravel database configuration](https://laravel.com/docs/12.x/database), [Laravel migrations](https://laravel.com/docs/12.x/migrations).
