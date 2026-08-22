<?php

namespace App\Support\Assessment\Reporting;

use App\Support\Perpustakaan\LiteracySubmissionQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AssessmentReportQueueGate
{
    public function __construct(
        private readonly LiteracySubmissionQueue $literacySubmissionQueue,
    ) {}

    public function shouldRun(): bool
    {
        return $this->status()['can_run'];
    }

    /**
     * @return array{can_run:bool,reason:string,pending_reports:int,pending_priority_jobs:int}
     */
    public function status(): array
    {
        if (! config('assessment.enabled')) {
            return [
                'can_run' => false,
                'reason' => 'assessment_module_disabled',
                'pending_reports' => 0,
                'pending_priority_jobs' => 0,
            ];
        }

        if (! Schema::hasTable('jobs')) {
            return [
                'can_run' => false,
                'reason' => 'jobs_table_missing',
                'pending_reports' => 0,
                'pending_priority_jobs' => 0,
            ];
        }

        $pendingReports = DB::table('jobs')
            ->where('queue', (string) config('assessment.reports.queue', 'assessment-reports'))
            ->count();
        $priorityQueues = array_values(array_unique([
            (string) config('literacy.similarity_queue', 'literacy-analysis'),
            'default',
        ]));
        $pendingPriorityJobs = DB::table('jobs')
            ->whereIn('queue', $priorityQueues)
            ->count();

        if ($pendingReports === 0) {
            return [
                'can_run' => false,
                'reason' => 'no_pending_reports',
                'pending_reports' => 0,
                'pending_priority_jobs' => $pendingPriorityJobs,
            ];
        }

        if ($pendingPriorityJobs > 0) {
            return [
                'can_run' => false,
                'reason' => 'priority_queue_not_empty',
                'pending_reports' => $pendingReports,
                'pending_priority_jobs' => $pendingPriorityJobs,
            ];
        }

        if (
            Schema::hasTable('perpustakaan_literasi_submission_tickets')
            && Schema::hasTable('perpustakaan_literasi_submission_queue_states')
            && $this->literacySubmissionQueue->analysisShouldWait()
        ) {
            return [
                'can_run' => false,
                'reason' => 'literacy_submission_active',
                'pending_reports' => $pendingReports,
                'pending_priority_jobs' => 0,
            ];
        }

        return [
            'can_run' => true,
            'reason' => 'ready',
            'pending_reports' => $pendingReports,
            'pending_priority_jobs' => 0,
        ];
    }
}
