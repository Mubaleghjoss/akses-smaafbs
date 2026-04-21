<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('data_siswa')) {
            return;
        }

        $hasKategori = Schema::hasColumn('data_siswa', 'kategori_non_aktif');
        $hasAlasan = Schema::hasColumn('data_siswa', 'alasan_non_aktif');

        if ($hasKategori && $hasAlasan) {
            return;
        }

        Schema::table('data_siswa', function (Blueprint $table) use ($hasKategori, $hasAlasan): void {
            if (! $hasKategori) {
                $table->string('kategori_non_aktif', 50)->nullable()->after('status');
            }

            if (! $hasAlasan) {
                $table->text('alasan_non_aktif')->nullable()->after('kategori_non_aktif');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('data_siswa')) {
            return;
        }

        $hasKategori = Schema::hasColumn('data_siswa', 'kategori_non_aktif');
        $hasAlasan = Schema::hasColumn('data_siswa', 'alasan_non_aktif');

        if (! $hasKategori && ! $hasAlasan) {
            return;
        }

        Schema::table('data_siswa', function (Blueprint $table) use ($hasKategori, $hasAlasan): void {
            if ($hasAlasan) {
                $table->dropColumn('alasan_non_aktif');
            }

            if ($hasKategori) {
                $table->dropColumn('kategori_non_aktif');
            }
        });
    }
};
