<?php

namespace App\Support\Perpustakaan;

use App\Models\DataSiswa;
use App\Models\PerpustakaanLiterasiAnswer;
use App\Models\PerpustakaanLiterasiDispensation;
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

    public static function forProgramCategory(string $category): array
    {
        return static::build(null, $category);
    }

    public static function categoryAnalytics(): array
    {
        return collect(static::programCategoryScopes())
            ->map(fn (string $label, string $category): array => [
                'key' => $category,
                'label' => $label,
                'analytics' => static::forProgramCategory($category),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  Carbon|null  $start  Rentang khusus; null = bulan berjalan.
     */
    public static function monthlyShare(
        ?string $programCategory = null,
        ?Carbon $start = null,
        ?Carbon $end = null,
    ): array {
        [$monthStart, $monthEnd] = $start !== null && $end !== null
            ? [$start, $end]
            : static::monthRange();
        $grading = static::gradingSummary(null, $monthStart, $monthEnd, $programCategory);
        $classParticipation = static::leastClassResponseRanking(null, $monthStart, $monthEnd, null, $programCategory);
        $mostClassParticipation = collect($classParticipation)
            ->sortBy([
                ['total', 'desc'],
                ['percentage', 'desc'],
                ['class', 'asc'],
            ])
            ->values()
            ->all();

        return [
            'period_label' => $monthStart->format('d/m/Y').' - '.$monthEnd->format('d/m/Y'),
            'grading_summary' => $grading
                + static::respondentGradingSummary($monthStart, $monthEnd, $programCategory)
                + static::respondentSimilaritySummary($monthStart, $monthEnd, $programCategory),
            'class_participation' => $classParticipation,
            'class_response_ranking' => $mostClassParticipation,
            'least_class_response_ranking' => $classParticipation,
            'class_correct_ranking' => static::classCorrectRanking(null, $monthStart, $monthEnd, 3, $programCategory),
            'student_correct_ranking_by_class' => static::studentCorrectRankingByClass(null, $monthStart, $monthEnd, null, $programCategory),
            'student_wrong_ranking' => static::studentWrongRanking(null, $monthStart, $monthEnd, null, $programCategory),
            'missing_students' => static::missingStudents(null, $monthStart, $monthEnd, null, $programCategory),
            'plagiarism_class_ranking' => static::plagiarismClassRanking(
                null,
                $monthStart,
                $monthEnd,
                null,
                $programCategory,
                [PerpustakaanLiterasiSimilarityMatch::REVIEW_SUSPECTED, PerpustakaanLiterasiSimilarityMatch::REVIEW_CONFIRMED],
            ),
            'plagiarism_student_ranking' => static::plagiarismStudentRanking(
                null,
                $monthStart,
                $monthEnd,
                null,
                $programCategory,
                [PerpustakaanLiterasiSimilarityMatch::REVIEW_SUSPECTED, PerpustakaanLiterasiSimilarityMatch::REVIEW_CONFIRMED],
            ),
        ];
    }

    public static function forMaterial(PerpustakaanLiterasiMaterial $material): array
    {
        return static::build($material) + [
            'material_completion' => static::materialCompletion($material),
        ];
    }

    public static function materialCompletion(PerpustakaanLiterasiMaterial $material): array
    {
        if (! Schema::hasTable('data_siswa') || ! Schema::hasTable('perpustakaan_literasi_responses')) {
            return [
                'active_total' => 0,
                'completed_total' => 0,
                'dispensation_total' => 0,
                'missing_total' => 0,
                'trashed_total' => 0,
                'respondent_base' => 0,
                'completion_percentage' => null,
                'ratio' => '0/0',
                'classes' => [],
            ];
        }

        $students = DataSiswa::query()
            ->where('status', 'aktif')
            ->orderBy('rombel_saat_ini')
            ->orderBy('nama')
            ->get(['id', 'nama', 'rombel_saat_ini']);

        $responses = PerpustakaanLiterasiResponse::withTrashed()
            ->where('material_id', $material->getKey())
            ->get(['id', 'data_siswa_id', 'deleted_at'])
            ->keyBy('data_siswa_id');
        $dispensations = static::dispensationTableAvailable()
            ? PerpustakaanLiterasiDispensation::query()
                ->with('confirmedBy:id,name')
                ->where('material_id', $material->getKey())
                ->get(['id', 'data_siswa_id', 'reason', 'confirmed_at', 'confirmed_by', 'note'])
                ->keyBy('data_siswa_id')
            : collect();

        $classes = $students
            ->groupBy(fn (DataSiswa $student): string => trim((string) $student->rombel_saat_ini) ?: '-')
            ->map(function (Collection $classStudents, string $class) use ($responses, $dispensations): array {
                $completed = [];
                $dispensated = [];
                $missing = [];
                $trashed = [];

                foreach ($classStudents as $student) {
                    $row = [
                        'student_id' => (int) $student->getKey(),
                        'name' => (string) $student->nama,
                        'class' => $class,
                    ];
                    $response = $responses->get($student->getKey());
                    $dispensation = $dispensations->get($student->getKey());

                    if (! $response && $dispensation) {
                        $dispensated[] = $row + [
                            'reason' => (string) $dispensation->reason,
                            'reason_label' => $dispensation->reasonLabel(),
                            'confirmed_at' => $dispensation->confirmed_at?->format('d/m/Y H:i'),
                            'confirmed_by' => $dispensation->confirmedBy?->name,
                            'note' => $dispensation->note,
                        ];
                    } elseif (! $response) {
                        $missing[] = $row;
                    } elseif ($response->deleted_at !== null) {
                        $trashed[] = $row;
                    } else {
                        $completed[] = $row;
                    }
                }

                $activeTotal = $classStudents->count();
                // Dispensasi dikeluarkan dari penyebut, bukan ditambahkan ke pembilang.
                $respondentBase = max(0, $activeTotal - count($dispensated));

                return [
                    'class' => $class,
                    'active_total' => $activeTotal,
                    'completed_total' => count($completed),
                    'dispensation_total' => count($dispensated),
                    'respondent_base' => $respondentBase,
                    'missing_total' => count($missing),
                    'trashed_total' => count($trashed),
                    'completion_percentage' => $respondentBase > 0
                        ? round((count($completed) / $respondentBase) * 100, 1)
                        : null,
                    'ratio' => count($completed).'/'.$respondentBase,
                    'dispensated_students' => $dispensated,
                    'missing_students' => $missing,
                    'trashed_students' => $trashed,
                ];
            })
            ->sortBy('class', SORT_NATURAL)
            ->values();

        $activeTotal = $students->count();
        $completedTotal = (int) $classes->sum('completed_total');
        $dispensationTotal = (int) $classes->sum('dispensation_total');
        $respondentBase = max(0, $activeTotal - $dispensationTotal);
        $missingTotal = (int) $classes->sum('missing_total');
        $trashedTotal = (int) $classes->sum('trashed_total');

        return [
            'active_total' => $activeTotal,
            'completed_total' => $completedTotal,
            'dispensation_total' => $dispensationTotal,
            'respondent_base' => $respondentBase,
            'missing_total' => $missingTotal,
            'trashed_total' => $trashedTotal,
            'completion_percentage' => $respondentBase > 0
                ? round(($completedTotal / $respondentBase) * 100, 1)
                : null,
            'ratio' => $completedTotal.'/'.$respondentBase,
            'classes' => $classes->all(),
        ];
    }

    protected static function build(?PerpustakaanLiterasiMaterial $material, ?string $programCategory = null): array
    {
        [$monthStart, $monthEnd] = static::monthRange();

        return [
            'period_label' => $monthStart->format('d/m/Y').' - '.$monthEnd->format('d/m/Y'),
            'grading_summary' => static::gradingSummary($material, $monthStart, $monthEnd, $programCategory),
            'class_activity' => static::classActivityRows($material, $programCategory),
            'class_response_ranking' => static::classResponseRanking($material, $monthStart, $monthEnd, programCategory: $programCategory),
            'class_correct_ranking' => static::classCorrectRanking($material, $monthStart, $monthEnd, programCategory: $programCategory),
            'least_class_response_ranking' => static::leastClassResponseRanking($material, $monthStart, $monthEnd, programCategory: $programCategory),
            'student_correct_ranking_by_class' => static::studentCorrectRankingByClass($material, $monthStart, $monthEnd, programCategory: $programCategory),
            'student_wrong_ranking' => static::studentWrongRanking($material, $monthStart, $monthEnd, programCategory: $programCategory),
            'missing_students' => static::missingStudents($material, $monthStart, $monthEnd, programCategory: $programCategory),
            'plagiarism_class_ranking' => static::plagiarismClassRanking($material, $monthStart, $monthEnd, programCategory: $programCategory),
            'plagiarism_student_ranking' => static::plagiarismStudentRanking($material, $monthStart, $monthEnd, programCategory: $programCategory),
        ];
    }

    public static function classActivityRows(?PerpustakaanLiterasiMaterial $material = null, ?string $programCategory = null): array
    {
        [$todayStart, $todayEnd] = static::todayRange();
        [$weekStart, $weekEnd] = static::weekRange();
        [$monthStart, $monthEnd] = static::monthRange();

        $activeTotals = static::activeStudentTotalsByClass();
        $todayTotals = static::responseCountsByClass($todayStart, $todayEnd, $material, $programCategory);
        $weekTotals = static::responseCountsByClass($weekStart, $weekEnd, $material, $programCategory);

        // Persentase bulanan memakai basis responden (slot materi x siswa,
        // dispensasi dikeluarkan) supaya nilainya tidak pernah melewati 100%.
        $monthBase = static::respondentBaseByClass($material, $monthStart, $monthEnd, $programCategory)
            ->keyBy('class');

        return $activeTotals
            ->keys()
            ->merge($todayTotals->keys())
            ->merge($weekTotals->keys())
            ->merge($monthBase->keys())
            ->unique()
            ->map(function (string $class) use ($activeTotals, $todayTotals, $weekTotals, $monthBase): array {
                $active = (int) ($activeTotals[$class] ?? 0);
                $month = $monthBase->get($class);

                return [
                    'class' => $class,
                    'active_total' => $active,
                    'today' => (int) ($todayTotals[$class] ?? 0),
                    'week' => (int) ($weekTotals[$class] ?? 0),
                    'month' => (int) ($month['total'] ?? 0),
                    'month_respondent_base' => (int) ($month['respondent_base'] ?? 0),
                    'month_missing' => (int) ($month['missing_total'] ?? 0),
                    'month_excluded' => (int) ($month['excluded_total'] ?? 0),
                    'month_ratio' => (string) ($month['ratio'] ?? '0/0'),
                    'month_percentage' => $month['percentage'] ?? null,
                ];
            })
            ->sortBy([
                ['month_percentage', 'desc'],
                ['month', 'desc'],
                ['class', 'asc'],
            ])
            ->values()
            ->all();
    }

    public static function classResponseRanking(
        ?PerpustakaanLiterasiMaterial $material,
        Carbon $start,
        Carbon $end,
        ?int $limit = 10,
        ?string $programCategory = null
    ): array {
        $rows = static::respondentBaseByClass($material, $start, $end, $programCategory)
            ->sortBy([
                ['total', 'desc'],
                ['class', 'asc'],
            ]);

        return ($limit === null ? $rows : $rows->take($limit))->values()->all();
    }

    public static function classCorrectRanking(
        ?PerpustakaanLiterasiMaterial $material,
        Carbon $start,
        Carbon $end,
        int $limit = 3,
        ?string $programCategory = null
    ): array {
        if (! static::gradingColumnsAvailable()) {
            return [];
        }

        return PerpustakaanLiterasiAnswer::query()
            ->join('perpustakaan_literasi_responses as responses', 'responses.id', '=', 'perpustakaan_literasi_answers.response_id')
            ->whereBetween('responses.submitted_at', [$start, $end])
            ->when($material, fn (Builder $query): Builder => $query->where('responses.material_id', $material->getKey()))
            ->when($programCategory, fn (Builder $query): Builder => static::constrainJoinedResponseCategory($query, $programCategory))
            ->whereNotNull('responses.student_class_snapshot')
            ->where('responses.student_class_snapshot', '!=', '')
            ->select('responses.student_class_snapshot')
            ->selectRaw('count(distinct responses.id) as response_count')
            ->selectRaw('sum(case when perpustakaan_literasi_answers.score_earned is not null or perpustakaan_literasi_answers.is_correct is not null then coalesce(perpustakaan_literasi_answers.score_possible, 1) else 0 end) as graded_answers')
            ->selectRaw('sum(coalesce(perpustakaan_literasi_answers.score_earned, case when perpustakaan_literasi_answers.is_correct = 1 then 1 else 0 end)) as correct_answers')
            ->groupBy('responses.student_class_snapshot')
            ->havingRaw('sum(coalesce(perpustakaan_literasi_answers.score_earned, case when perpustakaan_literasi_answers.is_correct = 1 then 1 else 0 end)) > 0')
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
        ?int $limit = 3,
        ?string $programCategory = null
    ): array {
        $rows = static::respondentBaseByClass($material, $start, $end, $programCategory)
            ->sortBy([
                ['percentage', 'asc'],
                ['total', 'asc'],
                ['class', 'asc'],
            ]);

        return ($limit === null ? $rows : $rows->take($limit))->values()->all();
    }

    /**
     * Baris per kelas dari basis responden: penyebut sudah bebas dispensasi.
     *
     * @return Collection<int, array<string, mixed>>
     */
    protected static function respondentBaseByClass(
        ?PerpustakaanLiterasiMaterial $material,
        Carbon $start,
        Carbon $end,
        ?string $programCategory = null,
        ?array $classes = null,
    ): Collection {
        // Ranking dan aktivitas selalu terikat rentang: jawaban di luar rentang
        // tidak boleh ikut terhitung meskipun materinya sama.
        $materialIds = $material !== null
            ? [(int) $material->getKey()]
            : LiteracyRespondentBase::materialIdsInScope($programCategory, $start, $end);

        $base = LiteracyRespondentBase::forMaterialIds($materialIds, $classes, $start, $end);

        return collect($base['classes'])->map(fn (array $row): array => [
            'class' => $row['class'],
            'total' => (int) $row['completed_total'],
            'response_total' => (int) $row['completed_total'],
            'excluded_total' => (int) $row['excluded_total'],
            'excluded_by_reason' => $row['excluded_by_reason'],
            'missing_total' => (int) $row['missing_total'],
            'respondent_base' => (int) $row['respondent_base'],
            'active_total' => (int) $row['active_total'],
            'ratio' => (string) $row['ratio'],
            'percentage' => $row['participation_percentage'],
        ]);
    }

    public static function studentCorrectRankingByClass(
        ?PerpustakaanLiterasiMaterial $material,
        Carbon $start,
        Carbon $end,
        ?int $limitPerClass = 5,
        ?string $programCategory = null
    ): array {
        if (! static::gradingColumnsAvailable()) {
            return [];
        }

        $rows = PerpustakaanLiterasiAnswer::query()
            ->join('perpustakaan_literasi_responses as responses', 'responses.id', '=', 'perpustakaan_literasi_answers.response_id')
            ->whereBetween('responses.submitted_at', [$start, $end])
            ->when($material, fn (Builder $query): Builder => $query->where('responses.material_id', $material->getKey()))
            ->when($programCategory, fn (Builder $query): Builder => static::constrainJoinedResponseCategory($query, $programCategory))
            ->select([
                'responses.data_siswa_id',
                'responses.student_name_snapshot',
                'responses.student_class_snapshot',
            ])
            ->selectRaw('sum(coalesce(perpustakaan_literasi_answers.score_possible, 1)) as total_answers')
            ->selectRaw('count(distinct responses.id) as response_count')
            ->selectRaw('sum(case when perpustakaan_literasi_answers.score_earned is not null or perpustakaan_literasi_answers.is_correct is not null then coalesce(perpustakaan_literasi_answers.score_possible, 1) else 0 end) as graded_answers')
            ->selectRaw('sum(coalesce(perpustakaan_literasi_answers.score_earned, case when perpustakaan_literasi_answers.is_correct = 1 then 1 else 0 end)) as correct_answers')
            ->whereNotNull('responses.student_class_snapshot')
            ->where('responses.student_class_snapshot', '!=', '')
            ->groupBy([
                'responses.data_siswa_id',
                'responses.student_name_snapshot',
                'responses.student_class_snapshot',
            ])
            ->havingRaw('sum(case when perpustakaan_literasi_answers.score_earned is not null or perpustakaan_literasi_answers.is_correct is not null then coalesce(perpustakaan_literasi_answers.score_possible, 1) else 0 end) > 0')
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
            ->map(function (Collection $classRows) use ($limitPerClass): array {
                $sorted = $classRows->sortBy([
                    ['correct_answers', 'desc'],
                    ['accuracy', 'desc'],
                    ['graded_answers', 'desc'],
                    ['name', 'asc'],
                ]);

                return ($limitPerClass === null ? $sorted : $sorted->take($limitPerClass))->values()->all();
            })
            ->all();
    }

    public static function plagiarismClassRanking(
        ?PerpustakaanLiterasiMaterial $material,
        Carbon $start,
        Carbon $end,
        ?int $limit = 10,
        ?string $programCategory = null,
        ?array $reviewStatuses = null,
    ): array {
        $query = static::similarityQuery($material, $start, $end, $programCategory)
            ->when($reviewStatuses !== null, fn (Builder $query): Builder => $query->whereIn('review_status', $reviewStatuses))
            ->whereNotNull('student_class_snapshot')
            ->where('student_class_snapshot', '!=', '')
            ->select('student_class_snapshot')
            ->selectRaw('count(*) as total')
            ->groupBy('student_class_snapshot')
            ->orderByDesc('total')
            ->orderBy('student_class_snapshot');

        return $query
            ->when($limit !== null, fn (Builder $query): Builder => $query->limit($limit))
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
        ?int $limit = 10,
        ?string $programCategory = null,
        ?array $reviewStatuses = null,
    ): array {
        $query = static::similarityQuery($material, $start, $end, $programCategory)
            ->when($reviewStatuses !== null, fn (Builder $query): Builder => $query->whereIn('review_status', $reviewStatuses))
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
            ->orderBy('later.student_name_snapshot');

        return $query
            ->when($limit !== null, fn (Builder $query): Builder => $query->limit($limit))
            ->get()
            ->map(fn ($row): array => [
                'student_id' => (int) $row->data_siswa_id,
                'name' => (string) $row->student_name_snapshot,
                'class' => (string) ($row->student_class_snapshot ?: '-'),
                'total' => (int) $row->total,
            ])
            ->all();
    }

    public static function studentWrongRanking(
        ?PerpustakaanLiterasiMaterial $material,
        Carbon $start,
        Carbon $end,
        ?int $limit = 15,
        ?string $programCategory = null
    ): array {
        if (! static::gradingColumnsAvailable()) {
            return [];
        }

        $query = PerpustakaanLiterasiAnswer::query()
            ->join('perpustakaan_literasi_responses as responses', 'responses.id', '=', 'perpustakaan_literasi_answers.response_id')
            ->whereBetween('responses.submitted_at', [$start, $end])
            ->when($material, fn (Builder $query): Builder => $query->where('responses.material_id', $material->getKey()))
            ->when($programCategory, fn (Builder $query): Builder => static::constrainJoinedResponseCategory($query, $programCategory))
            ->where(function (Builder $query): void {
                $query
                    ->whereNotNull('perpustakaan_literasi_answers.score_earned')
                    ->orWhere('perpustakaan_literasi_answers.is_correct', false);
            })
            ->select([
                'responses.data_siswa_id',
                'responses.student_name_snapshot',
                'responses.student_class_snapshot',
            ])
            ->selectRaw('sum(case when coalesce(perpustakaan_literasi_answers.score_possible, 1) > coalesce(perpustakaan_literasi_answers.score_earned, 0) then coalesce(perpustakaan_literasi_answers.score_possible, 1) - coalesce(perpustakaan_literasi_answers.score_earned, 0) else 0 end) as wrong_answers')
            ->groupBy([
                'responses.data_siswa_id',
                'responses.student_name_snapshot',
                'responses.student_class_snapshot',
            ])
            ->havingRaw('sum(case when coalesce(perpustakaan_literasi_answers.score_possible, 1) > coalesce(perpustakaan_literasi_answers.score_earned, 0) then coalesce(perpustakaan_literasi_answers.score_possible, 1) - coalesce(perpustakaan_literasi_answers.score_earned, 0) else 0 end) > 0')
            ->orderByDesc('wrong_answers')
            ->orderBy('responses.student_class_snapshot')
            ->orderBy('responses.student_name_snapshot');

        return $query
            ->when($limit !== null, fn (Builder $query): Builder => $query->limit($limit))
            ->get()
            ->map(fn ($row): array => [
                'student_id' => (int) $row->data_siswa_id,
                'name' => (string) $row->student_name_snapshot,
                'class' => (string) ($row->student_class_snapshot ?: '-'),
                'wrong_answers' => (int) $row->wrong_answers,
            ])
            ->all();
    }

    public static function missingStudents(
        ?PerpustakaanLiterasiMaterial $material,
        Carbon $start,
        Carbon $end,
        ?int $limit = 30,
        ?string $programCategory = null
    ): array {
        if (! Schema::hasTable('data_siswa')) {
            return [];
        }

        $respondedStudentIds = static::responseQuery($material, $programCategory)
            ->whereBetween('submitted_at', [$start, $end])
            ->select('data_siswa_id');
        $dispensatedStudentIds = static::dispensationTableAvailable()
            ? static::dispensationQuery($material, $programCategory)
                ->whereBetween('confirmed_at', [$start, $end])
                ->select('data_siswa_id')
            : null;

        $query = DataSiswa::query()
            ->where('status', 'aktif')
            ->whereNotIn('id', $respondedStudentIds)
            ->when(
                $dispensatedStudentIds,
                fn (Builder $query, Builder $ids): Builder => $query->whereNotIn('id', $ids),
            )
            ->orderBy('rombel_saat_ini')
            ->orderBy('nama');

        return $query
            ->when($limit !== null, fn (Builder $query): Builder => $query->limit($limit))
            ->get(['id', 'nama', 'rombel_saat_ini'])
            ->map(fn (DataSiswa $student): array => [
                'student_id' => (int) $student->getKey(),
                'name' => (string) $student->nama,
                'class' => (string) ($student->rombel_saat_ini ?: '-'),
            ])
            ->all();
    }

    public static function gradingSummary(
        ?PerpustakaanLiterasiMaterial $material,
        Carbon $start,
        Carbon $end,
        ?string $programCategory = null,
        ?array $classes = null
    ): array {
        $responses = static::responseQuery($material, $programCategory)
            ->whereBetween('submitted_at', [$start, $end])
            ->count();
        $base = static::respondentBase($material, $start, $end, $programCategory, $classes);

        if (! static::gradingColumnsAvailable()) {
            return [
                // Responden = yang benar-benar mengisi. Dispensasi TIDAK ditambahkan.
                'responses' => (int) $responses,
                'response_records' => (int) $responses,
                'dispensations' => (int) $base['excluded_total'],
                'respondent_base' => (int) $base['respondent_base'],
                'excluded_total' => (int) $base['excluded_total'],
                'excluded_by_reason' => $base['excluded_by_reason'],
                'missing_total' => (int) $base['missing_total'],
                'participation_percentage' => $base['participation_percentage'],
                'participation_ratio' => (string) $base['ratio'],
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
            ->when($programCategory, fn (Builder $query): Builder => static::constrainJoinedResponseCategory($query, $programCategory))
            ->selectRaw('sum(coalesce(perpustakaan_literasi_answers.score_possible, 1)) as total_answers')
            ->selectRaw('sum(case when perpustakaan_literasi_answers.score_earned is not null or perpustakaan_literasi_answers.is_correct is not null then coalesce(perpustakaan_literasi_answers.score_possible, 1) else 0 end) as graded_answers')
            ->selectRaw('sum(coalesce(perpustakaan_literasi_answers.score_earned, case when perpustakaan_literasi_answers.is_correct = 1 then 1 else 0 end)) as correct_answers')
            ->first();

        $graded = (int) ($answerTotals?->graded_answers ?? 0);
        $correct = (int) ($answerTotals?->correct_answers ?? 0);

        return [
            // Responden = yang benar-benar mengisi. Dispensasi TIDAK ditambahkan.
            'responses' => (int) $responses,
            'response_records' => (int) $responses,
            // Dipertahankan sebagai informasi terpisah, bukan bagian pembilang.
            'dispensations' => (int) $base['excluded_total'],
            'respondent_base' => (int) $base['respondent_base'],
            'excluded_total' => (int) $base['excluded_total'],
            'excluded_by_reason' => $base['excluded_by_reason'],
            'missing_total' => (int) $base['missing_total'],
            'participation_percentage' => $base['participation_percentage'],
            'participation_ratio' => (string) $base['ratio'],
            'total_answers' => (int) ($answerTotals?->total_answers ?? 0),
            'graded_answers' => $graded,
            'correct_answers' => $correct,
            'accuracy' => $graded > 0 ? round(($correct / $graded) * 100, 1) : 0.0,
            'confirmed_plagiarism' => static::confirmedPlagiarismCount($material, $start, $end, $programCategory),
        ];
    }

    /**
     * Basis responden untuk lingkup yang sedang dilihat.
     *
     * Selalu lewat LiteracyRespondentBase agar aturan pengecualian dispensasi
     * hanya hidup di satu tempat.
     *
     * @param  array<int, string>|null  $classes
     * @return array<string, mixed>
     */
    public static function respondentBase(
        ?PerpustakaanLiterasiMaterial $material,
        Carbon $start,
        Carbon $end,
        ?string $programCategory = null,
        ?array $classes = null,
    ): array {
        $materialIds = $material !== null
            ? [(int) $material->getKey()]
            : LiteracyRespondentBase::materialIdsInScope($programCategory, $start, $end);

        // Untuk satu materi, rekap "seluruh waktu" tetap dipakai (panel materi).
        // Untuk lingkup kategori, jawaban dibatasi ke rentang yang diminta.
        return $material !== null
            ? LiteracyRespondentBase::forMaterialIds($materialIds, $classes)
            : LiteracyRespondentBase::forMaterialIds($materialIds, $classes, $start, $end);
    }

    protected static function confirmedPlagiarismCount(
        ?PerpustakaanLiterasiMaterial $material,
        Carbon $start,
        Carbon $end,
        ?string $programCategory = null
    ): int {
        if (! static::similarityReviewColumnsAvailable()) {
            return 0;
        }

        return (int) static::similarityQuery($material, $start, $end, $programCategory)
            ->where('review_status', PerpustakaanLiterasiSimilarityMatch::REVIEW_CONFIRMED)
            ->count();
    }

    /**
     * @return array{unique_students:int,fully_graded_responses:int,pending_grading_responses:int}
     */
    protected static function respondentGradingSummary(
        Carbon $start,
        Carbon $end,
        ?string $programCategory = null,
    ): array {
        $responseRows = static::responseQuery(null, $programCategory)
            ->whereBetween('submitted_at', [$start, $end])
            ->get(['id', 'data_siswa_id']);
        $responseIds = $responseRows->pluck('id');
        $dispensatedStudentIds = static::dispensationTableAvailable()
            ? static::dispensationQuery(null, $programCategory)
                ->whereBetween('confirmed_at', [$start, $end])
                ->pluck('data_siswa_id')
            : collect();
        $fullyGraded = 0;

        if ($responseIds->isNotEmpty() && static::gradingColumnsAvailable()) {
            $fullyGraded = PerpustakaanLiterasiAnswer::query()
                ->whereIn('response_id', $responseIds)
                ->select('response_id')
                ->groupBy('response_id')
                ->havingRaw('count(*) > 0')
                ->havingRaw('sum(case when score_earned is null and is_correct is null then 1 else 0 end) = 0')
                ->pluck('response_id')
                ->count();
        }

        return [
            'unique_students' => $responseRows
                ->pluck('data_siswa_id')
                ->merge($dispensatedStudentIds)
                ->filter()
                ->unique()
                ->count(),
            'fully_graded_responses' => $fullyGraded,
            'pending_grading_responses' => max(0, $responseRows->count() - $fullyGraded),
        ];
    }

    /**
     * @return array{confirmed_plagiarism_students:int,pending_similarity_students:int}
     */
    protected static function respondentSimilaritySummary(
        Carbon $start,
        Carbon $end,
        ?string $programCategory = null,
    ): array {
        if (! static::similarityReviewColumnsAvailable()) {
            return [
                'confirmed_plagiarism_students' => 0,
                'pending_similarity_students' => 0,
            ];
        }

        $rows = static::similarityQuery(null, $start, $end, $programCategory)
            ->join('perpustakaan_literasi_responses as later', 'later.id', '=', 'perpustakaan_literasi_similarity_matches.later_response_id')
            ->whereNotNull('later.data_siswa_id')
            ->whereIn('review_status', [
                PerpustakaanLiterasiSimilarityMatch::REVIEW_SUSPECTED,
                PerpustakaanLiterasiSimilarityMatch::REVIEW_CONFIRMED,
            ])
            ->select('later.data_siswa_id')
            ->selectRaw(
                'max(case when review_status = ? then 1 else 0 end) as has_confirmed',
                [PerpustakaanLiterasiSimilarityMatch::REVIEW_CONFIRMED],
            )
            ->selectRaw(
                'max(case when review_status = ? then 1 else 0 end) as has_suspected',
                [PerpustakaanLiterasiSimilarityMatch::REVIEW_SUSPECTED],
            )
            ->groupBy('later.data_siswa_id')
            ->get();

        return [
            'confirmed_plagiarism_students' => $rows->where('has_confirmed', 1)->count(),
            'pending_similarity_students' => $rows
                ->filter(fn ($row): bool => (int) $row->has_confirmed === 0 && (int) $row->has_suspected === 1)
                ->count(),
        ];
    }

    protected static function responseCountsByClass(
        Carbon $start,
        Carbon $end,
        ?PerpustakaanLiterasiMaterial $material,
        ?string $programCategory = null
    ): Collection {
        return static::responseQuery($material, $programCategory)
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

    protected static function responseQuery(?PerpustakaanLiterasiMaterial $material, ?string $programCategory = null): Builder
    {
        return PerpustakaanLiterasiResponse::query()
            ->whereNotNull('submitted_at')
            ->when($material, fn (Builder $query): Builder => $query->where('material_id', $material->getKey()))
            ->when($programCategory, fn (Builder $query): Builder => static::constrainResponseCategory($query, $programCategory));
    }

    protected static function dispensationQuery(?PerpustakaanLiterasiMaterial $material, ?string $programCategory = null): Builder
    {
        return PerpustakaanLiterasiDispensation::query()
            ->whereNotNull('confirmed_at')
            ->when($material, fn (Builder $query): Builder => $query->where('material_id', $material->getKey()))
            ->when(
                $programCategory,
                fn (Builder $query): Builder => $query->whereIn(
                    'material_id',
                    static::materialIdsForCategoryQuery($programCategory),
                ),
            );
    }

    protected static function similarityQuery(
        ?PerpustakaanLiterasiMaterial $material,
        Carbon $start,
        Carbon $end,
        ?string $programCategory = null
    ): Builder {
        return PerpustakaanLiterasiSimilarityMatch::query()
            ->when($material, fn (Builder $query): Builder => $query->where('perpustakaan_literasi_similarity_matches.material_id', $material->getKey()))
            ->when($programCategory, fn (Builder $query): Builder => static::constrainSimilarityCategory($query, $programCategory))
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

    /**
     * @return array<string, string>
     */
    protected static function programCategoryScopes(): array
    {
        return [
            '__blank' => PerpustakaanLiterasiMaterial::uncategorizedProgramLabel(),
        ] + PerpustakaanLiterasiMaterial::programCategoryOptions();
    }

    protected static function constrainResponseCategory(Builder $query, string $programCategory): Builder
    {
        return $query->whereIn('material_id', static::materialIdsForCategoryQuery($programCategory));
    }

    protected static function constrainJoinedResponseCategory(Builder $query, string $programCategory): Builder
    {
        return $query->whereIn('responses.material_id', static::materialIdsForCategoryQuery($programCategory));
    }

    protected static function constrainSimilarityCategory(Builder $query, string $programCategory): Builder
    {
        return $query->whereIn('perpustakaan_literasi_similarity_matches.material_id', static::materialIdsForCategoryQuery($programCategory));
    }

    protected static function materialIdsForCategoryQuery(string $programCategory): Builder
    {
        return PerpustakaanLiterasiMaterial::query()
            ->select('id')
            ->when(
                $programCategory === '__blank',
                fn (Builder $query): Builder => $query->where(function (Builder $inner): void {
                    $inner->whereNull('program_category')->orWhere('program_category', '');
                }),
                fn (Builder $query): Builder => $query->where('program_category', $programCategory),
            );
    }

    protected static function gradingColumnsAvailable(): bool
    {
        return Schema::hasTable('perpustakaan_literasi_answers')
            && Schema::hasColumn('perpustakaan_literasi_answers', 'is_correct')
            && Schema::hasColumn('perpustakaan_literasi_answers', 'score_earned')
            && Schema::hasColumn('perpustakaan_literasi_answers', 'score_possible');
    }

    protected static function similarityReviewColumnsAvailable(): bool
    {
        return Schema::hasTable('perpustakaan_literasi_similarity_matches')
            && Schema::hasColumn('perpustakaan_literasi_similarity_matches', 'review_status');
    }

    protected static function dispensationTableAvailable(): bool
    {
        return Schema::hasTable('perpustakaan_literasi_dispensations');
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
