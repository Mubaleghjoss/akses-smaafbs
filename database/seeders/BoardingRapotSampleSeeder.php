<?php

namespace Database\Seeders;

use App\Models\BoardingRapot;
use App\Models\DataSiswa;
use App\Models\User;
use Illuminate\Database\Seeder;

class BoardingRapotSampleSeeder extends Seeder
{
    public function run(): void
    {
        $student = DataSiswa::query()
            ->select(['id', 'nama', 'rombel_saat_ini'])
            ->withCount([
                'boardingKonselingMts',
                'boardingRapots',
            ])
            ->withExists([
                'boardingPencapaian',
                'boardingKeuanganSiswa',
            ])
            ->orderByDesc('boarding_pencapaian_exists')
            ->orderByDesc('boarding_keuangan_siswa_exists')
            ->orderByDesc('boarding_konseling_mts_count')
            ->orderByDesc('boarding_rapots_count')
            ->orderBy('nama')
            ->first();

        if (! $student) {
            $this->command?->warn('BoardingRapotSampleSeeder dilewati karena tidak ada data siswa.');

            return;
        }

        $pamongUserId = $this->resolvePamongUserId($student->getKey());
        $periodeTahun = now()->format('Y').'/'.now()->addYear()->format('Y');

        $rapot = BoardingRapot::query()->updateOrCreate(
            [
                'siswa_id' => $student->getKey(),
                'periode_tahun' => $periodeTahun,
                'semester' => 'genap',
            ],
            [
                'pamong_user_id' => $pamongUserId,
                'tanggal_rapot' => now()->toDateString(),
                'status_rapot' => 'siap_export',
                'predikat_boarding' => 'jayyid',
                'tempat_cetak' => 'Bogor',
            ],
        );

        $rapot->syncFromSources(overwriteNarratives: true);

        $this->command?->info(sprintf(
            'BoardingRapot sample siap: #%d untuk %s (%s).',
            $rapot->getKey(),
            $student->nama,
            $student->rombel_saat_ini ?: 'Tanpa rombel',
        ));
    }

    protected function resolvePamongUserId(int $studentId): ?int
    {
        $student = DataSiswa::query()
            ->with([
                'boardingPencapaian:id,siswa_id,pamong_user_id',
                'boardingKeuanganSiswa:id,siswa_id,pamong_user_id',
                'boardingKonselingMts' => fn ($query) => $query
                    ->select(['id', 'siswa_id', 'pamong_user_id'])
                    ->latest('tanggal_konseling')
                    ->latest('id')
                    ->limit(1),
            ])
            ->find($studentId);

        $candidateIds = collect([
            $student?->boardingPencapaian?->pamong_user_id,
            $student?->boardingKeuanganSiswa?->pamong_user_id,
            $student?->boardingKonselingMts->first()?->pamong_user_id,
        ])
            ->filter(fn ($value): bool => filled($value))
            ->map(fn ($value): int => (int) $value)
            ->unique()
            ->values();

        if ($candidateIds->isNotEmpty()) {
            return $candidateIds->first();
        }

        return User::boardingPamongQuery()->orderBy('name')->value('id')
            ?: User::query()->role('admin')->orderBy('name')->value('id');
    }
}
