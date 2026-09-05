<?php

namespace App\Http\Controllers;

use App\Models\Assessment\AuditLog;
use App\Models\Assessment\ClassReportArtifact;
use App\Models\Assessment\AssessmentPeriod;
use App\Models\Assessment\AssessmentPeriodRombel;
use App\Models\Assessment\AssessmentPeriodStudent;
use App\Models\Assessment\ReportSnapshot;
use App\Models\Assessment\ReportTemplate;
use App\Exceptions\AssessmentReportRenderBusy;
use App\Support\Assessment\Reporting\AssessmentReportRenderer;
use App\Support\Assessment\Reporting\AssessmentReportRenderGate;
use App\Support\Assessment\Reporting\AssessmentReportShareService;
use App\Support\Assessment\Reporting\AssessmentReportStorage;
use App\Support\Assessment\Reporting\AssessmentReportWatermark;
use App\Support\Assessment\Reporting\AssessmentSnapshotIntegrity;
use App\Support\Assessment\Reporting\BuildAssessmentReportPreviewSnapshot;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class AssessmentReportController extends Controller
{
    public function livePreview(
        AssessmentPeriod $assessmentPeriod,
        ReportTemplate $reportTemplate,
        AssessmentPeriodStudent $periodStudent,
        AssessmentReportRenderer $renderer,
        AssessmentReportRenderGate $renderGate,
        BuildAssessmentReportPreviewSnapshot $builder,
    ): Response {
        $this->abortUnlessEnabled();
        Gate::authorize('view', $assessmentPeriod);
        Gate::authorize('view', $reportTemplate);
        Gate::authorize('view', $periodStudent);
        abort_unless((int) $periodStudent->assessment_period_id === (int) $assessmentPeriod->getKey(), 404);

        $periodType = $assessmentPeriod->type instanceof \BackedEnum
            ? $assessmentPeriod->type->value
            : (string) $assessmentPeriod->type;
        $templateType = $reportTemplate->type instanceof \BackedEnum
            ? $reportTemplate->type->value
            : (string) $reportTemplate->type;
        abort_unless($periodType === $templateType, 422);

        $preview = $builder->build($assessmentPeriod, $reportTemplate, $periodStudent);

        try {
            $contents = $renderGate->run(fn (): string => $renderer->renderStudent($preview));
        } catch (AssessmentReportRenderBusy $exception) {
            return $this->busyResponse($exception);
        }

        return response($contents, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="pratinjau-rapor.pdf"',
            'Cache-Control' => 'private, no-store, max-age=0, must-revalidate',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
            'X-Robots-Tag' => 'noindex, nofollow, noarchive',
        ]);
    }

    public function preview(
        ReportSnapshot $reportSnapshot,
        AssessmentReportRenderer $renderer,
        AssessmentReportRenderGate $renderGate,
        AssessmentReportWatermark $watermark,
    ): Response {
        $this->abortUnlessEnabled();
        Gate::authorize('view', $reportSnapshot);
        $reportSnapshot->loadMissing('template');
        $template = $reportSnapshot->template;
        abort_unless($template, 404);

        $snapshotData = is_array($reportSnapshot->snapshot_data) ? $reportSnapshot->snapshot_data : [];
        data_set(
            $snapshotData,
            'template.settings',
            $watermark->freezeSettings(is_array($template->settings) ? $template->settings : []),
        );
        data_set($snapshotData, 'meta.preview', true);

        $preview = new ReportSnapshot([
            'assessment_period_id' => $reportSnapshot->assessment_period_id,
            'assessment_period_student_id' => $reportSnapshot->assessment_period_student_id,
            'assessment_report_template_id' => $reportSnapshot->assessment_report_template_id,
            'assessment_report_generation_run_id' => $reportSnapshot->assessment_report_generation_run_id,
            'revision' => $reportSnapshot->revision,
            'template_version' => $template->version,
            'snapshot_data' => $snapshotData,
        ]);

        try {
            $contents = $renderGate->run(fn (): string => $renderer->renderStudent($preview));
        } catch (AssessmentReportRenderBusy $exception) {
            return $this->busyResponse($exception);
        }

        return response($contents, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="pratinjau-rapor.pdf"',
            'Cache-Control' => 'private, no-store, max-age=0, must-revalidate',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
            'X-Robots-Tag' => 'noindex, nofollow, noarchive',
        ]);
    }

    public function downloadSnapshot(
        ReportSnapshot $reportSnapshot,
        AssessmentReportStorage $storage,
        AssessmentReportRenderer $renderer,
        AssessmentReportRenderGate $renderGate,
        AssessmentSnapshotIntegrity $integrity,
    ): Response|StreamedResponse {
        $this->abortUnlessEnabled();
        Gate::authorize('view', $reportSnapshot);
        $this->abortUnlessValid($reportSnapshot, $storage, $integrity);

        if ((string) $reportSnapshot->delivery_mode === 'stream') {
            try {
                $contents = $renderGate->run(fn (): string => $renderer->renderStudent($reportSnapshot));
            } catch (AssessmentReportRenderBusy $exception) {
                return $this->busyResponse($exception);
            }

            $this->auditPrivateDownload($reportSnapshot, request());

            return response($contents, 200, $this->downloadHeaders() + [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="'.basename($storage->individualPath($reportSnapshot)).'"',
            ]);
        }

        $this->auditPrivateDownload($reportSnapshot, request());

        return $storage->disk()->download(
            $reportSnapshot->pdf_path,
            $storage->downloadName($reportSnapshot),
            $this->downloadHeaders(),
        );
    }

    public function downloadClass(
        ClassReportArtifact $classReportArtifact,
        AssessmentReportStorage $storage,
    ): StreamedResponse {
        $this->abortUnlessEnabled();
        Gate::authorize('view', $classReportArtifact);
        abort_unless(
            $classReportArtifact->cache_expires_at?->isFuture()
                && $storage->isValid($classReportArtifact->pdf_path, $classReportArtifact->checksum),
            410,
            'Cache PDF kelas sudah kedaluwarsa. Silakan jadwalkan ulang kelas ini.',
        );
        $this->auditPrivateDownload($classReportArtifact, request());

        return $storage->disk()->download(
            $classReportArtifact->pdf_path,
            $storage->downloadName($classReportArtifact),
            $this->downloadHeaders(),
        );
    }

    public function downloadClassZip(
        AssessmentPeriod $assessmentPeriod,
        ReportTemplate $reportTemplate,
        AssessmentPeriodRombel $periodRombel,
        AssessmentReportRenderer $renderer,
        AssessmentReportRenderGate $renderGate,
        BuildAssessmentReportPreviewSnapshot $builder,
    ): BinaryFileResponse|Response {
        $this->abortUnlessEnabled();
        Gate::authorize('view', $assessmentPeriod);
        Gate::authorize('view', $reportTemplate);
        Gate::authorize('view', $periodRombel);
        abort_unless((int) $periodRombel->assessment_period_id === (int) $assessmentPeriod->getKey(), 404);
        $this->abortUnlessMatchingTemplate($assessmentPeriod, $reportTemplate);

        $students = AssessmentPeriodStudent::query()
            ->where('assessment_period_id', $assessmentPeriod->getKey())
            ->where('assessment_period_rombel_id', $periodRombel->getKey())
            ->where('is_active', true)
            ->orderBy('student_name_snapshot')
            ->get();
        abort_if($students->isEmpty(), 404, 'Tidak ada siswa aktif pada kelas ini.');

        foreach ($students as $student) {
            Gate::authorize('view', $student);
        }

        $snapshots = ReportSnapshot::query()
            ->where('assessment_period_id', $assessmentPeriod->getKey())
            ->where('assessment_report_template_id', $reportTemplate->getKey())
            ->whereIn('assessment_period_student_id', $students->modelKeys())
            ->orderByDesc('revision')
            ->orderByDesc('id')
            ->get()
            ->unique('assessment_period_student_id')
            ->keyBy('assessment_period_student_id');

        $zipPath = tempnam(storage_path('app'), 'rapor-kelas-');
        abort_if($zipPath === false, Response::HTTP_INTERNAL_SERVER_ERROR, 'Tidak bisa membuat file ZIP sementara.');

        try {
            $renderGate->run(function () use ($zipPath, $students, $snapshots, $assessmentPeriod, $reportTemplate, $builder, $renderer): void {
                abort_unless(class_exists(ZipArchive::class), Response::HTTP_INTERNAL_SERVER_ERROR, 'Ekstensi ZIP PHP belum aktif.');

                $zip = new ZipArchive();
                abort_if($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true, Response::HTTP_INTERNAL_SERVER_ERROR, 'Tidak bisa membuat file ZIP rapor.');

                try {
                    $usedNames = [];
                    foreach ($students as $student) {
                        $snapshot = $snapshots->get($student->getKey())
                            ?? $builder->build($assessmentPeriod, $reportTemplate, $student);
                        $filename = $this->uniqueZipEntryName((string) $student->student_name_snapshot, $usedNames);
                        $zip->addFromString($filename, $renderer->renderStudent($snapshot));
                    }
                } finally {
                    $zip->close();
                }
            });
        } catch (AssessmentReportRenderBusy $exception) {
            @unlink($zipPath);

            return $this->busyResponse($exception);
        } catch (\Throwable $exception) {
            @unlink($zipPath);

            throw $exception;
        }

        return response()->download($zipPath, $this->classZipFilename($assessmentPeriod, $periodRombel), [
            'Content-Type' => 'application/zip',
            ...$this->downloadHeaders(),
        ])->deleteFileAfterSend(true);
    }

    public function downloadShared(
        Request $request,
        string $token,
        AssessmentReportShareService $shares,
        AssessmentReportStorage $storage,
        AssessmentReportRenderer $renderer,
        AssessmentReportRenderGate $renderGate,
    ): Response|StreamedResponse {
        $this->abortUnlessEnabled();
        $link = $shares->resolve($token);
        $snapshot = $link->getRelation('snapshot');

        if ((string) $snapshot->delivery_mode === 'stream') {
            try {
                $contents = $renderGate->run(fn (): string => $renderer->renderStudent($snapshot));
            } catch (AssessmentReportRenderBusy $exception) {
                return $this->busyResponse($exception);
            }

            $shares->recordDownload($link, $request->ip(), $request->userAgent());

            return response($contents, 200, $this->downloadHeaders() + [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="'.basename($storage->individualPath($snapshot)).'"',
            ]);
        }

        $link = $shares->recordDownload($link, $request->ip(), $request->userAgent());
        $snapshot = $link->getRelation('snapshot');

        return $storage->disk()->download(
            $snapshot->pdf_path,
            $storage->downloadName($snapshot),
            $this->downloadHeaders(),
        );
    }

    private function abortUnlessEnabled(): void
    {
        abort_unless((bool) config('assessment.enabled', false), 404);
    }

    private function abortUnlessMatchingTemplate(AssessmentPeriod $period, ReportTemplate $template): void
    {
        $periodType = $period->type instanceof \BackedEnum ? $period->type->value : (string) $period->type;
        $templateType = $template->type instanceof \BackedEnum ? $template->type->value : (string) $template->type;

        abort_unless($periodType === $templateType, 422);
    }

    /** @param array<string, true> $usedNames */
    private function uniqueZipEntryName(string $studentName, array &$usedNames): string
    {
        $base = Str::slug($studentName) ?: 'siswa';
        $filename = $base.'.pdf';
        $suffix = 2;

        while (isset($usedNames[$filename])) {
            $filename = $base.'-'.$suffix.'.pdf';
            $suffix++;
        }

        $usedNames[$filename] = true;

        return $filename;
    }

    private function classZipFilename(AssessmentPeriod $period, AssessmentPeriodRombel $rombel): string
    {
        $type = $period->type instanceof \BackedEnum ? $period->type->value : (string) $period->type;

        return sprintf(
            'rapor-%s-%s-%s.zip',
            Str::slug($type) ?: 'penilaian',
            Str::slug((string) $period->code) ?: 'periode',
            Str::slug((string) $rombel->rombel_name_snapshot) ?: 'kelas',
        );
    }

    private function abortUnlessValid(
        ReportSnapshot|ClassReportArtifact $report,
        AssessmentReportStorage $storage,
        AssessmentSnapshotIntegrity $integrity,
    ): void {
        $status = $report->generation_status;
        $status = $status instanceof \BackedEnum ? $status->value : (string) $status;

        $valid = $report instanceof ReportSnapshot && (string) $report->delivery_mode === 'stream'
            ? $status === 'ready' && $integrity->isValid($report)
            : $status === 'completed' && $storage->isValid($report->pdf_path, $report->checksum);

        abort_unless($valid, 404, 'Rapor belum tersedia atau data snapshot tidak valid.');
    }

    private function auditPrivateDownload(
        ReportSnapshot|ClassReportArtifact $report,
        Request $request,
    ): void {
        AuditLog::query()->create([
            'assessment_period_id' => $report->assessment_period_id,
            'actor_id' => $request->user()?->getAuthIdentifier(),
            'event' => $report instanceof ReportSnapshot
                ? 'student_report_pdf_downloaded'
                : 'class_report_pdf_downloaded',
            'subject_type' => $report::class,
            'subject_id' => $report->getKey(),
            'old_values' => null,
            'new_values' => ['checksum' => $report->checksum],
            'reason' => null,
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
            'created_at' => Carbon::now(),
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function downloadHeaders(): array
    {
        return [
            'Cache-Control' => 'private, no-store, max-age=0, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
            'X-Content-Type-Options' => 'nosniff',
            'X-Robots-Tag' => 'noindex, nofollow, noarchive',
        ];
    }

    private function busyResponse(AssessmentReportRenderBusy $exception): Response
    {
        return response()->view('assessment.reports.busy', [
            'retryAfterSeconds' => $exception->retryAfterSeconds,
        ], 429, [
            'Retry-After' => (string) $exception->retryAfterSeconds,
            'Cache-Control' => 'private, no-store, max-age=0, must-revalidate',
            'X-Robots-Tag' => 'noindex, nofollow, noarchive',
        ]);
    }
}
