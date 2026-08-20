# Task 4 Report — Preview receiver and encrypted preview snapshot

## Status

Complete.

## Commit

- SHA: `015e1a61613379d850dd4296e92b34a554572239`
- Message: `feat(student-sync): preview student updates on server`

## RED evidence

Command:

```text
E:/xampp/php/php.exe -d opcache.enable_cli=0 vendor/bin/phpunit tests/Feature/StudentSyncPreviewApiTest.php
```

Initial result before production implementation:

```text
FAILURES!
Tests: 5, Assertions: 5, Failures: 5.
```

All five tests failed with the expected `503` preview-placeholder response instead of the required `200`/`422` behavior.

A subsequent validation slice exposed `302` redirect responses for invalid API payloads; `StudentSyncPreviewRequest::failedValidation()` was then added to return deterministic JSON `422` responses.

## GREEN evidence

Fresh post-commit direct PHPUnit result:

```text
..... 5 / 5 (100%)
OK (5 tests, 84 assertions)
```

Additional impacted suite:

```text
OK (31 tests, 157 assertions)
```

This covered the Task 4 feature test plus matcher, merge-policy, and foundation tests.

Formatting and boot checks:

- Pint `--test`: pass.
- `git diff --cached --check`: pass before commit.
- `artisan route:list --path=api/internal/student-sync`: booted with preview controller and apply placeholder routes.
- PHP syntax checks: pass for all new PHP files.

## Files committed

- `app/Support/StudentSync/StudentSyncPreviewService.php`
- `app/Http/Controllers/Api/StudentSyncPreviewController.php`
- `app/Http/Requests/StudentSyncPreviewRequest.php`
- `routes/api.php`
- `tests/Feature/StudentSyncPreviewApiTest.php`

## Implementation summary

- Added configured batch and strict item-shape validation.
- Added deterministic recursive-key canonicalization and server-side SHA-256 payload verification.
- Reused `StudentSyncMatcher` and `StudentSyncMergePolicy` for identity resolution and patch computation.
- Added safe update/unchanged/conflict/not_found summaries without before/after values.
- Stored the complete apply snapshot through the existing encrypted model cast with configured expiry.
- Created a completed `preview` audit run transactionally with the snapshot.
- Preserved `data_siswa` without mutation.
- Replaced only the preview placeholder route; apply remains a `503` placeholder.
- Preserved and did not stage unrelated hotspot baseline modifications.

## Self-review

- Confirmed response item keys are limited to `status`, `source_id`, `target_id`, `changed_fields`, and `reason`.
- Confirmed unknown and denied fields cannot enter the computed patch.
- Confirmed raw stored ciphertext contains no tested personal/identity values while the model decrypts the snapshot correctly.
- Confirmed checksum is stable across associative key ordering while preserving student list order.
- Confirmed preview and audit creation share one database transaction.
- Confirmed commit contains exactly the five planned Task 4 files.

## Concerns

- The older Task 3 signature feature test still encodes the intentionally replaced preview placeholder's `503` response for valid requests. It was not modified because Task 4 explicitly limits the commit to its planned files and uses the new Task 4 direct test as its acceptance command. Security middleware itself is exercised by signed Task 4 requests.
- Unrelated pre-existing modifications remain unstaged in `HotspotManager.php`, `HotspotStudentAccounts.php`, and `RouterOS.php`.

## Important findings fix round 1 (2026-08-20)

### RED evidence

The direct cumulative command was run after adding the focused request-shape regressions and migrating the superseded Task 3 success-path expectations:

```text
E:/xampp/php/php.exe -d opcache.enable_cli=0 vendor/bin/phpunit tests/Feature/StudentSyncSignatureTest.php tests/Feature/StudentSyncPreviewApiTest.php
```

It failed with `19 tests, 126 assertions, 6 failures`. Two expected failures proved that valid requests with an unknown root key or `identity.id` were still accepted with `200`. The four additional failures exposed the Task 3 fixture's missing `data_siswa` table after its valid signed requests began reaching the implemented preview receiver; the fixture was updated accordingly.

### GREEN evidence

After adding the exact root and identity allowlists and completing the Task 3 fixture migration, the same direct command passed:

```text
OK (19 tests, 165 assertions)
```

### Fix summary and self-review

- Root request keys are limited to exactly `payload_checksum` and `students`; unknown root keys return JSON `422` and create no preview or audit run.
- Identity keys are limited to matcher-supported `nipd`, `nisn`, `billing_code`, `nama`, and `tanggal_lahir`; client-supplied `identity.id` can no longer override `source_id`.
- Signature, replay, future-timestamp replay, and rate-limit success paths now use a deterministic valid checksummed preview payload and assert the current `200` preview response while preserving their middleware intent.
- The signature test owns a minimal `data_siswa` fixture so successful receiver execution is deterministic and isolated.
- The Minor canonicalization coverage observation remains intentionally deferred.
- Baseline hotspot modifications remain unstaged and excluded from this fix.
