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

## Round 2 regression remediation — deterministic contender coordination

### RED
- The re-review correctly identified that the prior race test had only the first post-claim marker: it had no second-child acknowledgement before `consume()`, waited indefinitely for the first marker, and did not stop started children before deleting its temporary SQLite/barrier files.
- A focused run after adding the contender-block assertion demonstrated that SQLite may fail the second contender closed with a lock/query failure before the first holder is released; the stable regression therefore proves contention via the second child’s pre-consume acknowledgement and final `[]` result rather than assuming it remains alive for the full hold.

### GREEN / test-only change
- Added a separate `contender-ready` marker emitted by the second child immediately before its `consume()` call. The parent bounded-waits for the first durable-claim marker, then for that contender marker, holds the first child for seven seconds (past the former five-second lease), and only then releases it.
- Added bounded marker waits that fail early if a child exits, plus `finally` cleanup that releases/stops every started child before removing the markers and isolated SQLite file.
- The test still requires clean exits and exactly one `[98]` and one `[]`; no production service behavior was changed.

### Verification
- `E:/xampp/php/php.exe -d opcache.enable_cli=0 artisan test tests/Feature/HotspotStudentPushShortcutTest.php tests/Feature/StudentServerPushPageTest.php tests/Feature/StudentServerPushClientTest.php`: PASS — 25 tests, 126 assertions.
- `E:/xampp/php/php.exe vendor/bin/pint --test tests/Feature/HotspotStudentPushShortcutTest.php`: PASS.
- PHP syntax check and `git diff --check`: PASS.

### Scope / self-review
- Only `tests/Feature/HotspotStudentPushShortcutTest.php` and this report are changed for this remediation.
- `HotspotManager.php`, `RouterOS.php`, and the pre-existing unstaged `HotspotStudentAccounts.php` `updateOrCreate` hunk remain untouched and unstaged.

### Commit
- Recorded in `test(hotspot): coordinate durable shortcut claim race`.

### Final self-review
- Cached/committed paths were limited to this regression test and report; protected production WIP was neither staged nor included.

## Round 3 — inner consume contention proof

### RED
- Replaced the second child’s pre-call `contender-ready` signal with a marker that requires a `consume()`-internal hook. Before the hook existed, the race regression failed at the first durable-claim barrier because the previous constructor-only after-claim seam had been removed from the child harness; there was no internal marker path for the second child.
- Added an explicit regression that configures a hook while the app environment is non-testing and requires the callback never to run.

### GREEN / test-only seam
- `StudentSyncScopeToken::consume()` now invokes a configured callback only when `app()->environment('testing')`, immediately before the transaction/database work, and after the durable conditional claim. It has no public constructor parameter or production-environment behavior.
- The first independent child signals after the durable conditional claim and holds its transaction. The second child signals from the new `before_database` hook, then blocks until the parent has held the first for seven seconds. The parent releases the second toward the still-held transaction before releasing the first.
- Both marker waits are bounded and assert the relevant child remains running. `finally` releases and stops both children before cleaning up every barrier marker and the isolated SQLite file. The exact-one `[98]`/`[]` assertion remains; SQLite fail-closed `[]` is now accompanied by proof that the second child entered `consume()` during the held window.

### Verification
- `E:/xampp/php/php.exe -d opcache.enable_cli=0 artisan test tests/Feature/HotspotStudentPushShortcutTest.php tests/Feature/StudentServerPushPageTest.php tests/Feature/StudentServerPushClientTest.php`: PASS — **26 tests, 128 assertions**.
- `E:/xampp/php/php.exe vendor/bin/pint --test app/Support/StudentSync/StudentSyncScopeToken.php tests/Feature/HotspotStudentPushShortcutTest.php`: PASS after focused formatting.
- PHP syntax checks for both changed PHP files and `git diff --check`: PASS.

### Scope / self-review
- Only `app/Support/StudentSync/StudentSyncScopeToken.php`, `tests/Feature/HotspotStudentPushShortcutTest.php`, and this report are staged for this remediation.
- `HotspotManager.php`, `RouterOS.php`, and the pre-existing unstaged `HotspotStudentAccounts.php` `updateOrCreate` hunk remain untouched and unstaged.
- The hook is configuration-only, is only callable under the `testing` environment gate, accepts only a stage label, and is regression-tested as ignored outside that environment. No production/router/network/user or assessment storage was accessed.

### Commit
- Created as `bfa895743ad52da48fab028ebda1f4a03c343150 test(hotspot): prove durable shortcut claim contention`; amended immediately to record this evidence, with final SHA reported in the delivery summary.
