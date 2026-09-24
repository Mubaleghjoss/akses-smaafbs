<?php

namespace App\Support\Assessment;

use App\Models\Assessment\AssessmentExtracurricular;
use App\Models\Assessment\AssessmentExtracurricularParticipant;
use App\Models\Assessment\AssessmentPeriod;
use App\Models\Assessment\AssessmentPeriodStudent;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

final class AssessmentExtracurricularImport
{
    /** @return array{created:int,skipped:int} */
    public function import(AssessmentPeriod $period, UploadedFile|string $file, int $actorId): array
    {
        $rows = Excel::toArray([], $file)[0] ?? [];
        $headers = array_map(fn ($value) => strtolower(trim((string) $value)), array_shift($rows) ?? []);
        foreach (['nama_siswa', 'kelas', 'nama_ekskul'] as $required) {
            if (! in_array($required, $headers, true)) {
                throw ValidationException::withMessages(['import' => "Kolom {$required} wajib tersedia."]);
            }
        }

        $created = 0;
        $skipped = 0;
        DB::transaction(function () use ($rows, $headers, $period, $actorId, &$created, &$skipped): void {
            foreach ($rows as $values) {
                $row = array_combine($headers, array_pad(array_slice($values, 0, count($headers)), count($headers), null));
                $name = trim((string) ($row['nama_siswa'] ?? ''));
                $class = trim((string) ($row['kelas'] ?? ''));
                $activity = trim((string) ($row['nama_ekskul'] ?? ''));
                $nisn = trim((string) ($row['nisn'] ?? ''));
                $students = AssessmentPeriodStudent::query()->where('assessment_period_id', $period->getKey())->where('rombel_name_snapshot', $class)
                    ->when($nisn !== '', fn ($query) => $query->where('nisn_snapshot', $nisn), fn ($query) => $query->whereRaw('LOWER(student_name_snapshot) = ?', [strtolower($name)]))->get();
                if ($name === '' || $class === '' || $activity === '' || $students->count() !== 1) {
                    $skipped++;
                    continue;
                }
                $extracurricular = AssessmentExtracurricular::query()->firstOrCreate(
                    ['assessment_period_id' => $period->getKey(), 'name' => $activity],
                    ['is_active' => true, 'created_by' => $actorId],
                );
                $participant = AssessmentExtracurricularParticipant::query()->firstOrCreate(
                    ['assessment_extracurricular_id' => $extracurricular->getKey(), 'assessment_period_student_id' => $students->first()->getKey()],
                    ['assessment_period_rombel_id' => $students->first()->assessment_period_rombel_id, 'source' => 'import'],
                );
                $participant->wasRecentlyCreated ? $created++ : $skipped++;
            }
        });

        return compact('created', 'skipped');
    }
}
