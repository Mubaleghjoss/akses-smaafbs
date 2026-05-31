<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('boarding_hafalan_points')) {
            return;
        }

        if (! Schema::hasColumn('boarding_hafalan_points', 'materi_scope')) {
            Schema::table('boarding_hafalan_points', function (Blueprint $table): void {
                $table->string('materi_scope', 20)->default('boarding')->after('id')->index();
            });
        }

        DB::table('boarding_hafalan_points')
            ->whereNull('materi_scope')
            ->orWhere('materi_scope', '')
            ->update(['materi_scope' => 'boarding']);

        $now = now();

        foreach ($this->mtMaterials() as $material) {
            DB::table('boarding_hafalan_points')->updateOrInsert(
                [
                    'materi_scope' => 'mt',
                    'materi_key' => $material['materi_key'],
                    'jenis' => $material['jenis'],
                    'nama_point' => $material['nama_point'],
                ],
                [
                    'urutan' => $material['urutan'],
                    'is_active' => true,
                    'updated_by' => null,
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('boarding_hafalan_points')) {
            return;
        }

        DB::table('boarding_hafalan_points')
            ->where('materi_scope', 'mt')
            ->delete();

        if (Schema::hasColumn('boarding_hafalan_points', 'materi_scope')) {
            Schema::table('boarding_hafalan_points', function (Blueprint $table): void {
                $table->dropColumn('materi_scope');
            });
        }
    }

    /**
     * @return array<int, array{materi_key: string, jenis: string, nama_point: string, urutan: int}>
     */
    private function mtMaterials(): array
    {
        return [
            ['materi_key' => 'mt_makna_hadits', 'jenis' => 'mt_makna_hadits', 'nama_point' => 'Muslim Jilid 1', 'urutan' => 1],
            ['materi_key' => 'mt_makna_hadits', 'jenis' => 'mt_makna_hadits', 'nama_point' => 'Muslim Jilid 2', 'urutan' => 2],
            ['materi_key' => 'mt_makna_hadits', 'jenis' => 'mt_makna_hadits', 'nama_point' => 'Muslim Jilid 3', 'urutan' => 3],
            ['materi_key' => 'mt_makna_hadits', 'jenis' => 'mt_makna_hadits', 'nama_point' => 'Muslim Jilid 4', 'urutan' => 4],
            ['materi_key' => 'mt_tambahan', 'jenis' => 'mt_praktek', 'nama_point' => 'Tugas Praktek', 'urutan' => 10],
            ['materi_key' => 'mt_hafalan', 'jenis' => 'mt_hafalan', 'nama_point' => 'Hafalan Surat Quran Juz 1', 'urutan' => 20],
            ['materi_key' => 'mt_hafalan', 'jenis' => 'mt_hafalan', 'nama_point' => 'Hafalan Dalil 29 Karakter Luhur', 'urutan' => 21],
            ['materi_key' => 'mt_catatan_saran', 'jenis' => 'mt_catatan_saran', 'nama_point' => 'Kedisiplinan', 'urutan' => 30],
            ['materi_key' => 'mt_catatan_saran', 'jenis' => 'mt_catatan_saran', 'nama_point' => 'Ketertiban', 'urutan' => 31],
            ['materi_key' => 'mt_catatan_saran', 'jenis' => 'mt_catatan_saran', 'nama_point' => 'Akhlak', 'urutan' => 32],
            ['materi_key' => 'mt_catatan_saran', 'jenis' => 'mt_catatan_saran', 'nama_point' => 'Kesemangatan', 'urutan' => 33],
        ];
    }
};
