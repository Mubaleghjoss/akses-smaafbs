<?php

namespace App\Jobs\Assessment;

use App\Models\Assessment\AuditLog;
use App\Models\Assessment\ClassReportArtifact;
use App\Models\Assessment\ReportGenerationRun;
use App\Models\Assessment\ReportSnapshot;
use App\Models\Assessment\ReportTemplate;
use App\Support\Assessment\Reporting\AssessmentReportRenderer;
use App\Support\Assessment\Reporting\AssessmentReportStorage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Carbon;
use RuntimeException;
use Throwable;

class GenerateClassReportPipeline implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 150;

    /** @var array<int, int> */
    public array $backoff = [30, 120, 300];

    public function __construct(public readonly int $classReportArtifactId)
    {
        $this->onQueue((string) config('assessment.reports.queue', 'assessment-reports'));
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('assessment-class-pipeline-'.$this->classReportArtifactId))
                ->releaseAfter(15)
                ->expireAfter(170)
                ->shared(),
        ];
    }

    public function handle(
        AssessmentReportRenderer $renderer,
        AssessmentReportStorage $storage,
    ): void {
        $artifact = ClassReportArtifact::query()
            ->with(['generationRun', 'periodRombel'])
            ->find($this->classReportArtifactId);

        if (! $artifact || $this->shouldStop($artifact) || $this->isCompletedAndValid($artifact, $storage)) {
            return;
        }

        $artifact->forceFill([
            'generation_status' => 'processing',
            'started_at' => $artifact->started_at ?: Carbon::now(),
            'error_message' => null,
        ])->save();

        $started = microtime(true);
        $limit = max(1, (int) config('assessment.reports.pipeline.students_per_job', 3));
        $maxSeconds = max(10, (int) config('assessment.reports.pipeline.max_seconds', 40));
        $processed = 0;

        while ($processed < $limit && (microtime(true) - $started) < $maxSeconds) {
            if ($this->shouldStop($artifact->refresh())) {
                return;
            }

            $snapshot = $this->classSnapshots($artifact)
                ->whereIn('generation_status', ['pending', 'processing', 'failed'])
                ->orderBy('assessment_period_student_id')
                ->first();

            if (! $snapshot) {
                break;
            }

            $snapshot->forceFill([
                'generation_status' => 'processing',
                'error_message' => null,
            ])->save();

            $stored = $storage->putAtomically(
                $storage->individualPath($snapshot),
                $renderer->renderStudent($snapshot),
            );

            if ($this->shouldStop($artifact->refresh())) {
                $storage->disk()->delete($stored['path']);

                return;
            }

            $snapshot->forceFill([
                'generation_status' => 'completed',
                'pdf_path' => $stored['path'],
                'checksum' => $stored['checksum'],
                'error_message' => null,
                'generated_at' => Carbon::now(),
            ])->save();
            $processed++;
        }

        $remaining = $this->classSnapshots($artifact)
            ->where('generation_status', '!=', 'completed')
            ->count();

        if ($remaining > 0) {
            $this->refreshRunProgress($artifact);
            self::dispatch($artifact->getKey())->delay(now()->addSeconds(5));

            return;
        }

        if ($this->shouldStop($artifact->refresh())) {
            return;
        }

        $template = ReportTemplate::query()->findOrFail($artifact->assessment_report_template_id);
        $snapshots = $this->classSnapshots($artifact)
            ->with('student')
            ->get()
            ->sortBy(fn (ReportSnapshot $snapshot): string => mb_strtolower((string) $snapshot->student?->student_name_snapshot))
            ->values();

        if ($snapshots->isEmpty()) {
            throw new RuntimeException('Snapshot kelas tidak ditemukan.');
        }

        $stored = $storage->putAtomically(
            $storage->classPath($artifact),
            $renderer->renderClass($snapshots, $template),
        );

        if ($this->shouldStop($artifact->refresh())) {
            $storage->disk()->delete($stored['path']);

            return;
        }

        $artifact->forceFill([
            'generation_status' => 'completed',
            'pdf_path' => $stored['path'],
            'checksum' => $stored['checksum'],
            'error_message' => null,
            'generated_at' => Carbon::now(),
        ])->save();

        AuditLog::query()->create([
            'assessment_period_id' => $artifact->assessment_period_id,
            'actor_id' => $artifact->generated_by,
            'event' => 'class_report_pipeline_completed',
            'subject_type' => ClassReportArtifact::class,
            'subject_id' => $artifact->getKey(),
            'old_values' => null,
            'new_values' => [
                'revision' => $artifact->revision,
                'student_count' => $snapshots->count(),
                'checksum' => $artifact->checksum,
            ],
            'reason' => null,
            'ip_address' => null,
            'user_agent' => null,
            'created_at' => Carbon::now(),
        ]);

        $this->refreshRunProgress($artifact);
    }

    public function failed(?Throwable $exception): void
    {
        $artifact = ClassReportArtifact::query()->with('generationRun')->find($this->classReportArtifactId);
        if (! $artifact || $this->shouldStop($artifact)) {
            return;
        }

        $message = mb_substr((string) ($exception?->getMessage() ?: 'Pipeline PDF gagal tanpa pesan error.'), 0, 1000);
        $artifact->forceFill(['generation_status' => 'failed', 'error_message' => $message])->save();
        $artifact->generationRun?->forceFill(['status' => 'failed'])->save();
    }

    private function classSnapshots(ClassReportArtifact $artifact)
    {
        return ReportSnapshot::query()
            ->where('assessment_period_id', $artifact->assessment_period_id)
            ->where('assessment_report_template_id', $artifact->assessment_report_template_id)
            ->where('revision', $artifact->revision)
            ->whereHas('student', fn ($students) => $students
                ->where('assessment_period_rombel_id', $artifact->assessment_period_rombel_id)
                ->where('is_active', true));
    }

    private function shouldStop(ClassReportArtifact $artifact): bool
    {
        $artifactStatus = $artifact->generation_status instanceof \BackedEnum
            ? $artifact->generation_status->value
            : (string) $artifact->generation_status;
        $runStatus = $artifact->generationRun?->status;
        $runStatus = $runStatus instanceof \BackedEnum ? $runStatus->value : (string) $runStatus;

        return $artifactStatus === 'cancelled'
            || $runStatus === 'cancelled'
            || $artifact->generationRun?->cancel_requested_at !== null;
    }

    private function refreshRunProgress(ClassReportArtifact $artifact): void
    {
        $run = ReportGenerationRun::query()->find($artifact->assessment_report_generation_run_id);
        if (! $run) {
            return;
        }

        $completedStudents = $run->snapshots()->where('generation_status', 'completed')->count();
        $completedClasses = $run->classArtifacts()->where('generation_status', 'completed')->count();
        $allCompleted = $completedStudents === (int) $run->total_students
            && $completedClasses === (int) $run->total_classes;

        $run->forceFill([
            'completed_students' => $completedStudents,
            'completed_classes' => $completedClasses,
            'status' => $allCompleted ? 'completed' : 'running',
            'completed_at' => $allCompleted ? Carbon::now() : null,
        ])->save();
    }

    private function isCompletedAndValid(
        ClassReportArtifact $artifact,
        AssessmentReportStorage $storage,
    ): bool {
        $status = $artifact->generation_status instanceof \BackedEnum
            ? $artifact->generation_status->value
            : (string) $artifact->generation_status;

        return $status === 'completed' && $storage->isValid($artifact->pdf_path, $artifact->checksum);
    }
}
