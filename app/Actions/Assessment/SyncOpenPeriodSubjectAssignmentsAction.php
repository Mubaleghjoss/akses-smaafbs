<?php

namespace App\Actions\Assessment;

use App\Enums\Assessment\AssessmentPeriodStatus;
use App\Enums\Assessment\AssignmentStatus;
use App\Models\Assessment\AssessmentPeriod;
use App\Models\Assessment\AssessmentPeriodAssignment;
use App\Models\Assessment\Subject;
use App\Models\Assessment\TeachingAssignment;
use App\Models\User;
use App\Support\Assessment\AssessmentAuditLogger;
use App\Support\Assessment\AssessmentCalculator;
use App\Support\Assessment\AssessmentSchemeResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SyncOpenPeriodSubjectAssignmentsAction
{
    use AuthorizesAssessmentAction;

    public function __construct(
        private readonly AssessmentSchemeResolver $schemeResolver,
        private readonly AssessmentCalculator $calculator,
        private readonly AssessmentAuditLogger $audit,
    ) {}

    /** @return array{created:int,updated:int,deleted:int} */
    public function execute(User $actor, Subject $subject, AssessmentPeriod $period): array
    {
        $this->authorizePermission($actor, 'penilaian.period.manage');

        if ($period->status !== AssessmentPeriodStatus::OPEN) {
            throw ValidationException::withMessages([
                'period' => 'Sinkronisasi plotting hanya tersedia ketika periode berstatus Terbuka.',
            ]);
        }

        if ((int) $period->assessment_semester_id <= 0) {
            throw ValidationException::withMessages(['period' => 'Semester periode tidak valid.']);
        }

        return DB::transaction(function () use ($actor, $subject, $period): array {
            $lockedPeriod = AssessmentPeriod::query()->lockForUpdate()->findOrFail($period->getKey());
            if ($lockedPeriod->status !== AssessmentPeriodStatus::OPEN) {
                throw ValidationException::withMessages(['period' => 'Status periode berubah. Muat ulang halaman.']);
            }

            $periodRombels = $lockedPeriod->periodRombels()
                ->where('is_active', true)
                ->get()
                ->keyBy(fn ($rombel): int => (int) $rombel->source_rombel_id);

            $desired = TeachingAssignment::query()
                ->with(['category', 'teacher.userAccount'])
                ->where('assessment_semester_id', $lockedPeriod->assessment_semester_id)
                ->where('assessment_subject_id', $subject->getKey())
                ->where('is_active', true)
                ->whereIn('rombel_id', $periodRombels->keys())
                ->get();

            $duplicateRombels = $desired->groupBy('rombel_id')->filter(fn ($rows): bool => $rows->count() > 1);
            if ($duplicateRombels->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'assignments' => 'Satu mapel dan kelas masih mempunyai lebih dari satu guru pada master. Perbaiki plotting terlebih dahulu.',
                ]);
            }

            foreach ($desired as $master) {
                if (! $master->category || ! $master->teacher?->userAccount) {
                    throw ValidationException::withMessages([
                        'assignments' => "Plotting {$master->rombel_name_snapshot} belum memiliki kategori atau akun guru tertaut.",
                    ]);
                }
            }

            $existing = AssessmentPeriodAssignment::query()
                ->with(['scores:id,assessment_period_assignment_id', 'results:id,assessment_period_assignment_id', 'periodRombel'])
                ->where('assessment_period_id', $lockedPeriod->getKey())
                ->where('assessment_subject_id', $subject->getKey())
                ->lockForUpdate()
                ->get();

            $existingBySourceRombel = $existing->groupBy(
                fn (AssessmentPeriodAssignment $assignment): int => (int) $assignment->periodRombel?->source_rombel_id,
            );
            if ($existingBySourceRombel->contains(fn ($rows): bool => $rows->count() > 1)) {
                throw ValidationException::withMessages([
                    'assignments' => 'Periode memiliki assignment ganda untuk mapel dan kelas yang sama. Sinkronisasi dibatalkan.',
                ]);
            }

            $desiredByRombel = $desired->keyBy(fn (TeachingAssignment $assignment): int => (int) $assignment->rombel_id);

            foreach ($existing as $assignment) {
                $sourceRombelId = (int) $assignment->periodRombel?->source_rombel_id;
                $master = $desiredByRombel->get($sourceRombelId);
                $status = $assignment->status instanceof AssignmentStatus
                    ? $assignment->status
                    : AssignmentStatus::from((string) $assignment->status);

                if (! $master) {
                    if ($status !== AssignmentStatus::DRAFT || $assignment->scores->isNotEmpty() || $assignment->results->isNotEmpty()) {
                        throw ValidationException::withMessages([
                            'assignments' => "Assignment {$assignment->rombel_name_snapshot} hanya dapat dihapus saat masih Draf dan benar-benar kosong; status saat ini {$status->label()}.",
                        ]);
                    }
                    continue;
                }

                $metadataChanged = (int) $assignment->teacher_id !== (int) $master->teacher_id
                    || (int) $assignment->source_teaching_assignment_id !== (int) $master->getKey()
                    || (string) $assignment->subject_group_code_snapshot !== (string) $master->category->code
                    || (string) $assignment->subject_group_name_snapshot !== (string) $master->category->name
                    || (int) $assignment->subject_group_sort_order_snapshot !== (int) $master->category->sort_order;

                if ($metadataChanged && ! $status->isEditable()) {
                    throw ValidationException::withMessages([
                        'assignments' => "Assignment {$assignment->rombel_name_snapshot} berstatus {$status->label()} dan harus dikembalikan sebelum guru/kategorinya diubah.",
                    ]);
                }
            }

            $summary = ['created' => 0, 'updated' => 0, 'deleted' => 0];

            foreach ($desired as $master) {
                $periodRombel = $periodRombels->get((int) $master->rombel_id);
                $assignment = $existingBySourceRombel->get((int) $master->rombel_id)?->first();
                $values = [
                    'source_teaching_assignment_id' => $master->getKey(),
                    'teacher_id' => $master->teacher_id,
                    'teacher_name_snapshot' => $master->teacher_name_snapshot,
                    'subject_name_snapshot' => $subject->name,
                    'subject_group_code_snapshot' => $master->category->code,
                    'subject_group_name_snapshot' => $master->category->name,
                    'subject_group_sort_order_snapshot' => (int) $master->category->sort_order,
                    'subject_sort_order_snapshot' => (int) $subject->sort_order,
                    'rombel_name_snapshot' => $periodRombel->rombel_name_snapshot,
                ];

                if ($assignment) {
                    $old = $assignment->only(array_keys($values));
                    if ($old !== $values) {
                        $old['lock_version'] = (int) $assignment->lock_version;
                        $assignment->forceFill([...$values, 'lock_version' => (int) $assignment->lock_version + 1])->save();
                        $this->audit->record(
                            actor: $actor,
                            event: 'assignment.teacher_category_synchronized',
                            subject: $assignment,
                            oldValues: $old,
                            newValues: [...$values, 'lock_version' => (int) $assignment->lock_version],
                            reason: 'Sinkronisasi eksplisit plotting guru-mapel-kelas.',
                        );
                        $summary['updated']++;
                    }
                    continue;
                }

                $assignment = AssessmentPeriodAssignment::query()->create([
                    'assessment_period_id' => $lockedPeriod->getKey(),
                    'assessment_period_rombel_id' => $periodRombel->getKey(),
                    'assessment_subject_id' => $subject->getKey(),
                    ...$values,
                    'status' => AssignmentStatus::DRAFT,
                    'lock_version' => 1,
                ]);
                $scheme = $this->schemeResolver->forAssignment($assignment);
                $this->calculator->calculate($scheme->components, [], $scheme);
                $this->audit->record(
                    actor: $actor,
                    event: 'assignment.added_from_master',
                    subject: $assignment,
                    newValues: $values,
                    reason: 'Sinkronisasi eksplisit plotting guru-mapel-kelas.',
                );
                $summary['created']++;
            }

            foreach ($existing as $assignment) {
                $sourceRombelId = (int) $assignment->periodRombel?->source_rombel_id;
                if ($desiredByRombel->has($sourceRombelId)) {
                    continue;
                }
                $this->audit->record(
                    actor: $actor,
                    event: 'assignment.empty_removed_from_period',
                    subject: $assignment,
                    oldValues: $assignment->only(['teacher_id', 'assessment_subject_id', 'rombel_name_snapshot', 'status']),
                    reason: 'Sinkronisasi eksplisit plotting guru-mapel-kelas.',
                );
                $assignment->delete();
                $summary['deleted']++;
            }

            return $summary;
        }, 3);
    }
}
