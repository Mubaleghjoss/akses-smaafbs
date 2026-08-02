<?php

namespace App\Actions\Assessment;

use App\Enums\Assessment\AssessmentPeriodStatus;
use App\Enums\Assessment\AssignmentStatus;
use App\Models\Assessment\AssessmentPeriod;
use App\Models\Assessment\AssessmentPeriodAssignment;
use App\Models\Assessment\ReportGenerationRun;
use App\Models\Assessment\ReportShareLink;
use App\Models\Assessment\ReportTemplate;
use App\Models\User;
use App\Support\Assessment\AssessmentAuditLogger;
use App\Support\Assessment\AssessmentWorkflowGuard;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ReopenAssessmentPeriodAction
{
    use AuthorizesAssessmentAction;

    public function __construct(
        private readonly AssessmentWorkflowGuard $guard,
        private readonly AssessmentAuditLogger $audit,
        private readonly CancelOpenReportRevisionsAction $cancelOpenReportRevisions,
    ) {}

    /**
     * @param  array<int, int|string>  $assignmentIds
     */
    public function execute(
        User $actor,
        AssessmentPeriod $period,
        array $assignmentIds,
        string $reason,
    ): AssessmentPeriod {
        $this->authorizePermission($actor, 'penilaian.period.manage');

        $reason = trim($reason);
        $ids = collect($assignmentIds)
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values();

        if ($reason === '' || mb_strlen($reason) < 10) {
            throw ValidationException::withMessages([
                'reason' => 'Alasan pembukaan kembali wajib diisi minimal 10 karakter.',
            ]);
        }

        if ($ids->isEmpty()) {
            throw ValidationException::withMessages([
                'assignments' => 'Pilih minimal satu penugasan yang akan dibuka kembali.',
            ]);
        }

        return DB::transaction(function () use ($actor, $period, $ids, $reason): AssessmentPeriod {
            /** @var AssessmentPeriod $lockedPeriod */
            $lockedPeriod = AssessmentPeriod::query()
                ->lockForUpdate()
                ->findOrFail($period->getKey());
            $assignments = AssessmentPeriodAssignment::query()
                ->where('assessment_period_id', $lockedPeriod->getKey())
                ->whereIn('id', $ids)
                ->lockForUpdate()
                ->get();

            if ($assignments->count() !== $ids->count()) {
                throw ValidationException::withMessages([
                    'assignments' => 'Pilihan penugasan tidak valid atau berasal dari periode lain.',
                ]);
            }

            if ($this->periodStatus($lockedPeriod) === AssessmentPeriodStatus::OPEN
                && $assignments->every(
                    fn (AssessmentPeriodAssignment $assignment): bool => $assignment->status === AssignmentStatus::RETURNED
                        && trim((string) $assignment->returned_reason) === $reason,
                )) {
                $this->authorize($actor, 'view', $lockedPeriod);

                return $lockedPeriod->refresh();
            }

            $this->authorize($actor, 'reopen', $lockedPeriod);
            $this->guard->periodStatus(
                $lockedPeriod,
                [AssessmentPeriodStatus::LOCKED, AssessmentPeriodStatus::PUBLISHED],
                'Hanya periode yang sudah dikunci atau diterbitkan yang dapat dibuka kembali.',
            );
            $oldPeriodStatus = $this->periodStatus($lockedPeriod)?->value;
            $cancelledReports = ['runs' => 0, 'snapshots' => 0, 'classes' => 0];
            $openTemplateIds = ReportGenerationRun::query()
                ->where('assessment_period_id', $lockedPeriod->getKey())
                ->whereIn('status', ['prepared', 'running', 'failed'])
                ->pluck('assessment_report_template_id')
                ->unique();

            foreach (ReportTemplate::query()->whereIn('id', $openTemplateIds)->get() as $reportTemplate) {
                $cancelled = $this->cancelOpenReportRevisions->execute(
                    $actor,
                    $lockedPeriod,
                    $reportTemplate,
                    $reason,
                );
                foreach ($cancelledReports as $key => $count) {
                    $cancelledReports[$key] = $count + $cancelled[$key];
                }
            }

            foreach ($assignments as $assignment) {
                $oldValues = [
                    'status' => $assignment->status->value,
                    'lock_version' => $assignment->lock_version,
                    'locked_at' => $assignment->locked_at?->toISOString(),
                    'locked_by' => $assignment->locked_by,
                ];
                $assignment->forceFill([
                    'status' => AssignmentStatus::RETURNED,
                    'returned_at' => now(),
                    'returned_by' => $actor->getKey(),
                    'returned_reason' => $reason,
                    'verified_at' => null,
                    'verified_by' => null,
                    'locked_at' => null,
                    'locked_by' => null,
                    'lock_version' => (int) $assignment->lock_version + 1,
                ])->save();
                $this->audit->record(
                    actor: $actor,
                    event: 'assignment.reopened',
                    subject: $assignment,
                    oldValues: $oldValues,
                    newValues: [
                        'status' => AssignmentStatus::RETURNED->value,
                        'lock_version' => $assignment->lock_version,
                        'returned_at' => $assignment->returned_at?->toISOString(),
                        'returned_by' => $actor->getKey(),
                    ],
                    reason: $reason,
                );
            }

            $settings = is_array($lockedPeriod->settings) ? $lockedPeriod->settings : [];
            data_set($settings, '_workflow.last_reopen_reason', $reason);
            data_set($settings, '_workflow.last_reopened_at', now()->toISOString());
            data_set($settings, '_workflow.last_reopened_assignment_ids', $ids->all());
            Arr::forget($settings, '_reporting.pending');
            $lockedPeriod->forceFill([
                'status' => AssessmentPeriodStatus::OPEN,
                'settings' => $settings,
            ])->save();
            $revokedLinks = ReportShareLink::query()
                ->whereHas('snapshot', fn ($query) => $query->where('assessment_period_id', $lockedPeriod->getKey()))
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);
            $this->audit->record(
                actor: $actor,
                event: 'period.reopened',
                subject: $lockedPeriod,
                oldValues: ['status' => $oldPeriodStatus],
                newValues: [
                    'status' => AssessmentPeriodStatus::OPEN->value,
                    'assignment_ids' => $ids->all(),
                    'revoked_share_links' => $revokedLinks,
                    'cancelled_report_runs' => $cancelledReports['runs'],
                    'cancelled_report_snapshots' => $cancelledReports['snapshots'],
                    'cancelled_report_classes' => $cancelledReports['classes'],
                ],
                reason: $reason,
            );

            return $lockedPeriod->refresh();
        }, 3);
    }

    private function periodStatus(AssessmentPeriod $period): ?AssessmentPeriodStatus
    {
        return $period->status instanceof AssessmentPeriodStatus
            ? $period->status
            : AssessmentPeriodStatus::tryFrom((string) $period->status);
    }
}
