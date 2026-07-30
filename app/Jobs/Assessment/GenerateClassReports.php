<?php

namespace App\Jobs\Assessment;

use App\Models\Assessment\AssessmentPeriodStudent;
use App\Models\Assessment\AuditLog;
use App\Models\Assessment\ClassReportArtifact;
use App\Models\Assessment\ReportSnapshot;
use App\Models\Assessment\ReportTemplate;
use App\Support\Assessment\Reporting\AssessmentReportRenderer;
use App\Support\Assessment\Reporting\AssessmentReportStorage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class GenerateClassReports implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    // Harus lebih kecil daripada DB_QUEUE_RETRY_AFTER=180 agar job lama tidak
    // tersedia kembali ketika proses yang sama masih berjalan.
    public int $timeout = 150;

    /** @var array<int, int> */
    public array $backoff = [60, 180, 600];

    public function __construct(public readonly int $classReportArtifactId)
    {
        $this->onQueue((string) config('assessment.reports.queue', 'assessment-reports'));
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('assessment-class-report-'.$this->classReportArtifactId))
                ->releaseAfter(30)
                ->expireAfter(170)
                ->shared(),
        ];
    }

    public function handle(
        AssessmentReportRenderer $renderer,
        AssessmentReportStorage $storage,
    ): void {
        $artifact = ClassReportArtifact::query()
            ->with('periodRombel')
            ->find($this->classReportArtifactId);

        if (! $artifact) {
            return;
        }

        if ($this->isCompletedAndValid($artifact, $storage)) {
            return;
        }

        DB::transaction(function () use (&$artifact, $storage): void {
            $artifact = ClassReportArtifact::query()
                ->with('periodRombel')
                ->lockForUpdate()
                ->find($this->classReportArtifactId);

            if (! $artifact || $this->isCompletedAndValid($artifact, $storage)) {
                return;
            }

            $artifact->forceFill([
                'generation_status' => 'processing',
                'started_at' => Carbon::now(),
                'error_message' => null,
            ])->save();
        }, 3);

        if (! $artifact || $this->isCompletedAndValid($artifact, $storage)) {
            return;
        }

        $template = ReportTemplate::query()->findOrFail($artifact->assessment_report_template_id);
        $snapshots = ReportSnapshot::query()
            ->where('assessment_period_id', $artifact->assessment_period_id)
            ->where('assessment_report_template_id', $artifact->assessment_report_template_id)
            ->where('revision', $artifact->revision)
            ->whereHas(
                'student',
                fn ($query) => $query
                    ->where('assessment_period_rombel_id', $artifact->assessment_period_rombel_id)
                    ->where('is_active', true),
            )
            ->with('student')
            ->get()
            ->sortBy(fn (ReportSnapshot $snapshot): string => mb_strtolower(
                (string) $snapshot->student?->student_name_snapshot,
            ))
            ->values();

        $expected = AssessmentPeriodStudent::query()
            ->where('assessment_period_id', $artifact->assessment_period_id)
            ->where('assessment_period_rombel_id', $artifact->assessment_period_rombel_id)
            ->where('is_active', true)
            ->count();

        if ($expected < 1 || $snapshots->count() !== $expected) {
            throw new RuntimeException(sprintf(
                'Snapshot kelas belum lengkap: %d dari %d siswa.',
                $snapshots->count(),
                $expected,
            ));
        }

        $stored = $storage->putAtomically(
            $storage->classPath($artifact),
            $renderer->renderClass($snapshots, $template),
        );

        DB::transaction(function () use ($stored): void {
            $artifact = ClassReportArtifact::query()->lockForUpdate()->find($this->classReportArtifactId);

            if (! $artifact) {
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
                'event' => 'class_report_pdf_generated',
                'subject_type' => ClassReportArtifact::class,
                'subject_id' => $artifact->getKey(),
                'old_values' => null,
                'new_values' => [
                    'revision' => $artifact->revision,
                    'checksum' => $artifact->checksum,
                    'pdf_path' => $artifact->pdf_path,
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
        $artifact = ClassReportArtifact::query()->find($this->classReportArtifactId);

        if (! $artifact) {
            return;
        }

        $status = $artifact->generation_status;
        $status = $status instanceof \BackedEnum ? $status->value : (string) $status;

        if ($status === 'completed') {
            return;
        }

        $message = mb_substr(
            (string) ($exception?->getMessage() ?: 'Pembuatan PDF kelas gagal tanpa pesan error.'),
            0,
            1000,
        );

        $artifact->forceFill([
            'generation_status' => 'failed',
            'error_message' => $message,
        ])->save();

        AuditLog::query()->create([
            'assessment_period_id' => $artifact->assessment_period_id,
            'actor_id' => $artifact->generated_by,
            'event' => 'class_report_pdf_failed',
            'subject_type' => ClassReportArtifact::class,
            'subject_id' => $artifact->getKey(),
            'old_values' => null,
            'new_values' => ['error_message' => $message],
            'reason' => null,
            'ip_address' => null,
            'user_agent' => null,
            'created_at' => Carbon::now(),
        ]);
    }

    private function isCompletedAndValid(
        ClassReportArtifact $artifact,
        AssessmentReportStorage $storage,
    ): bool {
        $status = $artifact->generation_status;
        $status = $status instanceof \BackedEnum ? $status->value : (string) $status;

        return $status === 'completed'
            && $storage->isValid($artifact->pdf_path, $artifact->checksum);
    }
}
