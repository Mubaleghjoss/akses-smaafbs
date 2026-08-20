# Task 8 report — Hotspot student push shortcut

## RED
- Added `tests/Feature/HotspotStudentPushShortcutTest.php` before Task 8 production code.
- Ran `E:/xampp/php/php.exe -d opcache.enable_cli=0 artisan test tests/Feature/HotspotStudentPushShortcutTest.php`.
- Observed expected RED: `App\Support\StudentSync\StudentSyncScopeToken` did not exist (and the initial page fixture lacked required local tables).

## GREEN / implementation
- Added cache-backed `StudentSyncScopeToken` with normalized positive unique IDs, 15-minute TTL, per-user cache-key binding, and one-time `Cache::pull` consumption.
- `HotspotStudentAccounts::createAccounts()` now returns `student_ids` only after a successful router account creation; failed, skipped, invalid, and connection-failure items do not enter the result.
- `BuatAkunSiswa` carries source IDs into create items, issues the shortcut only when successes exist, and renders the requested preview link. It does not call preview/apply automatically.
- The existing secure Push page consumes `scope_token` once, converts verified IDs into Task 7's signed `scope` contract, and retains all Task 7 authorization/invalid-scope guards. Invalid/reused/wrong-user/expired shortcut tokens produce an invalid scope and cannot widen selection.

## Files
- Modified: `app/Services/HotspotStudentAccounts.php` (Task 8 hunk staged with `git add -p`; unrelated pre-existing `updateOrCreate` hunk remains unstaged)
- Modified: `app/Filament/Pages/BuatAkunSiswa.php`
- Modified: `app/Filament/Resources/DataSiswaResource/Pages/PushDataSiswasToServer.php`
- Modified: `resources/views/filament/pages/buat-akun-siswa.blade.php`
- Added: `app/Support/StudentSync/StudentSyncScopeToken.php`
- Added: `tests/Feature/HotspotStudentPushShortcutTest.php`

## Verification
- `E:/xampp/php/php.exe -d opcache.enable_cli=0 artisan test tests/Feature/HotspotStudentPushShortcutTest.php tests/Feature/StudentServerPushPageTest.php`
  - PASS: 14 tests, 69 assertions.
- PHP syntax check on Task 8 PHP files: PASS.
- `git diff --cached --check`: PASS.
- `E:/xampp/php/php.exe vendor/bin/pint --test`: repository-wide baseline failure across many unrelated pre-existing files, including the protected pre-existing hotspot files. No formatter writes were performed to preserve scope.

## Staging proof / self-review
- Cached paths are only Task 8 files: page, Push page integration, scoped `HotspotStudentAccounts` hunk, new token support, view, and feature test.
- `app/Services/HotspotManager.php` and `app/Services/RouterOS.php` remain unstaged and untouched.
- `HotspotStudentAccounts.php` is `MM`: staged changes are only source-ID/result support; the pre-existing `updateOrCreate` mutation remains unstaged.
- No credentials, raw source IDs, passwords, or tokens are rendered by the shortcut UI; URL contains an opaque, one-time token.

## Commit
- `401e0a0 feat(hotspot): link created students to server push preview`

## Concerns
- Full Pint cannot pass due broad pre-existing repository formatting drift; focused tests, syntax, and diff-whitespace checks passed.
- The wrong-user consume attempt is intentionally non-destructive because the cache key is user-bound; the rightful user can still consume their shortcut.


## Critical review remediation — atomic consume / race/UI safety

### RED
- Added a database-cache concurrency regression that creates an isolated temporary SQLite database-cache store and lock table, starts two independent PHP child processes behind a shared barrier, and requires exactly one `consume()` call to receive `[98]` while the other receives `[]`.
- Before the fix, the real concurrent test failed with both children receiving `[98]`, proving the `Cache::pull()` get-then-forget race on the database cache driver.
- Added rendered Livewire shortcut coverage requiring the opaque URL and label while rejecting rendered password, raw student ID, and raw token text.

### GREEN / implementation
- Replaced `Cache::pull()` with a database-cache-compatible per-token cache lock. The lock uses the SHA-256-derived user-bound key plus a lock suffix, has a 5-second lease and a bounded 2-second wait, and fails closed (`[]`) on lock timeout.
- `get()` and `forget()` now execute only while the successful claimant holds that same lock. Wrong-user requests still use a separate user-bound key and therefore cannot consume the rightful user’s token. The existing 15-minute issue TTL is unchanged.
- The concurrent test provisions and destroys its temporary cache database/barrier; each child has a 10-second process deadline and test assertions require both clean child exits.

### Verification
- `E:/xampp/php/php.exe -d opcache.enable_cli=0 artisan test tests/Feature/HotspotStudentPushShortcutTest.php tests/Feature/StudentServerPushPageTest.php tests/Feature/StudentServerPushClientTest`
  - PASS: **25 tests, 124 assertions**.
- `E:/xampp/php/php.exe vendor/bin/pint --test app/Support/StudentSync/StudentSyncScopeToken.php tests/Feature/HotspotStudentPushShortcutTest.php`: PASS.
- `git diff --check`: PASS.

### Self-review / scope
- Verified the staged commit excludes `HotspotManager.php`, `RouterOS.php`, and the pre-existing unstaged `HotspotStudentAccounts.php` `updateOrCreate` hunk.
- No credentials, production configuration, routers, assessment data, or user storage were used. The concurrency test uses an isolated temporary SQLite cache store and child PHP processes only.
- Bounded lock wait intentionally fails closed under lock contention rather than permitting another preview; a valid request can be retried after the short contention window.

### Commit
- `bace1f837a61425d21cf84142e741219f8017194 fix(hotspot): atomically consume student push shortcuts` (feature/test files only).

## Round 2 — durable atomic shortcut claim

### RED
- The prior cache-lock implementation used a five-second lease around `get()`/`forget()`, so a stalled claimant could outlive the lease and permit a second read.
- Replaced the old startup-only cache race coverage with a two-process SQLite claim test that pauses the first process **after its durable conditional claim**, starts the second claimant, waits six seconds (beyond the old lease), then releases the first. The test requires exactly one `[98]` result.

### GREEN / implementation
- Added `student_sync_scope_tokens` and `StudentSyncScopeTokenRecord`: SHA-256 opaque-token hash, owner user ID, encrypted normalized student IDs, expiry, and retained `consumed_at` audit marker.
- `issue()` now persists the durable record with the existing 15-minute lifetime.
- `consume()` runs on the token database connection in a transaction, locks the owner/hash row, and performs a `consumed_at IS NULL AND expires_at > now()` conditional update before returning IDs. A wrong owner cannot locate or consume the owner record. Malformed, expired, reused, and database-contention paths fail closed.
- The conditional consumed marker is retained for reconciliation; no raw student IDs appear in URLs, UI, or logs.

### Verification
- `E:/xampp/php/php.exe -d opcache.enable_cli=0 artisan test tests/Feature/HotspotStudentPushShortcutTest.php tests/Feature/StudentServerPushPageTest.php tests/Feature/StudentServerPushClientTest.php`
  - PASS: **25 tests, 124 assertions**.
- Focused Pint, PHP syntax checks, and `git diff --check`: PASS.

### Self-review / scope
- The test creates and removes an isolated temporary SQLite file and schema; it does not invoke unscoped pending migrations.
- `HotspotManager.php`, `RouterOS.php`, and the existing unstaged `HotspotStudentAccounts.php` `updateOrCreate` hunk remain untouched and unstaged.

### Commit
- `fix(hotspot): atomically claim student push shortcuts` (recorded in this commit)
