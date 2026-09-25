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
    /** @return array{created:int,skipped:int,scores_updated:int,protected_scores:int} */
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
        $scoresUpdated = 0;
        $protectedScores = 0;
        DB::transaction(function () use ($rows, $headers, $period, $actorId, &$created, &$skipped, &$scoresUpdated, &$protectedScores): void {
            foreach ($rows as $values) {
                $row = array_combine($headers, array_pad(array_slice($values, 0, count($headers)), count($headers), null));
                $name = trim((string) ($row['nama_siswa'] ?? ''));
                $class = trim((string) ($row['kelas'] ?? ''));
                $activity = trim((string) ($row['nama_ekskul'] ?? ''));
                $nisn = trim((string) ($row['nisn'] ?? ''));
                $predicate = strtoupper(trim((string) ($row['predikat'] ?? '')));
                $description = trim((string) ($row['catatan'] ?? ''));

                if ($class === '' || $activity === '' || ($nisn === '' && $name === '')) {
                    $skipped++;
                    continue;
                }
                if ($predicate !== '' && ! in_array($predicate, ['A', 'B', 'C', 'D'], true)) {
                    $skipped++;
                    continue;
                }

                $students = AssessmentPeriodStudent::query()
                    ->where('assessment_period_id', $period->getKey())
                    ->where('rombel_name_snapshot', $class)
                    ->when($nisn !== '', fn ($query) => $query->where('nisn_snapshot', $nisn), fn ($query) => $query->whereRaw('LOWER(student_name_snapshot) = ?', [strtolower($name)]))
                    ->get();
                if ($students->count() !== 1) {
                    $skipped++;
                    continue;
                }

                $student = $students->first();
                $extracurricular = AssessmentExtracurricular::query()->firstOrCreate(
                    ['assessment_period_id' => $period->getKey(), 'name' => $activity],
                    ['is_active' => true, 'created_by' => $actorId],
                );
                $participant = AssessmentExtracurricularParticipant::query()->firstOrCreate(
                    ['assessment_extracurricular_id' => $extracurricular->getKey(), 'assessment_period_student_id' => $student->getKey()],
                    ['assessment_period_rombel_id' => $student->assessment_period_rombel_id, 'source' => 'import'],
                );
                $participant->wasRecentlyCreated ? $created++ : $skipped++;

                if ($predicate === '') {
                    continue;
                }

                $score = $participant->score()->firstOrNew();
                if ($score->exists && in_array($score->status, ['submitted', 'verified'], true)) {
                    $protectedScores++;
                    continue;
                }
                $score->fill([
                    'predicate' => $predicate,
                    'description' => $description ?: null,
                    'status' => 'draft',
                    'updated_by' => $actorId,
                ])->save();
                $scoresUpdated++;
            }
        });

        return ['created' => $created, 'skipped' => $skipped, 'scores_updated' => $scoresUpdated, 'protected_scores' => $protectedScores];
    }
}
