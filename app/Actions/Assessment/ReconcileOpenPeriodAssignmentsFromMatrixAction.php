<?php

namespace App\Actions\Assessment;

use App\Enums\Assessment\AssessmentPeriodStatus;
use App\Enums\Assessment\AssessmentType;
use App\Enums\Assessment\AssignmentStatus;
use App\Models\Assessment\AssessmentPeriod;
use App\Models\Assessment\AssessmentPeriodAssignment;
use App\Models\Assessment\AssessmentPeriodHomeroom;
use App\Models\Assessment\AssessmentPeriodRombel;
use App\Models\Assessment\HomeroomAssignment;
use App\Models\Assessment\TeachingAssignment;
use App\Models\User;
use App\Support\Assessment\AssessmentAuditLogger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Adds only absent class-subject snapshots to open periods after the teaching
 * matrix changes. Existing assignments retain their scores and workflow state.
 */
final class ReconcileOpenPeriodAssignmentsFromMatrixAction
{
    public function __construct(private readonly AssessmentAuditLogger $audit) {}

    public function forSemester(int $semesterId, ?User $actor = null): int
    {
        return AssessmentPeriod::query()
            ->where('assessment_semester_id', $semesterId)
            ->where('status', AssessmentPeriodStatus::OPEN->value)
            ->pluck('id')
            ->sum(fn (int $periodId): int => $this->forPeriod($periodId, $actor));
    }

    public function forOpenPeriodsOfType(AssessmentType $type, ?User $actor = null): int
    {
        return AssessmentPeriod::query()
            ->where('type', $type->value)
            ->where('status', AssessmentPeriodStatus::OPEN->value)
            ->pluck('id')
            ->sum(fn (int $periodId): int => $this->forPeriod($periodId, $actor));
    }

    /**
     * Reconciles homeroom snapshots without touching scores, reports, or
     * assignment workflow state. A blank/inactive matrix row intentionally
     * leaves the existing snapshot untouched.
     */
    public function homeroomsForSemester(int $semesterId, ?User $actor = null): int
    {
        return AssessmentPeriod::query()
            ->where('assessment_semester_id', $semesterId)
            ->where('status', AssessmentPeriodStatus::OPEN->value)
            ->pluck('id')
            ->sum(fn (int $periodId): int => $this->homeroomsForPeriod($periodId, $actor));
    }

    public function homeroomsForOpenPeriods(?User $actor = null): int
    {
        return AssessmentPeriod::query()
            ->where('status', AssessmentPeriodStatus::OPEN->value)
            ->pluck('id')
            ->sum(fn (int $periodId): int => $this->homeroomsForPeriod($periodId, $actor));
    }

    public function homeroomsForOpenPeriodsOfType(AssessmentType $type, ?User $actor = null): int
    {
        return AssessmentPeriod::query()
            ->where('type', $type->value)
            ->where('status', AssessmentPeriodStatus::OPEN->value)
            ->pluck('id')
            ->sum(fn (int $periodId): int => $this->homeroomsForPeriod($periodId, $actor));
    }

    public function homeroomsForPeriod(int $periodId, ?User $actor = null): int
    {
        return DB::transaction(function () use ($periodId, $actor): int {
            /** @var AssessmentPeriod|null $period */
            $period = AssessmentPeriod::query()->lockForUpdate()->find($periodId);
            if (! $period || $period->status !== AssessmentPeriodStatus::OPEN) {
                return 0;
            }

            $snapshots = AssessmentPeriodHomeroom::query()
                ->where('assessment_period_id', $period->getKey())
                ->with('periodRombel:id,source_rombel_id')
                ->lockForUpdate()
                ->get();
            if ($snapshots->isEmpty()) {
                return 0;
            }

            $homerooms = HomeroomAssignment::query()
                ->where('assessment_semester_id', $period->assessment_semester_id)
                ->where('is_active', true)
                ->whereIn('rombel_id', $snapshots->pluck('periodRombel.source_rombel_id')->filter())
                ->lockForUpdate()
                ->get()
                ->keyBy('rombel_id');

            $updated = 0;
            foreach ($snapshots as $snapshot) {
                $homeroom = $homerooms->get($snapshot->periodRombel?->source_rombel_id);
                if (! $homeroom) {
                    continue;
                }

                $values = [
                    'source_homeroom_assignment_id' => $homeroom->getKey(),
                    'teacher_id' => $homeroom->teacher_id,
                    'teacher_name_snapshot' => $homeroom->teacher_name_snapshot,
                    'rombel_name_snapshot' => $homeroom->rombel_name_snapshot,
                ];
                if ($snapshot->only(array_keys($values)) === $values) {
                    continue;
                }

                $old = $snapshot->only(array_keys($values));
                $snapshot->forceFill($values)->save();
                $this->audit->record(
                    actor: $actor,
                    event: 'homeroom.teacher_snapshot_synchronized_from_matrix',
                    subject: $snapshot,
                    oldValues: $old,
                    newValues: $snapshot->only(array_keys($values)),
                    reason: 'Rekonsiliasi menyelaraskan wali kelas aktif pada periode terbuka dengan Matriks Penugasan.',
                );
                $updated++;
            }

            return $updated;
        }, 3);
    }

