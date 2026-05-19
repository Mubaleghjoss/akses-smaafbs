<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('perpustakaan_literasi_activities')
            || Schema::hasColumn('perpustakaan_literasi_activities', 'participant_id')) {
            return;
        }

        Schema::table('perpustakaan_literasi_activities', function (Blueprint $table): void {
            $table->unsignedBigInteger('participant_id')->nullable()->index()->after('purpose');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('perpustakaan_literasi_activities')
            || ! Schema::hasColumn('perpustakaan_literasi_activities', 'participant_id')) {
            return;
        }

        Schema::table('perpustakaan_literasi_activities', function (Blueprint $table): void {
            $table->dropColumn('participant_id');
        });
    }
};
