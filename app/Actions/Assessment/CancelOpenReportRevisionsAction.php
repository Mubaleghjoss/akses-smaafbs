<?php

namespace App\Actions\Assessment;

use App\Models\Assessment\AssessmentPeriod;
use App\Models\Assessment\ReportGenerationRun;
use App\Models\Assessment\ReportTemplate;
use App\Models\User;
use App\Support\Assessment\AssessmentAuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class CancelOpenReportRevisionsAction
{
    public function __construct(private readonly AssessmentAuditLogger $audit) {}

    /**
     * @return array{runs:int,snapshots:int,classes:int}
     */
    public function execute(
        User $actor,
        AssessmentPeriod $period,
        ReportTemplate $template,
        string $reason,
    ): array {
        Gate::forUser($actor)->authorize('create', \App\Models\Assessment\ReportSnapshot::class);
        $reason = trim($reason);

        if (mb_strlen($reason) < 10) {
            throw ValidationException::withMessages([
                'reason' => 'Alasan mulai ulang wajib diisi minimal 10 karakter.',
            ]);
        }

        return DB::transaction(function () use ($actor, $period, $template, $reason): array {
            $period = AssessmentPeriod::query()->whereKey($period->getKey())->lockForUpdate()->firstOrFail();
            $template = ReportTemplate::query()->whereKey($template->getKey())->lockForUpdate()->firstOrFail();
            $runs = ReportGenerationRun::query()
                ->where('assessment_period_id', $period->getKey())
                ->where('assessment_report_template_id', $template->getKey())
                ->whereIn('status', ['prepared', 'running', 'failed'])
                ->lockForUpdate()
                ->get();

            $snapshotCount = 0;
            $classCount = 0;

            foreach ($runs as $run) {
                $snapshotCount += $run->snapshots()
                    ->whereIn('generation_status', ['not_scheduled', 'pending', 'processing', 'failed'])
                    ->update([
                        'generation_status' => 'cancelled',
                        'error_message' => 'Digantikan revisi baru: '.$reason,
                    ]);
                $classCount += $run->classArtifacts()
                    ->whereIn('generation_status', ['not_scheduled', 'pending', 'processing', 'failed'])
                    ->update([
                        'generation_status' => 'cancelled',
                        'error_message' => 'Digantikan revisi baru: '.$reason,
                    ]);

                $oldStatus = $run->status instanceof \BackedEnum
                    ? $run->status->value
                    : (string) $run->status;
                $run->forceFill([
                    'status' => 'cancelled',
                    'cancel_requested_at' => now(),
                    'cancelled_at' => now(),
                    'cancelled_by' => $actor->getKey(),
                    'cancellation_reason' => $reason,
                ])->save();

                $this->audit->record(
                    actor: $actor,
                    event: 'report_revision.superseded',
                    subject: $run,
                    oldValues: ['status' => $oldStatus, 'revision' => (int) $run->revision],
                    newValues: ['status' => 'cancelled'],
                    reason: $reason,
                    periodId: (int) $period->getKey(),
                );
            }

            return [
                'runs' => $runs->count(),
                'snapshots' => $snapshotCount,
                'classes' => $classCount,
            ];
        }, 3);
    }
}
