<?php

namespace App\Support\Assessment;

use App\Models\Assessment\AssessmentPeriodHomeroom;
use App\Models\Assessment\HomeroomReport;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

final class HomeroomExtracurricularImport
{
    /** @return array{students_updated:int,items_imported:int} */
    public function import(AssessmentPeriodHomeroom $homeroom, string $path, int $actorId): array
    {
        $rows = Excel::toArray([], $path)[0] ?? [];
        if (count($rows) < 1) {
            throw new HomeroomExtracurricularImportException(['File Excel tidak memiliki sheet data.']);
        }

        $headers = array_map(fn ($value): string => strtolower(trim((string) $value)), array_shift($rows));
        $required = ['id_siswa_periode', 'nisn', 'nama_siswa', 'kelas', 'nama_ekskul', 'predikat', 'catatan'];
        if ($headers !== $required) {
            throw new HomeroomExtracurricularImportException(['Header template tidak sesuai. Unduh template kelas ini kembali lalu isi tanpa mengubah header.']);
        }

        $students = $homeroom->period->students()
            ->where('assessment_period_rombel_id', $homeroom->assessment_period_rombel_id)
            ->eligibleForAssessment()->get();
        $byId = $students->keyBy('id');
        $byNisn = $students->filter(fn ($student) => filled($student->nisn_snapshot))->keyBy('nisn_snapshot');
        $byNameAndClass = $students->groupBy(fn ($student): string => mb_strtolower(trim((string) $student->student_name_snapshot)).'|'.mb_strtolower(trim((string) $student->rombel_name_snapshot)));
        $itemsByStudent = [];
        $errors = [];

        foreach ($rows as $offset => $values) {
            $rowNumber = $offset + 2;
            $row = array_combine($required, array_pad(array_slice(array_values($values), 0, 7), 7, ''));
            $name = trim((string) $row['nama_ekskul']);
            $predicate = strtoupper(trim((string) $row['predikat']));
            $note = trim((string) $row['catatan']);
            if ($name === '' && $predicate === '' && $note === '') continue;
            if ($name === '' || ! in_array($predicate, ['A', 'B', 'C', 'D'], true)) {
                $errors[] = "Baris {$rowNumber}: nama ekstrakurikuler wajib diisi dan predikat harus A, B, C, atau D.";
                continue;
            }

            $student = filled($row['id_siswa_periode']) ? $byId->get((int) $row['id_siswa_periode']) : null;
            if (! $student && filled($row['nisn'])) $student = $byNisn->get(trim((string) $row['nisn']));
            if (! $student && filled($row['nama_siswa']) && filled($row['kelas'])) {
                $matches = $byNameAndClass->get(mb_strtolower(trim((string) $row['nama_siswa'])).'|'.mb_strtolower(trim((string) $row['kelas'])), collect());
                $student = $matches->count() === 1 ? $matches->first() : null;
            }
            if (! $student || trim((string) $row['kelas']) !== (string) $homeroom->rombel_name_snapshot) {
                $errors[] = "Baris {$rowNumber}: siswa tidak ditemukan pada kelas wali yang dipilih.";
                continue;
            }
            if (filled($row['nama_siswa']) && strcasecmp(trim((string) $row['nama_siswa']), (string) $student->student_name_snapshot) !== 0) {
                $errors[] = "Baris {$rowNumber}: nama siswa tidak cocok dengan data kelas.";
                continue;
            }
            $itemsByStudent[$student->getKey()][] = array_filter([
                'name' => $name, 'description' => $predicate, 'note' => $note !== '' ? $note : null,
            ], fn ($value) => $value !== null);
        }
        if ($errors) throw new HomeroomExtracurricularImportException($errors);

        DB::transaction(function () use ($homeroom, $itemsByStudent, $actorId): void {
            foreach ($itemsByStudent as $studentId => $items) {
                HomeroomReport::query()->updateOrCreate([
                    'assessment_period_id' => $homeroom->assessment_period_id,
                    'assessment_period_student_id' => $studentId,
                ], ['extracurricular_data' => $items, 'updated_by' => $actorId]);
            }
        });

        return ['students_updated' => count($itemsByStudent), 'items_imported' => array_sum(array_map('count', $itemsByStudent))];
    }
}

final class HomeroomExtracurricularImportException extends \RuntimeException
{
    /** @param array<int, string> $errors */
    public function __construct(public readonly array $errors)
    {
        parent::__construct($errors[0] ?? 'File Excel tidak valid.');
    }
}
