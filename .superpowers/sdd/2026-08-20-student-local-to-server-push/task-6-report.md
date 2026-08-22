# Task 6 Report — Local Payload Builder and Signed HTTP Client

## Status

Implemented using RED → GREEN → REFACTOR. No production, network, `.env`, assessment, user-storage, or hotspot baseline changes were made.

## Files created

- `app/Support/StudentSync/StudentServerPushPayloadBuilder.php`
- `app/Support/StudentSync/StudentServerPushClient.php`
- `tests/Feature/StudentServerPushClientTest.php`

## TDD evidence

### RED

1. The initial builder tracer failed as expected because `StudentServerPushPayloadBuilder` did not exist: `Tests: 1 failed (0 assertions)`.
2. The client signer/HTTPS tests then failed as expected because `StudentServerPushClient` did not exist: `Tests: 2 failed, 1 passed (5 assertions)`.
3. The connection-retry tracer initially exposed test-fake sequencing incompatibility; it was replaced with a deterministic local fake callback. The failing non-2xx case then showed that a retry fake's exhausted response was masking the separate assertion, so the safe non-2xx check was split into its own tracer.

### GREEN

Mandated direct command:

```text
E:/xampp/php/php.exe -d opcache.enable_cli=0 artisan test tests/Feature/StudentServerPushClientTest.php
```

Result: exit 0, `Tests: 5 passed (16 assertions)`.

Impacted sync suites:

```text
E:/xampp/php/php.exe -d opcache.enable_cli=0 artisan test tests/Feature/StudentServerPushClientTest.php tests/Feature/StudentSyncSignatureTest.php tests/Feature/StudentSyncPreviewApiTest.php tests/Feature/StudentSyncApplyApiTest.php tests/Feature/StudentSyncMatcherTest.php tests/Unit/StudentSyncMergePolicyTest.php
```

Result: exit 0, `Tests: 62 passed (351 assertions)`.

Additional verification:

- Pint: pass.
- PHP syntax checks: pass for the two support classes and feature test.
- `git diff --check`: pass.

## Implementation

- Builder reads the live local `data_siswa` schema, subtracts the configured denylist and ID, selects only `status = aktif`, and optionally restricts scope to requested IDs.
- It omits null, blank, and whitespace values; emits available identity evidence plus `data_siswa` origin/context; and orders records by ID.
- Source and payload checksums use SHA-256 over recursively key-sorted JSON, matching the existing receiver canonical contract.
- Client uses the existing `StudentSyncRequestSigner` against the receiver's exact routes and raw JSON bodies.
- It uses Laravel `Http` with timeout, JSON accept header, signed headers, and safe generic failures. Preview retries only `ConnectionException` requests; apply accepts the supplied idempotency key and uses that same key for its request/retry behavior.
- Disabled client configuration and insecure production HTTP URLs fail before a request. Non-2xx and malformed response payloads do not disclose remote body data or secrets.

## Self-review

- Verified exact method/path/body/signature contract using `Http::fake()` only; no external network request was performed.
- Verified active-only and optional-ID selection, denied/system field omission, blank omission, identity/context inclusion, deterministic checksums, HTTPS protection, retry, and generic transport errors.
- Verified staged candidates are only the three planned Task 6 files. The pre-existing hotspot modifications remain unstaged and unchanged.
- Confirmed no secrets are emitted by client exceptions or assertions.

## Concerns

- The generic client exception intentionally does not preserve remote HTTP diagnostic content. Operators will need separate controlled observability if status-level troubleshooting is required.
- Builder checksums serialize raw model attributes after blank filtering; date casts are not applied because it uses attributes aligned with the selected database schema/receiver scalar contract.

## Commit

- SHA: `75e0406c68581a06551c7a7404eac7c550ac43f8`
- Message: `feat(student-sync): build and send signed student payloads`
- Commit contains exactly the three planned Task 6 implementation/test files.

## Review Coverage Follow-up — 2026-08-20

### Scope

Added focused regressions only in `tests/Feature/StudentServerPushClientTest.php`; no production behavior was changed. The three pre-existing hotspot files remain unstaged and were restored from the shared original worktree after an accidental formatter pass touched their formatting.

### RED → GREEN evidence

- Builder edge regression initially failed at the false-value assertion because SQLite returns a raw boolean column as `0`; the test was corrected to assert the receiver-facing raw scalar `0` is retained (not omitted). It then passed: `1 passed (19 assertions)`.
- Disabled and missing/short credential regressions passed against existing fail-before-send behavior: `2 passed (8 assertions)`.
- Apply retry/idempotency regression passed against existing retry behavior: `1 passed (3 assertions)`.

### Verification

```text
E:/xampp/php/php.exe -d opcache.enable_cli=0 artisan test tests/Feature/StudentServerPushClientTest.php
```

Result: exit 0, `Tests: 9 passed (46 assertions)`.

```text
E:/xampp/php/php.exe -d opcache.enable_cli=0 artisan test tests/Feature/StudentServerPushClientTest.php tests/Feature/StudentSyncSignatureTest.php tests/Feature/StudentSyncPreviewApiTest.php tests/Feature/StudentSyncApplyApiTest.php tests/Feature/StudentSyncMatcherTest.php tests/Unit/StudentSyncMergePolicyTest.php
```

Result: exit 0, `Tests: 66 passed (381 assertions)`.

`git diff --check` passed. All transport tests use `Http::fake()`; no network request was made.

### Self-review

- The builder regression proves zero-like scalar values remain emitted, raw `tanggal_lahir` remains a date string, every configured denylist field is absent, and the dynamically added unexpected source schema column is also absent.
- The client regressions prove disabled, missing credential, and short-secret paths issue no requests and safe errors do not disclose the supplied secret.
- The apply retry regression records both fake requests and proves the caller-supplied idempotency key is identical on each attempt.
- No `.env`, production/network, assessment, user-storage, or Task 6 production file was modified.

### Concerns

- The deferred minor client-side payload/batch validation remains intentionally unaddressed; remote validation remains the current contract.
- The source test database represents `boolean false` as raw `0`, which is deliberately asserted as retained because the builder sends raw schema attributes.

### Follow-up Commit

- Message: `test(student-sync): cover safe payload and retry edges`
