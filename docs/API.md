# ESP machine API — version 2

All endpoints require the unique reader token:

```http
Authorization: Bearer YOUR_READER_TOKEN
Accept: application/json
Content-Type: application/json
```

Do not put a token in a URL, log it or expose it to guests. The token resolves the reader and its assigned ride. A device cannot submit a different charge or ride to change its debit.

## Configuration and heartbeat

`GET /api/machine/config`

Returns `api_version=2`, `machine_id`, `serial`, `enabled`, the assigned ride (`id`, `name`, `code`, `points_per_play`), top-level `points_per_play`, `pulse_ms`, `cooldown_seconds`, and whether a play is pending. The example firmware calls it every 30 seconds. Last-seen is a heartbeat indicator, not a hardware-health certificate. All authenticated machine responses use `Cache-Control: private, no-store` and `Pragma: no-cache`.

API v2 adds machine identity to redemption/status/ack responses so the camera firmware can verify its expected serial/ID. Original API fields and routes remain. Limits are 120 requests/minute per reader token and 3,600/minute per shared source IP, allowing 25 readers behind one LAN router.

## Balance check, without a debit

`POST /api/machine/check`

```json
{"credential":"QR-0123456789ABCDEF0123456789ABCDEF"}
```

Returns `ok`, `usable`, `balance`, `status` and `expires_at`. Prefer POST to avoid putting the bearer QR into ordinary URL access logs. The legacy GET form also exists. Unknown passes return 404; disabled machines cannot check balances.

## Authorize and debit

`POST /api/machine/redeem`

```json
{
  "credential":"QR-0123456789ABCDEF0123456789ABCDEF",
  "source":"qr",
  "request_id":"esp-example-9fa212bd3a0842d1"
}
```

QR credentials are exactly `QR-` followed by 32 uppercase hex characters. Request IDs must be 8–80 letters, digits, hyphens or underscores. Persist a globally fresh request ID per intended play and reuse it across network retries and restarts. Never mint a new ID merely because an HTTP reply was lost.

Successful response example:

```json
{
  "ok":true,
  "api_version":2,
  "machine_id":3,
  "serial":"ESP-RIDE-01",
  "ride":{"id":1,"name":"Example ride","code":"RIDE-01"},
  "success":true,
  "terminal":true,
  "status":"approved",
  "code":"APPROVED",
  "transaction_id":12,
  "request_id":"esp-example-9fa212bd3a0842d1",
  "replay":false,
  "approved":true,
  "allow_pulse":true,
  "points_deducted":100,
  "balance":500,
  "balance_after":500,
  "pulse_ms":250,
  "approval_ttl_seconds":30,
  "message":"Points deducted. Send one pulse and acknowledge."
}
```

`balance_after` is the balance snapshot at this transaction, including on replay. Use the balance-check endpoint for the latest balance after other rides or top-ups.

**Do not pulse solely because `ok` is true.** A completed replay can have `ok=true` with `allow_pulse=false`. Check expected machine serial/ID, request-ID equality, status, allow_pulse, approval age, safe pulse bounds and the persisted local delivery state. Persist an armed marker before a physical output. No HTTP response can prove a ride actually operated.

Duplicate IDs with the same credential replay their prior result. A duplicate ID with a different credential returns `REQUEST_CONFLICT` (409). Retain the original ID and stop for investigation; do not blindly retry under a new one.

Domain denials are recorded and normally return 422 with status `denied`: `INSUFFICIENT_POINTS`, `INVALID_QR`, `PASS_EXPIRED`, `PASS_BLOCKED`, `MACHINE_DISABLED`, `RIDE_DISABLED`, `MACHINE_PENDING`, `COOLDOWN`. Retrying the same denied request will replay that denial even if a recharge happens later. A genuinely new guest attempt needs a new ID after the denial has been confirmed.

Validation failures can also return Laravel's ordinary 422 error shape, without a transaction. Authentication failure is 401. Rate limits return 429. Transport failures, malformed replies, 5xx responses and uncertain outcomes must never trigger a pulse or an unqualified new-ID retry.

## Lookup after a lost reply

`GET /api/machine/transactions/{request_id}`

Only the authenticated machine's requests can be looked up. `NOT_FOUND` is 404 with `terminal=false`: an earlier request could still be in flight. A fresh unrelated scan must not be used as recovery.

An unacknowledged approval becomes `uncertain` when consulted after its 30-second approval window or after the reader/ride is disabled. It is not automatically refunded. One unresolved approved/uncertain transaction blocks new plays on that reader.

## Pulse acknowledgement

`POST /api/machine/transactions/{request_id}/ack`

```json
{"outcome":"pulsed"}
```

Use `pulsed` only after the firmware has actually sent its credit pulse. The server changes the record to `completed`; repeated acknowledgements are safe and do not change the point balance. A device acknowledgement does not confirm that a motor, gate or ride completed a cycle.

After a reboot in a possibly-pulsed state:

```json
{"outcome":"uncertain"}
```

Do not pulse again. Keep the request pending for staff review. An admin resolves it only with the reader disabled, physically powered off and the approval window elapsed. A points refund is an auditable new ledger entry, not deletion of the debit.

## Compatibility aliases

`POST /api/qr/redeem` maps to the same redeem action and accepts `qr_code` as an alias for `credential`.
`POST /api/qr/check` maps to the same check action.

These aliases do not make old firmware automatically compatible. This app still requires its own reader token, 35-character QR credential, fresh/persisted request IDs and acknowledgement protocol. No NFC or terminal WebSocket SDK integration is bundled.

## Manual test (isolated test database only)

```bash
curl -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept: application/json" \
  http://SERVER_IP:8000/api/machine/config

curl -X POST http://SERVER_IP:8000/api/machine/redeem \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"credential":"ACTUAL_QR_FROM_A_TEST_BILL","request_id":"manual-test-00000001","source":"qr"}'
```

The second request deducts real database points if accepted. Do not run against a live guest wallet. Without an actual pulse, do not falsely acknowledge `pulsed`; use a dedicated test environment or mark uncertain and follow the admin resolution workflow.
