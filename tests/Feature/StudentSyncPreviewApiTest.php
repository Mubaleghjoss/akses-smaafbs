<?php

namespace Tests\Feature;

use App\Models\StudentSyncPreview;
use App\Models\StudentSyncRun;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class StudentSyncPreviewApiTest extends TestCase
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
        config([
            'student_sync.receiver.enabled' => true,
            'student_sync.receiver.client_id' => 'school-local',
            'student_sync.receiver.secret' => str_repeat('s', 32),
            'student_sync.security.preview_ttl_seconds' => 600,
            'student_sync.security.max_batch' => 10,
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

    public function test_signed_preview_returns_safe_summary_and_stores_encrypted_snapshot_without_mutating_students(): void
    {
        $this->student(10, 'Old Personal Name', 'P001', 'N001');
        $this->student(20, 'Bella', 'P002', 'N002');
        $this->student(30, 'Citra', 'P003', 'N003');

        $students = [
            $this->payloadStudent(10, ['nipd' => 'P001'], [
                'nama' => 'New Personal Name',
                'nisn' => 'N001',
                'status' => 'alumni',
                'unknown_private_field' => 'must not enter patch',
            ]),
            $this->payloadStudent(20, ['nipd' => 'P002'], ['nama' => 'Bella']),
            $this->payloadStudent(30, ['nipd' => 'P003', 'nisn' => 'OTHER'], ['nama' => 'Changed Citra']),
            $this->payloadStudent(40, ['nipd' => 'MISSING'], ['nama' => 'Missing Student']),
        ];

        $response = $this->postSigned($students, 'nonce-preview');

        $response->assertOk()
            ->assertJsonStructure([
                'preview_token',
                'payload_checksum',
                'expires_at',
                'counts' => ['total', 'update', 'unchanged', 'conflict', 'not_found'],
                'field_summary',
                'items' => [
                    '*' => ['status', 'source_id', 'target_id', 'changed_fields', 'reason'],
                ],
            ])
            ->assertJsonPath('counts', [
                'total' => 4,
                'update' => 1,
                'unchanged' => 1,
                'conflict' => 1,
                'not_found' => 1,
            ])
            ->assertJsonPath('field_summary', ['nama' => 1])
            ->assertJsonPath('items.0.status', 'update')
            ->assertJsonPath('items.0.source_id', 10)
            ->assertJsonPath('items.0.target_id', 10)
            ->assertJsonPath('items.0.changed_fields', ['nama'])
            ->assertJsonPath('items.1.status', 'unchanged')
            ->assertJsonPath('items.2.status', 'conflict')
            ->assertJsonPath('items.3.status', 'not_found');

        $this->assertSame('Old Personal Name', DB::table('data_siswa')->where('id', 10)->value('nama'));
        $this->assertSame('aktif', DB::table('data_siswa')->where('id', 10)->value('status'));

        $json = $response->getContent();
        $this->assertStringNotContainsString('Old Personal Name', $json);
        $this->assertStringNotContainsString('New Personal Name', $json);
        $this->assertStringNotContainsString('must not enter patch', $json);
        $this->assertStringNotContainsString('before', $json);
        $this->assertStringNotContainsString('after', $json);

        $preview = StudentSyncPreview::query()->sole();
        $this->assertSame($response->json('preview_token'), $preview->getKey());
        $this->assertSame($response->json('payload_checksum'), $preview->payload_checksum);
        $this->assertSame('school-local', $preview->client_id);
        $this->assertTrue($preview->expires_at->equalTo(now()->addSeconds(600)));
        $this->assertSame(['nama'], $preview->encrypted_payload['items'][0]['changed_fields']);
        $this->assertSame(['nama' => 'New Personal Name'], $preview->encrypted_payload['items'][0]['patch']);
        $this->assertArrayNotHasKey('unknown_private_field', $preview->encrypted_payload['items'][0]['patch']);
        $this->assertArrayNotHasKey('status', $preview->encrypted_payload['items'][0]['patch']);

        $rawEncryptedPayload = (string) DB::table('student_sync_previews')->value('encrypted_payload');
        $this->assertStringNotContainsString('New Personal Name', $rawEncryptedPayload);
        $this->assertStringNotContainsString('P001', $rawEncryptedPayload);

        $run = StudentSyncRun::query()->sole();
        $this->assertSame('preview', $run->operation);
        $this->assertSame('completed', $run->status);
        $this->assertSame('school-local', $run->client_id);
        $this->assertNull($run->user_id);
        $this->assertSame($preview->payload_checksum, $run->payload_checksum);
        $this->assertSame($response->json('counts'), $run->counts);
        $this->assertSame(['nama' => 1], $run->field_summary);
        $this->assertTrue($run->started_at->equalTo(now()));
        $this->assertTrue($run->finished_at->equalTo(now()));
    }

    public function test_preview_rejects_a_batch_above_configured_maximum(): void
    {
        config(['student_sync.security.max_batch' => 1]);
        $students = [
            $this->payloadStudent(1, ['nipd' => 'P001'], ['nama' => 'A']),
            $this->payloadStudent(2, ['nipd' => 'P002'], ['nama' => 'B']),
        ];

        $this->postSigned($students, 'nonce-max')->assertUnprocessable()
            ->assertJsonValidationErrors('students');

        $this->assertDatabaseCount('student_sync_previews', 0);
        $this->assertDatabaseCount('student_sync_runs', 0);
    }

    public function test_preview_rejects_invalid_item_shape(): void
    {
        $students = [[
            'source_id' => 1,
            'fields' => ['nama' => ['nested' => 'invalid']],
            'source_checksum' => str_repeat('a', 64),
        ]];

        $this->postSigned($students, 'nonce-shape')->assertUnprocessable()
            ->assertJsonValidationErrors(['students.0.identity', 'students.0.fields.nama']);
    }

    public function test_preview_rejects_payload_checksum_mismatch(): void
    {
        $students = [$this->payloadStudent(1, ['nipd' => 'P001'], ['nama' => 'A'])];

        $this->postSigned($students, 'nonce-checksum', str_repeat('0', 64))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('payload_checksum');

        $this->assertDatabaseCount('student_sync_previews', 0);
    }

    public function test_server_checksum_is_deterministic_for_object_key_order(): void
    {
        $this->student(10, 'Alya', 'P001', 'N001');
        $first = [$this->payloadStudent(10, ['nipd' => 'P001', 'nisn' => 'N001'], [
            'nama' => 'Alya Putri',
            'rombel_saat_ini' => 'X-A',
        ])];
        $second = [[
            'source_checksum' => $first[0]['source_checksum'],
            'fields' => ['rombel_saat_ini' => 'X-A', 'nama' => 'Alya Putri'],
            'identity' => ['nisn' => 'N001', 'nipd' => 'P001'],
            'source_id' => 10,
        ]];

        $firstResponse = $this->postSigned($first, 'nonce-order-1');
        $secondResponse = $this->postSigned($second, 'nonce-order-2');

        $firstResponse->assertOk();
        $secondResponse->assertOk();
        $this->assertSame($firstResponse->json('payload_checksum'), $secondResponse->json('payload_checksum'));
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

    /** @param array<int, array<string, mixed>> $students */
    private function postSigned(array $students, string $nonce, ?string $checksum = null)
    {
        $payload = [
            'payload_checksum' => $checksum ?? $this->checksum($students),
            'students' => $students,
        ];
        $body = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $path = '/api/internal/student-sync/preview';
        $bodyHash = hash('sha256', $body);
        $timestamp = (string) now()->timestamp;
        $canonical = implode("\n", ['POST', $path, $timestamp, $nonce, 'idem-preview', $bodyHash]);
        $headers = [
            'X-Student-Sync-Client' => 'school-local',
            'X-Student-Sync-Timestamp' => $timestamp,
            'X-Student-Sync-Nonce' => $nonce,
            'X-Student-Sync-Idempotency-Key' => 'idem-preview',
            'X-Student-Sync-Body-SHA256' => $bodyHash,
            'X-Student-Sync-Signature' => hash_hmac('sha256', $canonical, str_repeat('s', 32)),
            'Content-Type' => 'application/json',
        ];

        return $this->call('POST', $path, [], [], [], $this->serverHeaders($headers), $body);
    }

    /** @param array<int, array<string, mixed>> $students */
    private function checksum(array $students): string
    {
        return hash('sha256', json_encode(
            $this->canonicalize($students),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        ));
    }

    private function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (! array_is_list($value)) {
            ksort($value, SORT_STRING);
        }

        foreach ($value as $key => $item) {
            $value[$key] = $this->canonicalize($item);
        }

        return $value;
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

    private function student(int $id, string $name, string $nipd, string $nisn): void
    {
        DB::table('data_siswa')->insert([
            'id' => $id,
            'nama' => $name,
            'nipd' => $nipd,
            'nisn' => $nisn,
            'status' => 'aktif',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
