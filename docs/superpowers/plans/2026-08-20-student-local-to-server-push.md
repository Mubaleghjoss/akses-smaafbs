# Student Local-to-Server Push Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Menambahkan preview dan push aman seluruh field bernilai siswa aktif dari Laravel lokal ke `app.smaafbs.sch.id`, tanpa menghapus siswa server, disertai backup, audit, idempotency, permission, dan shortcut dari generator akun hotspot.

**Architecture:** Satu codebase menjalankan dua peran melalui feature flag: lokal sebagai signed HTTPS client dan server sebagai receiver. Business logic dipisah menjadi payload builder, matcher, merge policy, preview/apply service, signature verifier, dan Filament Resource Page. Receiver menyimpan preview terenkripsi, nonce, run audit, serta backup terenkripsi sebelum transaksi apply.

**Tech Stack:** Laravel 12, Filament v5, Livewire, MySQL/MariaDB, Laravel HTTP Client, Spatie Permission, PHPUnit, encrypted casts/Laravel Crypt.

**Spec:** `docs/superpowers/specs/2026-08-20-student-local-to-server-push-design.md`

## Global Constraints

- Hanya siswa lokal `status=aktif` menjadi kandidat.
- Nilai lokal `null`, kosong, atau whitespace tidak menimpa server.
- Tidak ada delete, create siswa server, atau perubahan status pada versi pertama.
- Matching konflik tidak pernah di-apply otomatis.
- Primary key dan field sistem tidak boleh masuk patch.
- Preview tidak mengubah `data_siswa`.
- Apply wajib memakai preview yang belum kedaluwarsa dan checksum yang sama.
- Server membuat backup terenkripsi dan audit sebelum update.
- Request memakai HTTPS, HMAC-SHA256, timestamp, nonce, body hash, dan idempotency key.
- Secret tidak boleh masuk Git, database browser, log, notification, atau exception.
- Pengujian otomatis tidak boleh mengakses server production.
- Working tree saat ini memiliki perubahan hotspot/assessment yang belum di-commit. Jangan memakai `git reset --hard`, jangan stage file di luar daftar task, dan jangan menimpa perubahan yang sudah ada.
- Jalankan PHP CLI dengan `E:/xampp/php/php.exe -d opcache.enable_cli=0`.

---

### Task 1: Configuration, persistence, and permission foundation

**Files:**
- Create: `config/student_sync.php`
- Create: `database/migrations/2026_08_20_120000_create_student_sync_tables.php`
- Create: `app/Models/StudentSyncRun.php`
- Create: `app/Models/StudentSyncPreview.php`
- Create: `app/Models/StudentSyncNonce.php`
- Create: `app/Console/Commands/InstallStudentSyncDefaults.php`
- Test: `tests/Feature/StudentSyncFoundationTest.php`

**Interfaces:**
- Produces: `StudentSyncRun`, `StudentSyncPreview`, `StudentSyncNonce` models.
- Produces: `student-sync:install-defaults` command and permission `data_siswa.push_server`.
- Produces config keys `student_sync.receiver.*`, `student_sync.client.*`, `student_sync.security.*`.

- [ ] **Step 1: Write the failing persistence/config test**

Create `tests/Feature/StudentSyncFoundationTest.php` that boots user/permission tables, runs the new migration, and asserts:

```php
public function test_student_sync_foundation_exposes_safe_defaults_and_tables(): void
{
    $this->runStudentSyncMigration();

    $this->assertFalse(config('student_sync.receiver.enabled'));
    $this->assertFalse(config('student_sync.client.enabled'));
    $this->assertSame(250, config('student_sync.security.max_batch'));
    $this->assertTrue(Schema::hasTable('student_sync_runs'));
    $this->assertTrue(Schema::hasTable('student_sync_previews'));
    $this->assertTrue(Schema::hasTable('student_sync_nonces'));
}
```

Add a command test that runs `student-sync:install-defaults` and asserts permission `data_siswa.push_server` exists and is assigned to `admin`, `super_admin`, and `guru_admin` when those roles exist.

- [ ] **Step 2: Run the test to verify RED**

Run:

```bash
E:/xampp/php/php.exe -d opcache.enable_cli=0 artisan test tests/Feature/StudentSyncFoundationTest.php
```

