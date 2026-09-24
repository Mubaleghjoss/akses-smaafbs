<?php

namespace App\Support\Assessment;

use App\Models\Assessment\AssessmentExtracurricularParticipant;
use App\Models\Assessment\AssessmentPeriodStudent;
use Illuminate\Support\Facades\Schema;

final class AssessmentExtracurricularReportResolver
{
    /** @return array<int, array{name:string,predicate:string,description:string,source:string}> */
    public function resolve(AssessmentPeriodStudent $student, mixed $manual = []): array
    {
        return $this->resolveFor((int) $student->getKey(), (int) $student->assessment_period_id, $manual);
    }

    /** @return array<int, array{name:string,predicate:string,description:string,source:string}> */
    public function resolveFor(int $studentId, int $periodId, mixed $manual = []): array
    {
        if (! Schema::hasTable('assessment_extracurricular_participants')) {
            return $this->manualRows($manual);
        }

        $verified = AssessmentExtracurricularParticipant::query()
            ->where('assessment_period_student_id', $studentId)
            ->whereHas('extracurricular', fn ($query) => $query
                ->where('assessment_period_id', $periodId)
                ->where('is_active', true))
            ->whereHas('score', fn ($query) => $query->where('status', 'verified')->whereNotNull('predicate'))
            ->with(['extracurricular:id,name', 'score'])
            ->get()
            ->map(fn ($participant): array => [
                'name' => (string) $participant->extracurricular->name,
                'predicate' => (string) $participant->score->predicate,
                'description' => (string) $participant->score->predicate,
                'source' => 'guru_ekskul',
            ])->values()->all();

        if ($verified !== []) {
            return $verified;
        }

        return $this->manualRows($manual);
    }

    /** @return array<int, array<string, mixed>> */
    private function manualRows(mixed $manual): array
    {
        $manual = is_array($manual) ? $manual : (json_decode((string) $manual, true) ?: []);

        return collect($manual)
            ->filter(fn ($item): bool => is_array($item))
            ->values()
            ->all();
    }
}
