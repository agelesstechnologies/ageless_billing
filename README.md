# ParkDesk v2 — Billing, staff attendance and 25 camera rides

A source update of the supplied **Ageless Park Billing** Laravel 12 / PHP project. It adds site-staff attendance and a premium park-themed interface while keeping the original ticket billing, QR wallets and ESP32 redemption source.

**25-ride camera update (9 October 2026):** Admins can configure 25 ride names, points, pulse timing and cooldowns together at `/rides/setup-25`, then download a private ZIP of unique reader configurations. AI-Thinker ESP32-CAM + OV2640 firmware scans the existing bill QR and uses server-controlled wallet deductions. Default bench mode never charges. Existing rides, readers, user accounts and attendance are retained. Start with `docs/ESP32-CAMERA-MALAYALAM.md`; verification is in `docs/ESP32-CAMERA-TEST-REPORT.md`.

**Database login / Android admin update (9 October 2026):** The admin and staff APK use the project database. Admins can create database users from `/mobile`, and staff authenticate and save attendance through the same ERP. The current 102-test Laravel suite includes the database-login changes; the earlier 14 actual HTTP/SQLite admin/staff scenarios also passed. This package has not been deployed to your live server; physical GPS, MySQL server, printers and ESP hardware were not tested. Read `START-HERE-MALAYALAM.md` and `docs/DATABASE-LOGIN-TEST-REPORT.md`.

Start in Malayalam: **`START-HERE-MALAYALAM.md`**. Updating the earlier release: **`docs/UPGRADE-V1-TO-V2.md`**.

## What changed

| Area | Implemented in source |
| --- | --- |
| Camera ride setup | One 25-row admin form, configurable points per ride, unique reader tokens and an immediately downloadable camera code/config pack. |
| ESP32-CAM reader | Locally decoded bill QR, removal latch, persisted request retries, identity/TTL checks and one isolated credit pulse per approved play. |
| Staff mobile portal | Staff role always lands at `/staff` after login. Mobile-first My day / History / Requests / Profile tabs. |
| Attendance | Location-based punch-in and punch-out, server timestamps, open shift status, completed recorded duration and own history. |
| Work site | Name, address, timezone, centre coordinates, radius, required accuracy, optional geofence, active state. One default unconfigured site is created; no actual location is guessed. |
| Admin CRM | Attendance register, date/site/staff/status filters, current on-duty count, review flags, raw punch detail and CSV export. |
| Corrections | Staff requests for GPS/missed-punch issues; administrator approves/rejects with a reason and effective times. Original raw GPS punches remain unchanged. |
| Access | Separate admin, cashier and staff roles. Staff cannot access billing, device tokens, settings or other employees' attendance. Cashiers keep billing but cannot read all-staff CRM. |
| Design | Deep-green / warm-white park workspace, configurable accents and logo, ferris-wheel/roller-coaster artwork, responsive login, billing and CRM layouts. |

The ERP contains the site attendance module. The separately supplied **Ageless Staff Android 1.1.0 APK** opens the database-backed admin/staff workflows. Billing remains a single-park system; additional attendance sites do not partition bills into separate tenant accounts.

## Open the design before installation

Extract the whole ZIP and open `PREVIEW-STAFF.html`, `PREVIEW-CRM.html`, `PREVIEW-LOGIN.html` or `PREVIEW.html` (ticket counter) in a browser. Keep the `public/assets` folder beside them. These files are **sample-data static previews**, not working login or attendance backends. The staff preview never collects location or saves attendance. Screenshot images are under `docs/`.

## New local installation

PHP 8.2+ with the listed Laravel/PDO extensions and Composer is required. The initial dependency install needs internet. The supplied project includes its existing `vendor/` and `composer.lock`; dependencies were used for the passing test suite. Use `composer install --no-dev --prefer-dist --optimize-autoloader` when preparing your deployment environment.

Windows: run `01-setup-windows.bat`, enter your own administrator details, then run `02-start-windows.bat` and open `http://localhost:8000` on that computer.

Linux / macOS:

```bash
bash setup-linux.sh
php artisan serve --host=0.0.0.0 --port=8000
```

Manual fresh installation:

```bash
php tools/prepare.php
composer install --no-dev --prefer-dist --optimize-autoloader
php tools/key.php
php artisan config:clear
php artisan migrate --force
php artisan storage:link
php artisan park:install
php artisan park:database-check
```

There is no shared/default password. Sample ticket prices, points and tax settings must be reviewed. The prepare/key helpers preserve an existing environment and key; they do not reset the database.