Expected: FAIL because config, migration, models, and command do not exist.

- [ ] **Step 3: Add exact configuration contract**

Create `config/student_sync.php` with:

```php
<?php

$bool = static fn (string $key, bool $default = false): bool => filter_var(
    env($key, $default),
    FILTER_VALIDATE_BOOL,
);

return [
    'receiver' => [
        'enabled' => $bool('STUDENT_SYNC_RECEIVER_ENABLED', false),
        'client_id' => env('STUDENT_SYNC_RECEIVER_CLIENT_ID'),
        'secret' => env('STUDENT_SYNC_RECEIVER_SECRET'),
    ],
    'client' => [
        'enabled' => $bool('STUDENT_SYNC_CLIENT_ENABLED', false),
        'server_url' => rtrim((string) env('STUDENT_SYNC_SERVER_URL', 'https://app.smaafbs.sch.id'), '/'),
        'client_id' => env('STUDENT_SYNC_CLIENT_ID'),
        'secret' => env('STUDENT_SYNC_SECRET'),
        'timeout' => (int) env('STUDENT_SYNC_TIMEOUT', 60),
    ],
    'security' => [
        'clock_skew_seconds' => (int) env('STUDENT_SYNC_CLOCK_SKEW', 300),
        'preview_ttl_seconds' => (int) env('STUDENT_SYNC_PREVIEW_TTL', 900),
        'max_batch' => (int) env('STUDENT_SYNC_MAX_BATCH', 250),
    ],
    'denied_fields' => [
        'id', 'created_at', 'updated_at', 'status', 'kategori_non_aktif',
        'alasan_non_aktif', 'tanggal_non_aktif', 'spmb_synced_at',
        'spmb_source_updated_at',
    ],
];
```

- [ ] **Step 4: Implement migration and models**

Create tables:

- `student_sync_runs`: UUID primary key, operation, client_id, user_id nullable, status, idempotency_key nullable unique, payload_checksum, counts JSON nullable, field_summary JSON nullable, result_summary JSON nullable, backup_path nullable, error nullable text, started_at, finished_at, timestamps.
- `student_sync_previews`: UUID primary key, client_id, payload_checksum, encrypted_payload longText, expires_at, applied_at nullable, timestamps.
- `student_sync_nonces`: bigint ID, client_id, nonce, expires_at, timestamps, unique `(client_id, nonce)`.

Use casts:

```php
protected function casts(): array
{
    return [
        'counts' => 'array',
        'field_summary' => 'array',
        'result_summary' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];
}
```

`StudentSyncPreview` uses `'encrypted_payload' => 'encrypted:array'` and datetime casts.

- [ ] **Step 5: Implement idempotent permission installer**

`InstallStudentSyncDefaults` uses `Permission::findOrCreate('data_siswa.push_server', 'web')`, assigns it only to existing full-admin roles, and clears `PermissionRegistrar` cache.

- [ ] **Step 6: Verify GREEN and commit**

Run the task test, then:

```bash
E:/xampp/php/php.exe -d opcache.enable_cli=0 artisan test tests/Feature/StudentSyncFoundationTest.php
git add config/student_sync.php database/migrations/2026_08_20_120000_create_student_sync_tables.php app/Models/StudentSyncRun.php app/Models/StudentSyncPreview.php app/Models/StudentSyncNonce.php app/Console/Commands/InstallStudentSyncDefaults.php tests/Feature/StudentSyncFoundationTest.php
git commit -m "feat(student-sync): add secure sync foundation"
```

---

### Task 2: Non-empty merge policy and identity matcher

**Files:**
- Create: `app/Support/StudentSync/StudentSyncMergePolicy.php`
- Create: `app/Support/StudentSync/StudentSyncMatcher.php`
- Create: `app/Support/StudentSync/StudentSyncMatchResult.php`
- Test: `tests/Unit/StudentSyncMergePolicyTest.php`
- Test: `tests/Feature/StudentSyncMatcherTest.php`

**Interfaces:**
- Produces: `StudentSyncMergePolicy::patch(array $source, array $target, array $sharedColumns): array`.
- Produces: `StudentSyncMatcher::match(array $source): StudentSyncMatchResult`.
- `StudentSyncMatchResult` exposes status, matched `DataSiswa|null`, reason, and evidence.

