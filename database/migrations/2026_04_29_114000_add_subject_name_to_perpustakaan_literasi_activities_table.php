<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('perpustakaan_literasi_activities')
            || Schema::hasColumn('perpustakaan_literasi_activities', 'subject_name')) {
            return;
        }

        Schema::table('perpustakaan_literasi_activities', function (Blueprint $table): void {
            $table->string('subject_name', 150)->nullable()->index()->after('book_author_snapshot');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('perpustakaan_literasi_activities')
            || ! Schema::hasColumn('perpustakaan_literasi_activities', 'subject_name')) {
            return;
        }

        Schema::table('perpustakaan_literasi_activities', function (Blueprint $table): void {
            $table->dropColumn('subject_name');
        });
    }
};
