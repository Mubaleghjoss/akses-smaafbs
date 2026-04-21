<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('boarding_perizinan_siswas')) {
            return;
        }

        $hasWaktuIzin = Schema::hasColumn('boarding_perizinan_siswas', 'waktu_izin');
        $hasDetailKembali = Schema::hasColumn('boarding_perizinan_siswas', 'detail_kembali');

        if (! $hasWaktuIzin) {
            Schema::table('boarding_perizinan_siswas', function (Blueprint $table): void {
                $table->time('waktu_izin')->nullable();
            });
        }

        if (! $hasDetailKembali) {
            Schema::table('boarding_perizinan_siswas', function (Blueprint $table): void {
                $table->text('detail_kembali')->nullable();
            });
        }

        if (Schema::hasColumn('boarding_perizinan_siswas', 'waktu_jemput') && Schema::hasColumn('boarding_perizinan_siswas', 'waktu_izin')) {
            DB::table('boarding_perizinan_siswas')
                ->whereNull('waktu_izin')
                ->whereNotNull('waktu_jemput')
                ->update(['waktu_izin' => DB::raw('waktu_jemput')]);
        }

        if (Schema::hasColumn('boarding_perizinan_siswas', 'catatan_kembali') && Schema::hasColumn('boarding_perizinan_siswas', 'detail_kembali')) {
            DB::table('boarding_perizinan_siswas')
                ->whereNull('detail_kembali')
                ->whereNotNull('catatan_kembali')
                ->update(['detail_kembali' => DB::raw('catatan_kembali')]);
        }
    }

    public function down(): void
    {
        // Compatibility migration intentionally keeps canonical columns in place.
    }
};
