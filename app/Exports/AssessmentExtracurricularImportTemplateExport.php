<?php

namespace App\Exports;

use App\Exports\Sheets\AssessmentArraySheetExport;
use App\Models\Assessment\AssessmentExtracurricular;
use App\Models\Assessment\AssessmentPeriod;
use App\Models\Assessment\AssessmentPeriodStudent;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class AssessmentExtracurricularImportTemplateExport implements WithMultipleSheets
{
    public function __construct(private readonly AssessmentPeriod $period) {}

    public function sheets(): array
    {
        $students = AssessmentPeriodStudent::query()
            ->where('assessment_period_id', $this->period->getKey())
            ->where('is_active', true)
            ->orderBy('rombel_name_snapshot')
            ->orderBy('student_name_snapshot')
            ->get(['nisn_snapshot', 'student_name_snapshot', 'rombel_name_snapshot']);

        $templateRows = [
            ['nisn', 'nama_siswa', 'kelas', 'nama_ekskul', 'predikat', 'catatan'],
            ...$students->map(fn (AssessmentPeriodStudent $student): array => [
                $student->nisn_snapshot,
                $student->student_name_snapshot,
                $student->rombel_name_snapshot,
                '',
                '',
                '',
            ])->all(),
        ];

        $activities = AssessmentExtracurricular::query()
            ->where('assessment_period_id', $this->period->getKey())
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name')
            ->all();

        return [
            new AssessmentArraySheetExport($templateRows, 'TEMPLATE_PESERTA'),
            new AssessmentArraySheetExport([
                ['PANDUAN IMPORT PESERTA EKSKUL ASTS'],
                ['Isi nama_ekskul untuk setiap siswa yang menjadi peserta.'],
                ['Predikat opsional: A, B, C, atau D. Predikat dan catatan yang diisi akan disimpan sebagai draf nilai.'],
                ['Nilai dengan status dikirim atau terverifikasi tidak akan ditimpa.'],
                ['Siswa dicocokkan memakai nisn; bila kosong, memakai nama_siswa dan kelas.'],
            ], 'PANDUAN', freezeHeader: false),
            new AssessmentArraySheetExport([
                ['NAMA_EKSKUL_AKTIF'],
                ...array_map(fn (string $name): array => [$name], $activities),
            ], 'REF_EKSKUL'),
        ];
    }
}