- [ ] **Step 1: Write merge-policy RED tests**

Cover:

```php
public function test_non_empty_local_values_patch_different_server_values(): void
{
    $policy = app(StudentSyncMergePolicy::class);

    $patch = $policy->patch(
        ['nama' => 'Nama Baru', 'nisn' => '001', 'tanggal_lahir' => '', 'status' => 'aktif'],
        ['nama' => 'Nama Lama', 'nisn' => null, 'tanggal_lahir' => '2010-01-01', 'status' => 'aktif'],
        ['nama', 'nisn', 'tanggal_lahir', 'status'],
    );

    $this->assertSame(['nama' => 'Nama Baru', 'nisn' => '001'], $patch);
}
```

Also assert `id`, timestamps, status/nonactive fields, and unknown columns are rejected; equal dates with time suffix do not produce changes; booleans normalize consistently.

- [ ] **Step 2: Run merge test and confirm RED**

```bash
E:/xampp/php/php.exe -d opcache.enable_cli=0 artisan test tests/Unit/StudentSyncMergePolicyTest.php
```

- [ ] **Step 3: Implement minimal merge policy**

The policy must derive allowed columns from the supplied shared-column list minus `config('student_sync.denied_fields')`, normalize dates to `Y-m-d`, trim strings, preserve numeric zero, and only return differing non-empty local values.

- [ ] **Step 4: Verify merge GREEN**

Run the merge test and confirm all cases pass.

- [ ] **Step 5: Write matcher RED tests**

Use an SQLite `data_siswa` fixture and test:

- same ID plus matching NIPD returns `matched`;
- different ID but unique NIPD returns `matched`;
- unique NISN returns `matched`;
- normalized name + date returns `matched`;
- two candidates return `conflict`;
- same ID with contradictory strong identifiers returns `conflict`;
- no candidate returns `not_found`.

- [ ] **Step 6: Implement matcher and result DTO**

Strong evidence is NIPD, NISN, or billing code. ID matching requires at least one non-conflicting evidence value. Name+DOB is fallback only and must be unique.

- [ ] **Step 7: Run both test files and commit**

```bash
E:/xampp/php/php.exe -d opcache.enable_cli=0 artisan test tests/Unit/StudentSyncMergePolicyTest.php tests/Feature/StudentSyncMatcherTest.php
git add app/Support/StudentSync/StudentSyncMergePolicy.php app/Support/StudentSync/StudentSyncMatcher.php app/Support/StudentSync/StudentSyncMatchResult.php tests/Unit/StudentSyncMergePolicyTest.php tests/Feature/StudentSyncMatcherTest.php
git commit -m "feat(student-sync): match and merge student records safely"
```

---

### Task 3: HMAC signature, nonce replay protection, and receiver routes

**Files:**
- Create: `app/Support/StudentSync/StudentSyncRequestSigner.php`
- Create: `app/Http/Middleware/VerifyStudentSyncSignature.php`
- Modify: `bootstrap/app.php:32-35`
- Modify: `routes/api.php`
- Modify: `app/Providers/AppServiceProvider.php` or existing rate-limiter registration location
- Test: `tests/Feature/StudentSyncSignatureTest.php`

**Interfaces:**
- Produces: `StudentSyncRequestSigner::headers(string $method, string $path, string $body, string $idempotencyKey): array`.
- Produces middleware alias `student.sync.signature`.
- Produces named API routes `api.internal.student-sync.preview` and `api.internal.student-sync.apply` pointing initially to stub controllers created in Task 4/5; route registration can reference controller classes added in the same task with 503 placeholder responses until Task 4.

- [ ] **Step 1: Write RED tests for canonical signing**

Assert valid signature passes; altered body, wrong path, wrong secret, expired timestamp, repeated nonce, short secret, and disabled receiver are rejected. Assert stored nonce contains no secret and has expiry.

Canonical payload:

```text
METHOD\n/path\ntimestamp\nnonce\nidempotency-key\nsha256-body
```

Headers:

