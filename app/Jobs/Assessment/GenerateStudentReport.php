<?php

namespace App\Jobs\Assessment;

use App\Models\Assessment\AuditLog;
use App\Models\Assessment\ReportSnapshot;
use App\Support\Assessment\Reporting\AssessmentReportRenderer;
use App\Support\Assessment\Reporting\AssessmentReportStorage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

class GenerateStudentReport implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    /** @var array<int, int> */
    public array $backoff = [30, 120, 300];

    public function __construct(public int $reportSnapshotId)
    {
        $this->onQueue((string) config('assessment.reports.queue', 'assessment-reports'));
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('assessment-report-snapshot-'.$this->reportSnapshotId))
                ->releaseAfter(15)
                ->expireAfter(170)
                ->shared(),
        ];
    }

    public function handle(
        AssessmentReportRenderer $renderer,
        AssessmentReportStorage $storage,
    ): void {
        $snapshot = ReportSnapshot::query()->find($this->reportSnapshotId);

        if (! $snapshot) {
            return;
        }

        if (! in_array($this->statusValue($snapshot->generation_status), ['pending', 'processing', 'failed'], true)) {
            return;
        }

        if ($this->isCompletedAndValid($snapshot, $storage)) {
            return;
        }

        DB::transaction(function () use (&$snapshot, $storage): void {
            $snapshot = ReportSnapshot::query()->lockForUpdate()->find($this->reportSnapshotId);

            if (! $snapshot || $this->isCompletedAndValid($snapshot, $storage)) {
                return;
            }

            $snapshot->forceFill([
                'generation_status' => 'processing',
                'error_message' => null,
            ])->save();
        }, 3);

        if (! $snapshot || $this->isCompletedAndValid($snapshot, $storage)) {
            return;
        }

        $stored = $storage->putAtomically(
            $storage->individualPath($snapshot),
            $renderer->renderStudent($snapshot),
        );

        $fresh = ReportSnapshot::query()->find($this->reportSnapshotId);
        if (! $fresh || $this->statusValue($fresh->generation_status) === 'cancelled') {
            $storage->disk()->delete($stored['path']);

            return;
        }

        DB::transaction(function () use ($stored): void {
            $snapshot = ReportSnapshot::query()->lockForUpdate()->find($this->reportSnapshotId);

            if (! $snapshot) {
                return;
            }

            $snapshot->forceFill([
                'generation_status' => 'completed',
                'pdf_path' => $stored['path'],
                'checksum' => $stored['checksum'],
                'error_message' => null,
                'generated_at' => Carbon::now(),
            ])->save();

            AuditLog::query()->create([
                'assessment_period_id' => $snapshot->assessment_period_id,
                'actor_id' => $snapshot->generated_by,
                'event' => 'student_report_pdf_generated',
                'subject_type' => ReportSnapshot::class,
                'subject_id' => $snapshot->getKey(),
                'old_values' => null,
                'new_values' => [
                    'revision' => $snapshot->revision,
                    'checksum' => $snapshot->checksum,
                    'pdf_path' => $snapshot->pdf_path,
                ],
                'reason' => null,
                'ip_address' => null,
                'user_agent' => null,
                'created_at' => Carbon::now(),
            ]);
        }, 3);
    }

    public function failed(?Throwable $exception): void
    {
        $snapshot = ReportSnapshot::query()->find($this->reportSnapshotId);

        if (! $snapshot) {
            return;
        }

        $status = $snapshot->generation_status;
        $status = $status instanceof \BackedEnum ? $status->value : (string) $status;

        if (in_array($status, ['completed', 'cancelled', 'not_scheduled'], true)) {
            return;
        }

        $message = mb_substr(
            (string) ($exception?->getMessage() ?: 'Pembuatan PDF gagal tanpa pesan error.'),
            0,
            1000,
        );

        $snapshot->forceFill([
            'generation_status' => 'failed',
            'error_message' => $message,
        ])->save();

        AuditLog::query()->create([
            'assessment_period_id' => $snapshot->assessment_period_id,
            'actor_id' => $snapshot->generated_by,
            'event' => 'student_report_pdf_failed',
            'subject_type' => ReportSnapshot::class,
            'subject_id' => $snapshot->getKey(),
            'old_values' => null,
            'new_values' => ['error_message' => $message],
            'reason' => null,
            'ip_address' => null,
            'user_agent' => null,
            'created_at' => Carbon::now(),
        ]);
    }

    private function isCompletedAndValid(
        ReportSnapshot $snapshot,
        AssessmentReportStorage $storage,
    ): bool {
        $status = $this->statusValue($snapshot->generation_status);

        return $status === 'completed'
            && $storage->isValid($snapshot->pdf_path, $snapshot->checksum);
    }

    private function statusValue(mixed $status): string
    {
        return $status instanceof \BackedEnum ? (string) $status->value : (string) $status;
    }
}
