<?php

namespace App\Exports;

use App\Models\Proker;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class ProkerPeriodExport implements FromArray, ShouldAutoSize
{
    public function __construct(
        protected int $periodeTahun,
    ) {}

    /**
     * @return array<int, array<int, mixed>>
     */
    public function array(): array
    {
        $monthColumns = $this->getMonthColumns();
        $rows = [
            [
                'periode_tahun',
                'periode_label',
                'point_dari',
                'nomor_urut',
                'nama_proker',
                'bidang',
                'penanggung_jawab',
                ...array_map(
                    fn (array $month): string => $month['column'],
                    $monthColumns
                ),
                'waktu_pelaksanaan',
                'rab_global',
                'keterangan',
                'status',
                'progress_persen',
                'prioritas',
                'target_mulai',
                'target_selesai',
                'last_monitored_at',
                'deskripsi',
                'output_target',
                'evaluasi_akhir',
                'tindak_lanjut_umum',
            ],
        ];

        $prokers = Proker::query()
            ->with(['bidang:id,nama'])
            ->where('periode_tahun', $this->periodeTahun)
            ->orderBy('bidang_id')
            ->orderBy('nomor_urut')
            ->orderBy('nama')
            ->get();

        foreach ($prokers as $proker) {
            $jadwalBulanan = (array) ($proker->jadwal_bulanan ?? []);

            $rows[] = [
                $proker->periode_tahun,
                $proker->periode_label,
                $proker->point_dari,
                $proker->nomor_urut,
                $proker->nama,
                $proker->bidang?->nama,
                $proker->penanggung_jawab,
                ...array_map(
                    fn (array $month): ?string => $jadwalBulanan[$month['label']] ?? null,
                    $monthColumns
                ),
                $proker->waktu_pelaksanaan,
                $proker->rab_global,
                $proker->keterangan,
                $proker->status,
                $proker->progress_persen,
                $proker->prioritas,
                $proker->target_mulai?->toDateString(),
                $proker->target_selesai?->toDateString(),
                $proker->last_monitored_at?->format('Y-m-d H:i:s'),
                $proker->deskripsi,
                $proker->output_target,
                $proker->evaluasi_akhir,
                $proker->tindak_lanjut_umum,
            ];
        }

        return $rows;
    }

    /**
     * @return array<int, array{column:string,label:string}>
     */
    protected function getMonthColumns(): array
    {
        $columns = [];
        $start = Carbon::create($this->periodeTahun, 6, 1)->startOfMonth();

        for ($offset = 0; $offset < 14; $offset++) {
            $month = $start->copy()->addMonths($offset);

            $columns[] = [
                'column' => 'jadwal_'.strtolower($month->format('M')).'_'.$month->format('Y'),
                'label' => $month->format('M-y'),
            ];
        }

        return $columns;
    }
}
