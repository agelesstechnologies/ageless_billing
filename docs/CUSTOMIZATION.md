# Customization map

No-code settings: park name/tagline, address, phone, tax ID, logo, two accent colours, greeting, bill prefix, one tax rate/label/mode, receipt width and footer. Package cards have editable descriptions, icons, colours, price, points and validity. Rides control point cost, credit-pulse duration and cooldown. Staff roles and readers are managed from their own screens.

For other changes, the complete application source is included:

| Change | Main files |
|---|---|
| Visual theme / layout | `public/assets/app.css`, `resources/views/layouts/app.blade.php` |
| Counter interactions | `public/assets/app.js`, `resources/views/sales/create.blade.php` |
| Receipt layout | `resources/views/sales/show.blade.php` |
| Price / integer tax calculations | `app/Domain/Money.php`, `app/Services/SaleService.php` |
| Point authorization / recovery policy | `app/Domain/RedemptionPolicy.php`, `app/Services/RedemptionService.php` |
| ESP API contract | `routes/api.php`, `app/Http/Controllers/MachineApiController.php` |
| Tables / indexes | `database/migrations/2026_10_06_000001_create_park_tables.php` |
| Initial illustrative packages / rides | `database/seeders/DemoSeeder.php` |
| Scanner / credit interface | `firmware/AgelessParkReader/` |

Do not edit an already-run migration to change a live database; add a new forward migration after backup/testing. Do not rewrite historical sale items or delete audit/point entries to hide corrections. Record explicit reversals or adjustments.

The ParkDesk/Ageless navigation mark is part of the layout source. The park's own name appears in the workspace and its logo/name appear on receipts. Settings do not provide a drag-and-drop UI builder or arbitrary custom fields.

Application code is supplied for customization. Third-party dependencies retain their own licences and notices when installed by Composer. No proprietary font files are bundled.


## V2 site CRM / attendance customization

Mobile staff layout: `resources/views/layouts/staff.blade.php`, `resources/views/staff/`, `public/assets/staff.css`.
Shared premium branding: `public/assets/premium.css`, `resources/views/layouts/app.blade.php` and `resources/views/auth/login.blade.php`.
Attendance UI client: `public/assets/attendance.js`; real writes use the same-origin session and CSRF, not device API tokens.
Site rules and business logic: `app/Domain/AttendancePolicy.php`, `app/Services/AttendanceService.php`, `AttendanceCorrectionService.php`.
Admin reports/review: `app/Http/Controllers/AttendanceAdminController.php`, `resources/views/crm/`.
Schema: additive migration `2026_10_06_000002_add_site_attendance.php`. Do not edit a migration already applied to a live DB; add a new one.

The auth route, role rules and Users table are part of this application. An unrelated site's existing CRM requires deliberate merging, not file replacement. Do not bypass `auth`, `active`, `billing` or `admin` middleware to make integration easier. Do not weaken geolocation permission or accuracy requirements without reviewing the documented behaviour and tests.
