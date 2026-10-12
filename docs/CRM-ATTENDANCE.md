# Site attendance module — behaviour and integration notes

This document describes implemented source behaviour, not a claim of completed Laravel or device testing. The module is for a park operator's own staff attendance. It is not multi-tenant sales CRM, payroll, leave or a time-clock certification.

## Routes and authorization

`/login` remains the shared form. A successful **staff** login always redirects to `/staff`, discarding a previously intended billing URL. Admin/cashier logins preserve the original dashboard flow. The `/` route redirects staff to the staff portal.

The `/staff` routes require authenticated, active users and password-session-stamp validity. They expose only the current user's home, history, detail, correction requests and password/profile pages. A staff role cannot reach `/pos`, `/sales`, `/passes` or admin routes. Admin and cashier roles may use their own attendance portal when enabled. Admins alone have `/crm`, correction review, exports, site configuration and staff administration. Cashiers still see all existing park bills/passes; this is not site-level billing partitioning.

No public employee signup, impersonation button or staff-controlled role/site change is included. Use separate accounts; no shared login or device token is an attendance identity guarantee.

## Tables and timestamps

- `work_sites`: site name/address/timezone, centre coordinates, radius, maximum allowed reported accuracy, geofence and active flags.
- `users`: additive employee code, phone, department/designation, work-site assignment and attendance-enabled fields.
- `attendance_sessions`: effective start/end, work date, original site name/timezone/policy snapshot, source and review flag. Nullable unique `open_user_id` prevents multiple open sessions per employee at the database level.
- `attendance_punches`: raw browser in/out events, server UTC event time, reported coordinates/accuracy, calculated distance/status, unique UUID request, fingerprint, IP, user agent and optional note. One in and one out per session. No edit/delete route is exposed.
- `attendance_corrections`: pending/approved/rejected workflow, requester, reviewer, reason, effective before/after snapshot and review time. Existing raw punches are not overwritten by review.

Business event timestamps (`started_at`, `ended_at`, `punched_at`, `reviewed_at`) are stored as **UTC DATETIME** through an explicit UTC cast. The server clock is authoritative. The effective start's site-local date is the work date; an overnight shift remains assigned to its punch-in day, rather than being divided at midnight. Views convert to the session's stored timezone. Site changes do not rewrite old sessions. Multiple non-overlapping sessions per day are supported; breaks/rosters/payroll are not calculated.

## Punch HTTP contract

`POST /staff/punch` uses the authenticated browser session, same-origin JSON, CSRF token and attendance rate limiter (20 requests/minute per user+IP). It is separate from machine-token APIs and cannot be called using an ESP bearer token.

```json
{
  "action": "in",
  "request_id": "ae5d7c54-2eb3-49d5-a876-7f1a0f067903",
  "latitude": 10.0001,
  "longitude": 76.3001,
  "accuracy_m": 12,
  "note": "Optional employee note"
}
```

These coordinates are **illustrative test values**, not the park's location. For `action: out`, include the specific `session_id`. The server obtains employee identity, site assignment and event time from trusted session/application state, not request-supplied user IDs or times.

A successful response includes `ok`, `replay`, `punch_id`, `session_id`, `action`, UTC `recorded_at`, `needs_review`, `message` and staff-home `redirect`. Validation errors return 422; conflicting UUID reuse returns 409; an invalid/other-user session returns 404; missing/expired authentication returns 401 JSON (or middleware-standard CSRF 419). The browser renders server error text safely as text.

The application locks the employee row inside a transaction, checks UUID/fingerprint deduplication and open-shift state, evaluates site rules, writes session and raw punch, then logs the audit entry. A UUID replay with the same identity and payload returns the prior result rather than creating another punch. A delayed out retry targets its original session, not a newly opened one. Locks plus uniqueness are design controls, not evidence of passing real MySQL concurrency tests.

## Location policy

Location is requested only when the user taps Check location/Punch. There is no `watchPosition`, continuous tracking, background polling or automatic GPS on login. Check location displays a fix locally but does not POST an attendance record. The next new punch gets its own fresh fix with `maximumAge: 0`, high-accuracy preference and a 20-second wait limit. The browser/OS/location provider may still communicate externally as part of location determination.

