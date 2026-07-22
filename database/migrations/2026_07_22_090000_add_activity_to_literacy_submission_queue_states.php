<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('perpustakaan_literasi_submission_queue_states')
            || Schema::hasColumn('perpustakaan_literasi_submission_queue_states', 'last_submission_activity_at')) {
            return;
        }

        Schema::table('perpustakaan_literasi_submission_queue_states', function (Blueprint $table): void {
            $table->timestamp('last_submission_activity_at')->nullable();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('perpustakaan_literasi_submission_queue_states')
            || ! Schema::hasColumn('perpustakaan_literasi_submission_queue_states', 'last_submission_activity_at')) {
            return;
        }

        Schema::table('perpustakaan_literasi_submission_queue_states', function (Blueprint $table): void {
            $table->dropColumn('last_submission_activity_at');
        });
    }
};