```text
X-Student-Sync-Client
X-Student-Sync-Timestamp
X-Student-Sync-Nonce
X-Student-Sync-Idempotency-Key
X-Student-Sync-Body-SHA256
X-Student-Sync-Signature
```

- [ ] **Step 2: Run test and confirm RED**

```bash
E:/xampp/php/php.exe -d opcache.enable_cli=0 artisan test tests/Feature/StudentSyncSignatureTest.php
```

- [ ] **Step 3: Implement signer and middleware**

Use `hash_equals`, `hash_hmac('sha256', ...)`, raw request content, `now()->timestamp`, and `StudentSyncNonce::create()` inside a unique-key guarded operation. Require secrets of at least 32 characters. Prune expired nonces opportunistically.

- [ ] **Step 4: Register middleware and rate limiter**

Add alias in `bootstrap/app.php`:

```php
'student.sync.signature' => VerifyStudentSyncSignature::class,
```

Register limiter `student_sync_receiver` at 20 requests/minute keyed by client ID and IP. Add API route group with `throttle:student_sync_receiver` and signature middleware.

- [ ] **Step 5: Verify GREEN and commit**

```bash
E:/xampp/php/php.exe -d opcache.enable_cli=0 artisan test tests/Feature/StudentSyncSignatureTest.php
git add app/Support/StudentSync/StudentSyncRequestSigner.php app/Http/Middleware/VerifyStudentSyncSignature.php bootstrap/app.php routes/api.php app/Providers/AppServiceProvider.php tests/Feature/StudentSyncSignatureTest.php
git commit -m "feat(student-sync): verify signed receiver requests"
```

---

### Task 4: Preview receiver and encrypted preview snapshot

**Files:**
- Create: `app/Support/StudentSync/StudentSyncPreviewService.php`
- Create: `app/Http/Controllers/Api/StudentSyncPreviewController.php`
- Create: `app/Http/Requests/StudentSyncPreviewRequest.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/StudentSyncPreviewApiTest.php`

**Interfaces:**
- Produces: `StudentSyncPreviewService::preview(string $clientId, array $students, ?int $actorId): array`.
- Preview response includes `preview_token`, `payload_checksum`, `expires_at`, counts, field summary, and per-item status/source_id/target_id/changed_fields/reason.

- [ ] **Step 1: Write preview API RED tests**

Create fixtures and assert:

- signed preview returns 200;
- response reports update/unchanged/conflict/not_found counts;
- `data_siswa` values remain unchanged;
- preview payload is stored encrypted and expires according to config;
- payload above max batch returns 422;
- unknown/denied fields do not enter patch;
- response omits full before/after personal values.

- [ ] **Step 2: Run preview test and confirm RED**

```bash
E:/xampp/php/php.exe -d opcache.enable_cli=0 artisan test tests/Feature/StudentSyncPreviewApiTest.php
```

- [ ] **Step 3: Implement request validation**

Validate `students` as required array capped by config, each item containing `source_id`, identity object, fields object, source checksum, and optional context. Reject a body whose server-computed checksum differs from submitted checksum.

- [ ] **Step 4: Implement preview service**

For each item: match, compute shared schema columns, compute non-empty patch, assign status, aggregate counts/fields, store encrypted payload in `StudentSyncPreview`, and create a `StudentSyncRun` with operation `preview`. Token is the random UUID preview ID; secrecy comes from signed transport and server-side snapshot, not client-controlled patch data.

- [ ] **Step 5: Implement controller/route and verify GREEN**

Controller obtains verified client ID from request attributes set by middleware and returns JSON. Run the test.

- [ ] **Step 6: Commit**

```bash
git add app/Support/StudentSync/StudentSyncPreviewService.php app/Http/Controllers/Api/StudentSyncPreviewController.php app/Http/Requests/StudentSyncPreviewRequest.php routes/api.php tests/Feature/StudentSyncPreviewApiTest.php
git commit -m "feat(student-sync): preview student updates on server"
```

---

### Task 5: Apply receiver with encrypted backup, audit, and idempotency

**Files:**
- Create: `app/Support/StudentSync/StudentSyncApplyService.php`
- Create: `app/Support/StudentSync/StudentSyncBackupStore.php`
- Create: `app/Http/Controllers/Api/StudentSyncApplyController.php`
- Create: `app/Http/Requests/StudentSyncApplyRequest.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/StudentSyncApplyApiTest.php`

