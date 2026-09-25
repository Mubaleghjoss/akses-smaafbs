<?php

namespace App\Support\Assessment;

use App\Models\Assessment\AssessmentExtracurricularParticipant;
use App\Models\Assessment\AssessmentPeriodStudent;
use Illuminate\Support\Facades\Schema;

final class AssessmentExtracurricularReportResolver
{
    /** @return array<int, array<string, mixed>> */
    public function resolve(AssessmentPeriodStudent $student, mixed $manual = [], bool $includeSource = false): array
    {
        return $this->resolveFor((int) $student->getKey(), (int) $student->assessment_period_id, $manual, $includeSource);
    }

    /** @return array<int, array<string, mixed>> */
    public function resolveFor(int $studentId, int $periodId, mixed $manual = [], bool $includeSource = false): array
    {
        if (! Schema::hasTable('assessment_extracurricular_participants')) {
            return $this->manualRows($manual, $includeSource);
        }

        $verified = AssessmentExtracurricularParticipant::query()
            ->where('assessment_period_student_id', $studentId)
            ->whereHas('extracurricular', fn ($query) => $query
                ->where('assessment_period_id', $periodId)
                ->where('is_active', true))
            ->whereHas('score', fn ($query) => $query->where('status', 'verified')->whereNotNull('predicate'))
            ->with(['extracurricular:id,name', 'score'])
            ->get()
            ->map(fn ($participant): array => array_filter([
                'name' => (string) $participant->extracurricular->name,
                'predicate' => (string) $participant->score->predicate,
                'description' => (string) $participant->score->predicate,
                'source' => $includeSource ? 'guru_ekskul' : null,
            ], fn ($value): bool => $value !== null))->values()->all();

        if ($verified !== []) {
            return $verified;
        }

        return $this->manualRows($manual, $includeSource);
    }

    /** @return array<int, array<string, mixed>> */
    private function manualRows(mixed $manual, bool $includeSource): array
    {
        $manual = is_array($manual) ? $manual : (json_decode((string) $manual, true) ?: []);

        return collect($manual)
            ->filter(fn ($item): bool => is_array($item))
            ->map(function (array $item) use ($includeSource): array {
                if (! $includeSource) {
                    unset($item['source']);

                    return $item;
                }

                return array_merge($item, ['source' => $item['source'] ?? 'manual_walas']);
            })
            ->values()
            ->all();
    }
}
