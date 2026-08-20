<?php

namespace Tests\Feature;

use App\Models\StudentSyncPreview;
use App\Models\StudentSyncRun;
use App\Support\StudentSync\StudentSyncBackupStore;
use App\Support\StudentSync\StudentSyncPreviewService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class StudentSyncApplyApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('data_siswa');
        Schema::create('data_siswa', function (Blueprint $table): void {
            $table->id();
            $table->string('nama');
            $table->string('nipd')->nullable();
            $table->string('nisn')->nullable();
            $table->string('billing_code')->nullable();
            $table->date('tanggal_lahir')->nullable();
            $table->string('rombel_saat_ini')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();
        });

        $migration = require database_path('migrations/2026_08_20_120000_create_student_sync_tables.php');
        $migration->up();

        Carbon::setTestNow('2026-08-20 12:34:56');
        Storage::fake('local');
        config([
            'student_sync.receiver.enabled' => true,
            'student_sync.receiver.client_id' => 'school-local',
            'student_sync.receiver.secret' => str_repeat('s', 32),
            'student_sync.security.preview_ttl_seconds' => 600,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Schema::dropIfExists('student_sync_nonces');
        Schema::dropIfExists('student_sync_previews');
        Schema::dropIfExists('student_sync_runs');
        Schema::dropIfExists('data_siswa');

        parent::tearDown();
    }

    public function test_apply_updates_only_current_allowed_non_empty_patch_and_writes_encrypted_backup_and_audit(): void
    {
        $this->student(10, 'Old Personal Name', 'P001', 'N001', 'X-A');
        $this->student(20, 'Bella', 'P002', 'N002', 'X-B');
        $this->student(30, 'Citra', 'P003', 'N003', 'X-C');
        $students = [
            $this->payloadStudent(10, ['nipd' => 'P001'], [
                'nama' => 'New Personal Name',
                'rombel_saat_ini' => 'X-Z',
                'nisn' => '',
                'status' => 'alumni',
            ]),
            $this->payloadStudent(20, ['nipd' => 'P002'], ['nama' => 'Bella']),
            $this->payloadStudent(30, ['nipd' => 'P003', 'nisn' => 'OTHER'], ['nama' => 'Wrong Citra']),
            $this->payloadStudent(40, ['nipd' => 'MISSING'], ['nama' => 'Missing']),
        ];
        $preview = $this->preview($students);

        $response = $this->postApply($preview, 'apply-main', 'nonce-main');

        $response->assertOk()
            ->assertJsonPath('counts', [
                'total' => 4,
                'update' => 1,
                'unchanged' => 1,
                'conflict' => 1,
                'not_found' => 1,
            ])
            ->assertJsonPath('field_summary', ['nama' => 1, 'rombel_saat_ini' => 1])
            ->assertJsonPath('items.0.status', 'update')
            ->assertJsonPath('items.0.source_id', 10)
            ->assertJsonPath('items.0.target_id', 10)
            ->assertJsonPath('items.0.changed_fields', ['nama', 'rombel_saat_ini'])
            ->assertJsonPath('items.1.status', 'unchanged')
            ->assertJsonPath('items.2.status', 'conflict')
            ->assertJsonPath('items.3.status', 'not_found');

        $student = DB::table('data_siswa')->where('id', 10)->first();
        $this->assertSame('New Personal Name', $student->nama);
        $this->assertSame('X-Z', $student->rombel_saat_ini);
        $this->assertSame('N001', $student->nisn);
        $this->assertSame('aktif', $student->status);
        $this->assertSame('Citra', DB::table('data_siswa')->where('id', 30)->value('nama'));

        $run = StudentSyncRun::query()->where('operation', 'apply')->sole();
        $this->assertSame('completed', $run->status);
        $this->assertSame('school-local', $run->client_id);
        $this->assertSame('apply-main', $run->idempotency_key);
        $this->assertSame($preview->payload_checksum, $run->payload_checksum);
        $this->assertSame($response->json('counts'), $run->counts);
        $this->assertSame(['nama' => 1, 'rombel_saat_ini' => 1], $run->field_summary);
        $this->assertNotNull($run->backup_path);
        Storage::disk('local')->assertExists($run->backup_path);

        $encrypted = Storage::disk('local')->get($run->backup_path);
        $this->assertStringNotContainsString('Old Personal Name', $encrypted);
        $backup = json_decode(Crypt::decryptString($encrypted), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('Old Personal Name', $backup['records'][0]['before']['nama']);
        $this->assertSame('X-A', $backup['records'][0]['before']['rombel_saat_ini']);
        $this->assertSame(['nama', 'rombel_saat_ini'], $backup['records'][0]['changed_fields']);

        $json = $response->getContent();
        $this->assertStringNotContainsString('Old Personal Name', $json);
        $this->assertStringNotContainsString('New Personal Name', $json);
        $this->assertStringNotContainsString('X-Z', $json);
        $this->assertNotNull($preview->fresh()->applied_at);
    }

    public function test_apply_rejects_missing_unknown_expired_applied_wrong_client_and_wrong_checksum_previews(): void
    {
        $checksum = str_repeat('a', 64);
        $this->postSignedApply([], 'missing-body', 'nonce-missing')->assertUnprocessable();
        $this->postSignedApply([
            'preview_token' => '00000000-0000-4000-8000-000000000000',
            'payload_checksum' => $checksum,
        ], 'unknown', 'nonce-unknown')->assertUnprocessable();

        foreach (['expired', 'applied', 'wrong-client', 'wrong-checksum'] as $state) {
            $preview = StudentSyncPreview::query()->create([
                'client_id' => $state === 'wrong-client' ? 'other-client' : 'school-local',
                'payload_checksum' => $checksum,
                'encrypted_payload' => ['items' => []],
                'expires_at' => $state === 'expired' ? now()->subSecond() : now()->addMinute(),
                'applied_at' => $state === 'applied' ? now() : null,
            ]);
            $submittedChecksum = $state === 'wrong-checksum' ? str_repeat('b', 64) : $checksum;

            $this->postApply($preview, 'reject-'.$state, 'nonce-'.$state, $submittedChecksum)
                ->assertUnprocessable();
        }

        $this->assertDatabaseCount('student_sync_runs', 0);
    }

    public function test_repeated_idempotency_key_returns_first_result_without_reapplying_or_reauditing(): void
    {
        $this->student(10, 'Before', 'P001', 'N001', 'X-A');
        $preview = $this->preview([
            $this->payloadStudent(10, ['nipd' => 'P001'], ['nama' => 'Applied Once']),
        ]);

        $first = $this->postApply($preview, 'same-key', 'nonce-first')->assertOk();
        DB::table('data_siswa')->where('id', 10)->update(['nama' => 'Manual Later Change']);
        $second = $this->postApply($preview, 'same-key', 'nonce-second')->assertOk();

        $this->assertSame($first->json(), $second->json());
        $this->assertSame('Manual Later Change', DB::table('data_siswa')->where('id', 10)->value('nama'));
        $this->assertDatabaseCount('student_sync_runs', 2); // one preview audit and one apply audit
        $this->assertSame(1, StudentSyncRun::query()->where('operation', 'apply')->count());
        $this->assertCount(1, Storage::disk('local')->allFiles('student-sync/backups'));
    }

    public function test_idempotency_key_cannot_be_reused_for_a_different_preview(): void
    {
        $this->student(10, 'Before', 'P001', 'N001', 'X-A');
        $firstPreview = $this->preview([
            $this->payloadStudent(10, ['nipd' => 'P001'], ['nama' => 'First']),
        ]);
        $secondPreview = $this->preview([
            $this->payloadStudent(10, ['nipd' => 'P001'], ['nama' => 'Second']),
        ]);
        $this->postApply($firstPreview, 'bound-key', 'nonce-bound-first')->assertOk();

        $this->postApply($secondPreview, 'bound-key', 'nonce-bound-second')->assertUnprocessable();

        $this->assertSame('First', DB::table('data_siswa')->where('id', 10)->value('nama'));
        $this->assertSame(1, StudentSyncRun::query()->where('operation', 'apply')->count());
        $this->assertNull($secondPreview->fresh()->applied_at);
    }

    public function test_apply_rechecks_changed_target_and_skips_stale_rematch_without_updating_alternate_record(): void
    {
        $this->student(10, 'Before', 'P001', 'N001', 'X-A');
        $preview = $this->preview([
            $this->payloadStudent(10, ['nipd' => 'P001'], ['nama' => 'Proposed']),
        ]);
        DB::table('data_siswa')->where('id', 10)->update(['nipd' => null]);
        $this->student(20, 'Alternate Target', 'P001', 'N002', 'X-B');

        $response = $this->postApply($preview, 'stale-key', 'nonce-stale');

        $response->assertOk()
            ->assertJsonPath('counts.update', 0)
            ->assertJsonPath('counts.conflict', 1)
            ->assertJsonPath('items.0.status', 'conflict')
            ->assertJsonPath('items.0.reason', 'stale_target');
        $this->assertSame('Before', DB::table('data_siswa')->where('id', 10)->value('nama'));
        $this->assertSame('Alternate Target', DB::table('data_siswa')->where('id', 20)->value('nama'));
        $run = StudentSyncRun::query()->where('operation', 'apply')->sole();
        $this->assertNull($run->backup_path);
        $this->assertSame([], Storage::disk('local')->allFiles('student-sync/backups'));
    }

    public function test_backup_failure_aborts_transaction_before_student_preview_or_audit_changes(): void
    {
        $this->student(10, 'Before', 'P001', 'N001', 'X-A');
        $preview = $this->preview([
            $this->payloadStudent(10, ['nipd' => 'P001'], ['nama' => 'Must Roll Back']),
        ]);
        $this->mock(StudentSyncBackupStore::class, function ($mock): void {
            $mock->shouldReceive('write')->once()->andThrow(new RuntimeException('simulated backup failure'));
        });

        $this->postApply($preview, 'backup-fails', 'nonce-backup-fails')->assertStatus(500);

        $this->assertSame('Before', DB::table('data_siswa')->where('id', 10)->value('nama'));
        $this->assertNull($preview->fresh()->applied_at);
        $this->assertSame(0, StudentSyncRun::query()->where('operation', 'apply')->count());
    }

    /** @param array<int, array<string, mixed>> $students */
    private function preview(array $students): StudentSyncPreview
    {
        $result = app(StudentSyncPreviewService::class)->preview('school-local', $students, null);

        return StudentSyncPreview::query()->findOrFail($result['preview_token']);
    }

    private function postApply(
        StudentSyncPreview $preview,
        string $idempotencyKey,
        string $nonce,
        ?string $checksum = null,
    ) {
        return $this->postSignedApply([
            'preview_token' => $preview->getKey(),
            'payload_checksum' => $checksum ?? $preview->payload_checksum,
        ], $idempotencyKey, $nonce);
    }

    /** @param array<string, mixed> $payload */
    private function postSignedApply(array $payload, string $idempotencyKey, string $nonce)
    {
        $body = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $path = '/api/internal/student-sync/apply';
        $bodyHash = hash('sha256', $body);
        $timestamp = (string) now()->timestamp;
        $canonical = implode("\n", ['POST', $path, $timestamp, $nonce, $idempotencyKey, $bodyHash]);
        $headers = [
            'X-Student-Sync-Client' => 'school-local',
            'X-Student-Sync-Timestamp' => $timestamp,
            'X-Student-Sync-Nonce' => $nonce,
            'X-Student-Sync-Idempotency-Key' => $idempotencyKey,
            'X-Student-Sync-Body-SHA256' => $bodyHash,
            'X-Student-Sync-Signature' => hash_hmac('sha256', $canonical, str_repeat('s', 32)),
            'Content-Type' => 'application/json',
        ];

        return $this->call('POST', $path, [], [], [], $this->serverHeaders($headers), $body);
    }

    /** @param array<string, mixed> $identity
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    private function payloadStudent(int $sourceId, array $identity, array $fields): array
    {
        return [
            'source_id' => $sourceId,
            'identity' => $identity,
            'fields' => $fields,
            'source_checksum' => hash('sha256', 'source-'.$sourceId),
        ];
    }

    /** @param array<string, string> $headers
     * @return array<string, string>
     */
    private function serverHeaders(array $headers): array
    {
        $server = [];

        foreach ($headers as $name => $value) {
            $key = strtoupper(str_replace('-', '_', $name));
            $server[$key === 'CONTENT_TYPE' ? $key : 'HTTP_'.$key] = $value;
        }

        return $server;
    }

    private function student(int $id, string $name, string $nipd, string $nisn, string $rombel): void
    {
        DB::table('data_siswa')->insert([
            'id' => $id,
            'nama' => $name,
            'nipd' => $nipd,
            'nisn' => $nisn,
            'rombel_saat_ini' => $rombel,
            'status' => 'aktif',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