**Interfaces:**
- Produces: `StudentSyncApplyService::apply(string $clientId, string $previewToken, string $checksum, string $idempotencyKey, ?int $actorId): array`.
- Produces encrypted backup under `storage/app/private/student-sync/backups/<run-id>.json.enc`.

- [ ] **Step 1: Write apply RED tests**

Assert:

- apply without preview is rejected;
- expired/applied/wrong-client/wrong-checksum preview is rejected;
- update changes only patch fields;
- empty values and denied fields remain untouched;
- conflict/not_found do not update;
- backup exists and decrypts to pre-change values;
- audit run records counts/field summary;
- repeated idempotency key returns same result without new updates;
- changed target after preview is re-evaluated and conflict/stale record is skipped;
- simulated backup failure aborts apply before DB change.

- [ ] **Step 2: Run apply test and confirm RED**

```bash
E:/xampp/php/php.exe -d opcache.enable_cli=0 artisan test tests/Feature/StudentSyncApplyApiTest.php
```

- [ ] **Step 3: Implement encrypted backup store**

Use `Crypt::encryptString(json_encode($snapshot, JSON_THROW_ON_ERROR))` and `Storage::disk('local')->put('student-sync/backups/'.$runId.'.json.enc', $encrypted)`. Throw if write fails.

- [ ] **Step 4: Implement apply transaction**

Acquire a row lock on preview, check existing run by idempotency key, re-match each update, recompute patch against current values, write backup, then update records inside a DB transaction. Mark preview applied and finish run. Return safe per-item metadata only.

- [ ] **Step 5: Implement controller/route, verify GREEN, commit**

```bash
E:/xampp/php/php.exe -d opcache.enable_cli=0 artisan test tests/Feature/StudentSyncApplyApiTest.php
git add app/Support/StudentSync/StudentSyncApplyService.php app/Support/StudentSync/StudentSyncBackupStore.php app/Http/Controllers/Api/StudentSyncApplyController.php app/Http/Requests/StudentSyncApplyRequest.php routes/api.php tests/Feature/StudentSyncApplyApiTest.php
git commit -m "feat(student-sync): apply audited idempotent student updates"
```

---

### Task 6: Local payload builder and signed HTTP client

**Files:**
- Create: `app/Support/StudentSync/StudentServerPushPayloadBuilder.php`
- Create: `app/Support/StudentSync/StudentServerPushClient.php`
- Test: `tests/Feature/StudentServerPushClientTest.php`

**Interfaces:**
- Produces: `StudentServerPushPayloadBuilder::build(?array $studentIds = null): array`.
- Produces: `StudentServerPushClient::preview(array $payload): array`.
- Produces: `StudentServerPushClient::apply(string $previewToken, string $checksum, string $idempotencyKey): array`.

- [ ] **Step 1: Write builder/client RED tests**

Use `Http::fake()` and assert builder selects active students only, optional IDs restrict scope, denied/system fields are absent, empty values are absent, and identity/context are present. Assert signed headers/path/body are correct and production URL must be HTTPS.

- [ ] **Step 2: Run test and confirm RED**

```bash
E:/xampp/php/php.exe -d opcache.enable_cli=0 artisan test tests/Feature/StudentServerPushClientTest.php
```

- [ ] **Step 3: Implement payload builder**

Use `Schema::getColumnListing('data_siswa')`, subtract denylist and ID, query `DataSiswa::where('status', 'aktif')`, and include only non-empty values. Source checksum is SHA-256 over canonical sorted JSON for each item; payload checksum covers canonical sorted items.

- [ ] **Step 4: Implement HTTP client**

Use Laravel `Http::timeout()->acceptJson()->withHeaders()` and exact JSON body from the signer. Preview can retry connection failures twice; apply must retry only with the same idempotency key. Convert non-2xx responses to a safe domain exception without including secret/body personal data.

- [ ] **Step 5: Verify GREEN and commit**

