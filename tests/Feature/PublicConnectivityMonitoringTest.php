<?php

namespace Tests\Feature;

use App\Models\PerpustakaanLiterasiNetworkCheck;
use App\Models\PublicConnectivityEvent;
use App\Support\Perpustakaan\AnonymousConnectivity;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class PublicConnectivityMonitoringTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('public_connectivity_events');
        Schema::dropIfExists('perpustakaan_literasi_network_checks');

        Schema::create('public_connectivity_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('event_uuid')->unique();
            $table->string('event_type', 50);
            $table->string('route_group', 40);
            $table->char('client_hash', 64);
            $table->char('recovery_ip_hash', 64)->nullable();
            $table->string('network_scope', 20)->default('unknown');
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->string('service_worker_version', 30)->nullable();
            $table->timestamp('occurred_at');
            $table->timestamp('recovered_at')->nullable();
            $table->unsignedInteger('offline_duration_seconds')->nullable();
            $table->timestamps();
        });

        Schema::create('perpustakaan_literasi_network_checks', function (Blueprint $table): void {
            $table->id();
            $table->string('source', 40);
            $table->string('status', 20);
            $table->boolean('dns_ok');
            $table->boolean('tcp_ok');
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->unsignedInteger('consecutive_failures')->default(0);
            $table->string('error_code', 80)->nullable();
            $table->json('context')->nullable();
            $table->timestamp('checked_at');
            $table->timestamps();
        });
    }

    public function test_anonymous_events_are_hashed_classified_and_deduplicated(): void
    {
        config()->set('literacy.school_monitor.stale_minutes', 3);
        PerpustakaanLiterasiNetworkCheck::query()->create([
            'source' => 'school-test',
            'status' => 'ok',
            'dns_ok' => true,
            'tcp_ok' => true,
            'http_status' => 200,
            'duration_ms' => 100,
            'checked_at' => now(),
            'context' => [
                'source_ip_hash' => AnonymousConnectivity::hashIp('127.0.0.1'),
            ],
        ]);

        $clientId = (string) Str::uuid();
        $eventUuid = (string) Str::uuid();
        $payload = [
            'client_id' => $clientId,
            'events' => [[
                'event_uuid' => $eventUuid,
                'event_type' => PublicConnectivityEvent::TYPE_NETWORK_ERROR,
                'route_group' => 'literacy_material',
                'http_status' => 0,
                'service_worker_version' => 'public-shell-v7',
                'occurred_at' => now()->subSeconds(30)->toIso8601String(),
                'recovered_at' => now()->toIso8601String(),
            ]],
        ];

        $this->postJson(route('api.monitoring.public-connectivity'), $payload)
            ->assertStatus(202)
            ->assertJson(['accepted' => 1, 'received' => 1]);

        $event = PublicConnectivityEvent::query()->sole();
        $this->assertSame($eventUuid, $event->event_uuid);
        $this->assertSame('school', $event->network_scope);
        $this->assertSame(AnonymousConnectivity::hashClient($clientId), $event->client_hash);
        $this->assertNotSame($clientId, $event->client_hash);
        $this->assertSame(30, $event->offline_duration_seconds);

        $this->postJson(route('api.monitoring.public-connectivity'), $payload)
            ->assertStatus(202)
            ->assertJson(['accepted' => 0, 'received' => 1]);

        $this->assertSame(1, PublicConnectivityEvent::query()->count());
    }

    public function test_connectivity_endpoint_rejects_sensitive_or_unknown_payload_shapes(): void
    {
        $this->postJson(route('api.monitoring.public-connectivity'), [
            'client_id' => (string) Str::uuid(),
            'events' => [[
                'event_uuid' => (string) Str::uuid(),
                'event_type' => 'answer_payload',
                'route_group' => '/perpustakaan/program-literasi-numerasi/example?nisn=secret',
                'student_name' => 'Tidak boleh tersimpan',
                'occurred_at' => now()->toIso8601String(),
            ]],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['events.0.event_type', 'events.0.route_group']);

        $this->assertSame(0, PublicConnectivityEvent::query()->count());
    }
}
