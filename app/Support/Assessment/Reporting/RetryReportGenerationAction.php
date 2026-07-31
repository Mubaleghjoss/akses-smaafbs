<?php

namespace App\Support\Assessment\Reporting;

use App\Enums\Assessment\ReportGenerationStatus;
use App\Jobs\Assessment\GenerateClassReportPipeline;
use App\Jobs\Assessment\GenerateStudentReportJob;
use App\Models\Assessment\AuditLog;
use App\Models\Assessment\ClassReportArtifact;
use App\Models\Assessment\ReportSnapshot;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class RetryReportGenerationAction
{
    public function retrySnapshot(User $actor, ReportSnapshot $snapshot): ReportSnapshot
    {
        return DB::transaction(function () use ($actor, $snapshot): ReportSnapshot {
            $locked = ReportSnapshot::query()
                ->lockForUpdate()
                ->findOrFail($snapshot->getKey());

            Gate::forUser($actor)->authorize('generate', $locked);
            $this->assertFailed($locked);
            $this->assertLatestSnapshot($locked);

            $oldValues = $this->retryOldValues($locked);
            $locked->forceFill([
                'generation_status' => ReportGenerationStatus::PENDING,
                'pdf_path' => null,
                'checksum' => null,
                'error_message' => null,
                'generated_at' => null,
            ])->save();
            $this->auditRetry($actor, $locked, 'student_report_retry_requested', $oldValues);

            GenerateStudentReportJob::dispatch($locked->getKey())->afterCommit();

            return $locked->refresh();
        }, 3);
    }

    public function retryClass(User $actor, ClassReportArtifact $artifact): ClassReportArtifact
    {
        return DB::transaction(function () use ($actor, $artifact): ClassReportArtifact {
            $locked = ClassReportArtifact::query()
                ->lockForUpdate()
                ->findOrFail($artifact->getKey());

            Gate::forUser($actor)->authorize('generate', $locked);
            $this->assertFailed($locked);
            $this->assertLatestArtifact($locked);

            $oldValues = $this->retryOldValues($locked);
            $locked->forceFill([
                'generation_status' => ReportGenerationStatus::PENDING,
                'pdf_path' => null,
                'checksum' => null,
                'error_message' => null,
                'queued_at' => now(),
                'started_at' => null,
                'generated_at' => null,
            ])->save();
            $this->auditRetry($actor, $locked, 'class_report_retry_requested', $oldValues);

            GenerateClassReportPipeline::dispatch($locked->getKey())->afterCommit();

            return $locked->refresh();
        }, 3);
    }

    private function assertFailed(ReportSnapshot|ClassReportArtifact $report): void
    {
        $status = $this->statusValue($report->generation_status);

        if ($status !== ReportGenerationStatus::FAILED->value) {
            throw ValidationException::withMessages([
                'report' => 'Hanya pembuatan PDF yang berstatus gagal yang dapat dijadwalkan ulang.',
            ]);
        }
    }

    private function assertLatestSnapshot(ReportSnapshot $snapshot): void
    {
        $latestRevision = (int) ReportSnapshot::query()
            ->where('assessment_period_id', $snapshot->assessment_period_id)
            ->where('assessment_report_template_id', $snapshot->assessment_report_template_id)
            ->max('revision');

        if ((int) $snapshot->revision !== $latestRevision) {
            throw ValidationException::withMessages([
                'report' => 'Revisi lama tidak dapat dijadwalkan ulang. Gunakan revisi rapor terbaru.',
            ]);
        }
    }

    private function assertLatestArtifact(ClassReportArtifact $artifact): void
    {
        $latestRevision = (int) ClassReportArtifact::query()
            ->where('assessment_period_id', $artifact->assessment_period_id)
            ->where('assessment_period_rombel_id', $artifact->assessment_period_rombel_id)
            ->where('assessment_report_template_id', $artifact->assessment_report_template_id)
            ->max('revision');

        if ((int) $artifact->revision !== $latestRevision) {
            throw ValidationException::withMessages([
                'report' => 'Revisi PDF kelas lama tidak dapat dijadwalkan ulang.',
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function retryOldValues(ReportSnapshot|ClassReportArtifact $report): array
    {
        return [
            'generation_status' => $this->statusValue($report->generation_status),
            'pdf_path' => $report->pdf_path,
            'checksum' => $report->checksum,
            'error_message' => $report->error_message,
        ];
    }

    /**
     * @param  array<string, mixed>  $oldValues
     */
    private function auditRetry(
        User $actor,
        ReportSnapshot|ClassReportArtifact $report,
        string $event,
        array $oldValues,
    ): void {
        AuditLog::query()->create([
            'assessment_period_id' => $report->assessment_period_id,
            'actor_id' => $actor->getKey(),
            'event' => $event,
            'subject_type' => $report::class,
            'subject_id' => $report->getKey(),
            'old_values' => $oldValues,
            'new_values' => [
                'generation_status' => ReportGenerationStatus::PENDING->value,
                'revision' => $report->revision,
            ],
            'reason' => 'Penjadwalan ulang manual setelah pembuatan PDF gagal.',
            'ip_address' => request()?->ip(),
            'user_agent' => mb_substr((string) request()?->userAgent(), 0, 500),
            'created_at' => now(),
        ]);
    }

    private function statusValue(mixed $status): string
    {
        return $status instanceof \BackedEnum ? (string) $status->value : (string) $status;
    }
}
