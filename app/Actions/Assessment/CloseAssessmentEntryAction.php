<?php

namespace App\Actions\Assessment;

use App\Enums\Assessment\AssessmentPeriodStatus;
use App\Enums\Assessment\AssignmentStatus;
use App\Models\Assessment\AssessmentPeriod;
use App\Models\User;
use App\Support\Assessment\AssessmentAuditLogger;
use App\Support\Assessment\AssessmentWorkflowGuard;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CloseAssessmentEntryAction
{
    use AuthorizesAssessmentAction;

    public function __construct(
        private readonly AssessmentWorkflowGuard $guard,
        private readonly AssessmentAuditLogger $audit,
    ) {}

    public function execute(User $actor, AssessmentPeriod $period): AssessmentPeriod
    {
        $this->authorizePermission($actor, 'penilaian.period.manage');

        return DB::transaction(function () use ($actor, $period): AssessmentPeriod {
            /** @var AssessmentPeriod $locked */
            $locked = AssessmentPeriod::query()->lockForUpdate()->findOrFail($period->getKey());

            if ($this->isStatus($locked, AssessmentPeriodStatus::ENTRY_CLOSED)) {
                $this->authorize($actor, 'view', $locked);

                return $locked->refresh();
            }

            $this->authorize($actor, 'closeEntry', $locked);
            $this->guard->periodStatus(
                $locked,
                [AssessmentPeriodStatus::OPEN],
                'Input hanya dapat ditutup dari periode berstatus terbuka.',
            );
            $outstanding = $locked->assignments()
                ->whereNotIn('status', [
                    AssignmentStatus::SUBMITTED->value,
                    AssignmentStatus::VERIFIED->value,
                    AssignmentStatus::LOCKED->value,
                ])
                ->count();

            if ($outstanding > 0) {
                throw ValidationException::withMessages([
                    'assignments' => "{$outstanding} penugasan belum dikirim. Input belum dapat ditutup.",
                ]);
            }

            $locked->forceFill(['status' => AssessmentPeriodStatus::ENTRY_CLOSED])->save();
            $this->audit->record(
                actor: $actor,
                event: 'period.entry_closed',
                subject: $locked,
                oldValues: ['status' => AssessmentPeriodStatus::OPEN->value],
                newValues: [
                    'status' => AssessmentPeriodStatus::ENTRY_CLOSED->value,
                    'submitted_assignments' => $locked->assignments()->where('status', 'submitted')->count(),
                    'outstanding_assignments' => $locked->assignments()->whereIn('status', ['draft', 'returned'])->count(),
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
}
