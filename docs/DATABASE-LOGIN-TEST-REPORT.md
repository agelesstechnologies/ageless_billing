# Database login and Android admin update — verification

9 October 2026. Android 1.1.0 / versionCode 2, application ID `com.ageless.staff`.

| Check | Result |
| --- | --- |
| Laravel feature suite, isolated in-memory SQLite | 91 tests / 430 assertions passed |
| Real HTTP integration: database admin login, admin-created staff, staff login, CSRF, persisted punches, History, admin register, password/session revocation | 14 scenarios passed |
| APK JavaScript adapter: location, punch/retry, error handling, site centre | 17 Chromium scenarios passed |
| Java server URL, origin and attendance/admin route policy | 85 checks passed |
| Official SDK/JDK build, DEX/resources, APK signing v2/v3, alignment | Passed |
| Same signer as earlier APK; higher versionCode | Passed; update identity preserved |
| Case-insensitive `DB_CONNECTION=MySQL` selects configured mysql driver | Passed; configuration selection only |
| Supplied SQLite database and .env compared byte-for-byte after changes | Identical; existing user hashes and records retained |
| Mobile Home and employee form at 390px | No horizontal overflow; screenshots inspected |
| Live installation / actual MySQL server / physical Android GPS | Not tested |

The HTTP integration used a new, disposable SQLite database and synthetic accounts. An admin whose stored email contained uppercase letters and spaces signed in, created a staff account through the actual form, and the new staff signed in and punched in/out through the APK adapter. Database verification found a hashed staff password, one closed shift and exactly two raw punch events. Staff received HTTP 403 for admin user creation. A subsequent admin request read the same staff/attendance records. A password change in the database invalidated the existing admin HTTP session.

Native location callbacks were simulated in Chromium. The APK was compiled with Android SDK 35 / JDK 17; Android Studio/Gradle and physical device execution were not tested. The software emulator test from the earlier release did not complete; no native runtime success is claimed. Test GPS permissions, precise location, actual site radius and the real server address on a phone after updating the ERP.

No live database migration or credential reset was performed. On the real server, use the diagnostic/reset commands described in the Malayalam guide. The existing database is never substituted with a demo database or reset by these commands. Do not use `migrate:fresh` or a fixture directory on real data.

Official references: [Laravel authentication](https://laravel.com/docs/12.x/authentication), [database configuration](https://laravel.com/docs/12.x/database), [Android APK signing](https://developer.android.com/tools/apksigner).