    public function forPeriod(int $periodId, ?User $actor = null): int
    {
        return DB::transaction(function () use ($periodId, $actor): int {
            /** @var AssessmentPeriod|null $period */
            $period = AssessmentPeriod::query()->lockForUpdate()->find($periodId);
            if (! $period || $period->status !== AssessmentPeriodStatus::OPEN) {
                return 0;
            }

            /** @var Collection<int, AssessmentPeriodRombel> $periodRombels */
            $periodRombels = AssessmentPeriodRombel::query()
                ->where('assessment_period_id', $period->getKey())
                ->where('is_active', true)
                ->lockForUpdate()
                ->get()
                ->keyBy('source_rombel_id');
            if ($periodRombels->isEmpty()) {
                return 0;
            }

            /** @var Collection<int, TeachingAssignment> $teaching */
            $teaching = TeachingAssignment::query()
                ->with(['category', 'subject'])
                ->where('assessment_semester_id', $period->assessment_semester_id)
                ->where('is_active', true)
                ->whereIn('rombel_id', $periodRombels->keys())
                ->lockForUpdate()
                ->get();
            if ($teaching->isEmpty()) {
                return 0;
            }

            $existing = AssessmentPeriodAssignment::query()
                ->where('assessment_period_id', $period->getKey())
                ->lockForUpdate()
                ->get()
                ->mapWithKeys(fn (AssessmentPeriodAssignment $assignment): array => [
                    $assignment->assessment_period_rombel_id.'|'.$assignment->assessment_subject_id => true,
                ]);

            $created = 0;
            foreach ($teaching as $master) {
                /** @var AssessmentPeriodRombel|null $periodRombel */
                $periodRombel = $periodRombels->get($master->rombel_id);
                if (! $periodRombel) {
                    continue;
                }

                $key = $periodRombel->getKey().'|'.$master->assessment_subject_id;
                if ($existing->has($key)) {
                    continue;
                }

                $assignment = AssessmentPeriodAssignment::query()->create([
                    'assessment_period_id' => $period->getKey(),
                    'assessment_period_rombel_id' => $periodRombel->getKey(),
                    'source_teaching_assignment_id' => $master->getKey(),
                    'teacher_id' => $master->teacher_id,
                    'assessment_subject_id' => $master->assessment_subject_id,
                    'teacher_name_snapshot' => $master->teacher_name_snapshot,
                    'subject_name_snapshot' => $master->subject_name_snapshot,
                    'subject_group_code_snapshot' => $master->category?->code ?: ($master->subject?->report_group_code ?: 'BELUM'),
                    'subject_group_name_snapshot' => $master->category?->name ?: ($master->subject?->report_group_name ?: 'Belum Dikelompokkan'),
                    'subject_group_sort_order_snapshot' => (int) ($master->category?->sort_order ?? $master->subject?->report_group_sort_order ?? 999),
                    'subject_sort_order_snapshot' => (int) ($master->subject?->sort_order ?? 0),
                    'rombel_name_snapshot' => $periodRombel->rombel_name_snapshot,
                    'status' => AssignmentStatus::DRAFT,
                    'lock_version' => 1,
                ]);
                $existing->put($key, true);
                $this->audit->record(
                    actor: $actor,
                    event: 'assignment.added_from_matrix_reconciliation',
                    subject: $assignment,
                    newValues: $assignment->only([
                        'source_teaching_assignment_id', 'teacher_id', 'assessment_subject_id',
                        'assessment_period_rombel_id', 'status', 'lock_version',
                    ]),
                    reason: 'Matriks Penugasan menambahkan kelas-mapel yang belum tersnapshot pada periode terbuka.',
                );
                $created++;
            }

            return $created;
        }, 3);
    }
}
