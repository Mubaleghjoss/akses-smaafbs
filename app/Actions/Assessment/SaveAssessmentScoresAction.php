<?php

namespace App\Actions\Assessment;

use App\Enums\Assessment\AssessmentPeriodStatus;
use App\Enums\Assessment\AssignmentStatus;
use App\Enums\Assessment\ScoreSource;
use App\Models\Assessment\AssessmentComponent;
use App\Models\Assessment\AssessmentPeriodAssignment;
use App\Models\Assessment\AssessmentPeriodStudent;
use App\Models\Assessment\AssessmentScore;
use App\Models\Assessment\StudentSubjectResult;
use App\Models\User;
use App\Support\Assessment\AssessmentAstsSourceResolver;
use App\Support\Assessment\AssessmentAuditLogger;
use App\Support\Assessment\AssessmentSchemeResolver;
use App\Support\Assessment\AssessmentWorkflowGuard;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class SaveAssessmentScoresAction
{
    use AuthorizesAssessmentAction;

    public function __construct(
        private readonly AssessmentWorkflowGuard $guard,
        private readonly AssessmentSchemeResolver $schemeResolver,
        private readonly AssessmentAstsSourceResolver $astsSourceResolver,
        private readonly CalculateStudentSubjectResultAction $calculateResult,
        private readonly AssessmentAuditLogger $audit,
    ) {}

    /**
     * @param  array<int, array{
     *     assessment_period_student_id:int,
     *     scores:array<int|string, mixed>,
     *     description?:?string
     * }>  $rows
     */
    public function execute(
        User $actor,
        AssessmentPeriodAssignment $assignment,
        array $rows,
        int $expectedLockVersion,
    ): AssessmentPeriodAssignment {
        $this->authorizePermission($actor, 'penilaian.input');

        $canonicalRows = $this->canonicalizeRows($rows);

        return DB::transaction(function () use (
            $actor,
            $assignment,
            $canonicalRows,
            $expectedLockVersion,
        ): AssessmentPeriodAssignment {
            /** @var AssessmentPeriodAssignment $locked */
            $locked = AssessmentPeriodAssignment::query()
                ->lockForUpdate()
                ->findOrFail($assignment->getKey());
            $this->authorize($actor, 'updateScores', $locked);
            $locked->load('period');
            $isVerifiedCorrection = $this->isVerifiedCorrection($locked);
            $this->guard->periodStatus(
                $locked->period,
                $isVerifiedCorrection
                    ? [AssessmentPeriodStatus::OPEN, AssessmentPeriodStatus::VERIFICATION]
                    : [AssessmentPeriodStatus::OPEN],
                'Nilai hanya dapat disimpan ketika periode berstatus terbuka.',
            );
            if ($locked->status === AssignmentStatus::DRAFT) {
                $this->guard->entryWindow(
                    $locked->period,
                    Gate::forUser($actor)->allows('overrideScoreEntryDeadline', $locked),
                );
            }
            $this->guard->assignmentStatus(
                $locked,
                $isVerifiedCorrection
                    ? [AssignmentStatus::DRAFT, AssignmentStatus::RETURNED, AssignmentStatus::SUBMITTED, AssignmentStatus::VERIFIED]
                    : [AssignmentStatus::DRAFT, AssignmentStatus::RETURNED],
                'Nilai tidak dapat diubah setelah dikirim atau dikunci.',
            );
            $scheme = $this->schemeResolver->forAssignment($locked);
            $manualComponentIds = $scheme->components
                ->filter(fn (AssessmentComponent $component): bool => $this->scoreSource($component) === ScoreSource::MANUAL)
                ->map(fn (AssessmentComponent $component): string => (string) $component->getKey())
                ->all();

            if ((int) $locked->lock_version !== $expectedLockVersion) {
                if ($this->payloadAlreadyApplied($locked, $canonicalRows, $manualComponentIds)) {
                    return $locked->refresh();
                }

                throw ValidationException::withMessages([
                    'lock_version' => 'Data nilai telah berubah di tab lain. Muat ulang halaman sebelum menyimpan kembali.',
                ]);
            }

            if ($canonicalRows === []) {
                return $locked;
            }

            /** @var \Illuminate\Database\Eloquent\Collection<int, AssessmentComponent> $components */
            $components = $scheme->components->keyBy(fn (AssessmentComponent $component): string => (string) $component->getKey());
            $changedScoreIds = [];

            foreach ($canonicalRows as $row) {
                /** @var AssessmentPeriodStudent $student */
                $student = AssessmentPeriodStudent::query()
                    ->whereKey($row['assessment_period_student_id'])
                    ->where('assessment_period_id', $locked->assessment_period_id)
                    ->where('assessment_period_rombel_id', $locked->assessment_period_rombel_id)
                    ->where('is_active', true)
                    ->eligibleForScoreEntry()
                    ->first();

                if (! $student) {
                    throw ValidationException::withMessages([
                        'students' => 'Salah satu siswa tidak termasuk dalam snapshot kelas penugasan.',
                    ]);
                }

                foreach ($row['scores'] as $componentId => $scoreInput) {
                    /** @var AssessmentComponent|null $component */
                    $component = $components->get((string) $componentId);

                    if (! $component) {
                        throw ValidationException::withMessages([
                            "scores.{$student->getKey()}.{$componentId}" => 'Komponen tidak termasuk dalam skema penugasan.',
                        ]);
                    }

                    if ($this->scoreSource($component) !== ScoreSource::MANUAL) {
                        // The UI submits one matrix row, including disabled
                        // reference cells. Never trust that client value: skip
                        // it and populate the authoritative snapshot below.
                        continue;
                    }

                    $changedScoreIds[] = $this->persistScore(
                        actor: $actor,
                        assignment: $locked,
                        student: $student,
                        component: $component,
                        score: $scoreInput['score'],
                        notes: $scoreInput['notes'],
                        source: ScoreSource::MANUAL,
                        isVerifiedCorrection: $isVerifiedCorrection,
                    );
                }

                foreach ($components as $component) {
                    if ($this->scoreSource($component) !== ScoreSource::ASTS_SNAPSHOT) {
                        continue;
                    }

                    $existingSnapshot = AssessmentScore::query()
                        ->where('assessment_period_assignment_id', $locked->getKey())
                        ->where('assessment_period_student_id', $student->getKey())
                        ->where('assessment_component_id', $component->getKey())
                        ->first();

                    // Referensi ASTS adalah snapshot satu kali. Setelah angka
                    // atau source result pertama tersalin, save ASAS berikutnya
                    // tidak boleh mengikuti koreksi ASTS yang terjadi kemudian.
                    if ($existingSnapshot
                        && (
                            $existingSnapshot->source_result_id !== null
                            || $existingSnapshot->source_score_snapshot !== null
                        )) {
                        $changedScoreIds[] = (int) $existingSnapshot->getKey();
                        continue;
                    }

                    $sourceResult = $this->astsSourceResolver->forStudent($locked, $student, $component);
                    $changedScoreIds[] = $this->persistScore(
                        actor: $actor,
                        assignment: $locked,
                        student: $student,
                        component: $component,
                        score: $sourceResult?->final_score,
                        notes: null,
                        source: ScoreSource::ASTS_SNAPSHOT,
                        sourceResult: $sourceResult,
                        isVerifiedCorrection: $isVerifiedCorrection,
                    );
                }

                $oldResult = StudentSubjectResult::query()
                    ->where('assessment_period_assignment_id', $locked->getKey())
                    ->where('assessment_period_student_id', $student->getKey())
                    ->first();
                $oldResultValues = $this->resultAuditValues($oldResult);
                $result = $this->calculateResult->execute(
                    assignment: $locked,
                    student: $student,
                    descriptionOverride: $row['description'],
                    hasDescriptionOverride: $row['has_description'],
                );
                $newResultValues = $this->resultAuditValues($result);
                if ($isVerifiedCorrection && $oldResultValues !== $newResultValues) {
                    $this->audit->record(
                        actor: $actor,
                        event: 'result.verified_corrected',
                        subject: $result,
                        oldValues: $oldResultValues,
                        newValues: $newResultValues + [
                            'assignment_id' => (int) $locked->getKey(),
                            'student_id' => (int) $student->getKey(),
                            'actor_role_context' => $this->actorRoleContext($actor),
                        ],
                    );
                }
            }

            $oldVersion = (int) $locked->lock_version;
            $locked->forceFill(['lock_version' => $oldVersion + 1])->save();
            $this->audit->record(
                actor: $actor,
                event: 'scores.batch_saved',
                subject: $locked,
                oldValues: ['lock_version' => $oldVersion],
                newValues: [
                    'lock_version' => $oldVersion + 1,
                    'student_count' => count($canonicalRows),
                    'score_ids' => array_values(array_unique($changedScoreIds)),
                ],
            );

            return $locked->refresh();
        }, 3);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array{
     *     assessment_period_student_id:int,
     *     scores:array<string, array{score:mixed,notes:?string}>,
     *     description:?string,
     *     has_description:bool
     * }>
     */
    private function canonicalizeRows(array $rows): array
    {
        $canonical = [];

        foreach ($rows as $index => $row) {
            $studentId = (int) ($row['assessment_period_student_id'] ?? 0);

            if ($studentId <= 0) {
                throw ValidationException::withMessages([
                    "rows.{$index}.assessment_period_student_id" => 'Siswa wajib dipilih.',
                ]);
            }

            if (isset($canonical[$studentId])) {
                throw ValidationException::withMessages([
                    "rows.{$index}.assessment_period_student_id" => 'Siswa yang sama tidak boleh dikirim dua kali dalam satu batch.',
                ]);
            }

            $scores = [];

            foreach ((array) ($row['scores'] ?? []) as $componentKey => $input) {
                $data = is_array($input) ? $input : ['score' => $input];
                $componentId = (int) ($data['assessment_component_id'] ?? $data['component_id'] ?? $componentKey);

                if ($componentId <= 0 || isset($scores[(string) $componentId])) {
                    throw ValidationException::withMessages([
                        "rows.{$index}.scores" => 'Komponen nilai tidak valid atau dikirim ganda.',
                    ]);
                }

                $score = $data['score'] ?? null;

                if ($score !== null && $score !== '' && ! is_numeric($score)) {
                    throw ValidationException::withMessages([
                        "rows.{$index}.scores.{$componentId}" => 'Nilai harus berupa angka atau dikosongkan.',
                    ]);
                }

                $scores[(string) $componentId] = [
                    'score' => $score === '' ? null : $score,
                    'notes' => filled($data['notes'] ?? null) ? trim((string) $data['notes']) : null,
                ];
            }

            $canonical[$studentId] = [
                'assessment_period_student_id' => $studentId,
                'scores' => $scores,
                'description' => array_key_exists('description', $row)
                    ? (filled($row['description']) ? trim((string) $row['description']) : null)
                    : null,
                // A null value is the UI's "not loaded yet" state. An empty
                // string remains an explicit request to clear the description.
                'has_description' => array_key_exists('description', $row)
                    && $row['description'] !== null,
            ];
        }

        return array_values($canonical);
    }

    /**
     * Retry-safe comparison for a request whose response was lost after commit.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<int, string>  $manualComponentIds
     */
    private function payloadAlreadyApplied(
        AssessmentPeriodAssignment $assignment,
        array $rows,
        array $manualComponentIds,
    ): bool
    {
        foreach ($rows as $row) {
            foreach ($row['scores'] as $componentId => $input) {
                if (! in_array((string) $componentId, $manualComponentIds, true)) {
                    continue;
                }

                $score = AssessmentScore::query()
                    ->where('assessment_period_assignment_id', $assignment->getKey())
                    ->where('assessment_period_student_id', $row['assessment_period_student_id'])
                    ->where('assessment_component_id', $componentId)
                    ->first();

                if (! $score
                    || ! $this->sameNumeric($score->score, $input['score'])
                    || $this->normalizeNullableText($score->notes) !== $this->normalizeNullableText($input['notes'])) {
                    return false;
                }
            }

            if ($row['has_description']) {
                $description = StudentSubjectResult::query()
                    ->where('assessment_period_assignment_id', $assignment->getKey())
                    ->where('assessment_period_student_id', $row['assessment_period_student_id'])
                    ->value('description');

                if ($this->normalizeNullableText($description) !== $this->normalizeNullableText($row['description'])) {
                    return false;
                }
            }
        }

        return true;
    }

    private function persistScore(
        User $actor,
        AssessmentPeriodAssignment $assignment,
        AssessmentPeriodStudent $student,
        AssessmentComponent $component,
        mixed $score,
        ?string $notes,
        ScoreSource $source,
        ?StudentSubjectResult $sourceResult = null,
        bool $isVerifiedCorrection = false,
    ): int {
        $record = AssessmentScore::query()->firstOrNew([
            'assessment_period_assignment_id' => $assignment->getKey(),
            'assessment_period_student_id' => $student->getKey(),
            'assessment_component_id' => $component->getKey(),
        ]);
        $oldValues = $record->exists
            ? Arr::only($record->getAttributes(), [
                'score',
                'notes',
                'source',
                'source_result_id',
                'source_score_snapshot',
            ])
            : [];
        $record->fill([
            'score' => $score === null ? null : (float) $score,
            'notes' => $notes,
            'source' => $source,
            'source_result_id' => $sourceResult?->getKey(),
            'source_score_snapshot' => $sourceResult?->final_score,
            'entered_by' => $record->entered_by ?: $actor->getKey(),
            'updated_by' => $actor->getKey(),
        ]);

        if (! $record->exists || $record->isDirty()) {
            $record->save();
            $this->audit->record(
                actor: $actor,
                event: $isVerifiedCorrection ? 'score.verified_corrected' : ($oldValues === [] ? 'score.created' : 'score.updated'),
                subject: $record,
                oldValues: $oldValues,
                newValues: Arr::only($record->getAttributes(), [
                    'score',
                    'notes',
                    'source',
                    'source_result_id',
                    'source_score_snapshot',
                ]) + ($isVerifiedCorrection ? [
                    'assignment_id' => (int) $assignment->getKey(),
                    'student_id' => (int) $student->getKey(),
                    'component_id' => (int) $component->getKey(),
                    'component_key' => (string) $component->code,
                    'actor_role_context' => $this->actorRoleContext($actor),
                ] : []),
            );
        }

        return (int) $record->getKey();
    }

    /**
     * Include the calculation state so a cleared required component has an
     * auditable transition from a complete result to an incomplete one.
     *
     * @return array<string, mixed>
     */
    private function resultAuditValues(?StudentSubjectResult $result): array
    {
        if (! $result) {
            return [];
        }

        return [
            'final_score' => $result->final_score,
            'predicate' => $result->predicate,
            'description' => $result->description,
            'is_complete' => (bool) data_get($result->calculation_detail, 'complete', $result->final_score !== null),
            'missing_required_component_ids' => array_values((array) data_get($result->calculation_detail, 'missing_required_component_ids', [])),
        ];
    }

    private function scoreSource(AssessmentComponent $component): ScoreSource
    {
        return $component->score_source instanceof ScoreSource
            ? $component->score_source
            : ScoreSource::from((string) $component->score_source);
    }

    private function sameNumeric(mixed $left, mixed $right): bool
    {
        if (($left === null || $left === '') && ($right === null || $right === '')) {
            return true;
        }

        return is_numeric($left)
            && is_numeric($right)
            && abs((float) $left - (float) $right) < 0.000001;
    }

    private function normalizeNullableText(mixed $value): ?string
    {
        return filled($value) ? trim((string) $value) : null;
    }

    private function isVerifiedCorrection(AssessmentPeriodAssignment $assignment): bool
    {
        return in_array($assignment->status, [AssignmentStatus::SUBMITTED, AssignmentStatus::VERIFIED], true);
    }

    private function actorRoleContext(User $actor): string
    {
        return $actor->hasFullAdminAccess() ? 'admin' : 'kurikulum';
    }
}
