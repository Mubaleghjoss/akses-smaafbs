<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('public_connectivity_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('event_uuid')->unique();
            $table->string('event_type', 50)->index();
            $table->string('route_group', 40)->index();
            $table->char('client_hash', 64)->index();
            $table->char('recovery_ip_hash', 64)->nullable()->index();
            $table->string('network_scope', 20)->default('unknown')->index();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->string('service_worker_version', 30)->nullable();
            $table->timestamp('occurred_at')->index();
            $table->timestamp('recovered_at')->nullable()->index();
            $table->unsignedInteger('offline_duration_seconds')->nullable();
            $table->timestamps();

            $table->index(['occurred_at', 'network_scope'], 'connectivity_time_scope_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('public_connectivity_events');
    }
};
