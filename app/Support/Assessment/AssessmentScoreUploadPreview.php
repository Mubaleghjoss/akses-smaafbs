<?php

namespace App\Support\Assessment;

use App\Models\Assessment\AssessmentPeriodAssignment;
use App\Models\Assessment\AssessmentPeriodStudent;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Facades\Excel;

final class AssessmentScoreUploadPreview
{
    /**
     * @param  array<int, array{id:int,name:string,minimum_score:float,maximum_score:float}>  $components
     * @return array<int, array<string, mixed>>
     */
    public function parse(AssessmentPeriodAssignment $assignment, UploadedFile|string $file, array $components): array
    {
        $sheet = Excel::toArray([], $file)[0] ?? [];
        $headers = array_map(fn (mixed $value): string => $this->header($value), array_shift($sheet) ?? []);
        $required = ['id siswa periode', 'nisn', 'nama siswa', 'kelas'];

        if (array_diff($required, $headers) !== []) {
            return [[
                'row_number' => 1,
                'student_name' => '-',
                'status' => 'Error',
                'message' => 'Header template tidak sesuai. Unduh template kelas ini lalu isi kolom nilainya.',
                'student_id' => null,
                'scores' => [],
            ]];
        }

        $componentColumns = [];
        foreach ($components as $component) {
            $column = array_search($this->header($component['name']), $headers, true);
            if ($column === false) {
                return [[
                    'row_number' => 1,
                    'student_name' => '-',
                    'status' => 'Error',
                    'message' => "Kolom {$component['name']} tidak ditemukan.",
                    'student_id' => null,
                    'scores' => [],
                ]];
            }
            $componentColumns[(int) $component['id']] = $column;
        }

        $students = AssessmentPeriodStudent::query()
            ->where('assessment_period_id', $assignment->assessment_period_id)
            ->where('assessment_period_rombel_id', $assignment->assessment_period_rombel_id)
            ->where('is_active', true)
            ->eligibleForScoreEntry()
            ->get();
        $seen = [];
        $preview = [];

        foreach ($sheet as $index => $values) {
            $row = array_combine($headers, array_pad(array_slice($values, 0, count($headers)), count($headers), null));
            if (! array_filter($row, fn (mixed $value): bool => trim((string) $value) !== '')) {
                continue;
            }

            $student = $this->findStudent($students, $row);
            $message = null;
            $scores = [];
            if (! $student) {
                $message = 'Siswa tidak ditemukan pada kelas assignment ini.';
            } elseif (isset($seen[$student->getKey()])) {
                $message = 'Siswa yang sama muncul lebih dari sekali di file.';
            } else {
                $seen[$student->getKey()] = true;
                foreach ($components as $component) {
                    $value = trim((string) ($values[$componentColumns[$component['id']]] ?? ''));
                    if ($value === '') {
                        $scores[$component['id']] = '';

                        continue;
                    }
                    if (! is_numeric($value) || (float) $value < 0 || (float) $value > 100) {
                        $message = "Nilai {$component['name']} harus berupa angka antara 0 dan 100.";
                        break;
                    }
                    $scores[$component['id']] = (float) $value;
                }
            }

            $preview[] = [
                'row_number' => $index + 2,
                'student_name' => $student?->student_name_snapshot ?: trim((string) ($row['nama siswa'] ?? '-')),
                'status' => $message ? 'Error' : 'Valid',
                'message' => $message ?: 'Siap diterapkan ke formulir.',
                'student_id' => $student?->getKey(),
                'scores' => $message ? [] : $scores,
            ];
        }

        return $preview;
    }

    private function findStudent($students, array $row): ?AssessmentPeriodStudent
    {
        $id = trim((string) ($row['id siswa periode'] ?? ''));
        if ($id !== '') {
            return $students->firstWhere('id', (int) $id);
        }

        $nisn = trim((string) ($row['nisn'] ?? ''));
        if ($nisn !== '') {
            return $students->first(fn (AssessmentPeriodStudent $student): bool => (string) $student->nisn_snapshot === $nisn);
        }

        $name = mb_strtolower(trim((string) ($row['nama siswa'] ?? '')));
        $class = mb_strtolower(trim((string) ($row['kelas'] ?? '')));

        return $students->first(fn (AssessmentPeriodStudent $student): bool => mb_strtolower($student->student_name_snapshot) === $name
            && mb_strtolower($student->rombel_name_snapshot) === $class);
    }

    private function header(mixed $value): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/', ' ', (string) $value) ?? ''));
    }
}
