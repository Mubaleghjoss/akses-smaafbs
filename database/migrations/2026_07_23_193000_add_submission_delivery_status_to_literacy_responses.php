<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('perpustakaan_literasi_responses')) {
            return;
        }

        Schema::table('perpustakaan_literasi_responses', function (Blueprint $table): void {
            if (! Schema::hasColumn('perpustakaan_literasi_responses', 'submission_delivery_code')) {
                $table->string('submission_delivery_code', 24)->nullable()->index();
            }

            if (! Schema::hasColumn('perpustakaan_literasi_responses', 'submission_queue_wait_seconds')) {
                $table->unsignedInteger('submission_queue_wait_seconds')->default(0);
            }

            if (! Schema::hasColumn('perpustakaan_literasi_responses', 'submission_retry_statuses')) {
                $table->json('submission_retry_statuses')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('perpustakaan_literasi_responses')) {
            return;
        }

        Schema::table('perpustakaan_literasi_responses', function (Blueprint $table): void {
            $columns = [
                'submission_delivery_code',
                'submission_queue_wait_seconds',
                'submission_retry_statuses',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('perpustakaan_literasi_responses', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
