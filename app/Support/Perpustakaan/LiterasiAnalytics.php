<?php

namespace App\Support\Perpustakaan;

use App\Models\DataSiswa;
use App\Models\PerpustakaanLiterasiAnswer;
use App\Models\PerpustakaanLiterasiMaterial;
use App\Models\PerpustakaanLiterasiResponse;
use App\Models\PerpustakaanLiterasiSimilarityMatch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class LiterasiAnalytics
{
    public static function global(): array
    {
        return static::build(null);
    }

    public static function forMaterial(PerpustakaanLiterasiMaterial $material): array
    {
        return static::build($material);
    }

    protected static function build(?PerpustakaanLiterasiMaterial $material): array
    {
        [$monthStart, $monthEnd] = static::monthRange();

        return [
            'period_label' => $monthStart->format('d/m/Y').' - '.$monthEnd->format('d/m/Y'),
            'grading_summary' => static::gradingSummary($material, $monthStart, $monthEnd),
            'class_activity' => static::classActivityRows($material),
            'class_response_ranking' => static::classResponseRanking($material, $monthStart, $monthEnd),
            'class_correct_ranking' => static::classCorrectRanking($material, $monthStart, $monthEnd),
            'least_class_response_ranking' => static::leastClassResponseRanking($material, $monthStart, $monthEnd),
            'student_correct_ranking_by_class' => static::studentCorrectRankingByClass($material, $monthStart, $monthEnd),
            'plagiarism_class_ranking' => static::plagiarismClassRanking($material, $monthStart, $monthEnd),
            'plagiarism_student_ranking' => static::plagiarismStudentRanking($material, $monthStart, $monthEnd),
        ];
    }

    public static function classActivityRows(?PerpustakaanLiterasiMaterial $material = null): array
    {
        [$todayStart, $todayEnd] = static::todayRange();
        [$weekStart, $weekEnd] = static::weekRange();
        [$monthStart, $monthEnd] = static::monthRange();

        $activeTotals = static::activeStudentTotalsByClass();
        $todayTotals = static::responseCountsByClass($todayStart, $todayEnd, $material);
        $weekTotals = static::responseCountsByClass($weekStart, $weekEnd, $material);
        $monthTotals = static::responseCountsByClass($monthStart, $monthEnd, $material);

        return $activeTotals
            ->keys()
            ->merge($todayTotals->keys())
            ->merge($weekTotals->keys())
            ->merge($monthTotals->keys())
            ->unique()
            ->map(function (string $class) use ($activeTotals, $todayTotals, $weekTotals, $monthTotals): array {
                $active = (int) ($activeTotals[$class] ?? 0);
                $month = (int) ($monthTotals[$class] ?? 0);

                return [
                    'class' => $class,
                    'active_total' => $active,
                    'today' => (int) ($todayTotals[$class] ?? 0),
                    'week' => (int) ($weekTotals[$class] ?? 0),
                    'month' => $month,
                    'month_ratio' => $active > 0 ? $month.'/'.$active : $month.'/?',
                    'month_percentage' => $active > 0 ? round(($month / $active) * 100, 1) : null,
                ];
            })
            ->sortBy([
                ['month', 'desc'],
                ['week', 'desc'],
                ['today', 'desc'],
                ['class', 'asc'],
            ])
            ->values()
            ->all();
    }

    public static function classResponseRanking(
        ?PerpustakaanLiterasiMaterial $material,
        Carbon $start,
        Carbon $end,
        int $limit = 10
    ): array {
        $activeTotals = static::activeStudentTotalsByClass();

        return static::responseQuery($material)
            ->whereBetween('submitted_at', [$start, $end])
            ->whereNotNull('student_class_snapshot')
            ->where('student_class_snapshot', '!=', '')
            ->select('student_class_snapshot')
            ->selectRaw('count(*) as total')
            ->groupBy('student_class_snapshot')
            ->orderByDesc('total')
            ->orderBy('student_class_snapshot')
            ->limit($limit)
            ->get()
            ->map(function ($row) use ($activeTotals): array {
                $class = (string) $row->student_class_snapshot;
                $active = (int) ($activeTotals[$class] ?? 0);
                $total = (int) $row->total;

                return [
                    'class' => $class,
                    'total' => $total,
                    'active_total' => $active,
                    'ratio' => $active > 0 ? $total.'/'.$active : $total.'/?',
                    'percentage' => $active > 0 ? round(($total / $active) * 100, 1) : null,
                ];
            })
            ->all();
    }

    public static function classCorrectRanking(
        ?PerpustakaanLiterasiMaterial $material,
        Carbon $start,
        Carbon $end,
        int $limit = 3
    ): array {
        if (! static::gradingColumnsAvailable()) {
            return [];
        }

        return PerpustakaanLiterasiAnswer::query()
            ->join('perpustakaan_literasi_responses as responses', 'responses.id', '=', 'perpustakaan_literasi_answers.response_id')
            ->whereBetween('responses.submitted_at', [$start, $end])
            ->when($material, fn (Builder $query): Builder => $query->where('responses.material_id', $material->getKey()))
            ->whereNotNull('responses.student_class_snapshot')
            ->where('responses.student_class_snapshot', '!=', '')
            ->select('responses.student_class_snapshot')
            ->selectRaw('count(distinct responses.id) as response_count')
            ->selectRaw('sum(case when perpustakaan_literasi_answers.is_correct is not null then 1 else 0 end) as graded_answers')
            ->selectRaw('sum(case when perpustakaan_literasi_answers.is_correct = 1 then 1 else 0 end) as correct_answers')
            ->groupBy('responses.student_class_snapshot')
            ->havingRaw('sum(case when perpustakaan_literasi_answers.is_correct = 1 then 1 else 0 end) > 0')
            ->orderByDesc('correct_answers')
            ->orderByDesc('graded_answers')
            ->orderBy('responses.student_class_snapshot')
            ->limit($limit)
            ->get()
            ->map(function ($row): array {
                $graded = (int) $row->graded_answers;
                $correct = (int) $row->correct_answers;

                return [
                    'class' => (string) $row->student_class_snapshot,
                    'response_count' => (int) $row->response_count,
                    'correct_answers' => $correct,
                    'graded_answers' => $graded,
                    'accuracy' => $graded > 0 ? round(($correct / $graded) * 100, 1) : 0.0,
                ];
            })
            ->all();
    }

    public static function leastClassResponseRanking(
        ?PerpustakaanLiterasiMaterial $material,
        Carbon $start,
        Carbon $end,
        int $limit = 3
    ): array {
        $activeTotals = static::activeStudentTotalsByClass();
        $monthTotals = static::responseCountsByClass($start, $end, $material);

        return $activeTotals
            ->keys()
            ->merge($monthTotals->keys())
            ->unique()
            ->map(function (string $class) use ($activeTotals, $monthTotals): array {
                $active = (int) ($activeTotals[$class] ?? 0);
                $total = (int) ($monthTotals[$class] ?? 0);

                return [
                    'class' => $class,
                    'total' => $total,
                    'active_total' => $active,
                    'ratio' => $active > 0 ? $total.'/'.$active : $total.'/?',
                    'percentage' => $active > 0 ? round(($total / $active) * 100, 1) : null,
                ];
            })
            ->sortBy([
                ['total', 'asc'],
                ['percentage', 'asc'],
                ['class', 'asc'],
            ])
            ->take($limit)
            ->values()
            ->all();
    }

    public static function studentCorrectRankingByClass(
        ?PerpustakaanLiterasiMaterial $material,
        Carbon $start,
        Carbon $end,
        int $limitPerClass = 5
    ): array {
        if (! static::gradingColumnsAvailable()) {
            return [];
        }

        $rows = PerpustakaanLiterasiAnswer::query()
            ->join('perpustakaan_literasi_responses as responses', 'responses.id', '=', 'perpustakaan_literasi_answers.response_id')
            ->whereBetween('responses.submitted_at', [$start, $end])
            ->when($material, fn (Builder $query): Builder => $query->where('responses.material_id', $material->getKey()))
            ->select([
                'responses.data_siswa_id',
                'responses.student_name_snapshot',
                'responses.student_class_snapshot',
            ])
            ->selectRaw('count(perpustakaan_literasi_answers.id) as total_answers')
            ->selectRaw('count(distinct responses.id) as response_count')
            ->selectRaw('sum(case when perpustakaan_literasi_answers.is_correct is not null then 1 else 0 end) as graded_answers')
            ->selectRaw('sum(case when perpustakaan_literasi_answers.is_correct = 1 then 1 else 0 end) as correct_answers')
            ->whereNotNull('responses.student_class_snapshot')
            ->where('responses.student_class_snapshot', '!=', '')
            ->groupBy([
                'responses.data_siswa_id',
                'responses.student_name_snapshot',
                'responses.student_class_snapshot',
            ])
            ->havingRaw('sum(case when perpustakaan_literasi_answers.is_correct is not null then 1 else 0 end) > 0')
            ->get()
            ->map(function ($row): array {
                $graded = (int) $row->graded_answers;
                $correct = (int) $row->correct_answers;

                return [
                    'student_id' => (int) $row->data_siswa_id,
                    'name' => (string) $row->student_name_snapshot,
                    'class' => (string) $row->student_class_snapshot,
                    'correct_answers' => $correct,
                    'graded_answers' => $graded,
                    'total_answers' => (int) $row->total_answers,
                    'response_count' => (int) $row->response_count,
                    'accuracy' => $graded > 0 ? round(($correct / $graded) * 100, 1) : 0.0,
                ];
            });

        return $rows
            ->groupBy('class')
            ->sortKeys()
            ->map(fn (Collection $classRows): array => $classRows
                ->sortBy([
                    ['correct_answers', 'desc'],
                    ['accuracy', 'desc'],
                    ['graded_answers', 'desc'],
                    ['name', 'asc'],
                ])
                ->take($limitPerClass)
                ->values()
                ->all())
            ->all();
    }

    public static function plagiarismClassRanking(
        ?PerpustakaanLiterasiMaterial $material,
        Carbon $start,
        Carbon $end,
        int $limit = 10
    ): array {
        return static::similarityQuery($material, $start, $end)
            ->whereNotNull('student_class_snapshot')
            ->where('student_class_snapshot', '!=', '')
            ->select('student_class_snapshot')
            ->selectRaw('count(*) as total')
            ->groupBy('student_class_snapshot')
            ->orderByDesc('total')
            ->orderBy('student_class_snapshot')
            ->limit($limit)
            ->get()
            ->map(fn ($row): array => [
                'class' => (string) $row->student_class_snapshot,
                'total' => (int) $row->total,
            ])
            ->all();
    }

    public static function plagiarismStudentRanking(
        ?PerpustakaanLiterasiMaterial $material,
        Carbon $start,
        Carbon $end,
        int $limit = 10
    ): array {
        return static::similarityQuery($material, $start, $end)
            ->join('perpustakaan_literasi_responses as later', 'later.id', '=', 'perpustakaan_literasi_similarity_matches.later_response_id')
            ->select([
                'later.data_siswa_id',
                'later.student_name_snapshot',
                'later.student_class_snapshot',
            ])
            ->selectRaw('count(*) as total')
            ->groupBy([
                'later.data_siswa_id',
                'later.student_name_snapshot',
                'later.student_class_snapshot',
            ])
            ->orderByDesc('total')
            ->orderBy('later.student_class_snapshot')
            ->orderBy('later.student_name_snapshot')
            ->limit($limit)
            ->get()
            ->map(fn ($row): array => [
                'student_id' => (int) $row->data_siswa_id,
                'name' => (string) $row->student_name_snapshot,
                'class' => (string) ($row->student_class_snapshot ?: '-'),
                'total' => (int) $row->total,
            ])
            ->all();
    }

    public static function gradingSummary(?PerpustakaanLiterasiMaterial $material, Carbon $start, Carbon $end): array
    {
        if (! static::gradingColumnsAvailable()) {
            return [
                'responses' => 0,
                'total_answers' => 0,
                'graded_answers' => 0,
                'correct_answers' => 0,
                'accuracy' => 0.0,
                'confirmed_plagiarism' => 0,
            ];
        }

        $answerTotals = PerpustakaanLiterasiAnswer::query()
            ->join('perpustakaan_literasi_responses as responses', 'responses.id', '=', 'perpustakaan_literasi_answers.response_id')
            ->whereBetween('responses.submitted_at', [$start, $end])
            ->when($material, fn (Builder $query): Builder => $query->where('responses.material_id', $material->getKey()))
            ->selectRaw('count(perpustakaan_literasi_answers.id) as total_answers')
            ->selectRaw('sum(case when perpustakaan_literasi_answers.is_correct is not null then 1 else 0 end) as graded_answers')
            ->selectRaw('sum(case when perpustakaan_literasi_answers.is_correct = 1 then 1 else 0 end) as correct_answers')
            ->first();

        $responses = static::responseQuery($material)
            ->whereBetween('submitted_at', [$start, $end])
            ->count();
        $graded = (int) ($answerTotals?->graded_answers ?? 0);
        $correct = (int) ($answerTotals?->correct_answers ?? 0);

        return [
            'responses' => (int) $responses,
            'total_answers' => (int) ($answerTotals?->total_answers ?? 0),
            'graded_answers' => $graded,
            'correct_answers' => $correct,
            'accuracy' => $graded > 0 ? round(($correct / $graded) * 100, 1) : 0.0,
            'confirmed_plagiarism' => static::confirmedPlagiarismCount($material, $start, $end),
        ];
    }

    protected static function confirmedPlagiarismCount(?PerpustakaanLiterasiMaterial $material, Carbon $start, Carbon $end): int
    {
        if (! static::similarityReviewColumnsAvailable()) {
            return 0;
        }

        return (int) static::similarityQuery($material, $start, $end)
            ->where('review_status', PerpustakaanLiterasiSimilarityMatch::REVIEW_CONFIRMED)
            ->count();
    }

    protected static function responseCountsByClass(Carbon $start, Carbon $end, ?PerpustakaanLiterasiMaterial $material): Collection
    {
        return static::responseQuery($material)
            ->whereBetween('submitted_at', [$start, $end])
            ->whereNotNull('student_class_snapshot')
            ->where('student_class_snapshot', '!=', '')
            ->select('student_class_snapshot')
            ->selectRaw('count(*) as total')
            ->groupBy('student_class_snapshot')
            ->pluck('total', 'student_class_snapshot');
    }

    protected static function activeStudentTotalsByClass(): Collection
    {
        if (! Schema::hasTable('data_siswa') || ! Schema::hasColumn('data_siswa', 'rombel_saat_ini')) {
            return collect();
        }

        return DataSiswa::query()
            ->select('rombel_saat_ini')
            ->selectRaw('count(*) as total')
            ->where('status', 'aktif')
            ->whereNotNull('rombel_saat_ini')
            ->where('rombel_saat_ini', '!=', '')
            ->groupBy('rombel_saat_ini')
            ->pluck('total', 'rombel_saat_ini');
    }

    protected static function responseQuery(?PerpustakaanLiterasiMaterial $material): Builder
    {
        return PerpustakaanLiterasiResponse::query()
            ->whereNotNull('submitted_at')
            ->when($material, fn (Builder $query): Builder => $query->where('material_id', $material->getKey()));
    }

    protected static function similarityQuery(?PerpustakaanLiterasiMaterial $material, Carbon $start, Carbon $end): Builder
    {
        return PerpustakaanLiterasiSimilarityMatch::query()
            ->when($material, fn (Builder $query): Builder => $query->where('perpustakaan_literasi_similarity_matches.material_id', $material->getKey()))
            ->where(function (Builder $query) use ($start, $end): void {
                $query
                    ->whereBetween('later_submitted_at', [$start, $end])
                    ->orWhere(function (Builder $fallback) use ($start, $end): void {
                        $fallback
                            ->whereNull('later_submitted_at')
                            ->whereBetween('perpustakaan_literasi_similarity_matches.created_at', [$start, $end]);
                    });
            });
    }

    protected static function gradingColumnsAvailable(): bool
    {
        return Schema::hasTable('perpustakaan_literasi_answers')
            && Schema::hasColumn('perpustakaan_literasi_answers', 'is_correct');
    }

    protected static function similarityReviewColumnsAvailable(): bool
    {
        return Schema::hasTable('perpustakaan_literasi_similarity_matches')
            && Schema::hasColumn('perpustakaan_literasi_similarity_matches', 'review_status');
    }

    protected static function todayRange(): array
    {
        $now = now();

        return [$now->copy()->startOfDay(), $now->copy()->endOfDay()];
    }

    protected static function weekRange(): array
    {
        $now = now();

        return [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()];
    }

    protected static function monthRange(): array
    {
        $now = now();

        return [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()];
    }
}