```bash
E:/xampp/php/php.exe -d opcache.enable_cli=0 artisan test tests/Feature/StudentServerPushClientTest.php
git add app/Support/StudentSync/StudentServerPushPayloadBuilder.php app/Support/StudentSync/StudentServerPushClient.php tests/Feature/StudentServerPushClientTest.php
git commit -m "feat(student-sync): build and send signed student payloads"
```

---

### Task 7: Filament preview/apply page and Data Siswa action

**Files:**
- Create: `app/Filament/Resources/DataSiswaResource/Pages/PushDataSiswasToServer.php`
- Create: `resources/views/filament/resources/data-siswa-resource/pages/push-data-siswas-to-server.blade.php`
- Modify: `app/Filament/Resources/DataSiswaResource.php:789-796`
- Modify: `app/Filament/Resources/DataSiswaResource/Pages/ManageDataSiswas.php:60-105`
- Test: `tests/Feature/StudentServerPushPageTest.php`

**Interfaces:**
- Produces Resource page key `push-server`, route `/push-server`.
- Page actions: `loadPreview`, `applyPush`, `resetPreview`.
- Page accepts optional signed scope token for hotspot shortcut.

- [ ] **Step 1: Write Filament page RED tests**

Boot user/permission tables and assert:

- full admin and manager with `data_siswa.push_server` can access;
- view-only user receives 403/redirect;
- header action appears only when client feature is enabled and authorized;
- Livewire `loadPreview` displays counts/field summary from fake client;
- `applyPush` requires existing preview and calls client with stable idempotency key;
- page never prints full payload or secret.

- [ ] **Step 2: Run test and confirm RED**

```bash
E:/xampp/php/php.exe -d opcache.enable_cli=0 artisan test tests/Feature/StudentServerPushPageTest.php
```

- [ ] **Step 3: Implement Resource page authorization and state**

Authorize when user is full admin or both `canManageModule('data_siswa')` and `can('data_siswa.push_server')`. State contains scope IDs/token, preview token/checksum, counts, field summary, safe item rows, apply result, and processing status.

- [ ] **Step 4: Implement Blade UI**

Render status cards, field-summary table, item table with status badges, conflict reason, preview timestamp/expiry, and confirmation action. Do not render raw before/after values by default; changed field names are sufficient for first release.

- [ ] **Step 5: Add Data Siswa header action and Resource route**

Add action before exports:

```php
Actions\Action::make('pushToServer')
    ->label('Push ke Server')
    ->icon('heroicon-o-cloud-arrow-up')
    ->color('info')
    ->url(DataSiswaResource::getUrl('push-server'))
    ->visible(fn (): bool => PushDataSiswasToServer::canAccessPage());
```

Add `'push-server' => Pages\PushDataSiswasToServer::route('/push-server')` before the dynamic `{record}` route.

- [ ] **Step 6: Verify GREEN and commit**

```bash
E:/xampp/php/php.exe -d opcache.enable_cli=0 artisan test tests/Feature/StudentServerPushPageTest.php
git add app/Filament/Resources/DataSiswaResource.php app/Filament/Resources/DataSiswaResource/Pages/ManageDataSiswas.php app/Filament/Resources/DataSiswaResource/Pages/PushDataSiswasToServer.php resources/views/filament/resources/data-siswa-resource/pages/push-data-siswas-to-server.blade.php tests/Feature/StudentServerPushPageTest.php
git commit -m "feat(student-sync): add Filament preview and push flow"
```

---

### Task 8: Hotspot shortcut with scoped student IDs

**Files:**
- Modify: `app/Services/HotspotStudentAccounts.php`
- Modify: `app/Filament/Pages/BuatAkunSiswa.php`
- Modify: `resources/views/filament/pages/buat-akun-siswa.blade.php`
- Create: `app/Support/StudentSync/StudentSyncScopeToken.php`
- Test: `tests/Feature/HotspotStudentPushShortcutTest.php`

**Interfaces:**
- `createAccounts()` result includes successful source student IDs.
- `StudentSyncScopeToken::issue(array $studentIds, int $userId): string` and `consume(string $token, int $userId): array` use cache with 15-minute TTL and one-time consumption.
- Hotspot page exposes `studentPushShortcutUrl` after successful account creation.

- [ ] **Step 1: Write shortcut RED test**

