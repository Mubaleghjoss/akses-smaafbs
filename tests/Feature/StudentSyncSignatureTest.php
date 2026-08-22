<?php

namespace Tests\Feature;

use App\Models\StudentSyncNonce;
use App\Support\StudentSync\StudentSyncPreviewService;
use App\Support\StudentSync\StudentSyncRequestSigner;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class StudentSyncSignatureTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('data_siswa', function (Blueprint $table): void {
            $table->id();
            $table->string('nama')->nullable();
            $table->string('nipd')->nullable();
            $table->string('nisn')->nullable();
            $table->string('billing_code')->nullable();
            $table->date('tanggal_lahir')->nullable();
        });

        $migration = require database_path('migrations/2026_08_20_120000_create_student_sync_tables.php');
        $migration->up();
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

    public function test_signer_produces_the_exact_canonical_headers(): void
    {
        Carbon::setTestNow('2026-08-20 12:34:56');
        config([
            'student_sync.client.client_id' => 'school-local',
            'student_sync.client.secret' => str_repeat('s', 32),
        ]);

        $headers = app(StudentSyncRequestSigner::class)->headers(
            'post',
            '/api/internal/student-sync/preview',
            '{"students":[1]}',
            'idem-123',
        );

        $this->assertSame([
            'X-Student-Sync-Client',
            'X-Student-Sync-Timestamp',
            'X-Student-Sync-Nonce',
            'X-Student-Sync-Idempotency-Key',
            'X-Student-Sync-Body-SHA256',
            'X-Student-Sync-Signature',
        ], array_keys($headers));
        $this->assertSame('school-local', $headers['X-Student-Sync-Client']);
        $this->assertSame((string) now()->timestamp, $headers['X-Student-Sync-Timestamp']);
        $this->assertSame('idem-123', $headers['X-Student-Sync-Idempotency-Key']);
        $this->assertSame(hash('sha256', '{"students":[1]}'), $headers['X-Student-Sync-Body-SHA256']);

        $canonical = implode("\n", [
            'POST',
            '/api/internal/student-sync/preview',
            $headers['X-Student-Sync-Timestamp'],
            $headers['X-Student-Sync-Nonce'],
            'idem-123',
            $headers['X-Student-Sync-Body-SHA256'],
        ]);

        $this->assertSame(
            hash_hmac('sha256', $canonical, str_repeat('s', 32)),
            $headers['X-Student-Sync-Signature'],
        );
    }

    public function test_valid_signature_reaches_receiver_and_stores_expiring_nonce(): void
    {
        Carbon::setTestNow('2026-08-20 12:34:56');
        $this->enableReceiver();
        $body = $this->validPreviewBody();

        $response = $this->call(
            'POST',
            '/api/internal/student-sync/preview',
            [],
            [],
            [],
            $this->serverHeaders($this->signedHeaders(
                'POST',
                '/api/internal/student-sync/preview',
                $body,
                'nonce-valid',
            )),
            $body,
        );

        $response->assertOk()
            ->assertJsonPath('counts.total', 1)
            ->assertJsonPath('items.0.status', 'not_found');

        $nonce = StudentSyncNonce::query()->sole();
        $this->assertSame('school-local', $nonce->client_id);
        $this->assertSame('nonce-valid', $nonce->nonce);
        $this->assertTrue($nonce->expires_at->greaterThan(now()));
        $this->assertStringNotContainsString(str_repeat('s', 32), json_encode($nonce->getAttributes()));
    }

    public function test_altered_body_is_rejected_without_storing_nonce(): void
    {
        $this->enableReceiver();
        $headers = $this->signedHeaders(
            'POST',
            '/api/internal/student-sync/preview',
            '{"students":[1]}',
            'nonce-altered',
        );

        $this->postRaw('/api/internal/student-sync/preview', '{"students":[2]}', $headers)
            ->assertUnauthorized();

        $this->assertDatabaseCount('student_sync_nonces', 0);
    }

    public function test_signature_for_a_different_path_is_rejected(): void
    {
        $this->enableReceiver();
        $body = '{"students":[1]}';
        $headers = $this->signedHeaders(
            'POST',
            '/api/internal/student-sync/apply',
            $body,
            'nonce-path',
        );

        $this->postRaw('/api/internal/student-sync/preview', $body, $headers)
            ->assertUnauthorized();
    }

    public function test_signature_from_wrong_secret_is_rejected(): void
    {
        $this->enableReceiver();
        $body = '{"students":[1]}';

        $this->postRaw('/api/internal/student-sync/preview', $body, $this->signedHeaders(
            'POST',
            '/api/internal/student-sync/preview',
            $body,
            'nonce-secret',
            secret: str_repeat('x', 32),
        ))->assertUnauthorized();
    }

    public function test_expired_timestamp_is_rejected(): void
    {
        $this->enableReceiver();
        $body = '{"students":[1]}';

        $this->postRaw('/api/internal/student-sync/preview', $body, $this->signedHeaders(
            'POST',
            '/api/internal/student-sync/preview',
            $body,
            'nonce-expired',
            timestamp: now()->subSeconds(301)->timestamp,
        ))->assertUnauthorized();
    }

    public function test_repeated_nonce_is_rejected(): void
    {
        $this->enableReceiver();
        $body = $this->validPreviewBody();
        $headers = $this->signedHeaders(
            'POST',
            '/api/internal/student-sync/preview',
            $body,
            'nonce-repeated',
        );

        $this->postRaw('/api/internal/student-sync/preview', $body, $headers)->assertOk();
        $this->postRaw('/api/internal/student-sync/preview', $body, $headers)->assertUnauthorized();
        $this->assertDatabaseCount('student_sync_nonces', 1);
    }

    public function test_future_timestamp_nonce_remains_replay_protected_for_its_full_validity_window(): void
    {
        Carbon::setTestNow('2026-08-20 12:00:00');
        $this->enableReceiver();
        $body = $this->validPreviewBody();
        $headers = $this->signedHeaders(
            'POST',
            '/api/internal/student-sync/preview',
            $body,
            'nonce-future-replay',
            timestamp: now()->addSeconds(240)->timestamp,
        );

        $this->postRaw('/api/internal/student-sync/preview', $body, $headers)->assertOk();

        Carbon::setTestNow(now()->addSeconds(301));

        $this->postRaw('/api/internal/student-sync/preview', $body, $headers)->assertUnauthorized();
        $this->assertDatabaseCount('student_sync_nonces', 1);
    }

    public function test_short_receiver_secret_fails_closed(): void
    {
        $this->enableReceiver();
        config(['student_sync.receiver.secret' => 'too-short']);
        $body = '{"students":[1]}';

        $this->postRaw('/api/internal/student-sync/preview', $body, $this->signedHeaders(
            'POST',
            '/api/internal/student-sync/preview',
            $body,
            'nonce-short',
        ))->assertUnauthorized();
    }

    public function test_disabled_receiver_fails_closed(): void
    {
        $this->enableReceiver();
        config(['student_sync.receiver.enabled' => false]);
        $body = '{"students":[1]}';

        $this->postRaw('/api/internal/student-sync/preview', $body, $this->signedHeaders(
            'POST',
            '/api/internal/student-sync/preview',
            $body,
            'nonce-disabled',
        ))->assertUnauthorized();
    }

    public function test_rate_limiter_cannot_be_bypassed_by_rotating_claimed_client_ids(): void
    {
        $this->enableReceiver();
        $body = '{"students":[1]}';

        foreach (range(1, 20) as $attempt) {
            $headers = $this->signedHeaders(
                'POST',
                '/api/internal/student-sync/preview',
                $body,
                'nonce-rotated-'.$attempt,
            );
            $headers['X-Student-Sync-Client'] = 'untrusted-'.$attempt;

            $this->postRaw('/api/internal/student-sync/preview', $body, $headers)
                ->assertUnauthorized();
        }

        $headers = $this->signedHeaders(
            'POST',
            '/api/internal/student-sync/preview',
            $body,
            'nonce-rotated-21',
        );
        $headers['X-Student-Sync-Client'] = 'untrusted-21';

        $this->postRaw('/api/internal/student-sync/preview', $body, $headers)
            ->assertStatus(429);
    }

    public function test_receiver_routes_are_named_and_rate_limited(): void
    {
        $this->assertSame(
            '/api/internal/student-sync/preview',
            route('api.internal.student-sync.preview', [], false),
        );
        $this->assertSame(
            '/api/internal/student-sync/apply',
            route('api.internal.student-sync.apply', [], false),
        );

        $this->enableReceiver();
        $body = $this->validPreviewBody();

        foreach (range(1, 20) as $attempt) {
            $this->postRaw('/api/internal/student-sync/preview', $body, $this->signedHeaders(
                'POST',
                '/api/internal/student-sync/preview',
                $body,
                'nonce-rate-'.$attempt,
            ))->assertOk();
        }

        $this->postRaw('/api/internal/student-sync/preview', $body, $this->signedHeaders(
            'POST',
            '/api/internal/student-sync/preview',
            $body,
            'nonce-rate-21',
        ))->assertStatus(429);
    }

    private function postRaw(string $path, string $body, array $headers)
    {
        return $this->call(
            'POST',
            $path,
            [],
            [],
            [],
            $this->serverHeaders($headers),
            $body,
        );
    }

    private function validPreviewBody(): string
    {
        $students = [[
            'source_id' => 999999,
            'identity' => ['nipd' => 'MISSING-PREVIEW-STUDENT'],
            'fields' => ['nama' => 'Signature Test Student'],
            'source_checksum' => hash('sha256', 'signature-test-source'),
        ]];

        return json_encode([
            'payload_checksum' => StudentSyncPreviewService::payloadChecksum($students),
            'students' => $students,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function enableReceiver(): void
    {
        config([
            'student_sync.receiver.enabled' => true,
            'student_sync.receiver.client_id' => 'school-local',
            'student_sync.receiver.secret' => str_repeat('s', 32),
            'student_sync.security.clock_skew_seconds' => 300,
        ]);
    }

    /** @return array<string, string> */
    private function signedHeaders(
        string $method,
        string $path,
        string $body,
        string $nonce,
        ?int $timestamp = null,
        ?string $secret = null,
    ): array {
        $timestamp ??= now()->timestamp;
        $secret ??= str_repeat('s', 32);
        $bodyHash = hash('sha256', $body);
        $canonical = implode("\n", [
            strtoupper($method),
            $path,
            (string) $timestamp,
            $nonce,
            'idem-123',
            $bodyHash,
        ]);

        return [
            'X-Student-Sync-Client' => 'school-local',
            'X-Student-Sync-Timestamp' => (string) $timestamp,
            'X-Student-Sync-Nonce' => $nonce,
            'X-Student-Sync-Idempotency-Key' => 'idem-123',
            'X-Student-Sync-Body-SHA256' => $bodyHash,
            'X-Student-Sync-Signature' => hash_hmac('sha256', $canonical, $secret),
            'Content-Type' => 'application/json',
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
}