**For the ordinary browser portal, a LAN HTTP address is not sufficient for browser geolocation.** The separate APK uses foreground native Android location on an explicitly configured private LAN HTTP server; hosted connections should use HTTPS. Use a reachable HTTPS site with a trusted certificate and allow location access. `localhost` on an employee's phone refers to that phone, not the billing PC. PHP's development server is only for testing, not public deployment. See official sources in `docs/REFERENCE-SOURCES.md`.

## Configure attendance

1. Log in as administrator. **Work site settings → Main park → Edit**. Set real site name, timezone, coordinates, allowed radius and accuracy. The location button fills the current device position, so use it at the intended site centre and verify it before saving. Enable the geofence only after a phone test.
2. **Staff access → Add staff**. Set role **Staff**, employee code/name, login email/password, department/designation, assigned site and **Enable attendance**. Existing admin/cashier accounts are not silently converted or enabled by migration.
3. On the employee phone, open the HTTPS login page. Staff role redirects to the mobile portal. Allow location, check location, then punch in. Punch out at the end of the shift. Administrators/cashiers can also open their own attendance portal after being explicitly assigned/enabled.
4. **Site CRM & attendance** shows the administrator's register. Open a row for location and raw punch evidence. Use **Punch requests** for missing/incorrect times and review exceptions.

Precise attendance rules and privacy: `docs/CRM-ATTENDANCE.md`. Acceptance checklist: `docs/ATTENDANCE-GO-LIVE.md`.

## Existing billing and QR functions retained

Tickets/packages and rides can be created, edited and soft-deleted. POS supports quantities, collected-payment recording, admin discounts, guest details and receipt reprint. A new bill creates **one shared points QR wallet**; a top-up reuses it. Packages supply points usable on enabled rides, not unlimited-ride entitlements or package-specific allowlists. Print formats remain 58 mm, 80 mm and A4. QR codes are rendered locally by BaconQrCode.

An ESP reader is assigned to a ride. Authenticated device requests debit points on the server; duplicate request IDs and uncertain-play review are retained. The camera release adds firmware and additive API v2 identity fields; the original UART firmware remains available. Billing calculation and the transactional redemption rules are retained. `docs/BASELINE-COMPATIBILITY.json` describes an earlier attendance-only release, not the camera release. Moving the host or enabling HTTPS still requires correct device URL and CA configuration. Firmware remains in non-charging bench mode by default. For camera readers, read `docs/ESP32-CAMERA-MALAYALAM.md`. The existing UART reader guide is `docs/ESP32.md`; commissioning checks remain in `docs/GO-LIVE.md`.

Guest points QR and employee attendance are separate systems: guest QR scans do not punch a staff member in or out.

## Deployment and boundaries

Serve only `public/` over trusted HTTPS. Preserve the live `.env`, APP_KEY, database and uploads. Set `APP_ENV=production`, `APP_DEBUG=false`, the correct HTTPS `APP_URL` and `SESSION_SECURE_COOKIE=true`. Configure trusted proxies appropriately where TLS terminates upstream. Do not publish previews, tests, source or backups through the document root.

Use a transactional MySQL/InnoDB database for a commissioned multi-reader/multi-staff deployment and exercise real concurrent requests on that target. SQLite is the local first-run default. Neither the supplied SQLite feature suite nor policy-only tests prove MySQL concurrency. No real site migration, hosting change, HR data import or earlier NFC/QR backend import has been performed.

Coordinates are browser-reported, not verified identity or tamper-proof proof of attendance. No continuous tracking, face/selfie collection, biometric/anti-mock-location enforcement, background tracking, offline attendance queue, GPS reverse-geocoding, roster/leave calculation or payroll is included. Review required sessions are visibly flagged, and the admin completed-time summary excludes them and open shifts. No pay or automatic absence inference is made.

Settings are configurable for the fields shown; deeper workflow changes require editing the included Laravel/Blade source. This is not a drag-and-drop app builder. Payments are recorded, not gateway-verified; tax remains the original generic single total, not a complete GST/accounting integration.

## Tests

Run without Composer:

```bash
php tests/domain_smoke.php
php tests/attendance_domain_smoke.php
node --check public/assets/app.js
node --check public/assets/attendance.js
```

In an isolated development/test copy, with PHP extensions and development dependencies:

```bash
php tools/prepare.php
composer install
php tools/key.php
php artisan config:clear
php artisan test
```

`phpunit.xml` forces an in-memory SQLite database. Never substitute live data. The current Laravel suite passed: **102 tests / 579 assertions**, using in-memory SQLite. The camera integration test launched a disposable Laravel ERP and verified real admin setup, 25 unique reader configs, POS QR issuance, native QR decoding, all 25 debits/retries and simultaneous final-balance requests. Older static browser tests use mocked location/HTTP; their results do not establish physical GPS operation. See `docs/TEST-REPORT.md` and `docs/ESP32-CAMERA-TEST-REPORT.md`.