Assert successful item IDs are retained, failed/skipped IDs are handled according to explicit expected behavior, token is user-bound/expiring/one-time, and generated URL points to `DataSiswaResource::getUrl('push-server', ['scope_token' => ...])`.

Use item shape:

```php
[
    'student_id' => 123,
    'username' => 'siswa123',
    'password' => '01-01-2010',
    'nama' => 'Siswa Contoh',
    'rombel' => 'X 1',
]
```

- [ ] **Step 2: Run test and confirm RED**

```bash
E:/xampp/php/php.exe -d opcache.enable_cli=0 artisan test tests/Feature/HotspotStudentPushShortcutTest.php
```

- [ ] **Step 3: Preserve existing uncommitted hotspot behavior while adding IDs**

Patch only the item/result shape; do not remove reconnect/retry/updateOrCreate changes already present in `HotspotManager`, `RouterOS`, or `HotspotStudentAccounts`.

- [ ] **Step 4: Implement shortcut UI**

After result notification, keep successful student IDs, issue token, and display a button below the result summary: **Preview Push Data Siswa ke Server**. No automatic apply.

- [ ] **Step 5: Verify GREEN and commit only scoped files**

Before commit, inspect `git diff` for `HotspotStudentAccounts.php` and ensure pre-existing edits are understood. If the file contains unrelated uncommitted changes that cannot be separated safely, use `git add -p` for only this task's hunks.

```bash
E:/xampp/php/php.exe -d opcache.enable_cli=0 artisan test tests/Feature/HotspotStudentPushShortcutTest.php
git add -p app/Services/HotspotStudentAccounts.php
git add app/Filament/Pages/BuatAkunSiswa.php resources/views/filament/pages/buat-akun-siswa.blade.php app/Support/StudentSync/StudentSyncScopeToken.php tests/Feature/HotspotStudentPushShortcutTest.php
git commit -m "feat(hotspot): link created students to server push preview"
```

---

### Task 9: Full verification, local migrations, and deployment readiness

**Files:**
- Modify: `.env.example`
- Create: `docs/student-sync-deployment.md`
- Test: all new tests plus impacted Data Siswa/hotspot/passkey tests.

**Interfaces:**
- Documents exact receiver/client environment keys and deployment order.
- Produces verified migration/route/config behavior without production mutation.

- [ ] **Step 1: Document environment keys without values**

Add to `.env.example`:

```dotenv
STUDENT_SYNC_RECEIVER_ENABLED=false
STUDENT_SYNC_RECEIVER_CLIENT_ID=
STUDENT_SYNC_RECEIVER_SECRET=
STUDENT_SYNC_CLIENT_ENABLED=false
STUDENT_SYNC_SERVER_URL=https://app.smaafbs.sch.id
STUDENT_SYNC_CLIENT_ID=
STUDENT_SYNC_SECRET=
STUDENT_SYNC_CLOCK_SKEW=300
STUDENT_SYNC_PREVIEW_TTL=900
STUDENT_SYNC_MAX_BATCH=250
STUDENT_SYNC_TIMEOUT=60
```

- [ ] **Step 2: Write deployment runbook**

Document: backup server DB, deploy receiver first, migrate, install permission, set receiver secret, config cache, fixture test, then enable local client, preview real batch, manual approval, apply, verify counts, rollback via encrypted backup if needed. Include warning that server working tree/storage is dirty and must not be hard-reset.

- [ ] **Step 3: Run targeted tests**

```bash
E:/xampp/php/php.exe -d opcache.enable_cli=0 artisan test tests/Feature/StudentSyncFoundationTest.php tests/Unit/StudentSyncMergePolicyTest.php tests/Feature/StudentSyncMatcherTest.php tests/Feature/StudentSyncSignatureTest.php tests/Feature/StudentSyncPreviewApiTest.php tests/Feature/StudentSyncApplyApiTest.php tests/Feature/StudentServerPushClientTest.php tests/Feature/StudentServerPushPageTest.php tests/Feature/HotspotStudentPushShortcutTest.php
```

Expected: all pass, zero failures/errors.

- [ ] **Step 4: Run impacted existing tests**

