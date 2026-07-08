<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('perpustakaan_literasi_materials')) {
            return;
        }

        Schema::table('perpustakaan_literasi_materials', function (Blueprint $table): void {
            if (! Schema::hasColumn('perpustakaan_literasi_materials', 'instructions')) {
                $table->text('instructions')->nullable()->after('reading_content');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('perpustakaan_literasi_materials')
            || ! Schema::hasColumn('perpustakaan_literasi_materials', 'instructions')) {
            return;
        }

        Schema::table('perpustakaan_literasi_materials', function (Blueprint $table): void {
            $table->dropColumn('instructions');
        });
    }
};
