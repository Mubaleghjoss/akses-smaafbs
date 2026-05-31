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
        $keys = [
            'materi_key' => 'materi_pengetesan_makna',
            'jenis' => 'pengetesan_makna',
            'nama_point' => 'Pengetesan Makna',
        ];

        if (Schema::hasColumn('boarding_hafalan_points', 'materi_scope')) {
            $keys = ['materi_scope' => 'boarding'] + $keys;
        }

        DB::table('boarding_hafalan_points')->updateOrInsert(
            $keys,
            [
                'urutan' => 1,
                'is_active' => true,
                'updated_by' => null,
                'updated_at' => $now,
                'created_at' => $now,
            ]
        );
    }

    public function down(): void
    {
        if (! Schema::hasTable('boarding_hafalan_points')) {
            return;
        }

        DB::table('boarding_hafalan_points')
            ->where('materi_key', 'materi_pengetesan_makna')
            ->where('jenis', 'pengetesan_makna')
            ->where('nama_point', 'Pengetesan Makna')
            ->delete();
    }
};
