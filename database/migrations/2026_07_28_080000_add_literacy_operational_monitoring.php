<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('perpustakaan_literasi_submission_events')) {
            Schema::create('perpustakaan_literasi_submission_events', function (Blueprint $table): void {
                $table->id();
                $table->string('event_code', 40)->index();
                $table->unsignedBigInteger('material_id')->nullable()->index();
                $table->unsignedBigInteger('response_id')->nullable()->index();
                $table->unsignedBigInteger('data_siswa_id')->nullable()->index();
                $table->unsignedBigInteger('ticket_id')->nullable()->index();
                $table->unsignedSmallInteger('http_status')->nullable();
                $table->json('retry_statuses')->nullable();
                $table->json('context')->nullable();
                $table->timestamp('occurred_at')->index();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('perpustakaan_literasi_network_checks')) {
            Schema::create('perpustakaan_literasi_network_checks', function (Blueprint $table): void {
                $table->id();
                $table->string('source', 40)->default('school')->index();
                $table->string('status', 20)->index();
                $table->boolean('dns_ok')->default(false);
                $table->boolean('tcp_ok')->default(false);
                $table->unsignedSmallInteger('http_status')->nullable();
                $table->unsignedInteger('duration_ms')->nullable();
                $table->unsignedInteger('consecutive_failures')->default(0);
                $table->string('error_code', 80)->nullable();
                $table->json('context')->nullable();
                $table->timestamp('checked_at')->index();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('perpustakaan_literasi_submission_queue_states')) {
            Schema::table('perpustakaan_literasi_submission_queue_states', function (Blueprint $table): void {
                if (! Schema::hasColumn('perpustakaan_literasi_submission_queue_states', 'scheduler_heartbeat_at')) {
                    $table->timestamp('scheduler_heartbeat_at')->nullable();
                }
                if (! Schema::hasColumn('perpustakaan_literasi_submission_queue_states', 'worker_started_at')) {
                    $table->timestamp('worker_started_at')->nullable();
                }
                if (! Schema::hasColumn('perpustakaan_literasi_submission_queue_states', 'worker_finished_at')) {
                    $table->timestamp('worker_finished_at')->nullable();
                }
                if (! Schema::hasColumn('perpustakaan_literasi_submission_queue_states', 'worker_status')) {
                    $table->string('worker_status', 30)->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('perpustakaan_literasi_submission_queue_states')) {
            $columns = collect([
                'scheduler_heartbeat_at',
                'worker_started_at',
                'worker_finished_at',
                'worker_status',
            ])->filter(fn (string $column): bool => Schema::hasColumn(
                'perpustakaan_literasi_submission_queue_states',
                $column,
            ))->all();

            if ($columns !== []) {
                Schema::table('perpustakaan_literasi_submission_queue_states', function (Blueprint $table) use ($columns): void {
                    $table->dropColumn($columns);
                });
            }
        }

        Schema::dropIfExists('perpustakaan_literasi_network_checks');
        Schema::dropIfExists('perpustakaan_literasi_submission_events');
    }
};
