# Attendance go-live gate — not completed in this source release

Complete this in an isolated staging installation, then conduct controlled site acceptance. Keep written results and resolve failures before attendance or pay decisions. Also complete `GO-LIVE.md` for billing/QR/ESP.

## Install and access

Run the supplied Laravel suite with dev dependencies and an in-memory test database. Compile/render every Blade screen through Laravel; static HTML previews are not Blade tests. Run dependency audit in the target installation, retain reviewed `composer.lock`, configure trusted HTTPS, secure cookies, web root `public/`, trusted proxies and server time. Confirm `.env`, SQLite file, storage/logs, backups, previews and test files are not public.

Exercise admin, cashier and staff role logins. Staff must always land in the mobile portal; direct `/pos`, sales/pass URLs, `/crm`, `/users`, `/sites`, tokens, audit and other employee detail URLs must be rejected. Cashier keeps existing billing but not all-staff attendance. Test disabled accounts, forgotten-password administration, changed passwords/session revocation, expired login/CSRF and logout. Do not ship shared accounts or sample passwords.

## Real phones and site boundaries

Use the intended Android Chrome and iPhone Safari devices on the actual HTTPS site. Check initial permission prompt, denied permission recovery, OS location disabled, slow location, timeout, poor indoor accuracy and narrow 360px screens. Verify both portrait/landscape, long names/site names, touch targets, bottom tabs, keyboard interaction and scrolling. No location request should run on login/page load.

Confirm actual centre coordinates/timezone with the operator. Test inside, near the boundary, outside, inaccurate and record-only mode fixes. Establish practical radius and accuracy limits without assuming consumer GPS is precise or tamper-proof. Check that invalid/no-fix punches are not stored, outside/poor-accuracy in-punches fail, outside/poor-accuracy out-punches close and visibly flag review, and the displayed map link opens the intended raw location only on click.

## Persistence, concurrency and recovery

Punch in, reload, use another browser/tab, and verify the same persisted open shift. Punch out, reload, and compare server/raw/effective times and duration. Try duplicate double clicks and repeated UUID requests; confirm one raw event. Reuse a UUID with modified data; require conflict. Use two connections to race two punch-ins and two punch-outs on the intended MySQL/InnoDB database. Confirm unique open session and one event per direction. Test rollback on write failure, connection loss, deadlock/retry and delayed old out-request after a new shift.

Drop the response after the server commits. UI must warn of uncertainty and retry the exact same UUID/payload while the tab remains open. Reload after uncertainty; verify History before retry. Test offline, reconnect, CSRF expiration and reverse-proxy timeout; no hidden offline/background queue may be presumed. Target-load tests must include simultaneous billing, QR debit and staff punches.

## Corrections and reporting

Test overnight shifts, a site-timezone date boundary, a shifted system/browser clock, several sessions in a day, an open shift over 24 hours, site reassignment/deactivation during a shift, attendance disabled mid-shift and a fully disabled account. Raw event UTC time must be server-authoritative; old site policy/timezone must remain attached.

Request a missing punch and a completely missed day. Verify only the employee owns the selected session, no future/duplicate pending date, and no raw event rewrite. Approve with reason and local times, reject another request, attempt future/end-before-start/overlapping/over-36h times and double approval. Confirm manual attendance has no invented GPS and before/after/reviewer data is retained. Test an admin-reviewed open session racing with an out-punch.

Compare register filters, page totals, own history, current-on-duty label, review flags and CSV against database fixtures. Export accented/Malayalam names, long names, commas, quotes, line breaks and formula-leading values. Open/review sessions must not inflate admin completed-time cards. These totals are not payroll, scheduled presence, leave or overtime.

## Operator sign-off

Document notice to staff, location purpose, permitted viewers, retention period, export handling and correction escalation. Test backup/restore including attendance and original billing balances. Limit administrative access and audit direct database access. No legal compliance, fake-location prevention, biometric identity, background tracking, monitoring, payroll or scheduled backup claim is made by this build.
