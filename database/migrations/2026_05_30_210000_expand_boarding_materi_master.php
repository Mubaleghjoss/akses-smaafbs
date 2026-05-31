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

        DB::table('boarding_hafalan_points')
            ->where('materi_key', 'seleksi_saringan')
            ->update(['materi_key' => 'materi_tambahan_hafalan']);

        $now = now();

        foreach ($this->boardingMaknaMaterials() as $material) {
            DB::table('boarding_hafalan_points')->updateOrInsert(
                [
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
            ->whereIn('jenis', ['makna_quran', 'makna_hadits'])
            ->delete();

        DB::table('boarding_hafalan_points')
            ->where('materi_key', 'materi_tambahan_hafalan')
            ->update(['materi_key' => 'seleksi_saringan']);
    }

    /**
     * @return array<int, array{materi_key: string, jenis: string, nama_point: string, urutan: int}>
     */
    private function boardingMaknaMaterials(): array
    {
        $materials = [];

        foreach (range(1, 30) as $juz) {
            $materials[] = [
                'materi_key' => 'materi_tambahan_makna_quran',
                'jenis' => 'makna_quran',
                'nama_point' => "Makna Al-Qur'an Juz {$juz}",
                'urutan' => 1000 + $juz,
            ];
        }

        $haditsAndMaterials = [
            'K. Sholah',
            'K. Nawafil',
            'K. Da\'wat',
            'K. Adab',
            'K. Jannah Wannar',
            'K. Janaiz',
            'K. Adillah',
            'K. Shoum',
            'K. Ahkam',
            'K. Manasik Waljihad',
            'K. Jihad',
            'K. Haji',
            'K. Manasikil Haji',
            'K. Imaroh',
            'Kanzil Umal',
            'K. Faroid',
            'K. Khotbah',
            'Materi Tata Krama',
            'Materi Bacaan',
            'Materi Pegon',
            'Materi Lambatan',
            'Materi Cepatan',
            'Materi Saringan',
            'K. Nikah',
            'K. Talaq',
            'K. Zakat',
        ];

        foreach ($haditsAndMaterials as $index => $label) {
            $materials[] = [
                'materi_key' => 'materi_tambahan_makna_hadits',
                'jenis' => 'makna_hadits',
                'nama_point' => $label,
                'urutan' => 1100 + $index,
            ];
        }

        return $materials;
    }
};
