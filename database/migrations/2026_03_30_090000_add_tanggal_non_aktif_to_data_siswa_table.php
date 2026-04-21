<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('data_siswa') || Schema::hasColumn('data_siswa', 'tanggal_non_aktif')) {
            return;
        }

        Schema::table('data_siswa', function (Blueprint $table): void {
            $table->date('tanggal_non_aktif')->nullable()->after('alasan_non_aktif');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('data_siswa') || ! Schema::hasColumn('data_siswa', 'tanggal_non_aktif')) {
            return;
        }

        Schema::table('data_siswa', function (Blueprint $table): void {
            $table->dropColumn('tanggal_non_aktif');
        });
    }
};
