# Go-live gate — not yet completed for this source release

For the new site CRM also complete `ATTENDANCE-GO-LIVE.md`. This file covers the retained billing/QR/device functions.

Use a separate test installation first. Keep the older live backend/website and its database untouched. No migration or import from the user's earlier projects has been performed.

## Application acceptance

Install Composer dependencies; keep the resulting reviewed composer.lock. Run the supplied Laravel feature tests. Resolve failures before real use. Test login, all three roles (admin, cashier, staff), expired sessions, password changes and disabled users. Test all CRUD screens, logo uploads, inclusive/exclusive tax, discounts, mixed quantities, invalid data, duplicate checkout submission, reprints and CSV export. Run the dependency security audit in the target installation.

Check real receipts on the intended 58/80 mm printer; select matching paper size, correct margins and no browser headers/footers. Scan every intended paper format on the actual QR reader under normal light. The HTML preview does not validate printed QR quality.

Confirm business rules with the operator: one shared QR per bill, minimum selected validity on mixed bills, all enabled rides share the same points wallet, and top-ups do not shorten validity. Separate guest QRs require separate bills. No package-specific allowlist, unlimited-ride rule or admission gate-count model is implemented.

## Database and concurrency

For multiple production readers, deploy and exercise a transactional MySQL/InnoDB installation with suitable capacity and a proper web server. The source uses transaction boundaries, unique idempotency keys, reader locks and wallet row locks; this is not a substitute for a target-database concurrency test.

Simultaneously scan the last 100 points of one pass at two readers costing 100 each. Only one transaction may debit. Retry an identical request many times and verify one ledger debit. Interleave top-ups, blocking, rate changes and redemption attempts. Test lock timeouts, deadlocks, dropped replies and server restarts.

## Device acceptance

Test generic firmware on the actual board with pulse output disabled first. Validate scanner voltage and data interface, relay polarity, inactive power-up state, pulse duration, UART behaviour and firmware compilation. Use an isolated bench input for output testing, not a live ride.

Cut Wi-Fi before a request, after a server debit, before a pulse, during a pulse and after a pulse but before its acknowledgement. Reboot the ESP in each state. Ensure request IDs and uncertain records are retained, that retries do not double-debit and that possibly-delivered pulses are not blindly repeated. Verify operator recovery and point refunds. Reconcile physical machine counters with logs.

Only a qualified technician should connect the verified credit interface to the ride's manufacturer-approved input. Ride safety and guest eligibility remain independent.

## Deployment, access and data protection

Serve only `public/` over HTTPS. Keep `.env`, the SQLite file, source, logs, firmware tokens, backups and preview/test files private. Do not expose the project root or a development server to the internet. Configure `APP_ENV=production`, `APP_DEBUG=false`, the correct URL, secure cookies, restricted reader networking, unique staff accounts and strong passwords. Never share a device token between unrelated readers.

The token is shown only once in the UI but necessarily exists in the device configuration/flash. Protect physical devices and firmware files. QR images are bearer passes; a photograph or copy can spend points until blocked. There is no guest PIN, biometric identity check or anti-sharing guarantee.

Avoid storing unnecessary guest information. Limit who can export sales and view wallets. Cashiers in this version can see all park sales/pass data. Establish retention, backup access and deletion policies appropriate to the deployment; this source is not a privacy-compliance certification.

Set up transactionally consistent backups, an offline copy and a restore test. Do not just copy the main SQLite file while it is actively using WAL; use a supported consistent backup method or stop all writes and checkpoint cleanly. For MySQL use a consistent snapshot method appropriate to the server. Include the correct APP_KEY, uploaded logos and configuration in secure disaster recovery records. Verify that restored balances and point-entry history agree.

## Accounting sign-off

Review actual prices and the tax/invoice format with an accountant. All seeded amounts are illustrative and tax defaults to zero. A single generic tax total is implemented, not complete GST logic or accounting. Payment-method selection is a record, not payment verification. Cash/UPI/card refunds and daily bank/cash reconciliation are outside the automatic payment flow.

No automated monitoring, backup schedule, payment gateway, SMS/WhatsApp sending or fiscal integration has been activated.
