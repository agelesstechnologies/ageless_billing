# Changelog

## v2.0-source — 2026-10-06

Added site/staff attendance schema, employee profile fields, Staff-only mobile login route, same-origin CSRF-protected location punches, UTC event casts, snapshotted geofence rules, recorded-time history, admin attendance filters/export, supervisor-review flags and reasoned correction approval/rejection preserving raw GPS evidence. Added explicit billing-role middleware so staff-only users do not inherit cashier access. Updated same-origin geolocation permission header and JSON login-expiration handling.

Added premium park-themed shared workspace, mobile staff tabs, new login, CRM and site/staff-management views. Added source documentation, additive-upgrade guide, safe sample previews, independent policy and mocked-browser tests, and 42 Laravel attendance feature-test methods (not executed here).

Preserved baseline billing/QR core, machine API and firmware. No new Composer dependency was introduced. No live website/database was accessed, updated or imported. Existing admin/cashier accounts are not auto-enabled for attendance; site coordinates and staff assignment must be configured. Full Laravel, real GPS and hardware acceptance remain outstanding.
