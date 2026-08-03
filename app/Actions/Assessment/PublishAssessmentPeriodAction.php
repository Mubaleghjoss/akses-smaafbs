<?php

namespace App\Actions\Assessment;

use App\Enums\Assessment\AssessmentPeriodStatus;
use App\Enums\Assessment\ReportGenerationStatus;
use App\Models\Assessment\AssessmentPeriod;
use App\Models\Assessment\ClassReportArtifact;
use App\Models\Assessment\ReportSnapshot;
use App\Models\User;
use App\Support\Assessment\AssessmentAuditLogger;
use App\Support\Assessment\AssessmentWorkflowGuard;
use App\Support\Assessment\Reporting\AssessmentReportStorage;
use App\Support\Assessment\Reporting\AssessmentSnapshotIntegrity;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PublishAssessmentPeriodAction
{
    use AuthorizesAssessmentAction;

    public function __construct(
        private readonly AssessmentWorkflowGuard $guard,
        private readonly AssessmentAuditLogger $audit,
        private readonly AssessmentReportStorage $storage,
        private readonly AssessmentSnapshotIntegrity $integrity,
    ) {}

    public function execute(User $actor, AssessmentPeriod $period): AssessmentPeriod
    {
        $this->authorizePermission($actor, 'penilaian.publish');

        return DB::transaction(function () use ($actor, $period): AssessmentPeriod {
            /** @var AssessmentPeriod $locked */
            $locked = AssessmentPeriod::query()->lockForUpdate()->findOrFail($period->getKey());
            $this->authorize($actor, 'publish', $locked);
            $this->guard->periodStatus(
                $locked,
                [AssessmentPeriodStatus::LOCKED, AssessmentPeriodStatus::PUBLISHED],
                'Periode hanya dapat diterbitkan setelah dikunci.',
            );

            $settings = is_array($locked->settings) ? $locked->settings : [];
            $pendingTemplateId = (int) data_get($settings, '_reporting.pending.template_id');
            $pendingRevision = (int) data_get($settings, '_reporting.pending.revision');
            $latestSnapshotQuery = ReportSnapshot::query()
                ->where('assessment_period_id', $locked->getKey());

            if ($pendingTemplateId > 0) {
                $latestSnapshotQuery->where('assessment_report_template_id', $pendingTemplateId);
            }

            if ($pendingRevision > 0) {
                $latestSnapshotQuery->where('revision', $pendingRevision);
            }

            $latestSnapshot = $latestSnapshotQuery->orderByDesc('id')->first();

            if (! $latestSnapshot instanceof ReportSnapshot) {
                throw ValidationException::withMessages([
                    'reports' => 'Snapshot rapor periode ini belum dibuat.',
                ]);
            }

            $templateId = (int) $latestSnapshot->assessment_report_template_id;
            $latestRevision = $pendingRevision > 0
                ? $pendingRevision
                : (int) ReportSnapshot::query()
                    ->where('assessment_period_id', $locked->getKey())
                    ->where('assessment_report_template_id', $templateId)
                    ->max('revision');
            $expected = $locked->students()->where('is_active', true)->count();
            $snapshots = ReportSnapshot::query()
                ->where('assessment_period_id', $locked->getKey())
                ->where('assessment_report_template_id', $templateId)
                ->where('revision', $latestRevision)
                ->whereHas('student', fn ($query) => $query->where('is_active', true))
                ->get();
            $validSnapshots = $snapshots->filter(
                function (ReportSnapshot $snapshot): bool {
                    $status = $snapshot->generation_status instanceof \BackedEnum
                        ? $snapshot->generation_status->value
                        : (string) $snapshot->generation_status;

                    if ((string) $snapshot->delivery_mode === 'stream') {
                        return $status === ReportGenerationStatus::READY->value
                            && $this->integrity->isValid($snapshot);
                    }

                    return $status === ReportGenerationStatus::COMPLETED->value
                        && $this->storage->isValid($snapshot->pdf_path, $snapshot->checksum);
                },
            )->count();

            if ($latestRevision <= 0 || $validSnapshots !== $expected) {
                throw ValidationException::withMessages([
                    'reports' => "Snapshot rapor revisi terbaru belum lengkap atau checksum tidak valid ({$validSnapshots} dari {$expected}). Siapkan ulang revisi setelah data diperbaiki.",
                ]);
            }

            $periodRombelIds = $locked->students()
                ->where('is_active', true)
                ->distinct()
                ->pluck('assessment_period_rombel_id')
                ->map(fn (mixed $id): int => (int) $id)
                ->values();
            $artifacts = ClassReportArtifact::query()
                ->where('assessment_period_id', $locked->getKey())
                ->where('assessment_report_template_id', $templateId)
                ->where('revision', $latestRevision)
                ->whereIn('assessment_period_rombel_id', $periodRombelIds)
                ->where('generation_status', ReportGenerationStatus::COMPLETED->value)
                ->whereNotNull('pdf_path')
                ->whereNotNull('checksum')
                ->where('cache_expires_at', '>', now())
                ->get();
            $validArtifacts = $artifacts->filter(
                fn (ClassReportArtifact $artifact): bool => $this->storage->isValid(
                    $artifact->pdf_path,
                    $artifact->checksum,
                ),
            )->count();
            $expectedArtifacts = $periodRombelIds->count();

            $alreadyPublished = $this->isStatus($locked, AssessmentPeriodStatus::PUBLISHED);
            $publishedSetUnchanged = $alreadyPublished
                && (int) data_get($settings, '_reporting.published.template_id') === $templateId
                && (int) data_get($settings, '_reporting.published.revision') === $latestRevision;

            if ($publishedSetUnchanged) {
                return $locked->refresh();
            }

            data_set($settings, '_reporting.published', [
                'template_id' => $templateId,
                'revision' => $latestRevision,
                'student_report_count' => $validSnapshots,
                'class_report_count' => $validArtifacts,
                'expected_class_report_count' => $expectedArtifacts,
                'individual_delivery_mode' => 'stream',
                'published_at' => now()->toIso8601String(),
                'published_by' => (int) $actor->getKey(),
            ]);
            Arr::forget($settings, '_reporting.pending');
            $oldStatus = $this->periodStatus($locked)?->value;
            $locked->forceFill([
                'status' => AssessmentPeriodStatus::PUBLISHED,
                'settings' => $settings,
            ])->save();
            $this->audit->record(
                actor: $actor,
                event: 'period.published',
                subject: $locked,
                oldValues: ['status' => $oldStatus],
                newValues: [
                    'status' => AssessmentPeriodStatus::PUBLISHED->value,
                    'template_id' => $templateId,
                    'revision' => $latestRevision,
                    'report_count' => $validSnapshots,
                    'class_report_count' => $validArtifacts,
                ],
            );

            return $locked->refresh();
        }, 3);
    }

    private function isStatus(AssessmentPeriod $period, AssessmentPeriodStatus $status): bool
    {
        return $this->periodStatus($period) === $status;
    }

    private function periodStatus(AssessmentPeriod $period): ?AssessmentPeriodStatus
    {
        return $period->status instanceof AssessmentPeriodStatus
            ? $period->status
            : AssessmentPeriodStatus::tryFrom((string) $period->status);
    }
}
