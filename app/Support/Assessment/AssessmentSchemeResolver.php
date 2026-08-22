<?php

namespace App\Support\Assessment;

use App\Models\Assessment\AssessmentPeriodAssignment;
use App\Models\Assessment\AssessmentScheme;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

final class AssessmentSchemeResolver
{
    public function forAssignment(AssessmentPeriodAssignment $assignment): AssessmentScheme
    {
        $sourceRombelId = (int) (
            $assignment->periodRombel?->source_rombel_id
                ?? $assignment->periodRombel()->value('source_rombel_id')
                ?? 0
        );

        /** @var Collection<int, AssessmentScheme> $schemes */
        $schemes = AssessmentScheme::query()
            ->where('assessment_period_id', $assignment->assessment_period_id)
            ->where('is_active', true)
            ->where(function ($query) use ($assignment): void {
                $query->whereNull('assessment_subject_id')
                    ->orWhere('assessment_subject_id', $assignment->assessment_subject_id);
            })
            ->where(function ($query) use ($assignment): void {
                $query->whereNull('assessment_period_rombel_id')
                    ->orWhere('assessment_period_rombel_id', $assignment->assessment_period_rombel_id);
            })
            ->where(function ($query) use ($sourceRombelId): void {
                $query->whereNull('source_rombel_id');

                if ($sourceRombelId > 0) {
                    $query->orWhere('source_rombel_id', $sourceRombelId);
                }
            })
            ->with(['components' => fn ($query) => $query->orderBy('sort_order')->orderBy('id')])
            ->get();

        $ranked = $schemes
            ->map(fn (AssessmentScheme $scheme): array => [
                'scheme' => $scheme,
                'specificity' => (int) ($scheme->assessment_subject_id !== null)
                    + (int) (
                        $scheme->source_rombel_id !== null
                        || $scheme->assessment_period_rombel_id !== null
                    ),
            ])
            ->sortByDesc('specificity')
            ->values();
        $highestSpecificity = $ranked->first()['specificity'] ?? null;
        $matches = $ranked
            ->filter(fn (array $item): bool => $item['specificity'] === $highestSpecificity)
            ->pluck('scheme')
            ->values();

        if ($matches->isEmpty()) {
            throw ValidationException::withMessages([
                'scheme' => 'Tidak ada skema penilaian aktif yang cocok dengan mapel dan kelas penugasan.',
            ]);
        }

        if ($matches->count() > 1) {
            throw ValidationException::withMessages([
                'scheme' => 'Terdapat lebih dari satu skema aktif dengan tingkat kecocokan yang sama. Nonaktifkan skema duplikat sebelum melanjutkan.',
            ]);
        }

        /** @var AssessmentScheme $scheme */
        $scheme = $matches->first();

        return $scheme;
    }
}
