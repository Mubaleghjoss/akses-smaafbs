<?php

namespace App\Actions\Assessment;

use App\Enums\Assessment\AssignmentStatus;
use App\Enums\Assessment\AssessmentPeriodStatus;
use App\Enums\Assessment\AssessmentType;
use App\Models\Assessment\AssessmentPeriod;
use App\Models\Assessment\AssessmentPeriodAssignment;
use App\Models\Assessment\HomeroomReport;
use App\Models\Assessment\ReportSnapshot;
use App\Models\Assessment\ReportTemplate;
use App\Models\User;
use App\Support\Assessment\AssessmentAuditLogger;
use App\Support\Assessment\AssessmentWorkflowGuard;
use App\Support\Assessment\Reporting\CreateReportSnapshotsAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class LockAssessmentPeriodAction
{
    use AuthorizesAssessmentAction;

    public function __construct(
        private readonly AssessmentWorkflowGuard $guard,
        private readonly AssessmentAuditLogger $audit,
        private readonly CreateReportSnapshotsAction $createReportSnapshots,
    ) {}

    public function execute(User $actor, AssessmentPeriod $period): AssessmentPeriod
    {
        $this->authorizePermission($actor, 'penilaian.period.manage');

        return DB::transaction(function () use ($actor, $period): AssessmentPeriod {
            /** @var AssessmentPeriod $locked */
            $locked = AssessmentPeriod::query()->lockForUpdate()->findOrFail($period->getKey());

            if ($this->isStatus($locked, AssessmentPeriodStatus::LOCKED)) {
                $this->authorize($actor, 'view', $locked);

                return $locked->refresh();
            }

            $this->authorize($actor, 'lock', $locked);
            $this->guard->periodStatus(
                $locked,
                [AssessmentPeriodStatus::VERIFICATION],
                'Periode hanya dapat dikunci setelah tahap verifikasi.',
            );
            $assignments = AssessmentPeriodAssignment::query()
                ->where('assessment_period_id', $locked->getKey())
                ->lockForUpdate()
                ->get();

            if ($assignments->isEmpty()) {
                throw ValidationException::withMessages([
                    'assignments' => 'Periode belum memiliki snapshot penugasan.',
                ]);
            }

            $unverified = $assignments->reject(
                fn (AssessmentPeriodAssignment $assignment): bool => in_array(
                    $assignment->status,
                    [AssignmentStatus::VERIFIED, AssignmentStatus::LOCKED],
                    true,
                ),
            );

            if ($unverified->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'assignments' => "{$unverified->count()} penugasan belum diverifikasi.",
                ]);
            }

            $expectedResults = $assignments->sum(function (AssessmentPeriodAssignment $assignment): int {
                return (int) DB::table('assessment_period_students')
                    ->where('assessment_period_id', $assignment->assessment_period_id)
                    ->where('assessment_period_rombel_id', $assignment->assessment_period_rombel_id)
                    ->where('is_active', true)
                    ->count();
            });
            $completeResults = (int) DB::table('assessment_student_subject_results as results')
                ->join(
                    'assessment_period_students as result_students',
                    'result_students.id',
                    '=',
                    'results.assessment_period_student_id',
                )
                ->join(
                    'assessment_period_assignments as result_assignments',
                    'result_assignments.id',
                    '=',
                    'results.assessment_period_assignment_id',
                )
                ->where('results.assessment_period_id', $locked->getKey())
                ->where('result_students.is_active', true)
                ->whereColumn(
                    'result_students.assessment_period_rombel_id',
                    'result_assignments.assessment_period_rombel_id',
                )
                ->whereNotNull('results.final_score')
                ->count();

            if ($completeResults !== $expectedResults) {
                throw ValidationException::withMessages([
                    'results' => "Hasil nilai belum lengkap ({$completeResults} dari {$expectedResults}).",
                ]);
            }

            if ($this->periodType($locked) === AssessmentType::ASAS) {
                $activeStudentIds = $locked->students()->where('is_active', true)->pluck('id');
                $homeroomCount = HomeroomReport::query()
                    ->where('assessment_period_id', $locked->getKey())
                    ->whereIn('assessment_period_student_id', $activeStudentIds)
                    ->count();

                if ($homeroomCount !== $activeStudentIds->count()) {
                    throw ValidationException::withMessages([
                        'homeroom_reports' => 'Rekap wali kelas ASAS harus tersedia untuk seluruh siswa aktif sebelum periode dikunci.',
                    ]);
                }
            }

            $template = ReportTemplate::query()
                ->where('type', $this->periodType($locked)->value)
                ->where('is_active', true)
                ->where(function ($query) use ($locked): void {
                    $query->whereNull('effective_from')
                        ->orWhereDate('effective_from', '<=', $locked->report_date ?? now()->toDateString());
                })
                ->orderByDesc('version')
                ->orderByDesc('id')
                ->first();

            if (! $template) {
                throw ValidationException::withMessages([
                    'template' => 'Belum ada template rapor aktif yang sesuai dengan jenis periode.',
                ]);
            }

            foreach ($assignments as $assignment) {
                if ($assignment->status === AssignmentStatus::LOCKED) {
                    continue;
                }

                $oldVersion = (int) $assignment->lock_version;
                $assignment->forceFill([
                    'status' => AssignmentStatus::LOCKED,
                    'locked_at' => now(),
                    'locked_by' => $actor->getKey(),
                    'lock_version' => $oldVersion + 1,
                ])->save();
                $this->audit->record(
                    actor: $actor,
                    event: 'assignment.locked',
                    subject: $assignment,
                    oldValues: [
                        'status' => AssignmentStatus::VERIFIED->value,
                        'lock_version' => $oldVersion,
                    ],
                    newValues: [
                        'status' => AssignmentStatus::LOCKED->value,
                        'lock_version' => $assignment->lock_version,
                        'locked_at' => $assignment->locked_at?->toISOString(),
                        'locked_by' => $actor->getKey(),
                    ],
                );
            }

            $locked->forceFill(['status' => AssessmentPeriodStatus::LOCKED])->save();
            $hasPreviousSnapshots = ReportSnapshot::query()
                ->where('assessment_period_id', $locked->getKey())
                ->exists();
            $reopenReason = data_get($locked->settings, '_workflow.last_reopen_reason');
            $snapshots = $this->createReportSnapshots->execute(
                period: $locked,
                template: $template,
                generatedBy: (int) $actor->getKey(),
                regenerate: $hasPreviousSnapshots,
                reason: $hasPreviousSnapshots
                    ? (filled($reopenReason) ? (string) $reopenReason : 'Pembuatan revisi setelah periode dibuka kembali.')
                    : null,
            );
            $this->audit->record(
                actor: $actor,
                event: 'period.locked',
                subject: $locked,
                oldValues: ['status' => AssessmentPeriodStatus::VERIFICATION->value],
                newValues: [
                    'status' => AssessmentPeriodStatus::LOCKED->value,
                    'assignment_count' => $assignments->count(),
                    'report_snapshot_count' => $snapshots->count(),
                    'report_revision' => $snapshots->max('revision'),
                ],
            );

            return $locked->refresh();
        }, 3);
    }

    private function isStatus(AssessmentPeriod $period, AssessmentPeriodStatus $status): bool
    {
        return ($period->status instanceof AssessmentPeriodStatus
            ? $period->status
            : AssessmentPeriodStatus::tryFrom((string) $period->status)) === $status;
    }

    private function periodType(AssessmentPeriod $period): AssessmentType
    {
        return $period->type instanceof AssessmentType
            ? $period->type
            : AssessmentType::from((string) $period->type);
    }
}
