<?php

namespace App\Exports;

use App\Exports\Sheets\AssessmentArraySheetExport;
use App\Models\Assessment\AssessmentPeriodHomeroom;
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

        return [
            new AssessmentArraySheetExport([
                ['id_siswa_periode', 'nisn', 'nama_siswa', 'kelas', 'nama_ekskul', 'predikat', 'catatan'],
                ...$students->map(fn ($student): array => [
                    $student->getKey(), $student->nisn_snapshot, $student->student_name_snapshot,
                    $student->rombel_name_snapshot, '', '', '',
                ])->all(),
            ], 'TEMPLATE_EKSKUL'),
            new AssessmentArraySheetExport([
                ['PANDUAN IMPORT EKSTRAKURIKULER'],
                ['Satu baris mewakili satu ekstrakurikuler siswa; tambahkan baris dengan id_siswa_periode yang sama untuk lebih dari satu ekskul.'],
                ['Isi nama_ekskul dan predikat A, B, C, atau D. Baris yang seluruh isian ekskulnya kosong diabaikan.'],
                ['Hanya siswa pada kelas template ini yang dapat diimpor. Isian ekskul siswa yang muncul pada file menggantikan daftar ekskul manualnya; kehadiran tidak diubah.'],
            ], 'PANDUAN', freezeHeader: false),
        ];
    }
}
