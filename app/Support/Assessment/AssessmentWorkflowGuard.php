<?php

namespace App\Support\Assessment;

use App\Enums\Assessment\AssessmentPeriodStatus;
use App\Enums\Assessment\AssignmentStatus;
use App\Models\Assessment\AssessmentPeriod;
use App\Models\Assessment\AssessmentPeriodAssignment;
use Illuminate\Validation\ValidationException;

final class AssessmentWorkflowGuard
{
    /**
     * @param  array<int, AssessmentPeriodStatus>  $allowed
     */
    public function periodStatus(AssessmentPeriod $period, array $allowed, string $message): void
    {
        $status = $period->status instanceof AssessmentPeriodStatus
            ? $period->status
            : AssessmentPeriodStatus::tryFrom((string) $period->status);

        if (! $status || ! in_array($status, $allowed, true)) {
            throw ValidationException::withMessages(['status' => $message]);
        }
    }

    /**
     * @param  array<int, AssignmentStatus>  $allowed
     */
    public function assignmentStatus(AssessmentPeriodAssignment $assignment, array $allowed, string $message): void
    {
        $status = $assignment->status instanceof AssignmentStatus
            ? $assignment->status
            : AssignmentStatus::tryFrom((string) $assignment->status);

        if (! $status || ! in_array($status, $allowed, true)) {
            throw ValidationException::withMessages(['status' => $message]);
        }
    }

    public function entryWindow(AssessmentPeriod $period): void
    {
        $now = now();

        if ($period->entry_start_at && $now->lt($period->entry_start_at)) {
            throw ValidationException::withMessages([
                'period' => 'Periode pengisian nilai belum dimulai.',
            ]);
        }

        if ($period->entry_end_at && $now->gt($period->entry_end_at)) {
            throw ValidationException::withMessages([
                'period' => 'Batas waktu pengisian nilai telah berakhir.',
            ]);
        }
    }
}
