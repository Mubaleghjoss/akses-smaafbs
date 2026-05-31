<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('boarding_hafalan_points')) {
            return;
        }

        $now = now();

        DB::table('boarding_hafalan_points')
            ->whereIn('materi_key', ['seleksi_saringan', 'materi_tambahan'])
            ->whereIn('jenis', ['surat', 'doa', 'dalil'])
            ->update([
                'materi_key' => 'materi_tambahan_hafalan',
                'updated_at' => $now,
            ]);

        DB::table('boarding_hafalan_points')
            ->where('materi_key', 'materi_tambahan')
            ->where('jenis', 'makna_quran')
            ->update([
                'materi_key' => 'materi_tambahan_makna_quran',
                'updated_at' => $now,
            ]);

        DB::table('boarding_hafalan_points')
            ->where('materi_key', 'materi_tambahan')
            ->where('jenis', 'makna_hadits')
            ->update([
                'materi_key' => 'materi_tambahan_makna_hadits',
                'updated_at' => $now,
            ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('boarding_hafalan_points')) {
            return;
        }

        DB::table('boarding_hafalan_points')
            ->whereIn('materi_key', [
                'materi_tambahan_hafalan',
                'materi_tambahan_makna_quran',
                'materi_tambahan_makna_hadits',
            ])
            ->update([
                'materi_key' => 'materi_tambahan',
                'updated_at' => now(),
            ]);
    }
};
