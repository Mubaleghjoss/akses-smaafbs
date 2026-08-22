<?php

namespace App\Support\Assessment\Reporting;

use App\Jobs\Assessment\GenerateClassReportPipeline;
use App\Models\Assessment\AssessmentPeriod;
use App\Models\Assessment\ClassReportArtifact;
use App\Models\Assessment\ReportGenerationRun;
use App\Models\Assessment\ReportSnapshot;
use App\Models\Assessment\ReportTemplate;
use App\Models\User;
use App\Support\Assessment\AssessmentAuditLogger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class ScheduleReportClassesAction
{
    public function __construct(
        private readonly AssessmentAuditLogger $audit,
        private readonly AssessmentReportStorage $storage,
    ) {}

    /**
     * @param  array<int, int|string>  $periodRombelIds
     */
    public function execute(
        User $actor,
        AssessmentPeriod $period,
        ReportTemplate $template,
        array $periodRombelIds,
    ): ReportGenerationRun {
        abort_unless($actor->hasFullAdminAccess() || $actor->can('penilaian.report.generate'), 403);
        Gate::forUser($actor)->authorize('create', ReportSnapshot::class);

        $classIds = collect($periodRombelIds)
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values();

        if ($classIds->isEmpty()) {
            throw ValidationException::withMessages([
                'classes' => 'Pilih minimal satu kelas yang akan dibuatkan PDF.',
            ]);
        }

        return DB::transaction(function () use ($actor, $period, $template, $classIds): ReportGenerationRun {
            $period = AssessmentPeriod::query()->lockForUpdate()->findOrFail($period->getKey());
            $template = ReportTemplate::query()->lockForUpdate()->findOrFail($template->getKey());
            $revision = (int) ReportSnapshot::query()
                ->where('assessment_period_id', $period->getKey())
                ->where('assessment_report_template_id', $template->getKey())
                ->max('revision');

            if ($revision < 1) {
                throw ValidationException::withMessages([
                    'reports' => 'Snapshot rapor belum disiapkan.',
                ]);
            }

            $validClassIds = $period->periodRombels()
                ->whereIn('id', $classIds)
                ->pluck('id')
                ->map(fn (mixed $id): int => (int) $id);

            if ($validClassIds->count() !== $classIds->count()) {
                throw ValidationException::withMessages([
                    'classes' => 'Pilihan kelas tidak valid atau berasal dari periode lain.',
                ]);
            }

            $run = ReportGenerationRun::query()->firstOrCreate(
                [
                    'assessment_period_id' => $period->getKey(),
                    'assessment_report_template_id' => $template->getKey(),
                    'revision' => $revision,
                ],
                [
                    'status' => 'prepared',
                    'total_students' => $period->students()->where('is_active', true)->count(),
                    'total_classes' => $period->periodRombels()->count(),
                    'requested_by' => $actor->getKey(),
                ],
            );

            DB::table('assessment_report_snapshots')
                ->where('assessment_period_id', $period->getKey())
                ->where('assessment_report_template_id', $template->getKey())
                ->where('revision', $revision)
                ->whereNull('assessment_report_generation_run_id')
                ->update(['assessment_report_generation_run_id' => $run->getKey()]);
            DB::table('assessment_class_report_artifacts')
                ->where('assessment_period_id', $period->getKey())
                ->where('assessment_report_template_id', $template->getKey())
                ->where('revision', $revision)
                ->whereNull('assessment_report_generation_run_id')
                ->update(['assessment_report_generation_run_id' => $run->getKey()]);

            foreach ($validClassIds as $classId) {
                $artifact = ClassReportArtifact::query()->firstOrCreate(
                    [
                        'assessment_period_id' => $period->getKey(),
                        'assessment_period_rombel_id' => $classId,
                        'assessment_report_template_id' => $template->getKey(),
                        'revision' => $revision,
                    ],
                    [
                        'assessment_report_generation_run_id' => $run->getKey(),
                        'generation_status' => 'not_scheduled',
                        'queued_at' => Carbon::now(),
                        'generated_by' => $actor->getKey(),
                    ],
                );

                $status = $artifact->generation_status instanceof \BackedEnum
                    ? $artifact->generation_status->value
                    : (string) $artifact->generation_status;
                $cacheFresh = $status === 'completed'
                    && $artifact->cache_expires_at?->isFuture()
                    && $this->storage->isValid($artifact->pdf_path, $artifact->checksum);

                if ($cacheFresh) {
                    continue;
                }

                if (filled($artifact->pdf_path)) {
                    $this->storage->disk()->delete((string) $artifact->pdf_path);
                }

                $artifact->forceFill([
                    'assessment_report_generation_run_id' => $run->getKey(),
                    'generation_status' => 'pending',
                    'error_message' => null,
                    'queued_at' => Carbon::now(),
                    'started_at' => null,
                    'generated_at' => null,
                    'cache_expires_at' => null,
                    'pdf_path' => null,
                    'checksum' => null,
                ])->save();

                GenerateClassReportPipeline::dispatch($artifact->getKey())->afterCommit();
            }

            $run->forceFill([
                'status' => 'running',
                'requested_by' => $actor->getKey(),
                'started_at' => $run->started_at ?: Carbon::now(),
                'completed_at' => null,
                'cancel_requested_at' => null,
                'cancelled_at' => null,
                'cancelled_by' => null,
                'cancellation_reason' => null,
            ])->save();
            $this->refreshProgress($run);

            $this->audit->record(
                actor: $actor,
                event: 'report_pipeline.classes_scheduled',
                subject: $run,
                newValues: [
                    'revision' => $revision,
                    'class_ids' => $validClassIds->all(),
                    'queue' => (string) config('assessment.reports.queue', 'assessment-reports'),
                ],
            );

            return $run->refresh();
        }, 3);
    }

    private function refreshProgress(ReportGenerationRun $run): void
    {
        $run->forceFill([
            'completed_students' => $run->snapshots()->whereIn('generation_status', ['ready', 'completed'])->count(),
            'completed_classes' => $run->classArtifacts()
                ->where('generation_status', 'completed')
                ->where('cache_expires_at', '>', Carbon::now())
                ->count(),
        ])->save();
    }
}
