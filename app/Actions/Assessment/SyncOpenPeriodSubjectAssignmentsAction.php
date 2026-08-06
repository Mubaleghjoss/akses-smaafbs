<?php

namespace App\Actions\Assessment;

use App\Models\Assessment\AssessmentPeriod;
use App\Models\Assessment\Subject;
use App\Models\User;

final class SyncOpenPeriodSubjectAssignmentsAction
{
    public function __construct(private readonly SyncOpenPeriodSubjectsAction $sync) {}

    /** @return array{created:int,updated:int,deleted:int} */
    public function execute(User $actor, Subject $subject, AssessmentPeriod $period): array
    {
        $summary = $this->sync->execute($actor, $period, [(int) $subject->getKey()]);

        return [
            'created' => $summary['created'],
            'updated' => $summary['updated'],
            'deleted' => 0,
        ];
    }
}
