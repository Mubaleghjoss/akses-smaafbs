<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_sync_runs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('operation');
            $table->string('client_id');
            $table->foreignId('user_id')->nullable();
            $table->string('status');
            $table->string('idempotency_key')->nullable()->unique();
            $table->string('payload_checksum');
            $table->json('counts')->nullable();
            $table->json('field_summary')->nullable();
            $table->json('result_summary')->nullable();
            $table->string('backup_path')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::create('student_sync_previews', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('client_id');
            $table->string('payload_checksum');
            $table->longText('encrypted_payload');
            $table->timestamp('expires_at');
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();
        });

        Schema::create('student_sync_nonces', function (Blueprint $table): void {
            $table->id();
            $table->string('client_id');
            $table->string('nonce');
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->unique(['client_id', 'nonce']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_sync_nonces');
        Schema::dropIfExists('student_sync_previews');
        Schema::dropIfExists('student_sync_runs');
    }
};
