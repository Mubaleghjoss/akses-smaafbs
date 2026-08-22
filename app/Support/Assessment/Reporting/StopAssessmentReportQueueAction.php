<?php

namespace App\Support\Assessment\Reporting;

use App\Models\Assessment\AuditLog;
use App\Models\Assessment\ReportGenerationRun;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class StopAssessmentReportQueueAction
{
    /**
     * @return array{jobs:int,runs:int,snapshots:int,classes:int}
     */
    public function execute(User $actor, string $reason): array
    {
        abort_unless($actor->hasFullAdminAccess() || $actor->can('penilaian.report.generate'), 403);
        $reason = trim($reason);

        if (mb_strlen($reason) < 10) {
            throw ValidationException::withMessages([
                'reason' => 'Alasan penghentian wajib diisi minimal 10 karakter.',
            ]);
        }

        $queue = (string) config('assessment.reports.queue', 'assessment-reports');
        $connection = (string) config('queue.default');

        if ($connection !== 'database') {
            throw ValidationException::withMessages([
                'queue' => 'Penghentian aman saat ini hanya tersedia untuk QUEUE_CONNECTION=database.',
            ]);
        }

        $result = DB::transaction(function () use ($actor, $reason, $queue): array {
            $now = Carbon::now();
            $activeRuns = ReportGenerationRun::query()
                ->whereIn('status', ['prepared', 'running', 'failed'])
                ->lockForUpdate()
                ->get();
            $runIds = $activeRuns->modelKeys();

            $snapshots = DB::table('assessment_report_snapshots')
                ->whereIn('generation_status', ['pending', 'processing'])
                ->update([
                    'generation_status' => 'cancelled',
                    'error_message' => 'Dihentikan admin: '.$reason,
                    'updated_at' => $now,
                ]);
            $classes = DB::table('assessment_class_report_artifacts')
                ->whereIn('generation_status', ['pending', 'processing'])
                ->update([
                    'generation_status' => 'cancelled',
                    'error_message' => 'Dihentikan admin: '.$reason,
                    'updated_at' => $now,
                ]);

            foreach ($activeRuns as $run) {
                $run->forceFill([
                    'status' => 'cancelled',
                    'cancel_requested_at' => $now,
                    'cancelled_at' => $now,
                    'cancelled_by' => $actor->getKey(),
                    'cancellation_reason' => $reason,
                ])->save();

                AuditLog::query()->create([
                    'assessment_period_id' => $run->assessment_period_id,
                    'actor_id' => $actor->getKey(),
                    'event' => 'report_pipeline.cancelled',
                    'subject_type' => ReportGenerationRun::class,
                    'subject_id' => $run->getKey(),
                    'old_values' => ['status' => 'running'],
                    'new_values' => ['status' => 'cancelled', 'queue' => $queue],
                    'reason' => $reason,
                    'ip_address' => request()?->ip(),
                    'user_agent' => mb_substr((string) request()?->userAgent(), 0, 500),
                    'created_at' => $now,
                ]);
            }

            $table = (string) config('queue.connections.database.table', 'jobs');
            $jobs = DB::table($table)->where('queue', $queue)->delete();

            return [
                'jobs' => $jobs,
                'runs' => count($runIds),
                'snapshots' => $snapshots,
                'classes' => $classes,
            ];
        }, 3);

        return $result;
    }
}
