<?php

namespace App\Exports;

use App\Models\BoardingBacaanAssessment;
use App\Models\BoardingPencapaian;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class BoardingBacaanAssessmentsExport implements FromArray, ShouldAutoSize, WithHeadings, WithTitle
{
    public function __construct(
        protected BoardingPencapaian $pencapaian,
    ) {}

    public function title(): string
    {
        return 'Tabel Bacaan';
    }

    public function headings(): array
    {
        return [
            'No',
            'Tanggal',
            'Murid',
            'Rombel',
            "Kelas Bacaan Qur'an",
            'PP',
            'KL',
            'TJ',
            'MJ',
            'Penyimak',
            'Catatan',
        ];
    }

    public function array(): array
    {
        $this->pencapaian->loadMissing('siswa:id,nama,rombel_saat_ini');

        return BoardingBacaanAssessment::query()
            ->where('boarding_pencapaian_id', $this->pencapaian->getKey())
            ->with('reviewerUser:id,name')
            ->orderByDesc('assessed_at')
            ->orderByDesc('id')
            ->get()
            ->values()
            ->map(fn (BoardingBacaanAssessment $assessment, int $index): array => [
                $index + 1,
                $assessment->assessed_at?->format('d M Y'),
                $this->pencapaian->siswa?->nama,
                $this->pencapaian->siswa?->rombel_saat_ini,
                BoardingBacaanAssessment::classLabel($assessment->kelas_bacaan),
                BoardingBacaanAssessment::gradeLabel($assessment->pp_grade),
                BoardingBacaanAssessment::gradeLabel($assessment->kl_grade),
                BoardingBacaanAssessment::gradeLabel($assessment->tj_grade),
                BoardingBacaanAssessment::gradeLabel($assessment->mj_grade),
                $assessment->reviewerUser?->name ?: ($assessment->reviewer_name ?: '-'),
                $assessment->notes,
            ])
            ->all();
    }
}