```bash
E:/xampp/php/php.exe -d opcache.enable_cli=0 artisan test tests/Feature/DataSiswaManagementTest.php tests/Feature/WebAuthnChallengeFlowTest.php tests/Feature/WebAuthnCredentialDomainTest.php
```

Expected: all pass.

- [ ] **Step 5: Run full suite and static checks**

```bash
E:/xampp/php/php.exe -d opcache.enable_cli=0 artisan test
E:/xampp/php/php.exe -d opcache.enable_cli=0 artisan route:list --path=api/internal/v1/student-sync --no-ansi
E:/xampp/php/php.exe -d opcache.enable_cli=0 artisan route:list --path=admin/data-siswas/push-server --no-ansi
git diff --check
```

- [ ] **Step 6: Run local migration and smoke test**

Backup local DB, then:

```bash
E:/xampp/php/php.exe -d opcache.enable_cli=0 artisan migrate --path=database/migrations/2026_08_20_120000_create_student_sync_tables.php --force
E:/xampp/php/php.exe -d opcache.enable_cli=0 artisan student-sync:install-defaults
curl -sS -o "$LOCALAPPDATA/Temp/student-sync-login.html" -w "HTTP %{http_code}\n" http://127.0.0.1:8090/admin/login
```

Expected migration success, permission installed, HTTP 200.

- [ ] **Step 7: Review secrets and staged files**

Search source/diff for actual configured secrets, ensure only `.env.example` placeholders exist, and verify no student payload fixture with real personal data was added.

- [ ] **Step 8: Commit readiness docs/config**

```bash
git add .env.example docs/student-sync-deployment.md
git commit -m "docs(student-sync): add secure deployment runbook"
```

---

### Task 10: Controlled server deployment and first real push

**Files/Systems:**
- GitHub remote branch `codex/boarding-guru-admin-deploy`
- Server app `/home/sman5479/akses-app`
- Server DB `sman5479_app`
- Local DB `aksessmaafbs`

**Interfaces:**
- Server receiver must be healthy before local client is enabled.
- First real preview baseline: 162 active, approximately 44 update candidates, with no deletes.

- [ ] **Step 1: Pre-deploy verification and backup**

Record local HEAD/status, server HEAD/status, remote branch HEAD, and create a server database backup plus `.env` backup. Do not include storage symlink contents in Git operations.

- [ ] **Step 2: Push reviewed commits to GitHub**

Push current branch only after full tests pass. Confirm remote SHA equals local SHA.

- [ ] **Step 3: Deploy receiver safely**

Use the repository's existing cPanel deploy procedure, preserving `.env`, `storage`, and uploaded files. Run targeted migration, permission installer, cache clear/config cache, and route check on server.

- [ ] **Step 4: Configure shared secret securely**

Generate one 32-byte-or-longer random secret at execution time; place receiver values in server `.env` and client values in local `.env`. Never print the secret in conversation/logs. Enable receiver first; keep client disabled until fixture probe passes.

- [ ] **Step 5: Probe receiver with fixture**

Use a nonexistent synthetic source ID and ensure preview returns `not_found` without changing DB. Verify nonce replay is rejected and logs contain no secret.

- [ ] **Step 6: Enable local client and run real preview only**

From Filament, preview all active students. Verify:

- local active = 162;
- server active = 162;
- unmatched local/server = 0 at baseline;
- around 44 students are candidates;
- no create/delete operations;
- field summary aligns with NISN/tempat lahir/tanggal lahir/nama baseline.

Stop for operator confirmation before apply.

- [ ] **Step 7: Apply first batch and verify**

Apply once, capture run ID, then recompute aggregate server stats. Expected: zero active students missing NISN/tanggal lahir unless conflicts are explicitly reported; server active remains 162; no rows deleted.

- [ ] **Step 8: Verify idempotency and hotspot shortcut**

Repeat apply with same idempotency key and confirm no second update. Create or use a controlled hotspot test candidate without mutating unrelated router users, open shortcut preview, and confirm scope is restricted.

- [ ] **Step 9: Final review and operational handoff**

Check server login/passkey remains visible, admin Data Siswa page loads, sync run history is readable, backups exist, and server logs contain no new errors/secrets. Record rollback instructions and retain `hasil-hermes`/database backups.
