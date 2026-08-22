<?php

namespace App\Support\Assessment;

use App\Models\Assessment\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

final class AssessmentAuditLogger
{
    /**
     * The caller is responsible for invoking this inside the same transaction
     * as the state change being audited.
     *
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     */
    public function record(
        ?User $actor,
        string $event,
        Model $subject,
        array $oldValues = [],
        array $newValues = [],
        ?string $reason = null,
        ?int $periodId = null,
    ): AuditLog {
        $request = app()->bound('request') ? request() : null;

        return AuditLog::query()->create([
            'assessment_period_id' => $periodId ?? $this->resolvePeriodId($subject),
            'actor_id' => $actor?->getKey(),
            'event' => $event,
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'old_values' => $oldValues !== [] ? $oldValues : null,
            'new_values' => $newValues !== [] ? $newValues : null,
            'reason' => filled($reason) ? trim((string) $reason) : null,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ]);
    }

    private function resolvePeriodId(Model $subject): ?int
    {
        if ($subject->getTable() === 'assessment_periods') {
            return (int) $subject->getKey();
        }

        $periodId = $subject->getAttribute('assessment_period_id');

        if ($periodId !== null) {
            return (int) $periodId;
        }

        $assignmentId = $subject->getAttribute('assessment_period_assignment_id');

        if ($assignmentId !== null && method_exists($subject, 'assignment')) {
            return (int) $subject->assignment()->value('assessment_period_id');
        }

        return null;
    }
}
