<?php

namespace Tests\Feature;

use App\Support\StudentSync\StudentServerPushClient;
use App\Support\StudentSync\StudentServerPushPayloadBuilder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class StudentServerPushClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('data_siswa');
        Schema::create('data_siswa', function (Blueprint $table): void {
            $table->id();
            $table->string('nama')->nullable();
            $table->string('nipd')->nullable();
            $table->string('nisn')->nullable();
            $table->string('billing_code')->nullable();
            $table->string('rombel_saat_ini')->nullable();
            $table->string('status')->nullable();
            $table->string('private_note')->nullable();
            $table->timestamps();
        });

        config(['student_sync.denied_fields' => [
            'id', 'created_at', 'updated_at', 'status', 'private_note',
        ]]);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('data_siswa');

        parent::tearDown();
    }

    public function test_builder_selects_only_requested_active_students_with_safe_non_empty_payloads(): void
    {
        $this->student(10, 'aktif', [
            'nama' => 'Alya',
            'nipd' => 'P010',
            'nisn' => 'N010',
            'billing_code' => 'B010',
            'rombel_saat_ini' => 'X-A',
            'private_note' => 'do not send',
        ]);
        $this->student(20, 'aktif', [
            'nama' => 'Bella',
            'nipd' => 'P020',
            'nisn' => '   ',
            'billing_code' => null,
            'rombel_saat_ini' => '',
        ]);
        $this->student(30, 'alumni', ['nama' => 'Citra', 'nipd' => 'P030']);

        $payload = app(StudentServerPushPayloadBuilder::class)->build([20, 10, 30]);

        $this->assertSame([10, 20], array_column($payload['students'], 'source_id'));
        $this->assertSame([
            'source_id' => 10,
            'identity' => [
                'nipd' => 'P010',
                'nisn' => 'N010',
                'billing_code' => 'B010',
                'nama' => 'Alya',
            ],
            'fields' => [
                'nama' => 'Alya',
                'nipd' => 'P010',
                'nisn' => 'N010',
                'billing_code' => 'B010',
                'rombel_saat_ini' => 'X-A',
            ],
            'source_checksum' => hash('sha256', json_encode([
                'fields' => [
                    'billing_code' => 'B010',
                    'nama' => 'Alya',
                    'nipd' => 'P010',
                    'nisn' => 'N010',
                    'rombel_saat_ini' => 'X-A',
                ],
                'identity' => [
                    'billing_code' => 'B010',
                    'nama' => 'Alya',
                    'nipd' => 'P010',
                    'nisn' => 'N010',
                ],
                'source_id' => 10,
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)),
            'context' => ['rombel_saat_ini' => 'X-A', 'origin' => 'data_siswa'],
        ], $payload['students'][0]);
        $this->assertSame([
            'source_id' => 20,
            'identity' => ['nipd' => 'P020', 'nama' => 'Bella'],
            'fields' => ['nama' => 'Bella', 'nipd' => 'P020'],
            'source_checksum' => $payload['students'][1]['source_checksum'],
            'context' => ['origin' => 'data_siswa'],
        ], $payload['students'][1]);
        $this->assertArrayNotHasKey('private_note', $payload['students'][0]['fields']);
        $this->assertSame($this->checksum($payload['students']), $payload['payload_checksum']);
    }

    private function checksum(mixed $value): string
    {
        return hash('sha256', json_encode(
            $this->canonicalize($value),
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

    public function test_client_sends_exact_signed_preview_and_apply_requests(): void
    {
        config([
            'student_sync.client.enabled' => true,
            'student_sync.client.server_url' => 'https://sync.example.test',
            'student_sync.client.client_id' => 'school-local',
            'student_sync.client.secret' => str_repeat('s', 32),
            'student_sync.client.timeout' => 12,
        ]);
        Http::fake([
            'https://sync.example.test/api/internal/student-sync/preview' => Http::response([
                'preview_token' => 'preview-token',
                'payload_checksum' => str_repeat('a', 64),
            ]),
            'https://sync.example.test/api/internal/student-sync/apply' => Http::response(['counts' => ['update' => 1]]),
        ]);

        $preview = app(StudentServerPushClient::class)->preview([
            'payload_checksum' => str_repeat('a', 64),
            'students' => [],
        ]);
        $apply = app(StudentServerPushClient::class)->apply('preview-token', str_repeat('a', 64), 'apply-idem');

        $this->assertSame('preview-token', $preview['preview_token']);
        $this->assertSame(['update' => 1], $apply['counts']);
        Http::assertSent(function (Request $request): bool {
            if ($request->url() !== 'https://sync.example.test/api/internal/student-sync/preview') {
                return false;
            }

            $body = '{"payload_checksum":"'.str_repeat('a', 64).'","students":[]}';
            $headers = $request->headers();

            return $request->method() === 'POST'
                && $request->body() === $body
                && $headers['X-Student-Sync-Client'][0] === 'school-local'
                && $headers['X-Student-Sync-Idempotency-Key'][0] !== ''
                && $headers['X-Student-Sync-Body-SHA256'][0] === hash('sha256', $body)
                && $this->hasValidSignature($headers, '/api/internal/student-sync/preview', $body);
        });
        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://sync.example.test/api/internal/student-sync/apply'
                && $request->body() === '{"preview_token":"preview-token","payload_checksum":"'.str_repeat('a', 64).'"}'
                && $request->header('X-Student-Sync-Idempotency-Key')[0] === 'apply-idem';
        });
    }

    public function test_client_rejects_insecure_production_url_without_sending_a_request(): void
    {
        config([
            'app.env' => 'production',
            'student_sync.client.enabled' => true,
            'student_sync.client.server_url' => 'http://sync.example.test',
            'student_sync.client.client_id' => 'school-local',
            'student_sync.client.secret' => str_repeat('s', 32),
        ]);
        Http::fake();

        try {
            app(StudentServerPushClient::class)->preview(['payload_checksum' => 'checksum', 'students' => []]);
            $this->fail('Expected insecure production URL to be rejected.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Student sync server URL must use HTTPS in production.', $exception->getMessage());
        }

        Http::assertNothingSent();
    }

    public function test_preview_retries_connection_failure_and_non_success_response_is_safe(): void
    {
        config([
            'student_sync.client.enabled' => true,
            'student_sync.client.server_url' => 'https://sync.example.test',
            'student_sync.client.client_id' => 'school-local',
            'student_sync.client.secret' => str_repeat('s', 32),
        ]);
        $attempts = 0;
        Http::fake(function () use (&$attempts) {
            $attempts++;

            return $attempts === 1
                ? Http::failedConnection('connection failed')
                : Http::response(['preview_token' => 'after-retry']);
        });

        $this->assertSame('after-retry', app(StudentServerPushClient::class)->preview([
            'payload_checksum' => 'checksum',
            'students' => [],
        ])['preview_token']);
        Http::assertSentCount(2);

    }

    public function test_non_success_response_fails_without_response_body_data(): void
    {
        config([
            'student_sync.client.enabled' => true,
            'student_sync.client.server_url' => 'https://sync.example.test',
            'student_sync.client.client_id' => 'school-local',
            'student_sync.client.secret' => str_repeat('s', 32),
        ]);
        Http::fake(['https://sync.example.test/api/internal/student-sync/preview' => Http::response([
            'message' => 'Personal student data must never escape an error.',
            'secret' => str_repeat('s', 32),
        ], 422)]);

        try {
            app(StudentServerPushClient::class)->preview(['payload_checksum' => 'checksum', 'students' => []]);
            $this->fail('Expected rejected response to fail safely.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Student sync server rejected the request.', $exception->getMessage());
            $this->assertStringNotContainsString('Personal student data', $exception->getMessage());
            $this->assertStringNotContainsString(str_repeat('s', 32), $exception->getMessage());
        }
    }

    private function hasValidSignature(array $headers, string $path, string $body): bool
    {
        $canonical = implode("\n", [
            'POST',
            $path,
            $headers['X-Student-Sync-Timestamp'][0],
            $headers['X-Student-Sync-Nonce'][0],
            $headers['X-Student-Sync-Idempotency-Key'][0],
            hash('sha256', $body),
        ]);

        return hash_equals(
            hash_hmac('sha256', $canonical, str_repeat('s', 32)),
            $headers['X-Student-Sync-Signature'][0],
        );
    }

    /** @param array<string, mixed> $attributes */
    private function student(int $id, string $status, array $attributes = []): void
    {
        DB::table('data_siswa')->insert([
            ...$attributes,
            'id' => $id,
            'status' => $status,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