Coordinates and reported accuracy are required for both in and out. Punch-in needs attendance enabled, an active assigned site, no open shift, acceptable accuracy and (when enforced) location inside the radius. Record-only mode ignores radius but still requires valid coordinates and acceptable accuracy. The radius compares the unrounded Haversine result; distance display/storage is rounded to metres. This is a centre-distance test, not a survey-accurate boundary or a claim that the entire uncertainty circle is inside the site.

To avoid leaving staff permanently on duty, a punch-out with valid supplied coordinates may be recorded outside the radius or with poor accuracy. It closes the session and marks it for review. Shifts longer than 24 hours are also flagged. A completely missing/denied location cannot create a normal punch; the user submits a correction request instead. Punch-out uses the session's snapshotted rules and is permitted after site reassignment/deactivation or attendance toggle, provided the user still has active login access. A disabled account needs administrator review to resolve an open shift.

Browser coordinates are **not anti-spoof proof or biometric identity**. Device settings, mock location, altered clients or another person using credentials can undermine them. There is no selfie/fingerprint collection or native-device attestation. Select radius/accuracy settings using practical on-site tests and maintain a supervisor review path.

## Network uncertainty

The browser freezes a punch payload and request UUID in memory while submitting it. A lost/5xx/429/timeout response keeps that same payload for retry, without recapturing location. The message warns that the server may already have saved it. An acknowledged validation rejection allows a fresh request. Double-clicks are disabled while pending and after confirmed success.

There is **no persistent/offline queue**. Reloading or closing the tab loses its in-memory pending data. The user must refresh history before trying again; server open-session checks provide another boundary. Real mobile network loss and concurrent tabs must be tested during acceptance. A saved response dispatches a cancelable `parkdesk:attendance-saved` CustomEvent before its default same-origin redirect; a trusted same-origin integration can handle it and refresh the UI itself, but must not silently leave stale state.

## Corrections and reporting

A staff member may request an existing owned session or a fully missed day with a reason. Future dates are rejected. There is at most one application-created pending request per employee/work-date, protected by an employee-row transaction lock. A second issue for another shift on that day can be submitted after the first is reviewed. A recent-session list is shown; older selected owned sessions can be opened from history.

An admin can also create a review request for an existing session. Approval requires a reason, local in/out values, a start date matching the request, positive duration within 36 hours, no future end and no overlap with another session. Open shifts count as ongoing in overlap checks. The employee is locked consistently with punching. Approval updates effective times or creates a `manual_admin` session without fabricated GPS. Rejection leaves attendance unchanged. A resolved request cannot be applied twice. Raw original events remain visible alongside all review history; these are application audit controls, not protection against someone with direct database-write privileges.

The register supports date ranges up to 366 days, site, employee and open/closed/review filters. On-duty count is **currently open shifts for the selected site across all dates**, explicitly not an absence/presence percentage. Completed recorded time in the admin cards excludes open and review-marked sessions. Detail/history retain flags. The CSV includes effective times and raw event location/accuracy/status, source and review flags, not payroll. CSV formula-like strings are prefixed to reduce spreadsheet formula injection.

## Privacy and deployment decisions for the operator

Tell staff what is collected, why, who can see it and how long it is retained before launch. Normal staff see only themselves; admins see records/exports. IP/user-agent detail is administrator-only. Exact coordinates are sensitive: protect exports, backups and admin accounts, agree a retention policy and implement a controlled deletion/retention process appropriate to your deployment. No legal compliance certification or automatic retention/deletion scheduler is supplied.

Coordinates are sent to the park server on punch. An optional Google Maps link sends the coordinates to Google only when followed; no embedded map or reverse-geocoding API/key runs in the attendance pages. The browser's geolocation provider can separately process location data according to its own platform behaviour. The app uses locally packaged fonts fallback, artwork, CSS and JS, and no analytics/tracking SDK.

Use valid trusted HTTPS, secure cookies, server clock synchronization and a web document root restricted to `public/`. The response policy permits same-origin geolocation (`geolocation=(self)`) while leaving camera/microphone disabled. Hosting-level policy must not override it to `geolocation=()`. User/browser/OS permission is still required. This source does not provision SSL, install a native app, configure hosting, schedule backups or add monitoring.
