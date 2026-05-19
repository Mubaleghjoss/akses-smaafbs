<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('perpustakaan_literasi_questions')
            || Schema::hasColumn('perpustakaan_literasi_questions', 'min_characters')) {
            return;
        }

        Schema::table('perpustakaan_literasi_questions', function (Blueprint $table): void {
            $table->unsignedInteger('min_characters')->default(20)->after('google_drive_url');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('perpustakaan_literasi_questions')
            || ! Schema::hasColumn('perpustakaan_literasi_questions', 'min_characters')) {
            return;
        }

        Schema::table('perpustakaan_literasi_questions', function (Blueprint $table): void {
            $table->dropColumn('min_characters');
        });
    }
};
