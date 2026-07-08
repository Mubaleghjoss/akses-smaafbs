<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('perpustakaan_literasi_materials')
            || Schema::hasColumn('perpustakaan_literasi_materials', 'student_verification_enabled')) {
            return;
        }

        Schema::table('perpustakaan_literasi_materials', function (Blueprint $table): void {
            $column = $table->boolean('student_verification_enabled')->default(true);

            if (Schema::hasColumn('perpustakaan_literasi_materials', 'instructions')) {
                $column->after('instructions');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('perpustakaan_literasi_materials')
            || ! Schema::hasColumn('perpustakaan_literasi_materials', 'student_verification_enabled')) {
            return;
        }

        Schema::table('perpustakaan_literasi_materials', function (Blueprint $table): void {
            $table->dropColumn('student_verification_enabled');
        });
    }
};
