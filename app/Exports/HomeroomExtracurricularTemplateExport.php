<?php

namespace App\Exports;

use App\Exports\Sheets\AssessmentArraySheetExport;
use App\Models\Assessment\AssessmentPeriodHomeroom;
use App\Models\Assessment\HomeroomReport;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class HomeroomExtracurricularTemplateExport implements WithMultipleSheets
{
    public function __construct(private readonly AssessmentPeriodHomeroom $homeroom) {}

    public function sheets(): array
    {
        $students = $this->homeroom->period->students()
            ->where('assessment_period_rombel_id', $this->homeroom->assessment_period_rombel_id)
            ->eligibleForAssessment()
            ->orderBy('student_name_snapshot')
            ->get();

        $reports = HomeroomReport::query()
            ->where('assessment_period_id', $this->homeroom->assessment_period_id)
            ->whereIn('assessment_period_student_id', $students->modelKeys())
            ->get()
            ->keyBy('assessment_period_student_id');
        $header = ['id_siswa_periode', 'nisn', 'nama_siswa', 'kelas', 'sakit', 'izin', 'alpa', 'nama_ekskul', 'predikat', 'catatan'];
        $rows = $students->flatMap(function ($student) use ($reports): array {
            $report = $reports->get($student->getKey());
            $items = collect($report?->extracurricular_data ?: [])->filter(fn ($item): bool => filled(data_get($item, 'name')))->values();
            $items = $items->isNotEmpty() ? $items : collect([[]]);

            return $items->map(fn ($item): array => [
                $student->getKey(), $student->nisn_snapshot, $student->student_name_snapshot, $student->rombel_name_snapshot,
                (int) ($report?->sick_days ?? 0), (int) ($report?->permission_days ?? 0), (int) ($report?->absent_days ?? 0),
                data_get($item, 'name', ''), data_get($item, 'description', data_get($item, 'predicate', '')), data_get($item, 'note', ''),
            ])->all();
        })->all();

        return [
            new AssessmentArraySheetExport([$header, ...$rows], 'TEMPLATE_EKSKUL'),
            new AssessmentArraySheetExport([
                ['PANDUAN IMPORT REKAP WALI KELAS'],
                ['Satu baris mewakili satu ekstrakurikuler siswa; tambahkan baris dengan id_siswa_periode yang sama untuk lebih dari satu ekskul.'],
                ['Isi sakit, izin, dan alpa dengan angka 0-366. Isian ekskul kosong berarti daftar ekskul siswa tersebut dikosongkan.'],
                ['Hanya siswa pada kelas template ini yang diimpor. Kehadiran dan daftar ekskul siswa yang muncul pada file menggantikan data sebelumnya.'],
            ], 'PANDUAN', freezeHeader: false),
        ];
    }
}
