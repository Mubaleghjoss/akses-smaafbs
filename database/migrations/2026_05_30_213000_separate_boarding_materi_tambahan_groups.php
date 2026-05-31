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

        foreach ($this->renamedDoaMaterials() as $oldName => $newName) {
            DB::table('boarding_hafalan_points')
                ->where('materi_key', 'materi_tambahan_hafalan')
                ->where('jenis', 'doa')
                ->where('nama_point', $oldName)
                ->update([
                    'nama_point' => $newName,
                    'updated_at' => $now,
                ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('boarding_hafalan_points')) {
            return;
        }

        $now = now();

        foreach (array_flip($this->renamedDoaMaterials()) as $newName => $oldName) {
            DB::table('boarding_hafalan_points')
                ->where('materi_key', 'materi_tambahan_hafalan')
                ->where('jenis', 'doa')
                ->where('nama_point', $newName)
                ->update([
                    'nama_point' => $oldName,
                    'updated_at' => $now,
                ]);
        }

        DB::table('boarding_hafalan_points')
            ->whereIn('materi_key', [
                'materi_tambahan_hafalan',
                'materi_tambahan_makna_quran',
                'materi_tambahan_makna_hadits',
            ])
            ->update([
                'materi_key' => 'materi_tambahan',
                'updated_at' => $now,
            ]);
    }

    /**
     * @return array<string, string>
     */
    private function renamedDoaMaterials(): array
    {
        return [
            'Sholat Dhuha' => 'Doa Sholat Dhuha',
            'Sholat Istiqoroh' => 'Doa Sholat Istiqoroh',
            'Sholat Hajat' => 'Doa Sholat Hajat',
            'Sholat Jenazah' => 'Doa Sholat Jenazah',
            'PR 13 dan keutamaannya' => 'Doa PR 13 dan keutamaannya',
        ];
    }
};
