<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('perpustakaan_literasi_responses')) {
            Schema::table('perpustakaan_literasi_responses', function (Blueprint $table): void {
                if (! Schema::hasColumn('perpustakaan_literasi_responses', 'similarity_analysis_status')) {
                    $table->string('similarity_analysis_status', 20)->default('completed')->index();
                }

                if (! Schema::hasColumn('perpustakaan_literasi_responses', 'similarity_analysis_version')) {
                    $table->unsignedInteger('similarity_analysis_version')->default(0);
                }

                if (! Schema::hasColumn('perpustakaan_literasi_responses', 'similarity_analysis_queued_at')) {
                    $table->timestamp('similarity_analysis_queued_at')->nullable();
                }

                if (! Schema::hasColumn('perpustakaan_literasi_responses', 'similarity_analyzed_at')) {
                    $table->timestamp('similarity_analyzed_at')->nullable();
                }

                if (! Schema::hasColumn('perpustakaan_literasi_responses', 'similarity_analysis_error')) {
                    $table->string('similarity_analysis_error', 1000)->nullable();
                }
            });
        }

        if (! Schema::hasTable('perpustakaan_literasi_submission_queue_states')) {
            Schema::create('perpustakaan_literasi_submission_queue_states', function (Blueprint $table): void {
                $table->string('scope', 60)->primary();
                $table->unsignedInteger('average_duration_ms')->default(2000);
                $table->timestamps();
            });

            DB::table('perpustakaan_literasi_submission_queue_states')->insert([
                'scope' => 'literacy',
                'average_duration_ms' => 2000,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if (! Schema::hasTable('perpustakaan_literasi_submission_tickets')) {
            Schema::create('perpustakaan_literasi_submission_tickets', function (Blueprint $table): void {
                $table->id();
                $table->string('public_token', 64)->unique();
                $table->string('scope', 60)->default('literacy')->index();
                $table->string('owner_hash', 64)->index();
                $table->string('operation_key', 150)->index();
                $table->string('operation', 20);
                $table->unsignedBigInteger('material_id')->nullable()->index();
                $table->unsignedBigInteger('response_id')->nullable()->index();
                $table->unsignedBigInteger('data_siswa_id')->nullable()->index();
                $table->string('status', 20)->default('waiting')->index();
                $table->timestamp('requested_at')->index();
                $table->timestamp('admitted_at')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamp('expires_at')->index();
                $table->unsignedBigInteger('result_response_id')->nullable();
                $table->timestamps();

                $table->index(['scope', 'status', 'requested_at'], 'perpus_lit_ticket_fifo_idx');
                $table->index(['owner_hash', 'operation_key', 'status'], 'perpus_lit_ticket_owner_op_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('perpustakaan_literasi_submission_tickets');
        Schema::dropIfExists('perpustakaan_literasi_submission_queue_states');

        if (Schema::hasTable('perpustakaan_literasi_responses')) {
            Schema::table('perpustakaan_literasi_responses', function (Blueprint $table): void {
                $columns = [
                    'similarity_analysis_status',
                    'similarity_analysis_version',
                    'similarity_analysis_queued_at',
                    'similarity_analyzed_at',
                    'similarity_analysis_error',
                ];

                foreach ($columns as $column) {
                    if (Schema::hasColumn('perpustakaan_literasi_responses', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
